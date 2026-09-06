export const SUPABASE_URL =
  (import.meta as any).env?.VITE_SUPABASE_URL || 'https://ehimyixcqnmnwgbqrdmr.supabase.co'
export const SUPABASE_KEY =
  (import.meta as any).env?.VITE_SUPABASE_KEY || 'sb_publishable_R5HC6lAC5-VVaQTmzRQdmA_g5S9NFDr'

/** الجوّال هو اسم الدخول — ويُحوّل إلى بريد داخلي ثابت لا يُعرض للمستخدم أبدًا. */
export const AUTH_EMAIL_DOMAIN = 'users.midad.sa'

export function normalizePhone(raw: string): string {
  const d = (raw || '').replace(/[^\d]/g, '')
  if (d.startsWith('966')) return '0' + d.slice(3)
  if (d.startsWith('00966')) return '0' + d.slice(5)
  if (d.length === 9 && d.startsWith('5')) return '0' + d
  return d
}
export function isValidPhone(raw: string): boolean {
  return /^05\d{8}$/.test(normalizePhone(raw))
}
export function phoneToAuthEmail(raw: string): string {
  return `p${normalizePhone(raw)}@${AUTH_EMAIL_DOMAIN}`
}
