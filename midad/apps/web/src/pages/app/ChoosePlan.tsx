import React, { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import type { Plan } from '../../lib/types'
import { fmtMoney, fmtNum } from '../../lib/format'
import { Alert, Badge, Button, Card, EmptyState, PageHead, PriceWas } from '../../ui/kit'
import { IcCheck, IcClose, IcLibrary } from '../../ui/icons'

function periodLabel(months: number): string {
  if (months === 12) return 'سنة كاملة'
  if (months === 1) return 'شهر'
  if (months === 2) return 'شهران'
  if (months >= 3 && months <= 10) return `${months} أشهر`
  return `${months} شهرًا`
}

export default function ChoosePlan() {
  const { subscriber, plan, plans, roles, access } = useApp()
  const nav = useNavigate()

  const list = useMemo(() => {
    const type = subscriber?.account_type
    const mine = plans.filter((p) => p.account_type === type)
    return mine.length ? mine : plans
  }, [plans, subscriber?.account_type])

  const currentId = plan?.id || null

  const catNames = (p: Plan) =>
    p.template_categories?.length
      ? p.template_categories.map((k) => roles.find((r) => r.key === k)?.name_ar || k).join(' · ')
      : 'كلّ الفئات'

  if (!list.length) {
    return (
      <>
        <PageHead title="الباقات" />
        <EmptyState
          art={<IcLibrary size={62} />}
          title="لا باقات متاحة الآن"
          line="نُحدّث قائمة الباقات — تواصل معنا وسنرشّح لك ما يناسب حسابك."
          action={<Button variant="primary" onClick={() => nav('/contact')}>تواصل معنا</Button>}
        />
      </>
    )
  }

  const rows: { label: string; render: (p: Plan) => React.ReactNode }[] = [
    { label: 'السعر', render: (p) => <strong className="mdd-num">{fmtMoney(p.price_sar)}</strong> },
    { label: 'المدّة', render: (p) => periodLabel(p.period_months) },
    { label: 'المقاعد', render: (p) => <span className="mdd-num">{fmtNum(p.seats)}</span> },
    { label: 'فئات القوالب', render: (p) => catNames(p) },
    {
      label: 'جداول نور',
      render: (p) => p.noor_enabled
        ? <span className="mdd-row" style={{ gap: 6, color: 'var(--mdd-success-fg)' }}><IcCheck size={15} /> مفتوحة</span>
        : <span className="mdd-row" style={{ gap: 6, color: 'var(--mdd-text-3)' }}><IcClose size={15} /> غير متاحة</span>,
    },
    { label: 'حصّة التحسين الشهرية', render: (p) => <span className="mdd-num">{fmtNum(p.ai_quota_monthly)}</span> },
  ]

  return (
    <>
      <PageHead
        title="اختر باقتك"
        sub={currentId ? 'باقتك الحالية مميّزة أدناه — قارن ثمّ اختر.' : 'قارن الباقتين واختر ما يناسب حسابك.'}
      />

      <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start', marginBlockEnd: 'var(--mdd-s-6)' }}>
        {list.map((p) => {
          const isCurrent = p.id === currentId
          return (
            <Card key={p.id}
              className={'mdd-col mdd-card--pad-lg' + (isCurrent ? ' mdd-card--selected' : '')}
              style={{ gap: 'var(--mdd-s-4)' }}>
              <div className="mdd-row mdd-row--between">
                <div>
                  <h2 style={{ fontSize: 19 }}>{p.name_ar}</h2>
                  <p style={{ fontSize: 12.5, color: 'var(--mdd-text-3)', marginBlockStart: 5 }}>
                    {p.account_type === 'school' ? 'حساب مدرسة' : 'حساب معلّم'} · {periodLabel(p.period_months)}
                  </p>
                </div>
                {isCurrent && <Badge tone="accent" dot>باقتك</Badge>}
              </div>

              <div className="mdd-row" style={{ gap: 8, alignItems: 'baseline' }}>
                <PriceWas plan={p} />
                        <span className="mdd-num" style={{ fontSize: 30, fontWeight: 700, color: 'var(--mdd-accent)' }}>
                  {fmtMoney(p.price_sar)}
                </span>
                <span style={{ fontSize: 13, color: 'var(--mdd-text-3)' }}>/ {periodLabel(p.period_months)}</span>
              </div>

              <ul className="mdd-col" style={{ gap: 9, listStyle: 'none', margin: 0, padding: 0, minHeight: 96 }}>
                {(p.features_ar || []).map((f, i) => (
                  <li key={i} className="mdd-row" style={{ gap: 9, alignItems: 'flex-start' }}>
                    <span style={{ color: 'var(--mdd-accent)', flex: 'none', marginBlockStart: 2 }}><IcCheck size={15} /></span>
                    <span style={{ fontSize: 13.5, color: 'var(--mdd-text-2)' }}>{f}</span>
                  </li>
                ))}
              </ul>

              <Button
                variant={isCurrent ? 'secondary' : 'primary'}
                size="lg" block
                onClick={() => nav(`/app/checkout?plan=${p.id}`)}>
                {isCurrent ? (access === 'expired' ? 'جدّد هذه الباقة' : 'جدّد باقتك') : 'اختر هذه الباقة'}
              </Button>
            </Card>
          )
        })}
      </div>

      <Card className="mdd-col" style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
        <h2 className="mdd-card__title">جدول المقارنة</h2>
        <div className="mdd-table-wrap">
          <table className="mdd-table">
            <thead>
              <tr>
                <th>المقارنة</th>
                {list.map((p) => (
                  <th key={p.id}>
                    <span className="mdd-row" style={{ gap: 8 }}>
                      {p.name_ar}
                      {p.id === currentId && <Badge tone="accent">باقتك</Badge>}
                    </span>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.label}>
                  <td style={{ fontWeight: 600, color: 'var(--mdd-text-2)', whiteSpace: 'nowrap' }}>{r.label}</td>
                  {list.map((p) => (
                    <td key={p.id} style={p.id === currentId ? { background: 'var(--mdd-accent-soft)' } : undefined}>
                      {r.render(p)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Alert tone="info">
        <strong style={{ display: 'block', marginBlockEnd: 4 }}>عن الترقية</strong>
        <span style={{ fontSize: 13 }}>
          الترقية تفتح مزايا الباقة الجديدة فورًا بعد تأكيد السداد، وتبدأ مدّةٌ جديدة من يوم التفعيل.
          ملفّاتك وجداولك تبقى كما هي — لا شيء يُحذف عند تغيير الباقة.
        </span>
      </Alert>
    </>
  )
}
