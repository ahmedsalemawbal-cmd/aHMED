/**
 * حارسُ دوالّ الحافّة — ثلاثةُ أعطابٍ لا يكشفها بناءٌ ولا فحصٌ سلوكيّ.
 *
 * كتبتُ في `ai-improve` نداءً لـ`callAi` ونسيتُ استيرادها. ومرّ الملفّ في
 * esbuild سالمًا: هو مُجمِّعٌ لا مُدقِّقُ أنواع، والاسمُ غيرُ المعرَّف عنده
 * عالميٌّ محتمَل. ولا يظهر العطبُ إلّا حين ينقر مشتركٌ «حسّن» في الإنتاج.
 *
 *     ما لا يُكشف عند البناء يُكشف عند المشترك.
 *
 * فتُفحص ثلاثة:
 *   ① كلُّ اسمٍ مُصدَّرٍ من `_shared` يُستعمل في ملفٍّ يجب أن يُستورد فيه.
 *   ② `index.deploy.ts` **يُولَّد** من `index.ts` لا يُقارَن به.
 *      وكانت المقارنةُ أوّلَ ما كتبتُ، ثمّ تبيّن أنّ ملفّات النشر
 *      مُستثناةٌ من غيت — فالمقارنةُ في CI تمرّ بلا ملفٍّ تقارنه،
 *      وتُبلّغ نجاحًا لم يُفحص. والتوليدُ يمنع التفارق أصلًا.
 *   ③ لا مفتاحَ سرّيٌّ يُطبع في سجلّ.
 */

import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { tally } from './lib/harness.mjs'

const FN = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '../../../supabase/functions')
const T = tally('دوالّ الحافّة')

/** ما تُصدّره وحدات `_shared` — يُقرأ منها لا يُكتب هنا. */
const exported = new Map()
for (const f of fs.readdirSync(path.join(FN, '_shared'))) {
  if (!f.endsWith('.ts')) continue
  const src = fs.readFileSync(path.join(FN, '_shared', f), 'utf8')
  for (const m of src.matchAll(/^export\s+(?:async\s+)?(?:function|const|class|interface)\s+(\w+)/gm)) {
    exported.set(m[1], f)
  }
}
T('قُرئت صادرات _shared', exported.size > 6, `${exported.size} اسمًا`)

const dirs = fs.readdirSync(FN).filter((d) =>
  !d.startsWith('_') && fs.statSync(path.join(FN, d)).isDirectory())

for (const d of dirs) {
  const idx = path.join(FN, d, 'index.ts')
  if (!fs.existsSync(idx)) continue
  const src = fs.readFileSync(idx, 'utf8')

  /* ① الاستيراد — تُطرح التعليقاتُ والنصوصُ أوّلًا، وإلّا عُدّ اسمٌ في
     تعليقٍ عربيٍّ أو في رسالةِ خطأٍ استعمالًا. */
  const code = src
    .replace(/\/\*[\s\S]*?\*\//g, ' ')
    .replace(/\/\/[^\n]*/g, ' ')
    .replace(/`(?:\\.|[^`\\])*`/g, '``')
    .replace(/'(?:\\.|[^'\\])*'/g, "''")
    .replace(/"(?:\\.|[^"\\])*"/g, '""')

  const imported = new Set()
  for (const m of code.matchAll(/import\s*\{([^}]*)\}/g)) {
    for (const part of m[1].split(',')) {
      const n = part.trim().split(/\s+as\s+/)[0].trim()
      if (n) imported.add(n)
    }
  }
  /* سطورُ الاستيراد تُطرح، فما بقي استعمالٌ حقيقيّ. */
  const body = code.replace(/import\s*\{[^}]*\}\s*from\s*''/g, ' ')

  const missing = [...exported.keys()].filter((name) =>
    new RegExp(`(?<![\\w.])${name}(?![\\w])`).test(body) && !imported.has(name))
  T(`${d}: كلُّ مشتركٍ مُستعملٍ مُستورد`, missing.length === 0, missing.join(' · ') || 'سليم')

  /* ② نسخةُ النشر تُولَّد الآن — فلا تتفارق أبدًا. */
  const dep = path.join(FN, d, 'index.deploy.ts')
  const want = src.replace(/from '\.\.\/_shared\//g, "from './")
  const had = fs.existsSync(dep) ? fs.readFileSync(dep, 'utf8') : null
  if (had !== want) fs.writeFileSync(dep, want)
  T('  ونسخةُ النشر مولَّدة', fs.readFileSync(dep, 'utf8') === want,
    had === want ? 'كانت محدَّثة' : had === null ? 'وُلِّدت' : 'حُدِّثت')

  /* ③ ولا يُطبع مفتاحٌ في سجلّ. */
  const leaks = [...body.matchAll(/console\.(?:log|error|warn)\s*\([^)]*\bapiKey\b/g)]
  T('  ولا يُطبع مفتاحٌ في سجلّ', leaks.length === 0, leaks.length ? 'يُطبع!' : 'نظيف')
}

/* والوحدةُ المشتركة نفسها تحجب المفاتيح قبل الطباعة. */
{
  const ai = fs.readFileSync(path.join(FN, '_shared/ai.ts'), 'utf8')
  T('نداءُ الذكاء يحجب المفتاح قبل السجلّ',
    /function scrub\b/.test(ai) && /scrub\(await res\.text/.test(ai))
  T('  ويعرف المزوّدَين',
    /api\.anthropic\.com/.test(ai) && /api\.openai\.com/.test(ai))
}

process.exit(T.done() === 0 ? 0 : 1)
