/**
 * استيراد ملفّ HTML من أداة تصميمٍ خارجيّة، وتحويله متنًا لقالب مِداد.
 *
 * الملفّ الذي تُصدره أداة التصميم **ليس صفحةً ثابتة**: هو حزمةٌ تفكّ نفسها
 * عند الفتح — مواردُ مضغوطةٌ بـgzip ومُرمَّزةٌ base64 داخل كتلة `manifest`،
 * وقالبُ HTML في كتلة `template`، ومكتباتٌ تُجلَب من الإنترنت. فلا يكفي أن
 * نقرأ الملفّ؛ نفكّه أوّلًا.
 *
 * وبعد الفكّ نجد وسومًا باصطلاح الأداة (`sc-raw-table` بدل `table`)،
 * وصفحاتٍ في `section.page`. فنُعيدها إلى وسومٍ قياسيّة يفهمها المحرّر.
 *
 * والتنقية شرطٌ لا تحسين: القالب يفتحه كلّ المعلّمين، فأيّ `<script>` فيه
 * يعمل في متصفّحاتهم. نُسقطها كلّها.
 */
import { sanitizeStyle } from './styleSafe'

export interface DesignImport {
  title: string
  html: string
  pages: number
  tables: number
  cells: number
  landscape: boolean
  /** هوامش الصفحة بالمليمتر — مقروءةٌ من حشو أقسام التصميم */
  margins: { top: number; right: number; bottom: number; left: number }
  warnings: string[]
  /** ما أُسقط، كي نُصارح به لا نُخفيه */
  dropped: string[]
}

/* ═══════════════ فكّ الحزمة ═══════════════ */

function scriptBlock(doc: Document, type: string): string | null {
  const el = doc.querySelector(`script[type="__bundler/${type}"]`)
  return el ? (el.textContent || '').trim() : null
}

/** يُخرج قالب HTML الحقيقيّ من الحزمة، أو يعيد الملفّ كما هو إن لم يكن محزومًا */
function unwrap(raw: string): { html: string; bundled: boolean } {
  const doc = new DOMParser().parseFromString(raw, 'text/html')
  const tpl = scriptBlock(doc, 'template')
  if (tpl) {
    try {
      const inner = JSON.parse(tpl)
      if (typeof inner === 'string' && inner.length > 40) return { html: inner, bundled: true }
    } catch { /* ليست حزمةً بهذا الشكل */ }
  }
  return { html: raw, bundled: false }
}

/* ═══════════════ إعادة الوسوم إلى القياس ═══════════════ */

const TAG_MAP: Record<string, string> = {
  'sc-raw-table': 'table', 'sc-raw-thead': 'thead', 'sc-raw-tbody': 'tbody',
  'sc-raw-tfoot': 'tbody', 'sc-raw-tr': 'tr', 'sc-raw-td': 'td', 'sc-raw-th': 'th',
  'sc-raw-caption': 'caption', 'sc-raw-col': 'col', 'sc-raw-colgroup': 'colgroup',
}

/** يبدّل اسم عنصرٍ مع الحفاظ على سماته وأبنائه */
function rename(el: Element, tag: string): Element {
  const n = el.ownerDocument.createElement(tag)
  for (const a of Array.from(el.attributes)) n.setAttribute(a.name, a.value)
  while (el.firstChild) n.appendChild(el.firstChild)
  el.replaceWith(n)
  return n
}

/* ═══════════════ التنقية ═══════════════ */

/** ما يُحذف كاملًا بمحتواه — لا يُفكّ ولا يُبقى منه شيء */
const KILL = 'script,style,link,meta,noscript,iframe,object,embed,form,input,button,select,textarea,helmet,title,base'

/**
 * السمات المسموحة على أيّ عنصر.
 *
 * `data-page-break` من علامتنا نحن لا من الملفّ — وقد كان محذوفًا هنا
 * فتضيع فواصل الصفحات بين التنقية والتحويل. أثرٌ صامت: مستندٌ من ستّ
 * صفحاتٍ يخرج صفحةً واحدة.
 */
