import { Controller, Get } from '@nestjs/common';
import { PublishQueues } from './publish-queues.js';

@Controller('queue')
export class QueueController {
  constructor(private readonly queues: PublishQueues) {}

  /** Active, waiting and delayed (backing off) jobs per site */
  @Get()
  counts() {
    return this.queues.counts();
  }
}
