import { Injectable, Logger, type OnApplicationBootstrap, type OnApplicationShutdown } from '@nestjs/common';
import { backoffDelay } from '@pressfleet/shared';
import { type Job, type JobsOptions, Queue, Worker } from 'bullmq';
import { Redis } from 'ioredis';
import { env } from '../config/env.js';
import { SitesService } from '../sites/sites.service.js';

export interface PublishJob {
  deliveryId: string;
}

export type PublishHandler = (job: Job<PublishJob>) => Promise<unknown>;

/**
 * One BullMQ queue and worker per site. Each site gets its own concurrency limit, and a site that is down only
 * delays its own posts: the other sites keep publishing at full speed.
 */
@Injectable()
export class PublishQueues implements OnApplicationBootstrap, OnApplicationShutdown {
  private readonly logger = new Logger(PublishQueues.name);
  private readonly connection = new Redis(env().REDIS_URL, { maxRetriesPerRequest: null });
  private readonly queues = new Map<string, Queue<PublishJob>>();
  private readonly workers = new Map<string, Worker<PublishJob>>();
  private handler: PublishHandler | undefined;

  constructor(private readonly sites: SitesService) {}

  /** The job processor (DeliveryRunner) registers itself; keeps this class free of an import cycle */
  setHandler(handler: PublishHandler): void {
    this.handler = handler;
  }

  /** Workers start once every provider is ready, so the handler is set */
  async onApplicationBootstrap() {
    for (const site of await this.sites.list()) await this.ensure(site.id);
  }

  async onApplicationShutdown() {
    await Promise.all([...this.workers.values()].map((w) => w.close()));
    await Promise.all([...this.queues.values()].map((q) => q.close()));
    await this.connection.quit();
  }

  /** Starts the queue and worker of a site (idempotent) */
  async ensure(siteId: string): Promise<Queue<PublishJob>> {
    const existing = this.queues.get(siteId);
    if (existing) return existing;

    const e = env();
    const name = `publish-${siteId}`;
    const queue = new Queue<PublishJob>(name, { connection: this.connection });
    const worker = new Worker<PublishJob>(
      name,
      (job) => {
        if (!this.handler) throw new Error('No publish handler registered');
        return this.handler(job);
      },
      {
        connection: this.connection.duplicate(),
        concurrency: e.PUBLISH_CONCURRENCY_PER_SITE,
        settings: {
          backoffStrategy: (attemptsMade: number) => backoffDelay(attemptsMade, e.RETRY_DELAY_MS, e.RETRY_MAX_DELAY_MS),
        },
      },
    );
    worker.on('error', (err) => this.logger.error(`Worker ${name}: ${err.message}`));
    this.queues.set(siteId, queue);
    this.workers.set(siteId, worker);
    return queue;
  }

  /** Queues a delivery; the job ID is the delivery ID, so the same delivery is never queued twice */
  async enqueue(delivery: { id: string; siteId: string }): Promise<void> {
    const queue = await this.ensure(delivery.siteId);
    // A finished job with the same ID would block a new one (a manual retry of a failed delivery)
    const previous = await queue.getJob(delivery.id);
    if (previous && ((await previous.isFailed()) || (await previous.isCompleted()))) await previous.remove();
    await queue.add('publish', { deliveryId: delivery.id }, { ...jobOptions(), jobId: delivery.id });
  }

  /** Stops and deletes a removed site's queue */
  async drop(siteId: string): Promise<void> {
    await this.workers.get(siteId)?.close();
    await this.queues.get(siteId)?.obliterate({ force: true });
    await this.queues.get(siteId)?.close();
    this.workers.delete(siteId);
    this.queues.delete(siteId);
  }

  /** Waiting, active and delayed jobs per site, for the dashboard */
  async counts(): Promise<Record<string, Record<string, number>>> {
    const entries = await Promise.all(
      [...this.queues].map(
        async ([siteId, q]) => [siteId, await q.getJobCounts('active', 'waiting', 'delayed')] as const,
      ),
    );
    return Object.fromEntries(entries);
  }
}

export const jobOptions = (): JobsOptions => ({
  attempts: env().PUBLISH_ATTEMPTS,
  backoff: { type: 'custom' },
  removeOnComplete: { age: 24 * 3600 },
  removeOnFail: { age: 7 * 24 * 3600 },
});
