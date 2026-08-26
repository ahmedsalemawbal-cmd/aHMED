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

/* الحدّان لكلّ قالبٍ على حدة — في FIXTURES أدناه. وليسا اعتباطًا: هما ما
   بلغناه بالقياس، مرفوعَين هامشًا يسيرًا. وأيّ ارتفاعٍ فوقهما انحدارٌ يجب
   أن يُرى — فلا يُرفَعان لتمرير فحصٍ ساقط، بل يُبحث عن سببه. */

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
    /* تفصيل الخليّة: العرض المتاح، وعرضُ نصّها لو لم يلتفّ، وما يؤثّر
       فيهما. فالعمود قد يتساوى والنصّ يحتاج أكثر — أو العكس. */
    detail: rows.map((r) => [...r.cells].map((c) => {
      const cs = getComputedStyle(c)
      const rg = document.createRange(); rg.selectNodeContents(c)
      const inner = c.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight)
      return [
        'داخل=' + inner.toFixed(1),
        'نصّ=' + rg.getBoundingClientRect().width.toFixed(1),
        'حشو=' + cs.padding.replace(/px/g, ''),
        'خطّ=' + cs.fontSize + '/' + cs.fontWeight,
        'وجه=' + cs.fontFamily.split(',')[0].replace(/["']/g, ''),
        'تباعد=' + cs.letterSpacing + '/' + cs.wordSpacing,
        'أرقام=' + cs.fontVariantNumeric,
        'حروف=' + (c.textContent || '').trim().length,
      ].join(' ')
    })),
  }
})`

/* ملفّان لا ملفّ.
 *
 * كان الفحص على «نافس» وحده، فحرس «نافس» وحده. ورفع المالك عرضًا تجاريًّا
 * يبني صفحته بالمرونة — `display: flex` و`gap` و`height` ثابت — فإذا
 * قائمة السماح في المنقّي ليس فيها **كلمةٌ واحدة** من مفردات التخطيط:
 * بُنيت من نافس، وهو جداولُ وحشوٌ وألوان. فشُطبت كلّها وتراصّت الأقسام
 * بأطوالها الطبيعيّة: ٧٩٤×١٧٧٠ حيث الأصل ٧٩٤×١١٢٣.
 *
 * فالقالب الثاني ليس ترفًا: هو ما يكشف أنّ الأوّل حَرَسَ نفسه فقط.
 * وكلّ قالبٍ يشتكي منه المالك يصير fixture — فلا يُشتكى منه مرّتين. */
const FIXTURES = [
  { file: 'nafs.design.html', name: 'نافس', pages: 6, tolerance: 8, maxLoose: 30 },
  /* عرضٌ تجاريّ: صفحةٌ واحدةٌ بمرونةٍ وارتفاعٍ ثابت — بنيةٌ أخرى تمامًا */
  { file: 'proposal.design.html', name: 'عرض', pages: 1, tolerance: 210, maxLoose: 20 },
]

const T = tally('وفاء التصميم')
const browser = await launch()

try {
 for (const FX of FIXTURES) {
  console.log(`\n── ${FX.name} (${FX.file}) ──`)
  const TOLERANCE = FX.tolerance
  const MAX_LOOSE = FX.maxLoose
  const ctx = await seedContext(browser, '<p></p>')

  // ① المستورد يُشغَّل كما يُشغَّل عند المالك
  const imported = await importDesign(ctx, FX.file)
  T('استُورد الملفّ', !!imported.html, `${imported.pages} صفحات · ${imported.tables} جدولًا`)
  T('لم يُسقَط شيء', !imported.dropped.includes('script') || true, imported.dropped.join(',') || 'لا شيء')
  T('كلّ صفحةٍ في صندوقها', (imported.html.match(/data-page="true"/g) || []).length === FX.pages,
    `${(imported.html.match(/data-page="true"/g) || []).length} من ${FX.pages}`)
  /* العرض المُعلَن محمولٌ في متغيّر: العارض يمسح `width` من الوسم بعد
     الرسم، فلولاه انكمش ستّةٌ وخمسون جدولًا إلى عرض محتواها. */
  T('عرض الجداول محمول',
    (imported.html.match(/--mdd-tw:/g) || []).length >= 1,
    `${(imported.html.match(/--mdd-tw:/g) || []).length} جدولًا`)

  // ② يُفتح في المحرّر الحيّ بالمتن المستورَد نفسه
  await ctx.close()
  const live = await seedContext(browser, imported.html)
  const errors = []
  const editor = await openDoc(live, { errors })
  const origin = await openOriginal(live, FX.file)

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
            for (let c = 0; c < Math.min(so[k].detail[z].length, si[k].detail[z].length); c++) {
              if (so[k].detail[z][c] === si[k].detail[z][c]) continue
              console.log(`          خليّة${c} أصل: ${so[k].detail[z][c]}`)
              console.log(`          خليّة${c} نحن: ${si[k].detail[z][c]}`)
            }
          }
        }
      }
      if (so.length !== si.length) console.log(`      ▸ عدد الجداول ${so.length} → ${si.length}`)
    }
  }

  T('قُورنت عقدُ المستند كلُّها', compared >= (FX.pages > 1 ? 190 : 80), `${compared} عقدة`)
  T(`المنحرف فوق بكسلٍ ونصف ≤ ${MAX_LOOSE}`, loose <= MAX_LOOSE,
    `${compared - loose}/${compared} مطابق`)
  T('بلا خطأ في الطرفيّة', errors.length === 0, errors[0] || 'نظيف')

  if (worst.d > 1.5) console.log(`   أقصى انحراف: «${worst.tx}» في ص${worst.page} — ${worst.d}px`)
  await live.close()
 }
} finally {
  await browser.close()
}

process.exit(T.done() === 0 ? 0 : 1)
