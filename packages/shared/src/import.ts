import { parse } from 'csv-parse/sync';
import { marked } from 'marked';
import type { PostPayload } from './types.js';

/**
 * Columns of an import file (CSV or Google Sheet). Only `title` and `content` are required.
 *
 * `id` identifies the post across imports: importing the same sheet again updates the posts instead of creating
 * new ones. Without it, the slug of the title is used.
 */
export const IMPORT_COLUMNS = [
  'id',
  'title',
  'content',
  'excerpt',
  'image_url',
  'image_alt',
  'categories',
  'tags',
  'seo_title',
  'seo_description',
  'slug',
  'status',
  'publish_at',
  'sites',
] as const;

export interface ImportRow {
  /** 1-based row number in the file, header excluded */
  row: number;
  payload: PostPayload;
  /** Site names or slugs to publish to; null means every selected site */
  sites: string[] | null;
}

export interface ImportProblem {
  row: number;
  message: string;
}

export interface ImportResult {
  rows: ImportRow[];
  problems: ImportProblem[];
  /** Columns in the file that PressFleet does not use */
  unknownColumns: string[];
}

const EXTERNAL_ID = /^[A-Za-z0-9._:-]{1,100}$/;
const STATUSES = new Set(['publish', 'draft', 'pending', 'private']);

/** URL-safe slug: lower case, ASCII letters, digits and dashes */
export function slugify(text: string): string {
  return text
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 100);
}

/** "Guides, News" or "Guides | News" → ['Guides', 'News'] */
export function splitList(value: string | undefined): string[] {
  if (!value) return [];
  return [
    ...new Set(
      value
        .split(/[|,;]/)
        .map((v) => v.trim())
        .filter(Boolean),
    ),
  ];
}

/**
 * "2026-11-01T09:00:00Z", "2026-11-01 09:00" or "2026-11-01" (UTC) → ISO string; undefined when empty.
 * Throws on anything else, so a typo never publishes a post at the wrong time.
 */
export function parseDate(value: string | undefined): string | undefined {
  const v = value?.trim();
  if (!v) return undefined;
  const plain = /^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2})(?::(\d{2}))?)?$/.exec(v);
  const date = plain ? new Date(`${plain[1]}T${plain[2] ?? '00:00'}:${plain[3] ?? '00'}Z`) : new Date(v);
  if (Number.isNaN(date.getTime()) || !/^\d{4}-/.test(v))
    throw new Error(`"${v}" is not a date (use 2026-11-01 09:00, UTC)`);
  return date.toISOString().replace('.000Z', 'Z');
}

/** HTML is kept as is (the plugin sanitizes it); anything else is treated as Markdown */
export function toHtml(content: string): string {
  const text = content.trim();
  return text.startsWith('<') ? text : (marked.parse(text, { async: false })).trim();
}

/** Parses a CSV export (a file or a Google Sheet) into post payloads, collecting every problem by row */
export function parseImport(csv: string): ImportResult {
  let records: Record<string, string>[];
  try {
    records = parse(csv, {
      columns: (header: string[]) => header.map((h) => h.trim().toLowerCase().replace(/\s+/g, '_')),
      bom: true,
      skip_empty_lines: true,
      trim: true,
      relax_column_count: true,
    });
  } catch (err) {
    return {
      rows: [],
      problems: [{ row: 0, message: `Not a valid CSV file: ${(err as Error).message}` }],
      unknownColumns: [],
    };
  }

  const known = new Set<string>(IMPORT_COLUMNS);
  const header = records[0] ? Object.keys(records[0]) : [];
  const unknownColumns = header.filter((c) => c && !known.has(c));
  const problems: ImportProblem[] = [];
  if (records.length && (!header.includes('title') || !header.includes('content'))) {
    return {
      rows: [],
      problems: [{ row: 0, message: 'The file needs "title" and "content" columns' }],
      unknownColumns,
    };
  }

  const rows: ImportRow[] = [];
  const seen = new Map<string, number>();
  records.forEach((r, i) => {
    const row = i + 1;
    const fail = (message: string) => problems.push({ row, message });
    const title = r.title ?? '';
    const content = r.content ?? '';
    if (!title) return fail('Title is empty');
    if (!content) return fail('Content is empty');

    const externalId = r.id ? r.id.trim() : slugify(title);
    if (!EXTERNAL_ID.test(externalId))
      return fail(`ID "${externalId}" may only contain letters, digits, ".", "_", ":" and "-"`);
    const twin = seen.get(externalId);
    if (twin) return fail(`Same ID "${externalId}" as row ${twin}`);
    seen.set(externalId, row);

    let date: string | undefined;
    try {
      date = parseDate(r.publish_at);
    } catch (err) {
      return fail((err as Error).message);
    }
    const status = r.status?.toLowerCase();
    if (status && !STATUSES.has(status))
      return fail(`Status "${r.status}" is not one of publish, draft, pending, private`);
    if (r.image_url && !/^https?:\/\/\S+$/i.test(r.image_url))
      return fail(`Image URL "${r.image_url}" is not an http(s) link`);
    if (title.length > 300) return fail('Title is longer than 300 characters');

    const payload: PostPayload = {
      external_id: externalId,
      title,
      content: toHtml(content),
      categories: splitList(r.categories),
      tags: splitList(r.tags),
      featured_image: r.image_url ? { url: r.image_url, ...(r.image_alt ? { alt: r.image_alt } : {}) } : null,
      seo: {
        ...(r.seo_title ? { title: r.seo_title.slice(0, 200) } : {}),
        ...(r.seo_description ? { description: r.seo_description.slice(0, 400) } : {}),
      },
      ...(r.excerpt ? { excerpt: r.excerpt } : {}),
      ...(r.slug ? { slug: slugify(r.slug) } : {}),
      ...(status ? { status: status as PostPayload['status'] } : {}),
      ...(date ? { date } : {}),
    };
    const sites = r.sites && r.sites.toLowerCase() !== 'all' ? splitList(r.sites) : null;
    rows.push({ row, payload, sites });
  });

  return { rows, problems, unknownColumns };
}

/**
 * A Google Sheets link (shared as "anyone with the link can view") → its CSV export URL.
 * Keeps the tab (gid) the link points to.
 */
export function sheetCsvUrl(link: string): string {
  const url = new URL(link);
  const id = /\/spreadsheets\/d\/([A-Za-z0-9_-]+)/.exec(url.pathname)?.[1];
  if (url.hostname !== 'docs.google.com' || !id) throw new Error('Paste the link of a Google Sheet');
  const gid = url.searchParams.get('gid') ?? /gid=(\d+)/.exec(url.hash)?.[1] ?? '0';
  return `https://docs.google.com/spreadsheets/d/${id}/export?format=csv&gid=${gid}`;
}
