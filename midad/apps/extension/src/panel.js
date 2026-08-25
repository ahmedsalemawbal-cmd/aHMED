/**
 * لوحة مِداد داخل صفحة نور ومدرستي.
 *
 * ولمَ داخل الصفحة لا في نافذةٍ منفصلة؟ لأنّ المعلّم لا يبحث عن أداة، بل
 * يعمل في نور ثمّ يحتاجها. والنافذة المنفصلة تقتضي أن يتذكّرها ويقصدها؛
 * واللوحة تنتظره حيث هو.
 *
 * وثلاثة قراراتٍ في التصميم قُصدت، وهي ما يفرقنا عمّن سبقنا:
 *
 * ١) **ظلٌّ لا صندوق.** ما رأيناه عند غيرنا صندوقٌ دائمٌ يزاحم صفحة نور
 *    بأزرارٍ ملوّنة. ونحن نبدأ بمقبضٍ صغيرٍ في الحافّة، لا يُفتح إلّا
 *    بطلب. فالصفحة للوزارة، ونحن ضيوف.
 *
 * ٢) **جذرٌ مظلّل (Shadow DOM).** صفحات نور تحمل Bootstrap قديمًا وأنماطًا
 *    عامّةً تطال كلّ عنصر. فلو حقنّا في الصفحة مباشرةً لتشوّهت لوحتنا
 *    عندهم، ولتسرّبت أنماطنا إلى صفحتهم. والجذر المظلّل يعزل الاتّجاهين
 *    عزلًا تامًّا — لا نُفسد عليهم ولا يُفسدون علينا.
 *
 * ٣) **لا نكتب في نور شيئًا.** نقرأ ما هو معروض، ونرسل ما يختاره المعلّم.
 *    لا رصدَ غيابٍ ولا إرسالَ رسائل نيابةً عن المدرسة. وهذا حدٌّ مقصود:
 *    ما يُكتب في بوّابةٍ حكوميّة يُسأل عنه صاحب الحساب لا نحن.
 */

/* لا `import`: سكربتات المحتوى في MV3 لا تقبل الوحدات. والنواة تُحمَّل
   قبلنا في البيان، فتضع نفسها على self.Midad. */
const { getKey, verifyKey, sendTable, readTables } = self.Midad

const HOST_ID = 'midad-host'
const COLLAPSE_KEY = 'midad_panel_open'

if (!document.getElementById(HOST_ID)) boot()

async function boot() {
  const host = document.createElement('div')
  host.id = HOST_ID
  /* أعلى ما يمكن: صفحات نور فيها عناصر بـz-index عالية، ولوحةٌ تختفي
     خلف قائمةٍ منسدلة لا تنفع. و`all: initial` يقطع أيّ وراثةٍ منها. */
  host.style.cssText = 'all: initial; position: fixed; z-index: 2147483646;'
  const root = host.attachShadow({ mode: 'open' })

  const style = document.createElement('style')
  style.textContent = CSS
  root.appendChild(style)

  const wrap = document.createElement('div')
  wrap.className = 'wrap'
  root.appendChild(wrap)

  document.documentElement.appendChild(host)

  const state = {
    open: false,
    linked: null,      // اسم المشترك، أو null إن لم يُربط
    tables: null,
    busy: false,
  }

  /* الطيّ يُحفظ: مَن أغلق اللوحة لا يريدها تعود مفتوحةً في كلّ صفحة. */
  try {
    const o = await chrome.storage.local.get(COLLAPSE_KEY)
    state.open = o?.[COLLAPSE_KEY] === true
  } catch { /* لا شيء */ }

  render(wrap, state, {
    toggle: async () => {
      state.open = !state.open
      try { await chrome.storage.local.set({ [COLLAPSE_KEY]: state.open }) } catch {}
      if (state.open && state.tables === null) await load(wrap, state)
      else render(wrap, state, handlers(wrap, state))
    },
    ...handlersRest(wrap, state),
  })

  if (state.open) await load(wrap, state)
}

function handlers(wrap, state) {
  return {
    toggle: async () => {
      state.open = !state.open
      try { await chrome.storage.local.set({ [COLLAPSE_KEY]: state.open }) } catch {}
      if (state.open && state.tables === null) await load(wrap, state)
      else render(wrap, state, handlers(wrap, state))
    },
    ...handlersRest(wrap, state),
  }
}

