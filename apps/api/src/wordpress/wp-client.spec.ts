import { signRequest } from '@pressfleet/shared';
import { WpClient, WpError } from './wp-client.js';

const site = { url: 'https://site.test/', username: 'admin', applicationPassword: 'app pass', secret: 'shh' };

function respond(status: number, body: unknown, raw = false) {
  return vi.fn().mockResolvedValue(new Response(raw ? (body as string) : JSON.stringify(body), { status }));
}

async function failure(promise: Promise<unknown>): Promise<WpError> {
  try {
    await promise;
  } catch (err) {
    return err as WpError;
  }
  throw new Error('expected a failure');
}

describe('WpClient', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('sends Basic auth and the HMAC signature of the exact body', async () => {
    const fetch = respond(201, { id: 1, action: 'created' });
    vi.stubGlobal('fetch', fetch);
    await new WpClient(site, 1000, () => 1700000000).publish({ external_id: 'a', title: 'T', content: 'C' });

    const [url, init] = fetch.mock.calls[0] as [string, RequestInit & { headers: Record<string, string> }];
    expect(url).toBe('https://site.test/wp-json/pressfleet/v1/posts');
    expect(init.headers.authorization).toBe(`Basic ${Buffer.from('admin:app pass').toString('base64')}`);
    expect(init.headers['x-pressfleet-signature']).toBe(
      signRequest('shh', 1700000000, 'POST', '/pressfleet/v1/posts', init.body as string),
    );
  });

  it.each([
    [503, true],
    [502, true],
    [429, true],
    [409, true],
    [400, false],
    [401, false],
    [403, false],
    [404, false],
  ])('status %i is retryable: %s', async (status, retryable) => {
    vi.stubGlobal('fetch', respond(status, { code: 'x', message: 'Nope' }));
    const err = await failure(new WpClient(site).health());
    expect(err).toBeInstanceOf(WpError);
    expect(err.retryable).toBe(retryable);
    expect(err.message).toBe('Nope');
  });

  it('retries a network failure and an HTML error page', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('fetch failed')));
    expect((await failure(new WpClient(site).health())).retryable).toBe(true);

    vi.stubGlobal('fetch', respond(500, '<html>Fatal error</html>', true));
    const err = await failure(new WpClient(site).health());
    expect(err.retryable).toBe(true);
    expect(err.message).toContain('non-JSON');
  });
});
