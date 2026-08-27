/**
 * عاملُ خدمة مِداد — يجعله تطبيقًا يُثبَّت، ويفتح فورًا.
 *
 * وبدونه لا يعرض أندرويد «تثبيت التطبيق» بل «اختصارًا» أضعف، ولا يقوم
 * غلافُ أندرويد (TWA) على أساسٍ سليم. ومعه يفتح المشترك التطبيق فيرى
 * الواجهة قبل أن تصل الشبكة.
 *
 * ═══ والاستراتيجيّة تتبع نوع الملفّ، لا اسمًا واحدًا لكلّ شيء ═══
 *
 * ملفّات البناء مبصومةٌ باسمها (`app.Bz_-RFtJ.js`)، فمحتواها لا يتغيّر
 * أبدًا — تُخزَّن للأبد وتُقدَّم من المخزن بلا سؤال الشبكة.
 *
 * و`index.html` وحده يدلّ على أسماء البصمات الجديدة. فلو قُدِّم من
 * المخزن بقي يدلّ على القديمة، ولم ينفع بصمُها شيئًا — فيُطلب من الشبكة
 * أوّلًا، ولا يُرجع إلى المخزن إلّا إذا انقطعت.
 *
 *     ما يدلّ على غيره لا يُخزَّن.
 *
 * وطلباتُ الخادم (سوبابيس) لا تُمسّ إطلاقًا: بياناتٌ حيّةٌ لا تُخزَّن،
 * وشاهدٌ حُذف لا يجوز أن يعود من مخزنٍ قديم.
 */

const VERSION = 'midad-v1'
const SHELL = `${VERSION}-shell`
const ASSETS = `${VERSION}-assets`

/* ما يكفي لرسم الواجهة بلا شبكة. ولا نُدرج الأصول المبصومة هنا: أسماؤها
   تتغيّر في كلّ بناء، فتُخزَّن عند أوّل طلبٍ لها. */
const CORE = ['./', './index.html', './manifest.webmanifest', './icon.svg']

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(SHELL)
      .then((c) => c.addAll(CORE))
      /* ملفٌّ واحدٌ يتعذّر جلبه لا يُسقط التثبيت كلَّه */
      .catch(() => undefined)
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k)),
      ))
      .then(() => self.clients.claim()),
  )
})

self.addEventListener('fetch', (e) => {
  const req = e.request
  if (req.method !== 'GET') return

  const url = new URL(req.url)

  /* ما ليس من أصلنا يُترك للشبكة: سوبابيس، والخطوط إن جُلبت، وأيّ خارج.
     وبياناتُ الخادم لا تُخزَّن — شاهدٌ حُذف لا يعود من مخزن. */
  if (url.origin !== self.location.origin) return

  /* الأصول المبصومة: محتواها ثابتٌ باسمه، فمن المخزن أوّلًا وبلا تحقّق. */
  if (/\/assets\/|\.(?:woff2|png|svg|jpg|jpeg|webp)$/i.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res.ok) {
          const copy = res.clone()
          caches.open(ASSETS).then((c) => c.put(req, copy))
        }
        return res
      })),
    )
    return
  }

  /* ما سواه — وأهمّه `index.html` ومسارات التطبيق: الشبكة أوّلًا.
     فهو الذي يدلّ على الأصول الجديدة، ولو قُدِّم قديمًا لبقي المشترك
     على نسخةٍ لا تُحدَّث أبدًا. */
  e.respondWith(
    fetch(req)
      .then((res) => {
        if (res.ok) {
          const copy = res.clone()
          caches.open(SHELL).then((c) => c.put(req, copy))
        }
        return res
      })
      .catch(() => caches.match(req).then((hit) => hit || caches.match('./index.html'))),
  )
})
