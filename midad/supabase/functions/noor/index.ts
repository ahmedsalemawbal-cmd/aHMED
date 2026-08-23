import { admin, audit, handle, json, readBody, requireUser, subscriberState, HttpError } from '../_shared/util.ts'

function makeKey(): string {
  const bytes = new Uint8Array(18)
  crypto.getRandomValues(bytes)
  const b64 = btoa(String.fromCharCode(...bytes)).replace(/[+/=]/g, '')
  return `MDD-${b64.slice(0, 8).toUpperCase()}-${b64.slice(8, 16).toUpperCase()}-${b64.slice(16, 24).toUpperCase()}`
}

Deno.serve(handle(async (req) => {
  const db = admin()
  const b = await readBody(req)
  const action = String(b.action || '')

  /* ---------- الإضافة: لا JWT، بل مفتاح الربط ---------- */
  if (action === 'ingest' || action === 'verify_key') {
    const key = String(req.headers.get('x-midad-key') || b.key || '').trim()
    if (!key) throw new HttpError('مفتاح الربط مفقود', 401)

    const { data: lk } = await db.from('link_keys').select('*').eq('key', key).maybeSingle()
    if (!lk) throw new HttpError('مفتاح الربط غير صحيح', 401)
    if (lk.revoked_at) throw new HttpError('أُلغي هذا المفتاح — أنشئ مفتاحًا جديدًا من مِداد', 401)
    if (new Date(lk.expires_at).getTime() < Date.now()) throw new HttpError('انتهت صلاحية المفتاح — جدّده من مِداد', 401)

    const { data: sub } = await db.from('subscribers').select('*').eq('id', lk.subscriber_id).maybeSingle()
    if (!sub) throw new HttpError('لم نجد المشترك', 404)

    const state = subscriberState(sub as any)
    if (state === 'suspended') throw new HttpError('حساب المشترك موقوف', 403)

    await db.from('link_keys').update({ last_used_at: new Date().toISOString() }).eq('id', lk.id)

    if (action === 'verify_key') {
      const { data: prof } = await db.from('profiles').select('full_name').eq('id', lk.user_id).maybeSingle()
      return json({ ok: true, subscriber: sub.name, user: prof?.full_name || '', state })
    }

    if (state === 'expired') throw new HttpError('انتهى اشتراكك — جدّده لتنزيل الجداول', 402)

    const title = String(b.title || 'جدول من نور').trim().slice(0, 160)
    const columns: string[] = Array.isArray(b.columns) ? b.columns.map((c: any) => String(c).slice(0, 120)) : []
    const rowsRaw: any[] = Array.isArray(b.rows) ? b.rows : []
    if (!columns.length) throw new HttpError('لم نجد أعمدةً في هذا الجدول')
    if (rowsRaw.length > 20000) throw new HttpError('الجدول أكبر من الحدّ المسموح (20000 صفّ)')

    const rows = rowsRaw.map((r) =>
      Array.isArray(r) ? r.map((c: any) => String(c ?? '').slice(0, 500)) : [])

    const { data: table, error } = await db.from('noor_tables').insert({
      subscriber_id: lk.subscriber_id,
      owner_id: lk.user_id,
      title, columns, rows,
      row_count: rows.length,
      source_url: b.source_url ? String(b.source_url).slice(0, 500) : null,
    }).select('id, title, row_count').single()
    if (error) throw new HttpError('تعذّر حفظ الجدول')

    await db.from('noor_ingest_log').insert({
      subscriber_id: lk.subscriber_id, user_id: lk.user_id,
      table_id: table.id, title, row_count: rows.length,
    })
    await audit(db, {
      subscriber_id: lk.subscriber_id, actor_id: lk.user_id,
      event_type: 'noor_ingest',
      message_ar: `نُزِّل جدول «${title}» من نور بـ ${rows.length} صفًّا`,
      meta: { table_id: table.id },
    })
    return json({ ok: true, table })
  }

  /* ---------- المشترك: يحتاج جلسة ---------- */
  const caller = await requireUser(req, db)
  const subscriberId = caller.profile.subscriber_id
  if (!subscriberId) throw new HttpError('لا مشترك مرتبط بهذا الحساب', 403)

  if (action === 'create_key') {
    await db.from('link_keys').update({ revoked_at: new Date().toISOString() })
      .eq('user_id', caller.userId).is('revoked_at', null)

    const key = makeKey()
    const { data, error } = await db.from('link_keys').insert({
      subscriber_id: subscriberId, user_id: caller.userId, key,
      expires_at: new Date(Date.now() + 90 * 86400000).toISOString(),
    }).select().single()
    if (error) throw new HttpError('تعذّر إنشاء المفتاح')

    await audit(db, {
      subscriber_id: subscriberId, actor_id: caller.userId, actor_name: caller.profile.full_name,
      event_type: 'noor_key_created',
      message_ar: `أنشأ ${caller.profile.full_name} مفتاح ربطٍ جديدًا لنور`,
    })
    return json({ ok: true, link_key: data })
  }

  if (action === 'revoke_key') {
    await db.from('link_keys').update({ revoked_at: new Date().toISOString() })
      .eq('user_id', caller.userId).is('revoked_at', null)
    await audit(db, {
      subscriber_id: subscriberId, actor_id: caller.userId, actor_name: caller.profile.full_name,
      event_type: 'noor_key_revoked',
      message_ar: `ألغى ${caller.profile.full_name} مفتاح ربط نور`,
    })
    return json({ ok: true })
  }

  throw new HttpError('طلب غير معروف')
}))
