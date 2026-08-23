import React, { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fetchInvoices } from '../../lib/data'
import type { InvoiceStatus as Status } from '../../lib/types'
import { fmtMoney, fmtShort } from '../../lib/format'
import {
  Button, Card, EmptyState, ErrorState, Field, PageHead, Select, SkeletonRows,
} from '../../ui/kit'
import { IcEye, IcInvoice, IcPrint } from '../../ui/icons'
import InvoiceStatusBadge, { INVOICE_STATUS } from './InvoiceStatus'

export default function Invoices() {
  const { subscriber, plans, access } = useApp()
  const nav = useNavigate()
  const sid = subscriber?.id

  const [year, setYear] = useState('all')
  const [status, setStatus] = useState<'all' | Status>('all')

  const { data, loading, error, reload } = useAsync(async () => {
    if (!sid) return []
    return fetchInvoices(sid)
  }, [sid])

  const invoices = data || []

  const years = useMemo(() => {
    const set = new Set<string>()
    for (const inv of invoices) {
      const d = new Date(inv.issued_at)
      if (!isNaN(d.getTime())) set.add(String(d.getFullYear()))
    }
    return Array.from(set).sort().reverse()
  }, [invoices])

  const filtered = useMemo(() => invoices.filter((inv) => {
    if (status !== 'all' && inv.status !== status) return false
    if (year !== 'all') {
      const d = new Date(inv.issued_at)
      if (isNaN(d.getTime()) || String(d.getFullYear()) !== year) return false
    }
    return true
  }), [invoices, year, status])

  const unpaidCount = invoices.filter((i) => i.status === 'unpaid').length

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="الفواتير"
        sub={loading
          ? 'جارٍ التحميل…'
          : invoices.length
            ? `${invoices.length} فاتورة${unpaidCount ? ` · ${unpaidCount} غير مدفوعة` : ''}`
            : 'كلّ فواتير حسابك تُحفظ هنا'}
        actions={<Button auto variant="secondary" onClick={() => nav('/app/subscription')}>حالة الاشتراك</Button>}
      />

      {invoices.length > 0 && (
        <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)', marginBlockEnd: 'var(--mdd-s-5)', alignItems: 'flex-end' }}>
          <Field label="السنة" style={{ minWidth: 160 }}>
            <Select value={year} onChange={(e) => setYear(e.target.value)}>
              <option value="all">كلّ السنوات</option>
              {years.map((y) => <option key={y} value={y}>{y}</option>)}
            </Select>
          </Field>
          <Field label="الحالة" style={{ minWidth: 180 }}>
            <Select value={status} onChange={(e) => setStatus(e.target.value as any)}>
              <option value="all">كلّ الحالات</option>
              {(Object.keys(INVOICE_STATUS) as Status[]).map((k) => (
                <option key={k} value={k}>{INVOICE_STATUS[k].label}</option>
              ))}
            </Select>
          </Field>
        </div>
      )}

      {loading ? (
        <SkeletonRows n={5} />
      ) : invoices.length === 0 ? (
        <EmptyState
          art={<IcInvoice size={62} />}
          title="لا فواتير بعد"
          line={access === 'trial'
            ? 'أنت في التجربة المجانية — تُنشأ أوّل فاتورة لحظة اشتراكك، وتبقى هنا للطباعة متى شئت.'
            : 'لم تُصدَر فاتورةٌ على حسابك حتى الآن.'}
          action={<Button variant="primary" onClick={() => nav('/app/plans')}>تصفّح الباقات</Button>}
        />
      ) : filtered.length === 0 ? (
        <EmptyState
          art={<IcInvoice size={62} />}
          title="لا فاتورة بهذه الفلاتر"
          line="جرّب سنةً أخرى أو امسح فلتر الحالة."
          action={<Button variant="primary" onClick={() => { setYear('all'); setStatus('all') }}>امسح الفلاتر</Button>}
        />
      ) : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead>
              <tr>
                <th>رقم الفاتورة</th>
                <th>التاريخ</th>
                <th>الوصف</th>
                <th>المبلغ</th>
                <th>الحالة</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {filtered.map((inv) => {
                const unpaid = inv.status === 'unpaid'
                const planName = plans.find((p) => p.id === inv.plan_id)?.name_ar
                return (
                  <tr key={inv.id} style={unpaid ? { background: 'var(--mdd-warn-soft)' } : undefined}>
                    <td data-label="رقم الفاتورة">
                      <span className="mdd-mono" style={{ fontWeight: 700, fontSize: 12.5 }}>{inv.number}</span>
                    </td>
                    <td data-label="التاريخ">
                      <span className="mdd-num" style={{ color: 'var(--mdd-text-2)' }}>{fmtShort(inv.issued_at)}</span>
                    </td>
                    <td data-label="الوصف">{inv.description_ar || planName || 'اشتراك مِداد'}</td>
                    <td data-label="المبلغ">
                      <span className="mdd-num" style={{ fontWeight: 700 }}>{fmtMoney(inv.total_sar)}</span>
                    </td>
                    <td data-label="الحالة"><InvoiceStatusBadge status={inv.status} /></td>
                    <td data-label="إجراءات">
                      <div className="mdd-row mdd-row--wrap" style={{ gap: 8, justifyContent: 'flex-end' }}>
                        {unpaid && (
                          <Button size="sm" auto variant="primary"
                            onClick={() => nav(`/app/checkout/${inv.id}`)}>أكمل الدفع</Button>
                        )}
                        <Button size="sm" auto variant="secondary" icon={<IcEye size={14} />}
                          onClick={() => nav(`/app/invoice/${inv.id}`)}>عرض</Button>
                        <Button size="sm" auto variant="secondary" icon={<IcPrint size={14} />}
                          onClick={() => nav(`/app/invoice/${inv.id}?print=1`)}>طباعة</Button>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      {!loading && unpaidCount > 0 && (
        <Card className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)', marginBlockStart: 'var(--mdd-s-5)' }}>
          <span style={{ fontSize: 13.5 }}>
            لديك <strong className="mdd-num">{unpaidCount}</strong> فاتورة غير مدفوعة — أكمل السداد ليبقى اشتراكك ساريًا.
          </span>
          <Button auto variant="primary" onClick={() => setStatus('unpaid')}>أظهر غير المدفوعة</Button>
        </Card>
      )}
    </>
  )
}
