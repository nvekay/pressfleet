import type { CampaignStatus, DeliveryStatus, PublishResult, SiteHealth } from '@pressfleet/shared';

export interface Site {
  id: string;
  name: string;
  slug: string;
  url: string;
  username: string;
  health: SiteHealth | null;
  healthStatus: 'ok' | 'error' | 'unknown';
  healthError: string | null;
  checkedAt: string | null;
  createdAt: string;
}

export type Counts = Record<DeliveryStatus, number> & { total: number };

export interface Campaign {
  id: string;
  name: string;
  source: 'csv' | 'sheet' | 'sample';
  sourceRef: string | null;
  status: CampaignStatus;
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
}

export interface CampaignSummary extends Campaign {
  counts: Counts;
}

export interface Delivery {
  id: string;
  campaignId: string;
  itemId: string;
  siteId: string;
  status: DeliveryStatus;
  attempts: number;
  action: PublishResult['action'] | null;
  wpPostId: number | null;
  link: string | null;
  lastError: string | null;
  warnings: string[] | null;
  queuedAt: string;
  startedAt: string | null;
  finishedAt: string | null;
  updatedAt: string;
}

export interface CampaignDetail {
  campaign: Campaign;
  counts: Counts;
  timing: { seconds: number; postsPerMinute: number };
  sites: Site[];
  items: { id: string; row: number; externalId: string; title: string }[];
  deliveries: Delivery[];
}

export interface ImportPreview {
  rows: {
    row: number;
    externalId: string;
    title: string;
    categories: string[];
    date: string | null;
    image: string | null;
    sites: string[] | null;
  }[];
  problems: { row: number; message: string }[];
  unknownColumns: string[];
}

export interface Lead {
  id: string;
  site: string;
  siteUrl: string;
  name: string;
  contact: string;
  message: string;
  page: string;
  submittedAt: string;
  receivedAt: string;
  notifiedAt: string | null;
  notifyError: string | null;
}
