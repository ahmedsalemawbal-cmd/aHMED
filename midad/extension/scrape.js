/**
 * يُحقن في صفحة نور عند ضغط المستخدم وحده — لا يعمل تلقائيًّا أبدًا.
 * يقرأ الجداول المعروضة في الصفحة كما هي، ولا يقرأ كلمات المرور ولا يرسل شيئًا بنفسه.
 */
function midadScrapeTables() {
  const clean = (s) => String(s ?? '').replace(/ /g, ' ').replace(/\s+/g, ' ').trim()

  const looksLikeLayout = (table) => {
    // جداول التخطيط في نور بلا رؤوس ولا صفوف حقيقية
    const rows = table.querySelectorAll('tr')
    if (rows.length < 2) return true
    const cells = rows[1] ? rows[1].querySelectorAll('td, th').length : 0
    return cells < 2
  }

  const titleFor = (table, index) => {
    // ابحث عن أقرب عنوانٍ فوق الجدول
    let el = table.previousElementSibling
    let hops = 0
    while (el && hops < 6) {
      const t = clean(el.textContent)
      if (t && t.length > 2 && t.length < 120 && !/^\s*$/.test(t)) return t
      el = el.previousElementSibling
      hops++
    }
    const cap = table.querySelector('caption')
    if (cap && clean(cap.textContent)) return clean(cap.textContent)
    return `جدول ${index + 1} — ${clean(document.title).slice(0, 60) || 'نور'}`
  }

  const out = []
  const tables = Array.from(document.querySelectorAll('table'))

  tables.forEach((table, i) => {
    if (looksLikeLayout(table)) return

    let headers = Array.from(table.querySelectorAll('thead th')).map((th) => clean(th.textContent))
    let bodyRows = Array.from(table.querySelectorAll('tbody tr'))

    if (!headers.length) {
      const all = Array.from(table.querySelectorAll('tr'))
      if (!all.length) return
      const first = all[0]
      headers = Array.from(first.querySelectorAll('th, td')).map((c) => clean(c.textContent))
      bodyRows = all.slice(1)
    }
    if (!bodyRows.length) bodyRows = Array.from(table.querySelectorAll('tr')).slice(1)
    if (!headers.length || !bodyRows.length) return

    const rows = bodyRows
      .map((tr) => Array.from(tr.querySelectorAll('td, th')).map((td) => clean(td.textContent)))
      .filter((r) => r.length && r.some((cell) => cell !== ''))

    if (!rows.length) return

    // وحّد عدد الأعمدة على الرأس
    const width = headers.length
    const normalized = rows.map((r) =>
      r.length === width ? r : width > r.length
        ? r.concat(Array(width - r.length).fill(''))
        : r.slice(0, width))

    out.push({
      title: titleFor(table, i).slice(0, 160),
      columns: headers.map((h, hi) => h || `عمود ${hi + 1}`),
      rows: normalized,
      row_count: normalized.length,
    })
  })

  return { url: location.href, pageTitle: clean(document.title), tables: out }
}
