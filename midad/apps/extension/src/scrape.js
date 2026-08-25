/**
 * قارئ الجداول — يُحقن في الصفحة المعروضة ويردّ ما فيها من جداول.
 *
 * ولا يفعل شيئًا غير القراءة: لا ينقر، ولا ينتقل، ولا يقرأ حقل كلمة مرور،
 * ولا يمسّ كوكيز. يقرأ ما تراه العين في اللحظة التي يضغط فيها المعلّم
 * الزرّ، ثمّ ينتهي. وهذا قيدٌ مقصود: التنقّل الآليّ في بوّابةٍ حكوميّة
 * سلوكٌ قد يُعدّ مخالفًا، والمعلّم هو مَن يفتح الصفحة لا نحن.
 *
 * تُحقن هذه الدالّة بـchrome.scripting عند الطلب فقط، فلا تعمل شيفرةٌ
 * في صفحات الوزارة ما لم يطلب المعلّم ذلك بنفسه.
 */

/** يُعيد نصّ الخليّة نظيفًا: بلا مسافاتٍ مضاعفة ولا محارف تحكّم. */
function cellText(el) {
  // `innerText` لا `textContent`: الثاني يجمع نصّ ما هو مخفيٌّ أيضًا،
  // فتخرج في الجدول أعمدةٌ لا يراها المعلّم على شاشته.
  const t = (el.innerText ?? el.textContent ?? '')
  return t.replace(/ /g, ' ').replace(/\s+/g, ' ').trim().slice(0, 500)
}

/** هل العنصر مرئيٌّ فعلًا؟ الجداول المخفيّة ليست ما يراه المعلّم. */
function visible(el) {
  const r = el.getBoundingClientRect()
  if (r.width < 40 || r.height < 20) return false
  const s = getComputedStyle(el)
  return s.display !== 'none' && s.visibility !== 'hidden' && s.opacity !== '0'
}

/**
 * عنوانٌ للجدول من محيطه.
 *
 * نور ومدرستي لا يضعان `<caption>` غالبًا، فنبحث عن أقرب عنوانٍ قبله.
 * وإن لم نجد فاسمُ الصفحة خيرٌ من «جدول ١».
 */
function titleFor(table, index) {
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

  const doc = (document.title || '').trim()
  return doc ? `${doc}${index > 0 ? ` — ${index + 1}` : ''}` : `جدول ${index + 1}`
}

/**
 * يُسوّي صفوف الجدول إلى مصفوفةٍ مستطيلة.
 *
 * الخلايا المدمَجة (`colspan`/`rowspan`) تجعل الصفوف غير متساوية، وجدولٌ
 * غير مستطيل لا يصلح إكسل ولا وورد. فنُكرّر قيمة الخليّة المدمَجة على ما
 * تشغله — وهو ما يفعله الوورد نفسه حين يُفكّ الدمج.
 */
function readGrid(table) {
  const trs = Array.from(table.querySelectorAll('tr')).filter((tr) => tr.cells.length)
  if (!trs.length) return null

  const grid = []
  /** لكلّ صفّ: هل كلّ خلاياه `th`؟ وهو ما يُميّز صفوف العنوان. */
  const allTh = []
  // ما تشغله خليّةٌ ممتدّةٌ رأسيًّا في الصفوف التالية: [صفّ][عمود] = نصّ
  const carry = new Map()

  trs.forEach((tr, r) => {
    allTh.push(Array.from(tr.cells).every((x) => x.tagName === 'TH'))
    const row = []
    let c = 0
    const put = (v) => {
      while (carry.has(`${r},${c}`)) { row[c] = carry.get(`${r},${c}`); c++ }
      row[c] = v
      c++
    }
    for (const cell of Array.from(tr.cells)) {
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
    while (carry.has(`${r},${c}`)) { row[c] = carry.get(`${r},${c}`); c++ }
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
 * `<thead>` إن وُجد. وإلّا فالصفّ الأوّل إن كانت خلاياه `<th>` أو كانت
 * كلّها نصًّا غير رقميّ — فصفّ العناوين لا يحمل أرقامًا عادةً.
 */
function headerOf(grid, allTh) {
  /* صفوف العنوان المتتالية في الصدر تُدمَج.
     ونور يكتب عناوين من صفّين: «الأسبوع الأوّل» فوق «غياب · تأخّر».
     فلو أخذنا الصفّ الأوّل وحده لصار الثاني صفَّ بياناتٍ كاذبًا، ولضاعت
     دلالة العمود. فنجمعهما: «الأسبوع الأوّل · غياب». */
  let n = 0
  while (n < allTh.length && allTh[n] && n < 3) n++

  if (n >= 1) {
    const cols = grid[0].map((_, c) => {
      const parts = []
      for (let r = 0; r < n; r++) {
        const v = (grid[r][c] || '').trim()
        // الخليّة الممتدّة رأسيًّا تتكرّر، فلا نكتبها مرّتين
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

/** يقرأ جداول الصفحة كلّها ويردّ ما يصلح منها. */
function readTables() {
  const out = []
  const tables = Array.from(document.querySelectorAll('table'))

  tables.forEach((table, i) => {
    try {
      if (!visible(table)) return
      const read = readGrid(table)
      if (!read || read.rect.length < 2) return
      const { rect: grid, allTh } = read

      const { cols, skip } = headerOf(grid, allTh)
      const rows = grid.slice(skip).filter((r) => r.some((v) => v !== ''))
      if (!rows.length) return
      // جدولٌ من عمودٍ واحد غالبًا تخطيطٌ لا بيانات
      if (cols.length < 2) return

      out.push({
        index: out.length,
        title: titleFor(table, i),
        columns: cols.map((c, k) => c || `عمود ${k + 1}`),
        rows,
        rowCount: rows.length,
        colCount: cols.length,
      })
    } catch { /* جدولٌ واحدٌ معطوب لا يُسقط البقيّة */ }
  })

  return {
    ok: true,
    url: location.href.split('?')[0].slice(0, 500),
    pageTitle: (document.title || '').trim().slice(0, 160),
    source: /madrasati\.sa/i.test(location.hostname) ? 'madrasati' : 'noor',
    tables: out.slice(0, 25),
  }
}

readTables()
