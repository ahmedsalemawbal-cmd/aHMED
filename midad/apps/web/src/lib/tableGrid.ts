/**
 * قراءة جدولٍ من متن HTML — أيًّا كان شكله.
 *
 * هذا هو المنطق نفسه الذي تعمل به إضافةُ كروم في صفحة نور. وأُخرِج إلى
 * هنا ليعمل على **متنٍ ملصوق** كما يعمل على صفحةٍ مفتوحة، فيصير للمعلّم
 * بابان إلى الجدول الواحد ونتيجتُهما واحدة:
 *
 *   ① الإضافة — تقرأ الصفحة المفتوحة أمامه.
 *   ② اللصق  — يُظلّل الجدول وينسخه، فينسخ المتصفّح **بنيته كاملةً**
 *      (`text/html` في الحافظة) لا نصًّا مبعثرًا. فتُقرأ كما تُقرأ الصفحة.
 *
 * ولا يفترض هذا القارئ شيئًا عن الجدول: لا عدد أعمدة، ولا أسماءها، ولا
 * أنّ له `thead`، ولا أنّ خلاياه غير مدمجة. أيُّ جدولٍ في نور أو مدرستي
 * أو غيرهما يُقرأ بالقواعد نفسها.
 *
 *     بابان إلى الشيء الواحد يجب أن يُخرجاه واحدًا.
 *
 * ولذا يقارن `tests/paste.mjs` ناتجَ هذا القارئ بناتج `core.js` في
 * الإضافة على العيّنات نفسها. فإن تفرّقا سقط الفحص — إذ لا معنى لبابٍ
 * يعطي المعلّم جدولًا يخالف ما يعطيه الآخر.
 */

export interface GridTable {
  title: string
  columns: string[]
  rows: string[][]
  rowCount: number
  colCount: number
}

/** يُعيد نصّ الخليّة نظيفًا: بلا مسافاتٍ مضاعفة ولا محارف تحكّم. */
function cellText(el: Element): string {
  /* `innerText` إن وُجد لا `textContent`: الثاني يجمع نصّ ما هو مخفيٌّ
     أيضًا، فتخرج أعمدةٌ لا يراها المعلّم. وفي متنٍ غير مُلحقٍ بالمستند
     لا `innerText` له، فيُقرأ `textContent` — ولا خفيَّ فيه أصلًا. */
  const t = (el as HTMLElement).innerText ?? el.textContent ?? ''
  return t.replace(/ /g, ' ').replace(/\s+/g, ' ').trim().slice(0, 500)
}

/**
 * يُسوّي صفوف الجدول إلى مصفوفةٍ مستطيلة.
 *
 * الخلايا المدمَجة (`colspan`/`rowspan`) تجعل الصفوف غير متساوية، وجدولٌ
 * غير مستطيلٍ لا يصلح إكسل ولا وورد. فتُكرَّر قيمة الخليّة المدمَجة على ما
 * تشغله من أعمدةٍ وصفوف — فيخرج مستطيلًا بلا ثقوب.
 */
function readGrid(table: Element): { rect: string[][]; allTh: boolean[] } | null {
  const trs = Array.from(table.querySelectorAll('tr'))
    .filter((tr) => (tr as HTMLTableRowElement).cells?.length)
  if (!trs.length) return null

  const grid: string[][] = []
  /** لكلّ صفّ: هل كلّ خلاياه `th`؟ وهو ما يُميّز صفوف العنوان. */
  const allTh: boolean[] = []
  // ما تشغله خليّةٌ ممتدّةٌ رأسيًّا في الصفوف التالية: «صفّ,عمود» → نصّ
  const carry = new Map<string, string>()

  trs.forEach((tr, r) => {
    const cells = Array.from((tr as HTMLTableRowElement).cells)
    allTh.push(cells.every((x) => x.tagName === 'TH'))
    const row: string[] = []
    let c = 0
    const put = (v: string) => {
      while (carry.has(`${r},${c}`)) { row[c] = carry.get(`${r},${c}`)!; c++ }
      row[c] = v
      c++
    }
    for (const cell of cells) {
      const text = cellText(cell)
      const cs = Math.min(Number(cell.getAttribute('colspan') || 1) || 1, 40)
      const rs = Math.min(Number(cell.getAttribute('rowspan') || 1) || 1, 200)
      const startC = (() => {
        let k = c
        while (carry.has(`${r},${k}`)) k++
        return k
      })()
      for (let i = 0; i < cs; i++) {
        put(text)
        for (let j = 1; j < rs; j++) carry.set(`${r + j},${startC + i}`, text)
      }
    }
    // ما بقي من امتدادٍ رأسيٍّ في ذيل الصفّ
    while (carry.has(`${r},${c}`)) { row[c] = carry.get(`${r},${c}`)!; c++ }
    grid.push(row)
  })

  const width = Math.max(...grid.map((r) => r.length))
  if (width < 2) return null
  const rect = grid.map((r) => {
    const out = r.slice(0, width)
    for (let i = 0; i < width; i++) out[i] = out[i] ?? ''
    return out
  })
  return { rect, allTh }
}

/**
 * أيّ صفٍّ هو العنوان؟
 *
 * صفوف العنوان المتتالية في الصدر تُدمَج. ونور يكتب عناوين من صفّين:
 * «الأسبوع الأوّل» فوق «غياب · تأخّر». فلو أُخذ الصفّ الأوّل وحده لصار
 * الثاني صفَّ بياناتٍ كاذبًا، ولضاعت دلالة العمود.
 *
 * وإن لم يكن ثمّة `th` أصلًا، فالصفّ الأوّل عنوانٌ إن خلا من الأرقام —
 * فصفّ العناوين لا يحمل أرقامًا عادةً. وإلّا فلا عنوان، وتُسمّى الأعمدة
 * بأرقامها ولا يُفقد صفٌّ من البيانات.
 */
