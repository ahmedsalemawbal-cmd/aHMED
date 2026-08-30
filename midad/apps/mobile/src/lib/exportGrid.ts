import * as FileSystem from 'expo-file-system'
import * as Print from 'expo-print'
import * as Sharing from 'expo-sharing'
import { zipSync, toBase64 } from './zip'

/**
 * تصديرُ أيّ جدول — PDF · Word · Excel.
 *
 * ولمَ وحدةٌ ثانية بجانب `exportAttendance`؟ لأنّ تلك تعرف **كشفَ حضور**:
 * أعمدتُها أربعةٌ بأسمائها، وفيها حصيلةٌ وتوقيع. وجدولُ نور لا يُعرف
 * شكلُه قبل أن يصل: أعمدتُه ما كتبته الوزارة في تلك الصفحة، وقد تكون
 * ثلاثةً أو ثلاثين.
 *
 *     ما لا يُعرف شكلُه لا يُصدَّر بقالبٍ يعرف شكله.
 *
 * فهذه تأخذ عناوينَ وصفوفًا وحسب. ولا تُكرّر ما في الأخرى: الضغطُ
 * والترميزُ والمشاركة كلُّها من `zip.ts` نفسها.
 */

export type GridFormat = 'pdf' | 'docx' | 'xlsx'

export const GRID_FORMAT_AR: Record<GridFormat, string> = {
  pdf: 'PDF للطباعة', docx: 'Word قابل للتعديل', xlsx: 'Excel للجداول',
}

export interface Grid {
  title: string
  columns: string[]
  rows: (string | number | null)[][]
  /** سطرٌ تحت العنوان: مصدرُ الجدول وتاريخُه */
  subtitle?: string
}

const esc = (v: any) => String(v ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')

/** اسمُ ملفٍّ يصلح على أندرويد وiOS: لا شرطاتٍ مائلةٍ ولا نقطتين. */
function safeName(s: string): string {
  return (s || 'جدول').replace(/[\\/:*?"<>|]+/g, ' ').replace(/\s+/g, '_').slice(0, 60)
}

/* ─────────────── PDF ─────────────── */

/**
 * والعرضُ يتبع عددَ الأعمدة: جدولٌ بعشرة أعمدةٍ على ورقةٍ طوليّة يخرج
 * بخطٍّ لا يُقرأ. فما جاوز ستّةً يُطبع عرضيًّا.
 */
function gridHtml(g: Grid): string {
  const wide = g.columns.length > 6
  const fs = g.columns.length > 12 ? 6.5 : g.columns.length > 8 ? 7.5 : 9
  return `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<style>
@page { size: A4 ${wide ? 'landscape' : 'portrait'}; margin: 12mm; }
body { font-family: -apple-system, "Segoe UI", Roboto, "Noto Naskh Arabic", sans-serif;
       color: #111; margin: 0; }
h1 { font-size: 14pt; margin: 0 0 3mm; }
.sub { font-size: 9pt; color: #555; margin: 0 0 5mm; }
table { width: 100%; border-collapse: collapse; font-size: ${fs}pt; }
th, td { border: 1px solid #999; padding: 1.6mm 2mm; text-align: start; vertical-align: top; }
th { background: #EFEDF9; font-weight: 700; }
tr:nth-child(even) td { background: #FAFAFD; }
</style></head><body>
<h1>${esc(g.title)}</h1>
${g.subtitle ? `<p class="sub">${esc(g.subtitle)}</p>` : ''}
<table><thead><tr>${g.columns.map((h) => `<th>${esc(h)}</th>`).join('')}</tr></thead>
<tbody>${g.rows.map((r) =>
    `<tr>${g.columns.map((_, i) => `<td>${esc(r[i])}</td>`).join('')}</tr>`).join('')}</tbody></table>
</body></html>`
}

/* ─────────────── Word ─────────────── */

function cell(text: any, w: number, header = false): string {
  return `<w:tc><w:tcPr><w:tcW w:w="${w}" w:type="dxa"/>${
    header ? '<w:shd w:val="clear" w:fill="EFEDF9"/>' : ''
  }</w:tcPr><w:p><w:pPr><w:bidi/><w:spacing w:after="0"/></w:pPr><w:r><w:rPr><w:rtl/>${
    header ? '<w:b/>' : ''
  }<w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">${esc(text)}</w:t></w:r></w:p></w:tc>`
}

function para(text: string, size: number, bold = false): string {
  return `<w:p><w:pPr><w:bidi/><w:jc w:val="right"/></w:pPr><w:r><w:rPr><w:rtl/>${
    bold ? '<w:b/>' : ''
  }<w:sz w:val="${size}"/></w:rPr><w:t xml:space="preserve">${esc(text)}</w:t></w:r></w:p>`
}

function docxBytes(g: Grid): Uint8Array {
  const wide = g.columns.length > 6
  /* عرضُ الورقة بالتويب ناقصًا الهوامش — يُقسَّم على الأعمدة بالتساوي */
  const usable = wide ? 14400 : 9360
  const w = Math.max(500, Math.floor(usable / Math.max(1, g.columns.length)))

  const head = `<w:tr><w:trPr><w:tblHeader/></w:trPr>${
    g.columns.map((h) => cell(h, w, true)).join('')}</w:tr>`
  const body = g.rows.map((r) =>
    `<w:tr>${g.columns.map((_, i) => cell(r[i], w)).join('')}</w:tr>`).join('')

  const doc = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
${para(g.title, 28, true)}
${g.subtitle ? para(g.subtitle, 18) : ''}
<w:tbl><w:tblPr><w:bidiVisual/><w:tblW w:w="${usable}" w:type="dxa"/>
<w:tblBorders>${['top', 'left', 'bottom', 'right', 'insideH', 'insideV']
    .map((s) => `<w:${s} w:val="single" w:sz="4" w:color="999999"/>`).join('')}</w:tblBorders>
</w:tblPr>${head}${body}</w:tbl>
<w:sectPr><w:pgSz w:w="${wide ? '16838' : '11906'}" w:h="${wide ? '11906' : '16838'}"${
    wide ? ' w:orient="landscape"' : ''}/><w:bidi/></w:sectPr>
</w:body></w:document>`

  return zipSync([
    { name: '[Content_Types].xml', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>` },
    { name: '_rels/.rels', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>` },
    { name: 'word/_rels/document.xml.rels', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>` },
    { name: 'word/document.xml', content: doc },
  ])
}

