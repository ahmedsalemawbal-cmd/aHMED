import React, { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { Button, IconButton } from '../../ui/kit'
import { IcLogo, IcMenu, IcClose, IcSun, IcMoon, IcWhatsapp, IcMail, IcClock, IcLibrary, IcTable } from '../../ui/icons'

const LINKS = [
  { to: '/service/templates', label: 'القوالب' },
  { to: '/service/noor', label: 'جداول نور' },
  { to: '/pricing', label: 'الأسعار' },
  { to: '/faq', label: 'الأسئلة' },
  { to: '/contact', label: 'تواصل' },
]

/** يتابع عرض الشاشة بلا مكتبات — لأنّ الرأس يتبدّل بين سطح المكتب والجوّال. */
function useNarrow(px = 900) {
  const [narrow, setNarrow] = useState(() => {
    try { return window.matchMedia(`(max-width: ${px}px)`).matches } catch { return false }
  })
  useEffect(() => {
    let mq: MediaQueryList
    try { mq = window.matchMedia(`(max-width: ${px}px)`) } catch { return }
    const on = () => setNarrow(mq.matches)
    on()
    mq.addEventListener?.('change', on)
    return () => mq.removeEventListener?.('change', on)
  }, [px])
  return narrow
}

function useDark(theme: string) {
  const [dark, setDark] = useState(false)
  useEffect(() => {
    const sys = (() => { try { return window.matchMedia('(prefers-color-scheme: dark)') } catch { return null } })()
    const calc = () => setDark(theme === 'dark' || (theme === 'auto' && !!sys?.matches))
    calc()
    sys?.addEventListener?.('change', calc)
    return () => sys?.removeEventListener?.('change', calc)
  }, [theme])
  return dark
}

export default function SiteLayout() {
  const { session, general, theme, setTheme } = useApp()
  const narrow = useNarrow()
  const dark = useDark(theme)
  const [open, setOpen] = useState(false)
  const loc = useLocation()

  useEffect(() => { setOpen(false) }, [loc.pathname])
  useEffect(() => { if (!narrow) setOpen(false) }, [narrow])

  const signedIn = !!session
  const primaryTo = signedIn ? '/app' : '/join'
  const primaryLabel = signedIn ? 'افتح لوحتي' : 'جرّب مجّانًا'

  const themeBtn = (
    <IconButton
      label={dark ? 'الوضع الفاتح' : 'الوضع الداكن'}
      onClick={() => setTheme(dark ? 'light' : 'dark')}
    >
      {dark ? <IcSun size={17} /> : <IcMoon size={17} />}
    </IconButton>
  )

  return (
    <div style={{ minHeight: '100%', display: 'flex', flexDirection: 'column' }}>
      <header className="mdd-site-header">
        <div className="mdd-site-wrap mdd-row mdd-row--between" style={{ height: 64, gap: 'var(--mdd-s-4)' }}>
          <Link to="/" className="mdd-row" style={{ gap: 10, flex: 'none' }} aria-label="مِداد — الصفحة الرئيسية">
            <span style={{ color: 'var(--mdd-accent)', display: 'flex' }}><IcLogo size={30} /></span>
            <span style={{ fontWeight: 700, fontSize: 18 }}>مِداد</span>
          </Link>

          {!narrow && (
            <nav className="mdd-row" style={{ gap: 2 }}>
              {LINKS.map((l) => (
                <NavLink
                  key={l.to}
                  to={l.to}
                  style={({ isActive }) => ({
                    padding: '9px 13px',
                    borderRadius: 'var(--mdd-r-sm)',
                    fontSize: 13.5,
                    fontWeight: isActive ? 700 : 500,
                    color: isActive ? 'var(--mdd-accent)' : 'var(--mdd-text-2)',
                  })}
                >
                  {l.label}
                </NavLink>
              ))}
            </nav>
          )}

          <div className="mdd-row" style={{ gap: 8, flex: 'none' }}>
            {themeBtn}
            {!narrow ? (
              <>
                {!signedIn && (
                  <Link to="/login"><Button auto size="sm" variant="secondary">دخول</Button></Link>
                )}
                <Link to={primaryTo}><Button auto size="sm" variant="primary">{primaryLabel}</Button></Link>
              </>
            ) : (
              <IconButton label={open ? 'إغلاق القائمة' : 'القائمة'} aria-expanded={open} onClick={() => setOpen((v) => !v)}>
                {open ? <IcClose size={17} /> : <IcMenu size={18} />}
              </IconButton>
            )}
          </div>
        </div>

        {narrow && open && (
          <div style={{ borderBlockStart: '1px solid var(--mdd-border)', background: 'var(--mdd-card)' }}>
            <div className="mdd-site-wrap mdd-col" style={{ gap: 2, paddingBlock: 'var(--mdd-s-3) var(--mdd-s-4)' }}>
              {LINKS.map((l) => (
                <NavLink
                  key={l.to}
                  to={l.to}
                  style={({ isActive }) => ({
                    padding: '13px 12px',
                    borderRadius: 'var(--mdd-r-sm)',
                    fontSize: 14.5,
                    fontWeight: isActive ? 700 : 500,
                    color: isActive ? 'var(--mdd-accent)' : 'var(--mdd-text)',
                    background: isActive ? 'var(--mdd-accent-soft)' : 'transparent',
                  })}
                >
                  {l.label}
                </NavLink>
              ))}
              <div className="mdd-col" style={{ gap: 10, marginBlockStart: 'var(--mdd-s-3)' }}>
                {!signedIn && <Link to="/login"><Button block variant="secondary">دخول</Button></Link>}
                <Link to={primaryTo}><Button block variant="primary">{primaryLabel}</Button></Link>
              </div>
            </div>
          </div>
        )}
      </header>

      <main style={{ flex: 1, minWidth: 0 }}>
        <Outlet />
      </main>

      <footer className="mdd-footer">
        <div className="mdd-site-wrap">
          <div className="mdd-grid mdd-grid--4" style={{ gap: 'var(--mdd-s-6)' }}>
            <div className="mdd-col" style={{ gap: 10 }}>
              <div className="mdd-row" style={{ gap: 10 }}>
                <span style={{ color: 'var(--mdd-accent)', display: 'flex' }}><IcLogo size={26} /></span>
                <span style={{ fontWeight: 700, fontSize: 16 }}>مِداد</span>
              </div>
              <p className="mdd-prose" style={{ fontSize: 12.5 }}>
                {general.tagline || 'منصّة الملفّات المدرسية والجداول'} — تُعدّ ملفّاتك المدرسية في المتصفّح،
                وتنزّل جداول نور إلى حسابك، بلا برامج تُثبَّت على الجهاز.
              </p>
            </div>

            <FootCol title="الخدمتان">
              <FootLink to="/service/templates" icon={<IcLibrary size={14} />}>قوالب الملفّات المدرسية</FootLink>
              <FootLink to="/service/noor" icon={<IcTable size={14} />}>جداول نور</FootLink>
            </FootCol>

            <FootCol title="روابط سريعة">
              <FootLink to="/pricing">الأسعار</FootLink>
              <FootLink to="/faq">الأسئلة الشائعة</FootLink>
              <FootLink to="/contact">تواصل معنا</FootLink>
              <FootLink to="/login">تسجيل الدخول</FootLink>
              <FootLink to="/join">إنشاء حساب</FootLink>
            </FootCol>

            <FootCol title="التواصل">
              <a
                className="mdd-row"
                style={{ gap: 8, fontSize: 12.5, color: 'var(--mdd-text-2)' }}
                href={`https://wa.me/${(general.whatsapp || '').replace(/[^\d]/g, '')}`}
                target="_blank"
                rel="noreferrer"
              >
                <IcWhatsapp size={14} />
                <span className="mdd-num">{general.whatsapp}</span>
              </a>
              <a
                className="mdd-row"
                style={{ gap: 8, fontSize: 12.5, color: 'var(--mdd-text-2)' }}
                href={`mailto:${general.email}`}
              >
                <IcMail size={14} />
                <span className="mdd-mono">{general.email}</span>
              </a>
              <span className="mdd-row" style={{ gap: 8, fontSize: 12.5, color: 'var(--mdd-text-3)' }}>
                <IcClock size={14} />
                {general.working_hours}
              </span>
            </FootCol>
          </div>

          <div
            className="mdd-row mdd-row--between mdd-row--wrap"
            style={{
              gap: 'var(--mdd-s-3)',
              marginBlockStart: 'var(--mdd-s-7)',
              paddingBlockStart: 'var(--mdd-s-5)',
              borderBlockStart: '1px solid var(--mdd-border)',
              fontSize: 12,
              color: 'var(--mdd-text-3)',
            }}
          >
            <span>
              © <span className="mdd-num">{new Date().getFullYear()}</span> {general.platform_name || 'مِداد'} — جميع الحقوق محفوظة.
            </span>
            <span className="mdd-row" style={{ gap: 'var(--mdd-s-4)' }}>
              <Link to="/terms" style={{ color: 'var(--mdd-text-2)' }}>الشروط والأحكام</Link>
              <Link to="/privacy" style={{ color: 'var(--mdd-text-2)' }}>سياسة الخصوصية</Link>
            </span>
          </div>
        </div>
      </footer>
    </div>
  )
}

function FootCol({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="mdd-col" style={{ gap: 10 }}>
      <h3 style={{ fontSize: 13 }}>{title}</h3>
      <div className="mdd-col" style={{ gap: 9 }}>{children}</div>
    </div>
  )
}

function FootLink({ to, icon, children }: { to: string; icon?: React.ReactNode; children: React.ReactNode }) {
  return (
    <Link to={to} className="mdd-row" style={{ gap: 8, fontSize: 12.5, color: 'var(--mdd-text-2)' }}>
      {icon}
      <span>{children}</span>
    </Link>
  )
}
