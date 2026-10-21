import { type PostPayload, type PublishResult, signRequest, type SiteHealth } from '@pressfleet/shared';

export interface SiteConnection {
  url: string;
  username: string;
  applicationPassword: string;
  secret: string;
}

/**
 * A failed call to a site. `retryable` is false when sending the same request again cannot help (bad credentials,
 * a payload the plugin rejects): those fail at once instead of using up the retries.
 */
export class WpError extends Error {
  constructor(
    message: string,
    readonly status: number | null,
    readonly retryable: boolean,
    readonly code?: string,
  ) {
    super(message);
    this.name = 'WpError';
  }
}

/** 409: the plugin is still writing this item for an earlier attempt. 429 and 5xx: the site is busy or down */
const RETRYABLE_STATUSES = new Set([408, 409, 425, 429, 500, 502, 503, 504]);

/** Talks to the connector plugin: Application Password (Basic auth) plus an HMAC signature on every request */
export class WpClient {
  constructor(
    private readonly site: SiteConnection,
    private readonly timeoutMs = 30_000,
    private readonly now = () => Math.floor(Date.now() / 1000),
  ) {}

  health(): Promise<SiteHealth> {
    return this.call<SiteHealth>('GET', '/pressfleet/v1/health');
  }

  publish(payload: PostPayload): Promise<PublishResult> {
    return this.call<PublishResult>('POST', '/pressfleet/v1/posts', payload);
  }

  private async call<T>(method: 'GET' | 'POST', route: string, body?: unknown): Promise<T> {
    const raw = body === undefined ? '' : JSON.stringify(body);
    const timestamp = this.now();
    const basic = Buffer.from(`${this.site.username}:${this.site.applicationPassword}`).toString('base64');
    let res: Response;
    try {
      res = await fetch(`${this.site.url.replace(/\/+$/, '')}/wp-json${route}`, {
        method,
        headers: {
          authorization: `Basic ${basic}`,
          'content-type': 'application/json',
          accept: 'application/json',
          'x-pressfleet-timestamp': String(timestamp),
          'x-pressfleet-signature': signRequest(this.site.secret, timestamp, method, route, raw),
        },
        body: raw || undefined,
        signal: AbortSignal.timeout(this.timeoutMs),
        redirect: 'error',
      });
    } catch (err) {
      const reason =
        err instanceof Error && err.name === 'TimeoutError'
          ? `no answer in ${this.timeoutMs / 1000} s`
          : String((err as Error).cause ?? err);
      throw new WpError(`Site unreachable: ${reason}`, null, true);
    }

    const text = await res.text();
    let json: unknown;
    try {
      json = text ? JSON.parse(text) : null;
    } catch {
      // A maintenance page or a PHP fatal error instead of JSON
      throw new WpError(
        `Site answered ${res.status} with a non-JSON page`,
        res.status,
        res.status >= 500 || RETRYABLE_STATUSES.has(res.status),
      );
    }
    if (!res.ok) {
      const { code, message } = (json ?? {}) as { code?: string; message?: string };
      throw new WpError(message ?? `Site answered ${res.status}`, res.status, RETRYABLE_STATUSES.has(res.status), code);
    }
    return json as T;
  }
}
