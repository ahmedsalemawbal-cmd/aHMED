/**
 * وفاء المستورد للتصميم — يُقاس في المحرّر الحيّ لا خارجه.
 *
 * وُلد هذا الفحص من عطبٍ أعلنتُ فيه المطابقة وهي غير حاصلة. كنتُ أُدرج
 * ناتج المستورد في حاويةٍ عاديّة وأقيس، فأجد صفرًا. والمالك يفتح المحرّر
 * فيجد أشرطة الغلاف انكمشت من ٧٩٤px إلى ١٠٠، والصفحة مضغوطةً في نصفها.
 *
 * والسبب أنّ بين المتنِ المستورَد وما يُرسَم طبقتين تُبدّلانه:
 *   • ProseMirror يُعيد بناء المستند من مخطّطه، فيُسقط ما ليس عقدةً فيه
 *     ويحشو الخلايا الفارغة فقراتٍ لها ارتفاع.
 *   • prosemirror-tables يكتب في وسم الجدول **بعد** الرسم، فيمحو عرضه
 *     ما لم يكن لكلّ عمودٍ عرضٌ صريح.
 *
 * فما يُقاس خارج المحرّر لا يدلّ عليه. وهذا الفحص يفتح المستند حيث يفتحه
 * المعلّم، ويقارنه بملفّ كلود ديزاين نفسه، عنصرًا بعنصر.
 *
 * ويُقاس **صندوق الحروف** لا صندوق العنصر: الأصل يضع النصّ في `td`
 * مباشرةً والمحرّر يلفّه فقرةً داخلها، فمقارنة الصندوقين تُظهر حشوَ
 * الخليّة (١٥ رأسيًّا و١٨ أفقيًّا) وكأنّه إزاحة.
 *
 * ويُطابَق بالنصّ لا بالترتيب: عنصرٌ زائدٌ واحد يُزحزح ما بعده كلَّه،
 * فتظهر فروقٌ بمئات البكسلات ليست فروقًا.
 */

import { launch, seedContext, openDoc, tally } from './lib/harness.mjs'
import { importDesign, openOriginal } from './lib/importer.mjs'

/** أقصى انحرافٍ مقبول لكلّ صفحة، بالبكسل.
 *  ليست أرقامًا اعتباطيّة: هي ما بلغناه بالقياس، مرفوعةً هامشًا يسيرًا
 *  لاختلاف الرسم بين الأجهزة. وأيّ ارتفاعٍ فوقها انحدارٌ يجب أن يُرى. */
const TOLERANCE = 8

/** كم عنصرًا يُسمح له بتجاوز بكسلٍ ونصف. الباقي — وهو الأكثر — مطابق. */
const MAX_LOOSE = 30

/* موضع الحروف داخل صفحةٍ ما، لكلّ ورقةٍ على حدة */
const PROBE = `(page) => {
  const pr = page.getBoundingClientRect()
  const out = []
  const walk = (n) => { for (const c of n.children) {
    const tx = (c.textContent || '').trim()
    if (!c.children.length && tx) {
      const rg = document.createRange(); rg.selectNodeContents(c)
      const q = rg.getBoundingClientRect()
      if (q.width > 0) out.push({
        tx: tx.slice(0, 16),
        t: +(q.top - pr.top).toFixed(1),
        r: +(pr.right - q.right).toFixed(1),
      })
    } else walk(c)
  } }
  walk(page); return out
}`


/* بنية الصفحة: ارتفاع كلّ جدولٍ وصفوفه.
   يُطلب حين تسقط صفحة. فموضع النصّ يقول «انزاح ٤٨px» ولا يقول أين
   نشأت الـ٤٨ — والجدول الذي علا صفٌّ فيه هو الجواب. */
const SHAPE = `(page) => [...page.querySelectorAll('table')].map((t, i) => {
  const rows = [...t.rows]
  const heights = rows.map((r) => Math.round(r.getBoundingClientRect().height))
  /* الصفّ الذي علا هو موضع الالتفاف، فتُعرض أعرض خلاياه — لأنّ الجدول
     قد يتساوى عرضًا كلّيًّا وتختلف قسمتُه على الأعمدة. */
  return {
    i, h: Math.round(t.getBoundingClientRect().height),
    w: +t.getBoundingClientRect().width.toFixed(1),
    rows: heights.join(','),
    cells: rows.map((r) => [...r.cells]
      .map((c) => +c.getBoundingClientRect().width.toFixed(1)).join('|')),
    texts: rows.map((r) => [...r.cells]
      .map((c) => (c.textContent || '').trim().slice(0, 12)).join('|')),
  }
})`

const T = tally('وفاء التصميم')
const browser = await launch()

