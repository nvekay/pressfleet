import { Body, Controller, Get, Param, Post, Sse } from '@nestjs/common';
import { map, merge, type Observable, interval } from 'rxjs';
import { z } from 'zod';
import { parse } from '../common/zod.js';
import { CampaignEvents } from '../publishing/campaign-events.js';
import { CampaignsService } from './campaigns.service.js';

const source = z.discriminatedUnion('source', [
  z.object({ source: z.literal('csv'), csv: z.string().min(1) }),
  z.object({ source: z.literal('sheet'), url: z.url() }),
]);

const create = z.intersection(
  source,
  z.object({ name: z.string().trim().min(1).max(120), siteIds: z.array(z.string()).min(1).max(20) }),
);

@Controller('campaigns')
export class CampaignsController {
  constructor(
    private readonly campaigns: CampaignsService,
    private readonly events: CampaignEvents,
  ) {}

  @Post('preview')
  preview(@Body() body: unknown) {
    return this.campaigns.preview(parse(source, body));
  }

  @Post()
  async create(@Body() body: unknown) {
    const campaign = await this.campaigns.create(parse(create, body));
    return { id: campaign.id };
  }

  @Get()
  list() {
    return this.campaigns.list();
  }

  @Get(':id')
  get(@Param('id') id: string) {
    return this.campaigns.get(id);
  }

  @Post(':id/retry-failed')
  async retryFailed(@Param('id') id: string) {
    return { queued: await this.campaigns.retryFailed(id) };
  }

  /** Live updates of deliveries and the campaign status; a comment every 15 s keeps proxies from closing it */
  @Sse(':id/events')
  stream(@Param('id') id: string): Observable<{ type: string; data: unknown }> {
    return merge(this.events.stream(id), interval(15_000).pipe(map(() => ({ type: 'ping', data: {} }))));
  }
}
