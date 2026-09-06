/**
 * ملفّ الإنجاز: الالتقاط، والمحاور، والاكتمال، والدور.
 *
 * والميزة كلُّها تقوم على شيءٍ واحد: **أن يكون الالتقاط أسرع من
 * نسيانه**. فإن بطُؤ، لم يكن ثمّة سجلٌّ في آخر العام أصلًا، ولا شيءَ
 * للذكاء أن يُركّبه.
 *
 * وأربعةٌ تُفحص هنا لأنّ عطبها **صامت** — الشاشة سليمةٌ والسجلّ ناقص:
 *
 *   ① المحاور تُقرأ من قالب الدور نفسه، لا من جدول إعدادات.
 *   ② الالتقاط يحفظ ما التُقط — ويُقاس بما يُرسَل للخادم لا بما يُرسَم:
 *      البطاقة قد تظهر في الشاشة ولا يُحفظ شيء، فتضيع في أوّل تحديث.
 *   ③ المحور اختياريّ — والإلزامُ يقتل الالتقاط، فيُفحص أنّه ليس شرطًا.
 *   ④ الاكتمال يُحسب من المحاور المغطّاة، وهو ما يُعيد الموظّف شهريًّا.
 */

import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { launch, ORIGIN, tally, APP } from './lib/harness.mjs'

/* ═════════ قالبُ دورٍ بمحاورَ حقيقيّة ═════════ */

const PAGE = (inner) =>
  `<div data-page="true" style="box-sizing:border-box;padding:26px 40px">${inner}</div>`

const TPL_HTML = [
  /* الغلاف — لا محاور فيه، ويجب أن يُترك */
  PAGE('<div style="font-size:28px;font-weight:900">ملف إنجاز رائد النشاط</div>'),
  PAGE('<div style="font-size:17px;font-weight:900;color:#0b6e6a">الهدف الوظيفي الأول: تنفيذ البرامج</div>'
     + '<table style="width:600px"><tr><th>معيار</th><th>الوزن</th></tr>'
     + '<tr><td>عدد البرامج</td><td>٢٠٪</td></tr></table>'),
  PAGE('<div style="font-size:17px;font-weight:900;color:#0b6e6a">الهدف الوظيفي الثاني: الإذاعة المدرسية</div>'),
  PAGE('<h3>الجدارات السلوكية</h3><p>وصف</p>'),
].join('<div data-page-break="true"></div>')

const TPL = {
  id: 't-pf', slug: 'pf-activity', title: 'ملف إنجاز رائد النشاط',
  category_key: 'general', description: null, body: '', fields: [],
  outputs: ['pdf'], estimated_minutes: 5, version: 1, status: 'published',
  usage_count: 0, is_new: false, sort: 0, sort_school: 0, sort_teacher: 0,
  kind: 'doc', folder_id: null, audience: 'all', role_keys: ['activity_leader'],
  content_html: TPL_HTML, thumb_html: '', body_len: TPL_HTML.length,
  page: { size: 'A4', orientation: 'portrait', margins: { top: 0, right: 0, bottom: 0, left: 0 } },
  source_pdf_path: null, source_pages: 4,
  created_at: '2026-08-01', updated_at: '2026-08-20',
}

/** قالبٌ لدورٍ آخر — يجب ألّا يراه رائد النشاط. */
const OTHER = { ...TPL, id: 't-vp', slug: 'pf-vice', title: 'ملف إنجاز وكيل المدرسة', role_keys: ['vice_principal'] }

const ITEMS = [
  {
    id: 'i1', subscriber_id: 's1', owner_id: 'u1', academic_year: '١٤٤٧',
    axis: 'الهدف الوظيفي الأول: تنفيذ البرامج', title: 'برنامج القراءة',
    note: 'تنفيذ برنامج القراءة الحرّة', kind: 'text',
    file_path: null, file_mime: null, file_size: null,
    happened_on: '2026-10-12', created_at: '2026-10-12', updated_at: '2026-10-12',
  },
  {
    id: 'i2', subscriber_id: 's1', owner_id: 'u1', academic_year: '١٤٤٧',
    axis: '', title: 'صورة', note: 'ركن النشاط', kind: 'photo',
    file_path: null, file_mime: null, file_size: null,
    happened_on: '2026-11-03', created_at: '2026-11-03', updated_at: '2026-11-03',
  },
]

/* ═════════ ① المحاور تُقرأ من القالب ═════════ */

