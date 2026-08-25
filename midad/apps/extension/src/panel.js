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

async function boot() {
  const host = document.createElement('div')
  host.id = HOST_ID
  /* أعلى ما يمكن: صفحات نور فيها عناصر بـz-index عالية، ولوحةٌ تختفي
     خلف قائمةٍ منسدلة لا تنفع. و`all: initial` يقطع أيّ وراثةٍ منها. */
  host.style.cssText = 'all: initial; position: fixed; z-index: 2147483646;'
  const root = host.attachShadow({ mode: 'open' })

  const style = document.createElement('style')
  style.textContent = PANEL_CSS
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
  out: ['M8 4.4H4.6v11h11V12', 'M11.4 4.4h4.2v4.2', 'M9.4 10.6l6.2-6.2'],
  shield: ['M10 2.8l5.6 2.2v4.2c0 3.4-2.3 6.2-5.6 7.2-3.3-1-5.6-3.8-5.6-7.2V5z'],
}

function render(wrap, state, h) {
  wrap.replaceChildren()
  wrap.className = 'wrap' + (state.open ? ' is-open' : '')

  const count = state.tables?.length || 0

  /* ── المقبض ──
     عائمٌ عن الحافّة لا ملتصقٌ بها: الملتصق يبدو جزءًا من الصفحة فيضيع
     بين عناصرها، والعائم يبدو طبقةً فوقها فتعرفه العين فورًا. */
  const tab = el('button', 'tab')
  tab.title = state.open ? 'أغلق مِداد' : 'مِداد — اسحب الجدول'
  const mark = el('span', 'tab-m')
  mark.appendChild(state.open ? svg(I.close, 14) : logoMark(18))
  tab.appendChild(mark)
  if (!state.open) {
    tab.appendChild(el('span', 'tab-l', 'مِداد'))
    // عدّادٌ لا يظهر إلّا حين يكون في الصفحة ما يُسحب
    if (count) tab.appendChild(el('span', 'tab-n', String(count)))
  }
  tab.addEventListener('click', h.toggle)
  wrap.appendChild(tab)

  if (!state.open) return

  // ── اللوحة ──
  const card = el('div', 'card')

  // الترويسة: هويّةٌ أوّلًا، ثمّ الحساب، ثمّ أداة الإنعاش
  const hd = el('div', 'hd')
  const badge = el('span', 'hd-b')
  badge.appendChild(logoMark(19))
  hd.appendChild(badge)

  const hdTx = el('div', 'hd-x')
  hdTx.appendChild(el('span', 'hd-t', 'مِداد'))
  const who = el('span', 'hd-s')
  if (state.linked) {
    who.appendChild(el('i', 'dot'))
    who.appendChild(el('span', null, state.linked))
  } else {
    who.appendChild(el('span', null, 'غير مربوط'))
  }
  hdTx.appendChild(who)
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
    for (let i = 0; i < 2; i++) {
      const k = el('div', 'sk'); k.style.animationDelay = `${i * 90}ms`; bd.appendChild(k)
    }
  } else if (!state.linked) {
    bd.appendChild(emptyState({
      ic: I.link, tt: 'اربط حسابك أوّلًا',
      ln: 'افتح مِداد ← جداول نور ← انسخ مفتاح الربط، ثمّ الصقه في أيقونة الإضافة بالأعلى.',
    }))
  } else if (!count) {
    bd.appendChild(emptyState({
      ic: I.table, tt: 'لا جدولَ في هذه الصفحة',
      ln: 'افتح الكشف أو التقرير حتّى يظهر الجدول، ثمّ أعِد القراءة.',
    }))
  } else {
    const cap = el('div', 'cap')
    cap.appendChild(el('span', 'cap-n', String(count)))
    cap.appendChild(el('span', null, count === 1 ? 'جدولٌ جاهزٌ للسحب' : 'جداولُ جاهزةٌ للسحب'))
    bd.appendChild(cap)
    state.tables.forEach((t, i) => bd.appendChild(row(t, h, i)))
  }

  const ft = el('div', 'ft')
  const a = el('a', 'ft-a', 'افتح مِداد')
  a.href = 'https://ahmedawbal.com/#/app/noor'
  a.target = '_blank'; a.rel = 'noopener'
  a.appendChild(svg(I.out, 12))
  ft.appendChild(a)
  const vow = el('span', 'ft-v')
  vow.appendChild(svg(I.shield, 12))
  vow.appendChild(el('span', null, 'نقرأ ولا نكتب في نور'))
  ft.appendChild(vow)
  card.appendChild(ft)

  wrap.appendChild(card)
}

