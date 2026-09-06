/**
 * أدوات الفحص المشتركة: متصفّحٌ، وخادمٌ محاكًى، ومستندٌ يُفتح في المحرّر.
 *
 * ولمَ «في المحرّر»؟ لأنّ هذا هو الدرس الذي كلّفنا جولةً كاملة. كنّا نقيس
 * ناتج المستورد في حاويةٍ عاديّة ونُعلن المطابقة، والمالك يرى تصميمًا
 * منكمشًا. والمحرّر يُعيد بناء المستند من مخطّطه، وعارضُ الجداول يكتب في
 * الوسم **بعد** الرسم — فيمحو ما كتبه المستورد. فما يُقاس خارجه لا يدلّ
 * عليه.
 *
 *   القاعدة: يُقاس المنتج حيث يراه صاحبه، لا حيث يسهل قياسه.
 */

import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

export const HERE = path.dirname(fileURLToPath(import.meta.url))
export const TESTS = path.join(HERE, '..')
export const APP = path.join(TESTS, '..')
export const PORT = Number(process.env.MIDAD_TEST_PORT || 4180)
export const ORIGIN = `http://127.0.0.1:${PORT}`

/** خطّ القاهرة مضمَّنٌ في الحزمة لا مجلوبًا من الشبكة.
 *  والسبب أنّ غيابه يُبدّل ارتفاع السطر — ٢٦px بالقاهرة و١٦ بغيرها —
 *  فيظهر فرقٌ رأسيٌّ يتراكم، ويُنسَب إلى المنتج وهو من الفحص. */
export const CAIRO = fs.readFileSync(path.join(TESTS, 'fixtures/cairo.css'), 'utf8')

/** الأوزان التي يستعملها القالب — تُطلَب صراحةً ثمّ يُنتظر تحميلها.
 *  و`fonts.ready` وحدها لا تكفي: الوجه لا يُطلَب إلّا حين يُستعمل، وقد
 *  أُدرج المتن بعدها. */
const FACES = [
  '400 14px Cairo', '600 21px Cairo',
  '700 24px Cairo', '800 24px Cairo', '900 54px Cairo',
]

export async function waitForFonts(page) {
  await page.evaluate(async (faces) => {
    await Promise.all(faces.map((f) => document.fonts.load(f).catch(() => {})))
    await document.fonts.ready
  }, FACES)
  await page.waitForTimeout(600)
}

/**
 * متصفّحٌ من حيثما وُجد.
 *
 * فالحزمة قد تكون محلّيّةً أو عامّة، والمتصفّح قد يكون في مسارٍ مُهيّأ
 * (`PLAYWRIGHT_BROWSERS_PATH`) أو حيث تضعه بلايرايت بنفسها. وتثبيتُ مسارٍ
 * بعينه يجعل الفحص يعمل عند من كتبه وحده — وفحصٌ لا يعمل إلّا عندي ليس
 * حارسًا.
 */
export async function launch() {
  let mod
  try { mod = await import('playwright') }
  catch { mod = await import('/opt/node22/lib/node_modules/playwright/index.js') }
  const chromium = mod.chromium || mod.default?.chromium

  const candidates = [
    process.env.MIDAD_CHROMIUM,
    process.env.PLAYWRIGHT_BROWSERS_PATH
      ? path.join(process.env.PLAYWRIGHT_BROWSERS_PATH, 'chromium')
      : null,
  ].filter(Boolean).filter((c) => fs.existsSync(c))

  return chromium.launch(candidates.length ? { executablePath: candidates[0] } : {})
}

/* ═════════════════ خادمٌ محاكًى ═════════════════ */

const PAGE_ZERO = { size: 'A4', orientation: 'portrait', margins: { top: 0, right: 0, bottom: 0, left: 0 } }

/**
 * يُهيّئ سياقًا يرى مستندًا واحدًا بمتنٍ نُعطيه إيّاه.
 *
 * ولا نمسّ قاعدة البيانات الحيّة: الفحص يجب أن يعمل بلا شبكةٍ ولا حساب،
 * وإلّا لم يُشغَّل إلّا حين يتذكّره أحد.
 */
