/** The body of POST /wp-json/pressfleet/v1/posts, as the connector plugin validates it */
export interface PostPayload {
  external_id: string;
  title: string;
  content: string;
  excerpt?: string;
  slug?: string;
  status?: 'publish' | 'draft' | 'pending' | 'private';
  /** ISO 8601 in UTC; a future date schedules the post */
  date?: string;
  categories?: string[];
  tags?: string[];
  featured_image?: { url: string; alt?: string } | null;
  seo?: { title?: string; description?: string };
}

/** The plugin's answer to a publish request */
export interface PublishResult {
  id: number;
  external_id: string;
  action: 'created' | 'updated' | 'unchanged';
  status: string;
  link: string;
  warnings: string[];
}

/** GET /wp-json/pressfleet/v1/health */
export interface SiteHealth {
  site: string;
  url: string;
  plugin_version: string;
  wordpress: string;
  php: string;
  seo_plugin: 'yoast' | 'rankmath' | null;
  posts_from_hub: number;
  last_received_at: string | null;
  time: string;
}

/**
 * One post on one site. queued → publishing → published, or → retrying (and back to publishing) → failed when
 * the attempts run out or the site rejects the post for good.
 */
export const DELIVERY_STATUSES = ['queued', 'publishing', 'retrying', 'published', 'failed'] as const;
export type DeliveryStatus = (typeof DELIVERY_STATUSES)[number];

export const CAMPAIGN_STATUSES = ['running', 'done', 'done_with_errors'] as const;
export type CampaignStatus = (typeof CAMPAIGN_STATUSES)[number];
