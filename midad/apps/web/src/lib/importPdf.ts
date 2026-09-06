/**
 * استخراج نصّ ملفّ PDF في المتصفّح — لبذر قالبٍ يُصمّمه المالك.
 *
 * هذا **استخراج نصٍّ لا نسخُ تصميم**. يعطيك المتن مرتّبًا بعنواناته وفقراته
 * لتبني عليه، لا صورةً طبق الأصل. والجداول لا تُكتشف هنا: كشفها يحتاج قراءة
 * خطوط الرسم، وهي في خطّ التحويل بالبايثون (`tools/pdf2doc`) لا في المتصفّح.
 * فما كان مؤطَّرًا يخرج أسطرًا، وتُعيد أنت جدولته بزرّ الجدول — وهو أسرع من
 * تصحيح جدولٍ مستنبطٍ خطأً.
 *
 * الخطر الحقيقيّ في العربيّة المستخرجة أربعةٌ لا يراها الناظر، وهي معالَجة
 * هنا بالمنطق نفسه المستعمل في خطّ البايثون.
 */

/* ═══════════════ تطبيع العربيّة ═══════════════ */

const PRESENTATION = /[ﭐ-﷿ﹰ-﻿]/
const ARABIC = /[؀-ۿݐ-ݿ]/
const DIGITS = new Set('0123456789٠١٢٣٤٥٦٧٨٩')

/** كلماتٌ لا تكاد تخلو منها وثيقةٌ مدرسيّة — شاهدُنا على اتّجاه السطر */
const ANCHORS = new Set([
  'المدرسة', 'الطالب', 'الطالبة', 'المعلم', 'المعلّم', 'الصف', 'الصفّ',
  'المادة', 'المادّة', 'التاريخ', 'اليوم', 'الاسم', 'ملاحظات', 'التوقيع',
  'إدارة', 'تعليم', 'الفصل', 'الدراسي', 'الدراسيّ', 'العام', 'رقم', 'وزارة',
  'مدير', 'وكيل', 'المشرف', 'الحضور', 'الغياب', 'الدرجة', 'المجموع',
  'النتيجة', 'خطة', 'خطّة', 'تقرير', 'محضر', 'اجتماع', 'نموذج', 'استمارة',
  'سجل', 'سجلّ', 'متابعة', 'الطلبة', 'الطلاب', 'المستوى', 'نسبة', 'عدد',
])

/**
 * صور العرض ← الحروف الأساسيّة.
 *
 * كثيرٌ من مولّدات PDF تكتب الحرف بشكله المتّصل (U+FE70–FEFF) لا بأساسه،
 * فـ«معلم» تُخزَّن محارف تختلف عن «م ع ل م»: البحث والفرز والتصدير تفسد،
 * والعين لا ترى شيئًا. NFKC يعيدها ويفكّ لام-ألف المدمجة إلى حرفين.
 */
export function toLogicalLetters(s: string): string {
  // التطويل زخرفةٌ لا حرف — يُحذف دائمًا، لا عند وجود صور العرض وحدها
  const bare = s.replace(/ـ+/g, '')
  return PRESENTATION.test(bare) ? bare.normalize('NFKC') : bare
}

const words = (s: string) => s.match(/[؀-ۿ]+/g) || []
const stripDiacritics = (w: string) => w.replace(/[ً-ٰٟ]/g, '')

function anchorScore(s: string): number {
  let n = 0
  for (const w of words(s)) if (ANCHORS.has(w) || ANCHORS.has(stripDiacritics(w))) n++
  return n
}

function alRatio(s: string): number {
  const ws = words(s).filter((w) => w.length > 3)
  if (!ws.length) return 0
  return ws.filter((w) => w.startsWith('ال')).length / ws.length
}

/** هل السطر بترتيبٍ بصريّ (مقلوب)؟ لا نحكم عند التعادل — القلب الخاطئ أسوأ. */
export function looksVisual(s: string): boolean {
  if (!ARABIC.test(s)) return false
  const rev = [...s].reverse().join('')
  const a = anchorScore(s), b = anchorScore(rev)
  if (a !== b) return b > a
  return alRatio(rev) > alRatio(s) + 0.15
}

export function normalizeArabic(s: string): string {
  let out = toLogicalLetters(s || '')
  if (looksVisual(out)) out = [...out].reverse().join('')
  return out
    .replace(/[‎‏‪-‮]/g, '')
    .replace(/[ \t ]+/g, ' ')
    .trim()
}

/**
 * يُصلح مقاطع الأرقام المخزَّنة بترتيبها البصريّ.
 *
 * الرقم اتّجاهه من اليسار دائمًا، لكنّ بعض الملفّات تخزّن محارفه بترتيب
 * الرسم من اليمين — فتخرج «١٤٤٦» على أنّها «٦٤٤١». لا نُخمّن أيّ الترتيبين
 * أصحّ: **نقيسه**. إن تناقصت الإحداثيّة الأفقيّة على امتداد المقطع فترتيب
 * التخزين معكوس. حكمٌ هندسيّ لا احتماليّ.
 */
