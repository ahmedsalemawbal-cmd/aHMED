/**
 * اللصق يقرأ **أيّ** جدول — وبالمنطق نفسه الذي تقرأ به الإضافة.
 *
 * للمعلّم بابان إلى الجدول الواحد: إضافةُ كروم تقرأ صفحة نور المفتوحة،
 * واللصقُ يقرأ ما نسخه بيده. ولا معنى لبابين يُخرجان شيئين.
 *
 *     بابان إلى الشيء الواحد يجب أن يُخرجاه واحدًا.
 *
 * فيُشغَّل هنا القارئان — `lib/tableGrid.ts` في المنصّة و`core.js` في
 * الإضافة — على العيّنات نفسها، ويُقارن ناتجُهما حرفًا بحرف. فإن عُدّل
 * أحدهما ولم يُعدَّل الآخر سقط الفحص، ولم يُترك للمعلّم أن يكتشفه.
 *
 * والعيّنات ليست جدولًا واحدًا مثاليًّا: هي أشكالٌ تقع في نور فعلًا —
 * خلايا مدمَجة، وعنوانٌ من صفّين، وجدولٌ بلا `th`، وجدولٌ داخل جدول،
 * وأرقامٌ هنديّة، وجدولٌ عربيٌّ من اليمين. فالمطلوب «أيّ جدول» لا
 * «الجدول الذي جرّبناه».
 */

import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { launch, tally, APP } from './lib/harness.mjs'

/* ═════════════ العيّنات ═════════════ */

const CASES = [
  {
    name: 'جدولٌ عاديّ برأس',
    html: `<h3>كشف طلاب ثاني/أ</h3><table>
      <thead><tr><th>م</th><th>اسم الطالب</th><th>الهوية</th></tr></thead>
      <tbody>
        <tr><td>١</td><td>عبدالله محمد</td><td>1098</td></tr>
        <tr><td>٢</td><td>فيصل سعد</td><td>1102</td></tr>
      </tbody></table>`,
    cols: ['م', 'اسم الطالب', 'الهوية'],
    rows: 2,
  },
  {
    name: 'عنوانٌ من صفّين يُدمج',
    html: `<table>
      <thead>
        <tr><th rowspan="2">الطالب</th><th colspan="2">الأسبوع الأوّل</th></tr>
        <tr><th>غياب</th><th>تأخّر</th></tr>
      </thead>
      <tbody><tr><td>سعد</td><td>١</td><td>٠</td></tr></tbody></table>`,
    cols: ['الطالب', 'الأسبوع الأوّل · غياب', 'الأسبوع الأوّل · تأخّر'],
    rows: 1,
  },
  {
    name: 'خلايا مدمَجة في المتن',
    html: `<table>
      <tr><th>الفصل</th><th>الطالب</th><th>الدرجة</th></tr>
      <tr><td rowspan="2">ثاني/أ</td><td>سعد</td><td>٩٥</td></tr>
      <tr><td>تركي</td><td>٨٨</td></tr></table>`,
    cols: ['الفصل', 'الطالب', 'الدرجة'],
    rows: 2,
    /* الخليّة الممتدّة تُكرَّر على ما تشغله — فلا يخرج جدولٌ فيه ثقب */
    check: (t) => t.rows[1][0] === 'ثاني/أ',
  },
  {
    name: 'بلا رأسٍ أصلًا — الصفّ الأوّل نصٌّ فيصير عنوانًا',
    html: `<table>
      <tr><td>الاسم</td><td>الصف</td></tr>
      <tr><td>سعد</td><td>ثاني</td></tr></table>`,
    cols: ['الاسم', 'الصف'],
    rows: 1,
  },
  {
    name: 'بلا رأسٍ والصفّ الأوّل أرقام — لا يُبتلع صفُّ بيانات',
    html: `<table>
      <tr><td>١</td><td>٩٥</td></tr>
      <tr><td>٢</td><td>٨٨</td></tr></table>`,
    cols: ['عمود 1', 'عمود 2'],
    rows: 2,
  },
  {
    name: 'جدولٌ داخل جدولٍ للتخطيط — يُؤخذ الأعمق',
    html: `<table><tr><td><table>
      <tr><th>الطالب</th><th>الدرجة</th></tr>
      <tr><td>سعد</td><td>٩٥</td></tr>
      </table></td></tr></table>`,
    cols: ['الطالب', 'الدرجة'],
    rows: 1,
    only: 1,
  },
  {
    name: 'صفٌّ فارغٌ يُطرح',
    html: `<table>
      <tr><th>أ</th><th>ب</th></tr>
      <tr><td>١</td><td>٢</td></tr>
      <tr><td></td><td></td></tr></table>`,
    cols: ['أ', 'ب'],
    rows: 1,
  },
  {
    name: 'عمودٌ واحدٌ يُترك — تخطيطٌ لا بيانات',
    html: '<table><tr><td>واحد</td></tr><tr><td>اثنان</td></tr></table>',
    empty: true,
  },
]

