import { Module } from '@nestjs/common';
import { APP_GUARD } from '@nestjs/core';
import { CampaignsController } from './campaigns/campaigns.controller.js';
import { CampaignsService } from './campaigns/campaigns.service.js';
import { InternalKeyGuard } from './common/internal-key.guard.js';
import { SecretBoxModule } from './common/secret-box.module.js';
import { DbModule } from './db/db.module.js';
import { DemoController } from './demo/demo.controller.js';
import { DemoService } from './demo/demo.service.js';
import { HealthController } from './health/health.controller.js';
import { LeadsController } from './leads/leads.controller.js';
import { LeadsService } from './leads/leads.service.js';
import { CampaignEvents } from './publishing/campaign-events.js';
import { DeliveryRunner } from './publishing/delivery-runner.js';
import { PublishQueues } from './publishing/publish-queues.js';
import { QueueController } from './publishing/queue.controller.js';
import { SitesController } from './sites/sites.controller.js';
import { SitesService } from './sites/sites.service.js';

@Module({
  imports: [DbModule, SecretBoxModule],
  controllers: [
    HealthController,
    SitesController,
    CampaignsController,
    QueueController,
    LeadsController,
    DemoController,
  ],
  providers: [
    SitesService,
    LeadsService,
    DemoService,
    CampaignsService,
    CampaignEvents,
    DeliveryRunner,
    PublishQueues,
    { provide: APP_GUARD, useClass: InternalKeyGuard },
  ],
})
export class AppModule {}