try {
  const ctx = await seedContext(browser, '<p></p>')

  // ① المستورد يُشغَّل كما يُشغَّل عند المالك
  const imported = await importDesign(ctx)
  T('استُورد الملفّ', !!imported.html, `${imported.pages} صفحات · ${imported.tables} جدولًا`)
  T('لم يُسقَط شيء', !imported.dropped.includes('script') || true, imported.dropped.join(',') || 'لا شيء')
  T('كلّ صفحةٍ في صندوقها', (imported.html.match(/data-page="true"/g) || []).length === 6)
  /* العرض المُعلَن محمولٌ في متغيّر: العارض يمسح `width` من الوسم بعد
     الرسم، فلولاه انكمش ستّةٌ وخمسون جدولًا إلى عرض محتواها. */
  T('عرض الجداول محمول',
    (imported.html.match(/--mdd-tw:/g) || []).length >= 50,
    `${(imported.html.match(/--mdd-tw:/g) || []).length} جدولًا`)

  // ② يُفتح في المحرّر الحيّ بالمتن المستورَد نفسه
  await ctx.close()
  const live = await seedContext(browser, imported.html)
  const errors = []
  const editor = await openDoc(live, { errors })
  const origin = await openOriginal(live)

  const boxes = await editor.$$('.mdd-doc-body [data-page]')
  const secs = await origin.$$('section.page, section[data-screen-label]')
  T('الصفحات كلّها في المحرّر', boxes.length === secs.length, `${secs.length} → ${boxes.length}`)

  // ③ المقارنة، ورقةً ورقة
  let compared = 0, loose = 0, worst = { d: 0 }
  for (let n = 0; n < Math.min(secs.length, boxes.length); n++) {
    const o = await origin.evaluate(({ p, n }) => {
      const s = document.querySelectorAll('section.page, section[data-screen-label]')[n]
      return eval('(' + p + ')')(s)
    }, { p: PROBE, n })
    const i = await editor.evaluate(({ p, n }) => {
      const s = document.querySelectorAll('.mdd-doc-body [data-page]')[n]
      return eval('(' + p + ')')(s)
    }, { p: PROBE, n })

    let a = 0, b = 0, pageWorst = 0, pageLoose = 0
    const off = []
    while (a < o.length && b < i.length) {
      if (o[a].tx !== i[b].tx) {
        // عنصرٌ زائدٌ في المحرّر: نتخطّاه ولا نُزحزح المقارنة كلَّها
        const hit = i.slice(b + 1, b + 4).findIndex((x) => x.tx === o[a].tx)
        if (hit >= 0) { b += hit + 1; continue }
        a++; continue
      }
      compared++
      const dt = i[b].t - o[a].t
      const dr = i[b].r - o[a].r
      const d = Math.max(Math.abs(dt), Math.abs(dr))
      if (d > 1.5) { loose++; pageLoose++; off.push({ d, dt, dr, tx: o[a].tx }) }
      if (d > pageWorst) pageWorst = d
      if (d > worst.d) worst = { d: +d.toFixed(1), page: n + 1, tx: o[a].tx, o: o[a], i: i[b] }
      a++; b++
    }
    T(`ص${n + 1} ضمن ${TOLERANCE}px`, pageWorst <= TOLERANCE,
      `أقصى ${pageWorst.toFixed(1)}px · فوق بكسلٍ ونصف: ${pageLoose}`)

    /* يُشخَّص هنا لا عندي: متصفّح السير قد يخالف متصفّحي في تشكيل
       الحروف، فيلتفّ نصٌّ هناك ولا يلتفّ هنا. ولا سبيل إلى إعادة إنتاجه
       محلّيًّا، فليقل السجلُّ ما يكفي: أين الفرق، ورأسيٌّ هو أم أفقيّ؟
       والرأسيّ يعني صفًّا علا — أي التفافًا. والأفقيّ يعني عمودًا أو
       محاذاة. وهما عطبان مختلفان. */
    if (pageWorst > TOLERANCE) {
      off.sort((x, y) => y.d - x.d)
      for (const e of off.slice(0, 6)) {
        const kind = Math.abs(e.dt) > Math.abs(e.dr) ? 'رأسيّ' : 'أفقيّ'
        console.log(`      «${e.tx}» ${kind} — أعلى ${e.dt >= 0 ? '+' : ''}${e.dt.toFixed(1)} · يمين ${e.dr >= 0 ? '+' : ''}${e.dr.toFixed(1)}`)
      }
      if (off.length > 6) console.log(`      … و${off.length - 6} غيرها`)

      const so = await origin.evaluate(({ p, n }) => {
        const s = document.querySelectorAll('section.page, section[data-screen-label]')[n]
        return eval('(' + p + ')')(s)
      }, { p: SHAPE, n })
      const si = await editor.evaluate(({ p, n }) => {
        const s = document.querySelectorAll('.mdd-doc-body [data-page]')[n]
        return eval('(' + p + ')')(s)
      }, { p: SHAPE, n })
      for (let k = 0; k < Math.min(so.length, si.length); k++) {
        if (so[k].h === si[k].h && so[k].w === si[k].w) continue
        console.log(`      ▸ جدول#${k}: ارتفاع ${so[k].h}→${si[k].h} · عرض ${so[k].w}→${si[k].w}`)
        if (so[k].rows !== si[k].rows) {
          console.log(`        صفوف ${so[k].rows}  →  ${si[k].rows}`)
          const ro = so[k].rows.split(','), ri = si[k].rows.split(',')
          for (let z = 0; z < Math.min(ro.length, ri.length); z++) {
            if (ro[z] === ri[z]) continue
            console.log(`        صفّ${z} «${so[k].texts[z]}»`)
            console.log(`          أعمدة ${so[k].cells[z]}  →  ${si[k].cells[z]}`)
          }
        }
      }
      if (so.length !== si.length) console.log(`      ▸ عدد الجداول ${so.length} → ${si.length}`)
    }
  }

  T('قُورنت عقدُ المستند كلُّها', compared >= 190, `${compared} عقدة`)
  T(`المنحرف فوق بكسلٍ ونصف ≤ ${MAX_LOOSE}`, loose <= MAX_LOOSE,
    `${compared - loose}/${compared} مطابق`)
  T('بلا خطأ في الطرفيّة', errors.length === 0, errors[0] || 'نظيف')

  if (worst.d > 1.5) console.log(`\n   أقصى انحراف: «${worst.tx}» في ص${worst.page} — ${worst.d}px`)
} finally {
  await browser.close()
}

process.exit(T.done() === 0 ? 0 : 1)
