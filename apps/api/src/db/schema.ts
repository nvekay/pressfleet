import type { CampaignStatus, DeliveryStatus, PostPayload, PublishResult, SiteHealth } from '@pressfleet/shared';
import { index, integer, jsonb, pgTable, text, timestamp, uniqueIndex } from 'drizzle-orm/pg-core';

const id = () => text('id').primaryKey();
const createdAt = () => timestamp('created_at', { withTimezone: true }).notNull().defaultNow();

/** A WordPress site with the connector plugin; credentials are encrypted (see SecretBox) */
export const sites = pgTable('sites', {
  id: id(),
  name: text('name').notNull(),
  slug: text('slug').notNull().unique(),
  url: text('url').notNull().unique(),
  username: text('username').notNull(),
  /** SecretBox-encrypted JSON: { applicationPassword, secret } */
  credentials: text('credentials').notNull(),
  health: jsonb('health').$type<SiteHealth>(),
  healthStatus: text('health_status').$type<'ok' | 'error' | 'unknown'>().notNull().default('unknown'),
  healthError: text('health_error'),
  checkedAt: timestamp('checked_at', { withTimezone: true }),
  createdAt: createdAt(),
});

/** One import (a CSV file or a Google Sheet) published to a set of sites */
export const campaigns = pgTable('campaigns', {
  id: id(),
  name: text('name').notNull(),
  source: text('source').$type<'csv' | 'sheet' | 'sample'>().notNull(),
  sourceRef: text('source_ref'),
  status: text('status').$type<CampaignStatus>().notNull().default('running'),
  createdAt: createdAt(),
  startedAt: timestamp('started_at', { withTimezone: true }),
  finishedAt: timestamp('finished_at', { withTimezone: true }),
});

/** A row of the import: the post to publish */
export const items = pgTable(
  'items',
  {
    id: id(),
    campaignId: text('campaign_id')
      .notNull()
      .references(() => campaigns.id, { onDelete: 'cascade' }),
    row: integer('row').notNull(),
    externalId: text('external_id').notNull(),
    title: text('title').notNull(),
    payload: jsonb('payload').$type<PostPayload>().notNull(),
  },
  (t) => [index('items_campaign_idx').on(t.campaignId, t.row)],
);

/** One item on one site: the unit of work the queue retries */
export const deliveries = pgTable(
  'deliveries',
  {
    id: id(),
    campaignId: text('campaign_id')
      .notNull()
      .references(() => campaigns.id, { onDelete: 'cascade' }),
    itemId: text('item_id')
      .notNull()
      .references(() => items.id, { onDelete: 'cascade' }),
    siteId: text('site_id')
      .notNull()
      .references(() => sites.id, { onDelete: 'cascade' }),
    status: text('status').$type<DeliveryStatus>().notNull().default('queued'),
    attempts: integer('attempts').notNull().default(0),
    action: text('action').$type<PublishResult['action']>(),
    wpPostId: integer('wp_post_id'),
    link: text('link'),
    lastError: text('last_error'),
    warnings: jsonb('warnings').$type<string[]>(),
    queuedAt: timestamp('queued_at', { withTimezone: true }).notNull().defaultNow(),
    startedAt: timestamp('started_at', { withTimezone: true }),
    finishedAt: timestamp('finished_at', { withTimezone: true }),
    updatedAt: timestamp('updated_at', { withTimezone: true }).notNull().defaultNow(),
  },
  (t) => [
    uniqueIndex('deliveries_item_site_idx').on(t.itemId, t.siteId),
    index('deliveries_campaign_idx').on(t.campaignId, t.status),
  ],
);

/** A request from a site's Lead form block; (siteId, wpLeadId) makes a resent lead a no-op */
export const leads = pgTable(
  'leads',
  {
    id: id(),
    siteId: text('site_id')
      .notNull()
      .references(() => sites.id, { onDelete: 'cascade' }),
    wpLeadId: integer('wp_lead_id').notNull(),
    name: text('name').notNull(),
    contact: text('contact').notNull(),
    message: text('message').notNull(),
    page: text('page').notNull(),
    submittedAt: timestamp('submitted_at', { withTimezone: true }).notNull(),
    receivedAt: timestamp('received_at', { withTimezone: true }).notNull().defaultNow(),
    notifiedAt: timestamp('notified_at', { withTimezone: true }),
    notifyError: text('notify_error'),
  },
  (t) => [uniqueIndex('leads_site_lead_idx').on(t.siteId, t.wpLeadId), index('leads_received_idx').on(t.receivedAt)],
);

export type Site = typeof sites.$inferSelect;
export type Campaign = typeof campaigns.$inferSelect;
export type Item = typeof items.$inferSelect;
export type Delivery = typeof deliveries.$inferSelect;
export type Lead = typeof leads.$inferSelect;