function bundleAxes() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'midad-axes-'))
  const entry = path.join(dir, 'e.ts')
  const out = path.join(dir, 'a.js')
  fs.writeFileSync(entry,
    `import { axesOf, coverage } from '${path.join(APP, 'src/lib/portfolio')}'\n` +
    `;(globalThis as any).__axes = axesOf\n;(globalThis as any).__cov = coverage\n`)
  execFileSync(path.join(APP, 'node_modules/.bin/esbuild'), [
    entry, '--bundle', '--format=iife', `--outfile=${out}`,
    '--platform=browser', '--log-level=error',
  ], { stdio: ['ignore', 'ignore', 'inherit'] })
  const js = fs.readFileSync(out, 'utf8')
  fs.rmSync(dir, { recursive: true, force: true })
  return js
}

const T = tally('ملفّ الإنجاز')
const browser = await launch()
const errors = []
const writes = []

try {
  /* ═════ ① و④ بلا متصفّحِ تطبيق: المنطقُ وحده ═════ */
  {
    const ctx = await browser.newContext()
    const p = await ctx.newPage()
    await p.setContent('<!doctype html><html lang="ar" dir="rtl"><body></body></html>')
    await p.addScriptTag({ content: bundleAxes() })

    const axes = await p.evaluate((html) =>
      globalThis.__axes({ content_html: html }), TPL_HTML)

    T('المحاور تُقرأ من القالب', axes.length >= 3, `${axes.length}: ${axes.join(' · ')}`)
    T('  الغلافُ لا يدخل فيها',
      !axes.some((a) => a.includes('ملف إنجاز رائد النشاط')),
      axes[0] || '—')
    T('  والعنوانُ المصمَّم يُلتقط كما يُلتقط h3',
      axes.some((a) => a.includes('الهدف الوظيفي الأول'))
      && axes.some((a) => a.includes('الجدارات السلوكية')),
      axes.join(' · ').slice(0, 80))
    T('  ولا يدخل فيها رأسُ جدول',
      !axes.includes('معيار') && !axes.includes('الوزن'))

    /* ④ الاكتمال — وهو ما يُعيد الموظّف شهريًّا */
    const cov = await p.evaluate(([a, items]) => globalThis.__cov(a, items), [axes, ITEMS])
    T('الاكتمال يُحسب من المحاور المغطّاة',
      cov.covered === 1 && cov.total === axes.length,
      `${cov.covered} من ${cov.total}`)
    T('  والشاهدُ بلا محورٍ يُعدّ ولا يُهمَل', cov.untagged === 1, `${cov.untagged}`)
    T('  وعددُ الشواهد كلُّها', cov.items === 2, `${cov.items}`)
    await ctx.close()
  }

  /* ═════ ② و③ في المنصّة نفسها ═════ */
  const ctx = await browser.newContext({ viewport: { width: 1240, height: 900 } })
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

  await ctx.route('**/rest/v1/**', (route) => {
    const req = route.request()
    const url = new URL(req.url())
    const t = url.pathname.split('/rest/v1/')[1] || ''
    const J = (d) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(d) })

    if (req.method() === 'POST' && t === 'portfolio_items') {
      let body = {}
      try { body = JSON.parse(req.postData() || '{}') } catch { /* لا شيء */ }
      writes.push(body)
      return J([{ ...ITEMS[0], ...body, id: 'new' }])
    }
    if (t === 'portfolio_items') return J(ITEMS)
    /* القالبُ يُطلب مرّتين: قائمةً بأعمدةٍ خفيفة، ثمّ كاملًا بمتنه.
       والثانية هي التي تحمل المحاور. */
    if (t === 'templates') {
      const wantsBody = (url.searchParams.get('select') || '').includes('*')
      return J(wantsBody ? [TPL] : [TPL, OTHER])
    }
    if (t === 'template_folders') return J([])
    if (t === 'template_folder_counts') return J([])
    if (t === 'profiles') return J([{ id: 'u1', subscriber_id: 's1', full_name: 'سعد', phone: '05', email: null, role_key: 'activity_leader', is_owner: false, status: 'active' }])
    if (t === 'subscribers') return J([{ id: 's1', name: 'المتوسطة ٤١', account_type: 'school', status: 'active', plan_id: 'p1', trial_ends_at: null, academic_year: '١٤٤٧' }])
    if (t === 'plans') return J([{ id: 'p1', key: 'school', name_ar: 'ب', account_type: 'school', price_sar: 749, seats: 10, template_categories: [], ai_quota_monthly: 1000, features_ar: [] }])
    return J([])
  })

  const p = await ctx.newPage()
  p.on('pageerror', (e) => errors.push(e.message))
  await p.goto(`${ORIGIN}/#/app/portfolio`, { waitUntil: 'load' })
  await p.waitForSelector('.mdd-pf-cov, .mdd-empty', { timeout: 25000 })
  await p.waitForTimeout(1400)

  const shown = await p.evaluate(() => ({
    pct: document.querySelector('.mdd-pf-cov__pct')?.textContent.trim() || '',
    head: document.querySelector('.mdd-pf-cov__top b')?.textContent.trim() || '',
    axes: [...document.querySelectorAll('.mdd-pf-axes > span')].map((s) => s.textContent.trim()),
    on: document.querySelectorAll('.mdd-pf-axes > span.on').length,
    items: document.querySelectorAll('.mdd-pf-item').length,
    nav: !!document.querySelector('a[href*="portfolio"]'),
  }))

  T('الصفحة تُرسم بمحاور دوره', shown.axes.length >= 3, shown.axes.join(' · ').slice(0, 70))
  T('  والاكتمال ظاهر', /من/.test(shown.head), `${shown.head} · ${shown.pct}`)
  T('  ومحورٌ واحدٌ مغطًّى', shown.on === 1, `${shown.on}`)
  T('  والشواهد معروضة', shown.items === 2, `${shown.items}`)
  T('  وفي القائمة أيقونةٌ له', shown.nav)

  /* ═════ ② و③ الالتقاط — والمحور متروك ═════ */
  writes.length = 0
  await p.click('button:has-text("أضف شاهدًا")')
  await p.waitForSelector('.mdd-pf-kinds', { timeout: 12000 })

  const kinds = await p.evaluate(() =>
    [...document.querySelectorAll('.mdd-pf-kinds button')].map((b) => b.textContent.trim()))
  T('أنواعُ الشاهد الأربعة بلمسة',
    JSON.stringify(kinds) === JSON.stringify(['صورة', 'شهادة', 'مرفق', 'ملاحظة']),
    kinds.join(' · '))

  /* «ملاحظة» لا تحتاج ملفًّا — وهو أسرعُ التقاطٍ ممكن */
  await p.click('.mdd-pf-kinds button:has-text("ملاحظة")')
  await p.waitForTimeout(300)
  await p.fill('.mdd-modal textarea', 'تنفيذ إذاعة مدرسية عن اليوم الوطني')
  await p.click('.mdd-modal button:has-text("احفظ")')
  await p.waitForTimeout(900)

  const w = writes[0]
  T('الالتقاط يُرسل الشاهد', !!w, w ? JSON.stringify(w).slice(0, 60) : 'لا إرسال')
  if (w) {
    T('  بنصّه', (w.note || '').includes('اليوم الوطني'), w.note || '')
    T('  ونوعه', w.kind === 'text', w.kind)
    T('  والعنوانُ يُشتقّ من النصّ إن تُرك', !!(w.title || '').trim(), w.title || '—')
    T('  وبعامه الدراسيّ', w.academic_year === '١٤٤٧', w.academic_year || '—')
    /* الحاسم: المحور ليس شرطًا. ولو كان لَما التُقط شيءٌ أصلًا. */
    T('  والمحورُ متروكٌ ولم يمنع الحفظ', w.axis === '', `«${w.axis}»`)
  }

  T('بلا خطأ في الطرفيّة', errors.length === 0, errors[0] || 'نظيف')
  await ctx.close()
} finally {
  await browser.close()
}