function handlersRest(wrap, state) {
  return {
    refresh: () => load(wrap, state),
    send: async (t, onDone) => {
      try {
        const r = await sendTable({
          title: t.title, columns: t.columns, rows: t.rows,
          source_url: location.href.split('?')[0].slice(0, 500),
          source: /madrasati\.sa/i.test(location.hostname) ? 'madrasati' : 'noor',
        })
        onDone(null, r)
      } catch (e) { onDone(e) }
    },
  }
}

async function load(wrap, state) {
  state.busy = true
  render(wrap, state, handlers(wrap, state))
  try {
    const key = await getKey()
    if (!key) { state.linked = null; state.tables = [] }
    else {
      const who = await verifyKey(key).catch(() => null)
      state.linked = who?.subscriber || null
      state.tables = state.linked ? (readTables().tables || []) : []
    }
  } catch {
    state.linked = null
    state.tables = []
  }
  state.busy = false
  render(wrap, state, handlers(wrap, state))
}

/* ═════════════════ الرسم ═════════════════ */

function el(tag, cls, text) {
  const n = document.createElement(tag)
  if (cls) n.className = cls
  if (text != null) n.textContent = text
  return n
}

function svg(paths, size = 18) {
  const s = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
  s.setAttribute('viewBox', '0 0 20 20'); s.setAttribute('width', size); s.setAttribute('height', size)
  s.setAttribute('fill', 'none')
  for (const d of paths) {
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path')
    p.setAttribute('d', d); p.setAttribute('stroke', 'currentColor')
    p.setAttribute('stroke-width', '1.6'); p.setAttribute('stroke-linecap', 'round')
    p.setAttribute('stroke-linejoin', 'round')
    s.appendChild(p)
  }
  return s
}

const I = {
  table: ['M3.4 4.6h13.2v10.8H3.4z', 'M3.4 8.2h13.2', 'M8.6 8.2v7.2'],
  send: ['M17 3.6 9.2 11.4', 'M17 3.6l-5 13.4-2.8-5.6L3.6 8.6z'],
  ok: ['M4.5 10.5l3.6 3.5 7.4-8'],
  close: ['M5.5 5.5l9 9M14.5 5.5l-9 9'],
  refresh: ['M16.4 10a6.4 6.4 0 1 1-1.9-4.5', 'M16.6 3v3.6H13'],
  link: ['M8.4 11.6a3.4 3.4 0 0 0 5 .3l2-2a3.4 3.4 0 0 0-4.8-4.8l-1.1 1.1',
         'M11.6 8.4a3.4 3.4 0 0 0-5-.3l-2 2a3.4 3.4 0 0 0 4.8 4.8l1.1-1.1'],
}

function render(wrap, state, h) {
  wrap.replaceChildren()
  wrap.className = 'wrap' + (state.open ? ' is-open' : '')

  // ── المقبض ──
  const tab = el('button', 'tab')
  tab.title = state.open ? 'أغلق مِداد' : 'مِداد — اسحب الجدول'
  tab.appendChild(el('span', 'tab-mark', 'مِ'))
  if (!state.open) tab.appendChild(el('span', 'tab-lbl', 'مِداد'))
  else tab.appendChild(svg(I.close, 15))
  tab.addEventListener('click', h.toggle)
  wrap.appendChild(tab)

  if (!state.open) return

  // ── اللوحة ──
  const card = el('div', 'card')

  const hd = el('div', 'hd')
  const hdTx = el('div', 'hd-tx')
  hdTx.appendChild(el('strong', null, 'مِداد'))
  hdTx.appendChild(el('span', null, state.linked || 'غير مربوط'))
  hd.appendChild(hdTx)
  const rf = el('button', 'ghost')
  rf.title = 'أعِد قراءة الصفحة'
  rf.appendChild(svg(I.refresh, 15))
  rf.addEventListener('click', h.refresh)
  hd.appendChild(rf)
  card.appendChild(hd)

  const bd = el('div', 'bd')
  card.appendChild(bd)

  if (state.busy) {
    bd.appendChild(el('div', 'sk'))
    bd.appendChild(el('div', 'sk'))
  } else if (!state.linked) {
    bd.appendChild(emptyState({
      ic: I.link,
      tt: 'اربط حسابك أوّلًا',
      ln: 'افتح مِداد ← جداول نور ← انسخ مفتاح الربط، ثمّ الصقه في أيقونة الإضافة بالأعلى.',
    }))
  } else if (!state.tables?.length) {
    bd.appendChild(emptyState({
      ic: I.table,
      tt: 'لا جدولَ في هذه الصفحة',
      ln: 'افتح الكشف أو التقرير حتّى يظهر الجدول، ثمّ أعِد القراءة.',
    }))
  } else {
    for (const t of state.tables) bd.appendChild(row(t, h))
  }

  const ft = el('div', 'ft')
  const a = el('a', 'ft-a', 'افتح مِداد')
  a.href = 'https://ahmedawbal.com/#/app/noor'
  a.target = '_blank'; a.rel = 'noopener'
  ft.appendChild(a)
  ft.appendChild(el('span', 'ft-note', 'نقرأ ولا نكتب في نور'))
  card.appendChild(ft)

  wrap.appendChild(card)
}

