import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { ROLE_LABELS } from '../../lib/auth'
import type { Role } from '../../lib/store'
import { LANGS, LANG_NAMES } from '../../lib/i18n'
import type { Lang } from '../../lib/i18n'
import { initials } from '../../lib/format'
import { IcClose, IcGlobe, IcMenu, IcMoon, IcSun } from '../../ui/icons'

type Item = { to: string; label: string; roles: Role[] }

/**
 * قائمةٌ واحدةٌ لكلّ الأدوار، يُرشَّح منها ما يخصّ الداخل.
 *
 * ولو كُتبت قائمةٌ لكلّ دورٍ على حدة لَتباعدت الخمسُ يومًا، فيُضاف بندٌ
 * في واحدةٍ ويُنسى في أربع. (عُرفُ «إعداداتٌ عامّة لا ترقيعَ ملفٍّ بملفّ».)
 */
const NAV: Item[] = [
  { to: '/dashboard',        label: 'لوحة القيادة',      roles: ['admin', 'branch', 'rep', 'partner'] },
  { to: '/dashboard/quotes', label: 'الطلبات',           roles: ['admin', 'branch', 'rep'] },
  { to: '/dashboard/new',    label: 'طلب عرض سعر',       roles: ['branch', 'rep'] },
  { to: '/dashboard/supply', label: 'طلباتي من أصول',    roles: ['partner'] },
  { to: '/dashboard/mine',   label: 'عروضي لعملائي',     roles: ['partner'] },
  { to: '/dashboard/brand',  label: 'هويّتي التجارية',   roles: ['partner'] },
  { to: '/dashboard/reps',   label: 'المناديب',          roles: ['admin', 'branch'] },
  { to: '/dashboard/branches', label: 'الفروع',          roles: ['admin'] },
  { to: '/dashboard/partners', label: 'الشركاء',         roles: ['admin'] },
  { to: '/dashboard/customers', label: 'العملاء',        roles: ['admin'] },
  { to: '/dashboard/leads',  label: 'طلبات الموقع',      roles: ['admin'] },
  { to: '/dashboard/products', label: 'المنتجات والأسعار', roles: ['admin'] },
  { to: '/dashboard/settings', label: 'الإعدادات',       roles: ['admin'] },
  { to: '/my-quotes',        label: 'عروض أسعاري',       roles: ['customer'] },
  { to: '/mail',             label: 'البريد',            roles: ['employee'] },
]

export default function PortalLayout() {
  const { t, profile, role, lang, setLang, theme, setTheme, signOut } = useApp()
  const [open, setOpen] = useState(false)
  const { pathname } = useLocation()

  useEffect(() => { setOpen(false) }, [pathname])

  const items = NAV.filter((n) => n.roles.includes(role))
  const nextTheme = theme === 'dark' ? 'light' : 'dark'

  return (
    <div className={'osl-portal' + (open ? ' is-open' : '')}>
      <aside className="osl-side">
        <Link to="/" className="osl-brand osl-brand--side">
          <span className="osl-brand__mark" aria-hidden="true">أ</span>
          <span className="osl-brand__text">
            <b>{t('أصول البناء')}</b>
            <i>{t(ROLE_LABELS[role] || '')}</i>
          </span>
        </Link>

        <nav className="osl-side__nav" aria-label={t('تنقّل اللوحة')}>
          {items.map((n) => (
            <NavLink
              key={n.to} to={n.to} end={n.to === '/dashboard'}
              className={({ isActive }) => 'osl-side__a' + (isActive ? ' is-on' : '')}
            >
              {t(n.label)}
            </NavLink>
          ))}
        </nav>

        <div className="osl-side__foot">
          <div className="osl-side__me">
            <span className="osl-avatar" aria-hidden="true">{initials(profile?.full_name)}</span>
            <span>
              <b>{profile?.full_name || '—'}</b>
              <i>{t(ROLE_LABELS[role] || '')}</i>
            </span>
          </div>
          <button type="button" className="osl-side__out" onClick={signOut}>
            {t('تسجيل الخروج')}
          </button>
        </div>
      </aside>

      <div className="osl-portal__main">
        <header className="osl-topbar">
          <button type="button" className="osl-iconbtn osl-topbar__burger"
                  onClick={() => setOpen((v) => !v)}
                  aria-expanded={open} aria-label={t(open ? 'إغلاق القائمة' : 'فتح القائمة')}>
            {open ? <IcClose size={20} /> : <IcMenu size={20} />}
          </button>

          <div className="osl-topbar__acts">
            <label className="osl-langsel osl-langsel--light">
              <IcGlobe size={16} />
              <span className="osl-sr">{t('اللغة')}</span>
              <select value={lang} onChange={(e) => setLang(e.target.value as Lang)}>
                {LANGS.map((l) => <option key={l} value={l}>{LANG_NAMES[l]}</option>)}
              </select>
            </label>
            <button type="button" className="osl-iconbtn osl-iconbtn--light"
                    onClick={() => setTheme(nextTheme)}
                    aria-label={t(nextTheme === 'dark' ? 'الوضع الليلي' : 'الوضع النهاري')}>
              {theme === 'dark' ? <IcSun size={18} /> : <IcMoon size={18} />}
            </button>
          </div>
        </header>

        <main className="osl-portal__body">
          <Outlet />
        </main>
      </div>

      {open ? <button className="osl-side__scrim" aria-label={t('إغلاق')} onClick={() => setOpen(false)} /> : null}
    </div>
  )
}
