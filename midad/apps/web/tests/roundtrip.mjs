/**
 * ما يَنجو بعد التحرير — وهو الفحص الذي يسقط فيه كلّ حلٍّ لا يمرّ بالمخطّط.
 *
 * ProseMirror يُعيد بناء المستند من مخطّطه عند أوّل تعديل، فيُسقط كلّ ما
 * ليس عقدةً فيه. فحشوٌ نضعه على `div` عاديّ يبدو صحيحًا حتّى يكتب المعلّم
 * حرفًا واحدًا، ثمّ ينهار بلا سبب ظاهر — وهذا أسوأ من ألّا يُحفظ أصلًا:
 * العطب الذي يظهر متأخّرًا يُنسَب إلى المعلّم لا إلى المنتج.
 *
 * فلا يكفي أن نقرأ المستند بعد فتحه. نكتب فيه ثمّ نقرأ.
 */

import { launch, seedContext, openDoc, tally } from './lib/harness.mjs'
import { importDesign } from './lib/importer.mjs'

const T = tally('النجاة بعد التحرير')
const browser = await launch()
const errors = []

try {
  const seed = await seedContext(browser, '<p></p>')
  const imported = await importDesign(seed)
  await seed.close()

  const ctx = await seedContext(browser, imported.html)
  const p = await openDoc(ctx, { errors })

  const read = () => p.evaluate(() => {
    const boxes = [...document.querySelectorAll('.mdd-doc-body [data-page]')]
    return {
      n: boxes.length,
      pads: boxes.map((b) => getComputedStyle(b).padding),
      widths: boxes.map((b) => Math.round(b.getBoundingClientRect().width)),
      sheetPad: getComputedStyle(document.querySelector('.mdd-doc-sheet')).padding,
      bars: [...document.querySelectorAll('.mdd-doc-body [data-page] table')]
        .slice(0, 1).map((t) => Math.round(t.getBoundingClientRect().width)),
    }
  })

  const before = await read()
  T('صناديق الصفحات ظهرت', before.n === 6, `${before.n}`)
  T('  الغلاف بلا حشو', before.pads[0] === '0px', before.pads[0])
  T('  صفحات المتن بحشوها', before.pads[1] === '34px 44px 30px', before.pads[1])
  T('  الورقة بلا هامشٍ مفروض', /^0px/.test(before.sheetPad), before.sheetPad)
  T('  شريط الغلاف يبلغ الحافّتين', before.bars[0] >= 790, `${before.bars[0]}px`)

  /* ═══ الاختبار الحاسم: نكتب ثمّ نقرأ ═══
     وتُقصد فقرةٌ فيها نصّ: أوّل `p` في المستند قد تكون داخل خليّةٍ زخرفيّة
     صُفِّر ارتفاع سطرها، فلا تُنقَر. */
  const target = await p.evaluateHandle(() =>
    [...document.querySelectorAll('.mdd-doc-body p')]
      .find((x) => (x.textContent || '').trim() && x.getBoundingClientRect().height > 4))
  await target.asElement().click()
  await p.keyboard.type('س')
  await p.waitForTimeout(1200)

  const after = await read()
  T('نجت الصناديق بعد التحرير', after.n === 6, `${after.n}`)
  T('  نجا حشو كلّ صفحة',
    JSON.stringify(after.pads) === JSON.stringify(before.pads),
    after.pads.slice(0, 2).join(' | '))
  T('  نجا عرض الصفحات',
    JSON.stringify(after.widths) === JSON.stringify(before.widths),
    after.widths.join(','))
  T('  نجا عرض الشريط', after.bars[0] === before.bars[0], `${after.bars[0]}px`)
  T('بلا خطأ في الطرفيّة', errors.length === 0, errors[0] || 'نظيف')
} finally {
  await browser.close()
}

process.exit(T.done() === 0 ? 0 : 1)