export function fixDigitRuns(chars: { ch: string; x: number }[]): string {
  const out: string[] = []
  let i = 0
  while (i < chars.length) {
    if (!DIGITS.has(chars[i].ch)) { out.push(chars[i].ch); i++; continue }
    let j = i
    while (j < chars.length && DIGITS.has(chars[j].ch)) j++
    let run = chars.slice(i, j)
    if (run.length > 1 && run[0].x > run[run.length - 1].x) run = run.slice().reverse()
    out.push(...run.map((c) => c.ch))
    i = j
  }
  return out.join('')
}

/** المحارف غير المتوقّعة في وثيقةٍ عربيّة — دلالةُ خريطةِ محارفَ فاسدة */
const EXPECTED = /[؀-ۿݐ-ݿﭐ-﷿ﹰ-﻿ -~ ·«»×÷ -⁯←-⇿─-╿■-◿✓✔✗]/

export interface Health { chars: number; bad: number; ratio: number; healthy: boolean; sample: string }

export function textHealth(text: string): Health {
  const chars = [...text].filter((c) => !/\s/.test(c))
  if (!chars.length) return { chars: 0, bad: 0, ratio: 0, healthy: true, sample: '' }
  const bad = chars.filter((c) => !EXPECTED.test(c))
  const seen = new Set<string>()
  for (const c of bad) { if (seen.size < 10) seen.add(c) }
  const ratio = bad.length / chars.length
  return {
    chars: chars.length, bad: bad.length, ratio,
    healthy: ratio < 0.03 || bad.length <= 2,
    sample: [...seen].join(''),
  }
}

/* ═══════════════ الاستخراج ═══════════════ */

export interface ImportResult {
  title: string
  html: string
  pages: number
  landscape: boolean
  health: Health
  warnings: string[]
}

const esc = (s: string) => s.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' } as any)[c])

/**
 * قطعةُ نصٍّ من الصفحة. `atomic` تعني أنّها جاءت عنصرًا متعدّد المحارف من
 * pdf.js — فنصُّها بترتيبه المنطقيّ أصلًا ولا يُعاد ترتيب ما بداخله.
 */
interface Ch { ch: string; x: number; x1: number; y: number; size: number; atomic?: boolean }

/**
 * يبني نصّ السطر من محارفه المرتّبة قراءةً (من اليمين).
 *
 * `pdf.js` في هذا النوع من الملفّات يُصدر **عنصرًا لكلّ محرف**، فالوصل
 * بمسافةٍ بين العناصر يُفرّق حروف الكلمة الواحدة. فنقيس الفجوة بين محرفٍ
 * وسابقه: ما دون خُمس حجم الخطّ التصاقٌ داخل كلمة، وما فوقه فاصلٌ بينها.
 */
function joinRow(row: Ch[]): string {
  let out = ''
  let run: string[] = []          // مقطع أرقامٍ من قطعٍ مفردة

  const flushRun = () => {
    // في ترتيب القراءة العربيّ (تنازليًّا) يظهر الرقم مقلوبًا حتمًا،
    // لأنّ الرقم وحده يُقرأ من اليسار. فنردّه. وهذا يصحّ على كلّ ملفّ.
    if (run.length) { out += run.reverse().join(''); run = [] }
  }

  for (let i = 0; i < row.length; i++) {
    const cur = row[i]
    const isLoneDigit = !cur.atomic && cur.ch.length === 1 && DIGITS.has(cur.ch)
    let space = ''
    if (i > 0) {
      const gap = row[i - 1].x - cur.x1
      // الفجوة التي تفصل كلمتين: أقلّ ممّا يبدو، فحروف العربيّة متّصلة
      if (gap > Math.max(0.6, cur.size * 0.11)) space = ' '
    }
    if (isLoneDigit) {
      if (space) flushRun()
      if (space) out += space
      run.push(cur.ch)
      continue
    }
    flushRun()
    out += space + cur.ch
  }
  flushRun()
  return out
}

