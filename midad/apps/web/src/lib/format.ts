const AR = 'ar-SA'

const gregFmt = new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
const hijriFmt = new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
const shortFmt = new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', { day: '2-digit', month: '2-digit', year: 'numeric' })

export function toDate(v: string | Date | null | undefined): Date | null {
  if (!v) return null
  const d = v instanceof Date ? v : new Date(v)
  return isNaN(d.getTime()) ? null : d
}
export function fmtDate(v: string | Date | null | undefined): string {
  const d = toDate(v); if (!d) return '—'
  return gregFmt.format(d)
}
export function fmtShort(v: string | Date | null | undefined): string {
  const d = toDate(v); if (!d) return '—'
  return shortFmt.format(d)
}
export function fmtHijri(v: string | Date | null | undefined): string {
  const d = toDate(v); if (!d) return '—'
  try { return hijriFmt.format(d) } catch { return fmtDate(d) }
}
/** «14 رجب 1447 · 4 يناير 2026» */
export function fmtBoth(v: string | Date | null | undefined): string {
  const d = toDate(v); if (!d) return '—'
  return `${fmtHijri(d)} · ${fmtDate(d)}`
}
export function fmtRelative(v: string | Date | null | undefined): string {
  const d = toDate(v); if (!d) return '—'
  const diff = Date.now() - d.getTime()
  const min = Math.round(diff / 60000)
  if (min < 1) return 'قبل لحظات'
  if (min < 60) return `قبل ${min} دقيقة`
  const h = Math.round(min / 60)
  if (h < 24) return `قبل ${h} ساعة`
  const days = Math.round(h / 24)
  if (days < 30) return `قبل ${days} يومًا`
  const months = Math.round(days / 30)
  if (months < 12) return `قبل ${months} شهرًا`
  return `قبل ${Math.round(months / 12)} سنة`
}
export function fmtMoney(n: number | null | undefined): string {
  const v = Number(n || 0)
  return `${v.toLocaleString('en-US', { minimumFractionDigits: v % 1 === 0 ? 0 : 2, maximumFractionDigits: 2 })} ر.س`
}
export function fmtNum(n: number | null | undefined): string {
  return Number(n || 0).toLocaleString('en-US')
}
export function daysBetween(from: Date | string, to: Date | string): number {
  const a = toDate(from)!, b = toDate(to)!
  return Math.ceil((b.getTime() - a.getTime()) / 86400000)
}
export function initials(name: string): string {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '؟'
  if (parts.length === 1) return parts[0].slice(0, 2)
  return (parts[0][0] || '') + (parts[1][0] || '')
}
export function pluralAr(n: number, one: string, two: string, few: string, many: string): string {
  if (n === 1) return one
  if (n === 2) return two
  if (n >= 3 && n <= 10) return few
  return many
}
export function daysLabel(n: number): string {
  return `${n} ${pluralAr(n, 'يوم', 'يومان', 'أيّام', 'يومًا')}`
}
export function greeting(): string {
  const h = new Date().getHours()
  if (h < 12) return 'صباح الخير'
  if (h < 17) return 'طاب يومك'
  return 'مساء الخير'
}

/**
 * العدد المعدود في العربيّة: الواحد مفرد، والاثنان مثنّى، ومن ثلاثةٍ إلى
 * عشرةٍ جمع، وما فوقها مفردٌ منصوب. «٧ مجلّدات» لا «٧ مجلّدًا».
 *
 * تُمرَّر الصور الأربع صراحةً، فلا اشتقاق آليّ يخطئ في الجمع العربيّ.
 */
export function counted(
  n: number,
  forms: { one: string; two: string; few: string; many: string },
): string {
  const k = Math.abs(Math.trunc(n))
  if (k === 0) return `لا ${forms.many}`
  if (k === 1) return forms.one
  if (k === 2) return forms.two
  if (k <= 10) return `${k} ${forms.few}`
  return `${k} ${forms.many}`
}

export const TPL = { one: 'قالبٌ واحد', two: 'قالبان', few: 'قوالب', many: 'قالبًا' }
export const FLD = { one: 'مجلّدٌ واحد', two: 'مجلّدان', few: 'مجلّدات', many: 'مجلّدًا' }
export const FILE = { one: 'ملفٌّ واحد', two: 'ملفّان', few: 'ملفّات', many: 'ملفًّا' }