const KEEP_ATTRS = new Set([
  'style', 'colspan', 'rowspan', 'dir', 'align', 'width', 'height',
  'data-page-break', 'data-page',
  /* `src` و`alt` للصور. وبدونهما تبقى الصورة عنصرًا فارغًا بلا مصدر —
     يُستورد التصميم فيخرج شعار المدرسة مربّعًا أبيض، ولا شيء يُنبّه.
     والمصادر الخطرة تُصفّى في safeSrc أدناه لا هنا. */
  'src', 'alt',
])

/**
 * مصدر صورةٍ مأمون: صورةٌ مضمَّنة، أو رابطٌ مشفَّر، أو نسبيّ.
 *
 * والممنوع صريحٌ لا مُستنتَج: `javascript:` ينفّذ، و`data:` من غير
 * الصور قد يحمل SVG فيه شيفرة. فنسمح بما نعرف ونردّ ما سواه.
 */
function safeSrc(v: string | null): string | null {
  const u = (v || '').trim()
  if (!u) return null
  if (/^data:image\/(png|jpeg|jpg|gif|webp);base64,/i.test(u)) return u
  if (/^https:\/\//i.test(u)) return u
  if (/^\/(?!\/)/.test(u)) return u
  return null
}

function clean(root: Element, dropped: Set<string>): void {
  root.querySelectorAll(KILL).forEach((el) => {
    dropped.add(el.tagName.toLowerCase())
    el.remove()
  })

  // إعادة تسمية وسوم الأداة
  Object.keys(TAG_MAP).forEach((from) => {
    root.querySelectorAll(from).forEach((el) => rename(el, TAG_MAP[from]))
  })

  const walk = (el: Element) => {
    for (const child of Array.from(el.children)) walk(child)

    for (const a of Array.from(el.attributes)) {
      const name = a.name.toLowerCase()
      if (name.startsWith('on')) { el.removeAttribute(a.name); continue }
      if (name === 'style') {
        const s = sanitizeStyle(a.value)
        if (s) el.setAttribute('style', s); else el.removeAttribute('style')
        continue
      }
      if (name === 'href') {
        // رابطٌ لا يبدأ ببروتوكولٍ معروف يُحذف — لا javascript: ولا data:
        if (!/^(https?:|mailto:|tel:|#)/i.test(a.value)) el.removeAttribute('href')
        continue
      }
      if (!KEEP_ATTRS.has(name)) { el.removeAttribute(a.name); continue }
      if (name === 'src') {
        const ok = safeSrc(a.value)
        if (ok) el.setAttribute('src', ok)
        else { el.removeAttribute('src'); dropped.add('src غير مأمون') }
      }
    }
  }
  walk(root)
}

/* ═══════════════ هوامش الصفحة ═══════════════ */

const PX_MM = 25.4 / 96

/** يفكّ اختصار padding إلى أربع قيمٍ بالبكسل */
function padSides(v: string): [number, number, number, number] | null {
  const nums = v.trim().split(/\s+/).map((x) => {
    const m = /^([\d.]+)(px|pt|mm|in)?$/.exec(x)
    if (!m) return null
    const n = Number(m[1])
    const u = m[2] || 'px'
    return u === 'px' ? n : u === 'pt' ? n * 96 / 72 : u === 'mm' ? n / PX_MM : n * 96
  })
  if (nums.some((n) => n === null)) return null
  const [a, b, c, d] = nums as number[]
  if (nums.length === 1) return [a, a, a, a]
  if (nums.length === 2) return [a, b, a, b]
  if (nums.length === 3) return [a, b, c, b]
  if (nums.length === 4) return [a, b, c, d]
  return null
}

/**
 * هوامش الصفحة من حشو الأقسام.
 *
 * التصميم يضع هوامشه في `padding` القسم، ونحن نفكّ الأقسام لنصل إلى
 * محتواها — فيضيع الحشو ويلتصق النصّ بحافّة الورقة. فنقرؤه قبل الفكّ
 * ونحوّله إلى هوامش الصفحة. ونأخذ **أكثر القيم تكرارًا** لا أوّلها:
 * الغلاف غالبًا بلا حشو، وصفحات المحتوى هي القاعدة.
 */
function marginsFrom(sections: Element[]): { top: number; right: number; bottom: number; left: number } {
  const seen = new Map<string, { n: number; v: [number, number, number, number] }>()
  for (const sec of sections) {
    const st = sec.getAttribute('style') || ''
    const m = /(?:^|;)\s*padding\s*:\s*([^;]+)/.exec(st)
    if (!m) continue
    const sides = padSides(m[1])
    if (!sides || sides.every((x) => x === 0)) continue
    const key = sides.join(',')
    const e = seen.get(key)
    if (e) e.n++
    else seen.set(key, { n: 1, v: sides })
  }
  let best: [number, number, number, number] | undefined
  let bn = 0
  seen.forEach((e) => { if (e.n > bn) { bn = e.n; best = e.v } })
  if (!best) return { top: 14, right: 14, bottom: 14, left: 14 }
  const [t, r, b, l] = best as [number, number, number, number]
  const mm = (px: number) => Math.round(px * PX_MM * 10) / 10
  return { top: mm(t), right: mm(r), bottom: mm(b), left: mm(l) }
}

/**
 * خطّ التصميم — يُقرأ من `<style>` قبل أن تحذفه التنقية.
 *
 * كلود ديزاين يُضمّن الخطّ في الملفّ بـ`@font-face` ويُعلنه في قاعدةٍ
 * عامّة، لا في سمة `style` على كلّ عنصر. والتنقية تحذف `<style>` كلّه —
 * وهو صواب، فقد يحمل ما لا نريد. لكنّها كانت تحذف معه هويّة الخطّ، فيقع
 * المستند على خطّ مِداد الافتراضيّ (نسخيّ) بينما صُمّم على خطٍّ هندسيّ.
 * فيبدو المستند «مختلطًا» وإن كان كلّ حرفٍ في موضعه.
 *
 * ولا نُضمّن الخطّ نفسه: وجوهه الخمسة نحو ١٧٥ كيلوبايت مُرمَّزة، وحفظها
 * في متن كلّ قالبٍ يُثقل قاعدة البيانات. بل نأخذ اسمه، فإن كان ممّا
 * تُحمّله المنصّة أصلًا (القاهرة) عُرض كما صُمّم.
 */
const FONTS_WE_HAVE = ['cairo', 'ibm plex mono']

function designFont(doc: Document): string | null {
  const decl: string[] = []
  doc.querySelectorAll('style').forEach((st) => {
    const t = st.textContent || ''
    // ما يُعلَن على body أو * أو حاوية الصفحة
    for (const m of t.matchAll(/font-family\s*:\s*([^;}]+)/gi)) decl.push(m[1])
  })
  for (const d of decl) {
    const first = d.split(',')[0].trim().replace(/^["']|["']$/g, '')
    if (FONTS_WE_HAVE.includes(first.toLowerCase())) return first
  }
  return null
}

/* ═══════════════ الصفحات ═══════════════ */



/* ═══════════════ ما لا يفهمه المحرّر ═══════════════ */

/**
 * `div` ليست في مخطَّط المحرّر. لكنّ أدوات التصميم تكتب بها عناوين الأقسام
 * والفقرات — فحذفها يُفقد النصّ. نُحوّلها إلى `p` بنمطها، فيبقى النصّ
 * ويبقى تنسيقه. والفقرة في مخطَّطنا تحفظ `style` (انظر editorStyled).
 *
 * ونستثني `div` التي تحوي كتلًا أخرى: تلك حاويةٌ لا فقرة، فنفكّها.
 */
function divsToParagraphs(root: Element): void {
  const BLOCK = 'div,table,ul,ol,section,h1,h2,h3,h4,h5,h6,blockquote,hr,p'
  let guard = 0
  while (guard++ < 60) {
    /* الصندوق يُستثنى كالفاصل: كلاهما عقدةٌ في مخطّط المحرّر لا حاويةُ
       تخطيطٍ عابرة، وتحويلُه فقرةً يُسقط حشوَ صفحته. */
    /* ما يحمل نمطًا يُترك: هو غلافُ تخطيطٍ لا فقرةٌ متنكّرة. وسحقه يُضيّع
       حشوَه وهوامشه — وهي كلّ ما يبني به التصميم مساحاته.
       ولا يُسحق إلّا العاري: `<div>` بلا نمطٍ ليس إلّا فقرةً بلا اسم. */
    const divs = Array.from(root.querySelectorAll(
      'div:not([data-page-break]):not([data-page]):not([style])'))
    if (!divs.length) break
    let changed = false
    for (const d of divs) {
      /* لا نستعمل isConnected: الشجرة هنا غير مُلحَقة بالمستند، فتكون
         false دائمًا فتُتخطّى كلّ العناصر وتخرج الدالّة بلا أثر. وقع هذا
         فعلًا، وكان يمرّ صامتًا: المستند يخرج بـdivs لا يفهمها المحرّر
         فيُسقطها كلّها بنصّها. المعيار الصحيح: هل ما زال له أب؟ */
      if (!d.parentNode) continue
      if (d.querySelector(BLOCK)) {
        // حاوية: نرفع أبناءها مكانها
        d.replaceWith(...Array.from(d.childNodes))
        changed = true
      } else {
        rename(d, 'p')
        changed = true
      }
    }
    if (!changed) break
  }
}

/** يُسقط الجداول الفارغة تمامًا — بقايا تخطيطٍ لا محتوى */
function dropEmptyTables(root: Element): number {
  let n = 0
  root.querySelectorAll('table').forEach((t) => {
    const cells = t.querySelectorAll('td,th')
    if (!cells.length) { t.remove(); n++; return }
    const hasText = Array.from(cells).some((c) => (c.textContent || '').trim())
    const hasColor = Array.from(cells).some((c) =>
      /background|border|height/.test(c.getAttribute('style') || ''))
    // جدولٌ بلا نصٍّ ولا لون: لا يرى المستخدم منه شيئًا
    if (!hasText && !hasColor) { t.remove(); n++ }
  })
  return n
}

/* ═══════════════ الواجهة ═══════════════ */

export function importDesignHtml(raw: string, fileName = ''): DesignImport {
  const warnings: string[] = []
  const droppedSet = new Set<string>()

  const { html: inner, bundled } = unwrap(raw)
  if (bundled) warnings.push('الملفّ كان محزومًا — فُكَّ واستُخرج منه المتن.')

  const doc = new DOMParser().parseFromString(inner, 'text/html')
  const body = doc.body

  // العنوان قبل التنقية — قد يكون في <title>
  const docTitle = (doc.querySelector('title')?.textContent || '').trim()

  /* الصفحات تُقرأ **قبل** التنقية: التنقية تحذف class وdata-*، فلو نقّينا
     أوّلًا لما بقي ما يدلّ على حدود الصفحات وصار المستند صفحةً واحدة.
     وقع هذا فعلًا في أوّل تشغيل. */
  const sections = Array.from(body.querySelectorAll('section.page, section[data-screen-label]'))
  const pages = sections.length || 1
  /* الحشو صار في صندوق كلّ صفحة، فلا يُرفع إلى الورقة مرّةً ثانية —
     وإلّا حُسب مرّتين. والورقة بلا هامشٍ من عندنا. */
  const margins = sections.length
    ? { top: 0, right: 0, bottom: 0, left: 0 }
    : marginsFrom(sections)

  const holder = doc.createElement('div')
  if (sections.length) {
    sections.forEach((sec, i) => {
      if (i) {
        const br = doc.createElement('div')
        br.setAttribute('data-page-break', 'true')
        holder.appendChild(br)
      }
      /* كلّ صفحةٍ في صندوقها بحشوها هي.
         ولمَ لا نرفع حشوًا واحدًا إلى مستوى الورقة كما كنّا؟ لأنّ الصفحات
         تختلف: الغلاف بلا حشوٍ إطلاقًا، وصفحات المتن 34px 44px 30px. فرفعُ
         الأكثر تكرارًا يُلبس الغلافَ حشوًا لم يُصمَّم به. والصندوق يحمل مع
         الحشو خطَّ الصفحة ولونها واتّجاهها — فينجو التصميم كما وُضع. */
      const box = doc.createElement('div')
      box.setAttribute('data-page', 'true')
      const st = sanitizeStyle(sec.getAttribute('style'))
      if (st) box.setAttribute('style', st)
      while (sec.firstChild) box.appendChild(sec.firstChild)
      holder.appendChild(box)
    })
  } else {
    while (body.firstChild) holder.appendChild(body.firstChild)
  }

  /* الخطّ يُقرأ قبل التنقية — فهي تحذف `<style>` الذي يحمله */
  const font = designFont(doc)
  clean(holder, droppedSet)
  if (font) {
    /* على كلّ فقرةٍ وخليّةٍ لا على الجذر وحده: المحرّر يُعيد بناء المستند
       من مخطّطه، والجذر ليس عقدةً فيه — فنمطُه يضيع عند أوّل تحرير. */
    holder.querySelectorAll('p, td, th, h1, h2, h3, div, span, li').forEach((el) => {
      const st = el.getAttribute('style') || ''
      if (!/font-family/i.test(st)) {
        el.setAttribute('style', `${st}${st && !st.trim().endsWith(';') ? ';' : ''}font-family:'${font}',sans-serif`)
      }
    })
  }
  divsToParagraphs(holder)
  const emptied = dropEmptyTables(holder)

  const tables = holder.querySelectorAll('table').length
  const cells = holder.querySelectorAll('td,th').length

  /* العنوان: أكبر نصٍّ خطًّا في الصفحة الأولى.
     و`textContent` يلصق ما فصله `<br>`: «تحليل نتائج<br>اختبار نافس» تخرج
     «تحليل نتائجاختبار نافس». فنستبدل الفواصل بمسافةٍ قبل القراءة. */
  const readable = (el: Element): string => {
    const c = el.cloneNode(true) as Element
    c.querySelectorAll('br').forEach((br) => br.replaceWith(doc.createTextNode(' ')))
    return (c.textContent || '').replace(/\s+/g, ' ').trim()
  }

  let title = ''
  let best = 0
  holder.querySelectorAll('p,h1,h2,h3').forEach((el) => {
    const t = readable(el)
    if (!t || t.length < 4 || t.length > 90) return
    const st = el.getAttribute('style') || ''
    const m = /font-size:\s*([\d.]+)px/.exec(st)
    const size = m ? Number(m[1]) : (el.tagName === 'H1' ? 26 : el.tagName === 'H2' ? 20 : 12)
    if (size > best) { best = size; title = t }
  })
  if (!title) title = docTitle && docTitle !== 'Bundled Page' ? docTitle
    : fileName.replace(/\.[^.]+$/, '')

  const dropped = Array.from(droppedSet)
  if (dropped.includes('script')) {
    warnings.push('أُسقطت الشيفرة البرمجيّة من الملفّ — القالب يفتحه كلّ المعلّمين، فلا يُشغَّل فيه سكربت.')
  }
  if (emptied) warnings.push(`أُسقط ${emptied} جدولًا فارغًا لا نصّ فيه ولا لون.`)
  if (!tables && !holder.textContent?.trim()) {
    warnings.push('لم نجد محتوًى في هذا الملفّ — تأكّد أنّه تصديرُ HTML لا صورة.')
  }

  // الاتّجاه: الصفحة الأولى إن كانت أعرض من طولها
  const st = sections[0]?.getAttribute('style') || ''
  const w = /width:\s*([\d.]+)(px|mm|in)/.exec(st)
  const h = /height:\s*([\d.]+)(px|mm|in)/.exec(st)
  const landscape = !!(w && h && Number(w[1]) > Number(h[1]))

  return {
    title,
    html: holder.innerHTML || '<p></p>',
    pages, tables, cells, landscape, margins,
    warnings, dropped,
  }
}
