import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { supabase, callFunction } from '../../lib/supabase'
import type { Invoice, Plan } from '../../lib/types'
import { fmtBoth, fmtMoney, fmtNum } from '../../lib/format'
import {
  Alert, Badge, Button, Card, CopyButton, ErrorState, Field, Input, PageHead, Skeleton,
} from '../../ui/kit'
import { IcCard, IcCheck, IcClock, IcLock, IcShield, IcWhatsapp } from '../../ui/icons'

function periodLabel(months: number): string {
  if (months === 12) return 'سنة كاملة'
  if (months === 1) return 'شهر'
  if (months === 2) return 'شهران'
  if (months >= 3 && months <= 10) return `${months} أشهر`
  return `${months} شهرًا`
}

export default function Checkout() {
  const { subscriber, profile, plan, plans, payment, general, toast, refresh } = useApp()
  const nav = useNavigate()
  const [params] = useSearchParams()
  const { invoiceId } = useParams()

  /** حالة واحدة تحكم الشاشة كلّها. */
  const [mode, setMode] = useState<'card' | 'bank'>(payment.payments_enabled ? 'card' : 'bank')

  const [invoice, setInvoice] = useState<Invoice | null>(null)
  const [orderLoading, setOrderLoading] = useState(true)
  const [orderError, setOrderError] = useState<string | null>(null)

  const [receiptUrl, setReceiptUrl] = useState('')
  const [sending, setSending] = useState(false)
  const [sentAt, setSentAt] = useState<string | null>(null)

  const [card, setCard] = useState({ name: '', number: '', expiry: '', cvc: '' })
  const [paying, setPaying] = useState(false)
  const payLock = useRef(false)

  const selectedPlan: Plan | null = useMemo(() => {
    const wanted = params.get('plan')
    if (wanted) {
      const byId = plans.find((p) => p.id === wanted)
      if (byId) return byId
      const byKey = plans.find((p) => p.key === wanted)
      if (byKey) return byKey
    }
    if (plan) return plan
    const mine = plans.filter((p) => p.account_type === subscriber?.account_type)
    return mine.find((p) => p.is_default) || mine[0] || plans[0] || null
  }, [params, plans, plan, subscriber?.account_type])

  /** الفاتورة إمّا محمّلة من الرابط، وإمّا مُنشأة عند فتح الشاشة. */
  const madeFor = useRef<string | null>(null)

  const createOrder = useCallback(async () => {
    if (!selectedPlan) { setOrderLoading(false); return }
    setOrderLoading(true); setOrderError(null)
    try {
      const res = await callFunction<any>('billing', { action: 'create_order', plan_id: selectedPlan.id })
      const inv: Invoice | null = res?.invoice || (res?.id ? res : null)
      if (!inv) throw new Error('لم يرجع الخادم رقم الفاتورة')
      setInvoice(inv)
      if (inv.submitted_at) setSentAt(inv.submitted_at)
    } catch (e: any) {
      setOrderError(e?.message || 'تعذّر إنشاء الفاتورة')
    } finally {
      setOrderLoading(false)
    }
  }, [selectedPlan])

  const loadInvoice = useCallback(async (id: string) => {
    setOrderLoading(true); setOrderError(null)
    try {
      const { data, error } = await supabase.from('invoices').select('*').eq('id', id).maybeSingle()
      if (error) throw new Error(error.message)
      if (!data) throw new Error('لم نجد هذه الفاتورة')
      const inv = data as Invoice
      setInvoice(inv)
      if (inv.submitted_at) setSentAt(inv.submitted_at)
      if (inv.receipt_url) setReceiptUrl(inv.receipt_url)
    } catch (e: any) {
      setOrderError(e?.message || 'تعذّر تحميل الفاتورة')
    } finally {
      setOrderLoading(false)
    }
  }, [])

  useEffect(() => {
    if (invoiceId) {
      if (madeFor.current === invoiceId) return
      madeFor.current = invoiceId
      loadInvoice(invoiceId)
      return
    }
    if (!selectedPlan) return
    if (madeFor.current === selectedPlan.id) return
    madeFor.current = selectedPlan.id
    createOrder()
  }, [invoiceId, selectedPlan, createOrder, loadInvoice])

  /* ---------- المبالغ ---------- */
  const rawRate = Number(payment.tax_rate || 0)
  const rate = rawRate > 1 ? rawRate / 100 : rawRate
  const base = invoice ? Number(invoice.amount_sar) : Number(selectedPlan?.price_sar || 0)
  const taxRate = invoice ? (Number(invoice.tax_rate) > 1 ? Number(invoice.tax_rate) / 100 : Number(invoice.tax_rate)) : rate
  const tax = invoice ? Number(invoice.tax_amount) : (payment.show_tax ? Math.round(base * rate * 100) / 100 : 0)
  const total = invoice ? Number(invoice.total_sar) : Math.round((base + tax) * 100) / 100
  const showTax = payment.show_tax || tax > 0

  const retry = () => { madeFor.current = null; if (invoiceId) loadInvoice(invoiceId); else createOrder() }

  /* ---------- إرسال التحويل ---------- */
  const submitTransfer = async () => {
    if (!invoice) return
    setSending(true)
    try {
      const res = await callFunction<any>('billing', {
        action: 'submit_transfer', invoice_id: invoice.id, receipt_url: receiptUrl.trim() || null,
      })
      const when = res?.invoice?.submitted_at || new Date().toISOString()
      setSentAt(when)
      setInvoice((v) => (v ? { ...v, status: 'under_review', submitted_at: when, receipt_url: receiptUrl.trim() || null } : v))
      toast('استلمنا إشعار التحويل')
      refresh()
    } catch (e: any) {
      toast(e?.message || 'تعذّر إرسال الإشعار — حاول مرّة أخرى', 'danger')
    } finally {
      setSending(false)
    }
  }

  /* ---------- الدفع بالبطاقة ---------- */
  const payByCard = async () => {
    if (payLock.current) return
    if (!card.name.trim() || !card.number.trim() || !card.expiry.trim() || !card.cvc.trim()) {
      toast('أكمل بيانات البطاقة أوّلًا', 'danger'); return
    }
    if (!invoice) { toast('لم تُنشأ الفاتورة بعد', 'danger'); return }
    payLock.current = true
    setPaying(true)
    try {
      await callFunction('billing', { action: 'pay_card', invoice_id: invoice.id })
      toast('تمّ الدفع — فُعّل اشتراكك')
      await refresh()
      nav('/app/subscription')
    } catch (e: any) {
      payLock.current = false
      setPaying(false)
      toast(e?.message || 'تعذّر إتمام الدفع — راجع بيانات البطاقة', 'danger')
    }
  }

  const wa = (general.whatsapp || '').replace(/\D/g, '')

  /* ---------- شاشة «استلمنا طلبك» ---------- */
  if (sentAt && invoice) {
    return (
      <>
        <PageHead title="طلب الاشتراك" />
        <Card className="mdd-col mdd-card--pad-lg" style={{ gap: 'var(--mdd-s-5)', maxWidth: 620, marginInline: 'auto', textAlign: 'center' }}>
          <span style={{
            width: 68, height: 68, borderRadius: 20, display: 'inline-grid', placeItems: 'center',
            background: 'var(--mdd-success-soft)', color: 'var(--mdd-success-fg)', marginInline: 'auto',
          }}><IcCheck size={34} /></span>
          <div>
            <h2 style={{ fontSize: 21 }}>استلمنا طلبك — نراجعه خلال 24 ساعة</h2>
            <p className="mdd-prose" style={{ fontSize: 13.5, marginBlockStart: 8, marginInline: 'auto' }}>
              نتحقّق من التحويل ثمّ نُفعّل اشتراكك ونرسل لك إشعارًا. تبقى ملفّاتك كما هي في الأثناء.
            </p>
          </div>
          <div className="mdd-col" style={{ gap: 10, background: 'var(--mdd-sunken)', borderRadius: 'var(--mdd-r-md)', padding: 'var(--mdd-s-4)' }}>
            <div className="mdd-row mdd-row--between">
              <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>رقم الفاتورة</span>
              <strong className="mdd-num" style={{ fontSize: 13.5 }}>{invoice.number}</strong>
            </div>
            <div className="mdd-row mdd-row--between">
              <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>أُرسل في</span>
              <strong style={{ fontSize: 13 }}>{fmtBoth(sentAt)}</strong>
            </div>
            <div className="mdd-row mdd-row--between">
              <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>المبلغ</span>
              <strong className="mdd-num" style={{ fontSize: 13.5 }}>{fmtMoney(total)}</strong>
            </div>
            <div className="mdd-row mdd-row--between">
              <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>الحالة</span>
              <Badge tone="warn" dot>قيد المراجعة</Badge>
            </div>
          </div>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 10, justifyContent: 'center' }}>
            <a href={`https://wa.me/${wa}`} target="_blank" rel="noreferrer">
              <Button auto variant="primary" icon={<IcWhatsapp size={16} />}>تواصل معنا</Button>
            </a>
            <Button auto variant="secondary" onClick={() => nav(`/app/invoice/${invoice.id}`)}>عرض الفاتورة</Button>
          </div>
        </Card>
      </>
    )
  }

  if (!selectedPlan) {
    return (
      <>
        <PageHead title="طلب اشتراك" />
        <ErrorState message="لم نتعرّف على الباقة المطلوبة — اختر باقةً من شاشة الباقات."
          onRetry={() => nav('/app/plans')} />
      </>
    )
  }

  const summary = (
    <Card className="mdd-col" style={{ gap: 'var(--mdd-s-3)' }}>
      <h2 className="mdd-card__title">ملخّص الطلب</h2>
      <div className="mdd-row mdd-row--between">
        <span style={{ fontSize: 13, color: 'var(--mdd-text-2)' }}>الباقة</span>
        <strong style={{ fontSize: 13.5 }}>{selectedPlan.name_ar}</strong>
      </div>
      <div className="mdd-row mdd-row--between">
        <span style={{ fontSize: 13, color: 'var(--mdd-text-2)' }}>المدّة</span>
        <strong style={{ fontSize: 13.5 }}>{periodLabel(selectedPlan.period_months)}</strong>
      </div>
      <div className="mdd-row mdd-row--between">
        <span style={{ fontSize: 13, color: 'var(--mdd-text-2)' }}>المبلغ</span>
        <span className="mdd-num" style={{ fontSize: 13.5 }}>{fmtMoney(base)}</span>
      </div>
      {showTax && (
        <div className="mdd-row mdd-row--between">
          <span style={{ fontSize: 13, color: 'var(--mdd-text-2)' }}>
            ضريبة القيمة المضافة <span className="mdd-num">{fmtNum(Math.round(taxRate * 100))}%</span>
          </span>
          <span className="mdd-num" style={{ fontSize: 13.5 }}>{fmtMoney(tax)}</span>
        </div>
      )}
      <div className="mdd-row mdd-row--between"
        style={{ borderBlockStart: '1px solid var(--mdd-border)', paddingBlockStart: 'var(--mdd-s-3)' }}>
        <span style={{ fontSize: 14, fontWeight: 700 }}>الإجمالي</span>
        <span className="mdd-num" style={{ fontSize: 22, fontWeight: 700, color: 'var(--mdd-accent)' }}>{fmtMoney(total)}</span>
      </div>
      <p style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
        باسم {subscriber?.name || profile?.full_name || 'حسابك'}
      </p>
    </Card>
  )

  return (
    <>
      <PageHead
        title={mode === 'bank' ? 'التحويل البنكيّ' : 'الدفع بالبطاقة'}
        sub={`اشتراك ${selectedPlan.name_ar} · ${periodLabel(selectedPlan.period_months)}`}
        actions={<Button auto variant="secondary" onClick={() => nav('/app/plans')}>غيّر الباقة</Button>}
      />

      <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
        <div className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>{summary}</div>

        <div className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
          {mode === 'bank' ? (
            <>
              {orderError && (
                <Card>
                  <ErrorState message={`تعذّر إنشاء الفاتورة: ${orderError}`} onRetry={retry} />
                </Card>
              )}

              <Card className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
                <h2 className="mdd-card__title">بيانات التحويل</h2>

                <div className="mdd-col" style={{ gap: 12, background: 'var(--mdd-sunken)', borderRadius: 'var(--mdd-r-md)', padding: 'var(--mdd-s-4)' }}>
                  <Row label="المستفيد" value={payment.beneficiary || '—'} />
                  <Row label="البنك" value={payment.bank || '—'} />

                  <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 8 }}>
                    <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>الآيبان</span>
                    <span className="mdd-row" style={{ gap: 8 }}>
                      <span className="mdd-mono" style={{ fontSize: 13, fontWeight: 600 }}>{payment.iban || '—'}</span>
                      {payment.iban && <CopyButton text={payment.iban} />}
                    </span>
                  </div>

                  <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 8 }}>
                    <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>رقم الفاتورة</span>
                    <span className="mdd-row" style={{ gap: 8 }}>
                      {orderLoading ? <Skeleton h={14} w={120} /> : (
                        <span className="mdd-mono" style={{ fontSize: 13, fontWeight: 600 }}>{invoice?.number || '—'}</span>
                      )}
                      {invoice?.number && <CopyButton text={invoice.number} />}
                    </span>
                  </div>
                </div>

                <Alert tone="info">
                  اكتب رقم الفاتورة في خانة «الغرض» أو «الملاحظات» عند التحويل — بها نطابق حوالتك بحسابك سريعًا.
                </Alert>

                <ol className="mdd-col" style={{ gap: 'var(--mdd-s-3)', listStyle: 'none', margin: 0, padding: 0 }}>
                  <Step n={1} title="حوّل المبلغ" line={`حوّل ${fmtMoney(total)} إلى الآيبان أعلاه من أيّ بنك سعوديّ.`} />
                  <Step n={2} title="أرسل الإيصال" line="ارفع صورة الإيصال في أيّ خدمة وضع رابطها في الحقل أدناه، أو أرسلها لنا على واتساب." />
                  <Step n={3} title="نُفعّل خلال 24 ساعة" line="نراجع التحويل ونفتح باقتك، ويصلك إشعار بالتفعيل." />
                </ol>

                <Field label="رابط الإيصال (اختياري)" help="رابط صورة الإيصال — إن لم يتوفّر أرسله لنا على واتساب.">
                  <Input ltr dir="ltr" type="url" placeholder="https://" value={receiptUrl}
                    onChange={(e) => setReceiptUrl(e.target.value)} />
                </Field>

                <Button variant="primary" size="lg" block loading={sending}
                  disabled={!invoice || orderLoading}
                  onClick={submitTransfer}>
                  أرسلت التحويل
                </Button>

                {payment.payments_enabled && (
                  <button className="mdd-btn mdd-btn--ghost mdd-btn--sm" onClick={() => setMode('card')}>
                    أفضّل الدفع بالبطاقة
                  </button>
                )}
              </Card>
            </>
          ) : (
            <Card className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
              <h2 className="mdd-card__title">بيانات البطاقة</h2>

              <Field label="الاسم على البطاقة">
                <Input value={card.name} placeholder="أحمد سالم الغامدي" autoComplete="cc-name"
                  onChange={(e) => setCard({ ...card, name: e.target.value })} />
              </Field>
              <Field label="رقم البطاقة">
                <Input ltr dir="ltr" inputMode="numeric" autoComplete="cc-number" placeholder="4111 1111 1111 1111"
                  value={card.number} onChange={(e) => setCard({ ...card, number: e.target.value })} />
              </Field>
              <div className="mdd-grid mdd-grid--2">
                <Field label="تاريخ الانتهاء">
                  <Input ltr dir="ltr" inputMode="numeric" autoComplete="cc-exp" placeholder="MM/YY"
                    value={card.expiry} onChange={(e) => setCard({ ...card, expiry: e.target.value })} />
                </Field>
                <Field label="رمز التحقّق CVC">
                  <Input ltr dir="ltr" inputMode="numeric" autoComplete="cc-csc" placeholder="123"
                    value={card.cvc} onChange={(e) => setCard({ ...card, cvc: e.target.value })} />
                </Field>
              </div>

              <div className="mdd-row mdd-row--wrap" style={{ gap: 8 }}>
                <Badge tone="success"><IcShield size={12} /> الدفع عبر ميسر</Badge>
                <Badge tone="info"><IcLock size={12} /> مشفّر</Badge>
                <Badge tone="neutral"><IcCard size={12} /> مدى · فيزا · ماستركارد</Badge>
              </div>

              <Button variant="primary" size="lg" block loading={paying} disabled={paying || orderLoading || !invoice}
                onClick={payByCard}>
                ادفع {fmtMoney(total)}
              </Button>

              <button className="mdd-btn mdd-btn--ghost mdd-btn--sm" onClick={() => setMode('bank')}>
                أفضّل التحويل البنكيّ
              </button>

              <p style={{ fontSize: 11.5, color: 'var(--mdd-text-3)', textAlign: 'center' }}>
                بإتمام الدفع أنت توافق على <Link to="/terms" style={{ color: 'var(--mdd-accent)' }}>شروط الاستعمال</Link>.
              </p>
            </Card>
          )}

          <div className="mdd-row" style={{ gap: 8, fontSize: 12, color: 'var(--mdd-text-3)' }}>
            <IcClock size={14} />
            <span>{general.working_hours}</span>
          </div>
        </div>
      </div>
    </>
  )
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 8 }}>
      <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>{label}</span>
      <strong style={{ fontSize: 13.5 }}>{value}</strong>
    </div>
  )
}

function Step({ n, title, line }: { n: number; title: string; line: string }) {
  return (
    <li className="mdd-row" style={{ gap: 12, alignItems: 'flex-start' }}>
      <span className="mdd-num" style={{
        width: 26, height: 26, borderRadius: 'var(--mdd-r-pill)', flex: 'none',
        display: 'grid', placeItems: 'center', fontSize: 12.5, fontWeight: 700,
        background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
      }}>{n}</span>
      <span style={{ minWidth: 0 }}>
        <span style={{ display: 'block', fontWeight: 700, fontSize: 13.5 }}>{title}</span>
        <span style={{ display: 'block', fontSize: 12.5, color: 'var(--mdd-text-2)', lineHeight: 1.8, marginBlockStart: 2 }}>{line}</span>
      </span>
    </li>
  )
}
