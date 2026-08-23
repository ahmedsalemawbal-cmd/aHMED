import { admin, audit, handle, isValidPhone, json, normalizePhone, phoneToAuthEmail, readBody, requireUser, HttpError } from '../_shared/util.ts'

Deno.serve(handle(async (req) => {
  const db = admin()
  const caller = await requireUser(req, db)
  const b = await readBody(req)
  const action = String(b.action || '')

  const subscriberId = caller.profile.subscriber_id
  if (!subscriberId) throw new HttpError('لا مشترك مرتبط بهذا الحساب', 403)
  if (!caller.profile.is_owner && !caller.isPlatformAdmin) throw new HttpError('إدارة الفريق لصاحب الاشتراك وحده', 403)

  const { data: sub } = await db.from('subscribers').select('*, plans(*)').eq('id', subscriberId).single()
  const plan = (sub as any)?.plans

  if (action === 'add_member') {
    if ((sub as any)?.account_type === 'teacher') throw new HttpError('اشتراك المعلّم لشخصٍ واحد — ارفع باقتك لإضافة أعضاء')

    const seats = Number(plan?.seats ?? 1)
    const { count } = await db.from('profiles').select('id', { count: 'exact', head: true })
      .eq('subscriber_id', subscriberId).eq('status', 'active')
    if ((count ?? 0) >= seats) throw new HttpError(`اكتملت مقاعد باقتك (${seats}) — ارفع باقتك لإضافة أعضاء`)

    const phone = normalizePhone(b.phone)
    const fullName = String(b.full_name || '').trim()
    const password = String(b.password || '')
    if (!isValidPhone(phone)) throw new HttpError('أدخل رقم جوّال صحيحًا من 10 أرقام يبدأ بـ 05')
    if (fullName.length < 3) throw new HttpError('اكتب اسم العضو كاملًا')
    if (password.length < 8) throw new HttpError('كلمة المرور 8 أحرف على الأقلّ')

    const { data: exists } = await db.from('profiles').select('id').eq('phone', phone).maybeSingle()
    if (exists) throw new HttpError('هذا الجوّال مسجّل مسبقًا', 409)

    const { data: created, error: uErr } = await db.auth.admin.createUser({
      email: phoneToAuthEmail(phone), password, email_confirm: true,
      user_metadata: { full_name: fullName, phone },
    })
    if (uErr || !created?.user) throw new HttpError('تعذّر إنشاء حساب العضو')

    const { data: prof, error: pErr } = await db.from('profiles').insert({
      id: created.user.id,
      subscriber_id: subscriberId,
      full_name: fullName,
      phone,
      email: b.email || null,
      role_key: String(b.role_key || 'teacher'),
      is_owner: false,
      status: 'active',
    }).select().single()
    if (pErr) {
      await db.auth.admin.deleteUser(created.user.id).catch(() => {})
      throw new HttpError('تعذّر إضافة العضو')
    }

    await audit(db, {
      subscriber_id: subscriberId, actor_id: caller.userId, actor_name: caller.profile.full_name,
      event_type: 'member_added',
      message_ar: `أضاف ${caller.profile.full_name} العضو ${fullName}`,
      meta: { member_id: prof.id },
    })
    return json({ ok: true, member: prof })
  }

  if (action === 'reset_password') {
    const userId = String(b.user_id || '')
    const password = String(b.password || '')
    if (password.length < 8) throw new HttpError('كلمة المرور 8 أحرف على الأقلّ')

    const { data: member } = await db.from('profiles').select('*').eq('id', userId).maybeSingle()
    if (!member || member.subscriber_id !== subscriberId) throw new HttpError('لم نجد هذا العضو', 404)

    const { error } = await db.auth.admin.updateUserById(userId, { password })
    if (error) throw new HttpError('تعذّر تغيير كلمة المرور')

    await audit(db, {
      subscriber_id: subscriberId, actor_id: caller.userId, actor_name: caller.profile.full_name,
      event_type: 'member_password_reset',
      message_ar: `أعاد ${caller.profile.full_name} تعيين كلمة مرور ${member.full_name}`,
      meta: { member_id: userId },
    })
    return json({ ok: true })
  }

  if (action === 'set_member_status') {
    const userId = String(b.user_id || '')
    const status = b.status === 'suspended' ? 'suspended' : 'active'
    const { data: member } = await db.from('profiles').select('*').eq('id', userId).maybeSingle()
    if (!member || member.subscriber_id !== subscriberId) throw new HttpError('لم نجد هذا العضو', 404)
    if (member.is_owner) throw new HttpError('لا يمكن إيقاف صاحب الاشتراك')

    await db.from('profiles').update({ status }).eq('id', userId)
    await audit(db, {
      subscriber_id: subscriberId, actor_id: caller.userId, actor_name: caller.profile.full_name,
      event_type: 'member_status',
      message_ar: `${status === 'suspended' ? 'أوقف' : 'أعاد تفعيل'} ${caller.profile.full_name} العضو ${member.full_name}`,
      meta: { member_id: userId, status },
    })
    return json({ ok: true })
  }

  throw new HttpError('طلب غير معروف')
}))
