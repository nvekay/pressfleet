import type { Metadata } from 'next';
import { api } from '@/lib/api';
import { dateTime } from '@/lib/format';

export const metadata: Metadata = { title: 'Leads' };
export const dynamic = 'force-dynamic';

export default async function Leads() {
  const [leads, config] = await Promise.all([api.leads(), api.config()]);
  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <h1 className="text-2xl font-semibold">Leads</h1>
      <p className="mt-1 max-w-2xl text-sm text-ink-600">
        Requests from the Lead form block on every site. Each site saves a request first and forwards it here, signed
        with its secret; if the hub is down, the site retries later, and a resent request is not counted twice.
      </p>
      {config.demo && (
        <p className="mt-3 max-w-2xl text-sm text-ink-600">
          This page is public in the demo, so names, contacts and messages are masked here. Send a request from any demo
          site&apos;s contact form to see it arrive.
        </p>
      )}
      <div className="mt-6 overflow-x-auto rounded-xl border border-ink-200">
        <table className="w-full text-sm">
          <thead className="bg-ink-50 text-left text-xs text-ink-500">
            <tr>
              <th className="px-4 py-2 font-medium">Received</th>
              <th className="px-4 py-2 font-medium">Site</th>
              <th className="px-4 py-2 font-medium">From</th>
              <th className="px-4 py-2 font-medium">Message</th>
              <th className="px-4 py-2 font-medium">Telegram</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {leads.map((l) => (
              <tr key={l.id} className="align-top">
                <td className="px-4 py-3 whitespace-nowrap text-ink-500">{dateTime(l.receivedAt)}</td>
                <td className="px-4 py-3 whitespace-nowrap">
                  <a href={l.page || l.siteUrl} target="_blank" rel="noreferrer" className="hover:text-accent">
                    {l.site}
                  </a>
                </td>
                <td className="px-4 py-3">
                  <div className="font-medium">{l.name}</div>
                  <div className="text-ink-500">{l.contact}</div>
                </td>
                <td className="max-w-md px-4 py-3 text-ink-700">{l.message || '—'}</td>
                <td className="px-4 py-3 whitespace-nowrap">
                  {l.notifiedAt ? (
                    <span className="text-emerald-700">Sent</span>
                  ) : l.notifyError ? (
                    <span className="text-red-700" title={l.notifyError}>
                      Failed
                    </span>
                  ) : (
                    <span className="text-ink-400">Not configured</span>
                  )}
                </td>
              </tr>
            ))}
            {!leads.length && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-ink-500">
                  No leads yet. Send the form on any demo site.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
