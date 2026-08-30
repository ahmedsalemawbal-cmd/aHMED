/**
 * إيقاعُ التطبيق — ما لا يصطفّ يُقرأ عشوائيًّا.
 *
 * قال المالك: «أشوف عشوائيّة بدون تنظيم». ولم يكن في الألوان ولا في
 * الخطّ — كلاهما على سلّمٍ معرَّف. كان في **الحشو**: خمسةَ عشرَ مقدارًا
 * مختلفًا (٩ و١٠ و١١ و١٣ و١٤ …) في شيفرةٍ سلّمُها ثمانيةُ مقادير.
 *
 * ولا يُرى الفرقُ بين ٩ و١٠ في موضعٍ واحد؛ يُرى في الشاشة كلِّها: حافّةٌ
 * لا تحاذي حافّة، وبطاقتان متجاورتان بحشوين مختلفين. فتبدو الشاشة
 * مبعثرةً ولا يُشار إلى موضعِ الخلل.
 *
 *     ما لا يصطفّ يُقرأ عشوائيًّا، ولو كان كلُّ جزءٍ منه صحيحًا.
 *
 * ويُقاس هنا لأنّه يعود: كلُّ شاشةٍ جديدةٍ تُكتب فيها `gap: 10` بلا قصد.
 */

import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { tally } from './lib/harness.mjs'

const APPDIR = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '../../mobile/src')
const T = tally('إيقاع التطبيق')

/** السلّمُ المسموح — و٠..٢ شعراتٌ لا حشو. */
const SCALE = new Set([0, 1, 2, 4, 8, 12, 16, 20, 24, 32, 40])
const RADII = new Set([0, 2, 4, 10, 12, 14, 18, 22, 26, 999])

const SPACE_RE = /(padding\w*|margin\w*|gap|rowGap|columnGap): (\d+)\b/g
const RADIUS_RE = /borderRadius: (\d+)\b/g

const files = ['screens', 'ui'].flatMap((d) =>
  fs.readdirSync(path.join(APPDIR, d))
    .filter((f) => f.endsWith('.tsx'))
    .map((f) => path.join(APPDIR, d, f)))

T('قُرئت ملفّاتُ الواجهة', files.length > 8, `${files.length} ملفًّا`)

const strays = []
const strayR = []
for (const f of files) {
  const src = fs.readFileSync(f, 'utf8')
  const name = path.basename(f)
  for (const m of src.matchAll(SPACE_RE)) {
    if (!SCALE.has(Number(m[2]))) strays.push(`${name}: ${m[1]}=${m[2]}`)
  }
  for (const m of src.matchAll(RADIUS_RE)) {
    if (!RADII.has(Number(m[1]))) strayR.push(`${name}: r=${m[1]}`)
  }
}

T('كلُّ حشوٍ على السلّم', strays.length === 0,
  strays.slice(0, 6).join(' · ') || 'لا شاذّ')
T('وكلُّ نصفِ قطرٍ من الرموز', strayR.length === 0,
  strayR.slice(0, 6).join(' · ') || 'لا شاذّ')

/* وعددُ المقادير المستعملة نفسه: سلّمٌ يُستعمل كلُّه ليس سلّمًا. */
{
  const used = new Set()
  for (const f of files) {
    for (const m of fs.readFileSync(f, 'utf8').matchAll(SPACE_RE)) used.add(Number(m[2]))
  }
  T('ولا تتجاوز المقاديرُ المستعملة ثمانية', used.size <= 8,
    [...used].sort((a, b) => a - b).join(' · '))
}

/* ═══ ولا شاشةَ يتيمةٍ بلا هيدر ═══
 *
 * الهيدر الافتراضيّ مُطفأ في كلّ التطبيق (يُرسم LTR ويضع الرجوع في
 * الجهة الخطأ)، فكلُّ شاشةٍ ترسم `AppHeader` بنفسها. ومن نسيها خرجت
 * شاشتُه بلا عنوانٍ ولا سهم رجوع — ولا شيء يُنبّه.
 */
{
  const missing = files
    .filter((f) => f.includes('/screens/'))
    /* شاشاتُ ما قبل الدخول تُستثنى: `AppHeader` يرسم صورةَ المستخدم
       والجرسَ ودُرجَ الحساب، ولا حسابَ بعد. فترسم كلٌّ منها رجوعَها. */
    .filter((f) => !/Login|Signup|Blocked/.test(f))
    .filter((f) => {
      const src = fs.readFileSync(f, 'utf8')
      return !/AppHeader/.test(src) && !/<Screen\b/.test(src)
    })
    .map((f) => path.basename(f))
  T('كلُّ شاشةٍ ترسم هيدرها', missing.length === 0, missing.join(' · ') || 'كلُّها')
}

