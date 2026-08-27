/**
 * تركيبُ ملفّ الإنجاز — الذكاء يكتب، والشيفرةُ تُركّب.
 *
 * الموظّف يلتقط شواهدَه من الجوّال طولَ العام: صورةٌ من فعالية، شهادةٌ
 * مُنحها، ملاحظةٌ كتبها. ثمّ يأتي يونيو فيُطلب منه ملفُّ إنجازٍ منظَّمٌ
 * بمحاورَ ومقدّمةٍ وخاتمة.
 *
 * ═══ ولمَ لا يُخرج الذكاءُ الملفَّ HTML جاهزًا؟ ═══
 *
 * لأنّ قيمةَ مِداد أنّ الملفّ يخرج **بتصميم القالب المعتمَد** لا بتصميمٍ
 * يخترعه نموذج. ولو طُلب منه HTML لأعاد رسمَ كلّ شيء: خطوطًا وألوانًا
 * وحدودَ صفحات — فيخرج ملفٌّ صحيحُ المعنى غريبُ الشكل، ويُردّ.
 *
 *     الذكاءُ يكتب النثر ويوزّع الشواهد. والتصميمُ ليس رأيًا.
 *
 * فيردّ هذه الدالّةُ **خطّةً** لا صفحات: لكلّ محورٍ فقرةٌ مكتوبة وقائمةُ
 * معرّفاتِ الشواهد التي تخصّه. والتركيبُ بعدها حسابيٌّ لا احتماليّ:
 * يضع الشيفرةُ الفقرةَ تحت عنوان المحور والصورَ في شبكتها، بأنماط
 * القالب نفسها. فالنتيجة تُعاد بحذافيرها لو أُعيد التركيب.
 *
 * ولا يخترع شاهدًا: القائمةُ تُصفّى بعد الردّ على المعرّفات الحقيقيّة،
 * فما لم يكن في سجلّه لا يدخل ملفَّه.
 */

import { admin, handle, json, readBody, requireUser, subscriberState, HttpError } from '../_shared/util.ts'
import { callAi, loadAi } from '../_shared/ai.ts'

/** حدٌّ للشواهد في النداء الواحد: الطلبُ يكبر بعددها، ومئةٌ تكفي عامًا. */
const MAX_ITEMS = 120
/** وطولُ الملاحظة يُقتطع: الشاهدُ عنوانٌ وملاحظةٌ لا مقالة. */
const MAX_NOTE = 400

/**
 * محاورُ القالب من عناوينه — بلا DOM.
 *
 * والصفحةُ الأولى تُترك: هي غلافٌ عنوانُه اسمُ الملفّ لا محورٌ فيه.
 * (ونظيرتُها في المتصفّح `axesOf` تقرأ التصميمَ أيضًا؛ وهنا العناوين
 *  وحدها — فما يُرسل إلى النموذج تلميحٌ يُصحّحه المستعمل، لا عقد.)
 */
function axesOf(html: string): string[] {
  if (!html) return []
  const pages = html.split(/(?=<div[^>]*data-page)/i)
  const body = pages.length > 1 ? pages.slice(1).join('') : html

  const out: string[] = []
  const seen = new Set<string>()
  for (const m of body.matchAll(/<h([1-4])[^>]*>([\s\S]*?)<\/h\1>/gi)) {
    const t = m[2].replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ')
      .replace(/\s+/g, ' ').trim().replace(/[:：.]+$/, '')
    if (t.length < 4 || t.length > 100) continue
    if (/^[\d٠-٩\s.\-/]+$/.test(t)) continue
    if (seen.has(t)) continue
    seen.add(t); out.push(t)
    if (out.length >= 24) break
  }
  return out
}

