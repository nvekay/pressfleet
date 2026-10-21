import { Body, Controller, Delete, ForbiddenException, Get, HttpCode, Param, Post } from '@nestjs/common';
import { z } from 'zod';
import { parse } from '../common/zod.js';
import { env } from '../config/env.js';
import { PublishQueues } from '../publishing/publish-queues.js';
import { publicSite, SitesService } from './sites.service.js';

const newSite = z.object({
  name: z.string().trim().max(80).optional(),
  url: z.url({ protocol: /^https?$/ }),
  username: z.string().trim().min(1).max(60),
  // WordPress shows Application Passwords in groups of four with spaces; both forms work
  applicationPassword: z.string().trim().min(16).max(64),
  secret: z.string().trim().min(16).max(128),
});

const outage = z.object({ seconds: z.coerce.number().int().min(0).max(300) });

const notInDemo = () => {
  if (env().DEMO_MODE) throw new ForbiddenException('Sites cannot be changed in the public demo.');
};

@Controller('sites')
export class SitesController {
  constructor(
    private readonly sites: SitesService,
    private readonly queues: PublishQueues,
  ) {}

  @Get()
  async list() {
    return (await this.sites.list()).map(publicSite);
  }

  @Post()
  async add(@Body() body: unknown) {
    notInDemo();
    const site = await this.sites.add(parse(newSite, body));
    await this.queues.ensure(site.id);
    return publicSite(site);
  }

  /** Demo: the site answers 503 to publishing for a while (at most 2 minutes in the public demo) */
  @Post(':id/outage')
  async outage(@Param('id') id: string, @Body() body: unknown) {
    const { seconds } = parse(outage, body);
    const site = await this.sites.get(id);
    return this.sites.client(site).outage(env().DEMO_MODE ? Math.min(seconds, 120) : seconds);
  }

  @Post(':id/check')
  async check(@Param('id') id: string) {
    return publicSite(await this.sites.check(id));
  }

  @Delete(':id')
  @HttpCode(204)
  async remove(@Param('id') id: string) {
    notInDemo();
    await this.queues.drop(id);
    await this.sites.remove(id);
  }
}
