import { admin, handle, json, readBody, requireUser, subscriberState, HttpError } from '../_shared/util.ts'
import { callAi, loadAi } from '../_shared/ai.ts'

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

  const ai = await loadAi(db)

  const { count: globalCalls } = await db.from('ai_usage').select('id', { count: 'exact', head: true })
    .gte('created_at', monthStart.toISOString())
  const cap = Number(ai.cfg.monthly_cap_calls ?? 20000)
  if ((globalCalls ?? 0) >= cap) throw new HttpError('بلغت المنصّة سقفها الشهريّ للتحسين — تواصل معنا')

  const fieldLabel = String(b.field_label || 'حقل').slice(0, 120)

  const system = [
    'أنت محرّرٌ لغويّ تربويّ سعوديّ متخصّص في وثائق المدارس الرسمية.',
    'تُعيد صياغة نصّ المستخدم بالعربية الفصحى الإدارية، وتحافظ على كلّ المعاني والأرقام والأسماء كما هي.',
    'لا تخترع بياناتٍ ولا أسماءً ولا أرقامًا غير موجودة في نصّ المستخدم.',
    'أعد النصّ المصوغ وحده — بلا مقدّمة ولا تعليق ولا علامات تنسيق.',
  ].join(' ')

  const prompt = `الحقل: ${fieldLabel}\nالمطلوب: ${TONE_AR[tone]}\n\nالنصّ:\n${text}`

  /* والمزوّدُ يُختار من الإعدادات لا يُثبَّت هنا: تثبيتُه يعني أنّ مالكًا
     اختار ChatGPT يُرسَل مفتاحُه إلى Anthropic فيُردّ بخطأٍ لا يدلّ عليه. */
  const reply = await callAi(ai, { system, prompt, maxTokens: 1400 })
  const improved = reply.text

  await db.from('ai_usage').insert({
    subscriber_id: subscriberId,
    user_id: caller.userId,
    document_id: b.document_id || null,
    field_key: b.field_key || null,
    tone,
    tokens_in: reply.tokensIn,
    tokens_out: reply.tokensOut,
  })

  return json({ text: improved, used: (used ?? 0) + 1, limit })
}))
