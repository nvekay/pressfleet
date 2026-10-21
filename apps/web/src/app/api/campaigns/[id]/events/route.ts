import { env } from '@/lib/env';

/** Relays the API's server-sent events to the browser; the internal key stays on the server */
export async function GET(request: Request, ctx: RouteContext<'/api/campaigns/[id]/events'>) {
  const { id } = await ctx.params;
  const { API_URL, API_INTERNAL_KEY } = env();
  const upstream = await fetch(`${API_URL}/campaigns/${encodeURIComponent(id)}/events`, {
    headers: { 'x-api-key': API_INTERNAL_KEY, accept: 'text/event-stream' },
    signal: request.signal,
    cache: 'no-store',
  }).catch(() => null);
  if (!upstream?.ok || !upstream.body) return new Response('Not available', { status: 502 });
  return new Response(upstream.body, {
    headers: {
      'content-type': 'text/event-stream',
      'cache-control': 'no-cache, no-transform',
      connection: 'keep-alive',
      // Compression and proxy buffering would hold events back until a chunk fills up
      'content-encoding': 'none',
      'x-accel-buffering': 'no',
    },
  });
}