/* ═══ ولا إحالةَ إلى شاشةٍ حُذفت ═══
 *
 * حُذفت «القوالب» و«ملفّاتي» من التطبيق بقرار المالك. وإحالةٌ باقيةٌ
 * إليهما لا تُخطئ عند الترجمة — تُخطئ في يد المستعمل: يضغط فلا يحدث
 * شيء، أو يسقط التطبيق.
 */
{
  const gone = ['القوالب', 'ملفّاتي', 'TemplateDetail', "'Editor'"]
  const bad = []
  for (const f of files) {
    const src = fs.readFileSync(f, 'utf8')
    for (const g of gone) {
      const re = new RegExp(`navigate\\(\\s*['"\`]?${g.replace(/'/g, '')}`)
      if (re.test(src)) bad.push(`${path.basename(f)} → ${g}`)
    }
  }
  T('لا إحالةَ إلى شاشةٍ محذوفة', bad.length === 0, bad.join(' · ') || 'نظيف')

  const dead = ['Library.tsx', 'MyFiles.tsx', 'TemplateDetail.tsx', 'Editor.tsx']
    .filter((f) => fs.existsSync(path.join(APPDIR, 'screens', f)))
  T('  ولا ملفَّ شاشةٍ ميّت', dead.length === 0, dead.join(' · ') || 'نظيف')
}

/* ═══ الاتّجاه: بالمحرّك لا بأيدينا ═══
 *
 * كان الاتّجاه يُكتب في كلّ عنصرٍ بـ`row-reverse`: خمسةٌ وعشرون قلبًا
 * يدويًّا. وما يُكتب في خمسةٍ وعشرين موضعًا يُنسى في السادس والعشرين —
 * فتخرج شاشةٌ جديدةٌ من اليسار ولا شيء يُنبّه.
 *
 *     ما يُكتب في كلّ موضعٍ يُنسى في موضع.
 *
 * وأخطرُ ما في التحويل أنّ قلبًا يدويًّا واحدًا بقي يعني **انقلابًا
 * مزدوجًا**: المحرّك يقلب والصفُّ يقلب، فيعود إلى اليسار في موضعٍ واحدٍ
 * وسط شاشةٍ عربيّة. وهو أسوأ من ألّا يُقلب شيء.
 */
{
  const bad = []
  for (const f of files) {
    const src = fs.readFileSync(f, 'utf8')
      .replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' ')
    if (/row-reverse/.test(src)) bad.push(path.basename(f))
  }
  T('لا قلبَ يدويًّا للاتّجاه', bad.length === 0, bad.join(' · ') || 'نظيف')

  const store = fs.readFileSync(path.join(APPDIR, 'lib/store.tsx'), 'utf8')
  T('  والمحرّك يُشغَّل RTL',
    /allowRTL\(true\)/.test(store) && /forceRTL\(true\)/.test(store))

  /* والمُلحقُ هو الذي يجعله عربيًّا من **أوّل فتحة**: بدونه لا يسري
     الاتّجاه إلّا بعد إعادة تشغيل، فيرى المستعمل شاشةً مقلوبةً أوّل مرّة. */
  const app = JSON.parse(fs.readFileSync(path.join(APPDIR, '../app.json'), 'utf8'))
  const loc = (app.expo.plugins || []).find((p) => Array.isArray(p) && p[0] === 'expo-localization')
  T('  ومُلحقُ الاتّجاه مضبوطٌ في المشروع الأصليّ',
    !!loc && loc[1]?.forcesRTL === true && loc[1]?.supportsRTL === true,
    loc ? JSON.stringify(loc[1]) : 'غائب')

  /* ونسخةُ الحزمة تتبع نسخةَ الـSDK.
   *
   * ثبّتُّها بـ`npm install` فجاءت الأحدثَ — ستَّ نسخاتٍ كبرى أمام
   * SDK المشروع. ولم يُخطئ شيءٌ عندي: الأنواعُ سليمة، والحزمُ يمرّ،
   * والفحوصُ كلُّها خضراء. ثمّ سقط البناءُ في إعداد Gradle بعد تسع
   * دقائقَ من نجاحٍ متكرّر — لأنّ وحدةَ أندرويد فيها تطلب نواةً أحدث.
   *
   *     ما يُثبَّت بلا نظرٍ إلى SDK يسقط في المشروع الأصليّ وحده.
   */
  {
    const pkg = JSON.parse(fs.readFileSync(path.join(APPDIR, '../package.json'), 'utf8'))
    const sdk = Number((pkg.dependencies.expo || '').replace(/[^\d.]/g, '').split('.')[0])
    const loc = Number((pkg.dependencies['expo-localization'] || '').replace(/[^\d.]/g, '').split('.')[0])
    /* في SDK 51 نسختُها 15. والقاعدةُ العمليّة: لا تُجاوز الـSDK أبدًا. */
    T('  ونسخةُ حزمة الاتّجاه توافق الـSDK', loc > 0 && loc < sdk,
      `expo ${sdk} · expo-localization ${loc}`)
  }

  /* وما وُضع لعلاج LTR يُرفع برفعه: القفزُ إلى طرف الشريط كان يعالج
     أنّ المحرّك يفتحه يسارًا — ومع RTL صار يقفز إلى الطرف الخطأ. */
  const jumpy = files.filter((f) => /scrollToEnd/.test(fs.readFileSync(f, 'utf8')))
    .map((f) => path.basename(f))
  T('  ولا قفزَ إلى طرف شريطٍ أفقيّ', jumpy.length === 0, jumpy.join(' · ') || 'نظيف')
}

process.exit(T.done() === 0 ? 0 : 1)
