// Registers sites straight in the database, for servers where the HTTP API refuses it (the public demo).
// Reads a JSON array of { name, url, username, applicationPassword, secret } from stdin:
//   docker compose exec -T api node dist/cli/register-sites.js < sites.json
import 'reflect-metadata';
import { Module } from '@nestjs/common';
import { NestFactory } from '@nestjs/core';
import { text } from 'node:stream/consumers';
import { SecretBoxModule } from '../common/secret-box.module.js';
import { DbModule } from '../db/db.module.js';
import { type NewSite, SitesService } from '../sites/sites.service.js';

@Module({ imports: [DbModule, SecretBoxModule], providers: [SitesService] })
class RegisterSitesModule {}

const input = JSON.parse(await text(process.stdin)) as NewSite[];
const app = await NestFactory.createApplicationContext(RegisterSitesModule, { logger: ['error'] });
const sites = app.get(SitesService);
let failed = 0;
for (const site of input) {
  try {
    const saved = await sites.add(site);
    console.log(`${saved.name}: registered (${saved.url})`);
  } catch (err) {
    failed += 1;
    console.error(`${site.name ?? site.url}: ${(err as Error).message}`);
  }
}
await app.close();
process.exitCode = failed ? 1 : 0;
