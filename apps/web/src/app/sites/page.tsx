import { RefreshCw } from 'lucide-react';
import type { Metadata } from 'next';
import { HealthDot } from '@/components/status';
import { api } from '@/lib/api';
import { ago } from '@/lib/format';
import { checkSite } from '../actions';
import { AddSiteForm } from './add-site-form';
import { OutageButton } from '@/components/outage-button';

export const metadata: Metadata = { title: 'Sites' };
export const dynamic = 'force-dynamic';

export default async function Sites() {
  const [sites, config] = await Promise.all([api.sites(), api.config()]);
  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <h1 className="text-2xl font-semibold">Sites</h1>
      <p className="mt-1 text-sm text-ink-600">
        Each site runs the PressFleet Connector plugin. The hub signs every request with the site&apos;s secret, on top
        of a WordPress Application Password.
      </p>

      <div className="mt-6 overflow-x-auto rounded-xl border border-ink-200">
        <table className="w-full text-sm">
          <thead className="bg-ink-50 text-left text-xs text-ink-500">
            <tr>
              <th className="px-4 py-2 font-medium">Site</th>
              <th className="px-4 py-2 font-medium">WordPress</th>
              <th className="px-4 py-2 font-medium">Plugin</th>
              <th className="px-4 py-2 text-right font-medium">Posts from hub</th>
              <th className="px-4 py-2 font-medium">SEO fields</th>
              <th className="px-4 py-2 font-medium">Checked</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {sites.map((s) => (
              <tr key={s.id}>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-2 font-medium">
                    <HealthDot status={s.healthStatus} /> {s.name}
                  </div>
                  <a href={s.url} target="_blank" rel="noreferrer" className="text-xs text-ink-500 hover:text-accent">
                    {s.url.replace(/^https?:\/\//, '')}
                  </a>
                  {s.healthError && <div className="mt-1 text-xs text-red-700">{s.healthError}</div>}
                </td>
                <td className="px-4 py-3 tabular-nums">{s.health?.wordpress ?? '—'}</td>
                <td className="px-4 py-3 tabular-nums">{s.health?.plugin_version ?? '—'}</td>
                <td className="px-4 py-3 text-right tabular-nums">{s.health?.posts_from_hub ?? '—'}</td>
                <td className="px-4 py-3 text-ink-600">{s.health ? (s.health.seo_plugin ?? 'plugin') : '—'}</td>
                <td className="px-4 py-3 text-ink-500">{ago(s.checkedAt)}</td>
                <td className="px-4 py-3 text-right whitespace-nowrap">
                  <OutageButton siteId={s.id} siteName={s.name} compact />
                  <form action={checkSite.bind(null, s.id)} className="inline">
                    <button
                      type="submit"
                      className="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-ink-600 hover:bg-ink-100"
                    >
                      <RefreshCw className="size-3.5" /> Check
                    </button>
                  </form>
                </td>
              </tr>
            ))}
            {!sites.length && (
              <tr>
                <td colSpan={7} className="px-4 py-6 text-center text-ink-500">
                  No sites yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {config.demo ? (
        <p className="mt-8 rounded-xl border border-dashed border-ink-300 p-5 text-sm text-ink-600">
          Adding sites is turned off in the public demo. Run PressFleet locally to connect your own WordPress sites.
        </p>
      ) : (
        <AddSiteForm />
      )}
    </div>
  );
}
