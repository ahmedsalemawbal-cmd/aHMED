/**
 * مصغّرة البطاقة تحمل حبرًا — لا مجرّد صندوقٍ موجود.
 *
 * وُلد هذا الفحص من عطبٍ **مرّ من تحت فحصٍ يرصده**. كان في `editor` سطرٌ
 * يتحقّق من المصغّرة، وكلّ ما يقوله:
 *
 *     T('مصغّرة البطاقة من المتن', thumb.found)
 *
 * و`found` معناها أنّ `.mdd-thumb-page` في الصفحة. وهي كانت في الصفحة
 * حقًّا — **وبيضاء**. فمرّ العطب، وفتح المالك مكتبته فوجد بطاقة قالبه
 * الجديد فارغة.
 *
 *     وجودُ الصندوق ليس رسمَ ما فيه.
 *
 * وسببُ البياض قصٌّ أعمى في `firstPage`: `html.slice(0, 14000)`. فقالبٌ
 * أوّلُ صفحةٍ فيه ستّة آلاف حرفٍ نجا، وقالبٌ فيه صورٌ مضمَّنة قُصّ في منتصف
 * وسمٍ فخرج ترميزٌ مكسورٌ لا يُرسم — وهذا هو الفرق بين العيّنتين هنا،
 * وهو وحده سببُ وجودهما معًا.
 *
 * فيُقاس الحبر: عناصرُ رُسمت، وحروفٌ ظهرت، وصورٌ حُمّلت (`naturalWidth`).
 */

import { launch, seedContext, ORIGIN, tally } from './lib/harness.mjs'
import { importDesign } from './lib/importer.mjs'

/** عيّنتان تختلفان بنيةً: الأولى جداولُ نصّيّة، والثانية صورٌ مضمَّنة. */
const FIXTURES = [
  { file: 'nafs.design.html', name: 'نافس', text: 300 },
  { file: 'proposal.design.html', name: 'العرض', text: 300, imgs: 1 },
]

const T = tally('المصغّرة')
const browser = await launch()
const errors = []

try {
  for (const fx of FIXTURES) {
    const boot = await browser.newContext()
    const { html } = await importDesign(boot, fx.file)
    await boot.close()

    const ctx = await seedContext(browser, html, undefined, { viewport: { width: 1400, height: 950 } })
    const p = await ctx.newPage()
    p.on('pageerror', (e) => errors.push(e.message))
    await p.goto(`${ORIGIN}/#/app/library/assessment`, { waitUntil: 'load' })
    await p.waitForSelector('.mdd-thumb-page', { timeout: 25000 })
    await p.waitForTimeout(1800)

    const m = await p.evaluate(() => {
      const t = document.querySelector('.mdd-thumb-page')
      if (!t) return { found: false }
      const imgs = [...t.querySelectorAll('img')]
      const cs = getComputedStyle(t)
      return {
        found: true,
        els: t.querySelectorAll('*').length,
        text: (t.textContent || '').trim().length,
        imgs: imgs.length,
        drawn: imgs.filter((i) => i.naturalWidth > 0).length,
        /* الصندوق يُرسم بضبط التصميم لا بطباعة مِداد المفروضة عليه:
           لو عاد `.mdd-doc-body [data-page]` لتقيّد الضبط بالمحرّر
           والمعاينة، لعادت المصغّرة تفرض حدودها على جداول التصميم. */
        boxed: cs.boxSizing,
        page: !!t.querySelector('[data-page]'),
      }
    })

    console.log(`  ${fx.name}: ${JSON.stringify(m)}`)
    T(`${fx.name} — المصغّرة فيها حبر`,
      m.found && m.els > 8 && m.text >= fx.text,
      m.found ? `${m.els} عنصرًا · ${m.text} حرفًا` : 'لا صندوق')

    if (fx.imgs) {
      T(`  وصورُها رُسمت`, m.drawn >= fx.imgs, `${m.drawn} من ${m.imgs}`)
    }
    T(`  وضبطُ صندوق الصفحة يبلغها`, m.page, m.page ? 'يبلغ' : 'مقصورٌ على المحرّر')

    await ctx.close()
  }

  T('بلا خطأ في الطرفيّة', errors.length === 0, errors[0] || 'نظيف')
} finally {
  await browser.close()
}

process.exit(T.done() === 0 ? 0 : 1)
