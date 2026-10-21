import { Injectable } from '@nestjs/common';
import { filter, map, type Observable, Subject } from 'rxjs';

export interface CampaignEvent {
  campaignId: string;
  type: 'delivery' | 'campaign';
  data: unknown;
}

/** In-process bus for live dashboard updates (the API and its queue workers run in one process) */
@Injectable()
export class CampaignEvents {
  private readonly bus = new Subject<CampaignEvent>();

  emit(event: CampaignEvent): void {
    this.bus.next(event);
  }

  /** Server-sent events for one campaign */
  stream(campaignId: string): Observable<{ type: string; data: unknown }> {
    return this.bus.pipe(
      filter((e) => e.campaignId === campaignId),
      map((e) => ({ type: e.type, data: e.data })),
    );
  }
}
