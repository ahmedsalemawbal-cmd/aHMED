import * as FileSystem from 'expo-file-system'
import * as Print from 'expo-print'
import * as Sharing from 'expo-sharing'
import { zipSync, toBase64 } from './zip'
import { STATUS_AR } from './classroom'
import type { AttendanceStatus } from './types'

export type ExportFormat = 'pdf' | 'docx' | 'xlsx'

export const FORMAT_AR: Record<ExportFormat, string> = {
  pdf: 'PDF للطباعة', docx: 'Word قابل للتعديل', xlsx: 'Excel للجداول',
}

export interface SheetMeta {
  className: string
  subject?: string | null
  periodLabel: string
  date: string          // 2026-08-23
  dayName: string
  teacher: string
  school?: string | null
}

export interface SheetRow { n: number; name: string; status: AttendanceStatus; note?: string }

function esc(s: any): string {
  return String(s ?? '').replace(/[&<>"']/g, (ch) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } as Record<string, string>)[ch])
}

function safeName(s: string): string {
  return String(s).replace(/[\/\\?%*:|"<>]/g, '-').replace(/\s+/g, '_').slice(0, 60)
}

function tally(rows: SheetRow[]) {
  const t: Record<AttendanceStatus, number> = { present: 0, late: 0, absent: 0, excused: 0 }
  for (const r of rows) t[r.status]++
  return t
}

/* ─────────────── PDF ─────────────── */

function sheetHtml(meta: SheetMeta, rows: SheetRow[]): string {
  const t = tally(rows)
  return `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<style>
  @page { size: A4; margin: 14mm 12mm; }
  * { box-sizing: border-box; }
  body { font-family: "Noto Naskh Arabic","Amiri",serif; color:#14131f; margin:0; direction:rtl; }
  .head { display:flex; justify-content:space-between; align-items:flex-start;
          border-bottom:2px solid #5B4BD6; padding-bottom:9px; margin-bottom:14px; }
  h1 { font-size:19px; margin:0 0 4px; }
  .sub { font-size:11.5px; color:#5a5870; line-height:1.9; }
  .meta { font-size:11.5px; color:#5a5870; text-align:left; line-height:1.9; }
  table { width:100%; border-collapse:collapse; font-size:12px; }
  th { background:#ECE9FC; color:#241C63; font-weight:700; padding:7px 6px; border:1px solid #cfc9ee; }
  td { padding:6px; border:1px solid #ddd; }
  td.n, th.n { width:34px; text-align:center; }
  td.s { width:78px; text-align:center; font-weight:700; }
  tbody tr:nth-child(even) td { background:#faf9ff; }
  .present { color:#0E9F6E } .late { color:#B4791B } .absent { color:#D64545 } .excused { color:#2E7BD6 }
  .sum { margin-top:14px; display:flex; gap:9px; font-size:11.5px; }
  .sum div { border:1px solid #e4e2f0; border-radius:8px; padding:6px 11px; }
  .sig { margin-top:26px; display:flex; justify-content:space-between; font-size:11.5px; color:#5a5870; }
</style></head><body>
<div class="head">
  <div>
    <h1>كشف الحضور والغياب</h1>
    <div class="sub">${esc(meta.className)}${meta.subject ? ` — ${esc(meta.subject)}` : ''} · ${esc(meta.periodLabel)}</div>
  </div>
  <div class="meta">
    ${meta.school ? esc(meta.school) + '<br>' : ''}
    ${esc(meta.dayName)} ${esc(meta.date)}<br>
    المعلّم: ${esc(meta.teacher)}
  </div>
</div>
<table>
  <thead><tr><th class="n">م</th><th>اسم الطالب</th><th class="s">الحالة</th><th style="width:26%">ملاحظة</th></tr></thead>
  <tbody>${rows.map((r) => `<tr>
    <td class="n">${r.n}</td><td>${esc(r.name)}</td>
    <td class="s ${r.status}">${STATUS_AR[r.status]}</td><td>${esc(r.note || '')}</td>
  </tr>`).join('')}</tbody>
</table>
<div class="sum">
  <div>حاضر: <b>${t.present}</b></div><div>متأخّر: <b>${t.late}</b></div>
  <div>غائب: <b>${t.absent}</b></div><div>معذور: <b>${t.excused}</b></div>
  <div>الإجمالي: <b>${rows.length}</b></div>
</div>
<div class="sig"><span>توقيع المعلّم: ..............................</span><span>الاعتماد: ..............................</span></div>
</body></html>`
}

/* ─────────────── Word ─────────────── */

function docxCell(text: string, opts: { header?: boolean; w: number; align?: 'center' | 'right'; color?: string } = { w: 2000 }) {
  const shade = opts.header ? '<w:shd w:val="clear" w:fill="ECE9FC"/>' : ''
  const b = opts.header ? '<w:b/>' : ''
  const col = opts.color ? `<w:color w:val="${opts.color}"/>` : ''
  return `<w:tc><w:tcPr><w:tcW w:w="${opts.w}" w:type="dxa"/>${shade}</w:tcPr>` +
    `<w:p><w:pPr><w:bidi/><w:jc w:val="${opts.align || 'right'}"/></w:pPr>` +
    `<w:r><w:rPr><w:rtl/>${b}${col}<w:sz w:val="22"/></w:rPr><w:t xml:space="preserve">${esc(text)}</w:t></w:r></w:p></w:tc>`
}

function docxPara(text: string, size = 22, bold = false, align = 'right') {
  return `<w:p><w:pPr><w:bidi/><w:jc w:val="${align}"/></w:pPr>` +
    `<w:r><w:rPr><w:rtl/>${bold ? '<w:b/>' : ''}<w:sz w:val="${size}"/></w:rPr>` +
    `<w:t xml:space="preserve">${esc(text)}</w:t></w:r></w:p>`
}

const STATUS_COLOR: Record<AttendanceStatus, string> = {
  present: '0E9F6E', late: 'B4791B', absent: 'D64545', excused: '2E7BD6',
}

function docxBytes(meta: SheetMeta, rows: SheetRow[]): Uint8Array {
  const t = tally(rows)
  const header =
    `<w:tr>${docxCell('م', { header: true, w: 600, align: 'center' })}` +
    `${docxCell('اسم الطالب', { header: true, w: 4600 })}` +
    `${docxCell('الحالة', { header: true, w: 1500, align: 'center' })}` +
    `${docxCell('ملاحظة', { header: true, w: 2500 })}</w:tr>`
  const body = rows.map((r) =>
    `<w:tr>${docxCell(String(r.n), { w: 600, align: 'center' })}` +
    `${docxCell(r.name, { w: 4600 })}` +
    `${docxCell(STATUS_AR[r.status], { w: 1500, align: 'center', color: STATUS_COLOR[r.status] })}` +
    `${docxCell(r.note || '', { w: 2500 })}</w:tr>`).join('')

  const doc = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>
${docxPara('كشف الحضور والغياب', 32, true)}
${docxPara(`${meta.className}${meta.subject ? ' — ' + meta.subject : ''} · ${meta.periodLabel}`, 22)}
${docxPara(`${meta.dayName} ${meta.date} · المعلّم: ${meta.teacher}${meta.school ? ' · ' + meta.school : ''}`, 20)}
<w:p/>
<w:tbl>
  <w:tblPr><w:bidiVisual/><w:tblW w:w="9200" w:type="dxa"/>
    <w:tblBorders>
      <w:top w:val="single" w:sz="6" w:color="DDDDDD"/><w:left w:val="single" w:sz="6" w:color="DDDDDD"/>
      <w:bottom w:val="single" w:sz="6" w:color="DDDDDD"/><w:right w:val="single" w:sz="6" w:color="DDDDDD"/>
      <w:insideH w:val="single" w:sz="6" w:color="DDDDDD"/><w:insideV w:val="single" w:sz="6" w:color="DDDDDD"/>
    </w:tblBorders>
  </w:tblPr>
  <w:tblGrid><w:gridCol w:w="600"/><w:gridCol w:w="4600"/><w:gridCol w:w="1500"/><w:gridCol w:w="2500"/></w:tblGrid>
  ${header}${body}
</w:tbl>
<w:p/>
${docxPara(`حاضر: ${t.present} · متأخّر: ${t.late} · غائب: ${t.absent} · معذور: ${t.excused} · الإجمالي: ${rows.length}`, 22, true)}
<w:p/>
${docxPara('توقيع المعلّم: ..............................          الاعتماد: ..............................', 20)}
<w:sectPr><w:bidi/><w:pgSz w:w="11906" w:h="16838"/>
  <w:pgMar w:top="1000" w:right="900" w:bottom="1000" w:left="900"/></w:sectPr>
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

function col(i: number): string {
  let s = ''
  let n = i
  while (n >= 0) { s = String.fromCharCode(65 + (n % 26)) + s; n = Math.floor(n / 26) - 1 }
  return s
}

function xlsxBytes(meta: SheetMeta, rows: SheetRow[]): Uint8Array {
  const t = tally(rows)
  // كلّ النصوص inline فلا نحتاج sharedStrings
  const grid: (string | number)[][] = [
    ['كشف الحضور والغياب'],
    [`${meta.className}${meta.subject ? ' — ' + meta.subject : ''}`, meta.periodLabel, `${meta.dayName} ${meta.date}`, `المعلّم: ${meta.teacher}`],
    [],
    ['م', 'اسم الطالب', 'الحالة', 'ملاحظة'],
    ...rows.map((r) => [r.n, r.name, STATUS_AR[r.status], r.note || '']),
    [],
    ['حاضر', t.present, 'متأخّر', t.late],
    ['غائب', t.absent, 'معذور', t.excused],
    ['الإجمالي', rows.length],
  ]

  const sheetRows = grid.map((cells, ri) => {
    const r = ri + 1
    const cs = cells.map((v, ci) => {
      const ref = `${col(ci)}${r}`
      const isHeader = r === 4
      const style = r === 1 ? ' s="2"' : isHeader ? ' s="1"' : ''
      if (typeof v === 'number') return `<c r="${ref}"${style}><v>${v}</v></c>`
      if (v === '' || v == null) return ''
      return `<c r="${ref}"${style} t="inlineStr"><is><t xml:space="preserve">${esc(v)}</t></is></c>`
    }).join('')
    return `<row r="${r}">${cs}</row>`
  }).join('')

  const sheet = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetPr><outlinePr/></sheetPr>
<sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews>
<cols><col min="1" max="1" width="5"/><col min="2" max="2" width="34"/>
<col min="3" max="3" width="12"/><col min="4" max="4" width="26"/></cols>
<sheetData>${sheetRows}</sheetData>
</worksheet>`

  const styles = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="3">
  <font><sz val="11"/><name val="Arial"/></font>
  <font><b/><sz val="11"/><color rgb="FF241C63"/><name val="Arial"/></font>
  <font><b/><sz val="15"/><color rgb="FF241C63"/><name val="Arial"/></font>
</fonts>
<fills count="3"><fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFECE9FC"/><bgColor indexed="64"/></patternFill></fill>
</fills>
<borders count="2"><border/>
  <border><left style="thin"><color rgb="FFCFC9EE"/></left><right style="thin"><color rgb="FFCFC9EE"/></right>
  <top style="thin"><color rgb="FFCFC9EE"/></top><bottom style="thin"><color rgb="FFCFC9EE"/></bottom></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="3">
  <xf xfId="0" numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  <xf xfId="0" numFmtId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" readingOrder="2"/></xf>
  <xf xfId="0" numFmtId="0" fontId="2" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment readingOrder="2"/></xf>
</cellXfs>
<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
<dxfs count="0"/>
<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>
</styleSheet>`

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
<sheets><sheet name="الحضور" sheetId="1" r:id="rId1"/></sheets></workbook>` },
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

const MIME: Record<ExportFormat, string> = {
  pdf: 'application/pdf',
  docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
}

/** يُنتج الملفّ ويفتح ورقة المشاركة — ويعيد مساره. */
export async function exportSheet(
  format: ExportFormat, meta: SheetMeta, rows: SheetRow[],
): Promise<string> {
  if (!rows.length) throw new Error('لا طلّاب في هذه الحصّة')
  const base = `حضور_${safeName(meta.className)}_${meta.date}`
  let uri: string

  if (format === 'pdf') {
    const { uri: tmp } = await Print.printToFileAsync({ html: sheetHtml(meta, rows), base64: false })
    uri = `${FileSystem.cacheDirectory}${base}.pdf`
    await FileSystem.moveAsync({ from: tmp, to: uri })
  } else {
    const bytes = format === 'docx' ? docxBytes(meta, rows) : xlsxBytes(meta, rows)
    uri = `${FileSystem.cacheDirectory}${base}.${format}`
    await FileSystem.writeAsStringAsync(uri, toBase64(bytes), {
      encoding: FileSystem.EncodingType.Base64,
    })
  }

  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(uri, {
      mimeType: MIME[format],
      dialogTitle: 'كشف الحضور',
      UTI: format === 'pdf' ? 'com.adobe.pdf'
        : format === 'docx' ? 'org.openxmlformats.wordprocessingml.document'
        : 'org.openxmlformats.spreadsheetml.sheet',
    })
  }
  return uri
}