/* ═════════════ تشغيل القارئَين ═════════════ */

/** يبني وحدة المنصّة قابلةً للتنفيذ في صفحة. */
function bundleWeb() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'midad-grid-'))
  const entry = path.join(dir, 'e.ts')
  const out = path.join(dir, 'grid.js')
  fs.writeFileSync(entry,
    `import { readTablesFrom } from '${path.join(APP, 'src/lib/tableGrid')}'\n` +
    `;(globalThis as any).__webRead = readTablesFrom\n`)
  execFileSync(path.join(APP, 'node_modules/.bin/esbuild'), [
    entry, '--bundle', '--format=iife', `--outfile=${out}`,
    '--platform=browser', '--log-level=error',
  ], { stdio: ['ignore', 'ignore', 'inherit'] })
  const js = fs.readFileSync(out, 'utf8')
  fs.rmSync(dir, { recursive: true, force: true })
  return js
}

/** نواة الإضافة كما هي — تُحمَّل نصًّا، فهي سكربتٌ عاديٌّ لا وحدة. */
function extensionCore() {
  return fs.readFileSync(
    path.join(APP, '../extension/src/core.js'), 'utf8')
}

const T = tally('اللصق')
const browser = await launch()

try {
  const ctx = await browser.newContext()
  const p = await ctx.newPage()
  await p.setContent('<!doctype html><html lang="ar" dir="rtl"><body></body></html>')
  await p.addScriptTag({ content: bundleWeb() })

  /* نواة الإضافة تنادي `chrome.storage` عند التحميل، فتُلقَّم بديلًا
     صامتًا. ونحن لا نفحص الاتّصال هنا بل قراءة الجدول وحدها. */
  await p.evaluate(() => {
    globalThis.chrome = { storage: { local: { get: async () => ({}), set: async () => {}, remove: async () => {} } } }
  })
  await p.addScriptTag({ content: extensionCore() })

  const ok = await p.evaluate(() => typeof globalThis.__webRead === 'function'
    && typeof globalThis.Midad?.readTables === 'function')
  T('القارئان مُحمَّلان', ok)

  for (const c of CASES) {
    /* المنصّة تقرأ من متنٍ مُحلَّل (كما تفعل بما لُصق)، والإضافة تقرأ من
       المستند نفسه (كما تفعل في نور). فيُوضع المتن في الاثنين. */
    const res = await p.evaluate((html) => {
      const holder = document.createElement('div')
      holder.innerHTML = html
      /* الإضافة تتخطّى ما هو أصغر من أربعين بكسلًا: في صفحةٍ حقيقيّةٍ
         ذاك تخطيطٌ لا بيانات. والعيّنات هنا قصيرةٌ فتُعطى عرضًا، وإلّا
         قِيس فرقٌ في السياق وحُسب فرقًا في المنطق. */
      holder.querySelectorAll('table').forEach((t) => { t.style.width = '600px' })
      document.body.innerHTML = ''
      document.body.appendChild(holder)

      const strip = (t) => ({ columns: t.columns, rows: t.rows })
      return {
        web: globalThis.__webRead(holder, '').map(strip),
        ext: globalThis.Midad.readTables().tables.map(strip),
      }
    }, c.html)

    if (c.empty) {
      T(`${c.name} — يُترك`, res.web.length === 0, `${res.web.length} جدولًا`)
      T('  والإضافة كذلك', res.ext.length === 0, `${res.ext.length}`)
      continue
    }

    const w = res.web[0]
    T(`${c.name}`, !!w, w ? `${w.columns.length}×${w.rows.length}` : 'لم يُقرأ')
    if (!w) continue

    T('  الأعمدة', JSON.stringify(w.columns) === JSON.stringify(c.cols), w.columns.join(' · '))
    T('  الصفوف', w.rows.length === c.rows, `${w.rows.length} من ${c.rows}`)
    if (c.only !== undefined) {
      T('  عددُ الجداول المقروءة', res.web.length === c.only, `${res.web.length}`)
    }
    if (c.check) T('  المدمَجُ كُرِّر', c.check(w))

    /* الحاسم: البابان يُخرجان الشيء نفسه */
    T('  والإضافة تُخرج مثله بالضبط',
      JSON.stringify(res.web) === JSON.stringify(res.ext),
      JSON.stringify(res.ext[0]?.columns || []).slice(0, 70))
  }

  /* ═════ ولا يقتصر على شكلٍ بعينه ═════
   * جدولٌ من عشرين عمودًا وأسماءٍ لم نرَها — لأنّ المطلوب «أيّ جدول». */
  const wide = await p.evaluate(() => {
    const cols = Array.from({ length: 20 }, (_, i) => `حقل ${i + 1}`)
    const head = `<tr>${cols.map((c) => `<th>${c}</th>`).join('')}</tr>`
    const body = Array.from({ length: 40 }, (_, r) =>
      `<tr>${cols.map((_, c) => `<td>${r}-${c}</td>`).join('')}</tr>`).join('')
    const holder = document.createElement('div')
    holder.innerHTML = `<table>${head}${body}</table>`
    const t = globalThis.__webRead(holder, '')[0]
    return { cols: t?.columns.length, rows: t?.rows.length, last: t?.rows[39]?.[19] }
  })
  T('عشرون عمودًا وأربعون صفًّا — بلا حدٍّ مفروض',
    wide.cols === 20 && wide.rows === 40 && wide.last === '39-19',
    `${wide.cols}×${wide.rows} · آخر خليّة «${wide.last}»`)

  await ctx.close()

/* ═════════════ والنافذة تعمل في المنصّة نفسها ═════════════
 *
 * ما تقدّم يفحص القراءة. وهذا يفحص ما يراه المعلّم: أيلصق فيظهر الجدول
 * ويُحفظ؟ فمنطقٌ سليمٌ خلف زرٍّ لا يعمل لا ينفع أحدًا.
 */
{
  const { seedContext, ORIGIN } = await import('./lib/harness.mjs')
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 950 } })
  await ctx.addInitScript(() => {
    if (window.top !== window) return
    localStorage.setItem('midad.auth', JSON.stringify({
      access_token: 'x', token_type: 'bearer', expires_in: 7200,
      expires_at: Math.floor(Date.now() / 1000) + 7200, refresh_token: 'y',
      user: { id: 'u1', aud: 'authenticated', role: 'authenticated', email: 'a@b.c', app_metadata: {}, user_metadata: {}, created_at: '2026-08-01T00:00:00Z' },
    }))
  })
  await ctx.route('**/auth/v1/**', (r) =>
    r.fulfill({ status: 200, contentType: 'application/json', body: '{}' }))

  const saved = []
  await ctx.route('**/rest/v1/**', (route) => {
    const req = route.request()
    const t = new URL(req.url()).pathname.split('/rest/v1/')[1] || ''
    const J = (d) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(d) })
    if (req.method() === 'POST' && t === 'noor_tables') {
      try { saved.push(JSON.parse(req.postData() || '{}')) } catch { /* لا شيء */ }
      return J([{ id: 'n1' }])
    }
    if (t === 'noor_tables') return J([])
    if (t === 'link_keys') return J([])
    if (t === 'profiles') return J([{ id: 'u1', subscriber_id: 's1', full_name: 'أحمد', phone: '05', email: null, role_key: 'teacher', is_owner: true, status: 'active' }])
    if (t === 'subscribers') return J([{ id: 's1', name: 'مِداد', account_type: 'school', status: 'active', plan_id: 'p1', trial_ends_at: null }])
    if (t === 'plans') return J([{ id: 'p1', key: 'school', name_ar: 'ب', account_type: 'school', price_sar: 749, seats: 10, template_categories: [], ai_quota_monthly: 1000, features_ar: [] }])
    return J([])
  })

  const errors = []
  const pg = await ctx.newPage()
  pg.on('pageerror', (e) => errors.push(e.message))
  await pg.goto(`${ORIGIN}/#/app/noor`, { waitUntil: 'load' })
  await pg.waitForTimeout(1800)

  const btn = await pg.$('button:has-text("الصق جدولًا")')
  T('زرّ «الصق جدولًا» في الصفحة', !!btn)
  if (btn) {
    await btn.click()
    await pg.waitForSelector('.mdd-paste', { timeout: 15000 })

    /* لصقٌ حقيقيٌّ بحدثٍ يحمل `text/html` — كما يفعل المتصفّح حين
       يُنسخ جدولٌ من صفحة. */
    await pg.evaluate(() => {
      const dt = new DataTransfer()
      dt.setData('text/html', `<table>
        <tr><th>م</th><th>اسم الطالب</th><th>الصف</th></tr>
        <tr><td>١</td><td>عبدالله محمد</td><td>ثاني/أ</td></tr>
        <tr><td>٢</td><td>فيصل سعد</td><td>ثاني/أ</td></tr>
      </table>`)
      document.querySelector('.mdd-paste')
        .dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true }))
    })
    await pg.waitForTimeout(700)

    const seen = await pg.evaluate(() => {
      const tb = document.querySelector('.mdd-imp-prev-body table')
      if (!tb) return null
      return {
        cols: [...tb.querySelectorAll('thead th')].map((x) => x.textContent.trim()),
        rows: tb.querySelectorAll('tbody tr').length,
        name: document.querySelector('.mdd-modal input')?.value || '',
      }
    })
    T('  اللصق يُظهر الجدول', !!seen, seen ? `${seen.cols.length}×${seen.rows}` : 'لا معاينة')
    if (seen) {
      T('  بأعمدته', JSON.stringify(seen.cols) === JSON.stringify(['م', 'اسم الطالب', 'الصف']), seen.cols.join(' · '))
      T('  وصفوفه', seen.rows === 2, String(seen.rows))
    }

    const save = await pg.$('.mdd-modal button:has-text("احفظ الجدول")')
    if (save) {
      await save.click()
      await pg.waitForTimeout(900)
    }
    T('  والحفظ يُرسل الجدول', saved.length === 1, `${saved.length} إرسالًا`)
    if (saved[0]) {
      T('    بأعمدته وصفوفه',
        JSON.stringify(saved[0].columns) === JSON.stringify(['م', 'اسم الطالب', 'الصف'])
        && saved[0].row_count === 2,
        `${(saved[0].columns || []).length} عمودًا · ${saved[0].row_count} صفًّا`)
    }
  }

  T('بلا خطأ في الطرفيّة', errors.length === 0, errors[0] || 'نظيف')
  await ctx.close()
}
} finally {
  await browser.close()
}

process.exit(T.done() === 0 ? 0 : 1)
