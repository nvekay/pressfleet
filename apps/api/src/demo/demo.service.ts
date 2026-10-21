import { Injectable, Logger, type OnApplicationBootstrap, type OnApplicationShutdown } from '@nestjs/common';
import { Queue, Worker } from 'bullmq';
import { lt } from 'drizzle-orm';
import { Redis } from 'ioredis';
import { env } from '../config/env.js';
import { type Db, InjectDb } from '../db/db.module.js';
import { CampaignsService } from '../campaigns/campaigns.service.js';
import { campaigns, leads } from '../db/schema.js';
import { SitesService } from '../sites/sites.service.js';

/**
 * The nightly reset of the public demo: every site deletes what the hub published there, the campaigns go, and the
 * sample is published again so the sites never stand empty. Leads are kept for a week.
 */
@Injectable()
export class DemoService implements OnApplicationBootstrap, OnApplicationShutdown {
  private readonly logger = new Logger(DemoService.name);
  private connection?: Redis;
  private queue?: Queue;
  private worker?: Worker;

  constructor(
    @InjectDb() private readonly db: Db,
    private readonly sites: SitesService,
    private readonly campaigns: CampaignsService,
  ) {}

  async onApplicationBootstrap() {
    const e = env();
    if (!e.DEMO_MODE) return;
    this.connection = new Redis(e.REDIS_URL, { maxRetriesPerRequest: null });
    this.queue = new Queue('maintenance', { connection: this.connection });
    this.worker = new Worker('maintenance', () => this.reset(), { connection: this.connection.duplicate() });
    await this.queue.upsertJobScheduler(
      'demo-reset',
      { pattern: e.DEMO_RESET_CRON, tz: 'UTC' },
      { name: 'demo-reset' },
    );
  }

  async onApplicationShutdown() {
    await this.worker?.close();
    await this.queue?.close();
    await this.connection?.quit();
  }

  async reset() {
    const results = await Promise.allSettled(
      (await this.sites.list()).map(async (site) => ({ site: site.name, ...(await this.sites.client(site).reset()) })),
    );
    for (const r of results) {
      if (r.status === 'rejected') this.logger.warn(`Demo reset of a site failed: ${String(r.reason)}`);
    }
    await this.db.delete(campaigns);
    await this.db.delete(leads).where(lt(leads.receivedAt, new Date(Date.now() - 7 * 24 * 3600_000)));
    const done = results.flatMap((r) => (r.status === 'fulfilled' ? [r.value] : []));
    this.logger.log(`Demo reset: ${JSON.stringify(done)}`);
    const siteIds = (await this.sites.list()).map((s) => s.id);
    if (siteIds.length) await this.campaigns.create({ source: 'sample', name: 'Nightly refresh', siteIds });
    return done;
  }
}