/** شعار مِداد: قلمٌ مائلٌ وقطرةُ حبر — لا حرفٌ في مربّع. */
function logoMark(size) {
  const s = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
  s.setAttribute('viewBox', '0 0 24 24')
  s.setAttribute('width', size); s.setAttribute('height', size)
  s.setAttribute('fill', 'none')
  const nib = document.createElementNS('http://www.w3.org/2000/svg', 'path')
  nib.setAttribute('d', 'M17.8 3.6 20.4 6.2 9.6 17H7v-2.6z')
  nib.setAttribute('fill', 'currentColor')
  const tail = document.createElementNS('http://www.w3.org/2000/svg', 'path')
  tail.setAttribute('d', 'M4.4 20.4h15.2')
  tail.setAttribute('stroke', 'currentColor')
  tail.setAttribute('stroke-width', '2'); tail.setAttribute('stroke-linecap', 'round')
  tail.setAttribute('opacity', '.55')
  s.appendChild(nib); s.appendChild(tail)
  return s
}

function emptyState({ ic, tt, ln }) {
  const b = el('div', 'st')
  const i = el('div', 'st-i'); i.appendChild(svg(ic, 26)); b.appendChild(i)
  b.appendChild(el('div', 'st-t', tt))
  b.appendChild(el('div', 'st-l', ln))
  return b
}

/**
 * صفّ الجدول.
 *
 * يعرض أسماء الأعمدة لا عددها: «اسم الطالب · الرياضيات · العلوم» تُعرّف
 * الجدول في لمحة، و«٥ أعمدة» لا تُعرّف شيئًا. والمعلّم يفتح صفحةً فيها
 * جداولٌ متشابهةٌ العناوين، فالأعمدة هي ما يُفرّق.
 */
function row(t, h, i) {
  const b = el('button', 'rw')
  b.style.animationDelay = `${60 + i * 55}ms`

  const ic = el('span', 'rw-i'); ic.appendChild(svg(I.table, 16)); b.appendChild(ic)

  const tx = el('span', 'rw-x')
  tx.appendChild(el('span', 'rw-t', t.title))

  const chips = el('span', 'rw-c')
  const shown = (t.columns || []).slice(0, 2)
  shown.forEach((c) => chips.appendChild(el('i', 'chip', c)))
  if ((t.columns || []).length > shown.length) {
    chips.appendChild(el('i', 'chip chip--n', `+${t.columns.length - shown.length}`))
  }
  tx.appendChild(chips)

  b.appendChild(tx)

  /* العدّاد عمودٌ مستقلٌّ لا سطرٌ تحت الرقائق: الرقم هو أوّل ما تسأل عنه
     العين — «كم صفًّا؟» — فيستحقّ موضعًا ثابتًا لا يتزحزح بطول العنوان. */
  const meta = el('span', 'rw-m')
  meta.appendChild(el('b', 'rw-num', String(t.rowCount)))
  meta.appendChild(el('span', 'rw-lbl', 'صفًّا'))
  b.appendChild(meta)

  const go = el('span', 'rw-g'); go.appendChild(svg(I.send, 15)); b.appendChild(go)

  b.addEventListener('click', () => {
    if (b.disabled) return
    b.disabled = true
    b.classList.add('is-busy')
    meta.replaceChildren(el('span', 'rw-lbl', 'يُرسَل…'))
    h.send(t, (err, r) => {
      b.classList.remove('is-busy')
      if (err) {
        b.disabled = false
        b.classList.add('is-bad')
        meta.replaceChildren(el('span', 'rw-lbl', 'تعذّر'))
        b.title = err.message || 'تعذّر الإرسال'
        return
      }
      b.classList.add('is-ok')
      meta.replaceChildren(
        el('b', 'rw-num', String(r?.row_count ?? t.rowCount)),
        el('span', 'rw-lbl', 'وصلت'),
      )
      ic.replaceChildren(svg(I.ok, 16))
      go.replaceChildren(svg(I.ok, 15))
    })
  })
  return b
}

/* ═════════════════ التنسيق ═════════════════
   داخل الجذر المظلّل، فلا يتسرّب إلى نور ولا يتسرّب منها إلينا. */

