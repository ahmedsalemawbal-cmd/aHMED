import { admin, audit, handle, json, readBody, requireUser, HttpError } from '../_shared/util.ts'

Deno.serve(handle(async (req) => {
  const db = admin()
  const caller = await requireUser(req, db)
  const b = await readBody(req)
  const action = String(b.action || '')

  if (!caller.profile.subscriber_id) throw new HttpError('لا مشترك مرتبط بهذا الحساب', 403)
  const subscriberId = caller.profile.subscriber_id

  const { data: pay } = await db.from('platform_settings').select('value').eq('key', 'payment_public').maybeSingle()
  const taxRate = Number((pay?.value as any)?.tax_rate ?? 0) || 0

  if (action === 'create_order') {
    if (!caller.profile.is_owner) throw new HttpError('طلب الاشتراك لصاحب الحساب وحده', 403)

    const { data: sub } = await db.from('subscribers').select('*').eq('id', subscriberId).single()
    const planId = b.plan_id || sub?.plan_id
    if (!planId) throw new HttpError('اختر باقةً أوّلًا')

    const { data: plan } = await db.from('plans').select('*').eq('id', planId).eq('is_active', true).maybeSingle()
    if (!plan) throw new HttpError('هذه الباقة غير متاحة')

    // فاتورة قائمة لم تُدفع لنفس الباقة — أعِدها بدل إنشاء أخرى
    const { data: open } = await db.from('invoices').select('*')
      .eq('subscriber_id', subscriberId).eq('plan_id', planId)
      .in('status', ['unpaid', 'under_review'])
      .order('issued_at', { ascending: false }).limit(1).maybeSingle()
    if (open) return json({ ok: true, invoice: open, plan, reused: true })

    const amount = Number(plan.price_sar) || 0
    const tax = Math.round(amount * taxRate * 100) / 100
    const { data: inv, error } = await db.from('invoices').insert({
      subscriber_id: subscriberId,
      plan_id: plan.id,
      description_ar: `${plan.name_ar} — ${plan.period_months} شهرًا`,
      amount_sar: amount,
      tax_rate: taxRate,
      tax_amount: tax,
      total_sar: amount + tax,
      status: 'unpaid',
    }).select().single()
    if (error || !inv) throw new HttpError('تعذّر إنشاء الفاتورة')

    await audit(db, {
      subscriber_id: subscriberId, actor_id: caller.userId, actor_name: caller.profile.full_name,
      event_type: 'invoice_created',
      message_ar: `أنشأ ${caller.profile.full_name} طلب اشتراك بالفاتورة ${inv.number}`,
      meta: { invoice_id: inv.id, plan: plan.key },
    })
    return json({ ok: true, invoice: inv, plan })
  }

  if (action === 'submit_transfer') {
    const invoiceId = String(b.invoice_id || '')
    const { data: inv } = await db.from('invoices').select('*').eq('id', invoiceId).maybeSingle()
    if (!inv || inv.subscriber_id !== subscriberId) throw new HttpError('لم نجد هذه الفاتورة', 404)
    if (inv.status === 'paid') throw new HttpError('هذه الفاتورة مدفوعة بالفعل')

    const { data: updated, error } = await db.from('invoices').update({
      status: 'under_review',
      submitted_at: new Date().toISOString(),
      receipt_url: b.receipt_url || null,
    }).eq('id', invoiceId).select().single()
    if (error) throw new HttpError('تعذّر إرسال الطلب')

    await audit(db, {
      subscriber_id: subscriberId, actor_id: caller.userId, actor_name: caller.profile.full_name,
      event_type: 'transfer_submitted',
      message_ar: `أبلغ ${caller.profile.full_name} عن تحويل الفاتورة ${inv.number}`,
      meta: { invoice_id: invoiceId },
    })
    return json({ ok: true, invoice: updated })
  }

  throw new HttpError('طلب غير معروف')
}))
