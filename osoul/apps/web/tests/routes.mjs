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
const indexed = routes.filter((r) => r.index !== false)

/*
 * اللوحةُ شجرةٌ متفرّعة: `path="quotes"` تحت `/dashboard` تعني
 * `/dashboard/quotes` لا `/quotes`. فيُقسَم الملفُّ عند جذر اللوحة،
 * ويُشترط أن يكون الجذرُ نفسُه مُسجَّلًا — فيُغطّي فروعَه.
 */
const cut = app.indexOf('path="/dashboard"')
const publicPart = cut < 0 ? app : app.slice(0, cut)
const portalPart = cut < 0 ? '' : app.slice(cut)

// `*` صفحةُ «غير موجودة»، و`:slug` لا يُعرَف عددُه إلّا من القاعدة
const inApp = [...publicPart.matchAll(/path="([^"*:]+)"/g)].map((m) => '/' + m[1].replace(/^\//, ''))
if (/<Route index/.test(publicPart)) inApp.unshift('/')

for (const p of inApp) T(`المسار ${p} مُسجَّلٌ في routes.json`, declared.has(p))

if (portalPart) {
  T('جذرُ اللوحة /dashboard مُسجَّل', declared.has('/dashboard'))
  const r = routes.find((x) => x.path === '/dashboard')
  T('واللوحةُ مُعلَنةٌ أنّها لا تُؤرشَف', r?.index === false)
}

/*
 * حدودُ الطول تخصّ **المُؤرشَف وحده**: العنوانُ يُقتطع في نتيجة البحث
 * بعد نحو ٦٥ حرفًا، والوصفُ بعد ١٦٠. وصفحةٌ لا تُؤرشَف لا تصل نتيجةً
 * أصلًا، فقياسُها بهذا المسطرة قياسٌ في غير موضعه.
 *
 *     تُقاس القاعدةُ حيث تعمل، لا حيث يسهل تطبيقُها.
 *
 * ويبقى وجودُ العنوان والوصف مشروطًا في الكلّ: منهما تُبنى بطاقةُ
 * الرابط حين يُلصَق في واتساب، وذاك يقع على `/login` كما يقع على `/`.
 */
for (const r of routes) {
  T(`${r.path}: له عنوانٌ ووصف`, !!r.title?.trim() && !!r.desc?.trim())
}
for (const r of indexed) {
  T(`${r.path}: عنوانٌ لا يتجاوز ٦٥ حرفًا`, r.title.length <= 65, `${r.title.length}`)
  T(`${r.path}: وصفٌ بين ٧٠ و١٦٠ حرفًا`,
    r.desc.length >= 70 && r.desc.length <= 160, `${r.desc.length}`)
}

T('يوجد مسارٌ عامٌّ يُؤرشَف', indexed.length > 0, `${indexed.length}`)

// المساراتُ التي تعتمد على القاعدة تُعلن أقلَّ ما يُقبل، وإلّا نُشرت فارغة
for (const p of ['/doors', '/gypsum', '/strut']) {
  const r = routes.find((x) => x.path === p)
  T(`${p}: يُعلن حدًّا أدنى للمنتجات`, !!r && typeof r.min === 'number' && r.min > 0)
}

process.exit(T.done())
