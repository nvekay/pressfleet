import { Body, Controller, Get, Headers, HttpCode, Post, type RawBodyRequest, Req } from '@nestjs/common';
import type { Request } from 'express';
import { z } from 'zod';
import { Public } from '../common/public.decorator.js';
import { parse } from '../common/zod.js';
import { LeadsService } from './leads.service.js';

const incoming = z.object({
  id: z.number().int().positive(),
  name: z.string().min(1).max(100),
  contact: z.string().min(1).max(120),
  message: z.string().max(2000),
  page: z.string().max(500),
  created_at: z.iso.datetime({ offset: true }),
});

@Controller('leads')
export class LeadsController {
  constructor(private readonly leads: LeadsService) {}

  /** Called by the connector plugin on each site; authenticated by the site's HMAC signature */
  @Public()
  @Post()
  @HttpCode(200)
  async receive(
    @Req() req: RawBodyRequest<Request>,
    @Body() body: unknown,
    @Headers('x-pressfleet-site') site?: string,
    @Headers('x-pressfleet-timestamp') timestamp?: string,
    @Headers('x-pressfleet-signature') signature?: string,
  ) {
    const from = await this.leads.verify({ site, timestamp, signature }, req.rawBody?.toString('utf8') ?? '');
    return this.leads.receive(from.id, from.name, parse(incoming, body));
  }

  @Get()
  list() {
    return this.leads.list();
  }
}
