/**
 * نداءُ الذكاء الاصطناعيّ — مزوّدان خلف بابٍ واحد.
 *
 * يختار المالكُ من إعدادات المنصّة: Claude أو ChatGPT. والفرق بينهما في
 * العنوان والترويسة وشكل الردّ لا في شيءٍ يخصّ مِداد. فلو تُرك كلُّ
 * مُنادٍ يعرف ذلك بنفسه، لزم أن يُبدَّل في كلّ موضعٍ عند كلّ تبديل — ولن
 * يُبدَّل في كلّها، فيبقى موضعٌ ينادي المزوّد القديم بمفتاح الجديد.
 *
 *     ما يتبدّل في مكانٍ واحدٍ لا يُنسى بعضُه.
 *
 * والمفتاحُ لا يخرج من هنا: يُقرأ بمفتاح الخدمة، ويُنادى به، ولا يُعاد
 * في ردٍّ ولا يُكتب في سجلّ.
 */

import { SupabaseClient } from 'https://esm.sh/@supabase/supabase-js@2.45.4'
import { HttpError } from './util.ts'

export interface AiConfig {
  provider: 'anthropic' | 'openai'
  model: string
  apiKey: string
  cfg: Record<string, any>
}

export interface AiReply {
  text: string
  tokensIn: number
  tokensOut: number
}

/** يقرأ الإعداد والمفتاح — ويرمي رسالةً تدلّ على الإصلاح لا على العطب. */
export async function loadAi(db: SupabaseClient): Promise<AiConfig> {
  const { data: row } = await db.from('platform_settings').select('value').eq('key', 'ai').maybeSingle()
  const cfg = ((row?.value as any) || {}) as Record<string, any>
  if (cfg.enabled === false) throw new HttpError('الذكاء الاصطناعيّ موقوفٌ مؤقّتًا')

  const { data: secret } = await db.from('platform_settings').select('value').eq('key', 'ai_secret').maybeSingle()
  /* ومتغيّرُ البيئة احتياطٌ لا أصل: يعمل قبل أن يضبط المالكُ مفتاحه. */
  const apiKey = String(
    (secret?.value as any)?.api_key
    || Deno.env.get('ANTHROPIC_API_KEY')
    || Deno.env.get('OPENAI_API_KEY')
    || '',
  ).trim()

  if (!apiKey) {
    throw new HttpError(
      'لم يُضبط مفتاح الذكاء الاصطناعيّ بعد — يضيفه مالك المنصّة من: إعدادات المنصّة ← الذكاء',
      503,
    )
  }

  const provider = cfg.provider === 'openai' ? 'openai' : 'anthropic'
  const fallback = provider === 'openai' ? 'gpt-4o' : 'claude-sonnet-4-5'
  return { provider, model: String(cfg.model || fallback), apiKey, cfg }
}

/**
 * ولا يُطبع المفتاح في سجلٍّ أبدًا — ولو ردّه المزوّد في رسالة خطئه.
 * والسجلّات تُقرأ من لوحاتٍ لا يملكها من يملك المفتاح.
 */
function scrub(s: string, key: string): string {
  const out = key.length > 8 ? s.split(key).join('sk-…محجوب') : s
  return out.replace(/sk-[A-Za-z0-9_\-]{16,}/g, 'sk-…محجوب')
}

export async function callAi(
  ai: AiConfig,
  opts: { system: string; prompt: string; maxTokens?: number },
): Promise<AiReply> {
  const maxTokens = opts.maxTokens ?? 1400

  const res = ai.provider === 'openai'
    ? await callOpenAi(ai, opts, maxTokens)
    : await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-api-key': ai.apiKey,
        'anthropic-version': '2023-06-01',
      },
      body: JSON.stringify({
        model: ai.model,
        max_tokens: maxTokens,
        system: opts.system,
        messages: [{ role: 'user', content: opts.prompt }],
      }),
    })

  if (!res.ok) {
    const detail = scrub(await res.text().catch(() => ''), ai.apiKey)
    console.error('ai error', ai.provider, res.status, detail.slice(0, 400))
    if (res.status === 401 || res.status === 403) {
      throw new HttpError('مفتاح الذكاء الاصطناعيّ غير صالح — راجعه في إعدادات المنصّة', 503)
    }
    if (res.status === 429) throw new HttpError('الخدمة مزدحمة الآن — حاول بعد قليل', 429)
    /* ٤٠٤ عند المزوّد غالبًا اسمُ نموذجٍ لم يعد موجودًا — والرسالة تدلّ عليه. */
    if (res.status === 404) {
      throw new HttpError(`النموذج «${ai.model}» غير متاحٍ عند المزوّد — بدّله من إعدادات المنصّة`, 503)
    }
    throw new HttpError('تعذّر التوليد — حاول مرّة أخرى', 502)
  }

  const out = await res.json()

  if (ai.provider === 'openai') {
    const text = String(out?.choices?.[0]?.message?.content || '').trim()
    if (!text) throw new HttpError('لم يصل نصٌّ من الخدمة — حاول مرّة أخرى', 502)
    return {
      text,
      tokensIn: Number(out?.usage?.prompt_tokens ?? 0),
      tokensOut: Number(out?.usage?.completion_tokens ?? 0),
    }
  }

  const text = (out?.content || [])
    .filter((c: any) => c.type === 'text').map((c: any) => c.text).join('\n').trim()
  if (!text) throw new HttpError('لم يصل نصٌّ من الخدمة — حاول مرّة أخرى', 502)
  return {
    text,
    tokensIn: Number(out?.usage?.input_tokens ?? 0),
    tokensOut: Number(out?.usage?.output_tokens ?? 0),
  }
}

/**
 * ونماذج OpenAI اختلفت في اسم حدّ المخرجات: القديمة `max_tokens`
 * والأحدث `max_completion_tokens`، والخطأ الذي يردّه المزوّد عند
 * الخلط ٤٠٠ لا يدلّ عليه إلّا في متنه.
 *
 * فيُجرَّب الأوّل، وإن ردّ المزوّد باسم الثاني أُعيد النداء به. ولا
 * يُثبَّت أحدهما: تثبيتُه يكسر نصف النماذج أيًّا كان المثبَّت.
 */
async function callOpenAi(
  ai: AiConfig,
  opts: { system: string; prompt: string },
  maxTokens: number,
): Promise<Response> {
  const send = (limitKey: string) => fetch('https://api.openai.com/v1/chat/completions', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${ai.apiKey}` },
    body: JSON.stringify({
      model: ai.model,
      [limitKey]: maxTokens,
      messages: [
        { role: 'system', content: opts.system },
        { role: 'user', content: opts.prompt },
      ],
    }),
  })

  const first = await send('max_tokens')
  if (first.status !== 400) return first

  /* والمتنُ يُقرأ مرّةً واحدةً — فيُستنسخ قبل قراءته لئلّا يضيع الردّ
     الأصليّ إن لم يكن هذا سببَ الرفض. */
  const body = await first.clone().text().catch(() => '')
  if (!/max_completion_tokens/.test(body)) return first
  return send('max_completion_tokens')
}
