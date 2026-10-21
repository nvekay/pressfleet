import { UnauthorizedException } from '@nestjs/common';
import { signRequest } from '@pressfleet/shared';
import type { Db } from '../db/db.module.js';
import type { Site } from '../db/schema.js';
import type { SitesService } from '../sites/sites.service.js';
import { LeadsService } from './leads.service.js';

vi.mock('../config/env.js', () => ({ env: () => ({ LEADS_PUBLIC_PATH: '/leads' }) }));

const site = { id: 'site_1', name: 'Alder Physio', url: 'http://alder.test' } as Site;
const sites = {
  list: async () => [site],
  connection: () => ({ url: site.url, username: 'admin', applicationPassword: 'x', secret: 'site-secret' }),
} as unknown as SitesService;
const service = new LeadsService({} as Db, sites);

const body = JSON.stringify({ id: 7, name: 'Ana' });
const now = () => Math.floor(Date.now() / 1000);
const headers = (overrides: Partial<{ site: string; timestamp: string; signature: string }> = {}) => {
  const timestamp = String(now());
  return {
    site: 'http://alder.test/',
    timestamp,
    signature: signRequest('site-secret', Number(timestamp), 'POST', '/leads', body),
    ...overrides,
  };
};

describe('LeadsService.verify', () => {
  it('accepts a lead signed by a registered site (trailing slash or not)', async () => {
    await expect(service.verify(headers(), body)).resolves.toBe(site);
  });

  it('refuses an unknown site, a wrong signature, a changed body and an old timestamp', async () => {
    await expect(service.verify(headers({ site: 'http://other.test' }), body)).rejects.toThrow(UnauthorizedException);
    await expect(service.verify(headers({ signature: 'f'.repeat(64) }), body)).rejects.toThrow(UnauthorizedException);
    await expect(service.verify(headers(), body.replace('Ana', 'Bob'))).rejects.toThrow(UnauthorizedException);
    const old = String(now() - 600);
    await expect(
      service.verify(
        headers({ timestamp: old, signature: signRequest('site-secret', Number(old), 'POST', '/leads', body) }),
        body,
      ),
    ).rejects.toThrow(UnauthorizedException);
  });
});
