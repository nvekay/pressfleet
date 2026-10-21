import 'server-only';
import { env } from './env';
import type { CampaignDetail, CampaignSummary, ImportPreview, Site } from './types';

export class ApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
  ) {
    super(message);
  }
}

export type ImportSource = { source: 'csv'; csv: string } | { source: 'sheet'; url: string };

async function call<T>(path: string, init: { method?: string; body?: unknown } = {}): Promise<T> {
  const { API_URL, API_INTERNAL_KEY } = env();
  const res = await fetch(`${API_URL}${path}`, {
    method: init.method ?? 'GET',
    headers: { 'x-api-key': API_INTERNAL_KEY, 'content-type': 'application/json' },
    body: init.body === undefined ? undefined : JSON.stringify(init.body),
    cache: 'no-store',
  });
  if (res.status === 204) return undefined as T;
  if (!res.ok) {
    const detail = (await res.json().catch(() => ({}))) as { message?: string | string[] };
    const message = Array.isArray(detail.message) ? detail.message.join('; ') : detail.message;
    throw new ApiError(res.status, message ?? `API ${res.status}`);
  }
  return (await res.json()) as T;
}

/** Calls the hub API from the dashboard's server; the internal key never reaches the browser */
export const api = {
  sites: () => call<Site[]>('/sites'),
  addSite: (site: { name?: string; url: string; username: string; applicationPassword: string; secret: string }) =>
    call<Site>('/sites', { method: 'POST', body: site }),
  checkSite: (id: string) => call<Site>(`/sites/${id}/check`, { method: 'POST' }),
  removeSite: (id: string) => call<void>(`/sites/${id}`, { method: 'DELETE' }),
  campaigns: () => call<CampaignSummary[]>('/campaigns'),
  campaign: (id: string) => call<CampaignDetail>(`/campaigns/${id}`),
  preview: (source: ImportSource) => call<ImportPreview>('/campaigns/preview', { method: 'POST', body: source }),
  createCampaign: (input: ImportSource & { name: string; siteIds: string[] }) =>
    call<{ id: string }>('/campaigns', { method: 'POST', body: input }),
  retryFailed: (id: string) => call<{ queued: number }>(`/campaigns/${id}/retry-failed`, { method: 'POST' }),
};
