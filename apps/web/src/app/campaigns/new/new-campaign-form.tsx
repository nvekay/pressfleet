'use client';

import { AlertTriangle, FileSpreadsheet, FileText, Sparkles } from 'lucide-react';
import { useState, useTransition } from 'react';
import { Button } from '@/components/ui/button';
import type { ImportSource } from '@/lib/api';
import type { ImportPreview } from '@/lib/types';
import { previewImport, startCampaign } from '../../actions';

type Tab = 'sample' | 'csv' | 'sheet';

const SAMPLE = { file: '/samples/health-network.csv', name: 'Health tips for the clinic network' };

const TABS: { id: Tab; label: string; icon: typeof FileText }[] = [
  { id: 'sample', label: 'Sample: 50 posts', icon: Sparkles },
  { id: 'csv', label: 'CSV file', icon: FileText },
  { id: 'sheet', label: 'Google Sheet', icon: FileSpreadsheet },
];

export function NewCampaignForm({ sites }: { sites: { id: string; name: string; ok: boolean }[] }) {
  const [tab, setTab] = useState<Tab>('sample');
  const [csv, setCsv] = useState<string | null>(null);
  const [sheetUrl, setSheetUrl] = useState('');
  const [name, setName] = useState(SAMPLE.name);
  const [siteIds, setSiteIds] = useState(sites.filter((s) => s.ok).map((s) => s.id));
  const [preview, setPreview] = useState<ImportPreview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  const reset = () => {
    setPreview(null);
    setError(null);
  };

  async function source(): Promise<ImportSource | null> {
    if (tab === 'sheet') return sheetUrl ? { source: 'sheet', url: sheetUrl } : null;
    if (tab === 'csv') return csv ? { source: 'csv', csv } : null;
    const res = await fetch(SAMPLE.file);
    // The sample points its images at {{ASSETS}}: this dashboard, as the WordPress sites can reach it
    const assets = process.env.NEXT_PUBLIC_SAMPLE_ASSETS_URL || window.location.origin;
    return { source: 'csv', csv: (await res.text()).replaceAll('{{ASSETS}}', assets) };
  }

  const runPreview = () =>
    startTransition(async () => {
      const src = await source();
      if (!src) return setError(tab === 'csv' ? 'Choose a CSV file' : 'Paste the link of a Google Sheet');
      const result = await previewImport(src);
      setError(result.error ?? null);
      setPreview(result.preview ?? null);
    });

  const start = () =>
    startTransition(async () => {
      const src = await source();
      if (!src) return;
      const result = await startCampaign({ ...src, name, siteIds });
      if (result?.error) setError(result.error);
    });

  return (
    <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_320px]">
      <div>
        <div className="flex flex-wrap gap-2">
          {TABS.map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => {
                setTab(t.id);
                setName(t.id === 'sample' ? SAMPLE.name : '');
                reset();
              }}
              className={`inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm ${
                tab === t.id ? 'border-accent bg-accent-soft text-accent-strong' : 'border-ink-200 hover:bg-ink-50'
              }`}
            >
              <t.icon className="size-4" /> {t.label}
            </button>
          ))}
        </div>

        <div className="mt-4 rounded-xl border border-ink-200 p-4 text-sm">
          {tab === 'sample' && (
            <p className="text-ink-600">
              50 fictional health articles for three clinic sites: Markdown text, categories, tags, SEO fields, cover
              images, a few scheduled for later, and a few meant for one site only.{' '}
              <a href={SAMPLE.file} className="text-accent hover:underline" download>
                Download the CSV
              </a>
            </p>
          )}
          {tab === 'csv' && (
            <input
              type="file"
              accept=".csv,text/csv"
              onChange={async (e) => {
                const file = e.target.files?.[0];
                reset();
                setCsv(file ? await file.text() : null);
                if (file) setName(file.name.replace(/\.csv$/i, ''));
              }}
              className="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-1.5 file:text-sm"
            />
          )}
          {tab === 'sheet' && (
            <label className="block">
              <span className="text-ink-600">
                Link to a sheet shared as &ldquo;Anyone with the link can view&rdquo;
              </span>
              <input
                type="url"
                value={sheetUrl}
                onChange={(e) => {
                  setSheetUrl(e.target.value);
                  reset();
                }}
                placeholder="https://docs.google.com/spreadsheets/d/…"
                className="mt-1 h-9 w-full rounded-md border border-ink-200 px-2.5 focus:border-accent focus:ring-2 focus:ring-accent/30 focus:outline-none"
              />
            </label>
          )}
          <Button type="button" className="mt-3" onClick={runPreview} loading={pending && !preview}>
            Check the rows
          </Button>
        </div>

        {error && <p className="mt-4 text-sm text-red-700">{error}</p>}
        {preview && <PreviewTable preview={preview} />}
      </div>

      <aside className="h-fit rounded-xl border border-ink-200 p-4 lg:sticky lg:top-6">
        <label className="block text-sm">
          <span className="text-ink-600">Campaign name</span>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="mt-1 h-9 w-full rounded-md border border-ink-200 px-2.5 focus:border-accent focus:ring-2 focus:ring-accent/30 focus:outline-none"
          />
        </label>
        <fieldset className="mt-4 text-sm">
          <legend className="text-ink-600">Publish to</legend>
          {sites.map((s) => (
            <label key={s.id} className="mt-2 flex items-center gap-2">
              <input
                type="checkbox"
                checked={siteIds.includes(s.id)}
                onChange={(e) =>
                  setSiteIds(e.target.checked ? [...siteIds, s.id] : siteIds.filter((id) => id !== s.id))
                }
                className="size-4 accent-[var(--color-accent)]"
              />
              {s.name}
              {!s.ok && <span className="text-xs text-red-700">(not reachable)</span>}
            </label>
          ))}
        </fieldset>
        <Button
          type="button"
          variant="primary"
          className="mt-5 w-full"
          disabled={!preview?.rows.length || !siteIds.length || !name.trim()}
          loading={pending && !!preview}
          onClick={start}
        >
          Publish {preview?.rows.length ?? ''} posts to {siteIds.length} sites
        </Button>
        {!preview && <p className="mt-2 text-xs text-ink-500">Check the rows first.</p>}
      </aside>
    </div>
  );
}