function headerOf(grid: string[][], allTh: boolean[]): { cols: string[]; skip: number } {
  let n = 0
  while (n < allTh.length && allTh[n] && n < 3) n++

  if (n >= 1) {
    const cols = grid[0].map((_, c) => {
      const parts: string[] = []
      for (let r = 0; r < n; r++) {
        const v = (grid[r][c] || '').trim()
        // الخليّة الممتدّة رأسيًّا تتكرّر، فلا تُكتب مرّتين
        if (v && parts[parts.length - 1] !== v) parts.push(v)
      }
      return parts.join(' · ')
    })
    if (cols.some(Boolean)) return { cols, skip: n }
  }

  const first = grid[0] || []
  const looksHeader =
    first.length >= 2 &&
    first.every((v) => v && !/^[\d٠-٩.,\-/\s]+$/.test(v))
  if (looksHeader) return { cols: first, skip: 1 }
  return { cols: first.map((_, i) => `عمود ${i + 1}`), skip: 0 }
}

/** عنوانٌ للجدول من محيطه — فنور ومدرستي لا يضعان `<caption>` غالبًا. */
function titleFor(table: Element, index: number, fallback: string): string {
  const cap = table.querySelector('caption')
  if (cap && cellText(cap)) return cellText(cap)

  let node = table.previousElementSibling
  let hops = 0
  while (node && hops < 4) {
    if (/^H[1-6]$/.test(node.tagName) || node.classList?.contains('panel-title')) {
      const t = cellText(node)
      if (t && t.length < 160) return t
    }
    node = node.previousElementSibling
    hops++
  }

  let up = table.parentElement
  hops = 0
  while (up && hops < 3) {
    const h = up.querySelector('h1, h2, h3, h4, legend, .panel-title, .card-title')
    if (h) {
      const t = cellText(h)
      if (t && t.length < 160) return t
    }
    up = up.parentElement
    hops++
  }
  return fallback ? `${fallback}${index > 0 ? ` — ${index + 1}` : ''}` : `جدول ${index + 1}`
}

/**
 * يقرأ كلّ جدولٍ في المتن المُعطى.
 *
 * والجدول المتداخل يُترك لأبيه: نور يلفّ جداوله أحيانًا في جدولٍ للتخطيط،
 * فلو قُرئ الاثنان لخرج الجدول مرّتين — مرّةً وحده ومرّةً داخل ما يحويه.
 * فيُؤخذ الأعمق: هو الذي فيه البيانات.
 */
export function readTablesFrom(root: ParentNode, pageTitle = ''): GridTable[] {
  const all = Array.from(root.querySelectorAll('table'))
  const deepest = all.filter((t) => !t.querySelector('table'))
  const tables = deepest.length ? deepest : all

  const out: GridTable[] = []
  tables.forEach((table, i) => {
    try {
      const read = readGrid(table)
      if (!read || read.rect.length < 2) return
      const { rect: grid, allTh } = read

      const { cols, skip } = headerOf(grid, allTh)
      const rows = grid.slice(skip).filter((r) => r.some((v) => v !== ''))
      if (!rows.length) return
      // جدولٌ من عمودٍ واحدٍ غالبًا تخطيطٌ لا بيانات
      if (cols.length < 2) return

      out.push({
        title: titleFor(table, i, pageTitle),
        columns: cols.map((c, k) => c || `عمود ${k + 1}`),
        rows,
        rowCount: rows.length,
        colCount: cols.length,
      })
    } catch { /* جدولٌ واحدٌ معطوب لا يُسقط البقيّة */ }
  })
  return out.slice(0, 25)
}

/**
 * يقرأ ما في الحافظة.
 *
 * والأولويّة لـ`text/html`: هو الذي يحمل بنية الجدول. فإن لم يكن — نُسخ
 * من ملفٍّ نصّيٍّ أو طرفيّة — قُرئ النصّ المفصول بجدولةٍ (وهو ما يُنتجه
 * إكسل والحافظة النصّيّة)، وذاك خيرٌ من لا شيء.
 */
export function readClipboard(data: DataTransfer | null): GridTable[] {
  const html = data?.getData('text/html') || ''
  if (html.trim()) {
    const doc = new DOMParser().parseFromString(html, 'text/html')
    const found = readTablesFrom(doc.body, '')
    if (found.length) return found
  }

  const text = data?.getData('text/plain') || ''
  const grid = text
    .split(/\r?\n/)
    .map((line) => line.split('\t'))
    .filter((r) => r.some((v) => v.trim() !== ''))
  if (grid.length < 2) return []
  const width = Math.max(...grid.map((r) => r.length))
  if (width < 2) return []

  const rect = grid.map((r) => {
    const out = r.map((v) => v.trim())
    for (let i = 0; i < width; i++) out[i] = out[i] ?? ''
    return out.slice(0, width)
  })
  const { cols, skip } = headerOf(rect, [])
  const rows = rect.slice(skip)
  if (!rows.length) return []
  return [{
    title: '',
    columns: cols.map((c, k) => c || `عمود ${k + 1}`),
    rows,
    rowCount: rows.length,
    colCount: cols.length,
  }]
}
