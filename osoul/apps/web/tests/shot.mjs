/**
 * صورةُ الموقع كما يراه الزائر — من البناء لا من خادم التطوير.
 * `node tests/shot.mjs [مسار…]`
 */
import { createServer } from 'node:http'
import { readFile } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { extname, join, resolve } from 'node:path'
import { launchBrowser } from './lib/browser.mjs'
import { readFile as rf } from 'node:fs/promises'

const DIST = resolve('dist'); const PORT = 4198
const MIME = { '.html':'text/html; charset=utf-8', '.js':'text/javascript', '.css':'text/css',
  '.woff2':'font/woff2', '.svg':'image/svg+xml', '.png':'image/png', '.json':'application/json' }

/*
 * الخادمُ المصغَّرُ يخدم من الجذر، والبناءُ قد يكون تحت أساسٍ
 * (`/aHMED/`). فتُقشَّر البادئةُ قبل البحث عن الملفّ — وإلّا طُلب
 * `dist/aHMED/assets/app.js` ولا شيءَ هناك، فتُرسم الصفحةُ بلا أنماطٍ
 * ولا شيفرة، وتُحفظ **بنجاح**.
 */
const BASE = (process.env.OSOUL_BASE || '/').replace(/\/*$/, '/')
const strip = (u) => (BASE !== '/' && u.startsWith(BASE)) ? '/' + u.slice(BASE.length) : u

const server = createServer(async (req, res) => {
  const url = strip(decodeURIComponent((req.url || '/').split('?')[0]))
  let f = join(DIST, url)
  if (!extname(url) || !existsSync(f)) f = join(DIST, 'index.html')
  try { res.writeHead(200, { 'Content-Type': MIME[extname(f)] || 'application/octet-stream' }); res.end(await readFile(f)) }
  catch { res.writeHead(404); res.end() }
})
await new Promise((r) => server.listen(PORT, '127.0.0.1', r))

const browser = await launchBrowser()

/*
 * ══ خادمٌ محاكًى لـPostgREST ══
 *
 * بيئةُ البناء هنا محجوبةٌ عن `supabase.co`، فالصفحاتُ التي تعتمد على
 * القاعدة تُرسم فارغةً ولا تُصوَّر. فتُقدَّم لها **صفوفٌ حقيقيّةٌ مأخوذةٌ
 * من القاعدة نفسِها** (`tests/fixtures/`) لا صفوفٌ مخترَعة.
 *
 * والفرقُ ليس شكليًّا: اسمٌ مخترَعٌ قصيرٌ يرسم بطاقةً متّزنةً كاذبة،
 * و«الأبواب المعدنية المقاومة للحريق» يكشف إن كان العنوانُ يفيض.
 *
 *     تُقاس الواجهةُ بأطولِ ما سيدخلها لا بأقصرِه.
 *
 * على نمط `seedContext` في `midad/apps/web/tests/lib/harness.mjs`.
 */
const DOORS = JSON.parse(await rf(new URL('./fixtures/products-doors.json', import.meta.url), 'utf8'))

/*
 * ══ جلسةٌ مزروعة ══
 *
 * اللوحةُ خلف تسجيل دخول، ولا خادمَ مصادقةٍ تصله هذه البيئة. فتُزرع
 * جلسةٌ في التخزين المحلّيّ **قبل أوّل سكربت** (`addInitScript`) —
 * ولو زُرعت بعد التحميل لَقرأ العميلُ تخزينًا فارغًا فحسِبها زيارةَ
 * زائر، ولا يعيد القراءةَ بعدها.
 *
 *     ما يُقرأ مرّةً يُزرَع قبلها.
 *
 * وهذه للصورة وحدَها: لا تُثبت أنّ الصلاحيّةَ تعمل — تلك يُثبتها
 * `supabase/tests/wall.sql` في القاعدة نفسِها.
 */
const FAKE_UID = '11111111-2222-3333-4444-555555555555'

async function seedSession(page, role = 'admin', name = 'مدير أصول') {
  await page.addInitScript(([uid, r, n]) => {
    const far = Math.floor(Date.now() / 1000) + 60 * 60 * 24 * 365
    localStorage.setItem('osoul.auth', JSON.stringify({
      access_token: 'shot.' + r, token_type: 'bearer',
      expires_in: 31536000, expires_at: far, refresh_token: 'shot-refresh',
      user: { id: uid, aud: 'authenticated', role: 'authenticated',
              email: 'shot@osoulalbinaa.com', app_metadata: {}, user_metadata: { full_name: n },
              created_at: new Date().toISOString() },
    }))
  }, [FAKE_UID, role, name])
  page.__role = role
  page.__name = name
}

async function stubRest(page) {
  await page.route('**/rest/v1/**', async (route) => {
    const u = route.request().url()
    let body = '[]'
    if (u.includes('/profiles')) {
      body = JSON.stringify({ id: FAKE_UID, full_name: page.__name || 'مدير أصول',
                              role: page.__role || 'admin', phone: null, active: true })
    } else if (u.includes('/quotes') && u.includes('head=true')) {
      body = '[]'
    } else if (u.includes('/products')) {
      body = u.includes('group_key=eq.doors') || !u.includes('group_key=') ? JSON.stringify(DOORS) : '[]'
    } else if (u.includes('/settings')) {
      body = JSON.stringify([
        { key: 'general', value: { company_name_ar: 'شركة أصول البناء للصناعة',
          phone_primary: '+966 563 627 063', phone_secondary: '+966 11 810 8717',
          email: 'info@osoulalbinaa.com', whatsapp: '966556847029',
          hours_ar: 'السبت — الخميس | 8:00 ص — 5:00 م' } },
        { key: 'contact', value: { address_ar: 'المملكة العربية السعودية — جدة، المدينة الصناعية الثالثة' } },
        { key: 'brand', value: {} },
        { key: 'quote_doc', value: { vat_number: '311325076500003', cr_number: '4030495019',
          national_address_ar: 'جدة — المدينة الصناعية الثالثة، المملكة العربية السعودية' } },
      ])
    }
    await route.fulfill({ status: 200, contentType: 'application/json', body })
  })
}

const argv = process.argv.slice(2)
// `--as=admin` يزرع جلسةَ دورٍ قبل الفتح
const asArg = argv.find((a) => a.startsWith('--as='))
const AUTH = asArg ? asArg.slice(5) : ''
const routes = argv.filter((a) => !a.startsWith('--')).length
  ? argv.filter((a) => !a.startsWith('--')) : ['/']

for (const theme of ['light', 'dark']) {
  const ctx = await browser.newContext({
    viewport: { width: 1360, height: 1000 }, deviceScaleFactor: 2,
    colorScheme: theme,
  })
  const page = await ctx.newPage()
  if (AUTH) await seedSession(page, AUTH)
  await stubRest(page)
  for (const r of routes) {
    await page.goto(`http://127.0.0.1:${PORT}${BASE}${r.replace(/^\//, '')}`, { waitUntil: 'networkidle' })
    // الخطُّ يُنتظَر صراحةً: بدونه تُقاس أبعادٌ ليست هي، وتخرج الصورةُ بخطٍّ آخر
    await page.evaluate(() => document.fonts.ready)
    const name = 'shot' + (r === '/' ? '-home' : r.replace(/\//g, '-')) + '-' + theme + '.png'
    await page.screenshot({ path: name, fullPage: true })
    console.log('  ✓ ' + name)
  }
  await ctx.close()
}
await browser.close(); server.close()
