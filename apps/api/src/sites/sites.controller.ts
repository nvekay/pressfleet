import { Body, Controller, Delete, Get, HttpCode, Param, Post } from '@nestjs/common';
import { z } from 'zod';
import { parse } from '../common/zod.js';
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
    const site = await this.sites.add(parse(newSite, body));
    await this.queues.ensure(site.id);
    return publicSite(site);
  }

  @Post(':id/check')
  async check(@Param('id') id: string) {
    return publicSite(await this.sites.check(id));
  }

  @Delete(':id')
  @HttpCode(204)
  async remove(@Param('id') id: string) {
    await this.queues.drop(id);
    await this.sites.remove(id);
  }
}
