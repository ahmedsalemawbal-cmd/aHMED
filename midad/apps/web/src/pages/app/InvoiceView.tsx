import React, { useEffect } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { supabase } from '../../lib/supabase'
import type { Invoice } from '../../lib/types'
import { fmtDate, fmtMoney, fmtNum } from '../../lib/format'
import { Button, Card, EmptyState, ErrorState, Skeleton } from '../../ui/kit'
import { IcBack, IcInvoice, IcPrint } from '../../ui/icons'
import InvoiceStatusBadge from './InvoiceStatus'

function periodLabel(months: number): string {
  if (months === 12) return 'سنة كاملة'
  if (months === 1) return 'شهر'
  if (months === 2) return 'شهران'
  if (months >= 3 && months <= 10) return `${months} أشهر`
  return `${months} شهرًا`
}

export default function InvoiceView() {
  const { id } = useParams()
  const nav = useNavigate()
  const [params] = useSearchParams()
  const { subscriber, profile, plans, general, payment } = useApp()

  const { data, loading, error, reload } = useAsync(async () => {
    if (!id) return null
    const { data, error } = await supabase.from('invoices').select('*').eq('id', id).maybeSingle()
    if (error) throw new Error(error.message)
    return (data as Invoice) || null
  }, [id])

  const wantsPrint = params.get('print') === '1'
  useEffect(() => {
    if (!wantsPrint || loading || !data) return
    const t = setTimeout(() => window.print(), 400)
    return () => clearTimeout(t)
  }, [wantsPrint, loading, data])

  if (loading) {
    return (
      <>
        <div className="mdd-row mdd-noprint" style={{ gap: 10, marginBlockEnd: 'var(--mdd-s-4)' }}>
          <Skeleton h={40} w={110} /><Skeleton h={40} w={110} />
        </div>
        <Card><Skeleton h={420} /></Card>
      </>
    )
  }
  if (error) return <ErrorState onRetry={reload} message={error} />
  if (!data) {
    return (
      <EmptyState
        art={<IcInvoice size={62} />}
        title="لم نجد هذه الفاتورة"
        line="ربّما حُذف الرابط أو لا تخصّ حسابك — ارجع إلى قائمة فواتيرك."
        action={<Button variant="primary" onClick={() => nav('/app/invoices')}>كلّ الفواتير</Button>}
      />
    )
  }

  const inv = data
  const paid = inv.status === 'paid'
  const plan = plans.find((p) => p.id === inv.plan_id) || null
  const rawRate = Number(inv.tax_rate || 0)
  const ratePct = rawRate > 1 ? rawRate : rawRate * 100
  const desc = inv.description_ar || (plan ? `اشتراك ${plan.name_ar}` : 'اشتراك مِداد')
  const duration = plan ? periodLabel(plan.period_months) : '—'

  return (
    <>
      <div className="mdd-row mdd-row--between mdd-row--wrap mdd-noprint" style={{ gap: 'var(--mdd-s-3)', marginBlockEnd: 'var(--mdd-s-4)' }}>
        <div className="mdd-row" style={{ gap: 10 }}>
          <Button auto variant="secondary" icon={<IcBack size={15} />} onClick={() => nav('/app/invoices')}>رجوع</Button>
          <InvoiceStatusBadge status={inv.status} />
        </div>
        <Button auto variant="primary" icon={<IcPrint size={15} />} onClick={() => window.print()}>طباعة</Button>
      </div>

      <div className="mdd-paper-shell">
        <div className="mdd-paper">
          <div className="mdd-watermark" aria-hidden="true">
            <span style={{
              color: paid ? 'var(--mdd-success-fg)' : 'var(--mdd-danger-fg)',
              opacity: .32, border: '5px solid currentColor', borderRadius: 14, padding: '4px 26px',
            }}>
              {paid ? 'مدفوعة' : 'غير مدفوعة'}
            </span>
          </div>

          <div className="mdd-paper__head">
            <div className="mdd-paper__head-col" style={{ textAlign: 'start' }}>
              <strong>{general.platform_name}</strong>
              {payment.tax_number && (
                <span>الرقم الضريبيّ: <span className="mdd-num">{payment.tax_number}</span></span>
              )}
              <span>الرياض — المملكة العربية السعودية</span>
              <span className="mdd-mono" style={{ fontSize: '8.5pt' }}>{general.email}</span>
            </div>
            <div className="mdd-paper__logo-ph">{general.platform_name}</div>
          </div>

          <div className="mdd-paper__title">فاتورة ضريبية مبسّطة</div>

          <div className="mdd-paper__body">
            <table style={{ marginBlockEnd: '6mm' }}>
              <tbody>
                <tr>
                  <th style={{ width: '25%' }}>رقم الفاتورة</th>
                  <td className="mdd-mono">{inv.number}</td>
                  <th style={{ width: '25%' }}>تاريخ الإصدار</th>
                  <td>{fmtDate(inv.issued_at)}</td>
                </tr>
              </tbody>
            </table>

            <h2>بيانات العميل</h2>
            <table style={{ marginBlockEnd: '6mm' }}>
              <tbody>
                <tr>
                  <th style={{ width: '25%' }}>المشترك</th>
                  <td>{subscriber?.name || profile?.full_name || '—'}</td>
                  <th style={{ width: '25%' }}>الجوّال</th>
                  <td className="mdd-num">{subscriber?.contact_phone || profile?.phone || '—'}</td>
                </tr>
                <tr>
                  <th>البريد</th>
                  <td className="mdd-mono">{profile?.email || general.email}</td>
                  <th>المدينة</th>
                  <td>{subscriber?.city || '—'}</td>
                </tr>
              </tbody>
            </table>

            <h2>بنود الفاتورة</h2>
            <table>
              <thead>
                <tr>
                  <th>الوصف</th>
                  <th style={{ width: '18%' }}>المدّة</th>
                  <th style={{ width: '16%' }}>السعر</th>
                  <th style={{ width: '16%' }}>الضريبة</th>
                  <th style={{ width: '18%' }}>الإجمالي</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>{desc}</td>
                  <td>{duration}</td>
                  <td className="mdd-num">{fmtMoney(inv.amount_sar)}</td>
                  <td className="mdd-num">{fmtMoney(inv.tax_amount)}</td>
                  <td className="mdd-num">{fmtMoney(inv.total_sar)}</td>
                </tr>
              </tbody>
            </table>

            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBlockStart: '6mm' }}>
              <table style={{ width: '62%' }}>
                <tbody>
                  <tr>
                    <th style={{ width: '55%' }}>المجموع قبل الضريبة</th>
                    <td className="mdd-num">{fmtMoney(inv.amount_sar)}</td>
                  </tr>
                  <tr>
                    <th>ضريبة القيمة المضافة <span className="mdd-num">{fmtNum(Math.round(ratePct))}%</span></th>
                    <td className="mdd-num">{fmtMoney(inv.tax_amount)}</td>
                  </tr>
                  <tr>
                    <th style={{ fontSize: '12pt' }}>الإجمالي المستحقّ</th>
                    <td className="mdd-num" style={{ fontSize: '13pt', fontWeight: 700 }}>{fmtMoney(inv.total_sar)}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            {inv.status === 'rejected' && inv.rejected_reason && (
              <p style={{ marginBlockStart: '6mm', fontSize: '9.5pt' }}>
                <strong>سبب الرفض:</strong> {inv.rejected_reason}
              </p>
            )}

            <p style={{ marginBlockStart: '8mm', fontSize: '9pt', opacity: .75 }}>
              طريقة الدفع: تحويل بنكيّ إلى {payment.beneficiary || general.platform_name}
              {payment.bank ? ` — ${payment.bank}` : ''}.
              {' '}تاريخ السداد: {paid && inv.paid_at ? fmtDate(inv.paid_at) : 'لم يُسدَّد بعد'}.
            </p>
          </div>

          <div className="mdd-paper__foot">
            <span>
              {paid ? `سُدّدت في ${fmtDate(inv.paid_at)}` : inv.submitted_at ? 'أُرسل إشعار التحويل — قيد المراجعة' : 'بانتظار السداد'}
            </span>
            <span className="mdd-mono">{inv.number}</span>
          </div>
        </div>
      </div>

      <div className="mdd-row mdd-noprint" style={{ gap: 10, justifyContent: 'center', marginBlockStart: 'var(--mdd-s-5)' }}>
        <Button auto variant="secondary" icon={<IcPrint size={15} />} onClick={() => window.print()}>طباعة الفاتورة</Button>
        {inv.status === 'unpaid' && (
          <Button auto variant="primary" onClick={() => nav(`/app/checkout/${inv.id}`)}>أكمل الدفع</Button>
        )}
      </div>
    </>
  )
}
