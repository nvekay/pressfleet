import { Injectable, Logger, UnauthorizedException } from '@nestjs/common';
import { signRequest } from '@pressfleet/shared';
import { desc, eq } from 'drizzle-orm';
import { safeEqual } from '../common/internal-key.guard.js';
import { newId } from '../common/ids.js';
import { env } from '../config/env.js';
import { type Db, InjectDb } from '../db/db.module.js';
import { leads, sites } from '../db/schema.js';
import { SitesService } from '../sites/sites.service.js';
import { notifyTelegram } from './telegram.js';

export interface IncomingLead {
  id: number;
  name: string;
  contact: string;
  message: string;
  page: string;
  created_at: string;
}

const MAX_SKEW = 300;
const trimSlash = (url: string) => url.replace(/\/+$/, '');

@Injectable()
export class LeadsService {
  private readonly logger = new Logger(LeadsService.name);

  constructor(
    @InjectDb() private readonly db: Db,
    private readonly sites: SitesService,
  ) {}

  /**
   * Checks that a lead comes from a registered site: the site signs it with the same secret the hub uses to
   * publish there. Returns the site.
   */
  async verify(headers: { site?: string; timestamp?: string; signature?: string }, rawBody: string) {
    const site = (await this.sites.list()).find((s) => trimSlash(s.url) === trimSlash(headers.site ?? ''));
    const timestamp = Number(headers.timestamp);
    if (!site || !timestamp || Math.abs(Date.now() / 1000 - timestamp) > MAX_SKEW) throw new UnauthorizedException();
    const { secret } = this.sites.connection(site);
    const expected = signRequest(secret, timestamp, 'POST', env().LEADS_PUBLIC_PATH, rawBody);
    if (!safeEqual(expected, headers.signature ?? '')) throw new UnauthorizedException();
    return site;
  }

  /** Stores the lead once (a resend from the site is a no-op) and notifies Telegram the first time */
  async receive(siteId: string, siteName: string, lead: IncomingLead): Promise<{ duplicate: boolean }> {
    const [stored] = await this.db
      .insert(leads)
      .values({
        id: newId('lead'),
        siteId,
        wpLeadId: lead.id,
        name: lead.name,
        contact: lead.contact,
        message: lead.message,
        page: lead.page,
        submittedAt: new Date(lead.created_at),
      })
      .onConflictDoNothing({ target: [leads.siteId, leads.wpLeadId] })
      .returning();
    if (!stored) return { duplicate: true };

    // The site gets its answer now; a Telegram outage is recorded on the lead, not sent back
    void this.notify(stored.id, siteName, lead);
    return { duplicate: false };
  }

  private async notify(id: string, site: string, lead: IncomingLead): Promise<void> {
    try {
      if (await notifyTelegram({ site, ...lead })) {
        await this.db.update(leads).set({ notifiedAt: new Date() }).where(eq(leads.id, id));
      }
    } catch (err) {
      this.logger.warn(`Telegram: ${(err as Error).message}`);
      await this.db
        .update(leads)
        .set({ notifyError: (err as Error).message })
        .where(eq(leads.id, id));
    }
  }

  list() {
    return this.db
      .select({
        id: leads.id,
        site: sites.name,
        siteUrl: sites.url,
        name: leads.name,
        contact: leads.contact,
        message: leads.message,
        page: leads.page,
        submittedAt: leads.submittedAt,
        receivedAt: leads.receivedAt,
        notifiedAt: leads.notifiedAt,
        notifyError: leads.notifyError,
      })
      .from(leads)
      .innerJoin(sites, eq(sites.id, leads.siteId))
      .orderBy(desc(leads.receivedAt))
      .limit(100);
  }
}
