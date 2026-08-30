import { admin, audit, fail, handle, isValidPhone, json, normalizePhone, phoneToAuthEmail, readBody, HttpError } from '../_shared/util.ts'

Deno.serve(handle(async (req) => {
  const b = await readBody(req)
  const accountType = b.account_type === 'school' ? 'school' : 'teacher'
  const phone = normalizePhone(b.phone)
  const fullName = String(b.full_name || '').trim()
  const password = String(b.password || '')
  const subscriberName = String(b.subscriber_name || fullName).trim()
  const roleKey = String(b.role_key || 'teacher')

  if (!isValidPhone(phone)) throw new HttpError('أدخل رقم جوّال صحيحًا من 10 أرقام يبدأ بـ 05')
  if (fullName.length < 3) throw new HttpError('اكتب الاسم كاملًا')
  if (password.length < 8) throw new HttpError('كلمة المرور 8 أحرف على الأقلّ')
  if (subscriberName.length < 3) throw new HttpError('اكتب اسم المشترك كاملًا')

  const db = admin()

  const { data: existing } = await db.from('profiles').select('id').eq('phone', phone).maybeSingle()
  if (existing) throw new HttpError('هذا الجوّال مسجّل مسبقًا', 409)

  const { data: role } = await db.from('roles').select('key').eq('key', roleKey).maybeSingle()
  const finalRole = role?.key || 'teacher'

  const { data: plan } = await db.from('plans').select('id, name_ar')
    .eq('account_type', accountType).eq('is_default', true).eq('is_active', true).maybeSingle()

  const { data: trialSetting } = await db.from('platform_settings').select('value').eq('key', 'trial').maybeSingle()
  const trialDays = Number((trialSetting?.value as any)?.days ?? 7) || 7

  const { data: created, error: userErr } = await db.auth.admin.createUser({
    email: phoneToAuthEmail(phone),
    password,
    email_confirm: true,
    user_metadata: { full_name: fullName, phone },
  })
  if (userErr || !created?.user) {
    if (/already|exists|registered/i.test(userErr?.message || '')) throw new HttpError('هذا الجوّال مسجّل مسبقًا', 409)
    throw new HttpError('تعذّر إنشاء الحساب — حاول مرّة أخرى')
  }
  const userId = created.user.id

  const trialEnds = new Date(Date.now() + trialDays * 86400000).toISOString()
  const { data: sub, error: subErr } = await db.from('subscribers').insert({
    name: subscriberName,
    account_type: accountType,
    city: b.city || null,
    school_type: accountType === 'school' ? (b.school_type || 'حكومية') : null,
    education_dept: b.education_dept || null,
    principal_name: accountType === 'school' ? fullName : null,
    contact_phone: phone,
    status: 'trial',
    trial_ends_at: trialEnds,
    plan_id: plan?.id ?? null,
  }).select().single()

  if (subErr || !sub) {
    await db.auth.admin.deleteUser(userId).catch(() => {})
    throw new HttpError('تعذّر إنشاء المشترك — حاول مرّة أخرى')
  }

  const { error: profErr } = await db.from('profiles').insert({
    id: userId,
    subscriber_id: sub.id,
    full_name: fullName,
    phone,
    email: b.email || null,
    role_key: finalRole,
    is_owner: true,
    status: 'active',
  })
  if (profErr) {
    await db.from('subscribers').delete().eq('id', sub.id)
    await db.auth.admin.deleteUser(userId).catch(() => {})
    throw new HttpError('تعذّر إنشاء ملفّ المستخدم — حاول مرّة أخرى')
  }

  await audit(db, {
    subscriber_id: sub.id, actor_id: userId, actor_name: fullName,
    event_type: 'signup',
    message_ar: `سجّل ${fullName} حسابًا جديدًا باسم ${subscriberName}`,
    meta: { account_type: accountType, role: finalRole },
  })

  return json({ ok: true, subscriber_id: sub.id, trial_ends_at: trialEnds })
}))