function emptyState({ ic, tt, ln }) {
  const b = el('div', 'st')
  const i = el('div', 'st-i'); i.appendChild(svg(ic, 28)); b.appendChild(i)
  b.appendChild(el('div', 'st-t', tt))
  b.appendChild(el('div', 'st-l', ln))
  return b
}

function row(t, h) {
  const b = el('button', 'rw')
  const ic = el('span', 'rw-i'); ic.appendChild(svg(I.table, 16)); b.appendChild(ic)
  const tx = el('span', 'rw-x')
  tx.appendChild(el('span', 'rw-t', t.title))
  const meta = el('span', 'rw-m', `${t.rowCount} صفًّا · ${t.colCount} أعمدة`)
  tx.appendChild(meta)
  b.appendChild(tx)
  const go = el('span', 'rw-g'); go.appendChild(svg(I.send, 15)); b.appendChild(go)

  b.addEventListener('click', () => {
    if (b.disabled) return
    b.disabled = true
    meta.textContent = 'جارٍ الإرسال…'
    h.send(t, (err, r) => {
      if (err) {
        b.disabled = false
        meta.textContent = err.message || 'تعذّر الإرسال'
        b.classList.add('is-bad')
        return
      }
      b.classList.add('is-ok')
      meta.textContent = `وصل إلى مِداد ✓ ${r?.row_count ?? t.rowCount} صفًّا`
      ic.replaceChildren(svg(I.ok, 16))
      go.replaceChildren()
    })
  })
  return b
}

/* ═════════════════ التنسيق ═════════════════
   داخل الجذر المظلّل، فلا يتسرّب إلى نور ولا يتسرّب منها إلينا. */

