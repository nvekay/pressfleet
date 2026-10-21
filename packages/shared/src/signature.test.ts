import { describe, expect, it } from 'vitest';
import { backoffDelay, signRequest } from './signature.js';

describe('signRequest', () => {
  it('matches the PHP plugin (reference computed with hash_hmac)', () => {
    expect(signRequest('test-secret', 1792500000, 'post', '/pressfleet/v1/posts', '{"external_id":"row-1"}')).toBe(
      '1a325074b24ec2beaccaa83797475ca75aa20efcd7bef2d8efd96243178bff9c',
    );
  });

  it('changes when any part changes', () => {
    const base = signRequest('s', 1, 'GET', '/pressfleet/v1/health', '');
    expect(signRequest('s', 2, 'GET', '/pressfleet/v1/health', '')).not.toBe(base);
    expect(signRequest('s', 1, 'POST', '/pressfleet/v1/health', '')).not.toBe(base);
    expect(signRequest('s', 1, 'GET', '/pressfleet/v1/posts', '')).not.toBe(base);
    expect(signRequest('t', 1, 'GET', '/pressfleet/v1/health', '')).not.toBe(base);
  });
});

describe('backoffDelay', () => {
  const noJitter = () => 0.5;

  it('doubles from the base and stops at the cap', () => {
    expect([1, 2, 3, 4, 5, 6].map((a) => backoffDelay(a, 5000, 60_000, noJitter))).toEqual([
      5000, 10_000, 20_000, 40_000, 60_000, 60_000,
    ]);
  });

  it('jitters by ±20%', () => {
    expect(backoffDelay(1, 5000, 60_000, () => 0)).toBe(4000);
    expect(backoffDelay(1, 5000, 60_000, () => 0.999999)).toBe(6000);
  });
});
