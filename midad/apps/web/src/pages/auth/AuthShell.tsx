import React from 'react'
import { Link } from 'react-router-dom'
import { IcLogo } from '../../ui/icons'
import { useApp } from '../../lib/store'

export default function AuthShell({ children, aside, wide }: {
  children: React.ReactNode; aside?: React.ReactNode; wide?: boolean
}) {
  const { general } = useApp()
  return (
    <div style={{ minHeight: '100vh', display: 'flex', background: 'var(--mdd-bg)' }}>
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', padding: '32px 20px 48px', minWidth: 0 }}>
        <Link to="/" className="mdd-row" style={{ gap: 10, color: 'var(--mdd-accent)', marginBlockEnd: 28 }}>
          <IcLogo size={34} />
          <span style={{ fontSize: 19, fontWeight: 700, color: 'var(--mdd-text)' }}>مِداد</span>
        </Link>
        <div style={{ width: '100%', maxWidth: wide ? 720 : 420 }}>{children}</div>
        <p style={{ marginBlockStart: 'auto', paddingBlockStart: 32, fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
          <Link to="/terms">الشروط</Link> · <Link to="/privacy">الخصوصية</Link> · <Link to="/contact">تواصل معنا</Link>
        </p>
      </div>
      <aside className="mdd-auth-aside" aria-hidden="true">
        <div style={{ maxWidth: 380 }}>
          {/* الشعار فوق النصّ — يظهر في كلّ صفحات هذا الباب */}
          <div className="mdd-row mdd-enter mdd-enter--fade" style={{ gap: 12, marginBlockEnd: 34, color: '#fff' }}>
            <IcLogo size={46} />
            <span style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.35 }}>
              <span style={{ fontSize: 24, fontWeight: 700 }}>مِـداد</span>
              <span style={{ fontSize: 12.5, color: 'oklch(1 0 0 / .66)' }}>منصّة الملفّات المدرسية والجداول</span>
            </span>
          </div>
          {aside || (
            <>
              <h2 className="mdd-enter mdd-d1" style={{ fontSize: 26, lineHeight: 1.5, color: '#fff' }}>
                ملفّاتك المدرسية تُملأ في الشاشة، وتخرج جاهزةً للطباعة.
              </h2>
              <p style={{ marginBlockStart: 14, fontSize: 14.5, lineHeight: 1.9, color: 'oklch(1 0 0 / .78)' }}>
                {general.tagline} — تُملأ مرّةً، وتبقى في حسابك، وتفتحها العام القادم فتُعدّل التاريخ وتطبع.
              </p>
            </>
          )}
        </div>
      </aside>
    </div>
  )
}
