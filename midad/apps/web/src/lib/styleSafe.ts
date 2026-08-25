/**
 * تنقية الأنماط السطريّة الآتية من خارج المنصّة.
 *
 * القالب يراه كلّ المعلّمين، فما يدخله من ملفٍّ مرفوع يجب أن يكون محصورًا
 * في ما نفهمه. القاعدة: **قائمةُ سماحٍ لا قائمةَ منع** — فالمنع يُنسى منه
 * شيءٌ دائمًا، والسماح لا يمرّ منه إلّا ما عُدّ.
 *
 * ما يُمنع صراحةً وإن بدا بريئًا:
 * - `position` و`z-index` و`transform`: تُخرج العنصر من مجراه فيغطّي
 *   شريط الأدوات أو يخرج عن الورقة.
 * - `url(...)`: بابٌ لتحميل موردٍ خارجيّ من مستندٍ يفتحه آلاف المعلّمين.
 * - `expression(` و`javascript:`: تنفيذ شيفرة في متصفّحاتٍ قديمة.
 */

/** الخصائص المسموحة — كلّها مقيسةٌ على ما تُنتجه أدوات التصميم فعلًا */
const ALLOWED = new Set([
  // اللون والخلفيّة
  'color', 'background', 'background-color',
  // النصّ
  'font-size', 'font-weight', 'font-style', 'font-family',
  'line-height', 'letter-spacing', 'text-align', 'text-decoration',
  'white-space', 'direction', 'vertical-align',
  // الصندوق
  'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'width', 'min-width', 'max-width', 'height', 'min-height',
  'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
  'border-color', 'border-width', 'border-style', 'border-radius',
  'border-collapse', 'border-spacing', 'box-sizing',
  /* متغيّرٌ واحدٌ مسموح: عرض الجدول الذي أعلنه التصميم.
     وسببه أنّ عارض الجداول يمسح `width` من وسم الجدول بعد الرسم، ولا
     يمسّ المتغيّرات. فيُحمَل فيه ويُقرأ من الورقة. */
  '--mdd-tw',
])

/** قيمٌ ترفض دائمًا مهما كانت الخاصّيّة */
const DANGEROUS = /(url\s*\(|expression\s*\(|javascript:|@import|behavior\s*:|-moz-binding)/i

/** الأطوال المقبولة: أرقامٌ بوحداتٍ معروفة، ونسبٌ مئويّة، وكلماتٌ مفتاحيّة */
const SAFE_VALUE = /^[#a-zA-Z0-9\s.,%()'"\/_-]+$/

export function sanitizeStyle(raw: string | null | undefined): string {
  if (!raw) return ''
  const out: string[] = []
  for (const decl of String(raw).split(';')) {
    const i = decl.indexOf(':')
    if (i < 0) continue
    const prop = decl.slice(0, i).trim().toLowerCase()
    const value = decl.slice(i + 1).trim()
    if (!prop || !value) continue
    if (!ALLOWED.has(prop)) continue
    if (DANGEROUS.test(value)) continue
    if (!SAFE_VALUE.test(value)) continue
    if (value.length > 120) continue          // قيمةٌ بهذا الطول ليست لونًا ولا حشوًا
    out.push(`${prop}: ${value}`)
  }
  return out.join('; ')
}

/** يقرأ خاصّيّةً واحدة من نمطٍ سطريّ — للأدوات التي تسأل «ما لون هذه الخليّة؟» */
export function readStyleProp(style: string | null | undefined, prop: string): string {
  if (!style) return ''
  for (const decl of String(style).split(';')) {
    const i = decl.indexOf(':')
    if (i < 0) continue
    if (decl.slice(0, i).trim().toLowerCase() === prop) return decl.slice(i + 1).trim()
  }
  return ''
}

/** يضبط خاصّيّةً في نمطٍ سطريّ، ويحذفها إن كانت القيمة فارغة */
export function setStyleProp(style: string | null | undefined, prop: string, value: string): string {
  const kept: string[] = []
  let done = false
  for (const decl of String(style || '').split(';')) {
    const i = decl.indexOf(':')
    if (i < 0) continue
    const p = decl.slice(0, i).trim().toLowerCase()
    if (p === prop) {
      if (value) { kept.push(`${prop}: ${value}`); done = true }
      continue                                   // القيمة الفارغة تحذف الخاصّيّة
    }
    kept.push(`${p}: ${decl.slice(i + 1).trim()}`)
  }
  if (value && !done) kept.push(`${prop}: ${value}`)
  return sanitizeStyle(kept.join('; '))
}
