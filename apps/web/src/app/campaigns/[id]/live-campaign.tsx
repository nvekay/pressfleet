'use client';

import { DELIVERY_STATUSES, type DeliveryStatus } from '@pressfleet/shared';
import { ArrowLeft, Check, ExternalLink, Equal, RefreshCw, RotateCcw, X } from 'lucide-react';
import Link from 'next/link';
import { useEffect, useMemo, useState, useTransition } from 'react';
import { CampaignBadge, DELIVERY_STYLE } from '@/components/status';
import { Button } from '@/components/ui/button';
import { dateTime, duration } from '@/lib/format';
import type { Campaign, CampaignDetail, Delivery } from '@/lib/types';
import { retryFailed } from '../../actions';

/** The rows × sites matrix, updated live from the hub's event stream */
export function LiveCampaign({ initial }: { initial: CampaignDetail }) {
  const [campaign, setCampaign] = useState<Campaign>(initial.campaign);
  const [cells, setCells] = useState(() => new Map(initial.deliveries.map((d) => [d.id, d])));
  const [now, setNow] = useState(() => Date.now());
  const [retrying, startRetry] = useTransition();
  const { items, sites } = initial;
  const running = campaign.status === 'running';

  // Live updates; after a dropped connection the page resyncs from the JSON route
  useEffect(() => {
    const id = campaign.id;
    const source = new EventSource(`/api/campaigns/${id}/events`);
    source.addEventListener('delivery', (e) => {
      const d = JSON.parse((e as MessageEvent<string>).data) as Delivery;
      setCells((prev) => new Map(prev).set(d.id, d));
    });
    source.addEventListener('campaign', (e) => {
      setCampaign((prev) => ({ ...prev, ...(JSON.parse((e as MessageEvent<string>).data) as Campaign) }));
    });
    const resync = async () => {
      const res = await fetch(`/api/campaigns/${id}`, { cache: 'no-store' }).catch(() => null);
      if (!res?.ok) return;
      const fresh = (await res.json()) as CampaignDetail;
      setCampaign(fresh.campaign);
      setCells(new Map(fresh.deliveries.map((d) => [d.id, d])));
    };
    source.onerror = () => void resync();
    const timer = setInterval(() => void resync(), 15_000);
    return () => {
      source.close();
      clearInterval(timer);
    };
  }, [campaign.id]);

  // Ticks the elapsed time while the campaign runs
  useEffect(() => {
    if (!running) return;
    const timer = setInterval(() => setNow(Date.now()), 500);
    return () => clearInterval(timer);
  }, [running]);

  const all = [...cells.values()];
  const counts = useMemo(() => {
    const c = Object.fromEntries(DELIVERY_STATUSES.map((s) => [s, 0])) as Record<DeliveryStatus, number>;
    for (const d of cells.values()) c[d.status] += 1;
    return c;
  }, [cells]);
  const byCell = useMemo(() => new Map(all.map((d) => [`${d.itemId}:${d.siteId}`, d])), [all]);
  const total = all.length;
  const started = campaign.startedAt ? new Date(campaign.startedAt).getTime() : null;
  const lastFinish = Math.max(0, ...all.map((d) => (d.finishedAt ? new Date(d.finishedAt).getTime() : 0)));
  const end = campaign.finishedAt ? new Date(campaign.finishedAt).getTime() : running ? now : lastFinish || now;
  const seconds = started ? Math.max(0.1, (end - started) / 1000) : 0;
  const perMinute = seconds ? Math.round((counts.published / seconds) * 600) / 10 : 0;
  const problems = all
    .filter((d) => (d.lastError && (d.status === 'failed' || d.status === 'retrying')) || d.warnings?.length)
    .sort((a, b) => b.updatedAt.localeCompare(a.updatedAt));
  const warned = all.filter((d) => d.status === 'published' && d.warnings?.length).length;
  const siteName = (id: string) => sites.find((s) => s.id === id)?.name ?? id;
  const itemTitle = (id: string) => items.find((i) => i.id === id)?.title ?? id;

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
      <Link href="/campaigns" className="inline-flex items-center gap-1.5 text-sm text-ink-500 hover:text-ink-900">
        <ArrowLeft className="size-4" /> Campaigns
      </Link>
      <div className="mt-3 flex flex-wrap items-center gap-3">
        <h1 className="text-2xl font-semibold">{campaign.name}</h1>
        <CampaignBadge status={campaign.status} />
      </div>
      <p className="mt-1 text-sm text-ink-500">
        {items.length} posts × {sites.length} sites · created {dateTime(campaign.createdAt)}
      </p>

      <section className="mt-6 grid gap-4 sm:grid-cols-4">
        <Stat label="Published" value={`${counts.published} / ${total}`} />
        <Stat label="Elapsed" value={started ? duration(seconds) : '—'} />
        <Stat label="Posts per minute" value={perMinute ? String(perMinute) : '—'} />
        <Stat
          label={warned ? `Retrying / failed · ${warned} warnings` : 'Retrying / failed'}
          value={`${counts.retrying} / ${counts.failed}`}
          tone={counts.failed ? 'bad' : counts.retrying ? 'warn' : undefined}
        />
      </section>

      <div className="mt-4 flex h-2 overflow-hidden rounded-full bg-ink-100">
        {(['published', 'publishing', 'retrying', 'failed'] as const).map((s) => (
          <div
            key={s}
            className={`${DELIVERY_STYLE[s].dot} transition-all`}
            style={{ width: `${total ? (counts[s] / total) * 100 : 0}%` }}
          />
        ))}
      </div>
      <div className="mt-2 flex flex-wrap gap-4 text-xs text-ink-500">
        {DELIVERY_STATUSES.map((s) => (
          <span key={s} className="inline-flex items-center gap-1.5">
            <span className={`size-2 rounded-full ${DELIVERY_STYLE[s].dot}`} /> {DELIVERY_STYLE[s].label} {counts[s]}
          </span>
        ))}
      </div>

      {counts.failed > 0 && !running && (
        <Button className="mt-4" onClick={() => startRetry(() => retryFailed(campaign.id))} loading={retrying}>
          <RotateCcw className="size-4" /> Retry {counts.failed} failed
        </Button>
      )}

      <section className="mt-8 overflow-x-auto rounded-xl border border-ink-200">
        <table className="w-full text-sm">
          <thead className="bg-ink-50 text-left text-xs text-ink-500">
            <tr>
              <th className="px-3 py-2 font-medium">#</th>
              <th className="px-3 py-2 font-medium">Post</th>
              {sites.map((s) => (
                <th key={s.id} className="px-3 py-2 font-medium">
                  {s.name}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {items.map((item) => (
              <tr key={item.id}>
                <td className="px-3 py-1.5 text-ink-400 tabular-nums">{item.row}</td>
                <td className="max-w-80 truncate px-3 py-1.5" title={item.title}>
                  {item.title}
                </td>
                {sites.map((s) => (
                  <td key={s.id} className="px-3 py-1.5">
                    <Cell delivery={byCell.get(`${item.id}:${s.id}`)} />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      {problems.length > 0 && (
        <section className="mt-8">
          <h2 className="text-sm font-semibold tracking-wide text-ink-500 uppercase">
            Errors and warnings{warned ? ` (${warned} published with a warning)` : ''}
          </h2>
          <ul className="mt-2 divide-y divide-ink-100 rounded-xl border border-ink-200 text-sm">
            {problems.slice(0, 30).map((d) => (
              <li key={d.id} className="flex flex-wrap gap-x-3 px-4 py-2">
                <span className={d.status === 'failed' ? 'text-red-700' : 'text-amber-700'}>
                  {d.status === 'failed'
                    ? 'Failed'
                    : d.status === 'retrying'
                      ? `Retrying (attempt ${d.attempts})`
                      : 'Published with a warning'}
                </span>
                <span className="font-medium">{siteName(d.siteId)}</span>
                <span className="text-ink-600">{itemTitle(d.itemId)}</span>
                <span className="w-full text-xs text-ink-500">{d.lastError ?? d.warnings?.join('; ')}</span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

function Stat({ label, value, tone }: { label: string; value: string; tone?: 'warn' | 'bad' }) {
  const color = tone === 'bad' ? 'text-red-700' : tone === 'warn' ? 'text-amber-700' : 'text-ink-900';
  return (
    <div className="rounded-xl border border-ink-200 p-4">
      <div className="text-xs text-ink-500">{label}</div>
      <div className={`mt-1 text-xl font-semibold tabular-nums ${color}`}>{value}</div>
    </div>
  );
}

const ACTION_ICON = { created: Check, updated: RefreshCw, unchanged: Equal };

function Cell({ delivery }: { delivery: Delivery | undefined }) {
  if (!delivery) return <span className="text-xs text-ink-300">—</span>;
  const style = DELIVERY_STYLE[delivery.status];
  const Icon = delivery.status === 'failed' ? X : delivery.action ? ACTION_ICON[delivery.action] : null;
  const label =
    delivery.status === 'published' && delivery.action
      ? delivery.action
      : delivery.status === 'retrying'
        ? `retry ${delivery.attempts}`
        : style.label.toLowerCase();
  const chip = (
    <span
      title={delivery.lastError ?? delivery.warnings?.join('; ') ?? undefined}
      className={`inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap ${style.cell} ${
        delivery.warnings?.length ? 'ring-1 ring-amber-400' : ''
      }`}
    >
      {Icon && <Icon className="size-3" />}
      {label}
      {delivery.link && <ExternalLink className="size-3 opacity-60" />}
    </span>
  );
  return delivery.link ? (
    <a href={delivery.link} target="_blank" rel="noreferrer">
      {chip}
    </a>
  ) : (
    chip
  );
}
