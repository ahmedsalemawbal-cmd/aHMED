/**
 * السرعة تُحرَس كما يُحرَس السلوك.
 *
 * فتح المالك المكتبة فوقف أمام هياكل تحميلٍ لا تنتهي. وكان سببان لا
 * يظهران في أيّ فحصٍ سلوكيّ — كلاهما يُبقي الصفحة صحيحةً وبطيئة:
 *
 *   ① `select('*')` على `templates`: خمسةَ عشرَ قالبًا متونُها ثلاثةَ
 *      عشرَ ميغابايتًا، تُجلب كلُّها لترسم بطاقاتٍ لا يُعرض منها واحدٌ
 *      بالمئة. فصارت القاعدة تشتقّ `thumb_html` و`body_len`.
 *   ② الخطّ من `fonts.googleapis.com`: أصلان خارجيّان وأربعُ رحلاتٍ
 *      **تحجز الرسم**. قِيست هنا ١٢٧٣٠ms حتّى ظهرت المجلّدات، والحزمة
 *      كلُّها قد نزلت في أقلّ من ثانية. فصار الخطّ من عندنا: ٤٠٨ms.
 *
 *     ما لا يُقاس يعود.
 *
 * ويُقاس على **البناء** لا على خادم التطوير: خادم التطوير يقدّم الوحدات
 * مفرّقةً بلا ضغط، فرقمُه لا يدلّ على ما ينزل عند المشترك.
 *
 * والحدود مرفوعةٌ هامشًا عن المقيس: آلةُ السير أبطأ من آلةِ من كتب،
 * وحارسٌ يسقط بلا عطبٍ يُعطَّل بعد ثالث مرّة.
 */

import fs from 'node:fs'
import path from 'node:path'
import { spawn } from 'node:child_process'
import { launch, seedContext, tally, APP } from './lib/harness.mjs'

const PORT = Number(process.env.MIDAD_SPEED_PORT || 4181)
const ORIGIN = `http://127.0.0.1:${PORT}`

/** ما يُحمَّل قبل أن يُرسم شيء — وكلُّ كيلوبايتٍ فيه انتظارٌ لكلّ زائر. */
const FIRST_PAINT_KB = 560
/** والمسار الواحد بكلّ ما يجلبه */
const ROUTES = [
  { path: '/#/', sel: 'body', name: 'صفحة الهبوط', ms: 4000, kb: 700 },
  { path: '/#/app/library', sel: '.mdd-folder', name: 'المكتبة', ms: 4000, kb: 750 },
  { path: '/#/app/doc/d1', sel: '.mdd-doc-body', name: 'المحرّر', ms: 6000, kb: 1400 },
]

const T = tally('السرعة')
const dist = path.join(APP, 'dist')

if (!fs.existsSync(path.join(dist, 'index.html'))) {
  console.log('⚠ لا بناء في dist — شغّل npm run build أوّلًا. تُخطّى.')
  process.exit(0)
}

/* ═════ ① لا أصلَ خارجيًّا يحجز الرسم ═════ */
const html = fs.readFileSync(path.join(dist, 'index.html'), 'utf8')
const foreign = [...html.matchAll(/<link[^>]+href="(https?:\/\/[^"]+)"/g)].map((m) => m[1])
T('لا ورقةَ أنماطٍ من أصلٍ خارجيّ', foreign.length === 0, foreign.join(' · ') || 'نظيف')
T('الخطّ مستضافٌ عندنا',
  fs.existsSync(path.join(dist, 'fonts/cairo-arabic.woff2')),
  'fonts/cairo-arabic.woff2')

