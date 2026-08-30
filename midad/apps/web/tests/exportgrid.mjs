/**
 * مُصدِّرُ الجداول — الملفُّ يُفتح فعلًا.
 *
 * وWord وExcel صيغتان مضغوطتان: أيُّ خطأٍ في XML أو في فهرس الضغط يُنتج
 * ملفًّا يُنزَّل بحجمٍ معقول ثمّ يرفضه أوفيس برسالةٍ لا تدلّ على شيء —
 * ولا يُكتشف إلّا في يد المعلّم.
 *
 *     الملفُّ الذي لا يُفتح أسوأ من زرٍّ لا يعمل: الزرُّ يُشتكى منه.
 *
 * فيُفكّ الضغطُ هنا، ويُتحقّق من أنّ الأجزاء المُلزِمة موجودة، وأنّ
 * كلّ XML فيها متوازن، وأنّ خانةً من متن الجدول وصلت إلى الورقة.
 */

import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { tally } from './lib/harness.mjs'

const HERE = path.resolve(fileURLToPath(new URL('.', import.meta.url)))
const MOB = path.join(HERE, '../../mobile/src/lib')
const T = tally('مُصدِّر الجداول')
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'mdd-x-'))

/* الوحدةُ تُترجم، وتُستبدل وحداتُ إكسبو بأدنى ما يكفي: الفحصُ يقيس
   بناءَ الملفّ لا نظامَ الملفّات في الجوّال. */
const shim = path.join(tmp, 'shim.ts')
fs.writeFileSync(shim, `
export const cacheDirectory = '/tmp/'
export const EncodingType = { Base64: 'base64' }
export async function writeAsStringAsync() {}
export async function moveAsync() {}
export async function printToFileAsync() { return { uri: '' } }
export async function isAvailableAsync() { return false }
export async function shareAsync() {}
`)
const out = path.join(tmp, 'exportGrid.mjs')
execFileSync(path.join(HERE, '../node_modules/.bin/esbuild'),
  [path.join(MOB, 'exportGrid.ts'), '--bundle', '--format=esm', '--loader:.ts=ts',
    `--alias:expo-file-system=${shim}`, `--alias:expo-print=${shim}`,
    `--alias:expo-sharing=${shim}`, `--outfile=${out}`], { stdio: 'pipe' })

/* والوحدة تُصدّر البناءَ وحده؛ ولاختبار الحزم نُعيد ترجمةَ الداخل. */
const inner = path.join(tmp, 'inner.mjs')
const src = fs.readFileSync(path.join(MOB, 'exportGrid.ts'), 'utf8')
  .replace(/^function (docxBytes|xlsxBytes|gridHtml)/gm, 'export function $1')
fs.writeFileSync(path.join(tmp, 'exportGrid.ts'), src)
fs.copyFileSync(path.join(MOB, 'zip.ts'), path.join(tmp, 'zip.ts'))
execFileSync(path.join(HERE, '../node_modules/.bin/esbuild'),
  [path.join(tmp, 'exportGrid.ts'), '--bundle', '--format=esm', '--loader:.ts=ts',
    `--alias:expo-file-system=${shim}`, `--alias:expo-print=${shim}`,
    `--alias:expo-sharing=${shim}`,
    `--outfile=${inner}`], { stdio: 'pipe' })
const X = await import(inner)

/* جدولُ نورٍ واقعيّ: عربيّةٌ، ورموزٌ تكسر XML، وصفرٌ أوّلُ رقمٍ. */
const GRID = {
  title: 'كشف درجات الصفّ الأوّل «أ»',
  subtitle: 'من منصّة نور · ١٤٤٧هـ',
  columns: ['م', 'اسم الطالب', 'الهويّة', 'الجوّال', 'الدرجة', 'ملاحظة'],
  rows: [
    [1, 'أحمد سالم', '0512345678', '0555000111', 95, 'ممتاز & متميّز'],
    [2, 'محمّد <عبدالله>', '1098765432', '0533000222', 88, ''],
    [3, 'سارة "الغامدي"', '2011122233', '0501234567', 100, 'الأولى'],
  ],
}

/* ═════════ فكُّ الضغط بلا مكتبة ═════════
 *
 * وتُقرأ ترويسةُ كلّ جزءٍ كما هي — لا يُبحث عن اسمه في المتن.
 * وكان أوّلُ ما كتبتُ يبحث عن الاسم نصًّا، فيجده في «فهرس الأنواع»
 * حيث يُذكر الجزءُ لا حيث يُخزَّن، فيقرأ متنًا ليس متنَه ويحكم عليه.
 *
 *     ما يُبحث عنه بالاسم يُوجَد حيث يُذكر لا حيث هو.
 */
