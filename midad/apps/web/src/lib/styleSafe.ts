/**
 * تنقية الأنماط السطريّة الآتية من خارج المنصّة.
 *
 * ══ لماذا قُلبت القاعدة ══
 *
 * كانت **قائمة سماح**: لا يمرّ إلّا ما عُدّ. وهي الأسلم نظريًّا، لكنّها
 * في هذا الموضع كانت خطأً — لأنّها تُبنى ممّا رأيناه لا ممّا يُنتَج.
 *
 * بُنيت من قالب «نافس»: جداولُ وحشوٌ وألوان. فلمّا رُفع عرضٌ يبني صفحته
 * بالمرونة شُطبت مفرداته كلّها — ولا كلمةَ واحدةً منها كانت في القائمة.
 * فأضفتُ المرونة. ثمّ جاء `text-wrap` فأضفتُه. ثمّ `table-layout`.
 * وقال المالك — وهو محقّ —: «كلّ ما أضيف ملفًّا لازم أقول لك إنّه ملخبط».
 *
 * وذاك ليس عطبًا يُصلَح بإضافةٍ أخرى: هو **شكل الحلّ نفسه**. قائمةٌ
 * تُبنى بالاستقراء تلاحق ولا تلحق.
 *
 * ══ وما الذي يُخشى فعلًا ══
 *
 * أنماط ملفّ التصميم **وصفُ تخطيطٍ لا سلوك**: لا تُنفّذ شيفرةً ولا
 * تفتح اتّصالًا. والضرر منها محصورٌ في ثلاثة أبواب، وكلُّها يُغلق بالقيمة
 * لا بالاسم:
 *
 *   ① مورد خارجيّ — `url(...)`: يُحمّل من مستندٍ يفتحه آلاف المعلّمين،
 *      فيُفشي أنّهم فتحوه ومتى. يُمنع أيًّا كانت الخاصّيّة.
 *   ② خروجٌ من المستند — `position: fixed | sticky`: شريطٌ في قالبٍ يعلو
 *      على شريط أدوات المنصّة ولا يزول بالتمرير. فتُقيَّد القيم لا تُمنع
 *      الخاصّيّة: التصاميم تحتاج `absolute` و`relative`.
 *   ③ تنفيذٌ في متصفّحاتٍ قديمة — `expression(` و`javascript:`
 *      و`-moz-binding` و`behavior:`.
 *
 * وما عدا ذلك يمرّ. فالقالب لا يُرفع إلّا من لوحة الإدارة، والخطر ليس
 * خصمًا يتسلّل بل ملفًّا يحمل ما لم نتوقّعه.
 */

/** يُرفض أيًّا كانت الخاصّيّة: مواردُ خارجيّة، وتنفيذٌ، واستيراد */
const DANGEROUS = /(url\s*\(|expression\s*\(|javascript:|@import|behavior\s*:|-moz-binding|<|>)/i

/** أسماءٌ لا معنى لها في نمطٍ سطريّ، ووجودُها ريبة */
const DENIED = new Set([
  'behavior', '-moz-binding', 'content', 'src',
])

/**
 * قيودٌ على قيم خاصّيّاتٍ بعينها — لا على وجودها.
 *
 * `position` مسموحةٌ لأنّ التصاميم تحتاجها (شريطٌ فوق آخر، رقمُ صفحةٍ في
 * زاوية). لكنّ `fixed` و`sticky` تخرجان من المستند إلى إطار العرض.
 */
const VALUE_OK = (prop: string, value: string): boolean => {
  if (prop === 'position') return /^(static|relative|absolute)$/i.test(value.trim())
  return true
}

/** القيم المقبولة شكلًا: أرقامٌ ووحداتٌ ونسبٌ وألوانٌ وكلماتٌ مفتاحيّة */
const SAFE_VALUE = /^[#a-zA-Z0-9\s.,%()'"\/_+*-]+$/

export function sanitizeStyle(raw: string | null | undefined): string {
  if (!raw) return ''
  const out: string[] = []
  for (const decl of String(raw).split(';')) {
    const i = decl.indexOf(':')
    if (i < 0) continue
    const prop = decl.slice(0, i).trim().toLowerCase()
    const value = decl.slice(i + 1).trim()
    if (!prop || !value) continue

    /* اسمٌ لا معنى له في نمطٍ سطريّ، أو خاصّيّةٌ مخصّصة غير التي نحملها */
    if (DENIED.has(prop)) continue
    if (prop.startsWith('--') && prop !== '--mdd-tw') continue

    if (!VALUE_OK(prop, value)) continue
    if (DANGEROUS.test(prop) || DANGEROUS.test(value)) continue
    if (!SAFE_VALUE.test(value)) continue

    /* قيمةٌ بهذا الطول ليست تخطيطًا — والحدّ يمنع الانتفاخ لا الخطر.
       ورُفع من ١٢٠ إلى ٢٤٠: `grid-template-columns` و`box-shadow`
       المتعدّد يتجاوزان ١٢٠ في تصاميم حقيقيّة. */
    if (value.length > 240) continue
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