/* ═════ ①ب قابليّةُ التثبيت ═════
 *
 * مِداد يُثبَّت على الجوّال، وعليه يقوم غلافُ أندرويد. وأربعةٌ تُفحص
 * لأنّ نقصَها **لا يكسر شيئًا يُرى**: يعمل الموقع، ولا يعرض أندرويد
 * «تثبيت» بل «اختصارًا» أضعف، ولا يُفهم السبب.
 */
{
  const man = JSON.parse(fs.readFileSync(path.join(dist, 'manifest.webmanifest'), 'utf8'))
  const icons = man.icons || []
  T('عاملُ الخدمة في المخرجات', fs.existsSync(path.join(dist, 'sw.js')))
  T('  ومُسجَّلٌ في الصفحة', /serviceWorker/.test(html))
  /* SVG وحدها لا تكفي: أندرويد يضع مربّعًا فارغًا مكانها في الشاشة. */
  T('أيقونتا ١٩٢ و٥١٢ بصيغة PNG',
    icons.some((i) => i.sizes === '192x192' && i.type === 'image/png')
    && icons.some((i) => i.sizes === '512x512' && i.type === 'image/png'),
    icons.map((i) => `${i.sizes}/${(i.type || '').split('/')[1]}`).join(' · '))
  T('  ومقنَّعةٌ لأندرويد', icons.some((i) => (i.purpose || '').includes('maskable')))
  T('  وموجودةٌ فعلًا لا في البيان وحده',
    ['icon-192.png', 'icon-512.png', 'icon-maskable.png']
      .every((f) => fs.existsSync(path.join(dist, f))))

  /* ربطُ النطاق: بصمةُ المفتاح في الموقع يجب أن تطابق ما يوقّع به
     التطبيق، وإلّا عمل التطبيق وظهر فوقه شريطُ عنوان. */
  const linkPath = path.join(dist, '.well-known/assetlinks.json')
  T('ربطُ النطاق منشورٌ مع البناء', fs.existsSync(linkPath))
  if (fs.existsSync(linkPath)) {
    const link = JSON.parse(fs.readFileSync(linkPath, 'utf8'))
    const fp = link?.[0]?.target?.sha256_cert_fingerprints?.[0] || ''
    T('  ببصمةٍ كاملة', /^([0-9A-F]{2}:){31}[0-9A-F]{2}$/i.test(fp), fp.slice(0, 26) + '…')
    T('  وباسم حزمة التطبيق', link?.[0]?.target?.package_name === 'sa.midad.app',
      link?.[0]?.target?.package_name || '—')
  }
}

/* ═════ ② حجمُ أوّل فتحة ═════ */
const eager = [...html.matchAll(/(?:src|href)="\.\/(assets\/[^"]+\.js)"/g)].map((m) => m[1])
const kb = eager.reduce((n, f) => n + fs.statSync(path.join(dist, f)).size, 0) / 1024
T(`أوّل فتحة ≤ ${FIRST_PAINT_KB}K`, kb <= FIRST_PAINT_KB,
  `${kb.toFixed(0)}K في ${eager.length} قطع`)

/* والمحرّر ليس منها: صفحةُ المكتبة لا محرّرَ فيها، وتيبتاب وحده ٤٥٢ك */
const heavy = eager.filter((f) => fs.statSync(path.join(dist, f)).size > 300 * 1024)
T('  ولا قطعةَ فوق ٣٠٠ك فيها', heavy.length === 0, heavy.join(' · ') || 'لا شيء')

/* ═════ ③ القياس في متصفّح، على البناء ═════ */
const server = spawn(path.join(APP, 'node_modules/.bin/vite'),
  ['preview', '--port', String(PORT), '--strictPort', '--host', '127.0.0.1'],
  { cwd: APP, stdio: ['ignore', 'ignore', 'inherit'] })

const up = async () => {
  for (let i = 0; i < 60; i++) {
    try { if ((await fetch(ORIGIN + '/')).ok) return true } catch { /* بعدُ */ }
    await new Promise((r) => setTimeout(r, 250))
  }
  return false
}

let browser
try {
  if (!(await up())) {
    T('يقوم خادم المعاينة', false, `تعذّر على ${PORT}`)
  } else {
    browser = await launch()
    for (const r of ROUTES) {
      const ctx = await seedContext(browser,
        '<div data-page="true" style="padding:20px"><p>متن</p></div>',
        undefined, { viewport: { width: 1400, height: 950 } })
      const p = await ctx.newPage()
      let bytes = 0
      p.on('response', async (res) => {
        try {
          const h = res.headers()['content-length']
          bytes += h ? Number(h) : (await res.body()).length
        } catch { /* رُدّ من ذاكرةٍ أو أُلغي */ }
      })
      const t0 = Date.now()
      await p.goto(ORIGIN + r.path, { waitUntil: 'load' })
      let shown = true
      try { await p.waitForSelector(r.sel, { timeout: r.ms + 4000 }) } catch { shown = false }
      const ms = Date.now() - t0
      const k = bytes / 1024

      T(`${r.name} تظهر`, shown)
      T(`  في أقلّ من ${r.ms}ms`, shown && ms <= r.ms, `${ms}ms`)
      T(`  وبأقلّ من ${r.kb}K`, k <= r.kb, `${k.toFixed(0)}K`)
      await ctx.close()
    }
  }
} finally {
  if (browser) await browser.close()
  server.kill('SIGTERM')
}

process.exit(T.done() === 0 ? 0 : 1)