/* ─────────────── Excel ─────────────── */

function colRef(i: number): string {
  let s = ''
  let n = i
  while (n >= 0) { s = String.fromCharCode(65 + (n % 26)) + s; n = Math.floor(n / 26) - 1 }
  return s
}

function xlsxBytes(g: Grid): Uint8Array {
  const grid: (string | number | null)[][] = [
    [g.title],
    ...(g.subtitle ? [[g.subtitle]] : []),
    [],
    g.columns,
    ...g.rows,
  ]
  const headRow = g.subtitle ? 4 : 3

  const sheetRows = grid.map((cells, ri) => {
    const r = ri + 1
    const cs = (cells || []).map((v, ci) => {
      const ref = `${colRef(ci)}${r}`
      const style = r === 1 ? ' s="2"' : r === headRow ? ' s="1"' : ''
      /* الرقمُ يُكتب رقمًا ليُجمع في إكسل — والنصُّ الذي يبدو رقمًا
         (رقمُ الهويّة، والجوّال) يبقى نصًّا: تحويلُه يمحو صفرَه الأوّل. */
      if (typeof v === 'number' && Number.isFinite(v)) {
        return `<c r="${ref}"${style}><v>${v}</v></c>`
      }
      const t = String(v ?? '')
      if (!t) return ''
      return `<c r="${ref}"${style} t="inlineStr"><is><t xml:space="preserve">${esc(t)}</t></is></c>`
    }).join('')
    return `<row r="${r}">${cs}</row>`
  }).join('')

  const widths = g.columns.map((h, i) =>
    `<col min="${i + 1}" max="${i + 1}" width="${
      Math.min(40, Math.max(10, String(h || '').length + 6))}" customWidth="1"/>`).join('')

  const sheet = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews>
<cols>${widths}</cols>
<sheetData>${sheetRows}</sheetData></worksheet>`

  const styles = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="14"/><name val="Calibri"/></font></fonts>
<fills count="3"><fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FFEFEDF9"/></patternFill></fill></fills>
<borders count="1"><border/></borders>
<cellStyleXfs count="1"><xf/></cellStyleXfs>
<cellXfs count="3"><xf xfId="0"/>
<xf xfId="0" fontId="1" fillId="2" applyFont="1" applyFill="1"/>
<xf xfId="0" fontId="2" applyFont="1"/></cellXfs></styleSheet>`

  return zipSync([
    { name: '[Content_Types].xml', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>` },
    { name: '_rels/.rels', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>` },
    { name: 'xl/workbook.xml', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
 xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="الجدول" sheetId="1" r:id="rId1"/></sheets></workbook>` },
    { name: 'xl/_rels/workbook.xml.rels', content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>` },
    { name: 'xl/worksheets/sheet1.xml', content: sheet },
    { name: 'xl/styles.xml', content: styles },
  ])
}

/* ─────────────── الواجهة ─────────────── */

const MIME: Record<GridFormat, string> = {
  pdf: 'application/pdf',
  docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
}

const UTI: Record<GridFormat, string> = {
  pdf: 'com.adobe.pdf',
  docx: 'org.openxmlformats.wordprocessingml.document',
  xlsx: 'org.openxmlformats.spreadsheetml.sheet',
}

/** يُنتج الملفّ ويفتح ورقة المشاركة — ويعيد مساره. */
export async function exportGrid(format: GridFormat, g: Grid): Promise<string> {
  if (!g.rows.length) throw new Error('لا صفوف في هذا الجدول')
  const base = safeName(g.title)
  let uri: string

  if (format === 'pdf') {
    const { uri: tmp } = await Print.printToFileAsync({ html: gridHtml(g), base64: false })
    uri = `${FileSystem.cacheDirectory}${base}.pdf`
    /* والنقلُ لا النسخ: الاسمُ المولَّد من `Print` عشوائيّ، ويصل من
       يشاركه ملفًّا اسمُه رقم. */
    await FileSystem.moveAsync({ from: tmp, to: uri })
  } else {
    const bytes = format === 'docx' ? docxBytes(g) : xlsxBytes(g)
    uri = `${FileSystem.cacheDirectory}${base}.${format}`
    await FileSystem.writeAsStringAsync(uri, toBase64(bytes), {
      encoding: FileSystem.EncodingType.Base64,
    })
  }

  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(uri, {
      mimeType: MIME[format], dialogTitle: g.title, UTI: UTI[format],
    })
  }
  return uri
}
