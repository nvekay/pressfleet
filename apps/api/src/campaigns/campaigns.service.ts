import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import type { CampaignStatus, DeliveryStatus, ImportRow } from '@pressfleet/shared';
import { and, asc, count, desc, eq, inArray, isNull } from 'drizzle-orm';
import { newId } from '../common/ids.js';
import { type Db, InjectDb } from '../db/db.module.js';
import { type Campaign, campaigns, deliveries, items, type Site, sites } from '../db/schema.js';
import { CampaignEvents } from '../publishing/campaign-events.js';
import { PublishQueues } from '../publishing/publish-queues.js';
import { publicSite } from '../sites/sites.service.js';
import { type ImportSource, readImport } from './import-source.js';

const OPEN: DeliveryStatus[] = ['queued', 'publishing', 'retrying'];

/** Does a row's "sites" column include this site (by name or slug, case-insensitive)? */
const targets = (row: ImportRow, site: Site) =>
  !row.sites || row.sites.some((s) => [site.name.toLowerCase(), site.slug].includes(s.toLowerCase()));

export type Counts = Record<DeliveryStatus, number> & { total: number };

@Injectable()
export class CampaignsService {
  constructor(
    @InjectDb() private readonly db: Db,
    private readonly events: CampaignEvents,
    private readonly queues: PublishQueues,
  ) {}

  /** Parses the import without creating anything, so the dashboard can show problems first */
  async preview(input: ImportSource) {
    const result = await readImport(input);
    return {
      ...result,
      rows: result.rows.map((r) => ({
        row: r.row,
        externalId: r.payload.external_id,
        title: r.payload.title,
        categories: r.payload.categories ?? [],
        date: r.payload.date ?? null,
        image: r.payload.featured_image?.url ?? null,
        sites: r.sites,
      })),
    };
  }

  /** Creates the campaign, one delivery per row and site, and queues them all */
  async create(input: ImportSource & { name: string; siteIds: string[] }): Promise<Campaign> {
    const result = await readImport(input);
    if (!result.rows.length) {
      throw new BadRequestException(result.problems[0]?.message ?? 'The import has no rows to publish');
    }
    const chosen = await this.db.select().from(sites).where(inArray(sites.id, input.siteIds));
    if (!chosen.length) throw new BadRequestException('Choose at least one site');

    const campaign = await this.db.transaction(async (tx) => {
      const [created] = await tx
        .insert(campaigns)
        .values({ id: newId('cmp'), name: input.name, source: input.source, sourceRef: result.sourceRef })
        .returning();
      const itemRows = result.rows.map((r) => ({
        id: newId('itm'),
        campaignId: created!.id,
        row: r.row,
        externalId: r.payload.external_id,
        title: r.payload.title,
        payload: r.payload,
      }));
      await tx.insert(items).values(itemRows);
      const deliveryRows = result.rows.flatMap((r, i) =>
        chosen
          .filter((site) => targets(r, site))
          .map((site) => ({ id: newId('dlv'), campaignId: created!.id, itemId: itemRows[i]!.id, siteId: site.id })),
      );
      if (!deliveryRows.length) throw new BadRequestException('No row targets the chosen sites');
      await tx.insert(deliveries).values(deliveryRows);
      return created!;
    });

    const queued = await this.db
      .select({ id: deliveries.id, siteId: deliveries.siteId })
      .from(deliveries)
      .where(eq(deliveries.campaignId, campaign.id));
    for (const d of queued) await this.queues.enqueue(d);
    return campaign;
  }

  async list() {
    const rows = await this.db.select().from(campaigns).orderBy(desc(campaigns.createdAt)).limit(50);
    const counts = await this.countsFor(rows.map((r) => r.id));
    return rows.map((c) => ({ ...c, counts: counts.get(c.id) ?? emptyCounts() }));
  }