function PreviewTable({ preview }: { preview: ImportPreview }) {
  return (
    <div className="mt-6">
      <p className="text-sm">
        <strong>{preview.rows.length}</strong> rows ready
        {preview.problems.length > 0 && (
          <>
            , <strong className="text-red-700">{preview.problems.length}</strong> skipped
          </>
        )}
        {preview.unknownColumns.length > 0 && (
          <span className="text-ink-500"> · ignored columns: {preview.unknownColumns.join(', ')}</span>
        )}
      </p>
      {preview.problems.length > 0 && (
        <ul className="mt-2 space-y-1 rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {preview.problems.map((p) => (
            <li key={`${p.row}-${p.message}`} className="flex gap-2">
              <AlertTriangle className="mt-0.5 size-4 shrink-0" /> {p.row ? `Row ${p.row}: ` : ''}
              {p.message}
            </li>
          ))}
        </ul>
      )}
      <div className="mt-3 max-h-[28rem] overflow-auto rounded-xl border border-ink-200">
        <table className="w-full text-sm">
          <thead className="sticky top-0 bg-ink-50 text-left text-xs text-ink-500">
            <tr>
              <th className="px-3 py-2 font-medium">#</th>
              <th className="px-3 py-2 font-medium">Title</th>
              <th className="px-3 py-2 font-medium">Categories</th>
              <th className="px-3 py-2 font-medium">Publish</th>
              <th className="px-3 py-2 font-medium">Sites</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {preview.rows.map((r) => (
              <tr key={r.externalId}>
                <td className="px-3 py-2 text-ink-400 tabular-nums">{r.row}</td>
                <td className="px-3 py-2">
                  {r.title}
                  {r.image && <span className="ml-2 text-xs text-ink-400">image</span>}
                </td>
                <td className="px-3 py-2 text-ink-600">{r.categories.join(', ') || '—'}</td>
                <td className="px-3 py-2 text-ink-600">{r.date ? r.date.slice(0, 16).replace('T', ' ') : 'now'}</td>
                <td className="px-3 py-2 text-ink-600">{r.sites?.join(', ') ?? 'all'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
