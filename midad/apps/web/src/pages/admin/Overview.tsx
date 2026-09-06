import React, { useMemo } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { supabase } from '../../lib/supabase'
import { useAsync } from '../../lib/hooks'
import { useApp } from '../../lib/store'
import { fmtBoth, fmtMoney, fmtNum, fmtRelative } from '../../lib/format'
import type { Invoice, Subscriber } from '../../lib/types'
import {
  Badge, Button, Card, EmptyState, ErrorState, PageHead, Skeleton, Stat,
} from '../../ui/kit'
import { IcChevron, IcInvoice, IcSpark, IcTeam } from '../../ui/icons'
import { BarChart, lastMonths, monthKey } from './AdminChart'

const MONTHS = 12

export default function Overview() {
  const { general } = useApp()
  const nav = useNavigate()

  const { data, loading, error, reload } = useAsync(async () => {
    const now = new Date()
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0, 0).toISOString()
    const yearStart = new Date(now.getFullYear(), now.getMonth() - (MONTHS - 1), 1, 0, 0, 0, 0).toISOString()
    const nowIso = now.toISOString()
    const in30 = new Date(now.getTime() + 30 * 86400000).toISOString()

    const [
      subsCount, activeSubsCount, trialCount, paidThisMonth, pendingCount, endingSoon,
      newSubs, revenueRows, latest, pending,
    ] = await Promise.all([
      supabase.from('subscribers').select('id', { count: 'exact', head: true }),
      supabase.from('subscriptions').select('id', { count: 'exact', head: true })
        .eq('status', 'active').gte('ends_at', nowIso),
      supabase.from('subscribers').select('id', { count: 'exact', head: true })
        .eq('status', 'trial').gte('trial_ends_at', nowIso),
      supabase.from('invoices').select('total_sar').eq('status', 'paid').gte('paid_at', monthStart),
      supabase.from('invoices').select('id', { count: 'exact', head: true }).eq('status', 'under_review'),
      supabase.from('subscriptions').select('id', { count: 'exact', head: true })
        .eq('status', 'active').gte('ends_at', nowIso).lte('ends_at', in30),
      supabase.from('subscribers').select('created_at').gte('created_at', yearStart),
      supabase.from('invoices').select('total_sar,paid_at').eq('status', 'paid').gte('paid_at', yearStart),
      supabase.from('subscribers').select('*').order('created_at', { ascending: false }).limit(6),
      supabase.from('invoices').select('*').eq('status', 'under_review')
        .order('submitted_at', { ascending: true }).limit(6),
    ])

    const pendingRows = (pending.data || []) as Invoice[]
    const ids = Array.from(new Set(pendingRows.map((i) => i.subscriber_id).filter(Boolean)))
    const names: Record<string, string> = {}
    if (ids.length) {
      const { data: rows } = await supabase.from('subscribers').select('id,name').in('id', ids)
      for (const r of (rows || []) as any[]) names[r.id] = r.name
    }

    return {
      subscribers: subsCount.count || 0,
      activeSubs: activeSubsCount.count || 0,
      trials: trialCount.count || 0,
      revenue: ((paidThisMonth.data || []) as any[]).reduce((a, r) => a + Number(r.total_sar || 0), 0),
      pendingInvoices: pendingCount.count || 0,
      endingSoon: endingSoon.count || 0,
      newSubs: ((newSubs.data || []) as any[]).map((r) => String(r.created_at)),
      revenueRows: ((revenueRows.data || []) as any[]).map((r) => ({ at: String(r.paid_at), v: Number(r.total_sar || 0) })),
      latest: (latest.data || []) as Subscriber[],
      pending: pendingRows,
      names,
    }
  }, [])

  const months = useMemo(() => lastMonths(MONTHS), [])

  const signupSeries = useMemo(() => {
    const m = new Map<string, number>()
    for (const at of data?.newSubs || []) {
      const k = monthKey(at)
      if (k) m.set(k, (m.get(k) || 0) + 1)
    }
    return months.map((mo) => ({ label: mo.label, value: m.get(mo.key) || 0 }))
  }, [data?.newSubs, months])

  const revenueSeries = useMemo(() => {
    const m = new Map<string, number>()
    for (const r of data?.revenueRows || []) {
      const k = monthKey(r.at)
      if (k) m.set(k, (m.get(k) || 0) + r.v)
    }
    return months.map((mo) => ({ label: mo.label, value: m.get(mo.key) || 0 }))
  }, [data?.revenueRows, months])

  if (error) return <ErrorState onRetry={reload} message={error} />

  const fresh = !!data && data.subscribers === 0 && data.pendingInvoices === 0 && data.revenue === 0

  return (
    <>
      <PageHead
        title="نظرة عامّة"
        sub={fmtBoth(new Date())}
        actions={
          <>
            <Button auto variant="secondary" onClick={() => nav('/admin/subscribers')}>المشتركون</Button>
            <Button auto variant="primary" onClick={() => nav('/admin/invoices')}>الفواتير المنتظِرة</Button>
          </>
        }
      />

      <div className="mdd-grid mdd-grid--3" style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
        {loading ? (
          Array.from({ length: 6 }).map((_, i) => <Card key={i}><Skeleton h={62} /></Card>)
        ) : (
          <>
            <Stat label="المشتركون" value={<span className="mdd-num">{fmtNum(data?.subscribers)}</span>}
              hint="كلّ المدارس والمعلّمين المسجّلين" />
            <Stat label="الاشتراكات السارية" value={<span className="mdd-num">{fmtNum(data?.activeSubs)}</span>}
              hint="اشتراكٌ مدفوعٌ لم ينتهِ بعد" />
            <Stat label="التجارب النشطة" value={<span className="mdd-num">{fmtNum(data?.trials)}</span>}
              hint="لم تنتهِ تجربتهم بعد" />
            <Stat label="إيراد الشهر" value={<span className="mdd-num">{fmtMoney(data?.revenue)}</span>}
              hint="مجموع الفواتير المدفوعة هذا الشهر" />
            <Stat label="فواتير تنتظر التأكيد"
              value={<span className="mdd-num" style={data?.pendingInvoices ? { color: 'var(--mdd-danger-fg)' } : undefined}>
                {fmtNum(data?.pendingInvoices)}
              </span>}
              hint={data?.pendingInvoices ? 'مشتركون ينتظرون فتح اشتراكهم' : 'لا شيء ينتظرك'} />
            <Stat label="تنتهي خلال 30 يومًا" value={<span className="mdd-num">{fmtNum(data?.endingSoon)}</span>}
              hint="ذكّرهم قبل انقطاع الخدمة" />
          </>
        )}
      </div>

      {loading ? (
        <Card><Skeleton h={200} /></Card>
      ) : fresh ? (
        <Card className="mdd-col" style={{ gap: 'var(--mdd-s-4)', alignItems: 'center', textAlign: 'center', padding: 'var(--mdd-s-8)' }}>
          <span style={{
            width: 60, height: 60, borderRadius: 18, display: 'grid', placeItems: 'center',
            background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
          }}><IcSpark size={26} /></span>
          <h2 style={{ fontSize: 20 }}>{general.platform_name || 'مِداد'} جاهزة — ولم يسجّل أحدٌ بعد</h2>
          <p className="mdd-prose" style={{ fontSize: 13.5, margin: '0 auto' }}>
            الأرقام هنا تبدأ من الصفر وتنمو مع أوّل مدرسة. حتى ذلك الحين جهّز ما يراه المشترك:
            راجع الباقات وأسعارها، انشر قوالب المكتبة، واضبط بيانات التحويل البنكيّ في إعدادات المنصّة.
          </p>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 10, justifyContent: 'center' }}>
            <Button auto variant="primary" onClick={() => nav('/admin/plans')}>راجع الباقات</Button>
            <Button auto variant="secondary" onClick={() => nav('/admin/templates')}>انشر قالبًا</Button>
            <Button auto variant="secondary" onClick={() => nav('/admin/settings')}>إعدادات المنصّة</Button>
          </div>
        </Card>
      ) : (
        <>
          <div className="mdd-grid mdd-grid--2" style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
            <Card className="mdd-col">
              <div className="mdd-row mdd-row--between">
                <h2 className="mdd-card__title">المشتركون الجدد</h2>
                <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>آخر <span className="mdd-num">12</span> شهرًا</span>
              </div>
              <BarChart data={signupSeries} label="المشتركون الجدد شهرًا شهرًا" fmt={(n) => fmtNum(n)} />
            </Card>
            <Card className="mdd-col">
              <div className="mdd-row mdd-row--between">
                <h2 className="mdd-card__title">الإيراد</h2>
                <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>آخر <span className="mdd-num">12</span> شهرًا</span>
              </div>
              <BarChart data={revenueSeries} tone="success" label="الإيراد شهرًا شهرًا" fmt={(n) => fmtNum(n)} />
              <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>الأرقام بالريال السعوديّ — من الفواتير المدفوعة وحدها.</span>
            </Card>
          </div>

          <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
            <Card className="mdd-col">
              <div className="mdd-row mdd-row--between">
                <h2 className="mdd-card__title">آخر المسجّلين</h2>
                <Link to="/admin/subscribers" style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--mdd-accent)' }}>عرض الكلّ</Link>
              </div>
              {(data?.latest || []).length === 0 ? (
                <EmptyState art={<IcTeam size={54} />} title="لا مشتركين بعد"
                  line="أوّل تسجيل يظهر هنا لحظة حدوثه." />
              ) : (
                <div className="mdd-col" style={{ gap: 2 }}>
                  {(data?.latest || []).map((s) => (
                    <Link key={s.id} to={`/admin/subscriber/${s.id}`} className="mdd-row"
                      style={{ padding: '11px 10px', borderRadius: 'var(--mdd-r-sm)', gap: 12 }}>
                      <div style={{ minWidth: 0, flex: 1 }}>
                        <div style={{ fontWeight: 600, fontSize: 13.5, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.name}</div>
                        <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
                          {s.account_type === 'school' ? 'مدرسة' : 'معلّم'}
                          {s.city ? ` · ${s.city}` : ''} · {fmtRelative(s.created_at)}
                        </div>
                      </div>
                      <StatusBadge status={s.status} />
                      <IcChevron size={14} />
                    </Link>
                  ))}
                </div>
              )}
            </Card>

            <Card className="mdd-col">
              <div className="mdd-row mdd-row--between">
                <h2 className="mdd-card__title">فواتير تنتظر إجراءً</h2>
                <Link to="/admin/invoices" style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--mdd-accent)' }}>افتح الفواتير</Link>
              </div>
              {(data?.pending || []).length === 0 ? (
                <EmptyState art={<IcInvoice size={54} />} title="لا فاتورة تنتظرك"
                  line="كلّ التحويلات المرفوعة أُكّدت — لا شيء معلّق على المنصّة الآن." />
              ) : (
                <div className="mdd-col" style={{ gap: 2 }}>
                  {(data?.pending || []).map((inv) => (
                    <Link key={inv.id} to="/admin/invoices" className="mdd-row"
                      style={{ padding: '11px 10px', borderRadius: 'var(--mdd-r-sm)', gap: 12 }}>
                      <div style={{ minWidth: 0, flex: 1 }}>
                        <div style={{ fontWeight: 600, fontSize: 13.5, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                          {data?.names[inv.subscriber_id] || 'مشترك'}
                        </div>
                        <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
                          <span className="mdd-mono">{inv.number}</span> · انتظر {fmtRelative(inv.submitted_at || inv.issued_at)}
                        </div>
                      </div>
                      <span className="mdd-num" style={{ fontWeight: 700, fontSize: 13 }}>{fmtMoney(inv.total_sar)}</span>
                      <IcChevron size={14} />
                    </Link>
                  ))}
                </div>
              )}
              {(data?.pending || []).length > 0 && (
                <Button variant="primary" onClick={() => nav('/admin/invoices')}>أكّد الدفعات المنتظِرة</Button>
              )}
            </Card>
          </div>
        </>
      )}
    </>
  )
}

function StatusBadge({ status }: { status: Subscriber['status'] }) {
  if (status === 'active') return <Badge tone="success" dot>ساري</Badge>
  if (status === 'trial') return <Badge tone="info" dot>تجربة</Badge>
  if (status === 'suspended') return <Badge tone="danger" dot>موقوف</Badge>
  return <Badge tone="warn" dot>منتهٍ</Badge>
}

export { StatusBadge }