const PANEL_CSS = `
:host, * { box-sizing: border-box; }

/* الحافّة اليسرى، وبخصائص ماديّة لا منطقيّة.
   وسببان: أنّ نور يضع قائمته الرأسيّة على اليمين — فلوحةٌ هناك تحجب
   ملاحته؛ وأنّ خلط المنطقيّ بالماديّ في سياقٍ عربيّ فخٌّ وقعتُ فيه من
   قبل: inset-inline-end يعني اليسار، وtransform-origin: right يعني
   اليمين، فلا يتّفقان.
   والحاوية ltr كي يقع المقبض يسارًا والبطاقة يمينه؛ والعربيّة تعود
   داخل البطاقة نفسها. */
.wrap {
  position: fixed; left: 14px; top: 50%;
  transform: translateY(-50%);
  display: flex; align-items: center; gap: 10px;
  direction: ltr;
  font-family: "Segoe UI", "Noto Naskh Arabic", Tahoma, system-ui, sans-serif;
  --g:  #1f7a4d;
  --g2: #17603c;
}

/* ── المقبض ──
   عائمٌ عن الحافّة لا ملتصقٌ بها: الملتصق يبدو جزءًا من الصفحة فيضيع بين
   عناصرها، والعائم يبدو طبقةً فوقها فتعرفه العين فورًا. */
.tab {
  display: flex; flex-direction: column; align-items: center; gap: 7px;
  padding: 13px 9px; border: 0; cursor: pointer;
  border-radius: 16px;
  background: linear-gradient(160deg, #249b60 0%, #1f7a4d 45%, #17603c 100%);
  color: #fff; font: inherit; font-size: 11px; font-weight: 700;
  letter-spacing: .5px;
  box-shadow:
    0 10px 28px -8px rgba(16,60,38,.55),
    0 2px 6px rgba(16,12,45,.18),
    inset 0 1px 0 rgba(255,255,255,.22);
  transition: transform .18s cubic-bezier(.2,.8,.3,1), box-shadow .18s;
}
.tab:hover { transform: translateX(3px) scale(1.03); box-shadow: 0 14px 34px -8px rgba(16,60,38,.6), inset 0 1px 0 rgba(255,255,255,.28); }
.tab:active { transform: translateX(1px) scale(.98); }
.tab-m { display: grid; place-items: center; }
.tab-l { writing-mode: vertical-rl; }
.tab-n {
  min-inline-size: 18px; padding: 1px 5px; border-radius: 999px;
  background: #fff; color: #17603c; font-size: 10.5px; font-weight: 800;
  box-shadow: 0 1px 3px rgba(0,0,0,.2);
}
.wrap.is-open .tab { padding: 10px 9px; }
.wrap.is-open .tab-l { display: none; }

/* ── اللوحة ── */
.card {
  inline-size: 376px; max-block-size: min(76vh, 640px);
  display: flex; flex-direction: column;
  direction: rtl;
  background: #fff; color: #14131f;
  border: 1px solid rgba(20,19,31,.07);
  border-radius: 20px;
  box-shadow:
    0 32px 70px -22px rgba(16,12,45,.45),
    0 8px 20px -8px rgba(16,12,45,.18),
    0 0 0 1px rgba(255,255,255,.6) inset;
  overflow: hidden;
  animation: pop .26s cubic-bezier(.2,.9,.3,1) both;
}
@keyframes pop {
  from { opacity: 0; transform: translateX(-16px) scale(.96); }
}

/* الترويسة: تدرّجٌ أخضر عميقٌ يحمل الهويّة — لا شريطٌ رماديّ */
.hd {
  display: flex; align-items: center; gap: 11px;
  padding: 15px 16px 14px;
  background:
    radial-gradient(120% 140% at 100% 0%, rgba(255,255,255,.20), transparent 60%),
    linear-gradient(150deg, #249b60 0%, #1f7a4d 55%, #17603c 100%);
  color: #fff;
}
.hd-b {
  inline-size: 36px; block-size: 36px; border-radius: 11px; flex: none;
  display: grid; place-items: center; color: #fff;
  background: rgba(255,255,255,.16);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.3);
}
.hd-x { display: flex; flex-direction: column; gap: 3px; flex: 1; min-inline-size: 0; }
.hd-t { font-size: 15px; font-weight: 800; letter-spacing: .2px; }
.hd-s {
  display: flex; align-items: center; gap: 6px;
  font-size: 11.5px; color: rgba(255,255,255,.82);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.dot {
  inline-size: 6px; block-size: 6px; border-radius: 50%; flex: none;
  background: #7ee2a8; box-shadow: 0 0 0 3px rgba(126,226,168,.25);
}
.ghost {
  inline-size: 30px; block-size: 30px; flex: none;
  border: 1px solid rgba(255,255,255,.28); background: rgba(255,255,255,.10);
  color: #fff; border-radius: 9px; cursor: pointer; display: grid; place-items: center;
  transition: background .15s, transform .3s;
}
.ghost:hover { background: rgba(255,255,255,.22); }
.ghost:active { transform: rotate(180deg); }

.bd { padding: 12px; display: flex; flex-direction: column; gap: 9px; overflow-y: auto; flex: 1; }
.bd::-webkit-scrollbar { inline-size: 8px; }
.bd::-webkit-scrollbar-thumb { background: #dcd9e8; border-radius: 99px; border: 2px solid #fff; }

.cap {
  display: flex; align-items: center; gap: 7px;
  font-size: 11.5px; color: #6f6d86; padding-inline: 3px; margin-block-end: 1px;
}
.cap-n {
  display: grid; place-items: center; min-inline-size: 20px; block-size: 20px;
  border-radius: 7px; background: #e7f3ec; color: #17603c;
  font-size: 11.5px; font-weight: 800;
}

.sk {
  block-size: 66px; border-radius: 14px;
  background: linear-gradient(90deg, #f0eef7 25%, #f9f8fc 50%, #f0eef7 75%);
  background-size: 300% 100%; animation: sh 1.25s ease-in-out infinite;
}
@keyframes sh { to { background-position: -300% 0; } }

/* ── صفّ الجدول ── */
.rw {
  display: flex; align-items: flex-start; gap: 11px; inline-size: 100%;
  padding: 12px; border-radius: 14px; cursor: pointer;
  background: #fff; border: 1px solid #eae7f3;
  font: inherit; color: inherit; text-align: start;
  box-shadow: 0 1px 2px rgba(16,12,45,.04);
  transition: border-color .16s, transform .16s, box-shadow .16s, background .16s;
  animation: rise .3s cubic-bezier(.2,.9,.3,1) both;
}
@keyframes rise { from { opacity: 0; transform: translateY(7px); } }
.rw:hover:not(:disabled) {
  border-color: #bfe3cf; transform: translateX(3px);
  box-shadow: 0 8px 20px -8px rgba(16,60,38,.28);
}
.rw:disabled { cursor: default; }
.rw.is-busy { opacity: .75; }
.rw.is-ok {
  border-color: #9fd7b8;
  background: linear-gradient(180deg, #f4fbf7, #eef8f2);
  box-shadow: 0 6px 18px -8px rgba(16,60,38,.3);
}
.rw.is-bad { border-color: #e6b4b0; background: #fdf6f5; }

.rw-i {
  inline-size: 36px; block-size: 36px; align-self: flex-start; border-radius: 11px; flex: none;
  display: grid; place-items: center;
  background: linear-gradient(150deg, #eaf5ef, #dceee5); color: #1f7a4d;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
  transition: background .2s;
}
.rw.is-ok .rw-i { background: linear-gradient(150deg, #1f7a4d, #17603c); color: #fff; }

.rw-x { flex: 1; min-inline-size: 0; display: flex; flex-direction: column; gap: 5px; }
.rw-t {
  font-size: 13px; font-weight: 700; line-height: 1.45; color: #17162a;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
/* أسماء الأعمدة لا عددها: تُعرّف الجدول في لمحة حين تتشابه العناوين */
/* سطرٌ واحدٌ لا يلتفّ: لو التفّت الرقائق لعلا صفٌّ على أخيه وتفاوتت
   الصفوف، وشبكةٌ غير مستويةٍ تبدو عطبًا لا تصميمًا. */
.rw-c { display: flex; flex-wrap: nowrap; gap: 4px; min-inline-size: 0; }
.chip {
  font-style: normal; font-size: 9.5px; font-weight: 600;
  padding: 2px 7px; border-radius: 999px;
  background: #f3f1fa; color: #6a6784;
  min-inline-size: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.chip--n { background: #e7f3ec; color: #2c7a55; flex: none; }
.rw-m {
  flex: none; align-self: center; text-align: center;
  display: flex; flex-direction: column; align-items: center; gap: 1px;
  min-inline-size: 44px; padding-inline: 4px;
  border-inline-start: 1px solid #f0eef7;
}
.rw-num { font-size: 17px; font-weight: 800; color: #1f7a4d; letter-spacing: -.4px; line-height: 1.15; }
.rw-lbl { font-size: 9.5px; color: #9a97ae; font-weight: 600; }
.rw.is-ok .rw-num, .rw.is-ok .rw-lbl { color: #17603c; }
.rw.is-bad .rw-num, .rw.is-bad .rw-lbl { color: #b3261e; }

.rw-g { color: #cfcbdf; flex: none; align-self: center; transition: color .16s, transform .16s; }
.rw-x { padding-block: 1px; }
.rw:hover:not(:disabled) .rw-g { color: #1f7a4d; transform: translateX(-2px); }
.rw.is-ok .rw-g { color: #1f7a4d; }

/* ── الحالات الفارغة ── */
.st { text-align: center; padding: 26px 14px 22px; }
.st-i {
  inline-size: 54px; block-size: 54px; border-radius: 16px; margin: 0 auto 12px;
  display: grid; place-items: center;
  background: linear-gradient(150deg, #f4f2fb, #ece9f7); color: #a29fba;
}
.st-t { font-size: 13.5px; font-weight: 700; margin-block-end: 6px; }
.st-l { font-size: 11.5px; color: #7c7a92; line-height: 1.85; }

/* ── التذييل ── */
.ft {
  display: flex; align-items: center; gap: 8px;
  padding: 11px 14px; border-block-start: 1px solid #f0eef7;
  background: #fbfafd; font-size: 10.5px;
}
.ft-a {
  display: inline-flex; align-items: center; gap: 5px;
  color: #1f7a4d; font-weight: 800; text-decoration: none;
}
.ft-a:hover { text-decoration: underline; }
.ft-v {
  display: inline-flex; align-items: center; gap: 5px;
  color: #9a97ae; margin-inline-start: auto;
}

@media (prefers-color-scheme: dark) {
  .card { background: #1c1b25; color: #eeedf5; border-color: rgba(255,255,255,.07);
          box-shadow: 0 32px 70px -22px rgba(0,0,0,.7), 0 0 0 1px rgba(255,255,255,.05) inset; }
  .bd::-webkit-scrollbar-thumb { background: #3a3850; border-color: #1c1b25; }
  .cap { color: #8b88a6; } .cap-n { background: #16301f; color: #7ee2a8; }
  .sk { background: linear-gradient(90deg, #2a2836 25%, #22212c 50%, #2a2836 75%); background-size: 300% 100%; }
  .rw { background: #232230; border-color: #302e40; box-shadow: none; }
  .rw:hover:not(:disabled) { border-color: #2f6b4c; }
  .rw.is-ok { background: linear-gradient(180deg, #17301f, #142a1b); border-color: #2f6b4c; }
  .rw.is-bad { background: #2a1c1c; border-color: #6b3330; }
  .rw-t { color: #f2f1f8; }
  .rw-i { background: linear-gradient(150deg, #1e3a2a, #17301f); color: #7ee2a8; box-shadow: none; }
  .rw.is-ok .rw-i { background: linear-gradient(150deg, #1f7a4d, #17603c); color: #fff; }
  .chip { background: #2c2a3a; color: #a5a2bb; } .chip--n { background: #16301f; color: #7ee2a8; }
  .rw-m { border-inline-start-color: #302e40; } .rw-lbl { color: #6f6d86; } .rw-num { color: #7ee2a8; }
  .rw.is-ok .rw-num, .rw.is-ok .rw-lbl { color: #7ee2a8; }
  .rw-g { color: #4a4860; }
  .st-i { background: linear-gradient(150deg, #2a2836, #232230); color: #6a6784; }
  .st-l { color: #8b88a6; }
  .ft { background: #191822; border-block-start-color: #2c2a3a; }
  .ft-a { color: #7ee2a8; } .ft-v { color: #6f6d86; }
}
`

/* النداء في آخر الملفّ لا أوّله. و`boot` دالّةٌ مرفوعة فتُرى من الأعلى،
   لكنّ `PANEL_CSS` ثابتٌ لا يُرفع — فندعوها من الأعلى يقع في منطقته
   الميّتة: «Cannot access before initialization»، فلا تُركَّب اللوحة
   ولا يبين سبب. */
if (!document.getElementById(HOST_ID)) boot()
