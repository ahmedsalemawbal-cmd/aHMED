import React, { useMemo } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fetchDocuments, fetchNoorTables } from '../../lib/data'
import { fmtBoth, fmtMoney, fmtNum } from '../../lib/format'
import { Badge, Button, Card, Skeleton, PriceWas } from '../../ui/kit'
import { IcCheck, IcClock, IcLogo, IcLogout } from '../../ui/icons'

export default function Paywall() {
  const { subscriber, plan, plans, access, general, signOut } = useApp()
  const nav = useNavigate()
  const sid = subscriber?.id

  const { data, loading } = useAsync(async () => {
    if (!sid) return { docs: 0, noor: 0 }
    const [docs, noor] = await Promise.all([fetchDocuments(sid), fetchNoorTables(sid)])
    return { docs: docs.length, noor: noor.length }
  }, [sid])

  /** الباقة المقترحة: باقته الحالية إن وُجدت، وإلّا الافتراضية لنوع حسابه. */
  const suggested = useMemo(() => {
    const type = subscriber?.account_type
    const mine = plans.filter((p) => p.account_type === type)
    return plan
      || mine.find((p) => p.is_default)
      || mine[0]
      || plans.find((p) => p.is_default)
      || plans[0]
      || null
  }, [plans, plan, subscriber?.account_type])

  if (access !== 'expired') return <Navigate to="/app" replace />

  /** انتهى اشتراك مدفوع؟ أم انتهت التجربة فقط؟ */
  const wasPaid = subscriber?.status === 'expired'
  const endedAt = wasPaid ? null : subscriber?.trial_ends_at

  const docs = data?.docs ?? 0
  const noor = data?.noor ?? 0

  const monthsLabel = suggested
    ? suggested.period_months === 12 ? 'سنة كاملة' : `${suggested.period_months} أشهر`
    : ''

  return (
    <div style={{ minHeight: '100vh', background: 'var(--mdd-bg)', display: 'flex', flexDirection: 'column' }}>
      <header className="mdd-row" style={{ padding: '18px 20px', gap: 10 }}>
        <span className="mdd-row" style={{ gap: 9, color: 'var(--mdd-accent)' }}>
          <IcLogo size={28} />
          <span style={{ fontSize: 17, fontWeight: 700, color: 'var(--mdd-text)' }}>{general.platform_name}</span>
        </span>
        <div className="mdd-spacer" />
        <Button size="sm" auto variant="secondary" icon={<IcLogout size={15} />}
          onClick={async () => { await signOut(); nav('/') }}>خروج</Button>
      </header>

      <main style={{
        flex: 1, width: '100%', maxWidth: 640, marginInline: 'auto',
        padding: '12px 20px 56px', display: 'flex', flexDirection: 'column', gap: 'var(--mdd-s-6)',
      }}>
        <div style={{ textAlign: 'center' }}>
          <span style={{
            width: 74, height: 74, borderRadius: 22, display: 'inline-grid', placeItems: 'center',
            background: 'var(--mdd-warn-soft)', color: 'var(--mdd-warn-fg)', marginBlockEnd: 'var(--mdd-s-4)',
          }}>
            <IcClock size={38} />
          </span>
          <h1 style={{ fontSize: 26, letterSpacing: '-.3px' }}>
            {wasPaid ? 'انتهى اشتراكك' : 'انتهت تجربتك المجانية'}
          </h1>
          <p className="mdd-prose" style={{ fontSize: 13.5, marginBlockStart: 8, marginInline: 'auto' }}>
            {wasPaid
              ? 'شاشات المنتج مُقفلة حتى تجديد الاشتراك — لا شيء ضاع، كلّ ما بنيتَه في مكانه.'
              : <>انتهت في {endedAt ? fmtBoth(endedAt) : '—'} — شاشات المنتج مُقفلة حتى تشترك.</>}
          </p>
        </div>

        <Card className="mdd-col" style={{ gap: 10 }}>
          <h2 className="mdd-card__title">ما أنجزتَه</h2>
          {loading ? (
            <Skeleton h={18} w="80%" />
          ) : (
            <p className="mdd-prose" style={{ fontSize: 14, maxWidth: 'none' }}>
              {docs === 0 && noor === 0 ? (
                <>لم تبدأ ملفًّا في تجربتك — اشترك اليوم وابدأ أوّل ملفّ في دقائق.</>
              ) : docs > 0 && noor > 0 ? (
                <>أنشأت <strong><span className="mdd-num">{fmtNum(docs)}</span></strong> ملفًّا ونزّلت{' '}
                  <strong><span className="mdd-num">{fmtNum(noor)}</span></strong> جدولًا — كلّها محفوظة وتعود إليك فور اشتراكك.</>
              ) : docs > 0 ? (
                <>أنشأت <strong><span className="mdd-num">{fmtNum(docs)}</span></strong> ملفًّا — كلّها محفوظة وتعود إليك فور اشتراكك.</>
              ) : (
                <>نزّلت <strong><span className="mdd-num">{fmtNum(noor)}</span></strong> جدولًا — كلّها محفوظة وتعود إليك فور اشتراكك.</>
              )}
            </p>
          )}
        </Card>

        {suggested && (
          <Card className="mdd-col mdd-card--raised mdd-card--pad-lg" style={{ gap: 'var(--mdd-s-4)', borderColor: 'var(--mdd-accent)' }}>
            <div className="mdd-row mdd-row--between">
              <div>
                <span className="mdd-eyebrow">الباقة المناسبة لك</span>
                <h2 style={{ fontSize: 20, marginBlockStart: 10 }}>{suggested.name_ar}</h2>
              </div>
              <Badge tone="accent">{suggested.account_type === 'school' ? 'للمدارس' : 'للمعلّمين'}</Badge>
            </div>

            <div className="mdd-row" style={{ gap: 8, alignItems: 'baseline' }}>
              <PriceWas plan={suggested} />
                        <span style={{ fontSize: 32, fontWeight: 700, color: 'var(--mdd-accent)' }} className="mdd-num">
                {fmtMoney(suggested.price_sar)}
              </span>
              <span style={{ fontSize: 13, color: 'var(--mdd-text-3)' }}>/ {monthsLabel}</span>
            </div>

            <ul className="mdd-col" style={{ gap: 9, listStyle: 'none', margin: 0, padding: 0 }}>
              {(suggested.features_ar || []).map((f, i) => (
                <li key={i} className="mdd-row" style={{ gap: 9, alignItems: 'flex-start' }}>
                  <span style={{ color: 'var(--mdd-accent)', flex: 'none', marginBlockStart: 2 }}><IcCheck size={15} /></span>
                  <span style={{ fontSize: 13.5, color: 'var(--mdd-text-2)' }}>{f}</span>
                </li>
              ))}
            </ul>

            <Button variant="primary" size="lg" block
              onClick={() => nav(`/app/checkout?plan=${suggested.id}`)}>
              {wasPaid ? 'جدّد اشتراكك' : 'اشترك الآن'}
            </Button>
          </Card>
        )}

        <nav className="mdd-row mdd-row--wrap" style={{ gap: 6, justifyContent: 'center', fontSize: 12.5 }}>
          <Link to="/app/plans" style={{ color: 'var(--mdd-accent)', fontWeight: 600 }}>كلّ الباقات</Link>
          <span style={{ color: 'var(--mdd-text-3)' }}>·</span>
          <Link to="/app/invoices" style={{ color: 'var(--mdd-accent)', fontWeight: 600 }}>فواتيري</Link>
          <span style={{ color: 'var(--mdd-text-3)' }}>·</span>
          <Link to="/contact" style={{ color: 'var(--mdd-accent)', fontWeight: 600 }}>تواصل معنا</Link>
          <span style={{ color: 'var(--mdd-text-3)' }}>·</span>
          <button className="mdd-btn mdd-btn--ghost mdd-btn--sm mdd-btn--auto"
            onClick={async () => { await signOut(); nav('/') }}>خروج</button>
        </nav>
      </main>
    </div>
  )
}
