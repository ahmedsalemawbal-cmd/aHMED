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
const { getKey, sendTable, readTables, panelInfo } = self.Midad

const SITE = 'https://ahmedawbal.com'
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
    /* ما يردّه الخادم: الاسمُ والمدرسةُ والمتبقّي والأدوات. و`null`
       يعني «لم يُسأل بعد»، و`false` يعني «سُئل ولم يُربط». */
    info: null,
    tables: null,
    busy: false,
    err: null,
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

/**
 * نداءٌ واحدٌ عند الفتح — لا ثلاثة.
 *
 * كان التحقّقُ من المفتاح نداءً، وقراءةُ الجدول نداءً. والمعلّم يفتح
 * اللوحةَ فينتظر رحلتين. فصار `panel` يردّ الاسمَ والمدرسةَ والمتبقّي
 * والأدواتِ في ردٍّ واحد، وقراءةُ الجدول محلّيّةٌ لا تحتاج شبكة.
 *
 *     الرحلةُ التي تُدمج لا تُنتظر.
 */
async function load(wrap, state) {
  state.busy = true
  state.err = null
  render(wrap, state, handlers(wrap, state))
  try {
    const key = await getKey()
    if (!key) {
      state.info = false
    } else {
      state.info = await panelInfo()
      /* والجدولُ يُقرأ بعد التحقّق: قراءةُ صفحةٍ لمن لا حساب له عبثٌ
         يُبطئ الفتح ولا يُستعمل. */
      state.tables = readTables().tables || []
    }
  } catch (e) {
    /* والسببُ يُقال: «انتهت صلاحية المفتاح» أفضلُ من لوحةٍ فارغة.
       ومنافسانا يُخفيان كلَّ شيءٍ حين يُغلق الخادمُ الباب، فيظنّ
       المشترك أنّ الإضافة تعطّلت لا أنّ اشتراكه انتهى. */
    state.info = false
    state.err = e?.message || 'تعذّر الاتّصال بمِداد'
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
  files: ['M4 3.6h7l4 4v9H4z', 'M11 3.6v4h4'],
  doc: ['M4 3.6h12v12.8H4z', 'M7 7.4h6M7 10.4h6M7 13.4h3.5'],
  down: ['M10 3.4v9.4', 'M6.4 9.4 10 13l3.6-3.6', 'M4 16.4h12'],
  chat: ['M10 3.4a6.6 6.6 0 0 0-5.6 10.1L3.4 16.6l3.2-1a6.6 6.6 0 1 0 3.4-12.2z'],
  chart: ['M4 16.4V9.6M8.6 16.4V4.6M13.2 16.4v-8M17 16.4h-14'],
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

  const info = state.info || null
  const card = el('div', 'card')

  /* ═════ الشريط: الاسمُ والمتبقّي ═════
     المتبقّي أمام عينه دائمًا — تذكيرٌ بالتجديد في الموضع الذي يعمل فيه.
     وهو رقمٌ من الخادم لا سمةٌ في صورة كما عند غيرنا. */
  const hd = el('div', 'hd')
  hd.appendChild(el('span', 'hd-mk', 'مِ'))
  const hdTx = el('div', 'hd-tx')
  hdTx.appendChild(el('strong', null, 'مِداد'))
  hdTx.appendChild(el('span', null, info ? (info.school || '') : 'غير مربوط'))
  hd.appendChild(hdTx)

  if (info && info.days_left != null) {
    /* وثلاثةُ أيّامٍ أو أقلّ تُلوَّن: الرقمُ وحده لا يُنبّه. */
    const soon = info.days_left <= 7
    hd.appendChild(el('span', 'days' + (soon ? ' is-soon' : ''),
      info.days_left === 0 ? 'انتهى' : `متبقٍّ ${ar(info.days_left)} يومًا`))
  }

  const rf = el('button', 'ghost')
  rf.title = 'أعِد القراءة'
  rf.appendChild(svg(I.refresh, 14))
  rf.addEventListener('click', h.refresh)
  hd.appendChild(rf)
  card.appendChild(hd)

  /* ═════ ما في الصفحة الآن ═════
     وهو ما لا يفعله منافسانا: نافذتُهما تُفتح فتعرض شاشةً عامّةً لا
     تعرف أين أنت. */
  if (state.busy) {
    const b = el('div', 'bd'); b.appendChild(el('div', 'sk')); b.appendChild(el('div', 'sk'))
    card.appendChild(b)
  } else if (!info) {
    const b = el('div', 'bd')
    b.appendChild(emptyState({
      ic: I.link,
      tt: state.err || 'اربط حسابك أوّلًا',
      ln: state.err
        ? 'افتح مِداد ← جداول نور، وأنشئ مفتاحًا جديدًا ثمّ الصقه في أيقونة الإضافة.'
        : 'افتح مِداد ← جداول نور ← انسخ مفتاح الربط، ثمّ الصقه في أيقونة الإضافة بالأعلى.',
    }))
    card.appendChild(b)
  } else {
    const first = state.tables && state.tables[0]
    const hero = el('div', 'hero' + (first ? '' : ' is-empty'))
    const hl = el('div', 'hero-l')
    if (first) {
      const tag = el('div', 'hero-tag')
      tag.appendChild(el('span', 'pulse'))
      tag.appendChild(el('span', null, 'في هذه الصفحة'))
      hl.appendChild(tag)
      hl.appendChild(el('div', 'hero-t', first.title))
      hl.appendChild(el('div', 'hero-m',
        `${ar(first.rowCount)} صفًّا · ${ar(first.colCount)} أعمدة`))
    } else {
      hl.appendChild(el('div', 'hero-t', 'لا جدولَ في هذه الصفحة'))
      hl.appendChild(el('div', 'hero-m', 'افتح كشفًا أو تقريرًا ثمّ أعِد القراءة.'))
    }
    hero.appendChild(hl)

    if (first) {
      const go = el('button', 'go', 'اسحبه')
      go.addEventListener('click', () => {
        if (go.disabled) return
        go.disabled = true
        go.textContent = 'جارٍ الإرسال…'
        h.send(first, (err) => {
          go.textContent = err ? 'تعذّر' : 'تمّ ✓'
          go.className = 'go' + (err ? ' is-bad' : ' is-ok')
          if (err) {
            hero.appendChild(el('div', 'hero-err', err.message || 'تعذّر الإرسال'))
            setTimeout(() => { go.disabled = false; go.textContent = 'أعِد' ; go.className = 'go' }, 2400)
          }
        })
      })
      hero.appendChild(go)
    }
    card.appendChild(hero)

    /* وجداولُ الصفحة الأخرى — إن كان فيها أكثرُ من واحد. */
    if (state.tables && state.tables.length > 1) {
      const more = el('div', 'more')
      more.appendChild(el('div', 'more-h', `وفي الصفحة ${ar(state.tables.length - 1)} جدولًا آخر`))
      for (const t of state.tables.slice(1)) more.appendChild(row(t, h))
      card.appendChild(more)
    }

    /* ═════ الأدوات — من الخادم، لا من هنا ═════ */
    const tools = Array.isArray(info.tools) ? info.tools.filter(fits) : []
    if (tools.length) {
      const g = el('div', 'tools')
      for (const t of tools) g.appendChild(tile(t))
      card.appendChild(g)
    }
  }

  /* ═════ التذييل ═════ */
  const ft = el('div', 'ft')
  if (info && info.whats_new) {
    ft.appendChild(el('span', 'new', `الجديد · ${info.whats_new}`))
  }
  const a = el('a', 'ft-a', 'افتح مِداد')
  a.href = SITE + '/#/app/noor'
  a.target = '_blank'; a.rel = 'noopener'
  ft.appendChild(a)
  card.appendChild(ft)

  wrap.appendChild(card)
}

/** الأرقامُ عربيّةٌ شرقيّة — الصفحةُ عربيّة، والرقمُ اللاتينيّ يقطعها. */
function ar(n) {
  return String(n ?? '').replace(/\d/g, (d) => '٠١٢٣٤٥٦٧٨٩'[d])
}

/**
 * أتعمل الأداةُ في هذه الصفحة؟
 *
 * وزرٌّ ظاهرٌ لا يعمل أسوأ من زرٍّ غائب: يضغطه المعلّم فلا يحدث شيء،
 * فيظنّ الإضافةَ معطّلة. فما لم تُذكر صفحتُه لا يظهر.
 */
function fits(t) {
  if (!t || !t.name) return false
  const on = Array.isArray(t.on) ? t.on : null
  if (!on || !on.length) return true
  const host = location.hostname
  return on.some((w) => (w === 'noor' && /moe\.gov\.sa$/i.test(host))
    || (w === 'madrasati' && /madrasati\.sa$/i.test(host)))
}

function tile(t) {
  const isOpen = t.kind === 'open' && t.open
  const b = el(isOpen ? 'a' : 'button', 'tl')
  if (isOpen) {
    b.href = /^https?:/i.test(t.open) ? t.open : SITE + t.open
    b.target = '_blank'; b.rel = 'noopener'
  }
  const ic = el('span', 'tl-i')
  ic.appendChild(svg(I[t.icon] || I.table, 14))
  b.appendChild(ic)
  b.appendChild(el('span', 'tl-n', t.name))
  b.appendChild(el('span', 'tl-c', t.count != null ? ar(t.count) : (t.hint || '')))
  if (t.badge) b.appendChild(el('span', 'tl-b', t.badge))
  return b
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

const PANEL_CSS = `
:host, * { box-sizing: border-box; }

/* ═══════════ نظامُ التصميم ═══════════
 *
 * لوحاتُ منافسينا فيها خمسةُ ألوانٍ لا نظامَ بينها، وأزرارٌ بأنصاف
 * أقطارٍ مختلفة. وهذا سببُ ما يُقرأ «عشوائيّة»: لا يُرى الفرقُ بين ٩
 * و١٠ في موضعٍ واحد، ويُرى في اللوحة كلِّها — حافّةٌ لا تحاذي حافّة.
 *
 *     ما لا يصطفّ يُقرأ عشوائيًّا، ولو كان كلُّ جزءٍ منه صحيحًا.
 *
 * فأربعُ قواعد: لونٌ واحدٌ للهويّة، وثلاثةٌ للحالة لا تُستعمل للتزيين،
 * وسلّمُ فراغٍ من ستّة لا رقمَ بينها، وثلاثةُ أنصاف أقطار.
 */
:host {
  --brand: #1B7A4F;
  --brand-hi: #15633F;
  --brand-soft: #E0F0E6;
  --brand-fg: #0D5334;

  --ok: #1B7A4F;
  --warn: #96660F;  --warn-soft: #F8EFD6;
  --bad: #AE3327;   --bad-soft: #F8E6E3;

  --card: #FFFFFF;
  --sunk: #F1F5F2;
  --deep: #0C2419;
  --ink: #12211A;
  --ink2: #46574D;
  --ink3: #76867C;
  --line: #DEE7E1;

  --s1: 4px; --s2: 8px; --s3: 12px; --s4: 16px; --s5: 20px; --s6: 24px;
  --r1: 8px; --r2: 10px; --r3: 14px;
}

/* الحافّة اليسرى، وبخصائص ماديّة لا منطقيّة.
   وسببان: أنّ نور يضع قائمته الرأسيّة على اليمين — فلوحةٌ هناك تحجب
   ملاحته؛ وأنّ خلط المنطقيّ بالماديّ في سياقٍ عربيّ فخٌّ وقعتُ فيه من
   قبل: inset-inline-end يعني اليسار، وtransform-origin: right يعني
   اليمين، فلا يتّفقان.
   والحاوية ltr كي يقع المقبض يسارًا والبطاقة يمينه؛ والعربيّة تعود
   داخل البطاقة نفسها. */
.wrap {
  position: fixed; left: 0; bottom: var(--s5);
  display: flex; align-items: flex-end; gap: 0;
  direction: ltr;
  font-family: "Segoe UI", "Noto Naskh Arabic", Tahoma, system-ui, sans-serif;
  font-variant-numeric: tabular-nums;
}

/* ── المقبض: كلّ ما يراه المعلّم حتّى يطلب أكثر ── */
.tab {
  align-self: flex-end;
  display: flex; align-items: center; gap: var(--s2);
  padding: var(--s2) var(--s3) var(--s2) var(--s2);
  border: 0; cursor: pointer;
  background: var(--deep); color: #E8F4EC;
  border-radius: 0 999px 999px 0;
  font: 600 12.5px/1 inherit;
  box-shadow: 0 8px 22px -8px rgba(12,36,25,.5);
}
.tab:hover { background: #123326; }
.tab:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.tab-mark {
  width: 22px; height: 22px; border-radius: var(--r1); flex: none;
  background: var(--brand); color: #fff;
  display: grid; place-items: center; font: 900 12px/1 inherit;
}
.tab-lbl { white-space: nowrap; }

/* ── البطاقة ── */
.card {
  width: 420px; max-width: calc(100vw - 60px);
  margin-left: var(--s2);
  background: var(--card); color: var(--ink);
  border: 1px solid var(--line); border-radius: var(--r3);
  box-shadow: 0 22px 48px -14px rgba(12,36,25,.35);
  overflow: hidden; direction: rtl; text-align: right;
}

/* الشريط */
.hd {
  display: flex; align-items: center; gap: var(--s2);
  padding: var(--s2) var(--s3);
  background: var(--deep); color: #E8F4EC;
}
.hd-mk {
  width: 24px; height: 24px; border-radius: var(--r1); flex: none;
  background: var(--brand); color: #fff;
  display: grid; place-items: center; font: 900 12px/1 inherit;
}
.hd-tx { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
.hd-tx strong { font: 700 13px/1.3 inherit; }
.hd-tx span {
  font: 400 10.5px/1.3 inherit; opacity: .72;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.days {
  margin-right: auto; flex: none;
  font: 600 10px/1 inherit; padding: var(--s1) var(--s2);
  border-radius: 999px; background: rgba(255,255,255,.14); white-space: nowrap;
}
/* والحالةُ لونٌ دالٌّ لا زينة: تظهر حين يقترب الانتهاء وحده. */
.days.is-soon { background: var(--warn); color: #fff; }
.ghost {
  flex: none; width: 24px; height: 24px; border: 0; cursor: pointer;
  border-radius: var(--r1); background: rgba(255,255,255,.1); color: #E8F4EC;
  display: grid; place-items: center;
}
.ghost:hover { background: rgba(255,255,255,.2); }
.ghost:focus-visible { outline: 2px solid var(--brand); outline-offset: 1px; }

/* ما في الصفحة الآن */
.hero {
  display: flex; align-items: center; gap: var(--s3);
  padding: var(--s3); background: var(--brand-soft);
  border-bottom: 1px solid var(--line); flex-wrap: wrap;
}
.hero.is-empty { background: var(--sunk); }
.hero-l { flex: 1; min-width: 150px; }
.hero-tag {
  display: flex; align-items: center; gap: var(--s1) var(--s2);
  font: 700 9.5px/1 inherit; color: var(--brand-fg); letter-spacing: .06em;
}
.pulse {
  width: 6px; height: 6px; border-radius: 50%; background: var(--brand);
  flex: none; display: block;
}
.hero-t { font: 700 13.5px/1.4 inherit; margin-top: 3px; }
.hero-m { font: 400 11px/1.5 inherit; color: var(--ink2); }
.hero-err { flex-basis: 100%; font: 400 11px/1.5 inherit; color: var(--bad); }
.go {
  flex: none; border: 0; cursor: pointer;
  padding: var(--s2) var(--s5); border-radius: var(--r2);
  background: var(--brand); color: #fff; font: 700 13px/1 inherit;
}
.go:hover { background: var(--brand-hi); }
.go:disabled { opacity: .7; cursor: default; }
.go:focus-visible { outline: 2px solid var(--deep); outline-offset: 2px; }
.go.is-ok { background: var(--ok); }
.go.is-bad { background: var(--bad); }

/* جداولُ أخرى في الصفحة */
.more { padding: var(--s2) var(--s3); border-bottom: 1px solid var(--line); }
.more-h { font: 600 10px/1.6 inherit; color: var(--ink3); margin-bottom: var(--s1); }

/* الأدوات — من الخادم */
.tools {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: var(--s2); padding: var(--s3);
}
.tl {
  position: relative; border: 1px solid var(--line); border-radius: var(--r2);
  padding: var(--s2) var(--s1); background: var(--card); cursor: pointer;
  display: flex; flex-direction: column; align-items: center; gap: var(--s1);
  text-align: center; text-decoration: none; color: var(--ink);
  font-family: inherit;
}
.tl:hover { border-color: var(--brand); background: var(--brand-soft); }
.tl:focus-visible { outline: 2px solid var(--brand); outline-offset: 1px; }
.tl-i {
  width: 26px; height: 26px; border-radius: var(--r1); flex: none;
  background: var(--sunk); color: var(--ink2);
  display: grid; place-items: center;
}
.tl:hover .tl-i { background: #fff; color: var(--brand); }
.tl-n { font: 600 10.5px/1.3 inherit; }
.tl-c { font: 400 9px/1.3 inherit; color: var(--ink3); }
.tl-b {
  position: absolute; top: -6px; inset-inline-start: -4px;
  font: 700 8px/1 inherit; background: var(--bad); color: #fff;
  padding: 2px var(--s1); border-radius: 999px;
}

/* الحالات الفارغة */
.bd { padding: var(--s3); }
.st { text-align: center; padding: var(--s5) var(--s3); }
.st-i {
  width: 48px; height: 48px; margin: 0 auto var(--s3);
  border-radius: var(--r3); background: var(--sunk); color: var(--ink3);
  display: grid; place-items: center;
}
.st-t { font: 700 13px/1.5 inherit; }
.st-l { font: 400 11.5px/1.7 inherit; color: var(--ink2); margin-top: var(--s1); }
.sk {
  height: 44px; border-radius: var(--r2); background: var(--sunk);
  margin-bottom: var(--s2);
}

/* صفُّ جدول */
.rw {
  width: 100%; display: flex; align-items: center; gap: var(--s2);
  padding: var(--s2); border: 1px solid var(--line); border-radius: var(--r2);
  background: var(--card); cursor: pointer; text-align: right;
  font-family: inherit; margin-bottom: var(--s1);
}
.rw:hover { border-color: var(--brand); background: var(--brand-soft); }
.rw:focus-visible { outline: 2px solid var(--brand); outline-offset: 1px; }
.rw:disabled { opacity: .6; cursor: default; }
.rw-i {
  width: 26px; height: 26px; border-radius: var(--r1); flex: none;
  background: var(--sunk); color: var(--ink2); display: grid; place-items: center;
}
.rw-x { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.rw-t {
  font: 600 11.5px/1.4 inherit;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rw-m { font: 400 10px/1.4 inherit; color: var(--ink3); }
.rw-g { flex: none; color: var(--brand); }

/* التذييل */
.ft {
  display: flex; align-items: center; gap: var(--s2);
  padding: var(--s2) var(--s3);
  background: var(--sunk); border-top: 1px solid var(--line);
  font: 400 10px/1.4 inherit; color: var(--ink3);
}
.new {
  background: var(--brand-soft); color: var(--brand-fg);
  padding: 2px var(--s2); border-radius: 999px; font-weight: 600;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ft-a {
  margin-right: auto; color: var(--brand); text-decoration: none;
  font-weight: 600; white-space: nowrap;
}
.ft-a:hover { text-decoration: underline; }

@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
`

/* النداء في آخر الملفّ لا أوّله. و`boot` دالّةٌ مرفوعة فتُرى من الأعلى،
   لكنّ `PANEL_CSS` ثابتٌ لا يُرفع — فندعوها من الأعلى يقع في منطقته
   الميّتة: «Cannot access before initialization»، فلا تُركَّب اللوحة
   ولا يبين سبب. */
if (!document.getElementById(HOST_ID)) boot()
