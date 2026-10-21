import { Injectable, Logger, type OnModuleInit } from '@nestjs/common';
import type { PublishResult } from '@pressfleet/shared';
import { type Job, UnrecoverableError } from 'bullmq';
import { eq } from 'drizzle-orm';
import { CampaignsService } from '../campaigns/campaigns.service.js';
import { type Db, InjectDb } from '../db/db.module.js';
import { type Delivery, deliveries, items, sites } from '../db/schema.js';
import { SitesService } from '../sites/sites.service.js';
import { WpError } from '../wordpress/wp-client.js';
import { CampaignEvents } from './campaign-events.js';
import { PublishQueues, type PublishJob } from './publish-queues.js';

/**
 * Publishes one item to one site. The queue retries on errors that may pass (site down, timeout, 5xx, a lock
 * held by an earlier attempt); errors that cannot (bad credentials, rejected payload) fail at once.
 * The plugin upserts by external ID, so a retry after a timeout never creates a second post.
 */
@Injectable()
export class DeliveryRunner implements OnModuleInit {
  private readonly logger = new Logger(DeliveryRunner.name);

  constructor(
    @InjectDb() private readonly db: Db,
    private readonly sites: SitesService,
    private readonly campaigns: CampaignsService,
    private readonly events: CampaignEvents,
    private readonly queues: PublishQueues,
  ) {}

  onModuleInit() {
    this.queues.setHandler((job) => this.run(job));
  }

  async run(job: Job<PublishJob>): Promise<PublishResult | 'skipped'> {
    const [row] = await this.db
      .select({ delivery: deliveries, item: items, site: sites })
      .from(deliveries)
      .innerJoin(items, eq(items.id, deliveries.itemId))
      .innerJoin(sites, eq(sites.id, deliveries.siteId))
      .where(eq(deliveries.id, job.data.deliveryId));
    // Deleted with its campaign or site, or already done by an earlier job
    if (!row || row.delivery.status === 'published') return 'skipped';

    const { delivery, item, site } = row;
    const attempt = job.attemptsMade + 1;
    const lastAttempt = attempt >= (job.opts.attempts ?? 1);
    await this.campaigns.markStarted(delivery.campaignId);
    await this.update(delivery, {
      status: 'publishing',
      attempts: attempt,
      startedAt: delivery.startedAt ?? new Date(),
    });

    try {
      const result = await this.sites.client(site).publish(item.payload);
      await this.update(delivery, {
        status: 'published',
        action: result.action,
        wpPostId: result.id,
        link: result.link,
        warnings: result.warnings.length ? result.warnings : null,
        lastError: null,
        finishedAt: new Date(),
      });
      await this.campaigns.refresh(delivery.campaignId);
      return result;
    } catch (err) {
      const error = err instanceof WpError ? err : new WpError(String(err), null, true);
      const final = !error.retryable || lastAttempt;
      this.logger.warn(`${item.externalId} → ${site.name}, attempt ${attempt}: ${error.message}`);
      await this.update(delivery, {
        status: final ? 'failed' : 'retrying',
        lastError: error.message,
        ...(final ? { finishedAt: new Date() } : {}),
      });
      if (final) await this.campaigns.refresh(delivery.campaignId);
      if (!error.retryable) throw new UnrecoverableError(error.message);
      throw error;
    }
  }

  private async update(delivery: Delivery, patch: Partial<Delivery>): Promise<void> {
    const [updated] = await this.db
      .update(deliveries)
      .set({ ...patch, updatedAt: new Date() })
      .where(eq(deliveries.id, delivery.id))
      .returning();
    if (updated) this.events.emit({ campaignId: delivery.campaignId, type: 'delivery', data: updated });
  }
}
