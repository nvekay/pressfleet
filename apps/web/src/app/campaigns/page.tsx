import { Plus } from 'lucide-react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { CampaignTable } from '@/components/campaign-table';
import { api } from '@/lib/api';

export const metadata: Metadata = { title: 'Campaigns' };
export const dynamic = 'force-dynamic';

export default async function Campaigns() {
  const campaigns = await api.campaigns();
  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Campaigns</h1>
        <Link
          href="/campaigns/new"
          className="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3 text-sm font-medium text-white hover:bg-accent-strong"
        >
          <Plus className="size-4" /> New campaign
        </Link>
      </div>
      <CampaignTable campaigns={campaigns} />
    </div>
  );
}
