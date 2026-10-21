import Link from 'next/link';
import { CampaignBadge } from '@/components/status';
import { dateTime } from '@/lib/format';
import type { CampaignSummary } from '@/lib/types';

export function CampaignTable({ campaigns }: { campaigns: CampaignSummary[] }) {
  if (!campaigns.length) {
    return <p className="mt-3 text-sm text-ink-500">Nothing published yet.</p>;
  }
  return (
    <div className="mt-3 overflow-x-auto rounded-xl border border-ink-200">
      <table className="w-full text-sm">
        <thead className="bg-ink-50 text-left text-xs text-ink-500">
          <tr>
            <th className="px-4 py-2 font-medium">Campaign</th>
            <th className="px-4 py-2 font-medium">Status</th>
            <th className="px-4 py-2 text-right font-medium">Published</th>
            <th className="px-4 py-2 text-right font-medium">Failed</th>
            <th className="px-4 py-2 font-medium">Created</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-ink-100">
          {campaigns.map((c) => (
            <tr key={c.id} className="hover:bg-ink-50">
              <td className="px-4 py-2.5">
                <Link href={`/campaigns/${c.id}`} className="font-medium hover:text-accent">
                  {c.name}
                </Link>
              </td>
              <td className="px-4 py-2.5">
                <CampaignBadge status={c.status} />
              </td>
              <td className="px-4 py-2.5 text-right tabular-nums">
                {c.counts.published} / {c.counts.total}
              </td>
              <td
                className={`px-4 py-2.5 text-right tabular-nums ${c.counts.failed ? 'text-red-700' : 'text-ink-400'}`}
              >
                {c.counts.failed}
              </td>
              <td className="px-4 py-2.5 text-ink-500">{dateTime(c.createdAt)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