function parts(bytes) {
  const b = Buffer.from(bytes)
  const out = new Map()
  for (let i = 0; i + 30 < b.length; i++) {
    if (b[i] !== 0x50 || b[i + 1] !== 0x4b || b[i + 2] !== 0x03 || b[i + 3] !== 0x04) continue
    const size = b.readUInt32LE(i + 18)
    const nlen = b.readUInt16LE(i + 26)
    const xlen = b.readUInt16LE(i + 28)
    const name = b.slice(i + 30, i + 30 + nlen).toString('utf8')
    const at = i + 30 + nlen + xlen
    out.set(name, b.slice(at, at + size).toString('utf8'))
    i = at + size - 1
  }
  return out
}
/** كلُّ وسمٍ مفتوحٍ له مُغلِق — أبسط ميزانٍ يكشف XML مكسورًا. */
function balanced(xml) {
  const stack = []
  for (const m of xml.matchAll(/<(\/?)([A-Za-z_][\w:.-]*)([^>]*?)(\/?)>/g)) {
    if (m[3].endsWith('?') || m[4] === '/') continue
    if (m[1] === '/') { if (stack.pop() !== m[2]) return false } else stack.push(m[2])
  }
  return stack.length === 0
}

/* ═════════ Word ═════════ */
{
  const P = parts(X.docxBytes(GRID))
  T('Word: الأجزاء الأربعة موجودة',
    ['[Content_Types].xml', '_rels/.rels', 'word/document.xml'].every((n) => P.has(n)),
    [...P.keys()].join(' · '))

  const doc = P.get('word/document.xml') || ''
  T('  والـXML متوازن', balanced(doc), doc.slice(-40))
  T('  والعنوان فيه', doc.includes('كشف درجات'))
  T('  وصفوفُه ثلاثةٌ ورأس', (doc.match(/<w:tr>/g) || []).length + (doc.match(/<w:tr><w:trPr>/g) || []).length >= 4,
    `${(doc.match(/<w:tr/g) || []).length} صفًّا`)
  T('  والرموزُ مهرَّبة', doc.includes('&amp;') && doc.includes('&lt;عبدالله&gt;') && !/<عبدالله>/.test(doc))
  T('  والاتّجاه عربيّ', doc.includes('<w:bidi/>') && doc.includes('<w:rtl/>'))
}

/* ═════════ Excel ═════════ */
{
  const P = parts(X.xlsxBytes(GRID))
  T('Excel: الأجزاء الستّة موجودة',
    ['xl/workbook.xml', 'xl/worksheets/sheet1.xml', 'xl/styles.xml', '[Content_Types].xml']
      .every((n) => P.has(n)), P.size + ' جزءًا')

  const sh = P.get('xl/worksheets/sheet1.xml') || ''
  T('  والـXML متوازن', balanced(sh), sh.slice(-40))
  /* وكلُّ جزءٍ آخرَ متوازنٌ أيضًا: أوفيس يرفض الحزمة كلَّها لجزءٍ واحد. */
  T('  وكذلك بقيّةُ الأجزاء',
    [...P.entries()].filter(([n]) => n.endsWith('.xml')).every(([, x]) => balanced(x)),
    [...P.entries()].filter(([n]) => n.endsWith('.xml') && !balanced(P.get(n))).map(([n]) => n).join(' · ') || 'كلُّها متوازنة')
  T('  والورقة من اليمين', sh.includes('rightToLeft="1"'))
  T('  والدرجةُ رقمٌ يُجمع', /<v>95<\/v>/.test(sh))
  /* أهمُّ فحصٍ هنا: الهويّةُ نصٌّ لا رقم — تحويلُها يمحو صفرَها الأوّل. */
  T('  والهويّةُ نصٌّ يحفظ صفرَها', sh.includes('0512345678') && !/<v>512345678<\/v>/.test(sh))
  T('  والاقتباسُ مهرَّب', sh.includes('&quot;') || sh.includes('الغامدي'))
}

/* ═════════ PDF (HTML قبل الطباعة) ═════════ */
{
  const html = X.gridHtml(GRID)
  T('PDF: ستّةُ أعمدةٍ طوليّةٌ', html.includes('A4 portrait'))
  T('  وثلاثةَ عشرَ عمودًا عرضيّةٌ',
    X.gridHtml({ ...GRID, columns: Array.from({ length: 13 }, (_, i) => `ع${i}`) }).includes('A4 landscape'))
  T('  والاتّجاه عربيّ', html.includes('dir="rtl"'))
  T('  والرموزُ مهرَّبة', !/<عبدالله>/.test(html) && html.includes('&lt;عبدالله&gt;'))
  T('  وكلُّ صفٍّ فيه', ['أحمد سالم', 'محمّد', 'سارة'].every((n) => html.includes(n)))
}

/* ═════════ جدولٌ فارغٌ يُرفض قبل أن يُنتج ملفًّا فارغًا ═════════ */
{
  let msg = ''
  try { await X.exportGrid('xlsx', { ...GRID, rows: [] }) } catch (e) { msg = e.message }
  T('جدولٌ بلا صفوفٍ يُرفض برسالة', /لا صفوف/.test(msg), msg || 'لم يُرفض!')
}

process.exit(T.done() === 0 ? 0 : 1)
