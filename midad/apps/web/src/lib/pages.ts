/**
 * شطر متن المستند عند فواصل الصفحات.
 *
 * المستند عندنا متنٌ واحدٌ متّصل، وفيه عُقَدٌ تحمل `data-page-break` تدلّ
 * على مواضع القطع. وثلاثة مواضع تحتاج الشطر نفسه: المعاينة في صفحة
 * القالب، ومصغّرة البطاقة، وتوليد الـPDF. فكُتب هنا مرّةً واحدة — إذ لو
 * تفرّق لاختلف: يُصلَح في موضعٍ ويبقى العطب في اثنين.
 */

/** مقاس A4 بالبكسل عند ٩٦ نقطةً في البوصة — وهي وحدة CSS. */
export const A4_PX = { w: 794, h: 1123 }

/**
 * يردّ متن كلّ صفحةٍ على حدة.
 *
 * والصفحة الفارغة تُطرح: فاصلان متتاليان — وهما يقعان حين يُحرَّر المتن —
 * يُنشئان صفحةً لا شيء فيها، وعرضُ ورقةٍ بيضاء فارغة يبدو عطبًا لا تصميمًا.
 */
export function splitPages(html: string): string[] {
  const src = (html || '').trim()
  if (!src) return []

  const holder = document.createElement('div')
  holder.innerHTML = src

  const groups: Node[][] = [[]]
  for (const node of Array.from(holder.childNodes)) {
    const el = node as Element
    if (el.nodeType === 1 && el.hasAttribute?.('data-page-break')) {
      groups.push([])
      continue
    }
    groups[groups.length - 1].push(node)
  }

  const out: string[] = []
  for (const g of groups) {
    const box = document.createElement('div')
    for (const n of g) box.appendChild(n.cloneNode(true))
    // فيها نصٌّ أو جدول؟ وإلّا فهي فراغٌ لا صفحة.
    if (!(box.textContent || '').trim() && !box.querySelector('table, img')) continue
    out.push(box.innerHTML)
  }

  // متنٌ بلا فواصل صفحةٌ واحدة، لا صفر
  return out.length ? out : [src]
}
