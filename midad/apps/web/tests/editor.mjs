/**
 * المحرّر: الطباعة، والتنزيل، وتحرير الألوان، ومعاينة القالب.
 *
 * أربعة أمورٍ اشتكى منها المالك، ولكلٍّ منها سببٌ لا يظهر إلّا في متصفّح:
 *
 *   ① الألوان تختفي عند الطباعة — لأنّ المتصفّح يُسقط الخلفيّات ما لم
 *      يُطلَب `print-color-adjust: exact` على الورقة وعلى كلّ خليّة.
 *   ② التنزيل كان يفتح نافذة الطباعة — والمعلّم يريد ملفًّا لا حوارًا.
 *   ③ الألوان الجاهزة لا تكفي: لكلّ مدرسةٍ لونُها، فلزم منتقٍ حرّ.
 *   ④ بطاقة القالب كانت تعرض رسمًا عامًّا لا القالب — فيُشترى ما لم يُرَ.
 *
 * ويُفحص كلّ ذلك على مستندٍ حقيقيّ يمرّ بالمستورد أوّلًا: قالبٌ من ستّ
 * صفحاتٍ وستٍّ وخمسين جدولًا، لا متنٍ صغيرٍ يُرضي الفحص ولا يُشبه العمل.
 */

import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { launch, seedContext, openDoc, ORIGIN, tally } from './lib/harness.mjs'
import { importDesign } from './lib/importer.mjs'

const T = tally('المحرّر')
const browser = await launch()
const errors = []