  /** The campaign with its rows × sites matrix and timing */
  async get(id: string) {
    const [campaign] = await this.db.select().from(campaigns).where(eq(campaigns.id, id));
    if (!campaign) throw new NotFoundException('Campaign not found');
    const [rows, cells] = await Promise.all([
      this.db
        .select({ id: items.id, row: items.row, externalId: items.externalId, title: items.title })
        .from(items)
        .where(eq(items.campaignId, id))
        .orderBy(asc(items.row)),
      this.db.select().from(deliveries).where(eq(deliveries.campaignId, id)),
    ]);
    const siteIds = [...new Set(cells.map((c) => c.siteId))];
    const siteRows = siteIds.length ? await this.db.select().from(sites).where(inArray(sites.id, siteIds)) : [];
    const counts = (await this.countsFor([id])).get(id) ?? emptyCounts();
    return {
      campaign,
      counts,
      timing: timing(campaign, counts, cells),
      sites: siteRows.map(publicSite).sort((a, b) => a.name.localeCompare(b.name)),
      items: rows,
      deliveries: cells,
    };
  }

  /** Queues every failed delivery again with fresh attempts */
  async retryFailed(id: string): Promise<number> {
    const failed = await this.db
      .update(deliveries)
      .set({ status: 'queued', attempts: 0, lastError: null, finishedAt: null, updatedAt: new Date() })
      .where(and(eq(deliveries.campaignId, id), eq(deliveries.status, 'failed')))
      .returning();
    if (!failed.length) return 0;
    await this.db.update(campaigns).set({ status: 'running', finishedAt: null }).where(eq(campaigns.id, id));
    for (const d of failed) {
      this.events.emit({ campaignId: id, type: 'delivery', data: d });
      await this.queues.enqueue(d);
    }
    return failed.length;
  }

  /** Records when the first delivery of a campaign starts */
  async markStarted(id: string): Promise<void> {
    await this.db
      .update(campaigns)
      .set({ startedAt: new Date() })
      .where(and(eq(campaigns.id, id), isNull(campaigns.startedAt)));
  }

  /** Closes the campaign once nothing is queued, publishing or waiting for a retry */
  async refresh(id: string): Promise<void> {
    const counts = (await this.countsFor([id])).get(id);
    if (!counts || OPEN.some((s) => counts[s] > 0)) return;
    const status: CampaignStatus = counts.failed ? 'done_with_errors' : 'done';
    const [updated] = await this.db
      .update(campaigns)
      .set({ status, finishedAt: new Date() })
      .where(and(eq(campaigns.id, id), eq(campaigns.status, 'running')))
      .returning();
    if (updated) this.events.emit({ campaignId: id, type: 'campaign', data: { ...updated, counts } });
  }

  private async countsFor(ids: string[]): Promise<Map<string, Counts>> {
    if (!ids.length) return new Map();
    const rows = await this.db
      .select({ campaignId: deliveries.campaignId, status: deliveries.status, n: count() })
      .from(deliveries)
      .where(inArray(deliveries.campaignId, ids))
      .groupBy(deliveries.campaignId, deliveries.status);
    const out = new Map<string, Counts>();
    for (const r of rows) {
      const c = out.get(r.campaignId) ?? emptyCounts();
      c[r.status] = r.n;
      c.total += r.n;
      out.set(r.campaignId, c);
    }
    return out;
  }
}

export const emptyCounts = (): Counts => ({
  queued: 0,
  publishing: 0,
  retrying: 0,
  published: 0,
  failed: 0,
  total: 0,
});

/** Elapsed time and throughput: published posts per minute between the first start and the last finish */
export function timing(campaign: Campaign, counts: Counts, cells: { finishedAt: Date | null }[]) {
  if (!campaign.startedAt) return { seconds: 0, postsPerMinute: 0 };
  const lastFinish = cells.reduce<number>((max, c) => Math.max(max, c.finishedAt?.getTime() ?? 0), 0);
  const end = campaign.finishedAt?.getTime() ?? (campaign.status === 'running' ? Date.now() : lastFinish || Date.now());
  const seconds = Math.max(0.001, (end - campaign.startedAt.getTime()) / 1000);
  return {
    seconds: Math.round(seconds * 10) / 10,
    postsPerMinute: Math.round((counts.published / seconds) * 60 * 10) / 10,
  };
}
