import React from 'react'
import { Badge } from '../../ui/kit'
import type { InvoiceStatus as Status } from '../../lib/types'

export const INVOICE_STATUS: Record<Status, { label: string; tone: 'neutral' | 'success' | 'warn' | 'danger' | 'info' }> = {
  unpaid: { label: 'غير مدفوعة', tone: 'danger' },
  under_review: { label: 'قيد المراجعة', tone: 'warn' },
  paid: { label: 'مدفوعة', tone: 'success' },
  rejected: { label: 'مرفوضة', tone: 'danger' },
  cancelled: { label: 'ملغاة', tone: 'neutral' },
}

export function invoiceStatusLabel(s: Status | string): string {
  return INVOICE_STATUS[s as Status]?.label || 'غير معروفة'
}

export default function InvoiceStatusBadge({ status }: { status: Status | string }) {
  const meta = INVOICE_STATUS[status as Status] || { label: 'غير معروفة', tone: 'neutral' as const }
  return <Badge tone={meta.tone} dot>{meta.label}</Badge>
}
