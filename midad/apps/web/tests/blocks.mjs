/**
 * كُتلُ المستند — الرفعُ والإنزالُ والحذفُ لا تُتلف الملفّ.
 *
 * هذه العمليّاتُ الثلاث هي «التحرير كالوورد» في الجوّال. وخطؤها لا يظهر
 * إنذارًا: يظهر ملفًّا خرج بصفحةٍ ناقصةٍ أو فقرةٍ فقدت مقاسَ خطّها بعد
 * أن حرّكها صاحبُها — ولا يعرف أنّه هو من كسرها.
 *
 *     ما يُلحَم يجب أن يعود كما كان، وإلّا فليس لحمًا.
 *
 * والوحدةُ تُترجم من TypeScript وقتَ الفحص: هي في تطبيق الجوّال، وهذا
 * الفحص يجري في نود. فلا تُنسخ الشيفرة مرّتين.
 */

import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { tally } from './lib/harness.mjs'

const HERE = path.resolve(fileURLToPath(new URL('.', import.meta.url)))
const SRC = path.join(HERE, '../../mobile/src/lib/docBlocks.ts')
const T = tally('كُتل المستند')

const out = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'mdd-')), 'docBlocks.mjs')
execFileSync(path.join(HERE, '../node_modules/.bin/esbuild'),
  [SRC, '--format=esm', '--loader:.ts=ts', `--outfile=${out}`], { stdio: 'pipe' })
const B = await import(out)

/* صفحتان: الأولى عنوانٌ وفقرة، والثانية عنوانٌ وقائمةٌ وجدولُ صورة. */
const PAGE = (inner) => `<div data-page="true" style="padding:56px 48px">${inner}</div>`
const DOC = PAGE('<h1 style="font-size:26pt">ملفّ إنجازي</h1><p style="line-height:2">مقدّمة الملفّ.</p>')
  + PAGE('<h2>المحور الأوّل</h2><ul><li>بندٌ أوّل</li><li>بندٌ ثانٍ</li></ul>'
    + '<table><tbody><tr><td><img src="x.jpg"><div>تعليق</div></td></tr></tbody></table>')

const blocks = B.toBlocks(DOC)

T('يُقسَّم إلى خمس كُتل', blocks.length === 5, blocks.map((b) => b.tag).join(' · '))
T('  والصفحاتُ محفوظة', blocks.map((b) => b.page).join('') === '00111')
T('  والصورةُ تُعدّ', blocks[4].images === 1, `${blocks[4].images}`)
T('  ولا تُعدّ صورةٌ في فقرة', blocks[1].images === 0)

/* ① اللحمُ بلا تعديلٍ يعيد الأصل حرفًا بحرف. */
T('اللحم بلا تعديلٍ يعيد الأصل', B.fromBlocks(blocks) === DOC,
  B.fromBlocks(blocks) === DOC ? 'متطابق' : 'تفارقا')

/* ② الحذف: تختفي الكتلةُ وحدها، ويبقى ما حولها بسماته. */
{
  const after = B.fromBlocks(B.removeBlock(blocks, 3))   // القائمة
  T('حذفُ القائمة يُخرجها', !/<ul>/.test(after))
  T('  ويُبقي ما حولها', /المحور الأوّل/.test(after) && /<img/.test(after))
  T('  ويُبقي سماتِ الفقرة', /line-height:2/.test(after))
  T('  والصفحتان صفحتان', (after.match(/data-page/g) || []).length === 2)
}

/* ③ حذفُ كلّ كُتل صفحةٍ لا يترك صفحةً فارغة. */
{
  let b = blocks
  b = B.removeBlock(b, 0)
  b = B.removeBlock(b, 0)      // ذهبت الصفحة الأولى كلُّها
  const after = B.fromBlocks(b)
  T('صفحةٌ فُرّغت لا تبقى فارغة', (after.match(/data-page/g) || []).length === 1)
  T('  وما بقي كامل', /المحور الأوّل/.test(after) && /<img/.test(after))
}

