import type { Metadata } from 'next';
import { api } from '@/lib/api';
import { NewCampaignForm } from './new-campaign-form';

export const metadata: Metadata = { title: 'New campaign' };
export const dynamic = 'force-dynamic';

export default async function NewCampaign() {
  const sites = await api.sites();
  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <h1 className="text-2xl font-semibold">New campaign</h1>
      <p className="mt-1 max-w-2xl text-sm text-ink-600">
        One row is one post. Columns: <code>title</code> and <code>content</code> (Markdown or HTML) are required;{' '}
        <code>id</code>, <code>image_url</code>, <code>categories</code>, <code>tags</code>, <code>seo_title</code>,{' '}
        <code>seo_description</code>, <code>publish_at</code> and <code>sites</code> are optional. Importing the same
        rows again updates the posts instead of duplicating them.
      </p>
      <NewCampaignForm sites={sites.map((s) => ({ id: s.id, name: s.name, ok: s.healthStatus !== 'error' }))} />
    </div>
  );
}
