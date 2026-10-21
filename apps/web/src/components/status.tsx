import type { CampaignStatus, DeliveryStatus } from '@pressfleet/shared';

export const DELIVERY_STYLE: Record<DeliveryStatus, { label: string; cell: string; dot: string }> = {
  queued: { label: 'Queued', cell: 'bg-ink-100 text-ink-500', dot: 'bg-ink-300' },
  publishing: { label: 'Publishing', cell: 'bg-sky-100 text-sky-800 animate-pulse', dot: 'bg-sky-500' },
  retrying: { label: 'Retrying', cell: 'bg-amber-100 text-amber-800', dot: 'bg-amber-500' },
  published: { label: 'Published', cell: 'bg-emerald-100 text-emerald-800', dot: 'bg-emerald-500' },
  failed: { label: 'Failed', cell: 'bg-red-100 text-red-800', dot: 'bg-red-500' },
};

const CAMPAIGN_STYLE: Record<CampaignStatus, { label: string; className: string }> = {
  running: { label: 'Running', className: 'bg-sky-50 text-sky-800' },
  done: { label: 'Done', className: 'bg-emerald-50 text-emerald-800' },
  done_with_errors: { label: 'Done with errors', className: 'bg-red-50 text-red-800' },
};

export function CampaignBadge({ status }: { status: CampaignStatus }) {
  const s = CAMPAIGN_STYLE[status];
  return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${s.className}`}>{s.label}</span>;
}

export function HealthDot({ status }: { status: 'ok' | 'error' | 'unknown' }) {
  const color = status === 'ok' ? 'bg-emerald-500' : status === 'error' ? 'bg-red-500' : 'bg-ink-300';
  return <span className={`inline-block size-2 shrink-0 rounded-full ${color}`} aria-label={status} />;
}
