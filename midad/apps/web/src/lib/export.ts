import { zipSync } from './zip'
import type { Template, TemplateField } from './types'
import { renderBody } from './template'

export interface PaperMeta {
  schoolName: string
  educationDept: string
  academicYear: string
  semester: string
  title: string
  logoUrl?: string | null
  watermark?: string | null
}

export function download(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 4000)
}

export function safeFileName(s: string): string {
  return (s || 'ملف').replace(/[\\/:*?"<>|]+/g, '-').replace(/\s+/g, ' ').trim().slice(0, 90)
}

/* ============================ PDF — عبر الطباعة ============================ */
export function printPaper() {
  window.print()
}

/* ============================ DOCX ============================ */
const X = (s: any) => String(s ?? '').replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' } as any)[c])

function wPara(text: string, opts: { bold?: boolean; size?: number; align?: string; heading?: boolean } = {}) {
  const sz = (opts.size || 22)
  const runProps = `<w:rPr><w:rtl/><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>${opts.bold ? '<w:b/><w:bCs/>' : ''}<w:sz w:val="${sz}"/><w:szCs w:val="${sz}"/></w:rPr>`
  const paraProps = `<w:pPr><w:bidi/><w:jc w:val="${opts.align || 'both'}"/>${opts.heading ? '<w:spacing w:before="240" w:after="120"/>' : '<w:spacing w:after="120"/>'}</w:pPr>`
  const parts = String(text || '').split('\n')
  const runs = parts.map((p, i) => `${i ? '<w:r><w:br/></w:r>' : ''}<w:r>${runProps}<w:t xml:space="preserve">${X(p)}</w:t></w:r>`).join('')
  return `<w:p>${paraProps}${runs}</w:p>`
}

function wTable(headers: string[], rows: string[][]) {
  const cell = (t: string, bold: boolean) =>
    `<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/>${bold ? '<w:shd w:val="clear" w:fill="EFEFEF"/>' : ''}</w:tcPr>` +
    `<w:p><w:pPr><w:bidi/><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:rtl/><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>${bold ? '<w:b/><w:bCs/>' : ''}<w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr><w:t xml:space="preserve">${X(t)}</w:t></w:r></w:p></w:tc>`
  const borders = `<w:tblBorders>${['top','left','bottom','right','insideH','insideV'].map(b => `<w:${b} w:val="single" w:sz="6" w:space="0" w:color="999999"/>`).join('')}</w:tblBorders>`
  const head = `<w:tr>${headers.map((h) => cell(h, true)).join('')}</w:tr>`
  const body = rows.map((r) => `<w:tr>${r.map((c) => cell(c, false)).join('')}</w:tr>`).join('')
  return `<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:bidiVisual/><w:tblW w:w="5000" w:type="pct"/>${borders}</w:tblPr>${head}${body}</w:tbl>`
}

/** يحوّل HTML الورقة (h2/h3/p/ul/table) إلى WordprocessingML. */
function htmlToWml(html: string): string {
  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html')
  const root = doc.body.firstElementChild
  if (!root) return ''
  const out: string[] = []
  const walk = (el: Element) => {
    for (const node of Array.from(el.children)) {
      const tag = node.tagName.toLowerCase()
      if (tag === 'h1') out.push(wPara(node.textContent || '', { bold: true, size: 32, align: 'center', heading: true }))
      else if (tag === 'h2') out.push(wPara(node.textContent || '', { bold: true, size: 26, align: 'start', heading: true }))
      else if (tag === 'h3') out.push(wPara(node.textContent || '', { bold: true, size: 23, align: 'start', heading: true }))
      else if (tag === 'p') out.push(wPara(node.textContent || ''))
      else if (tag === 'ul' || tag === 'ol') {
        for (const li of Array.from(node.querySelectorAll(':scope > li'))) out.push(wPara('•  ' + (li.textContent || '')))
      } else if (tag === 'table') {
        const headers = Array.from(node.querySelectorAll('thead th')).map((t) => t.textContent || '')
        const rows = Array.from(node.querySelectorAll('tbody tr')).map((tr) =>
          Array.from(tr.querySelectorAll('td')).map((td) => (td.textContent || '').replace(/ /g, ' ').trim()))
        if (headers.length) out.push(wTable(headers, rows))
        out.push(wPara(''))
      } else if (tag === 'div' || tag === 'section') {
        if (node.classList.contains('mdd-sign-row')) {
          const cells = Array.from(node.children).map((c) => c.textContent || '')
          if (cells.length) { out.push(wPara('')); out.push(wTable(cells, [cells.map(() => ' ')])) }
        } else walk(node)
      } else if (node.children.length) walk(node)
      else if ((node.textContent || '').trim()) out.push(wPara(node.textContent || ''))
    }
  }
  walk(root)
  return out.join('')
}

export function buildDocx(meta: PaperMeta, bodyHtml: string): Blob {
  const header =
    wPara(meta.schoolName || '', { bold: true, size: 26, align: 'center' }) +
    wPara([meta.educationDept, meta.academicYear, meta.semester].filter(Boolean).join(' · '), { size: 20, align: 'center' }) +
    wPara(meta.title, { bold: true, size: 32, align: 'center', heading: true })
  const watermark = meta.watermark ? wPara(meta.watermark, { size: 20, align: 'center' }) : ''

  const documentXml =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>${watermark}${header}${htmlToWml(bodyHtml)}
<w:sectPr><w:bidi/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>
</w:body></w:document>`

  const contentTypes =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>`

  const rels =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>`

  const docRels =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>`

  const styles =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:rtl/></w:rPr></w:rPrDefault>
<w:pPrDefault><w:pPr><w:bidi/></w:pPr></w:pPrDefault></w:docDefaults>
<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/></w:style>
</w:styles>`

  return new Blob([zipSync([
    { name: '[Content_Types].xml', content: contentTypes },
    { name: '_rels/.rels', content: rels },
    { name: 'word/_rels/document.xml.rels', content: docRels },
    { name: 'word/document.xml', content: documentXml },
    { name: 'word/styles.xml', content: styles },
  ])], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' })
}

/* ============================ XLSX ============================ */
function colName(i: number): string {
  let s = ''
  i++
  while (i > 0) { const m = (i - 1) % 26; s = String.fromCharCode(65 + m) + s; i = Math.floor((i - 1) / 26) }
  return s
}

export function buildXlsx(sheetName: string, headers: string[], rows: (string | number)[][]): Blob {
  const all = [headers, ...rows]
  const sheetRows = all.map((r, ri) => {
    const cells = r.map((v, ci) => {
      const ref = `${colName(ci)}${ri + 1}`
      const num = typeof v === 'number' || (typeof v === 'string' && v !== '' && !isNaN(Number(v)) && /^-?\d+(\.\d+)?$/.test(v))
      if (num) return `<c r="${ref}"><v>${Number(v)}</v></c>`
      return `<c r="${ref}" t="inlineStr"><is><t xml:space="preserve">${X(v)}</t></is></c>`
    }).join('')
    return `<row r="${ri + 1}">${cells}</row>`
  }).join('')

  const sheet =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews>
<sheetData>${sheetRows}</sheetData></worksheet>`

  const workbook =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="${X((sheetName || 'ورقة1').slice(0, 28))}" sheetId="1" r:id="rId1"/></sheets></workbook>`

  const wbRels =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>`

  const rels =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>`

  const contentTypes =
`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>`

  return new Blob([zipSync([
    { name: '[Content_Types].xml', content: contentTypes },
    { name: '_rels/.rels', content: rels },
    { name: 'xl/workbook.xml', content: workbook },
    { name: 'xl/_rels/workbook.xml.rels', content: wbRels },
    { name: 'xl/worksheets/sheet1.xml', content: sheet },
  ])], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
}

/** يستخرج أوّل جدول من القالب لتصدير إكسل. */
export function documentToSheet(tpl: Pick<Template, 'fields'>, data: Record<string, any>): { headers: string[]; rows: string[][] } | null {
  const tableField = (tpl.fields || []).find((f: TemplateField) => f.type === 'table')
  if (!tableField || !tableField.columns?.length) return null
  const headers = ['م', ...tableField.columns.map((c) => c.label)]
  const raw: any[] = Array.isArray(data?.[tableField.key]) ? data[tableField.key] : []
  const rows = raw.map((r, i) => [String(i + 1), ...tableField.columns!.map((c) => String(r?.[c.key] ?? ''))])
  return { headers, rows }
}

export function renderPaperHtml(tpl: Pick<Template, 'body' | 'fields'>, data: Record<string, any>): string {
  return renderBody(tpl, data)
}