export async function seedContext(browser, html, page = PAGE_ZERO, opts = {}) {
  const ctx = await browser.newContext({
    viewport: opts.viewport || { width: 1500, height: 1000 },
    deviceScaleFactor: opts.scale || 1,
    acceptDownloads: true,
  })

  /* في الإطار الأعلى وحده: المستورد يُشغّل التصميم في إطارٍ معزولٍ بلا
     `allow-same-origin`، ولا تخزين فيه — وبلايرايت يحقن في كلّ إطار.
     فيرمي الحقنُ خطأً يُحسب على المنتج وهو من أداة الفحص. */
  await ctx.addInitScript(() => {
    if (window.top !== window) return
    localStorage.setItem('midad.auth', JSON.stringify({
    access_token: 'x', token_type: 'bearer', expires_in: 7200,
    expires_at: Math.floor(Date.now() / 1000) + 7200, refresh_token: 'y',
    user: {
      id: 'u1', aud: 'authenticated', role: 'authenticated', email: 'a@b.c',
      app_metadata: {}, user_metadata: {}, created_at: '2026-08-01T00:00:00Z',
      },
    }))
  })

  const DOC = {
    id: 'd1', subscriber_id: 's1', owner_id: 'u1', template_id: 't1',
    title: 'تحليل نتائج اختبار نافس', status: 'draft',
    content_html: html, page, created_at: '2026-08-20', updated_at: '2026-08-24',
  }
  const TPL = {
    ...DOC, id: 't1', slug: 'nafs', category_key: 'general', description: 'قالبُ فحص.',
    outputs: ['pdf', 'docx'], estimated_minutes: 12, version: 1, usage_count: 0,
    is_new: false, sort: 5, kind: 'doc', folder_id: 'f1',
    source_pdf_path: null, source_pages: 6,
  }

  await ctx.route('**/auth/v1/**', (r) =>
    r.fulfill({ status: 200, contentType: 'application/json', body: '{}' }))

  await ctx.route('**/rest/v1/**', (route) => {
    const t = new URL(route.request().url()).pathname.split('/rest/v1/')[1] || ''
    const J = (d) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(d) })
    if (t === 'documents') return J([DOC])
    if (t === 'templates') return J([TPL])
    if (t === 'template_folders') return J([{ id: 'f1', slug: 'assessment', name: 'المتابعة والتقييم', blurb: null, accent: '#0EA5A5', icon: 'folder', sort: 30, is_active: true, created_at: '2026-08-01', updated_at: '2026-08-01' }])
    if (t === 'template_folder_counts') return J([{ folder_id: 'f1', template_count: 1 }])
    if (t === 'profiles') return J([{ id: 'u1', subscriber_id: 's1', full_name: 'أحمد سالم', phone: '05', email: null, role_key: 'teacher', is_owner: true, status: 'active' }])
    if (t === 'subscribers') return J([{ id: 's1', name: 'المتوسطة الحادية والأربعون', account_type: 'school', status: 'active', plan_id: 'p1', trial_ends_at: null }])
    if (t === 'plans') return J([{ id: 'p1', key: 'school', name_ar: 'باقة المدرسة', account_type: 'school', price_sar: 749, seats: 10, template_categories: [], ai_quota_monthly: 1000, features_ar: [] }])
    if (t === 'roles') return J([{ key: 'teacher', name_ar: 'معلّم', blurb_ar: null, sort: 1 }])
    return J([])
  })

  return ctx
}

/** يفتح المستند في المحرّر ويردّ الصفحةَ بعد اكتمال الخطّ. */
export async function openDoc(ctx, { errors } = {}) {
  const p = await ctx.newPage()
  if (errors) {
    p.on('pageerror', (e) => errors.push('PAGEERROR ' + e.message))
    p.on('console', (m) => {
      if (m.type() === 'error' && !/favicon|ERR_|Failed to load resource/.test(m.text())) errors.push(m.text())
    })
  }
  await p.goto(`${ORIGIN}/#/app/doc/d1`, { waitUntil: 'load' })
  await p.waitForSelector('.mdd-doc-body', { timeout: 25000 })
  await p.addStyleTag({ content: CAIRO })
  await waitForFonts(p)
  return p
}

/* ═════════════════ عدّاد النتائج ═════════════════ */

export function tally(label) {
  let pass = 0, fail = 0
  const T = (l, ok, extra = '') => {
    ok ? pass++ : fail++
    console.log(`${ok ? '✅' : '✗ '} ${l}${extra ? ' — ' + extra : ''}`)
  }
  T.done = () => {
    console.log(`\n═══ ${label}: ${pass} ناجحًا · ${fail} فاشلًا ═══`)
    return fail
  }
  return T
}
