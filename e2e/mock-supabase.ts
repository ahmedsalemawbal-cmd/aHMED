// A small stand-in for the Supabase REST API so UI states can be tested
// without the network: Playwright intercepts *.supabase.co and answers from
// fixtures. The real backend is exercised by tests-api and the main-flow spec.
import type { Page, Request, Route } from '@playwright/test';
import { e2eEnv } from './helpers';

export const USER_ID = '11111111-1111-4111-8111-111111111111';

function b64url(s: string) {
  return Buffer.from(s).toString('base64url');
}

/** Puts a signed-in session in localStorage before the app boots. */
export async function fakeSignIn(page: Page) {
  const url = new URL(e2eEnv('VITE_SUPABASE_URL'));
  const ref = url.hostname.split('.')[0] ?? '';
  const exp = Math.floor(Date.now() / 1000) + 3600 * 24;
  const jwt = `${b64url(JSON.stringify({ alg: 'HS256', typ: 'JWT' }))}.${b64url(JSON.stringify({ sub: USER_ID, exp, role: 'authenticated', aud: 'authenticated' }))}.sig`;
  const session = {
    access_token: jwt,
    refresh_token: 'fake-refresh',
    token_type: 'bearer',
    expires_in: 86400,
    expires_at: exp,
    user: { id: USER_ID, aud: 'authenticated', role: 'authenticated', email: 'e2e-a@maidani.test', app_metadata: {}, user_metadata: {}, created_at: new Date().toISOString() },
  };
  await page.addInitScript(
    ([key, value]) => {
      window.localStorage.setItem(key, value);
    },
    [`sb-${ref}-auth-token`, JSON.stringify(session)] as const,
  );
}

export type Handler = (table: string, req: Request, params: URLSearchParams) => { status?: number; body?: unknown; count?: number; delayMs?: number } | undefined;

export interface MockLog {
  writes: { method: string; table: string; params: string; body: unknown }[];
}

/** Routes every REST call to `handler`; unknown calls return an empty list. */
export async function mockRest(page: Page, handler: Handler): Promise<MockLog> {
  const log: MockLog = { writes: [] };
  await page.route('**/rest/v1/**', async (route: Route) => {
    const req = route.request();
    const u = new URL(req.url());
    const table = u.pathname.split('/rest/v1/')[1] ?? '';
    if (req.method() === 'PATCH' || req.method() === 'POST' || req.method() === 'DELETE') {
      const posted: unknown = req.postDataJSON();
      log.writes.push({ method: req.method(), table, params: u.search, body: posted });
    }
    const r = handler(table, req, u.searchParams) ?? {};
    if (r.delayMs) await new Promise((res) => setTimeout(res, r.delayMs));
    const status = r.status ?? 200;
    const accept = req.headers()['accept'] ?? '';
    let body: unknown = r.body ?? [];
    if (accept.includes('vnd.pgrst.object') && Array.isArray(body)) {
      const rows: unknown[] = body;
      body = rows[0] ?? null;
    }
    const count = r.count ?? (Array.isArray(body) ? body.length : 1);
    await route.fulfill({
      status,
      contentType: 'application/json',
      headers: { 'content-range': `0-${Math.max(0, count - 1).toString()}/${count.toString()}`, 'access-control-expose-headers': 'content-range' },
      body: req.method() === 'HEAD' ? '' : JSON.stringify(status >= 400 ? { message: 'mock failure', code: 'XX000' } : body),
    });
  });
  // Realtime and auth refresh are not needed for UI-state tests.
  await page.route('**/realtime/v1/**', (route) => route.abort());
  return log;
}
