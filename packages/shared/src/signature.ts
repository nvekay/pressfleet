import { createHmac } from 'node:crypto';

/**
 * Signs a request to the connector plugin: HMAC-SHA256 (hex) of "{timestamp}.{METHOD}.{route}.{body}".
 * Must match PressFleet\Connector\Signature::sign() in the plugin.
 */
export function signRequest(secret: string, timestamp: number, method: string, route: string, body: string): string {
  return createHmac('sha256', secret).update(`${timestamp}.${method.toUpperCase()}.${route}.${body}`).digest('hex');
}

/** Delay before retry `attempt` (1-based): base, 2×, 4×… capped, with ±20% jitter so sites are not hit in waves */
export function backoffDelay(attempt: number, baseMs: number, maxMs: number, random = Math.random): number {
  const exact = Math.min(maxMs, baseMs * 2 ** Math.max(0, attempt - 1));
  return Math.round(exact * (0.8 + random() * 0.4));
}
