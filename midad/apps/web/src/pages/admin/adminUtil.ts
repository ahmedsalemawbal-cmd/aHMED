import type { Subscriber, SubscriberStatus } from '../../lib/types'

export const STATUS_AR: Record<string, string> = {
  trial: 'تجربة', active: 'ساري', expired: 'منتهٍ', suspended: 'موقوف',
}
export const STATUS_TONE: Record<string, 'neutral' | 'success' | 'warn' | 'danger' | 'info' | 'accent'> = {
  trial: 'info', active: 'success', expired: 'danger', suspended: 'neutral',
}
export const INVOICE_AR: Record<string, string> = {
  unpaid: 'غير مدفوعة', under_review: 'قيد المراجعة', paid: 'مدفوعة',
  rejected: 'مرفوضة', cancelled: 'ملغاة',
}
export const INVOICE_TONE: Record<string, 'neutral' | 'success' | 'warn' | 'danger' | 'info'> = {
  unpaid: 'warn', under_review: 'info', paid: 'success', rejected: 'danger', cancelled: 'neutral',
}
export const ACCOUNT_AR: Record<string, string> = { school: 'مدرسة', teacher: 'معلّم' }

/** الحالة المعروضة — التجربة تنتهي بمرور الوقت لا بتغيّر العمود. */
export function effectiveStatus(s: Pick<Subscriber, 'status' | 'trial_ends_at'>): SubscriberStatus {
  if (s.status === 'suspended') return 'suspended'
  if (s.status === 'active') return 'active'
  if (s.status === 'expired') return 'expired'
  return new Date(s.trial_ends_at).getTime() > Date.now() ? 'trial' : 'expired'
}

export function monthsBetweenNow(iso: string): number {
  return Math.ceil((new Date(iso).getTime() - Date.now()) / 86400000)
}
