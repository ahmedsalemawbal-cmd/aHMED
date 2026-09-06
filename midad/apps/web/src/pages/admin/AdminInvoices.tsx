import React, { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { supabase, callFunction } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fmtMoney, fmtRelative, fmtShort, fmtNum } from '../../lib/format'
import type { Invoice, Subscriber } from '../../lib/types'
import {
  Alert, Badge, Button, Card, EmptyState, ErrorState, Field, Modal, PageHead,
  SkeletonRows, Tabs, Textarea,
} from '../../ui/kit'
import { IcExternal } from '../../ui/icons'
import { INVOICE_AR, INVOICE_TONE } from './adminUtil'

type Tab = 'pending' | 'paid' | 'cancelled' | 'all'

export default function AdminInvoices() {
  const { plans, toast } = useApp()
  const [tab, setTab] = useState<Tab>('pending')
  const [confirmInv, setConfirmInv] = useState<Invoice | null>(null)
  const [rejectInv, setRejectInv] = useState<Invoice | null>(null)
  const [note, setNote] = useState('')
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [flash, setFlash] = useState<string | null>(null)

  const { data, loading, error, reload } = useAsync(async () => {
    const [inv, subs] = await Promise.all([
      supabase.from('invoices').select('*').order('issued_at', { ascending: false }),
      supabase.from('subscribers').select('id,name,account_type'),
    ])
    if (inv.error) throw new Error(inv.error.message)
    const byId = new Map<string, any>((subs.data || []).map((s: any) => [s.id, s]))
    return { invoices: (inv.data || []) as Invoice[], subs: byId }
  }, [])

  const invoices = data?.invoices || []
  const pendingCount = invoices.filter((i) => i.status === 'under_review').length

  const shown = useMemo(() => {
    if (tab === 'pending') return invoices.filter((i) => i.status === 'under_review' || i.status === 'unpaid')
    if (tab === 'paid') return invoices.filter((i) => i.status === 'paid')
    if (tab === 'cancelled') return invoices.filter((i) => i.status === 'cancelled' || i.status === 'rejected')
    return invoices
  }, [invoices, tab])

  const subName = (id: string) => data?.subs.get(id)?.name || '—'
  const planName = (id: string | null) => plans.find((p) => p.id === id)?.name_ar || '—'
  const planMonths = (id: string | null) => plans.find((p) => p.id === id)?.period_months ?? 12

  const projectedEnd = (inv: Invoice) => {
    const d = new Date()
    d.setMonth(d.getMonth() + planMonths(inv.plan_id))
    return d
  }

  const doConfirm = async () => {
    if (!confirmInv) return
    setBusy(true)
    try {
      await callFunction('admin', { action: 'confirm_payment', invoice_id: confirmInv.id, note: note.trim() || null })
      toast('أُكّد الدفع وفُتح الاشتراك')
      setFlash(confirmInv.id); setTimeout(() => setFlash(null), 2200)
      setConfirmInv(null); setNote(''); reload(); setTab('paid')
    } catch (e: any) { toast(e?.message || 'تعذّر التأكيد', 'danger') } finally { setBusy(false) }
  }

  const doReject = async () => {
    if (!rejectInv) return
    if (!reason.trim()) { toast('اكتب سبب الرفض', 'danger'); return }
    setBusy(true)
    try {
      await callFunction('admin', { action: 'reject_payment', invoice_id: rejectInv.id, reason: reason.trim() })
      toast('رُفض التحويل')
      setRejectInv(null); setReason(''); reload()
    } catch (e: any) { toast(e?.message || 'تعذّر الرفض', 'danger') } finally { setBusy(false) }
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="الفواتير وتأكيد الدفع"
        sub={pendingCount ? `${fmtNum(pendingCount)} فاتورة تنتظر إجراءك` : 'لا فواتير تنتظر التأكيد'}
      />

      {pendingCount > 0 && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
          <Alert tone="warn">
            التفعيل يدويّ حتى تصل بوّابة الدفع — كلّ فاتورة هنا مشتركٌ ينتظر أن يُفتح حسابه.
          </Alert>
        </div>
      )}

      <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
        <Tabs
          value={tab} onChange={setTab}
          tabs={[
            { key: 'pending', label: 'تنتظر التأكيد', count: pendingCount },
            { key: 'paid', label: 'مدفوعة', count: invoices.filter((i) => i.status === 'paid').length },
            { key: 'cancelled', label: 'ملغاة ومرفوضة', count: invoices.filter((i) => i.status === 'cancelled' || i.status === 'rejected').length },
            { key: 'all', label: 'الكلّ', count: invoices.length },
          ]}
        />
      </div>

      {loading ? <SkeletonRows n={6} /> : shown.length === 0 ? (
        <EmptyState
          title={tab === 'pending' ? 'لا شيء ينتظر إجراءك' : 'لا فواتير هنا'}
          line={tab === 'pending' ? 'كلّ التحويلات المرسَلة أُكّدت. ستظهر الفواتير الجديدة هنا فور إبلاغ المشترك بالتحويل.' : undefined}
        />
      ) : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead>
              <tr>
                <th>الفاتورة</th><th>المشترك</th><th>المبلغ</th><th>تاريخ الطلب</th>
                <th>الانتظار</th><th>الإيصال</th><th>الحالة</th><th aria-label="أفعال" />
              </tr>
            </thead>
            <tbody>
              {shown.map((inv) => (
                <tr key={inv.id} style={flash === inv.id ? { background: 'var(--mdd-accent-soft)' } : undefined}>
                  <td data-label="الفاتورة"><span className="mdd-mono">{inv.number}</span></td>
                  <td data-label="المشترك">
                    <Link to={`/admin/subscriber/${inv.subscriber_id}`} style={{ fontWeight: 700, color: 'var(--mdd-accent)' }}>
                      {subName(inv.subscriber_id)}
                    </Link>
                    <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{planName(inv.plan_id)}</div>
                  </td>
                  <td data-label="المبلغ">{fmtMoney(inv.total_sar)}</td>
                  <td data-label="تاريخ الطلب">{fmtShort(inv.issued_at)}</td>
                  <td data-label="الانتظار">{inv.submitted_at ? fmtRelative(inv.submitted_at) : '—'}</td>
                  <td data-label="الإيصال">
                    {inv.receipt_url
                      ? <a href={inv.receipt_url} target="_blank" rel="noreferrer"
                          className="mdd-row" style={{ gap: 6, color: 'var(--mdd-accent)', fontWeight: 600, fontSize: 12.5 }}>
                          <IcExternal size={14} /> افتح
                        </a>
                      : <span className="mdd-muted">لا إيصال</span>}
                  </td>
                  <td data-label="الحالة"><Badge tone={INVOICE_TONE[inv.status]} dot>{INVOICE_AR[inv.status]}</Badge></td>
                  <td>
                    {(inv.status === 'under_review' || inv.status === 'unpaid') && (
                      <div className="mdd-row" style={{ gap: 6, justifyContent: 'flex-end' }}>
                        <Button size="sm" auto variant="danger" onClick={() => { setRejectInv(inv); setReason('') }}>رفض</Button>
                        <Button size="sm" auto variant="primary" onClick={() => { setConfirmInv(inv); setNote('') }}>تأكيد الدفع</Button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={!!confirmInv} onClose={() => setConfirmInv(null)} title="تأكيد الدفع" wide
        footer={<>
          <Button variant="secondary" block onClick={() => setConfirmInv(null)}>إلغاء</Button>
          <Button variant="primary" block loading={busy} onClick={doConfirm}>أكّد وافتح الاشتراك</Button>
        </>}>
        {confirmInv && (
          <>
            <Alert tone="warn">
              سيُفتح اشتراك <strong>{subName(confirmInv.subscriber_id)}</strong>{' '}
              {planMonths(confirmInv.plan_id)} شهرًا — حتى <strong>{fmtShort(projectedEnd(confirmInv))}</strong> تقريبًا.
              وإن كان له اشتراكٌ ساري فالمدّة تُضاف إلى نهايته لا من اليوم.
            </Alert>
            <div className="mdd-col" style={{ gap: 0 }}>
              <KV k="الفاتورة" v={<span className="mdd-mono">{confirmInv.number}</span>} />
              <KV k="المشترك" v={subName(confirmInv.subscriber_id)} />
              <KV k="الباقة" v={planName(confirmInv.plan_id)} />
              <KV k="المبلغ" v={fmtMoney(confirmInv.amount_sar)} />
              <KV k="الضريبة" v={fmtMoney(confirmInv.tax_amount)} />
              <KV k="الإجمالي" v={<strong>{fmtMoney(confirmInv.total_sar)}</strong>} />
            </div>
            <Field label="ملاحظة داخلية (اختيارية)" help="لا تظهر للمشترك — تُحفظ في الفاتورة والسجلّ.">
              <Textarea rows={2} value={note} onChange={(e) => setNote(e.target.value)}
                placeholder="رقم عملية التحويل، أو من راجع الإيصال." />
            </Field>
          </>
        )}
      </Modal>

      <Modal open={!!rejectInv} onClose={() => setRejectInv(null)} title="رفض التحويل"
        footer={<>
          <Button variant="secondary" block onClick={() => setRejectInv(null)}>إلغاء</Button>
          <Button variant="danger-solid" block loading={busy} onClick={doReject}>ارفض</Button>
        </>}>
        <Field label="سبب الرفض" help="يصل هذا النصّ إلى المشترك — اكتبه واضحًا وقابلًا للتصرّف.">
          <Textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)}
            placeholder="لم يصل التحويل، أو المبلغ ناقص عن قيمة الفاتورة." />
        </Field>
      </Modal>
    </>
  )
}

function KV({ k, v }: { k: string; v: React.ReactNode }) {
  return (
    <div className="mdd-row mdd-row--between" style={{ paddingBlock: 7, borderBlockEnd: '1px solid var(--mdd-border)' }}>
      <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)', fontWeight: 600 }}>{k}</span>
      <span style={{ fontSize: 13, fontWeight: 600 }}>{v}</span>
    </div>
  )
}
