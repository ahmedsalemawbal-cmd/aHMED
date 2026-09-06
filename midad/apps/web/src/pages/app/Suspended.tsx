import React from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { Alert, Button, Card } from '../../ui/kit'
import { IcLock, IcLogo, IcLogout, IcMail, IcWhatsapp, IcClock } from '../../ui/icons'

export default function Suspended() {
  const { access, subscriber, profile, general, signOut } = useApp()
  const nav = useNavigate()

  if (access !== 'suspended' && access !== 'member_suspended') return <Navigate to="/app" replace />

  const isMember = access === 'member_suspended'
  const wa = (general.whatsapp || '').replace(/\D/g, '')

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
        flex: 1, width: '100%', maxWidth: 560, marginInline: 'auto',
        padding: '12px 20px 56px', display: 'flex', flexDirection: 'column', gap: 'var(--mdd-s-6)',
      }}>
        <div style={{ textAlign: 'center' }}>
          <span style={{
            width: 74, height: 74, borderRadius: 22, display: 'inline-grid', placeItems: 'center',
            background: 'var(--mdd-danger-soft)', color: 'var(--mdd-danger-fg)', marginBlockEnd: 'var(--mdd-s-4)',
          }}>
            <IcLock size={38} />
          </span>
          <h1 style={{ fontSize: 25, letterSpacing: '-.3px' }}>
            {isMember ? 'دخولك موقوف' : 'حسابك موقوف مؤقّتًا'}
          </h1>
          <p className="mdd-prose" style={{ fontSize: 13.5, marginBlockStart: 8, marginInline: 'auto' }}>
            {isMember
              ? 'أوقف مدير حسابك دخولك — ملفّاتك محفوظة، وتعود كما تركتَها فور إعادة تفعيلك.'
              : 'تواصل معنا لمعرفة السبب وإعادة التفعيل — لا شيء حُذف، وبياناتك في مكانها.'}
          </p>
        </div>

        {!isMember && subscriber?.suspended_reason && (
          <Alert tone="warn">
            <strong style={{ display: 'block', marginBlockEnd: 4 }}>سبب الإيقاف</strong>
            <span style={{ fontSize: 13 }}>{subscriber.suspended_reason}</span>
          </Alert>
        )}

        {isMember ? (
          <Card className="mdd-col" style={{ gap: 10 }}>
            <h2 className="mdd-card__title">ماذا تفعل الآن؟</h2>
            <p className="mdd-prose" style={{ fontSize: 13.5 }}>
              راجع مدير حساب <strong>{subscriber?.name || 'مشتركك'}</strong> ليعيد تفعيل عضويّتك من شاشة «الفريق».
              {profile?.full_name ? <> عضويّتك مسجّلة باسم {profile.full_name}.</> : null}
            </p>
          </Card>
        ) : (
          <Card className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
            <h2 className="mdd-card__title">قنوات التواصل</h2>

            <a href={`https://wa.me/${wa}`} target="_blank" rel="noreferrer"
              className="mdd-card mdd-card--action mdd-row" style={{ padding: 14, gap: 12 }}>
              <span style={{
                width: 40, height: 40, borderRadius: 12, display: 'grid', placeItems: 'center',
                background: 'var(--mdd-success-soft)', color: 'var(--mdd-success-fg)', flex: 'none',
              }}><IcWhatsapp size={19} /></span>
              <span style={{ minWidth: 0, flex: 1 }}>
                <span style={{ display: 'block', fontWeight: 700, fontSize: 13.5 }}>واتساب</span>
                <span className="mdd-num" style={{ display: 'block', fontSize: 12.5, color: 'var(--mdd-text-3)' }}>
                  {general.whatsapp}
                </span>
              </span>
            </a>

            <a href={`mailto:${general.email}`}
              className="mdd-card mdd-card--action mdd-row" style={{ padding: 14, gap: 12 }}>
              <span style={{
                width: 40, height: 40, borderRadius: 12, display: 'grid', placeItems: 'center',
                background: 'var(--mdd-info-soft)', color: 'var(--mdd-info-fg)', flex: 'none',
              }}><IcMail size={19} /></span>
              <span style={{ minWidth: 0, flex: 1 }}>
                <span style={{ display: 'block', fontWeight: 700, fontSize: 13.5 }}>البريد</span>
                <span style={{ display: 'block', fontSize: 12.5, color: 'var(--mdd-text-3)', direction: 'ltr', unicodeBidi: 'isolate' }}>
                  {general.email}
                </span>
              </span>
            </a>

            <div className="mdd-row" style={{ gap: 8, fontSize: 12.5, color: 'var(--mdd-text-3)' }}>
              <IcClock size={14} />
              <span>{general.working_hours}</span>
            </div>
          </Card>
        )}

        <div className="mdd-row" style={{ justifyContent: 'center' }}>
          <Button auto variant="secondary" icon={<IcLogout size={15} />}
            onClick={async () => { await signOut(); nav('/') }}>خروج من الحساب</Button>
        </div>
      </main>
    </div>
  )
}
