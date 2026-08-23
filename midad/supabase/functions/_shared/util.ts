import { createClient, SupabaseClient } from 'https://esm.sh/@supabase/supabase-js@2.45.4'

export const CORS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type, x-midad-key',
  'Access-Control-Allow-Methods': 'POST, GET, OPTIONS',
}

export function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...CORS, 'Content-Type': 'application/json; charset=utf-8' },
  })
}
export function fail(message: string, status = 400): Response {
  return json({ error: message }, status)
}
export function preflight(req: Request): Response | null {
  if (req.method === 'OPTIONS') return new Response('ok', { headers: CORS })
  return null
}

export function admin(): SupabaseClient {
  return createClient(
    Deno.env.get('SUPABASE_URL')!,
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
    { auth: { persistSession: false, autoRefreshToken: false } },
  )
}

export interface Caller {
  userId: string
  profile: {
    id: string; subscriber_id: string | null; full_name: string; phone: string
    role_key: string; is_owner: boolean; status: string
  }
  isPlatformAdmin: boolean
}

/** يتحقّق من هوية المستخدم من ترويسة Authorization ويجلب ملفّه. */
export async function requireUser(req: Request, db: SupabaseClient): Promise<Caller> {
  const auth = req.headers.get('Authorization') || ''
  const token = auth.replace(/^Bearer\s+/i, '').trim()
  if (!token) throw new HttpError('يلزم تسجيل الدخول', 401)
  const { data, error } = await db.auth.getUser(token)
  if (error || !data?.user) throw new HttpError('انتهت جلستك — سجّل الدخول مرّة أخرى', 401)

  const { data: profile } = await db.from('profiles')
    .select('id, subscriber_id, full_name, phone, role_key, is_owner, status')
    .eq('id', data.user.id).maybeSingle()

  const { data: adm } = await db.from('platform_admins')
    .select('user_id').eq('user_id', data.user.id).maybeSingle()

  if (!profile && !adm) throw new HttpError('لا ملفّ لهذا الحساب', 403)
  if (profile && profile.status === 'suspended') throw new HttpError('حسابك موقوف', 403)

  return {
    userId: data.user.id,
    profile: (profile as any) || { id: data.user.id, subscriber_id: null, full_name: 'مدير المنصّة', phone: '', role_key: 'general', is_owner: false, status: 'active' },
    isPlatformAdmin: !!adm,
  }
}

export class HttpError extends Error {
  status: number
  constructor(message: string, status = 400) { super(message); this.status = status }
}

export function handle(fn: (req: Request) => Promise<Response>) {
  return async (req: Request): Promise<Response> => {
    const pre = preflight(req)
    if (pre) return pre
    try {
      return await fn(req)
    } catch (e) {
      const err = e as HttpError
      console.error('midad function error:', err?.message, err)
      return fail(err?.message || 'حدث خللٌ غير متوقّع', err?.status || 500)
    }
  }
}

export function normalizePhone(raw: string): string {
  const d = String(raw || '').replace(/[^\d]/g, '')
  if (d.startsWith('966')) return '0' + d.slice(3)
  if (d.startsWith('00966')) return '0' + d.slice(5)
  if (d.length === 9 && d.startsWith('5')) return '0' + d
  return d
}
export function isValidPhone(raw: string): boolean {
  return /^05\d{8}$/.test(normalizePhone(raw))
}
export function phoneToAuthEmail(raw: string): string {
  return `p${normalizePhone(raw)}@users.midad.sa`
}

export async function audit(db: SupabaseClient, entry: {
  subscriber_id?: string | null; actor_id?: string | null; actor_name?: string | null
  event_type: string; message_ar: string; meta?: Record<string, unknown>
}) {
  await db.from('audit_log').insert({
    subscriber_id: entry.subscriber_id ?? null,
    actor_id: entry.actor_id ?? null,
    actor_name: entry.actor_name ?? null,
    event_type: entry.event_type,
    message_ar: entry.message_ar,
    meta: entry.meta ?? {},
  })
}

export async function readBody(req: Request): Promise<Record<string, any>> {
  try { return (await req.json()) ?? {} } catch { return {} }
}

/** حالة المشترك المحسوبة — التجربة تنتهي بمرور الوقت. */
export function subscriberState(sub: { status: string; trial_ends_at: string }): string {
  if (sub.status === 'suspended') return 'suspended'
  if (sub.status === 'active') return 'active'
  if (sub.status === 'expired') return 'expired'
  return new Date(sub.trial_ends_at).getTime() > Date.now() ? 'trial' : 'expired'
}