if (T.done() !== 0) process.exitCode = 1

/* ═══════════ صورُ الإنجاز: تُحفظ دائمةً وتُعرض موقّعة ═══════════
 *
 * دلوُ الإنجاز خاصّ، ورابطُه الموقّع ينتهي بعد ساعة. والعطبُ هنا من
 * أخبث ما يكون: يعمل كلُّ شيءٍ يومَ التركيب، وتنكسر الصورُ في اليوم
 * التالي — فلا يُربط السببُ بالنتيجة أبدًا.
 *
 *     ما ينتهي لا يُحفظ في متن.
 *
 * فتُفحص الدورةُ كاملةً: يُحفظ المسارُ الدائم، ويُوقَّع عند العرض،
 * ويعود دائمًا عند الحفظ. وتُفحص السمةُ في مخطّط المحرّر: لو أُسقطت
 * صامتًا — وهو ما يفعله تيبتاب بما لا يعرف — ضاع المسارُ وحُفظ الرابط.
 */
{
  const ROUND = tally('صور الإنجاز')
  const lib = fs.readFileSync(new URL('../src/lib/portfolio.ts', import.meta.url), 'utf8')
  const ext = fs.readFileSync(new URL('../src/lib/editorStyled.ts', import.meta.url), 'utf8')
  const ed = fs.readFileSync(new URL('../src/pages/app/Editor.tsx', import.meta.url), 'utf8')

  ROUND('السمةُ مسجّلةٌ في امتداد الصورة', /portfolioAttr/.test(ext))
  ROUND('  ومضمومةٌ إلى StyledImage',
    /StyledImage = Image\.extend\(\{[\s\S]{0,160}portfolioAttr/.test(ext))
  ROUND('المحرّر يوقّع عند الفتح', /resignPortfolioImages\(row\.content_html/.test(ed))
  ROUND('  ويُرجعها دائمةً عند الحفظ',
    (ed.match(/unsignPortfolioImages\(latest\.current\.html\)/g) || []).length === 2,
    `${(ed.match(/unsignPortfolioImages\(/g) || []).length} موضعًا`)

  /* والدالّتان عكسُ بعضهما — يُجرَّبان لا يُقرآن. */
  const unsign = (html) => html.replace(
    /src="[^"]*"(\s+data-mdd-portfolio="([^"]*)")/g,
    (_m, tail, p) => `src="portfolio:${p}"${tail}`)

  const stored = '<img src="portfolio:uid/1447/9.jpg" data-mdd-portfolio="uid/1447/9.jpg" style="width:100%">'
  const shown = stored.replace(/src="portfolio:([^"]*)"/g,
    (_m, p) => `src="https://x.supabase.co/o/${p}?token=abc"`)
  ROUND('التوقيعُ يبدّل src وحده', /token=abc/.test(shown) && /data-mdd-portfolio="uid/.test(shown))
  ROUND('  والعكسُ يعيد الأصل حرفًا بحرف', unsign(shown) === stored, unsign(shown))
  ROUND('  ويحفظ النمط', /style="width:100%"/.test(unsign(shown)))

  /* وصورةٌ عاديّةٌ (شعارُ مدرسةٍ مثلًا) لا تُمسّ: لا سمةَ لها. */
  const plain = '<img src="https://cdn/logo.png" style="width:64px">'
  ROUND('صورةٌ بلا سمةٍ لا تُمسّ', unsign(plain) === plain)

  ROUND('الوحدةُ تُصدّر الدالّتين',
    /export async function resignPortfolioImages/.test(lib)
    && /export function unsignPortfolioImages/.test(lib))

  if (ROUND.done() !== 0) process.exitCode = 1
}

/* ═══════════ مسارُ الرفع يطابق سياسةَ الدلو ═══════════
 *
 * سياسةُ `portfolio` تشترط `<المشترك>/<المالك>/<ملفّ>` بهذا الترتيب.
 * وكتبتُ في التطبيق `<المالك>/<العام>/…` وعلّقتُ بأنّ ذلك ما تشترطه —
 * ولم أقرأها. فكان كلُّ رفعٍ سيُرفض عند أوّل صورةٍ يلتقطها معلّم.
 *
 *     السياسةُ تُقرأ، لا تُتذكَّر.
 *
 * والفحصُ يقارن **المصدرين معًا** بالترتيب الذي تفرضه السياسة: فما دام
 * الموقعُ والتطبيقُ يكتبان في دلوٍ واحدٍ فيجب أن يتّفقا، وأيُّهما شذّ
 * فشل صامتًا عند المستعمل لا عند المطوّر.
 */
{
  const P = tally('مسار دلو الإنجاز')
  const web = fs.readFileSync(new URL('../src/lib/portfolio.ts', import.meta.url), 'utf8')
  const app = fs.readFileSync(
    new URL('../../mobile/src/lib/portfolio.ts', import.meta.url), 'utf8')

  /* الجزءان الأوّلان بالترتيب: المشترك ثمّ المالك. */
  const ORDER = /`\$\{subscriberId\}\/\$\{ownerId\}\//

  P('الموقع يبني المسار: مشترك ← مالك', ORDER.test(web),
    (web.match(/return `[^`]*`/g) || []).filter((x) => /subscriberId/.test(x))[0] || '—')
  P('والتطبيق مثله', ORDER.test(app),
    (app.match(/path = `[^`]*`/g) || [])[0] || '—')

  /* ولا مسافةَ ولا عربيّةَ في المفتاح: العام «1447 هـ» فيهما معًا. */
  P('ولا عامَ دراسيًّا في مفتاح التخزين',
    !/path = `[^`]*\$\{year/.test(app),
    /path = `[^`]*\$\{year/.test(app) ? 'العام في المسار!' : 'نظيف')

  /* والحذفُ يستعمل المسار المحفوظ لا يعيد بناءه — وإلّا حُذف ملفٌّ آخر. */
  P('الحذف يستعمل file_path المحفوظ',
    /remove\(\[it\.file_path\]\)/.test(app) || /remove\(\[it\.file_path/.test(app))

  if (P.done() !== 0) process.exitCode = 1
}

process.exit(process.exitCode || 0)
