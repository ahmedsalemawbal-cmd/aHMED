import type { Template, TemplateField } from './types'

function esc(s: any): string {
  return String(s ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } as any)[c])
}

export function fieldSections(fields: TemplateField[]): { name: string; fields: TemplateField[] }[] {
  const out: { name: string; fields: TemplateField[] }[] = []
  for (const f of fields || []) {
    const name = f.section || 'البيانات'
    let sec = out.find((s) => s.name === name)
    if (!sec) { sec = { name, fields: [] }; out.push(sec) }
    sec.fields.push(f)
  }
  return out
}

export function isFilled(f: TemplateField, v: any): boolean {
  if (f.type === 'table') return Array.isArray(v) && v.some((r) => Object.values(r || {}).some((c) => String(c ?? '').trim() !== ''))
  return String(v ?? '').trim() !== ''
}
export function filledCount(fields: TemplateField[], data: Record<string, any>): number {
  return (fields || []).filter((f) => isFilled(f, data?.[f.key])).length
}
export function emptyRow(cols: { key: string }[]): Record<string, string> {
  const r: Record<string, string> = {}
  for (const c of cols || []) r[c.key] = ''
  return r
}

function renderValue(f: TemplateField | undefined, raw: any): string {
  const v = String(raw ?? '').trim()
  if (!v) return `<span class="v empty">${esc(f?.label || '—')}</span>`
  if (f?.type === 'date') {
    const d = new Date(v)
    if (!isNaN(d.getTime())) {
      return `<span class="v">${esc(new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn',
        { day: 'numeric', month: 'long', year: 'numeric' }).format(d))}</span>`
    }
  }
  return `<span class="v">${esc(v).replace(/\n/g, '<br>')}</span>`
}

function renderTable(f: TemplateField | undefined, raw: any): string {
  const cols = f?.columns || []
  if (!cols.length) return ''
  const rows: any[] = Array.isArray(raw) ? raw : []
  const body = rows.length
    ? rows.map((r, i) => `<tr><td>${i + 1}</td>${cols.map((c) => `<td>${esc(r?.[c.key] ?? '')}</td>`).join('')}</tr>`).join('')
    : Array.from({ length: 3 }).map((_, i) => `<tr><td>${i + 1}</td>${cols.map(() => '<td>&nbsp;</td>').join('')}</tr>`).join('')
  return `<table><thead><tr><th style="width:8%">م</th>${cols.map((c) => `<th>${esc(c.label)}</th>`).join('')}</tr></thead><tbody>${body}</tbody></table>`
}

export function renderBody(tpl: Pick<Template, 'body' | 'fields'>, data: Record<string, any>): string {
  const byKey = new Map((tpl.fields || []).map((f) => [f.key, f]))
  let html = tpl.body || ''
  html = html.replace(/\{\{\s*table:\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_m, k) => renderTable(byKey.get(k), data?.[k]))
  html = html.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_m, k) => renderValue(byKey.get(k), data?.[k]))
  return html
}

export interface PaperMeta {
  title: string; schoolName?: string | null; educationDept?: string | null
  academicYear?: string | null; semester?: string | null; watermark?: string | null
}

/** صفحة A4 كاملة للطباعة وحفظ PDF — تُستعمل مع expo-print. */
export function paperHtml(tpl: Pick<Template, 'body' | 'fields'>, data: Record<string, any>, meta: PaperMeta): string {
  const dateLine = new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura-nu-latn',
    { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date())
  return `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
@page { size: A4; margin: 15mm; }
* { box-sizing: border-box; }
body { font-family: -apple-system, "Segoe UI", Roboto, "Noto Naskh Arabic", sans-serif;
       font-size: 11pt; line-height: 1.85; color: #111; margin: 0; }
.head { display: flex; justify-content: space-between; align-items: center; gap: 10mm;
        border-bottom: 2px solid #1c1c1c; padding-bottom: 5mm; margin-bottom: 7mm; font-size: 10pt; }
.head div { text-align: center; }
.head strong { display: block; }
h1.title { text-align: center; font-size: 16pt; margin: 0 0 7mm; }
h2 { font-size: 13pt; margin: 6mm 0 3mm; padding-bottom: 2mm; border-bottom: 1px solid #ddd; }
h3 { font-size: 11.5pt; margin: 5mm 0 2mm; }
p { margin: 0 0 3mm; }
ul { margin: 0 0 3mm; padding-inline-start: 6mm; }
table { width: 100%; border-collapse: collapse; margin: 3mm 0 5mm; font-size: 10pt; }
th, td { border: 1px solid #999; padding: 2.2mm 2.5mm; text-align: start; }
th { background: #f1f1f1; font-weight: 700; }
.v { font-weight: 600; }
.v.empty { color: #b3b3b3; font-weight: 400; }
.sign { display: flex; justify-content: space-between; gap: 10mm; margin-top: 12mm; font-size: 10pt; }
.sign > div { flex: 1; text-align: center; }
.sign > div::after { content: ""; display: block; border-bottom: 1px solid #666; margin-top: 12mm; }
.mdd-sign-row { display: flex; justify-content: space-between; gap: 10mm; margin-top: 12mm; font-size: 10pt; }
.mdd-sign-row > div { flex: 1; text-align: center; }
.mdd-sign-row > div::after { content: ""; display: block; border-bottom: 1px solid #666; margin-top: 12mm; }
.wm { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center;
      pointer-events: none; z-index: 9; }
.wm span { transform: rotate(-32deg); font-size: 42pt; font-weight: 700;
           color: rgba(31,122,77,.12); white-space: nowrap; }
.foot { margin-top: 10mm; padding-top: 3mm; border-top: 1px solid #ddd;
        display: flex; justify-content: space-between; font-size: 8.5pt; color: #777; }
</style></head><body>
${meta.watermark ? `<div class="wm"><span>${esc(meta.watermark)}</span></div>` : ''}
<div class="head">
  <div><strong>المملكة العربية السعودية</strong><span>وزارة التعليم</span>${meta.educationDept ? `<span>${esc(meta.educationDept)}</span>` : ''}</div>
  <div><strong>${esc(meta.schoolName || 'اسم المدرسة')}</strong>${meta.academicYear ? `<span>العام الدراسي ${esc(meta.academicYear)}</span>` : ''}${meta.semester ? `<span>${esc(meta.semester)}</span>` : ''}</div>
</div>
<h1 class="title">${esc(meta.title)}</h1>
${renderBody(tpl, data)}
<div class="foot"><span>${esc(dateLine)}</span><span>مِداد</span></div>
</body></html>`
}
