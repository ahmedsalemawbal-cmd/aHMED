import { admin, audit, handle, json, readBody, requireUser, HttpError } from '../_shared/util.ts'

Deno.serve(handle(async (req) => {
  const db = admin()
  const caller = await requireUser(req, db)
  if (!caller.isPlatformAdmin) throw new HttpError('هذه الشاشة لمالك المنصّة', 403)

  const b = await readBody(req)
  const action = String(b.action || '')
  const actorName = caller.profile.full_name || 'مالك المنصّة'

  if (action === 'confirm_payment') {
    const invoiceId = String(b.invoice_id || '')
    const { data: inv } = await db.from('invoices').select('*').eq('id', invoiceId).maybeSingle()
    if (!inv) throw new HttpError('لم نجد هذه الفاتورة', 404)
    if (inv.status === 'paid') throw new HttpError('هذه الفاتورة مؤكّدة بالفعل')

    const { data: plan } = await db.from('plans').select('*').eq('id', inv.plan_id).maybeSingle()
    const months = Number(plan?.period_months ?? 12)

    const { data: current } = await db.from('subscriptions').select('*')
      .eq('subscriber_id', inv.subscriber_id).eq('status', 'active')
      .order('ends_at', { ascending: false }).limit(1).maybeSingle()

    // التجديد يبدأ من نهاية الاشتراك الساري لا من اليوم — فلا تضيع أيّامٌ مدفوعة.
    const base = current && new Date(current.ends_at).getTime() > Date.now()
      ? new Date(current.ends_at) : new Date()
    const ends = new Date(base)
    ends.setMonth(ends.getMonth() + months)

    const { data: subscription, error: sErr } = await db.from('subscriptions').insert({
      subscriber_id: inv.subscriber_id,
      plan_id: inv.plan_id,
      status: 'active',
      starts_at: base.toISOString(),
      ends_at: ends.toISOString(),
      amount_sar: inv.total_sar,
    }).select().single()
    if (sErr) throw new HttpError('تعذّر فتح الاشتراك')

    if (current) await db.from('subscriptions').update({ status: 'expired' }).eq('id', current.id)

    await db.from('invoices').update({
      status: 'paid', paid_at: new Date().toISOString(),
      subscription_id: subscription.id,
      internal_note: b.note || null,
    }).eq('id', invoiceId)

    await db.from('subscribers').update({
      status: 'active', plan_id: inv.plan_id, suspended_reason: null,
    }).eq('id', inv.subscriber_id)

    await audit(db, {
      subscriber_id: inv.subscriber_id, actor_id: caller.userId, actor_name: actorName,
      event_type: 'payment_confirmed',
      message_ar: `أكّد ${actorName} دفع الفاتورة ${inv.number} وفتح الاشتراك حتى ${ends.toISOString().slice(0, 10)}`,
      meta: { invoice_id: invoiceId, subscription_id: subscription.id },
    })
    return json({ ok: true, subscription, ends_at: ends.toISOString() })
  }

  if (action === 'reject_payment') {
    const invoiceId = String(b.invoice_id || '')
    const reason = String(b.reason || '').trim()
    if (!reason) throw new HttpError('اكتب سبب الرفض — يصل إلى المشترك')
    const { data: inv } = await db.from('invoices').select('*').eq('id', invoiceId).maybeSingle()
    if (!inv) throw new HttpError('لم نجد هذه الفاتورة', 404)

    await db.from('invoices').update({ status: 'rejected', rejected_reason: reason }).eq('id', invoiceId)
    await audit(db, {
      subscriber_id: inv.subscriber_id, actor_id: caller.userId, actor_name: actorName,
      event_type: 'payment_rejected',
      message_ar: `رفض ${actorName} تحويل الفاتورة ${inv.number}: ${reason}`,
      meta: { invoice_id: invoiceId },
    })
    return json({ ok: true })
  }

  if (action === 'set_subscriber_status') {
    const id = String(b.subscriber_id || '')
    const status = ['trial', 'active', 'expired', 'suspended'].includes(b.status) ? b.status : null
    if (!status) throw new HttpError('حالة غير معروفة')
    const { data: sub } = await db.from('subscribers').select('name').eq('id', id).maybeSingle()
    if (!sub) throw new HttpError('لم نجد هذا المشترك', 404)

    await db.from('subscribers').update({
      status, suspended_reason: status === 'suspended' ? (b.reason || 'بقرار من الإدارة') : null,
    }).eq('id', id)

    await audit(db, {
      subscriber_id: id, actor_id: caller.userId, actor_name: actorName,
      event_type: 'subscriber_status',
      message_ar: `غيّر ${actorName} حالة ${sub.name} إلى ${status}`,
      meta: { status, reason: b.reason || null },
    })
    return json({ ok: true })
  }

  if (action === 'extend_trial') {
    const id = String(b.subscriber_id || '')
    const days = Math.max(1, Math.min(90, Number(b.days ?? 7)))
    const { data: sub } = await db.from('subscribers').select('*').eq('id', id).maybeSingle()
    if (!sub) throw new HttpError('لم نجد هذا المشترك', 404)

    const base = new Date(sub.trial_ends_at).getTime() > Date.now() ? new Date(sub.trial_ends_at) : new Date()
    const next = new Date(base.getTime() + days * 86400000)
    await db.from('subscribers').update({ trial_ends_at: next.toISOString(), status: 'trial' }).eq('id', id)

    await audit(db, {
      subscriber_id: id, actor_id: caller.userId, actor_name: actorName,
      event_type: 'trial_extended',
      message_ar: `مدّد ${actorName} تجربة ${sub.name} ${days} يومًا`,
      meta: { days },
    })
    return json({ ok: true, trial_ends_at: next.toISOString() })
  }

  if (action === 'set_plan') {
    const id = String(b.subscriber_id || '')
    const planId = String(b.plan_id || '')
    const { data: plan } = await db.from('plans').select('name_ar').eq('id', planId).maybeSingle()
    if (!plan) throw new HttpError('لم نجد هذه الباقة', 404)
    const { data: sub } = await db.from('subscribers').select('name').eq('id', id).maybeSingle()
    if (!sub) throw new HttpError('لم نجد هذا المشترك', 404)

    await db.from('subscribers').update({ plan_id: planId }).eq('id', id)
    await audit(db, {
      subscriber_id: id, actor_id: caller.userId, actor_name: actorName,
      event_type: 'plan_changed',
      message_ar: `غيّر ${actorName} باقة ${sub.name} إلى ${plan.name_ar}`,
      meta: { plan_id: planId },
    })
    return json({ ok: true })
  }

  if (action === 'create_invoice') {
    const id = String(b.subscriber_id || '')
    const planId = String(b.plan_id || '')
    const { data: plan } = await db.from('plans').select('*').eq('id', planId).maybeSingle()
    if (!plan) throw new HttpError('لم نجد هذه الباقة', 404)

    const { data: pay } = await db.from('platform_settings').select('value').eq('key', 'payment_public').maybeSingle()
    const taxRate = Number((pay?.value as any)?.tax_rate ?? 0) || 0
    const amount = Number(b.amount ?? plan.price_sar) || 0
    const tax = Math.round(amount * taxRate * 100) / 100

    const { data: inv, error } = await db.from('invoices').insert({
      subscriber_id: id, plan_id: planId,
      description_ar: `${plan.name_ar} — ${plan.period_months} شهرًا`,
      amount_sar: amount, tax_rate: taxRate, tax_amount: tax, total_sar: amount + tax,
      status: 'unpaid', internal_note: b.note || 'فاتورة أنشأتها الإدارة',
    }).select().single()
    if (error) throw new HttpError('تعذّر إنشاء الفاتورة')
    return json({ ok: true, invoice: inv })
  }

  throw new HttpError('طلب غير معروف')
}))