export async function importPdf(file: File): Promise<ImportResult> {
  // التحميل عند الطلب: مكتبة PDF ثقيلة ولا تُحمَّل إلّا عند الاستيراد فعلًا
  const pdfjs: any = await import('pdfjs-dist')
  pdfjs.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString()

  const buf = await file.arrayBuffer()
  const doc = await pdfjs.getDocument({ data: buf, isEvalSupported: false }).promise

  const warnings: string[] = []
  const blocks: string[] = []
  let allText = ''
  let landscape = false
  // العنوان: أكبر سطرٍ خطًّا في الصفحة الأولى — لا أوّل سطرٍ فيها.
  // فأوّل ما يُرسم غالبًا ترويسةٌ أو خانةُ اسمٍ، والعنوان هو الأبرز.
  let title = ''
  let titleSize = 0

  for (let n = 1; n <= doc.numPages; n++) {
    const page = await doc.getPage(n)
    const vp = page.getViewport({ scale: 1 })
    if (n === 1) landscape = vp.width > vp.height

    const content = await page.getTextContent()
    const chars: Ch[] = []

    for (const it of content.items as any[]) {
      const raw: string = it.str ?? ''
      if (!raw || !raw.trim()) continue
      const tr = it.transform as number[]
      const x = tr[4]
      const y = vp.height - tr[5]
      const size = Math.abs(tr[3]) || Math.abs(tr[0]) || 10
      const w = it.width || raw.length * size * 0.5

      /* القطعة تُحفظ كما هي: نصّ pdf.js بترتيبٍ منطقيّ. فلا نُقطّع ما وصله
         ولا نُعيد ترتيب ما بداخله — وإلّا قلبنا الأرقام مرّتين. */
      chars.push({ ch: raw, x, x1: x + w, y, size, atomic: raw.length > 1 })
    }

    if (!chars.length) {
      warnings.push(`صفحة ${n}: بلا نصٍّ مُستخرَج — قد تكون صورةً ممسوحة`)
      continue
    }

    // صفوفٌ بتقارب الإحداثيّة الرأسيّة
    chars.sort((a, b) => a.y - b.y)
    const rows: Ch[][] = []
    for (const c of chars) {
      const last = rows[rows.length - 1]
      if (last && Math.abs(last[0].y - c.y) <= Math.max(2.5, c.size * 0.55)) last.push(c)
      else rows.push([c])
    }

    // الحجم الغالب يحدّد المتن، فالعنوان ما علا عليه — لا رقمًا ثابتًا
    const freq = new Map<number, number>()
    for (const c of chars) {
      const k = Math.round(c.size * 2) / 2
      freq.set(k, (freq.get(k) || 0) + 1)
    }
    let body = 10
    let best = 0
    freq.forEach((v, k) => { if (v > best) { best = v; body = k } })

    if (n > 1) blocks.push('<hr>')

    let para: string[] = []
    const flush = () => {
      if (!para.length) return
      blocks.push(`<p>${esc(para.join(' '))}</p>`)
      para = []
    }

    for (const row of rows) {
      /* ترتيب القراءة في العربيّة من اليمين، فنرتّب تنازليًّا. وبعده يصير
         كلّ مقطع أرقامٍ مقلوبًا حتمًا — لأنّ الرقم وحده يُقرأ من اليسار —
         فيردّه fixDigitRuns. حكمٌ يصحّ على كلّ ملفّ، لا على هذا وحده. */
      row.sort((a, b) => b.x - a.x)
      const text = normalizeArabic(joinRow(row)).replace(/\s+/g, ' ').trim()
      if (!text) continue

      allText += text + ' '
      const size = Math.max(...row.map((c) => c.size))
      /* العنوان سطرٌ قصيرٌ كبير. واشتراط القِصَر يمنع أن يصير كلُّ سطرٍ
         عريضٍ عنوانًا — وهو ما كان يحدث فيغرق المستند في العنوانات. */
      const short = text.length <= 90
      const tag = size >= body * 1.5 && short ? 'h1'
        : size >= body * 1.24 && short ? 'h2'
        : size >= body * 1.12 && short && text.length <= 60 ? 'h3'
        : 'p'
      if (tag === 'p') { para.push(text); continue }
      flush()
      blocks.push(`<${tag}>${esc(text)}</${tag}>`)
      if (n === 1 && size > titleSize && text.length > 5) { title = text; titleSize = size }
    }
    flush()
  }

  const health = textHealth(allText)
  if (!health.healthy) {
    warnings.unshift(
      `طبقة النصّ في هذا الملفّ فاسدة (${Math.round(health.ratio * 100)}٪ محارف غريبة`
      + `${health.sample ? ': ' + health.sample : ''}) — الخطّ يخزّن رسوم الحروف برموزٍ`
      + ' غير عربيّة. لا استخراجَ يُصلحها؛ تحتاج قراءةً ضوئيّة.')
  }
  if (/[؀-ۿ]\d|\d[؀-ۿ]/.test(allText)) {
    warnings.push('أرقامٌ ملاصقةٌ لحروفٍ عربيّة — راجع ترتيبها في المحرّر قبل النشر.')
  }
  warnings.push('الجداول لا تُستخرج هنا: أعِد جدولة ما كان مؤطَّرًا بزرّ الجدول في المحرّر.')

  return {
    title: title || file.name.replace(/\.pdf$/i, ''),
    html: blocks.join('\n') || '<p></p>',
    pages: doc.numPages,
    landscape,
    health,
    warnings,
  }
}
