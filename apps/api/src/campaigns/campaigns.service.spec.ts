import type { Campaign } from '../db/schema.js';
import { emptyCounts, timing } from './campaigns.service.js';

const campaign = (patch: Partial<Campaign>): Campaign => ({
  id: 'cmp_1',
  name: 'Test',
  source: 'csv',
  sourceRef: null,
  status: 'done',
  createdAt: new Date(0),
  startedAt: null,
  finishedAt: null,
  ...patch,
});

describe('timing', () => {
  it('is zero before the first delivery starts', () => {
    expect(timing(campaign({ status: 'running' }), emptyCounts(), [])).toEqual({ seconds: 0, postsPerMinute: 0 });
  });

  it('measures published posts per minute from the first start to the finish', () => {
    const counts = { ...emptyCounts(), published: 150, total: 150 };
    const result = timing(
      campaign({ startedAt: new Date('2026-10-21T10:00:00Z'), finishedAt: new Date('2026-10-21T10:00:30Z') }),
      counts,
      [],
    );
    expect(result).toEqual({ seconds: 30, postsPerMinute: 300 });
  });
});
