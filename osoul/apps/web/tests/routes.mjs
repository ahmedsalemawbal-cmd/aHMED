/**
 * كلُّ مسارٍ عامٍّ في `App.tsx` له مدخلٌ في `routes.json`.
 *
 * ومسارٌ ينقصه ذلك **يعمل للزائر ولا يوجد لجوجل**: لا يُرسَم مسبقًا،
 * فيصل الزاحفُ إلى ورقةٍ فارغة، ويصل من يلصق الرابطَ في واتساب إلى
 * بطاقةٍ بلا عنوان. وهو عطبٌ لا يظهر في المتصفّح أبدًا.
 *
 *     ما لا يُلحَظ غيابُه يُنسى إضافتُه، فيُحرَس.
 */
import { readFileSync } from 'node:fs'
import { tally } from './lib/tally.mjs'

const T = tally('المسارات')
const app = readFileSync(new URL('../src/App.tsx', import.meta.url), 'utf8')
const routes = JSON.parse(readFileSync(new URL('../routes.json', import.meta.url), 'utf8'))

const declared = new Set(routes.map((r) => r.path))

// `path="about"` و `index` — ولا يُحسب `*` ولا ما فيه معاملٌ `:slug`،
// فالأوّلُ صفحةُ «غير موجودة» والثاني لا يُعرَف عددُه إلّا من القاعدة.
const inApp = [...app.matchAll(/path="([^"*:]+)"/g)].map((m) => '/' + m[1])
if (/<Route index/.test(app)) inApp.unshift('/')

for (const p of inApp) T(`المسار ${p} مُسجَّلٌ في routes.json`, declared.has(p))

for (const r of routes) {
  T(`${r.path}: عنوانٌ لا يتجاوز ٦٥ حرفًا`, r.title.length <= 65, `${r.title.length}`)
  T(`${r.path}: وصفٌ بين ٧٠ و١٦٠ حرفًا`,
    r.desc.length >= 70 && r.desc.length <= 160, `${r.desc.length}`)
}

// المساراتُ التي تعتمد على القاعدة تُعلن أقلَّ ما يُقبل، وإلّا نُشرت فارغة
for (const p of ['/doors', '/gypsum', '/strut']) {
  const r = routes.find((x) => x.path === p)
  T(`${p}: يُعلن حدًّا أدنى للمنتجات`, !!r && typeof r.min === 'number' && r.min > 0)
}

process.exit(T.done())
