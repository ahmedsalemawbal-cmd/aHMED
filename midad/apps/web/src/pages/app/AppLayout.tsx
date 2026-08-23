import React, { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useSidebarCollapsed } from '../../lib/sidebar'
import { daysLabel } from '../../lib/format'
import { Avatar, Button } from '../../ui/kit'
import {
  IcHome, IcLibrary, IcFiles, IcTable, IcTeam, IcUser, IcSettings, IcCard, IcInvoice,
  IcMenu, IcSun, IcMoon, IcLogo, IcLogout, IcShield, IcCollapse,
} from '../../ui/icons'

const NAV = [
  { to: '/app', end: true, label: 'الرئيسية', icon: IcHome },
  { to: '/app/library', label: 'مكتبة القوالب', icon: IcLibrary },
  { to: '/app/files', label: 'ملفّاتي', icon: IcFiles },
  { to: '/app/noor', label: 'جداول نور', icon: IcTable },
]
const NAV_ACCOUNT = [
  { to: '/app/team', label: 'الفريق', icon: IcTeam, schoolOnly: false },
  { to: '/app/settings', label: 'إعدادات المشترك', icon: IcSettings },
  { to: '/app/account', label: 'حسابي', icon: IcUser },
]
const NAV_BILLING = [
  { to: '/app/subscription', label: 'الاشتراك', icon: IcCard },
  { to: '/app/invoices', label: 'الفواتير', icon: IcInvoice },
]

export default function AppLayout() {
  const { profile, subscriber, access, trialDays, theme, setTheme, signOut, isAdmin, roles } = useApp()
  const [open, setOpen] = useState(false)
  const { collapsed, toggle } = useSidebarCollapsed()
  const loc = useLocation()
  const nav = useNavigate()

  useEffect(() => { setOpen(false) }, [loc.pathname])

  const roleName = roles.find((r) => r.key === profile?.role_key)?.name_ar || 'معلّم'
  const urgent = access === 'trial' && trialDays <= 2

  return (
    <div className="mdd-shell">
      {open && <div className="mdd-drawer-backdrop" onClick={() => setOpen(false)} />}

      <aside className="mdd-sidebar" data-open={open} aria-label="التنقّل الرئيسي">
        <div className="mdd-sidebar__brand" style={{ color: 'var(--mdd-accent)' }}>
          <IcLogo size={30} />
          <span style={{ color: 'var(--mdd-text)' }}>مِداد</span>
        </div>
        <nav className="mdd-sidebar__nav">
          {NAV.map((n) => (
            <NavLink key={n.to} to={n.to} end={n.end} className="mdd-navlink" title={collapsed ? n.label : undefined}>
              <n.icon size={18} /><span>{n.label}</span>
            </NavLink>
          ))}
          <div className="mdd-sidebar__group">الحساب</div>
          {NAV_ACCOUNT.map((n) => (
            <NavLink key={n.to} to={n.to} className="mdd-navlink" title={collapsed ? n.label : undefined}>
              <n.icon size={18} /><span>{n.label}</span>
            </NavLink>
          ))}
          <div className="mdd-sidebar__group">الاشتراك</div>
          {NAV_BILLING.map((n) => (
            <NavLink key={n.to} to={n.to} className="mdd-navlink" title={collapsed ? n.label : undefined}>
              <n.icon size={18} /><span>{n.label}</span>
            </NavLink>
          ))}
          {isAdmin && (
            <>
              <div className="mdd-sidebar__group">المنصّة</div>
              <NavLink to="/admin" className="mdd-navlink" title={collapsed ? 'لوحة الإدارة' : undefined}><IcShield size={18} /><span>لوحة الإدارة</span></NavLink>
            </>
          )}
        </nav>
        <div className="mdd-sidebar__foot">
          <button className="mdd-collapse-btn" onClick={toggle}
            title={collapsed ? 'وسّع القائمة' : 'طيّ القائمة'}
            aria-label={collapsed ? 'وسّع القائمة' : 'طيّ القائمة'} aria-expanded={!collapsed}>
            <IcCollapse size={15} collapsed={collapsed} />
            <span>طيّ القائمة</span>
          </button>
          <div className="mdd-row mdd-sidebar__who" style={{ gap: 10, paddingBlockStart: 10, borderBlockStart: '1px solid var(--mdd-border)' }}>
            <Avatar name={profile?.full_name || ''} size="sm" />
            <div className="mdd-sidebar__who-text" style={{ minWidth: 0, flex: 1 }}>
              <div style={{ fontSize: 12.5, fontWeight: 700, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{profile?.full_name}</div>
              <div style={{ fontSize: 11, color: 'var(--mdd-text-3)' }}>{roleName}</div>
            </div>
          </div>
        </div>
      </aside>

      <div className="mdd-main">
        <header className="mdd-header">
          <MobileMenuButton onClick={() => setOpen(true)} />
          <Link to="/app" className="mdd-row" style={{ gap: 8, color: 'var(--mdd-accent)' }}>
            <IcLogo size={26} />
          </Link>
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

        {access === 'trial' && (
          <div className={'mdd-trialbar' + (urgent ? ' mdd-trialbar--urgent' : '')} role="status">
            <span>
              {urgent
                ? `تنتهي تجربتك بعد ${daysLabel(trialDays)} — بعدها تُقفل شاشات المنتج حتى الاشتراك.`
                : `بقي ${daysLabel(trialDays)} من تجربتك المجانية.`}
            </span>
            <div className="mdd-spacer" />
            <Link to="/app/plans"><Button size="sm" auto variant={urgent ? 'primary' : 'soft'}>اشترك الآن</Button></Link>
          </div>
        )}

        <main className="mdd-content">
          <Outlet />
        </main>

        <nav className="mdd-bottomnav" aria-label="التنقّل السفلي">
          {NAV.map((n) => (
            <NavLink key={n.to} to={n.to} end={n.end}>
              <n.icon size={20} /><span>{n.label.replace('مكتبة ', '')}</span>
            </NavLink>
          ))}
        </nav>
      </div>
    </div>
  )
}

function MobileMenuButton({ onClick }: { onClick: () => void }) {
  return (
    <button className="mdd-btn mdd-btn--secondary mdd-btn--icon mdd-mobile-only" onClick={onClick} aria-label="فتح القائمة">
      <IcMenu size={18} />
    </button>
  )
}
