/**
 * نافذة الإضافة: تعرض جداول الصفحة، وترسل ما يختاره المعلّم إلى مِداد.
 *
 * كلّ ما يُعرض هنا يُبنى بـ`textContent` لا بـ`innerHTML`. وعناوين الجداول
 * تأتي من صفحةٍ لا نملكها، فلو حُقنت في HTML لصار عنوانُ جدولٍ في نور
 * شيفرةً تعمل في سياق الإضافة. والقاعدة أبسط من الاستثناء: لا نبني HTML
 * من نصٍّ خارجيّ أصلًا.
 */

/* لا `import`: النواة تُحمَّل قبلنا وتضع نفسها على self.Midad. وسبب ذلك
   أنّ سكربتات المحتوى في MV3 لا تقبل الوحدات، والنواة مشتركةٌ بيننا
   وبين اللوحة — فلو كانت وحدةً لعملت هنا وسقطت هناك. */
const { getKey, setKey, verifyKey, sendTable } = self.Midad

const view = document.getElementById('view')
const ftState = document.getElementById('ftState')
const hdSub = document.getElementById('hdSub')

/* ═════════════════ أدوات بناء العناصر ═════════════════ */

function el(tag, cls, text) {
  const n = document.createElement(tag)
  if (cls) n.className = cls
  if (text != null) n.textContent = text
  return n
}

function icon(paths, size = 17) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
  svg.setAttribute('viewBox', '0 0 20 20')
  svg.setAttribute('width', size); svg.setAttribute('height', size)
  svg.setAttribute('fill', 'none'); svg.setAttribute('aria-hidden', 'true')
  for (const d of paths) {
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path')
    p.setAttribute('d', d)
    p.setAttribute('stroke', 'currentColor')
    p.setAttribute('stroke-width', '1.6')
    p.setAttribute('stroke-linecap', 'round')
    p.setAttribute('stroke-linejoin', 'round')
    svg.appendChild(p)
  }
  return svg
}

const IC = {
  table: ['M3.4 4.6h13.2v10.8H3.4z', 'M3.4 8.2h13.2', 'M8.6 8.2v7.2'],
  chev: ['M12 5.5 7.5 10l4.5 4.5'],
  ok: ['M4.5 10.5l3.6 3.5 7.4-8'],
  warn: ['M10 3.5 2.8 16h14.4z', 'M10 8v3.4', 'M10 13.6v.1'],
  key: ['M12.6 4.4a3.6 3.6 0 1 0-3.3 6.1L4 15.8v1.8h2.4l.7-1.6h1.7l.6-1.6h1.7l1.4-1.4a3.6 3.6 0 0 0 .1-8.6'],
  spin: ['M10 3.2a6.8 6.8 0 1 0 6.8 6.8'],
}

function clear() { while (view.firstChild) view.removeChild(view.firstChild) }

function state({ ic, title, line, action }) {
  clear()
  const box = el('div', 'st')
  const i = el('div', 'st-ic'); i.appendChild(icon(ic, 34)); box.appendChild(i)
  box.appendChild(el('div', 'st-tt', title))
  if (line) box.appendChild(el('div', 'st-ln', line))
  if (action) box.appendChild(action)
  view.appendChild(box)
}

/* ═════════════════ شاشة الربط ═════════════════ */

async function screenKey(msg) {
  clear()
  hdSub.textContent = 'اربط حسابك'

  if (msg) view.appendChild(el('div', 'note note--bad', msg))

  const fld = el('div', 'fld')
  fld.appendChild(el('label', null, 'مفتاح الربط'))
  const input = el('input')
  input.type = 'text'
  input.placeholder = 'MDD-XXXXXXXX-XXXXXXXX-XXXXXXXX'
  input.spellcheck = false
  input.autocomplete = 'off'
  fld.appendChild(input)
  fld.appendChild(el('div', 'hint',
    'افتح مِداد ← جداول نور ← مفتاح الربط، وانسخه من هناك. يُحفظ في متصفّحك وحده.'))
  view.appendChild(fld)

  const btn = el('button', 'btn')
  btn.style.inlineSize = '100%'
  btn.appendChild(icon(IC.key, 15))
  btn.appendChild(el('span', null, 'اربط'))
  view.appendChild(btn)

  const go = async () => {
    const key = input.value.trim()
    if (!key) { input.focus(); return }
    btn.disabled = true
    btn.textContent = 'جارٍ التحقّق…'
    try {
      const r = await verifyKey(key)
      await setKey(key)
      ftState.textContent = `${r.subscriber || ''}`
      await screenTables()
    } catch (e) {
      btn.disabled = false
      clear()
      await screenKey(e.message || 'تعذّر التحقّق من المفتاح')
    }
  }
  btn.addEventListener('click', go)
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter') go() })
  input.focus()
}

/* ═════════════════ شاشة الجداول ═════════════════ */

