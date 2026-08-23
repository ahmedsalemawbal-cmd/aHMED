const greg = new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
const hijri = new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })

export function fmtDate(v?: string | Date | null): string {
  if (!v) return '—'
  const d = v instanceof Date ? v : new Date(v)
  if (isNaN(d.getTime())) return '—'
  return greg.format(d)
}
export function fmtHijri(v?: string | Date | null): string {
  if (!v) return '—'
  const d = v instanceof Date ? v : new Date(v)
  if (isNaN(d.getTime())) return '—'
  try { return hijri.format(d) } catch { return fmtDate(d) }
}
export function fmtBoth(v?: string | Date | null): string {
  if (!v) return '—'
  return `${fmtHijri(v)} · ${fmtDate(v)}`
}
export function fmtRelative(v?: string | Date | null): string {
  if (!v) return '—'
  const d = v instanceof Date ? v : new Date(v)
  if (isNaN(d.getTime())) return '—'
  const min = Math.round((Date.now() - d.getTime()) / 60000)
  if (min < 1) return 'قبل لحظات'
  if (min < 60) return `قبل ${min} دقيقة`
  const h = Math.round(min / 60)
  if (h < 24) return `قبل ${h} ساعة`
  const days = Math.round(h / 24)
  if (days < 30) return `قبل ${days} يومًا`
  return `قبل ${Math.round(days / 30)} شهرًا`
}
export function fmtMoney(n?: number | null): string {
  const v = Number(n || 0)
  return `${v.toLocaleString('en-US')} ر.س`
}
export function fmtNum(n?: number | null): string {
  return Number(n || 0).toLocaleString('en-US')
}
export function daysLabel(n: number): string {
  if (n === 1) return 'يوم واحد'
  if (n === 2) return 'يومان'
  if (n >= 3 && n <= 10) return `${n} أيّام`
  return `${n} يومًا`
}
export function greeting(): string {
  const h = new Date().getHours()
  if (h < 12) return 'صباح الخير'
  if (h < 17) return 'طاب يومك'
  return 'مساء الخير'
}
export function initials(name: string): string {
  const p = (name || '').trim().split(/\s+/).filter(Boolean)
  if (!p.length) return '؟'
  if (p.length === 1) return p[0].slice(0, 2)
  return (p[0][0] || '') + (p[1][0] || '')
}
