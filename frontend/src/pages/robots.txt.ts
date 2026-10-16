import type { APIRoute } from 'astro';
export const GET: APIRoute = () => new Response(
  import.meta.env.PUBLIC_SITE_INDEXABLE === 'true'
    ? 'User-agent: *\nDisallow: /admin/\nDisallow: /api/\n'
    : 'User-agent: *\nDisallow: /\n',
  { headers: { 'Content-Type': 'text/plain; charset=utf-8' } },
);
