import React, { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { Avatar } from '../../ui/kit'
import {
  IcHome, IcTeam, IcCard, IcInvoice, IcPuzzle, IcShield, IcLibrary, IcSpark,
  IcSettings, IcHistory, IcMenu, IcSun, IcMoon, IcLogo, IcLogout, IcBack,
} from '../../ui/icons'

const NAV_MAIN = [
  { to: '/admin', end: true, label: 'نظرة عامّة', icon: IcHome },
  { to: '/admin/subscribers', label: 'المشتركون', icon: IcTeam },
]
const NAV_MONEY = [
  { to: '/admin/subscriptions', label: 'الاشتراكات', icon: IcCard },
  { to: '/admin/invoices', label: 'الفواتير', icon: IcInvoice },
  { to: '/admin/plans', label: 'الباقات', icon: IcPuzzle },
]
const NAV_CONTENT = [
  { to: '/admin/roles', label: 'الأدوار', icon: IcShield },
  { to: '/admin/templates', label: 'مكتبة القوالب', icon: IcLibrary },
  { to: '/admin/ai', label: 'الذكاء الاصطناعيّ', icon: IcSpark },
]
const NAV_SYSTEM = [
  { to: '/admin/settings', label: 'إعدادات المنصّة', icon: IcSettings },
  { to: '/admin/log', label: 'سجلّ النظام', icon: IcHistory },
]

export default function AdminLayout() {
  const { profile, general, theme, setTheme, signOut } = useApp()
  const [open, setOpen] = useState(false)
  const loc = useLocation()
  const nav = useNavigate()

  useEffect(() => { setOpen(false) }, [loc.pathname])

  return (
    <div className="mdd-shell">
      {open && <div className="mdd-drawer-backdrop" onClick={() => setOpen(false)} />}

      <aside className="mdd-sidebar mdd-sidebar--admin" data-open={open} aria-label="تنقّل لوحة الإدارة">
        <div className="mdd-sidebar__brand">
          <IcLogo size={30} />
          <span style={{ minWidth: 0 }}>
            {general.platform_name || 'مِداد'}
            <span style={{ display: 'block', fontSize: 10.5, fontWeight: 600, opacity: 0.66 }}>لوحة الإدارة</span>
          </span>
        </div>

        <nav className="mdd-sidebar__nav">
          {NAV_MAIN.map((n) => (
            <NavLink key={n.to} to={n.to} end={(n as any).end} className="mdd-navlink">
              <n.icon size={18} /><span>{n.label}</span>
            </NavLink>
          ))}

          <div className="mdd-sidebar__group">المال</div>
          {NAV_MONEY.map((n) => (
            <NavLink key={n.to} to={n.to} className="mdd-navlink">
              <n.icon size={18} /><span>{n.label}</span>
            </NavLink>
          ))}

          <div className="mdd-sidebar__group">المحتوى</div>
          {NAV_CONTENT.map((n) => (
            <NavLink key={n.to} to={n.to} className="mdd-navlink">
              <n.icon size={18} /><span>{n.label}</span>
            </NavLink>
          ))}

          <div className="mdd-sidebar__group">النظام</div>
          {NAV_SYSTEM.map((n) => (
            <NavLink key={n.to} to={n.to} className="mdd-navlink">
              <n.icon size={18} /><span>{n.label}</span>
            </NavLink>
          ))}

          <div className="mdd-sidebar__group">العودة</div>
          <Link to="/app" className="mdd-navlink">
            <IcBack size={18} /><span>عُد إلى لوحتي</span>
          </Link>
        </nav>

        <div style={{
          marginBlockStart: 'auto', padding: 'var(--mdd-s-4)',
          borderBlockStart: '1px solid var(--mdd-border)',
        }}>
          <div className="mdd-row" style={{ gap: 10 }}>
            <Avatar name={profile?.full_name || 'مالك المنصّة'} size="sm" />
            <div style={{ minWidth: 0, flex: 1 }}>
              <div style={{ fontSize: 12.5, fontWeight: 700, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {profile?.full_name || 'مالك المنصّة'}
              </div>
              <div style={{ fontSize: 11, opacity: 0.66 }}>مالك المنصّة</div>
            </div>
          </div>
        </div>
      </aside>

      <div className="mdd-main">
        <header className="mdd-header">
          <button className="mdd-btn mdd-btn--secondary mdd-btn--icon mdd-mobile-only"
            onClick={() => setOpen(true)} aria-label="فتح قائمة الإدارة">
            <IcMenu size={18} />
          </button>
          <Link to="/admin" className="mdd-row" style={{ gap: 8, color: 'var(--mdd-accent)' }} aria-label="نظرة عامّة">
            <IcLogo size={26} />
          </Link>
          <span className="mdd-badge mdd-badge--accent">لوحة الإدارة</span>
          <div className="mdd-spacer" />
          <button className="mdd-btn mdd-btn--secondary mdd-btn--icon" title="تبديل الوضع"
            aria-label="تبديل الوضع الفاتح والداكن"
            onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}>
            {theme === 'dark' ? <IcSun size={17} /> : <IcMoon size={17} />}
          </button>
          <button className="mdd-btn mdd-btn--secondary mdd-btn--icon" title="خروج" aria-label="خروج"
            onClick={async () => { await signOut(); nav('/') }}>
            <IcLogout size={17} />
          </button>
        </header>

        <main className="mdd-content">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
