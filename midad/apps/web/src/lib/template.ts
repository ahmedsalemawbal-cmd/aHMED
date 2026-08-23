import type { Template, TemplateField, TemplateColumn } from './types'

export function esc(s: any): string {
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

export function emptyRow(cols: TemplateColumn[]): Record<string, string> {
  const r: Record<string, string> = {}
  for (const c of cols || []) r[c.key] = ''
  return r
}

function renderValue(f: TemplateField | undefined, raw: any): string {
  const v = String(raw ?? '').trim()
  if (!v) return `<span class="mdd-val mdd-val--empty">${esc(f?.label || '—')}</span>`
  if (f?.type === 'date') {
    const d = new Date(v)
    if (!isNaN(d.getTime())) {
      const g = new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' }).format(d)
      return `<span class="mdd-val">${esc(g)}</span>`
    }
  }
  return `<span class="mdd-val">${esc(v).replace(/\n/g, '<br>')}</span>`
}

function renderTable(f: TemplateField | undefined, raw: any): string {
  const cols = f?.columns || []
  if (!cols.length) return ''
  const rows: any[] = Array.isArray(raw) ? raw : []
  const body = rows.length
    ? rows.map((r, i) =>
        `<tr><td>${i + 1}</td>${cols.map((c) => `<td>${esc(r?.[c.key] ?? '')}</td>`).join('')}</tr>`).join('')
    : Array.from({ length: 3 }).map((_, i) =>
        `<tr><td>${i + 1}</td>${cols.map(() => '<td>&nbsp;</td>').join('')}</tr>`).join('')
  return `<table><thead><tr><th style="width:8%">م</th>${cols.map((c) => `<th>${esc(c.label)}</th>`).join('')}</tr></thead><tbody>${body}</tbody></table>`
}

/** يحوّل متن القالب إلى HTML الورقة بقيم الملفّ. */
export function renderBody(tpl: Pick<Template, 'body' | 'fields'>, data: Record<string, any>): string {
  const byKey = new Map((tpl.fields || []).map((f) => [f.key, f]))
  let html = tpl.body || ''
  html = html.replace(/\{\{\s*table:\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_m, key) =>
    renderTable(byKey.get(key), data?.[key]))
  html = html.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_m, key) =>
    renderValue(byKey.get(key), data?.[key]))
  return html
}

/** المفاتيح المستعملة في المتن وليست معرّفة في الحقول — تحذير محرّر القالب. */
export function orphanKeys(body: string, fields: TemplateField[]): string[] {
  const defined = new Set((fields || []).map((f) => f.key))
  const used = new Set<string>()
  for (const m of (body || '').matchAll(/\{\{\s*(?:table:\s*)?([a-zA-Z0-9_]+)\s*\}\}/g)) used.add(m[1])
  return [...used].filter((k) => !defined.has(k))
}
export function unusedFields(body: string, fields: TemplateField[]): string[] {
  const used = new Set<string>()
  for (const m of (body || '').matchAll(/\{\{\s*(?:table:\s*)?([a-zA-Z0-9_]+)\s*\}\}/g)) used.add(m[1])
  return (fields || []).map((f) => f.key).filter((k) => !used.has(k))
}
