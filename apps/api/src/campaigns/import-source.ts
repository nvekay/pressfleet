import { BadRequestException } from '@nestjs/common';
import { type ImportResult, parseImport, sheetCsvUrl } from '@pressfleet/shared';

export type ImportSource = { source: 'csv'; csv: string } | { source: 'sheet'; url: string };

const MAX_BYTES = 2 * 1024 * 1024;

/** Reads a public Google Sheet as CSV; a sheet that is not shared answers with a sign-in page instead */
async function fetchSheet(link: string): Promise<string> {
  let url: string;
  try {
    url = sheetCsvUrl(link);
  } catch (err) {
    throw new BadRequestException((err as Error).message);
  }
  const res = await fetch(url, { signal: AbortSignal.timeout(15_000), redirect: 'follow' }).catch(() => null);
  if (!res?.ok) throw new BadRequestException('Could not read the sheet. Check the link.');
  if (!res.headers.get('content-type')?.includes('text/csv')) {
    throw new BadRequestException('The sheet is not public. Share it as "Anyone with the link can view".');
  }
  const text = await res.text();
  if (text.length > MAX_BYTES) throw new BadRequestException('The sheet is larger than 2 MB');
  return text;
}

export async function readImport(input: ImportSource): Promise<ImportResult & { sourceRef: string | null }> {
  if (input.source === 'csv') {
    if (input.csv.length > MAX_BYTES) throw new BadRequestException('The file is larger than 2 MB');
    return { ...parseImport(input.csv), sourceRef: null };
  }
  return { ...parseImport(await fetchSheet(input.url)), sourceRef: input.url };
}
