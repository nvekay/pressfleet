import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { LiveCampaign } from './live-campaign';

export const metadata: Metadata = { title: 'Campaign' };
export const dynamic = 'force-dynamic';

export default async function CampaignPage({ params }: PageProps<'/campaigns/[id]'>) {
  const { id } = await params;
  const detail = await api.campaign(id).catch((err: unknown) => {
    if (err instanceof ApiError && err.status === 404) notFound();
    throw err;
  });
  return <LiveCampaign initial={detail} />;
}
