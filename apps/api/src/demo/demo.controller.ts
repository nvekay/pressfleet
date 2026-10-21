import { Controller, ForbiddenException, Get, Post } from '@nestjs/common';
import { env } from '../config/env.js';
import { DemoService } from './demo.service.js';

@Controller()
export class DemoController {
  constructor(private readonly demo: DemoService) {}

  /** What the dashboard needs to know about this hub */
  @Get('config')
  config() {
    const e = env();
    return { demo: e.DEMO_MODE, resetCron: e.DEMO_MODE ? e.DEMO_RESET_CRON : null };
  }

  /** Runs the nightly demo reset now */
  @Post('demo/reset')
  reset() {
    if (!env().DEMO_MODE) throw new ForbiddenException('Only a demo hub can be reset');
    return this.demo.reset();
  }
}
