import { admin, handle, json, readBody, requireUser, subscriberState, HttpError } from '../_shared/util.ts'

const TONE_AR: Record<string, string> = {
  formal:  'أعد صياغته بلغةٍ تربويةٍ إداريةٍ رسميّة كما تُكتب في وثائق وزارة التعليم السعودية.',
  simple:  'أعد صياغته بلغةٍ عربيةٍ واضحةٍ مبسّطة، بجملٍ قصيرة، مع بقاء الطابع الرسميّ.',
  shorter: 'أعد صياغته أقصر — احذف الحشو واحتفظ بالمعنى كاملًا، ولا تتجاوز ثلثي طول النصّ الأصلي.',
  longer:  'وسّعه بتفصيلٍ تربويّ مفيد (إجراءات وأمثلة واقعية من الميدان المدرسيّ) دون حشوٍ ولا تكرار.',
}

Deno.serve(handle(async (req) => {
  const db = admin()
  const caller = await requireUser(req, db)
  const b = await readBody(req)

  const subscriberId = caller.profile.subscriber_id
  if (!subscriberId) throw new HttpError('لا مشترك مرتبط بهذا الحساب', 403)

  const text = String(b.text || '').trim()
  if (!text) throw new HttpError('اكتب نصًّا أوّلًا ثمّ اطلب التحسين')
  if (text.length > 6000) throw new HttpError('النصّ أطول من الحدّ المسموح')

  const tone = TONE_AR[String(b.tone || 'formal')] ? String(b.tone) : 'formal'

  const { data: sub } = await db.from('subscribers').select('*, plans(*)').eq('id', subscriberId).single()
  const state = subscriberState(sub as any)
  if (state === 'suspended') throw new HttpError('حسابك موقوف', 403)
  if (state === 'expired') throw new HttpError('انتهى اشتراكك — جدّده لاستعمال التحسين', 402)

  const plan = (sub as any)?.plans
  const limit = Number((sub as any)?.ai_quota_override ?? plan?.ai_quota_monthly ?? 100)

  const monthStart = new Date()
  monthStart.setDate(1); monthStart.setHours(0, 0, 0, 0)

  const { count: used } = await db.from('ai_usage').select('id', { count: 'exact', head: true })
    .eq('subscriber_id', subscriberId).gte('created_at', monthStart.toISOString())
  if ((used ?? 0) >= limit) {
    throw new HttpError(`استهلكتَ حصّة التحسين لهذا الشهر (${limit}) — ارفع باقتك أو انتظر الشهر القادم`, 429)
  }

  const { data: aiCfg } = await db.from('platform_settings').select('value').eq('key', 'ai').maybeSingle()
  const cfg = (aiCfg?.value as any) || {}
  if (cfg.enabled === false) throw new HttpError('التحسين بالذكاء الاصطناعيّ موقوفٌ مؤقّتًا')

  const { count: globalCalls } = await db.from('ai_usage').select('id', { count: 'exact', head: true })
    .gte('created_at', monthStart.toISOString())
  const cap = Number(cfg.monthly_cap_calls ?? 20000)
  if ((globalCalls ?? 0) >= cap) throw new HttpError('بلغت المنصّة سقفها الشهريّ للتحسين — تواصل معنا')

  const { data: secret } = await db.from('platform_settings').select('value').eq('key', 'ai_secret').maybeSingle()
  const apiKey = String((secret?.value as any)?.api_key || Deno.env.get('ANTHROPIC_API_KEY') || '').trim()
  if (!apiKey) {
    throw new HttpError('لم يُضبط مفتاح الذكاء الاصطناعيّ بعد — يضيفه مالك المنصّة من: إعدادات المنصّة ← الذكاء', 503)
  }

  const model = String(cfg.model || 'claude-sonnet-4-5')
  const fieldLabel = String(b.field_label || 'حقل').slice(0, 120)

  const system = [
    'أنت محرّرٌ لغويّ تربويّ سعوديّ متخصّص في وثائق المدارس الرسمية.',
    'تُعيد صياغة نصّ المستخدم بالعربية الفصحى الإدارية، وتحافظ على كلّ المعاني والأرقام والأسماء كما هي.',
    'لا تخترع بياناتٍ ولا أسماءً ولا أرقامًا غير موجودة في نصّ المستخدم.',
    'أعد النصّ المصوغ وحده — بلا مقدّمة ولا تعليق ولا علامات تنسيق.',
  ].join(' ')

  const prompt = `الحقل: ${fieldLabel}\nالمطلوب: ${TONE_AR[tone]}\n\nالنصّ:\n${text}`

  const res = await fetch('https://api.anthropic.com/v1/messages', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'x-api-key': apiKey,
      'anthropic-version': '2023-06-01',
    },
    body: JSON.stringify({
      model,
      max_tokens: 1400,
      system,
      messages: [{ role: 'user', content: prompt }],
    }),
  })

  if (!res.ok) {
    const detail = await res.text().catch(() => '')
    console.error('anthropic error', res.status, detail.slice(0, 400))
    if (res.status === 401) throw new HttpError('مفتاح الذكاء الاصطناعيّ غير صالح — راجعه في إعدادات المنصّة', 503)
    if (res.status === 429) throw new HttpError('الخدمة مزدحمة الآن — حاول بعد قليل', 429)
    throw new HttpError('تعذّر التوليد — نصّك سليم كما هو، حاول مرّة أخرى', 502)
  }

  const out = await res.json()
  const improved = (out?.content || []).filter((c: any) => c.type === 'text').map((c: any) => c.text).join('\n').trim()
  if (!improved) throw new HttpError('لم يصل نصٌّ من الخدمة — حاول مرّة أخرى', 502)

  await db.from('ai_usage').insert({
    subscriber_id: subscriberId,
    user_id: caller.userId,
    document_id: b.document_id || null,
    field_key: b.field_key || null,
    tone,
    tokens_in: Number(out?.usage?.input_tokens ?? 0),
    tokens_out: Number(out?.usage?.output_tokens ?? 0),
  })

  return json({ text: improved, used: (used ?? 0) + 1, limit })
}))