/* ④ الرفعُ والإنزال. */
{
  const up = B.moveBlock(blocks, 1, -1)                 // الفقرة فوق العنوان
  T('الرفع يبدّل الترتيب', up[0].tag === 'p' && up[1].tag === 'h1')
  T('  ولا يُتلف المتن', B.fromBlocks(up).includes('مقدّمة الملفّ.'))
  T('  ولا يمسّ الأصل', blocks[0].tag === 'h1')

  const down = B.moveBlock(blocks, 3, 1)
  T('الإنزال يبدّل الترتيب', down[3].tag === 'table' && down[4].tag === 'ul')

  T('الرفعُ من الرأس لا يفعل شيئًا', B.moveBlock(blocks, 0, -1) === blocks)
  T('والإنزالُ من الذيل كذلك', B.moveBlock(blocks, 4, 1) === blocks)

  /* عبورُ الحدّ: كتلةٌ رُفعت من رأس صفحةٍ تلتحق بالسابقة. */
  const cross = B.moveBlock(blocks, 2, -1)              // «المحور الأوّل» يصعد
  T('العبور يُلحق الكتلة بالصفحة السابقة', cross[1].page === 0, `page=${cross[1].page}`)
  T('  والصفحتان تبقيان صفحتين',
    (B.fromBlocks(cross).match(/data-page/g) || []).length === 2)
}

/* ⑤ تعديلُ النصّ يحفظ الوسمَ والسمات. */
{
  const b1 = B.setText(blocks[1], 'مقدّمةٌ جديدة')
  T('التعديل يغيّر النصّ', b1.text === 'مقدّمةٌ جديدة')
  T('  ويحفظ السمات', /line-height:2/.test(b1.html), b1.html)
  T('  ويبقى الوسمُ فقرة', /^<p /.test(b1.html) && /<\/p>$/.test(b1.html))

  const rude = B.setText(blocks[1], '<script>خطر</script>')
  T('  ويُهرَّب ما يُكتب', !/<script>/.test(rude.html) && /&lt;script&gt;/.test(rude.html))

  const nl = B.setText(blocks[1], 'سطرٌ\nوسطر')
  T('  والسطرُ الجديد يصير <br>', /سطرٌ<br>وسطر/.test(nl.html))

  T('قائمةٌ لا تُعدَّل سطرًا واحدًا', !B.editable(blocks[3]), blocks[3].tag)
  T('  ولا كتلةُ صور', !B.editable(blocks[4]))
  T('  والفقرةُ تُعدَّل', B.editable(blocks[1]))
}

/* ⑥ ما لا يُفهم يُنقل كما هو — لا يُتلف. */
{
  const odd = PAGE('<p>قبل</p><section data-x="1"><b>غريب</b></section><p>بعد</p>')
  const ob = B.toBlocks(odd)
  T('كتلةٌ مجهولةُ النوع تُقرأ', ob.length === 3 && ob[1].tag === 'section')
  T('  وتُلحَم كما هي', B.fromBlocks(ob) === odd)
  T('  ولا تُعدَّل', !B.editable(ob[1]))
}

/* ⑦ متنٌ بلا صناديق صفحاتٍ صفحةٌ واحدة لا صفر. */
{
  const bare = B.toBlocks('<h2>بلا صندوق</h2><p>متن</p>')
  T('متنٌ بلا صندوقٍ صفحةٌ واحدة', bare.length === 2 && bare.every((b) => b.page === 0))
}

/* ⑧ جدولٌ داخل جدولٍ لا يقطع الكتلة — وهو حالُ كلّ تصميمٍ مستورد. */
{
  const nest = PAGE('<table><tbody><tr><td><table><tr><td>داخل</td></tr></table></td></tr></tbody></table><p>بعد</p>')
  const nb = B.toBlocks(nest)
  T('الجدولُ المتداخل كتلةٌ واحدة', nb.length === 2 && nb[0].tag === 'table', `${nb.length} كتلة`)
  T('  ويُلحَم كما هو', B.fromBlocks(nb) === nest)
}

process.exit(T.done() === 0 ? 0 : 1)