Deno.serve(handle(async (req) => {
  const db = admin()
  const caller = await requireUser(req, db)
  const b = await readBody(req)

  const subscriberId = caller.profile.subscriber_id
  if (!subscriberId) throw new HttpError('لا مشترك مرتبط بهذا الحساب', 403)

  const year = String(b.year || '').trim()
  if (!year) throw new HttpError('حدّد العام الدراسيّ')

  const { data: sub } = await db.from('subscribers').select('*, plans(*)').eq('id', subscriberId).single()
  const state = subscriberState(sub as any)
  if (state === 'suspended') throw new HttpError('حسابك موقوف', 403)
  if (state === 'expired') throw new HttpError('انتهى اشتراكك — جدّده لتركيب ملفّ الإنجاز', 402)

  /* ═══ الحصّة — كحصّة التحسين، فالنداء نداء ═══ */
  const monthStart = new Date()
  monthStart.setDate(1); monthStart.setHours(0, 0, 0, 0)

  const plan = (sub as any)?.plans
  const limit = Number((sub as any)?.ai_quota_override ?? plan?.ai_quota_monthly ?? 100)
  const { count: used } = await db.from('ai_usage').select('id', { count: 'exact', head: true })
    .eq('subscriber_id', subscriberId).gte('created_at', monthStart.toISOString())
  if ((used ?? 0) >= limit) {
    throw new HttpError(`استهلكتَ حصّة الذكاء لهذا الشهر (${limit}) — ارفع باقتك أو انتظر الشهر القادم`, 429)
  }

  const ai = await loadAi(db)
  const { count: globalCalls } = await db.from('ai_usage').select('id', { count: 'exact', head: true })
    .gte('created_at', monthStart.toISOString())
  if ((globalCalls ?? 0) >= Number(ai.cfg.monthly_cap_calls ?? 20000)) {
    throw new HttpError('بلغت المنصّة سقفها الشهريّ للذكاء — تواصل معنا')
  }

  /* ═══ القالب — وحقُّ الدور يُتحقَّق هنا لا في الواجهة ═══ */
  const { data: tpl } = await db.from('templates')
    .select('id,title,content_html,role_keys,status')
    .eq('id', String(b.template_id || '')).maybeSingle()
  if (!tpl) throw new HttpError('لم يُعثر على القالب', 404)
  if ((tpl as any).status !== 'published') throw new HttpError('هذا القالب غير منشور', 403)

  const roleKeys: string[] = (tpl as any).role_keys || []
  if (roleKeys.length && !roleKeys.includes(caller.profile.role_key)) {
    throw new HttpError('هذا القالب مخصّصٌ لفئةٍ أخرى من الموظّفين', 403)
  }

  /* ═══ الشواهد — سجلُّ صاحبه وحده ═══ */
  const { data: rows } = await db.from('portfolio_items')
    .select('id,axis,title,note,kind,happened_on,file_mime')
    .eq('owner_id', caller.userId).eq('academic_year', year)
    .order('happened_on', { ascending: true }).limit(MAX_ITEMS)

  const items = (rows || []) as any[]
  if (!items.length) {
    throw new HttpError('لا شواهد في سجلّك لهذا العام — أضِف شواهدك أوّلًا من التطبيق', 400)
  }

  const axes = axesOf(String((tpl as any).content_html || ''))
  const KIND_AR: Record<string, string> = {
    photo: 'صورة', certificate: 'شهادة', file: 'مرفق', text: 'ملاحظة',
  }

  const ledger = items.map((it, i) => [
    `#${i + 1} [${it.id}]`,
    `النوع: ${KIND_AR[it.kind] || it.kind}`,
    `التاريخ: ${it.happened_on}`,
    it.axis ? `المحور الذي اختاره: ${it.axis}` : 'بلا محور',
    it.title ? `العنوان: ${it.title}` : '',
    it.note ? `الوصف: ${String(it.note).slice(0, MAX_NOTE)}` : '',
  ].filter(Boolean).join(' · ')).join('\n')

  const system = [
    'أنت كاتبٌ تربويٌّ سعوديٌّ يُعدّ ملفّات الإنجاز للعاملين في مدارس وزارة التعليم.',
    'تكتب بالعربية الفصحى الإدارية، بلغةٍ مهنيّةٍ رصينةٍ بلا مبالغة ولا إنشاء.',
    'قاعدتك الأولى: لا تخترع نشاطًا ولا رقمًا ولا اسمًا ولا تاريخًا ليس في سجلّ الشواهد.',
    'إن قلّت شواهدُ محورٍ فاكتب عنه بإيجازٍ صادق، ولا تملأ الفراغ بكلامٍ عامّ.',
    'تردّ بـ JSON صالحٍ وحده — بلا شرحٍ قبله ولا بعده ولا أسوار شيفرة.',
  ].join(' ')

  const prompt = `الموظّف: ${caller.profile.full_name} — الوظيفة: ${caller.profile.role_key}
العام الدراسيّ: ${year}
اسم الملفّ: ${(tpl as any).title}
محاور القالب: ${axes.length ? axes.join(' | ') : '(لا محاور في القالب — استنبط محاورَ مناسبةً من الشواهد نفسها)'}

سجلّ الشواهد (${items.length}):
${ledger}

اكتب JSON بهذا الشكل تمامًا:
{
  "intro": "مقدّمة الملفّ في فقرةٍ واحدة (٣٠–٦٠ كلمة)",
  "sections": [
    { "axis": "اسم المحور", "summary": "فقرةٌ تصف ما أُنجز فيه استنادًا إلى شواهده (٤٠–٩٠ كلمة)", "item_ids": ["المعرّف بين القوسين المربّعين"] }
  ],
  "conclusion": "خاتمةٌ موجزة (٢٥–٥٠ كلمة)"
}

قواعد:
- ضع كلَّ شاهدٍ في محورٍ واحدٍ لا أكثر، ولا تترك شاهدًا خارج المحاور.
- استعمل محاور القالب إن وُجدت، بأسمائها حرفيًّا. وما لا يناسب أيَّها ضعه في محورٍ أخيرٍ اسمه «أعمالٌ أخرى».
- item_ids هي المعرّفات بين [ ] حرفيًّا لا الأرقام التي تسبقها.
- لا تذكر في summary شاهدًا ليس في item_ids الخاصّة بالمحور نفسه.`

  const reply = await callAi(ai, { system, prompt, maxTokens: 4000 })

  await db.from('ai_usage').insert({
    subscriber_id: subscriberId,
    user_id: caller.userId,
    document_id: null,
    field_key: 'portfolio',
    tone: 'compose',
    tokens_in: reply.tokensIn,
    tokens_out: reply.tokensOut,
  })

  /* ═══ والردُّ يُصدَّق قبل أن يُصدَّق ═══
   *
   * النموذج قد يُحيط الـJSON بأسوار شيفرة، وقد يخترع معرّفًا، وقد يضع
   * شاهدًا في محورين. ولو مرّ شيءٌ من ذلك لظهر في ملفّ الموظّف شاهدٌ
   * لا وجود له — وهو أسوأ من ألّا يُركَّب الملفّ أصلًا.
   *
   *     ما يُبنى على ردٍّ لم يُصدَّق يُصدَّق كلُّه أو لا شيء منه.
   */
  const raw = reply.text.replace(/^\s*```(?:json)?\s*/i, '').replace(/\s*```\s*$/,'').trim()
  let parsed: any
  try { parsed = JSON.parse(raw) } catch {
    console.error('portfolio: JSON غير صالح', raw.slice(0, 300))
    throw new HttpError('لم يصل ردٌّ مفهومٌ من الذكاء — أعد المحاولة', 502)
  }

  const valid = new Set(items.map((i) => i.id))
  const placed = new Set<string>()
  const sections = (Array.isArray(parsed.sections) ? parsed.sections : [])
    .map((s: any) => {
      const ids = (Array.isArray(s.item_ids) ? s.item_ids : [])
        .map(String)
        .filter((id: string) => valid.has(id) && !placed.has(id))
      ids.forEach((id: string) => placed.add(id))
      return {
        axis: String(s.axis || '').trim().slice(0, 120),
        summary: String(s.summary || '').trim(),
        item_ids: ids,
      }
    })
    .filter((s: any) => s.axis && (s.summary || s.item_ids.length))

  /* وما أسقطه النموذج لا يُسقَط: شاهدُ الموظّف حقُّه أن يظهر. */
  const orphans = items.filter((i) => !placed.has(i.id)).map((i) => i.id)
  if (orphans.length) {
    sections.push({
      axis: 'أعمالٌ أخرى',
      summary: 'شواهدُ إضافيّةٌ من أعمال العام لم تندرج تحت المحاور السابقة.',
      item_ids: orphans,
    })
  }

  if (!sections.length) throw new HttpError('تعذّر تنظيم الشواهد — أعد المحاولة', 502)

  return json({
    intro: String(parsed.intro || '').trim(),
    conclusion: String(parsed.conclusion || '').trim(),
    sections,
    axes,
    counted: items.length,
    placed: placed.size + orphans.length,
    used: (used ?? 0) + 1,
    limit,
  })
}))
