import Constants from 'expo-constants'

const extra = (Constants.expoConfig?.extra ?? {}) as Record<string, string>

export const SUPABASE_URL = extra.supabaseUrl || 'https://ehimyixcqnmnwgbqrdmr.supabase.co'
export const SUPABASE_KEY = extra.supabaseKey || 'sb_publishable_R5HC6lAC5-VVaQTmzRQdmA_g5S9NFDr'
export const WEB_APP_URL = `${SUPABASE_URL}/functions/v1/app`

/**
 * موقعُ مِداد على المتصفّح — حيث المحرّرُ الكامل.
 *
 * والتطبيق يحيل إليه فيما يعجز عنه: الألوانُ والجداولُ وتنسيقُ الخطّ.
 * فيُكتب النطاقُ في مكانٍ واحدٍ لا يُنسخ في كلّ شاشةٍ تحيل إليه.
 */
export const SITE_URL = extra.siteUrl || 'https://ahmedawbal.com'

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
  return `p${normalizePhone(raw)}@users.midad.sa`
}
