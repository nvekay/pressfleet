import { api, ApiError } from '@/lib/api';

/** The campaign as JSON, for the page to resync after a dropped event stream */
export async function GET(_request: Request, ctx: RouteContext<'/api/campaigns/[id]'>) {
  const { id } = await ctx.params;
  try {
    return Response.json(await api.campaign(id), { headers: { 'cache-control': 'no-store' } });
  } catch (err) {
    return new Response('Not found', { status: err instanceof ApiError ? err.status : 502 });
  }
}
