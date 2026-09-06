/**
 * ══ الرسمُ المسبَق ══
 *
 * موقعُ أصول البناء **يُؤرشَف**، وتطبيقُ الصفحة الواحدة يُسلّم إلى الزاحف
 * ورقةً فارغةً فيها `<div id="root"></div>` ووسمُ سكربت. وجوجل يشغّل
 * جافاسكربت — لكنّه يشغّله في دَورٍ ثانٍ متأخّر، وبقيّةُ الزواحف (واتساب
 * حين يُلصَق الرابط، ولينكدإن، وتويتر) لا تشغّله ألبتّة. فالرابطُ الذي
 * يُرسَل إلى مقاولٍ في واتساب يظهر بلا عنوانٍ ولا وصف.
 *
 *     ما لا يُرى قبل جافاسكربت لا يُوجد عند من لا يشغّله.
 *
 * فبعد البناء يُفتح كلُّ مسارٍ عامٍّ في متصفّحٍ حقيقيّ، ويُحفظ ما رسمه
 * ملفَّ HTML مكتملًا في موضعه. والزائرُ يأخذ الورقةَ المرسومةَ ثمّ يتولّى
 * React ما بعدها.
 *
 * ولمَ متصفّحٌ حقيقيٌّ لا `renderToString`؟ لأنّ الأخير يلزمه أن تكون كلُّ
 * وحدةٍ في الشجرة آمنةً على الخادم — ووحدةٌ واحدةٌ تلمس `window` عند
 * الاستيراد تُسقط البناءَ كلَّه. والمتصفّحُ يرسم ما يرسمه الزائرُ بعينه.
 */
import { createServer } from 'node:http'
import { readFile, mkdir, writeFile } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { extname, join, resolve } from 'node:path'
import { launchBrowser } from './tests/lib/browser.mjs'

const DIST = resolve('dist')
const PORT = 4199

if (!existsSync(join(DIST, 'index.html'))) {
  console.error('✗ لا بناءَ في dist — شغّل vite build أوّلًا')
  process.exit(1)
}

const MIME = {
  '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.css': 'text/css',
  '.woff2': 'font/woff2', '.svg': 'image/svg+xml', '.png': 'image/png',
  '.jpg': 'image/jpeg', '.webp': 'image/webp', '.json': 'application/json',
  '.webmanifest': 'application/manifest+json', '.ico': 'image/x-icon',
}

/* خادمٌ ساكنٌ يردّ index.html لكلّ ما ليس ملفًّا — كما سيفعل
   .htaccess على الاستضافة. ولولا هذا لَردّ 404 على كلّ مسارٍ عميق
   فرُسمت صفحةُ «غير موجودة» في كلّ ملفّ. */
const server = createServer(async (req, res) => {
  const url = decodeURIComponent((req.url || '/').split('?')[0])
  let file = join(DIST, url)
  if (!extname(url) || !existsSync(file)) file = join(DIST, 'index.html')
  try {
    const buf = await readFile(file)
    res.writeHead(200, { 'Content-Type': MIME[extname(file)] || 'application/octet-stream' })
    res.end(buf)
  } catch {
    res.writeHead(404); res.end('not found')
  }
})

await new Promise((r) => server.listen(PORT, '127.0.0.1', r))

const routes = JSON.parse(await readFile('routes.json', 'utf8'))
const browser = await launchBrowser()
const page = await browser.newPage()

let done = 0
const failures = []
for (const r of routes) {
  await page.goto(`http://127.0.0.1:${PORT}${r.path}`, { waitUntil: 'networkidle' })

  /* العنوانُ والوصفُ يُكتبان في الورقة نفسِها لا في وسمٍ يضيفه React
     بعد الرسم — الزاحفُ الذي لا يشغّل جافاسكربت يقرأ هذين وحدهما. */
  await page.evaluate(({ title, desc, path, origin }) => {
    document.title = title
    const meta = (name, content, attr = 'name') => {
      let el = document.querySelector(`meta[${attr}="${name}"]`)
      if (!el) { el = document.createElement('meta'); el.setAttribute(attr, name); document.head.appendChild(el) }
      el.setAttribute('content', content)
    }
    meta('description', desc)
    meta('og:title', title, 'property')
    meta('og:description', desc, 'property')
    meta('og:type', 'website', 'property')
    meta('og:url', origin + path, 'property')
    meta('twitter:card', 'summary_large_image')

    let link = document.querySelector('link[rel="canonical"]')
    if (!link) { link = document.createElement('link'); link.rel = 'canonical'; document.head.appendChild(link) }
    link.href = origin + path
  }, { title: r.title, desc: r.desc, path: r.path, origin: 'https://osoulalbinaa.com' })

  const html = '<!doctype html>\n' + await page.evaluate(() => document.documentElement.outerHTML)


  /*
   * ══ حارسُ الصفحات التي تعتمد على القاعدة ══
   *
   * الرسمُ المسبَقُ يفتح الصفحةَ في متصفّحٍ ويحفظ ما رُسم. فلو تعذّر
   * الوصولُ إلى القاعدة — شبكةٌ محجوبة، أو مفتاحٌ خطأ، أو سياسةٌ تمنع
   * القراءةَ لغير المسجّل — رُسمت الحالةُ الفارغةُ **وحُفظت بنجاح**،
   * فيُنشر كتالوجٌ بلا منتجٍ واحد ولا يُبلّغ أحدٌ بشيء.
   *
   *     البناءُ الذي ينجح بصفحةٍ فارغةٍ أسوأُ من بناءٍ يسقط.
   *
   * فكلُّ مسارٍ يُعلن `min` يُعدّ فيه ما رُسم، ويسقط البناءُ إن نقص.
   */
  if (r.min) {
    // عنوانٌ **فيه نصّ** لا وسمٌ فارغ: البطاقةُ تُرسم بعددها الصحيح
    // وهي خاوية، فالعدُّ وحده يشهد زورًا.
    const cards = (html.match(/class="osl-pcard__h"[^>]*>\s*<a[^>]*>[^<\s][^<]*</g) || []).length
    if (cards < r.min) {
      console.error(`\n✗ ${r.path}: رُسمت ${cards} بطاقةً والمنتظَرُ ${r.min} على الأقلّ.`)
      console.error('  الصفحةُ تعتمد على القاعدة — تحقّق من الوصول إليها ومن سياسة القراءة لغير المسجّل.')
      failures.push(r.path)
    }
  }

  const dir = r.path === '/' ? DIST : join(DIST, r.path)
  await mkdir(dir, { recursive: true })
  await writeFile(join(dir, 'index.html'), html, 'utf8')
  done++
  console.log(`  ✓ ${r.path}`)
}

await browser.close()
server.close()

if (failures.length) {
  console.error(`\n✗ ${failures.length} صفحةً رُسمت فارغةً: ${failures.join(' · ')}`)
  process.exit(1)
}
console.log(`✅ رُسمت ${done} صفحةً مسبقًا`)
