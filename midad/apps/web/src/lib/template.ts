import type {
  Template, TemplateField, TemplateColumn, TemplateFolder, TemplateAudience,
} from './types'

/**
 * أينتمي هذا القالب إلى هذا المجلّد؟
 *
 * وكان الجواب `t.folder_id === f.id` وحده. ثمّ صار للقالب صلاحيّةُ
 * «الكلّ» — وهي لا تسع مجلّدًا واحدًا: القالب يُعرَض في المجلّد العامّ عند
 * المدرسة وعند المعلّم معًا، وهو ملفٌّ واحدٌ لا نسختان. فيُخزَّن بلا
 * مجلّدٍ ويُشتقّ مجلّدُه عند العرض.
 *
 * ويُكتب هنا مرّةً واحدة: تستعمله صفحة المجلّد، وجذرُ المكتبة، وصفحة
 * القالب. ولو تفرّق لاختلف — يُصلَح في موضعٍ ويبقى العطب في اثنين.
 */
export function inFolder(t: Template, f: TemplateFolder): boolean {
  return t.audience === 'all' ? f.is_general : t.folder_id === f.id
}

/**
 * أيرى صاحبُ هذا الدور هذا القالب؟
 *
 * والسياسةُ في القاعدة هي الحارس — هذا ترشيحُ عرضٍ يتبعها، لا يُغني
 * عنها. والفارغ لكلّ الأدوار: فما رُفع قبل أن تُسنَد الأدوار يبقى
 * ظاهرًا لمن كان يراه.
 */
export function forRole(t: Template, roleKey: string | null | undefined): boolean {
  const keys = t.role_keys || []
  return keys.length === 0 || (!!roleKey && keys.includes(roleKey))
}

/** القوالب التي لا مجلّد لها — و«الكلّ» ليس منها: مجلّدُه العامّ. */
export function isLoose(t: Template): boolean {
  return !t.folder_id && t.audience !== 'all'
}

/**
 * ترتيب القوالب كما رتّبها المالك **لهذا الجمهور**.
 *
 * ولكلّ جمهورٍ عموده: أوّليّةُ المدير غير أوّليّة المعلّم، وقالبُ «الكلّ»
 * يظهر في القائمتين بترتيبين مختلفين.
 */
export function byOrder(audience: TemplateAudience | string | null | undefined) {
  const col = audience === 'teacher' ? 'sort_teacher' : 'sort_school'
  return (a: Template, b: Template) =>
    ((a as any)[col] ?? 0) - ((b as any)[col] ?? 0) ||
    a.title.localeCompare(b.title, 'ar')
}

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
