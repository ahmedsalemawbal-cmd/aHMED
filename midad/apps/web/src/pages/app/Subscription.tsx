import React from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { supabase } from '../../lib/supabase'
import { aiUsageThisMonth, fetchInvoices, fetchTeam } from '../../lib/data'
import type { Subscription as Sub } from '../../lib/types'
import { daysBetween, daysLabel, fmtBoth, fmtMoney, fmtNum, fmtShort } from '../../lib/format'
import {
  Alert, Badge, Button, Card, EmptyState, ErrorState, PageHead, Progress, Skeleton, SkeletonRows,
} from '../../ui/kit'
import { IcCard, IcCheck, IcChevron, IcInvoice, IcSpark, IcTeam } from '../../ui/icons'
import InvoiceStatusBadge from './InvoiceStatus'

export default function Subscription() {
  const { subscriber, plan, plans, access, trialDays, roles } = useApp()
  const nav = useNavigate()
  const sid = subscriber?.id

  const { data, loading, error, reload } = useAsync(async () => {
    if (!sid) return null
    const [subsRes, team, ai, invoices] = await Promise.all([
      supabase.from('subscriptions').select('*').eq('subscriber_id', sid).order('ends_at', { ascending: false }),
      fetchTeam(sid),
      aiUsageThisMonth(sid),
      fetchInvoices(sid),
    ])
    if (subsRes.error) throw new Error(subsRes.error.message)
    const all = (subsRes.data || []) as Sub[]
    const current = all.find((s) => s.status === 'active') || all[0] || null
    return { current, team, ai, invoices }
  }, [sid])

  if (error) return <ErrorState onRetry={reload} message={error} />

  const isTrial = access === 'trial'
  const isExpired = access === 'expired'
  const current = data?.current || null
  const activePlan = plan || plans.find((p) => p.id === current?.plan_id) || null

  const endsAt = isTrial ? subscriber?.trial_ends_at || null : current?.ends_at || null
  const startsAt = isTrial ? subscriber?.created_at || null : current?.starts_at || null

  const daysLeft = isTrial
    ? trialDays
    : endsAt ? Math.max(0, daysBetween(new Date(), endsAt)) : 0
  const totalDays = startsAt && endsAt ? Math.max(1, daysBetween(startsAt, endsAt)) : 1
  const nearEnd = !isExpired && daysLeft > 0 && daysLeft <= 30

  const seatsUsed = (data?.team || []).filter((m) => m.status === 'active').length
  const seats = activePlan?.seats ?? 0
  const aiQuota = subscriber?.ai_quota_override ?? activePlan?.ai_quota_monthly ?? 0
  const aiUsed = data?.ai ?? 0
  const isSolo = subscriber?.account_type === 'teacher'
  const lastInvoices = (data?.invoices || []).slice(0, 3)

  const statusBadge = isTrial
    ? <Badge tone="info" dot>تجربة مجانية</Badge>
    : isExpired
      ? <Badge tone="danger" dot>منتهٍ</Badge>
      : nearEnd
        ? <Badge tone="warn" dot>يقارب الانتهاء</Badge>
        : <Badge tone="success" dot>ساري</Badge>

  return (
    <>
      <PageHead
        title="الاشتراك"
        sub={activePlan ? `${activePlan.name_ar} · ${subscriber?.name || ''}` : 'حالة اشتراكك والمزايا المفتوحة لك'}
        actions={
          <>
            <Button auto variant="secondary" onClick={() => nav('/app/plans')}>ترقية الباقة</Button>
            <Button auto variant="primary" icon={<IcCard size={16} />}
              onClick={() => nav(activePlan ? `/app/checkout?plan=${activePlan.id}` : '/app/plans')}>
              {isExpired ? 'جدّد الآن' : 'جدّد'}
            </Button>
          </>
        }
      />

      {isExpired && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
          <Alert tone="danger">
            انتهى اشتراكك — شاشات المنتج مُقفلة حتى التجديد، وكلّ ملفّاتك محفوظة تعود إليك فور السداد.
          </Alert>
        </div>
      )}
      {nearEnd && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
          <Alert tone="warn">
            {isTrial
              ? <>تنتهي تجربتك بعد {daysLabel(daysLeft)} — جدّد قبل الموعد حتى لا تُقفل شاشات المنتج.</>
              : <>يقارب اشتراكك الانتهاء — بقي {daysLabel(daysLeft)}. جدّد الآن لتبقى ملفّاتك مفتوحة بلا انقطاع.</>}
          </Alert>
        </div>
      )}

      {loading ? (
        <Card><Skeleton h={150} /></Card>
      ) : (
        <Card className="mdd-col mdd-card--pad-lg" style={{ gap: 'var(--mdd-s-5)', marginBlockEnd: 'var(--mdd-s-5)' }}>
          <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)' }}>
            <div>
              {statusBadge}
              <h2 style={{ fontSize: 21, marginBlockStart: 10 }}>
                {isTrial ? 'التجربة المجانية' : activePlan?.name_ar || 'بلا باقة'}
              </h2>
              {!isTrial && current && (
                <p style={{ fontSize: 12.5, color: 'var(--mdd-text-3)', marginBlockStart: 5 }}>
                  بدأ في {fmtShort(current.starts_at)} · <span className="mdd-num">{fmtMoney(current.amount_sar)}</span>
                </p>
              )}
            </div>
            <div style={{ textAlign: 'start' }}>
              <span style={{ display: 'block', fontSize: 12, color: 'var(--mdd-text-3)' }}>
                {isExpired ? 'انتهى في' : 'ينتهي في'}
              </span>
              <strong style={{ display: 'block', fontSize: 16, marginBlockStart: 4 }}>{fmtBoth(endsAt)}</strong>
            </div>
          </div>

          {!isExpired && (
            <div className="mdd-col" style={{ gap: 8 }}>
              <div className="mdd-row mdd-row--between">
                <span style={{ fontSize: 13, fontWeight: 600 }}>بقي {daysLabel(daysLeft)}</span>
                <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }} className="mdd-num">
                  {fmtNum(Math.max(0, totalDays - daysLeft))} / {fmtNum(totalDays)} يومًا
                </span>
              </div>
              <Progress value={daysLeft} max={totalDays} tone={daysLeft <= 7 ? 'danger' : daysLeft <= 30 ? 'warn' : undefined} />
            </div>
          )}
        </Card>
      )}

      <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start', marginBlockEnd: 'var(--mdd-s-5)' }}>
        <Card className="mdd-col">
          <h2 className="mdd-card__title">ما في باقتك</h2>
          {!activePlan ? (
            <p className="mdd-prose" style={{ fontSize: 13 }}>
              لم تُختَر باقةٌ بعد — تصفّح الباقات واختر ما يناسب حسابك.
            </p>
          ) : (
            <ul className="mdd-col" style={{ gap: 9, listStyle: 'none', margin: 0, padding: 0 }}>
              {(activePlan.features_ar || []).map((f, i) => (
                <li key={i} className="mdd-row" style={{ gap: 9, alignItems: 'flex-start' }}>
                  <span style={{ color: 'var(--mdd-accent)', flex: 'none', marginBlockStart: 2 }}><IcCheck size={15} /></span>
                  <span style={{ fontSize: 13.5, color: 'var(--mdd-text-2)' }}>{f}</span>
                </li>
              ))}
              {!activePlan.features_ar?.length && (
                <li style={{ fontSize: 13, color: 'var(--mdd-text-3)' }}>لم تُسجَّل مزايا لهذه الباقة بعد.</li>
              )}
            </ul>
          )}
          {activePlan && (
            <div className="mdd-row mdd-row--wrap" style={{ gap: 8, paddingBlockStart: 4 }}>
              <Badge tone="neutral">
                القوالب: {activePlan.template_categories?.length
                  ? activePlan.template_categories.map((k) => roles.find((r) => r.key === k)?.name_ar || k).join(' · ')
                  : 'كلّ الفئات'}
              </Badge>
              <Badge tone={activePlan.noor_enabled ? 'success' : 'neutral'}>
                {activePlan.noor_enabled ? 'جداول نور مفتوحة' : 'بلا جداول نور'}
              </Badge>
            </div>
          )}
        </Card>

        <Card className="mdd-col" style={{ gap: 'var(--mdd-s-5)' }}>
          <h2 className="mdd-card__title">الاستعمال</h2>

          {!isSolo && (
            <div className="mdd-col" style={{ gap: 8 }}>
              <div className="mdd-row mdd-row--between">
                <span className="mdd-row" style={{ gap: 8, fontSize: 13, fontWeight: 600 }}>
                  <IcTeam size={16} /> المقاعد المستعملة
                </span>
                <span className="mdd-num" style={{ fontSize: 13, color: 'var(--mdd-text-2)' }}>
                  {fmtNum(seatsUsed)} / {seats ? fmtNum(seats) : '—'}
                </span>
              </div>
              {loading ? <Skeleton h={8} /> : (
                <Progress value={seatsUsed} max={seats || 1} tone={seats && seatsUsed >= seats ? 'danger' : undefined} />
              )}
              <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
                {seats && seatsUsed >= seats
                  ? 'امتلأت مقاعد باقتك — ارفع الباقة لإضافة أعضاء.'
                  : `يمكنك إضافة ${fmtNum(Math.max(0, seats - seatsUsed))} عضوًا آخر.`}
              </span>
            </div>
          )}

          <div className="mdd-col" style={{ gap: 8 }}>
            <div className="mdd-row mdd-row--between">
              <span className="mdd-row" style={{ gap: 8, fontSize: 13, fontWeight: 600 }}>
                <IcSpark size={16} /> حصّة التحسين هذا الشهر
              </span>
              <span className="mdd-num" style={{ fontSize: 13, color: 'var(--mdd-text-2)' }}>
                {fmtNum(aiUsed)} / {aiQuota ? fmtNum(aiQuota) : '—'}
              </span>
            </div>
            {loading ? <Skeleton h={8} /> : (
              <Progress value={aiUsed} max={aiQuota || 1}
                tone={aiQuota && aiUsed >= aiQuota ? 'danger' : aiQuota && aiUsed >= aiQuota * 0.8 ? 'warn' : undefined} />
            )}
            <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
              {aiQuota && aiUsed >= aiQuota
                ? 'استهلكت حصّتك — تتجدّد أوّل الشهر القادم.'
                : `بقي ${fmtNum(Math.max(0, aiQuota - aiUsed))} تحسينًا حتى أوّل الشهر القادم.`}
            </span>
          </div>
        </Card>
      </div>

      <Card className="mdd-col">
        <div className="mdd-row mdd-row--between">
          <h2 className="mdd-card__title">آخر الفواتير</h2>
          {lastInvoices.length > 0 && (
            <Link to="/app/invoices" style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--mdd-accent)' }}>كلّ الفواتير</Link>
          )}
        </div>
        {loading ? (
          <SkeletonRows n={3} />
        ) : lastInvoices.length === 0 ? (
          <EmptyState
            art={<IcInvoice size={58} />}
            title="لا فواتير بعد"
            line={isTrial ? 'أنت في التجربة المجانية — تُنشأ أوّل فاتورة عند اشتراكك.' : 'لم تُصدَر فاتورةٌ على حسابك حتى الآن.'}
            action={<Button variant="primary" onClick={() => nav('/app/plans')}>تصفّح الباقات</Button>}
          />
        ) : (
          <div className="mdd-col" style={{ gap: 2 }}>
            {lastInvoices.map((inv) => (
              <Link key={inv.id} to={`/app/invoice/${inv.id}`} className="mdd-row"
                style={{ padding: '11px 10px', borderRadius: 'var(--mdd-r-sm)', gap: 12 }}>
                <IcInvoice size={17} />
                <div style={{ minWidth: 0, flex: 1 }}>
                  <div style={{ fontWeight: 600, fontSize: 13.5 }} className="mdd-num">{inv.number}</div>
                  <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{fmtShort(inv.issued_at)}</div>
                </div>
                <span className="mdd-num" style={{ fontSize: 13, fontWeight: 700 }}>{fmtMoney(inv.total_sar)}</span>
                <InvoiceStatusBadge status={inv.status} />
                <IcChevron size={14} />
              </Link>
            ))}
          </div>
        )}
      </Card>
    </>
  )
}
