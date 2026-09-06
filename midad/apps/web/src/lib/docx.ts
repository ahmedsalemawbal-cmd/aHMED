/**
 * HTML غنيّ ← ‎.docx‎ صالح، عربيًّا من اليمين.
 *
 * المُصدّر القديم كان يأخذ `textContent` فيضيع كلّ تنسيقٍ داخليّ: العريض
 * والمائل واللون تختفي، والقوائم تصير فقراتٍ تبدأ بنقطة. هذا يمشي في
 * استمارةٍ تُملأ، ولا يمشي في مستندٍ يكتبه المعلّم بيده.
 *
 * فهنا نمشي على الشجرة عقدةً عقدة: كلّ عقدة نصٍّ تصير `w:r` تحمل علاماتها
 * المتراكمة، والقوائم تُشير إلى `numbering.xml` كما تفعل الوورد نفسها،
 * والخلايا المدموجة تُترجَم إلى `gridSpan` و`vMerge`.
 *
 * ثلاثة أشياء إلزاميّة تُسقِط الملفّ إن غابت، وتعلّمتُها بالفحص لا بالحفظ:
 *   ١) `w:tblGrid` في كلّ جدول.
 *   ٢) جزء `numbering.xml` مع علاقته ونوع محتواه، إن وُجدت قائمةٌ واحدة.
 *   ٣) `xml:space="preserve"` وإلّا أُكلت المسافات الطرفيّة.
 */
import { zipSync } from './zip'

const X = (s: any) => String(s ?? '').replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' } as Record<string, string>)[c])

/** نصفُ نقطةٍ — وحدة الوورد للأحجام */
const HP = (pt: number) => Math.round(pt * 2)

/** twip — 1/1440 بوصة، وحدة الوورد للأطوال */
const MM_TW = (mm: number) => Math.round((mm / 25.4) * 1440)

interface Marks {
  b?: boolean; i?: boolean; u?: boolean; strike?: boolean
  color?: string; shade?: string; size?: number; font?: string
}

/* ═══════════════════ العلامات الداخليّة ═══════════════════ */

const hex6 = (v: string): string | undefined => {
  const s = String(v || '').trim()
  let m = /^#([0-9a-f]{6})$/i.exec(s)
  if (m) return m[1].toUpperCase()
  m = /^#([0-9a-f]{3})$/i.exec(s)
  if (m) return m[1].split('').map((c) => c + c).join('').toUpperCase()
  m = /^rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/i.exec(s)
  if (m) {
    return [1, 2, 3].map((i) => Math.min(255, Number(m![i])).toString(16).padStart(2, '0')).join('').toUpperCase()
  }
  return undefined
}

