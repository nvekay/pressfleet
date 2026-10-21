// Registers the local demo sites written by infra/provision.sh with a running API.
//   pnpm --filter @pressfleet/api sites:register
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

interface LocalSite {
  name: string;
  url: string;
  username: string;
  applicationPassword: string;
  secret: string;
}

const file = fileURLToPath(new URL('../../../infra/.sites.json', import.meta.url));
const sites = JSON.parse(readFileSync(file, 'utf8')) as LocalSite[];
const api = process.env.API_URL ?? 'http://localhost:4000';

for (const site of sites) {
  const res = await fetch(`${api}/sites`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', 'x-api-key': process.env.API_INTERNAL_KEY ?? '' },
    body: JSON.stringify(site),
  });
  const body = (await res.json()) as { name?: string; message?: string };
  console.log(`${site.name}: ${res.ok ? 'registered' : `failed (${body.message})`}`);
}