async function readPage() {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true })
  if (!tab?.id) throw new Error('لا صفحةَ مفتوحة')

  const host = (() => { try { return new URL(tab.url).hostname } catch { return '' } })()
  const allowed = /(^|\.)moe\.gov\.sa$/i.test(host) || /(^|\.)madrasati\.sa$/i.test(host)
  if (!allowed) {
    const err = new Error('افتح صفحة الجدول في نور أو مدرستي، ثمّ اضغط الإضافة.')
    err.code = 'OFFSITE'
    throw err
  }

  /* حقنٌ ثمّ نداء: النواة لم تعد تُنادي القارئ في آخرها — كانت تفعل حين
     كانت ملفًّا للحقن وحده، فلمّا صارت مشتركةً مع اللوحة لزم أن تُعرّف
     ولا تُنفّذ، وإلّا قرأت كلَّ صفحةٍ تُفتح بلا سبب. */
  await chrome.scripting.executeScript({ target: { tabId: tab.id }, files: ['src/core.js'] })
  const [res] = await chrome.scripting.executeScript({
    target: { tabId: tab.id },
    func: () => self.Midad.readTables(),
  })
  return res?.result || null
}

function tableCard(t, onPick) {
  const b = el('button', 'tb')
  const ic = el('span', 'tb-ic'); ic.appendChild(icon(IC.table)); b.appendChild(ic)

  const tx = el('span', 'tb-tx')
  tx.appendChild(el('span', 'tb-tt', t.title))
  tx.appendChild(el('span', 'tb-mt', `${t.rowCount} صفًّا · ${t.colCount} أعمدة`))
  b.appendChild(tx)

  const go = el('span', 'tb-go'); go.appendChild(icon(IC.chev, 15)); b.appendChild(go)

  b.addEventListener('click', () => onPick(t, b))
  return b
}

async function screenTables() {
  clear()
  hdSub.textContent = 'جارٍ قراءة الصفحة…'
  view.appendChild(el('div', 'skel'))
  view.appendChild(el('div', 'skel'))

  let page
  try {
    page = await readPage()
  } catch (e) {
    if (e.code === 'OFFSITE') {
      state({ ic: IC.warn, title: 'لسنا في نور ولا مدرستي', line: e.message })
    } else {
      state({ ic: IC.warn, title: 'تعذّرت قراءة الصفحة', line: e.message || '' })
    }
    hdSub.textContent = 'اسحب الجدول المعروض'
    return
  }

  const tables = page?.tables || []
  hdSub.textContent = page.source === 'madrasati' ? 'مدرستي' : 'نور'

  if (!tables.length) {
    const again = el('button', 'btn btn--ghost', 'أعِد القراءة')
    again.addEventListener('click', screenTables)
    state({
      ic: IC.table,
      title: 'لا جدولَ في هذه الصفحة',
      line: 'افتح الكشف أو التقرير الذي تريد سحبه حتّى يظهر الجدول على الشاشة، ثمّ أعِد القراءة.',
      action: again,
    })
    return
  }

  clear()
  view.appendChild(el('div', 'note',
    `وجدنا ${tables.length === 1 ? 'جدولًا واحدًا' : `${tables.length} جداول`}. اختر ما تريد إرساله.`))

  const onPick = async (t, btn) => {
    const all = view.querySelectorAll('.tb')
    all.forEach((x) => { x.disabled = true })
    const meta = btn.querySelector('.tb-mt')
    const was = meta.textContent
    meta.textContent = 'جارٍ الإرسال…'
    try {
      const r = await sendTable({
        title: t.title,
        columns: t.columns,
        rows: t.rows,
        source_url: page.url,
        source: page.source,
      })
      meta.textContent = `أُرسل ✓ ${r.row_count ?? t.rowCount} صفًّا`
      btn.querySelector('.tb-ic').replaceChildren(icon(IC.ok))
      ftState.textContent = 'وصل إلى مِداد'
      all.forEach((x, i) => { if (x !== btn) x.disabled = false })
    } catch (e) {
      meta.textContent = was
      all.forEach((x) => { x.disabled = false })
      const bad = el('div', 'note note--bad', e.message || 'تعذّر الإرسال')
      view.insertBefore(bad, view.firstChild)
      if (e.code === 'BADKEY') { await setKey(''); await screenKey(e.message) }
    }
  }

  for (const t of tables) view.appendChild(tableCard(t, onPick))
}

/* ═════════════════ البداية ═════════════════ */

document.getElementById('btnSettings').addEventListener('click', async () => {
  await screenKey()
})

;(async () => {
  ftState.textContent = ''
  const key = await getKey()
  if (!key) { await screenKey(); return }
  try {
    const r = await verifyKey(key)
    ftState.textContent = r.subscriber || ''
    await screenTables()
  } catch (e) {
    await screenKey(e.message || 'انتهت صلاحيّة المفتاح')
  }
})()
