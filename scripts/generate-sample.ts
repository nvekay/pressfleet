// Generates the demo dataset: 50 fictional articles for a network of three clinics, with cover images.
//   pnpm sample:generate
// Writes apps/web/public/samples/health-network.csv and apps/web/public/samples/covers/*.jpg.
// Texts come from Claude Haiku (structured output, a few cents); covers are drawn here with sharp.
import Anthropic from '@anthropic-ai/sdk';
import { zodOutputFormat } from '@anthropic-ai/sdk/helpers/zod';
import { stringify } from 'csv-stringify/sync';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import sharp from 'sharp';
import { z } from 'zod';

const OUT = join(import.meta.dirname, '..', 'apps', 'web', 'public', 'samples');
const MODEL = 'claude-haiku-5-5';

const article = z.object({
  title: z.string().describe('Specific, useful headline, at most 70 characters'),
  excerpt: z.string().describe('One sentence summary'),
  body: z
    .string()
    .describe('Markdown, 180 to 260 words: a short intro, two or three ## subheadings, one bullet list, no title'),
  categories: z.array(z.string()).describe('One or two of the allowed categories'),
  tags: z.array(z.string()).describe('Two to four lower-case tags'),
  seoTitle: z.string().describe('At most 60 characters'),
  seoDescription: z.string().describe('At most 155 characters'),
});

interface Group {
  key: string;
  count: number;
  sites: string;
  audience: string;
  categories: string[];
  color: [string, string];
  label: string;
}

const GROUPS: Group[] = [
  {
    key: 'general',
    count: 20,
    sites: 'all',
    audience: 'patients of a group of clinics (physiotherapy, dentistry, eye care): everyday health habits for anyone',
    categories: ['Healthy habits', 'Clinic news', 'Guides'],
    color: ['#0f766e', '#134e4a'],
    label: 'Clinic network journal',
  },
  {
    key: 'physio',
    count: 10,
    sites: 'alder-physio',
    audience: 'patients of Alder Physio, a physiotherapy clinic: posture, back pain, recovery, exercise',
    categories: ['Exercises', 'Recovery', 'Guides'],
    color: ['#15803d', '#14532d'],
    label: 'Alder Physio',
  },
  {
    key: 'dental',
    count: 10,
    sites: 'birch-dental',
    audience: 'patients of Birch Dental, a dental clinic: brushing, gum health, check-ups, children’s teeth',
    categories: ['Oral care', 'Treatments', 'Guides'],
    color: ['#0369a1', '#0c4a6e'],
    label: 'Birch Dental',
  },
  {
    key: 'vision',
    count: 10,
    sites: 'cedar-vision',
    audience: 'patients of Cedar Vision, an eye care clinic: screen time, eye exams, contact lenses, kids’ eyes',
    categories: ['Eye care', 'Treatments', 'Guides'],
    color: ['#7c3aed', '#4c1d95'],
    label: 'Cedar Vision',
  },
];

const client = new Anthropic();

async function write(group: Group, count: number, avoid: string[]) {
  const res = await client.messages.parse({
    model: MODEL,
    max_tokens: 16000,
    system:
      'You write short, practical blog articles for clinic websites. General wellbeing advice only: no diagnoses, ' +
      'no drug names or doses, suggest seeing a professional where it matters. Plain, friendly English.',
    messages: [
      {
        role: 'user',
        content:
          `Write ${count} different articles for ${group.audience}. ` +
          `Allowed categories: ${group.categories.join(', ')}. ` +
          (avoid.length ? `Do not repeat these topics: ${avoid.join('; ')}.` : ''),
      },
    ],
    output_config: { format: zodOutputFormat(z.object({ articles: z.array(article) })) },
  });
  if (!res.parsed_output) throw new Error(`No articles (stop reason ${res.stop_reason})`);
  return res.parsed_output.articles.slice(0, count);
}

const escapeXml = (s: string) => s.replace(/[<>&"']/g, (c) => `&#${c.charCodeAt(0)};`);

/** Splits a title into lines of at most `width` characters */
function wrap(text: string, width: number): string[] {
  const lines: string[] = [];
  let line = '';
  for (const word of text.split(' ')) {
    if (line && (line + ' ' + word).length > width) {
      lines.push(line);
      line = word;
    } else line = line ? `${line} ${word}` : word;
  }
  return [...lines, line].slice(0, 4);
}

async function cover(file: string, title: string, group: Group) {
  const lines = wrap(title, 26);
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="${group.color[0]}"/><stop offset="1" stop-color="${group.color[1]}"/>
  </linearGradient></defs>
  <rect width="1200" height="630" fill="url(#g)"/>
  <circle cx="1050" cy="110" r="220" fill="#fff" fill-opacity=".06"/>
  <circle cx="1120" cy="560" r="160" fill="#fff" fill-opacity=".05"/>
  <text x="80" y="110" font-family="Arial, Helvetica, sans-serif" font-size="30" fill="#fff" fill-opacity=".75">${escapeXml(group.label)}</text>
  ${lines
    .map(
      (l, i) =>
        `<text x="80" y="${250 + i * 78}" font-family="Arial, Helvetica, sans-serif" font-size="66" font-weight="700" fill="#fff">${escapeXml(l)}</text>`,
    )
    .join('\n  ')}
</svg>`;
  await sharp(Buffer.from(svg)).jpeg({ quality: 78, mozjpeg: true }).toFile(file);
}

mkdirSync(join(OUT, 'covers'), { recursive: true });
const rows: Record<string, string>[] = [];
let n = 0;
for (const group of GROUPS) {
  const articles: z.infer<typeof article>[] = [];
  while (articles.length < group.count) {
    const batch = await write(
      group,
      Math.min(10, group.count - articles.length),
      articles.map((a) => a.title),
    );
    articles.push(...batch);
    console.log(`${group.key}: ${articles.length}/${group.count}`);
  }
  for (const [i, a] of articles.entries()) {
    n += 1;
    const id = `hc-${String(n).padStart(3, '0')}`;
    await cover(join(OUT, 'covers', `${id}.jpg`), a.title, group);
    rows.push({
      id,
      title: a.title,
      content: a.body,
      excerpt: a.excerpt,
      // The dashboard replaces {{ASSETS}} with its own public URL, so the sites can download the covers
      image_url: `{{ASSETS}}/samples/covers/${id}.jpg`,
      image_alt: a.title,
      categories: a.categories.filter((c) => group.categories.includes(c)).join('|') || 'Guides',
      tags: a.tags.join('|'),
      seo_title: a.seoTitle.slice(0, 60),
      seo_description: a.seoDescription.slice(0, 155),
      // A few general posts are scheduled, to show the WordPress "future" status
      publish_at: group.key === 'general' && i >= 15 ? `2027-01-0${i - 14} 09:00` : '',
      sites: group.sites,
    });
  }
}

writeFileSync(join(OUT, 'health-network.csv'), stringify(rows, { header: true }));
console.log(`Wrote ${rows.length} rows and covers to ${OUT}`);
