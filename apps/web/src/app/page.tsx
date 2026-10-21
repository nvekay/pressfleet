import { ArrowRight, Plus } from 'lucide-react';
import Link from 'next/link';
import { CampaignTable } from '@/components/campaign-table';
import { HealthDot } from '@/components/status';
import { api } from '@/lib/api';
import { ago } from '@/lib/format';

export const dynamic = 'force-dynamic';

export default async function Overview() {
  const [sites, campaigns] = await Promise.all([api.sites(), api.campaigns()]);
  const totalPosts = sites.reduce((n, s) => n + (s.health?.posts_from_hub ?? 0), 0);

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <section className="flex flex-wrap items-end justify-between gap-6">
        <div className="max-w-2xl">
          <p className="text-sm font-medium text-accent">One hub, every WordPress site</p>
          <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">Publish a spreadsheet to the whole network.</h1>
          <p className="mt-3 text-ink-600">
            Import posts from a CSV file or a Google Sheet and send them to every site at once. A queue retries what
            fails, a connector plugin on each site makes sure nothing is published twice.
          </p>
        </div>
        <Link
          href="/campaigns/new"
          className="inline-flex h-10 items-center gap-2 rounded-lg bg-accent px-4 text-sm font-medium text-white shadow-sm hover:bg-accent-strong"
        >
          <Plus className="size-4" /> New campaign
        </Link>
      </section>

      <section className="mt-10">
        <div className="flex items-baseline justify-between">
          <h2 className="text-lg font-semibold">Sites</h2>
          <span className="text-sm text-ink-500">{totalPosts} posts published from the hub</span>
        </div>
        <div className="mt-3 grid gap-4 sm:grid-cols-3">
          {sites.map((s) => (
            <a
              key={s.id}
              href={s.url}
              target="_blank"
              rel="noreferrer"
              className="rounded-xl border border-ink-200 p-4 hover:border-accent/40 hover:shadow-sm"
            >
              <div className="flex items-center gap-2 font-medium">
                <HealthDot status={s.healthStatus} /> {s.name}
              </div>
              <div className="mt-1 truncate text-sm text-ink-500">{s.url.replace(/^https?:\/\//, '')}</div>
              <div className="mt-3 text-xs text-ink-500">
                {s.health
                  ? `${s.health.posts_from_hub} posts · WordPress ${s.health.wordpress} · last post ${ago(s.health.last_received_at && `${s.health.last_received_at}Z`)}`
                  : (s.healthError ?? 'Not checked yet')}
              </div>
            </a>
          ))}
          {!sites.length && (
            <Link href="/sites" className="rounded-xl border border-dashed border-ink-300 p-4 text-sm text-ink-600">
              No sites yet. Add the first one →
            </Link>
          )}
        </div>
      </section>

      <section className="mt-10">
        <div className="flex items-baseline justify-between">
          <h2 className="text-lg font-semibold">Recent campaigns</h2>
          <Link href="/campaigns" className="inline-flex items-center gap-1 text-sm text-accent hover:underline">
            All campaigns <ArrowRight className="size-3.5" />
          </Link>
        </div>
        <CampaignTable campaigns={campaigns.slice(0, 5)} />
      </section>
    </div>
  );
}