try {
  const seed = await seedContext(browser, '<p></p>')
  const imported = await importDesign(seed)
  await seed.close()

  const ctx = await seedContext(browser, imported.html, undefined, { scale: 2 })
  const p = await openDoc(ctx, { errors })

  /* ═════ ① الألوان في الطباعة ═════ */
  console.log('═══ ① الألوان في الطباعة ═══')
  await p.emulateMedia({ media: 'print' })
  await p.waitForTimeout(700)
  const pr = await p.evaluate(() => {
    const tds = [...document.querySelectorAll('.mdd-doc-body td,.mdd-doc-body th')]
    const colored = tds.filter((t) => {
      const bg = getComputedStyle(t).backgroundColor
      return bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent'
    })
    const sheet = document.querySelector('.mdd-doc-sheet')
    const one = colored[0]
    const adj = (el) => getComputedStyle(el).printColorAdjust || getComputedStyle(el).webkitPrintColorAdjust
    return {
      colored: colored.length,
      adjustSheet: adj(sheet),
      adjustCell: one ? adj(one) : '',
    }
  })
  T('خلايا ملوّنة في وضع الطباعة', pr.colored >= 80, `${pr.colored} خليّة`)
  T('print-color-adjust على الورقة', pr.adjustSheet === 'exact', pr.adjustSheet)
  T('print-color-adjust على الخلايا', pr.adjustCell === 'exact', pr.adjustCell)
  await p.emulateMedia({ media: 'screen' })
  await p.waitForTimeout(400)

  /* ═════ ② التنزيل ملفًّا لا نافذة طباعة ═════ */
  console.log('\n═══ ② تنزيل PDF ═══')
  await p.click('button:has-text("صدّر")')
  await p.waitForTimeout(900)
  const modal = await p.evaluate(() => ({
    hasDownload: !![...document.querySelectorAll('button')].find((b) => b.textContent.includes('نزّل الآن')),
    noPrintOpen: ![...document.querySelectorAll('button')].find((b) => b.textContent.includes('افتح الطباعة')),
  }))
  T('نافذة التنزيل تفتح', modal.hasDownload)
  T('لا «افتح الطباعة»', modal.noPrintOpen)

  const dlPromise = p.waitForEvent('download', { timeout: 90000 })
  await p.click('button:has-text("نزّل الآن")')
  let dl = null
  try { dl = await dlPromise } catch { /* يُبلَّغ أدناه */ }
  if (dl) {
    const out = path.join(os.tmpdir(), 'midad-test.pdf')
    await dl.saveAs(out)
    const size = fs.statSync(out).size
    const head = fs.readFileSync(out).subarray(0, 5).toString()
    T('نُزّل الملفّ', true, `${dl.suggestedFilename()} · ${Math.round(size / 1024)} ك.ب`)
    T('  ملفّ PDF صالح', head === '%PDF-', head)

    /* عدد الورقات = عدد صفحات التصميم، لا ضعفَها.
       فبكسلٌ واحدٌ من ٢٢٤٦ كان يُنتج ورقةً بيضاء بعد كلِّ ورقة: ارتفاع
       الشريحة يُبتَر إلى ٢٢٤٥ والصورة ٢٢٤٦، فيصير الفائض صفحة. والعطب
       لا يظهر في المحرّر ولا في المعاينة — لا يراه إلّا من فتح الملفّ
       المنزَّل. فيُعدّ هنا. */
    const pdf = fs.readFileSync(out, 'latin1')
    const leaves = (pdf.match(/\/Type\s*\/Page[^s]/g) || []).length
    T('  ورقاتُه ست لا اثنتا عشرة', leaves === 6, `${leaves} ورقة`)
    fs.rmSync(out, { force: true })
  } else {
    T('نُزّل الملفّ', false, 'لم يقع تنزيل')
  }
  await p.waitForTimeout(600)

  /* ═════ ③ تحرير الألوان ═════ */
  console.log('\n═══ ③ تحرير الألوان ═══')
  await p.keyboard.press('Escape')
  await p.waitForTimeout(500)
  await p.locator('.mdd-doc-body td').first().click()
  await p.waitForTimeout(500)
  const tools = await p.evaluate(() => [...document.querySelectorAll('.mdd-tb-b')].map((b) => b.title))
  T('أداة «تعبئة الخليّة»', tools.includes('تعبئة الخليّة'))
  T('أداة «تعبئة الصفّ»', tools.includes('تعبئة الصفّ'))

  await p.click('button[title="تعبئة الخليّة"]')
  await p.waitForTimeout(500)
  const pop = await p.evaluate(() => ({
    swatches: document.querySelectorAll('.mdd-tb-sw-grid .mdd-tb-sw-i').length,
    custom: !!document.querySelector('.mdd-tb-sw-custom'),
    input: !!document.querySelector('input.mdd-tb-sw-input[type=color]'),
  }))
  T('ألوانٌ جاهزة', pop.swatches >= 10, `${pop.swatches}`)
  T('منتقي لونٍ حرّ', pop.custom && pop.input)

  const before = await p.evaluate(() =>
    getComputedStyle(document.querySelector('.mdd-doc-body td')).backgroundColor)
  const sw = await p.$$('.mdd-tb-sw-grid .mdd-tb-sw-i')
  if (sw.length > 5) { await sw[5].click(); await p.waitForTimeout(700) }
  const after = await p.evaluate(() =>
    getComputedStyle(document.querySelector('.mdd-doc-body td')).backgroundColor)
  T('تغيير لون الخليّة يعمل', before !== after, `${before} → ${after}`)

  /* ═════ ④ معاينة القالب بتصميمه ═════ */
  console.log('\n═══ ④ معاينة القالب ═══')
  await p.goto(`${ORIGIN}/#/app/template/nafs`, { waitUntil: 'load' })
  await p.waitForTimeout(2600)
  const prev = await p.evaluate(() => {
    const sheets = [...document.querySelectorAll('.mdd-tplview-sheet')]
    if (!sheets.length) return { found: false }
    const all = (q) => sheets.flatMap((s) => [...s.querySelectorAll(q)])
    const tds = all('td,th')
    return {
      found: true,
      pages: sheets.length,
      tables: all('table').length,
      colored: tds.filter((t) => {
        const bg = getComputedStyle(t).backgroundColor
        return bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent'
      }).length,
      label: document.body.textContent.includes('هكذا سيبدو القالب'),
    }
  })
  T('المعاينة من المتن الحقيقيّ', prev.found)
  T('  ستّ ورقاتٍ منفصلة', prev.pages === 6, `${prev.pages}`)
  T('  الجداول', (prev.tables || 0) >= 50, `${prev.tables}`)
  T('  الخلايا الملوّنة', (prev.colored || 0) >= 80, `${prev.colored}`)
  T('  عنوان «هكذا سيبدو القالب»', !!prev.label)

  await p.goto(`${ORIGIN}/#/app/library/assessment`, { waitUntil: 'load' })
  await p.waitForTimeout(2200)
  const thumb = await p.evaluate(() => {
    const card = document.querySelector('.mdd-tplc--art')
    const t = card && card.querySelector('.mdd-thumb-page')
    if (!t) return { found: false }
    const tds = [...t.querySelectorAll('td,th')]
    return {
      found: true,
      tables: t.querySelectorAll('table').length,
      colored: tds.filter((c) => {
        const bg = getComputedStyle(c).backgroundColor
        return bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent'
      }).length,
    }
  })
  T('مصغّرة البطاقة من المتن', thumb.found,
    thumb.found ? `${thumb.tables} جدولًا · ${thumb.colored} ملوّنة` : '')

  T('بلا خطأ في الطرفيّة', errors.length === 0, errors.slice(0, 2).join(' · ') || 'نظيف')
} finally {
  await browser.close()
}

process.exit(T.done() === 0 ? 0 : 1)