const CSS = `
:host, * { box-sizing: border-box; }

.wrap {
  position: fixed; inset-inline-start: 0; inset-block-start: 50%;
  transform: translateY(-50%);
  display: flex; align-items: stretch; gap: 0;
  font-family: "Segoe UI", "Noto Naskh Arabic", Tahoma, system-ui, sans-serif;
  direction: rtl;
}

/* ── المقبض: كلّ ما يراه المعلّم حتّى يطلب أكثر ── */
.tab {
  align-self: center;
  display: flex; align-items: center; gap: 7px;
  padding: 10px 9px; border: 0; cursor: pointer;
  background: #1f7a4d; color: #fff;
  border-start-end-radius: 12px; border-end-end-radius: 12px;
  box-shadow: 0 6px 22px -6px rgba(16,12,45,.45);
  font: inherit; font-size: 12px; font-weight: 700;
  writing-mode: vertical-rl;
  transition: background .16s, padding .16s;
}
.tab:hover { background: #17603c; padding-inline: 11px; }
.tab-mark { font-size: 13px; }
.tab-lbl { letter-spacing: 1px; }
.wrap.is-open .tab { writing-mode: horizontal-tb; padding: 9px; border-radius: 0 12px 12px 0; }

/* ── اللوحة ── */
.card {
  inline-size: 340px; max-block-size: min(74vh, 620px);
  display: flex; flex-direction: column;
  background: #fff; color: #14131f;
  border: 1px solid #e4e2ee;
  border-start-end-radius: 16px; border-end-end-radius: 16px;
  box-shadow: 0 24px 60px -18px rgba(16,12,45,.42), 0 2px 8px rgba(16,12,45,.10);
  overflow: hidden;
  animation: in .18s ease-out;
}
@keyframes in { from { opacity: 0; transform: translateX(14px); } }

.hd {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border-block-end: 1px solid #eeecf5;
  background: linear-gradient(180deg, #f6faf7, #fff);
}
.hd-tx { display: flex; flex-direction: column; line-height: 1.35; flex: 1; min-inline-size: 0; }
.hd-tx strong { font-size: 13.5px; }
.hd-tx span {
  font-size: 11px; color: #7c7a92;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ghost {
  inline-size: 28px; block-size: 28px; flex: none;
  border: 1px solid #e4e2ee; background: transparent; color: #4a4860;
  border-radius: 8px; cursor: pointer; display: grid; place-items: center;
}
.ghost:hover { border-color: #1f7a4d; color: #1f7a4d; }

.bd { padding: 11px; display: flex; flex-direction: column; gap: 8px; overflow-y: auto; flex: 1; }

.sk {
  block-size: 52px; border-radius: 11px;
  background: linear-gradient(90deg, #eeecf5 25%, #f8f7fc 50%, #eeecf5 75%);
  background-size: 300% 100%; animation: sh 1.3s ease-in-out infinite;
}
@keyframes sh { to { background-position: -300% 0; } }

.rw {
  display: flex; align-items: center; gap: 10px; inline-size: 100%;
  padding: 10px 11px; border-radius: 11px; cursor: pointer;
  background: #fff; border: 1px solid #e4e2ee;
  font: inherit; color: inherit; text-align: start;
  transition: border-color .14s, transform .14s, background .14s;
}
.rw:hover:not(:disabled) { border-color: #1f7a4d; transform: translateX(-2px); }
.rw:disabled { cursor: default; }
.rw.is-ok { border-color: #1f7a4d; background: #f2f9f5; }
.rw.is-bad { border-color: #b3261e; }
.rw-i {
  inline-size: 32px; block-size: 32px; border-radius: 9px; flex: none;
  display: grid; place-items: center; background: #e7f3ec; color: #1f7a4d;
}
.rw-x { flex: 1; min-inline-size: 0; display: flex; flex-direction: column; }
.rw-t {
  display: block; font-size: 12.5px; font-weight: 600; line-height: 1.45;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.rw-m { display: block; font-size: 10.5px; color: #7c7a92; margin-block-start: 2px; }
.rw.is-bad .rw-m { color: #b3261e; }
.rw-g { color: #b9b5cf; flex: none; }
.rw:hover:not(:disabled) .rw-g { color: #1f7a4d; }

.st { text-align: center; padding: 20px 12px; }
.st-i { color: #b9b5cf; margin-block-end: 9px; }
.st-t { font-size: 13px; font-weight: 600; margin-block-end: 5px; }
.st-l { font-size: 11.5px; color: #7c7a92; line-height: 1.8; }

.ft {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 14px; border-block-start: 1px solid #eeecf5; background: #fbfbfd;
  font-size: 10.5px;
}
.ft-a { color: #1f7a4d; font-weight: 700; text-decoration: none; }
.ft-a:hover { text-decoration: underline; }
.ft-note { color: #9a97ae; margin-inline-start: auto; }

@media (prefers-color-scheme: dark) {
  .card { background: #1e1d27; color: #eeedf5; border-color: #2c2a3a; }
  .hd { background: linear-gradient(180deg, #1a2620, #1e1d27); border-block-end-color: #2c2a3a; }
  .hd-tx span, .rw-m, .st-l, .ft-note { color: #8b88a6; }
  .ghost { border-color: #2c2a3a; color: #b9b6cb; }
  .rw { background: #23222e; border-color: #2c2a3a; }
  .rw.is-ok { background: #16301f; }
  .rw-i { background: #16301f; }
  .ft { background: #1a1922; border-block-start-color: #2c2a3a; }
  .sk { background: linear-gradient(90deg, #2c2a3a 25%, #23222e 50%, #2c2a3a 75%); background-size: 300% 100%; }
}
`