/** يقرأ العلامات التي يضيفها هذا العنصر إلى ما ورثه */
function marksOf(el: Element, inherited: Marks): Marks {
  const tag = el.tagName.toLowerCase()
  const m: Marks = { ...inherited }
  if (tag === 'strong' || tag === 'b') m.b = true
  if (tag === 'em' || tag === 'i') m.i = true
  if (tag === 'u' || tag === 'ins') m.u = true
  if (tag === 's' || tag === 'strike' || tag === 'del') m.strike = true
  if (tag === 'mark') m.shade = 'FFF3A3'

  const st = (el as HTMLElement).style
  if (st) {
    const c = hex6(st.color); if (c) m.color = c
    const bg = hex6(st.backgroundColor); if (bg) m.shade = bg
    const fs = /^([\d.]+)(px|pt)$/.exec(String(st.fontSize || '').trim())
    if (fs) m.size = fs[2] === 'pt' ? Number(fs[1]) : Number(fs[1]) * 0.75
    if (st.fontFamily) m.font = st.fontFamily.split(',')[0].replace(/["']/g, '').trim()
    if (st.fontWeight === 'bold' || Number(st.fontWeight) >= 600) m.b = true
    if (st.fontStyle === 'italic') m.i = true
    if (String(st.textDecorationLine || st.textDecoration || '').includes('underline')) m.u = true
    if (String(st.textDecorationLine || st.textDecoration || '').includes('line-through')) m.strike = true
  }
  // العنصر mark يحمل لونه في data-color حين يكون متعدّد الألوان
  const dc = el.getAttribute?.('data-color')
  if (dc) { const h = hex6(dc); if (h) m.shade = h }
  return m
}

function rPr(m: Marks): string {
  const font = m.font || 'Arial'
  const sz = HP(m.size ?? 11)
  return '<w:rPr>'
    + `<w:rFonts w:ascii="${X(font)}" w:hAnsi="${X(font)}" w:cs="${X(font)}"/>`
    + (m.b ? '<w:b/><w:bCs/>' : '')
    + (m.i ? '<w:i/><w:iCs/>' : '')
    + (m.u ? '<w:u w:val="single"/>' : '')
    + (m.strike ? '<w:strike/>' : '')
    + (m.color ? `<w:color w:val="${m.color}"/>` : '')
    + (m.shade ? `<w:shd w:val="clear" w:color="auto" w:fill="${m.shade}"/>` : '')
    + `<w:sz w:val="${sz}"/><w:szCs w:val="${sz}"/>`
    + '<w:rtl/>'
    + '</w:rPr>'
}

/** نصٌّ ← `w:r` */
function run1(text: string, m: Marks): string {
  return text ? `<w:r>${rPr(m)}<w:t xml:space="preserve">${X(text)}</w:t></w:r>` : ''
}

/**
 * مقطعٌ سطريّ قبل التسليس: نجمع المقاطع أوّلًا ثمّ نقصّ الفراغ من طرفَي
 * الفقرة — لأنّ سطرَ المصدر وإزاحته في HTML مسافةٌ واحدة لا فاصل سطر،
 * وقصُّها بعد الجمع أصحُّ من قصّها في كلّ عقدةٍ على حِدة.
 */
type Seg = { t: string; m: Marks } | { br: true }

function segRuns(segs: Seg[]): string {
  const list = segs.slice()
  // اقصص الفراغ من البداية
  while (list.length) {
    const f = list[0]
    if ('br' in f) { list.shift(); continue }
    f.t = f.t.replace(/^\s+/, '')
    if (f.t) break
    list.shift()
  }
  // ومن النهاية
  while (list.length) {
    const l = list[list.length - 1]
    if ('br' in l) { list.pop(); continue }
    l.t = l.t.replace(/\s+$/, '')
    if (l.t) break
    list.pop()
  }
  return list.map((s) => ('br' in s ? `<w:r>${rPr({})}<w:br/></w:r>` : run1(s.t, s.m))).join('')
}

/** يُبقى للمواضع التي تُمرّر نصًّا جاهزًا (الترويسة والعلامة المائية) */
function runs(text: string, m: Marks): string {
  return segRuns([{ t: String(text ?? ''), m }])
}

/* ═══════════════════ الفقرات ═══════════════════ */

const ALIGN: Record<string, string> = {
  right: 'right', left: 'left', center: 'center', justify: 'both',
}

interface PPr {
  align?: string
  style?: string
  list?: { id: 1 | 2; level: number }
  quote?: boolean
  spaceBefore?: number
  spaceAfter?: number
}

function pPr(p: PPr): string {
  return '<w:pPr>'
    + '<w:bidi/>'
    + (p.style ? `<w:pStyle w:val="${p.style}"/>` : '')
    + (p.list ? `<w:numPr><w:ilvl w:val="${p.list.level}"/><w:numId w:val="${p.list.id}"/></w:numPr>` : '')
    + (p.quote ? '<w:ind w:start="454"/><w:pBdr><w:end w:val="single" w:sz="18" w:space="6" w:color="CFC9EE"/></w:pBdr>' : '')
    + `<w:spacing w:before="${p.spaceBefore ?? 0}" w:after="${p.spaceAfter ?? 120}" w:line="300" w:lineRule="auto"/>`
    + `<w:jc w:val="${ALIGN[p.align || 'right'] || 'right'}"/>`
    + '</w:pPr>'
}

function collect(el: Node, m: Marks, into: Seg[]): void {
  el.childNodes.forEach((n) => {
    if (n.nodeType === 3) {
      // طيّ المسافات كما يفعل المتصفّح: سطرُ المصدر وإزاحته = مسافةٌ واحدة
      const t = (n.nodeValue || '').replace(/\s+/g, ' ')
      if (t) into.push({ t, m })
      return
    }
    if (n.nodeType !== 1) return
    const e = n as Element
    const tag = e.tagName.toLowerCase()
    if (tag === 'br') { into.push({ br: true }); return }
    /* الرابط: جعلُه قابلًا للنقر يحتاج علاقةً خارجيّة في الحزمة. نُبقيه
       نصًّا مُنسَّقًا ونُلحق عنوانه بين قوسين — أصدق من رابطٍ ميت. */
    if (tag === 'a') {
      const href = e.getAttribute('href') || ''
      collect(e, marksOf(e, { ...m, u: true, color: '4436B4' }), into)
      const label = (e.textContent || '').trim()
      if (href && href !== label) {
        into.push({ t: ` (${href})`, m: { ...m, size: (m.size ?? 11) - 1.5, color: '8B88A6' } })
      }
      return
    }
    collect(e, marksOf(e, m), into)
  })
}

/** يجمع محتوى عنصرٍ سطريّ إلى runs، ويعيد '' إن كان فارغًا */
function inlineRuns(el: Node, m: Marks): string {
  const segs: Seg[] = []
  collect(el, m, segs)
  return segRuns(segs)
}

/* ═══════════════════ الجداول ═══════════════════ */

function cellPr(td: Element, widthTw: number): string {
  const span = Number(td.getAttribute('colspan') || 1)
  const rows = Number(td.getAttribute('rowspan') || 1)
  const isTh = td.tagName.toLowerCase() === 'th'
  const bg = hex6((td as HTMLElement).style?.backgroundColor || '') || (isTh ? 'F1EEFB' : undefined)
  return '<w:tcPr>'
    + `<w:tcW w:w="${widthTw * Math.max(1, span)}" w:type="dxa"/>`
    + (span > 1 ? `<w:gridSpan w:val="${span}"/>` : '')
    + (rows > 1 ? '<w:vMerge w:val="restart"/>' : '')
    + (bg ? `<w:shd w:val="clear" w:color="auto" w:fill="${bg}"/>` : '')
    + '<w:vAlign w:val="top"/>'
    + '</w:tcPr>'
}

function tableXml(tbl: Element, availTw: number): string {
  const rows = Array.from(tbl.querySelectorAll('tr'))
  if (!rows.length) return ''

  // عدد الأعمدة = أكبر مجموع colspan في صفّ
  let cols = 0
  for (const tr of rows) {
    let n = 0
    Array.from(tr.children).forEach((c) => { n += Number(c.getAttribute('colspan') || 1) })
    cols = Math.max(cols, n)
  }
  cols = Math.max(1, cols)
  const w = Math.floor(availTw / cols)

  // w:tblGrid إلزاميّ — بغيره يرفض القارئ الصارم الملفّ
  const grid = `<w:tblGrid>${Array.from({ length: cols }, () => `<w:gridCol w:w="${w}"/>`).join('')}</w:tblGrid>`

  /* الخلايا الممتدّة رأسيًّا: الصفّ التالي يحتاج خليّةَ استمرارٍ فارغة
     تحمل vMerge بلا val، وإلّا انحرفت الأعمدة. نتبّع الامتدادات المفتوحة. */
  const pending: number[] = Array.from({ length: cols }, () => 0)

  const trXml = rows.map((tr) => {
    const isHead = tr.parentElement?.tagName.toLowerCase() === 'thead'
      || Array.from(tr.children).every((c) => c.tagName.toLowerCase() === 'th')
    const cells: string[] = []
    let col = 0
    const src = Array.from(tr.children)
    let si = 0

    while (col < cols) {
      if (pending[col] > 0) {
        pending[col]--
        cells.push(`<w:tc><w:tcPr><w:tcW w:w="${w}" w:type="dxa"/><w:vMerge/></w:tcPr>`
          + `<w:p>${pPr({ align: 'center' })}</w:p></w:tc>`)
        col++
        continue
      }
      const td = src[si++]
      if (!td) break
      const span = Math.max(1, Number(td.getAttribute('colspan') || 1))
      const rowspan = Math.max(1, Number(td.getAttribute('rowspan') || 1))
      const isTh = td.tagName.toLowerCase() === 'th'
      const base: Marks = { size: 10.5, b: isTh || undefined }
      const align = (td as HTMLElement).style?.textAlign
        || (td.querySelector('p') as HTMLElement | null)?.style?.textAlign
        || (isTh ? 'center' : 'right')

      const blocks = blocksIn(td, base, { align, inCell: true })
      cells.push(`<w:tc>${cellPr(td, w)}${blocks || `<w:p>${pPr({ align })}</w:p>`}</w:tc>`)

      if (rowspan > 1) for (let k = 0; k < span; k++) pending[col + k] = rowspan - 1
      col += span
    }

    return `<w:tr>${isHead ? '<w:trPr><w:tblHeader/></w:trPr>' : ''}${cells.join('')}</w:tr>`
  }).join('')

  const borders = `<w:tblBorders>${['top', 'start', 'bottom', 'end', 'insideH', 'insideV']
    .map((b) => `<w:${b} w:val="single" w:sz="6" w:space="0" w:color="B9B5CF"/>`).join('')}</w:tblBorders>`

  return '<w:tbl><w:tblPr>'
    + '<w:tblStyle w:val="TableGrid"/><w:bidiVisual/>'
    + `<w:tblW w:w="${availTw}" w:type="dxa"/>`
    + borders
    + '<w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:start w:w="90" w:type="dxa"/>'
    + '<w:bottom w:w="60" w:type="dxa"/><w:end w:w="90" w:type="dxa"/></w:tblCellMar>'
    + `</w:tblPr>${grid}${trXml}</w:tbl>`
}

/* ═══════════════════ المشي على الكتل ═══════════════════ */

interface Ctx { align?: string; inCell?: boolean; listLevel?: number; availTw?: number; media?: MediaMap }

/**
 * صور المستند بعد جلبها: مفتاحها `src` كما ورد في المتن.
 * `id` رقم العلاقة في الوورد، و`w`/`h` بالبكسل لحساب المقاس.
 */
export interface MediaItem { id: number; ext: string; mime: string; bytes: Uint8Array; w: number; h: number }
export type MediaMap = Map<string, MediaItem>

/** بكسلٌ واحد = ٩٥٢٥ وحدة EMU عند ٩٦ نقطةً في البوصة. */
const EMU_PER_PX = 9525
/** تويبٌ واحد = ٦٣٥ وحدة EMU. */
const EMU_PER_TW = 635

/**
 * صورةٌ في الوورد: علاقةٌ إلى ملفٍّ في word/media، ومقاسٌ بوحدات EMU.
 *
 * والمقاس يُقيَّد بعرض الصفحة المتاح: شعارٌ أصله ٣٠٠٠px يخرج بلا هذا
 * أعرضَ من الورقة، فيقصّه الوورد أو يُخرجه عن الحدّ.
 */
function drawing(it: MediaItem, availTw: number): string {
  const maxEmu = availTw * EMU_PER_TW
  let w = it.w * EMU_PER_PX
  let h = it.h * EMU_PER_PX
  if (w > maxEmu) { h = Math.round(h * (maxEmu / w)); w = maxEmu }
  const rid = `rIdImg${it.id}`
  return `<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">`
    + `<wp:extent cx="${Math.round(w)}" cy="${Math.round(h)}"/>`
    + `<wp:docPr id="${it.id}" name="Image${it.id}"/>`
    + `<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">`
    + `<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">`
    + `<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">`
    + `<pic:nvPicPr><pic:cNvPr id="${it.id}" name="Image${it.id}"/><pic:cNvPicPr/></pic:nvPicPr>`
    + `<pic:blipFill><a:blip r:embed="${rid}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>`
    + `<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="${Math.round(w)}" cy="${Math.round(h)}"/></a:xfrm>`
    + `<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>`
    + `</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>`
}

/**
 * يجلب صور المتن ويقرأ مقاساتها.
 *
 * ولمَ الجلب هنا لا عند الرسم؟ لأنّ بناء الوورد تركيبُ نصٍّ متزامن،
 * والشبكة غير متزامنة. فنجمعها أوّلًا ثمّ نبني.
 *
 * وما تعذّر جلبه يُطرح بصمتٍ ويُذكر في الحصيلة: خيرٌ من ملفٍّ لا يفتحه
 * الوورد لأنّ فيه علاقةً إلى ملفٍّ غير موجود.
 */
export async function collectMedia(bodyHtml: string): Promise<{ media: MediaMap; failed: number }> {
  const dom = new DOMParser().parseFromString(`<div>${bodyHtml}</div>`, 'text/html')
  const srcs = Array.from(new Set(
    Array.from(dom.querySelectorAll('img')).map((im) => im.getAttribute('src') || '').filter(Boolean),
  ))
  const media: MediaMap = new Map()
  let failed = 0
  let id = 1

  const EXT: Record<string, string> = {
    'image/png': 'png', 'image/jpeg': 'jpeg', 'image/gif': 'gif', 'image/webp': 'webp',
  }

  for (const src of srcs) {
    try {
      const res = await fetch(src, { mode: 'cors' })
      if (!res.ok) throw new Error(String(res.status))
      const blob = await res.blob()
      const mime = blob.type || 'image/png'
      const ext = EXT[mime]
      /* SVG لا يُضمَّن: الوورد لا يعرضه في هذا الموضع. وإسقاطه أصدق من
         ملفٍّ يفتح على مربّعٍ فارغ. */
      if (!ext) { failed++; continue }
      const bmp = await createImageBitmap(blob)
      media.set(src, {
        id: id++, ext, mime,
        bytes: new Uint8Array(await blob.arrayBuffer()),
        w: bmp.width, h: bmp.height,
      })
      bmp.close?.()
    } catch { failed++ }
  }
  return { media, failed }
}

const HEAD = { h1: { pt: 19, style: 'Heading1' }, h2: { pt: 15.5, style: 'Heading2' }, h3: { pt: 13.5, style: 'Heading3' } }

function blocksIn(root: Node, m: Marks, ctx: Ctx = {}): string {
  const avail = ctx.availTw ?? 9000
  const out: string[] = []

  const emitList = (list: Element, id: 1 | 2, level: number) => {
    Array.from(list.children).forEach((li) => {
      if (li.tagName.toLowerCase() !== 'li') return
      /* النصّ المباشر للعنصر بلا القوائم المتفرّعة منه. والمسافات التي
         تفصل النصّ عن القائمة المتفرّعة تُهمَل، وإلّا خرج العنصر بذيلٍ
         من الفراغ في الوورد. */
      const own = document.createElement('div')
      Array.from(li.childNodes).forEach((n) => {
        const t = (n as Element).tagName?.toLowerCase?.()
        if (t === 'ul' || t === 'ol') return
        if (n.nodeType === 3 && !(n.nodeValue || '').trim()) return
        own.appendChild(n.cloneNode(true))
      })
      const r = inlineRuns(own, m)
      if (!r) return
      out.push(`<w:p>${pPr({ list: { id, level }, align: ctx.align, spaceAfter: 60 })}${r}</w:p>`)
      // قوائم متفرّعة — الوورد يسمح بتسعة مستويات
      Array.from(li.children).forEach((ch) => {
        const t = ch.tagName.toLowerCase()
        if (t === 'ul') emitList(ch, 1, Math.min(8, level + 1))
        if (t === 'ol') emitList(ch, 2, Math.min(8, level + 1))
      })
    })
  }

  Array.from((root as Element).childNodes).forEach((node) => {
    if (node.nodeType === 3) {
      const t = (node.nodeValue || '').trim()
      if (t) out.push(`<w:p>${pPr({ align: ctx.align })}${runs(t, m)}</w:p>`)
      return
    }
    if (node.nodeType !== 1) return
    const el = node as Element
    const tag = el.tagName.toLowerCase()
    const styleAlign = (el as HTMLElement).style?.textAlign || undefined
    const align = styleAlign || ctx.align

    if (tag === 'h1' || tag === 'h2' || tag === 'h3') {
      const h = HEAD[tag]
      const hm = marksOf(el, { ...m, b: true, size: h.pt })
      out.push(`<w:p>${pPr({
        align: styleAlign || (tag === 'h1' ? 'center' : 'right'),
        style: h.style, spaceBefore: tag === 'h1' ? 0 : 200, spaceAfter: 120,
      })}${inlineRuns(el, hm)}</w:p>`)
      return
    }

    if (tag === 'img') {
      const it = ctx.media?.get(el.getAttribute('src') || '')
      if (it) out.push(`<w:p>${pPr({ align: 'center', spaceAfter: 120 })}${drawing(it, avail)}</w:p>`)
      return
    }

    if (tag === 'p') {
      /* فقرةٌ ليس فيها إلّا صورة — وهكذا يلفّها ProseMirror أحيانًا.
         فلو مررناها على inlineRuns لخرجت فقرةً فارغة والصورة ضائعة. */
      const only = el.children.length === 1 && el.children[0].tagName.toLowerCase() === 'img'
        && !(el.textContent || '').trim()
      if (only) {
        const it = ctx.media?.get(el.children[0].getAttribute('src') || '')
        if (it) out.push(`<w:p>${pPr({ align: 'center', spaceAfter: 120 })}${drawing(it, avail)}</w:p>`)
        return
      }
      const r = inlineRuns(el, marksOf(el, m))
      out.push(`<w:p>${pPr({ align })}${r}</w:p>`)
      return
    }

    if (tag === 'ul') { emitList(el, 1, ctx.listLevel ?? 0); return }
    if (tag === 'ol') { emitList(el, 2, ctx.listLevel ?? 0); return }

    if (tag === 'blockquote') {
      const inner = blocksIn(el, { ...m, color: '4A4860' }, { ...ctx, align })
      // نُضيف الإزاحة والحدّ إلى فقرات الاقتباس
      out.push(inner.replace(/<w:pPr><w:bidi\/>/g, '<w:pPr><w:bidi/><w:ind w:start="454"/>'))
      return
    }

    if (tag === 'hr') {
      out.push('<w:p><w:pPr><w:bidi/><w:pBdr>'
        + '<w:bottom w:val="single" w:sz="6" w:space="1" w:color="C9C6DA"/>'
        + '</w:pBdr><w:spacing w:before="120" w:after="180"/></w:pPr></w:p>')
      return
    }

    if (tag === 'table') { out.push(tableXml(el, avail)); out.push(`<w:p>${pPr({ spaceAfter: 0 })}</w:p>`); return }

    if (tag === 'br') { return }

    // حاوياتٌ عامّة: ننزل فيها
    if (el.children.length) { out.push(blocksIn(el, marksOf(el, m), { ...ctx, align })); return }
    const t = (el.textContent || '').trim()
    if (t) out.push(`<w:p>${pPr({ align })}${runs(t, marksOf(el, m))}</w:p>`)
  })

  return out.join('')
}

/* ═══════════════════ الأجزاء ═══════════════════ */

/** تعريف القائمتين — بغير هذا الجزء تظهر عناصر القائمة بلا نقاطٍ ولا أرقام */
function numberingXml(): string {
  const lvls = (fmt: 'bullet' | 'decimal') => Array.from({ length: 9 }, (_, i) => {
    const indent = 454 + i * 340
    if (fmt === 'bullet') {
      const ch = ['', 'o', '▪'][i % 3]
      return `<w:lvl w:ilvl="${i}"><w:start w:val="1"/><w:numFmt w:val="bullet"/>`
        + `<w:lvlText w:val="${ch}"/><w:lvlJc w:val="start"/>`
        + `<w:pPr><w:ind w:start="${indent}" w:hanging="284"/></w:pPr>`
        + `<w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol" w:hint="default"/></w:rPr></w:lvl>`
    }
    const marks = ['decimal', 'arabicAlpha', 'lowerRoman']
    return `<w:lvl w:ilvl="${i}"><w:start w:val="1"/><w:numFmt w:val="${marks[i % 3]}"/>`
      + `<w:lvlText w:val="%${i + 1}."/><w:lvlJc w:val="start"/>`
      + `<w:pPr><w:ind w:start="${indent}" w:hanging="284"/></w:pPr></w:lvl>`
  }).join('')

  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="hybridMultilevel"/>${lvls('bullet')}</w:abstractNum>
<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="hybridMultilevel"/>${lvls('decimal')}</w:abstractNum>
<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>
<w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num>
</w:numbering>`
}

function stylesXml(): string {
  const head = (id: string, name: string, pt: number, outline: number) =>
    `<w:style w:type="paragraph" w:styleId="${id}"><w:name w:val="${name}"/>`
    + `<w:basedOn w:val="Normal"/><w:qFormat/>`
    + `<w:pPr><w:bidi/><w:keepNext/><w:outlineLvl w:val="${outline}"/></w:pPr>`
    + `<w:rPr><w:b/><w:bCs/><w:sz w:val="${HP(pt)}"/><w:szCs w:val="${HP(pt)}"/></w:rPr></w:style>`

  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:docDefaults>
  <w:rPrDefault><w:rPr>
    <w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>
    <w:sz w:val="22"/><w:szCs w:val="22"/><w:rtl/>
  </w:rPr></w:rPrDefault>
  <w:pPrDefault><w:pPr><w:bidi/><w:jc w:val="right"/>
    <w:spacing w:after="120" w:line="300" w:lineRule="auto"/></w:pPr></w:pPrDefault>
</w:docDefaults>
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>
${head('Heading1', 'heading 1', 19, 0)}
${head('Heading2', 'heading 2', 15.5, 1)}
${head('Heading3', 'heading 3', 13.5, 2)}
<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/>
  <w:tblPr><w:bidiVisual/></w:tblPr></w:style>
</w:styles>`
}

/* ═══════════════════ الواجهة ═══════════════════ */

export interface DocxOpts {
  title?: string
  /** ترويسة تُدرج فوق المتن — اسم المدرسة وما معه */
  header?: { school?: string; dept?: string; year?: string; semester?: string } | null
  watermark?: string | null
  page?: { orientation?: 'portrait' | 'landscape'; margins?: { top: number; right: number; bottom: number; left: number } }
}

export async function buildRichDocx(bodyHtml: string, opts: DocxOpts = {}): Promise<Blob> {
  /* الصور تُجلب أوّلًا: بناء الوورد تركيبُ نصٍّ متزامن، والشبكة ليست كذلك. */
  const { media } = await collectMedia(bodyHtml)
  const portrait = opts.page?.orientation !== 'landscape'
  const mg = opts.page?.margins || { top: 18, right: 16, bottom: 18, left: 16 }
  const pgW = portrait ? 11906 : 16838
  const pgH = portrait ? 16838 : 11906
  const availTw = pgW - MM_TW(mg.right) - MM_TW(mg.left)

  const dom = new DOMParser().parseFromString(`<div id="r">${bodyHtml}</div>`, 'text/html')
  const root = dom.getElementById('r')
  const body = root ? blocksIn(root, {}, { availTw, media }) : ''

  const h = opts.header
  const headerXml = h && (h.school || h.dept || h.year || h.semester)
    ? `<w:p>${pPr({ align: 'center', spaceAfter: 40 })}${runs(h.school || '', { b: true, size: 13 })}</w:p>`
      + `<w:p>${pPr({ align: 'center', spaceAfter: 160 })}`
      + runs([h.dept, h.year, h.semester].filter(Boolean).join(' · '), { size: 10, color: '5B5878' })
      + '</w:p>'
    : ''

  const wm = opts.watermark
    ? `<w:p>${pPr({ align: 'center', spaceAfter: 100 })}${runs(opts.watermark, { size: 9.5, color: 'B9B5CF' })}</w:p>`
    : ''

  const documentXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">
<w:body>${wm}${headerXml}${body}
<w:sectPr><w:bidi/>
  <w:pgSz w:w="${pgW}" w:h="${pgH}"${portrait ? '' : ' w:orient="landscape"'}/>
  <w:pgMar w:top="${MM_TW(mg.top)}" w:right="${MM_TW(mg.right)}" w:bottom="${MM_TW(mg.bottom)}" w:left="${MM_TW(mg.left)}" w:header="708" w:footer="708" w:gutter="0"/>
</w:sectPr>
</w:body></w:document>`

  const items = [...media.values()]
  /* نوعٌ افتراضيّ لكلّ امتدادٍ مستعمَل مرّةً واحدة: تكرار Default لامتدادٍ
     واحد يُفسد الحزمة ويرفضها الوورد بلا بيان. */
  const exts = [...new Set(items.map((i) => i.ext))]
  const mimeOf: Record<string, string> = {
    png: 'image/png', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp',
  }

  return new Blob([zipSync([
    {
      name: '[Content_Types].xml', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
${exts.map((e) => `<Default Extension="${e}" ContentType="${mimeOf[e]}"/>`).join('\n')}
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
</Types>`,
    },
    {
      name: '_rels/.rels', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>`,
    },
    {
      name: 'word/_rels/document.xml.rels', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
${items.map((i) => `<Relationship Id="rIdImg${i.id}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image${i.id}.${i.ext}"/>`).join('\n')}
</Relationships>`,
    },
    { name: 'word/document.xml', content: documentXml },
    { name: 'word/styles.xml', content: stylesXml() },
    { name: 'word/numbering.xml', content: numberingXml() },
    ...items.map((i) => ({ name: `word/media/image${i.id}.${i.ext}`, content: i.bytes })),
  ])], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' })
}
