import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { LANGS, LANG_NAMES } from '../../lib/i18n'
import type { Lang } from '../../lib/i18n'
import { Container } from '../../ui/kit'
import { IcClose, IcGlobe, IcMenu, IcMoon, IcSun, IcWhatsapp } from '../../ui/icons'

const NAV: { to: string; label: string }[] = [
  { to: '/', label: 'الرئيسية' },
  { to: '/about', label: 'من نحن' },
  { to: '/doors', label: 'الأبواب' },
  { to: '/gypsum', label: 'الجبسوم' },
  { to: '/strut', label: 'أنظمة التثبيت' },
  { to: '/services', label: 'الخدمات' },
  { to: '/projects', label: 'المشاريع' },
  { to: '/contact', label: 'تواصل معنا' },
]

export default function SiteLayout() {
  const { t, lang, setLang, theme, setTheme } = useApp()
  const [open, setOpen] = useState(false)
  const { pathname } = useLocation()

  // القائمةُ تُغلق عند التنقّل — وإلّا بقيت مفتوحةً فوق الصفحة الجديدة
  useEffect(() => { setOpen(false) }, [pathname])

  // ولا تُمرَّر الصفحةُ خلف القائمة المفتوحة على الجوّال
  useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : ''
    return () => { document.body.style.overflow = '' }
  }, [open])

  const nextTheme = theme === 'dark' ? 'light' : 'dark'

  return (
    <div className="osl-site">
      <a href="#main" className="osl-skip">{t('تخطَّ إلى المحتوى')}</a>

      <header className="osl-hdr">
        <Container wide>
          <div className="osl-hdr__row">
            <Link to="/" className="osl-brand" aria-label={t('أصول البناء')}>
              <span className="osl-brand__mark" aria-hidden="true">أ</span>
              <span className="osl-brand__text">
                <b>{t('أصول البناء')}</b>
                <i>OSOUL ALBINAA</i>
              </span>
            </Link>

            <nav className="osl-nav" aria-label={t('التنقّل الرئيس')}>
              {NAV.map((n) => (
                <NavLink
                  key={n.to} to={n.to} end={n.to === '/'}
                  className={({ isActive }) => 'osl-nav__a' + (isActive ? ' is-on' : '')}
                >
                  {t(n.label)}
                </NavLink>
              ))}
            </nav>

            <div className="osl-hdr__acts">
              <label className="osl-langsel">
                <IcGlobe size={17} />
                <span className="osl-sr">{t('اللغة')}</span>
                <select value={lang} onChange={(e) => setLang(e.target.value as Lang)}>
                  {LANGS.map((l) => <option key={l} value={l}>{LANG_NAMES[l]}</option>)}
                </select>
              </label>

              <button
                type="button" className="osl-iconbtn"
                onClick={() => setTheme(nextTheme)}
                aria-label={t(nextTheme === 'dark' ? 'الوضع الليلي' : 'الوضع النهاري')}
              >
                {theme === 'dark' ? <IcSun size={18} /> : <IcMoon size={18} />}
              </button>

              <Link to="/quote" className="osl-btn osl-btn--primary osl-btn--sm osl-hdr__cta">
                {t('اطلب عرض سعر')}
              </Link>

              <button
                type="button" className="osl-iconbtn osl-hdr__burger"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                aria-label={t(open ? 'إغلاق القائمة' : 'فتح القائمة')}
              >
                {open ? <IcClose size={20} /> : <IcMenu size={20} />}
              </button>
            </div>
          </div>
        </Container>

        {open ? (
          <div className="osl-mobnav">
            <Container>
              {NAV.map((n) => (
                <NavLink
                  key={n.to} to={n.to} end={n.to === '/'}
                  className={({ isActive }) => 'osl-mobnav__a' + (isActive ? ' is-on' : '')}
                >
                  {t(n.label)}
                </NavLink>
              ))}
              <Link to="/quote" className="osl-btn osl-btn--primary osl-btn--block">
                {t('اطلب عرض سعر')}
              </Link>
            </Container>
          </div>
        ) : null}
      </header>

      <main id="main" className="osl-main">
        <Outlet />
      </main>

      <footer className="osl-ftr">
        <Container wide>
          <div className="osl-ftr__grid">
            <div>
              <div className="osl-brand osl-brand--ftr">
                <span className="osl-brand__mark" aria-hidden="true">أ</span>
                <span className="osl-brand__text">
                  <b>{t('أصول البناء')}</b>
                  <i>OSOUL ALBINAA</i>
                </span>
              </div>
              <p className="osl-ftr__line">
                {t('توريد وتركيب الأبواب وبروفايلات الجبسوم وأنظمة التثبيت — بضمانٍ شامل وخدمةٍ بعد البيع.')}
              </p>
            </div>

            <nav aria-label={t('روابط')}>
              <h3 className="osl-ftr__h">{t('روابط')}</h3>
              {NAV.slice(1).map((n) => (
                <Link key={n.to} to={n.to} className="osl-ftr__a">{t(n.label)}</Link>
              ))}
            </nav>

            <div>
              <h3 className="osl-ftr__h">{t('تواصل معنا')}</h3>
              <a className="osl-ftr__a osl-num" href="tel:+966563627063" dir="ltr">+966 56 362 7063</a>
              <a className="osl-ftr__a" href="mailto:info@osoulalbinaa.com">info@osoulalbinaa.com</a>
              <Link to="/privacy" className="osl-ftr__a">{t('سياسة الخصوصية')}</Link>
            </div>
          </div>

          <div className="osl-ftr__bot">
            <span>© <span className="osl-num">{new Date().getFullYear()}</span> {t('أصول البناء')}</span>
            <Link to="/dashboard" className="osl-ftr__a">{t('دخول الموظّفين')}</Link>
          </div>
        </Container>
      </footer>

      <a
        className="osl-wa" href="https://wa.me/966556847029"
        target="_blank" rel="noopener noreferrer"
        aria-label={t('راسلنا على واتساب')}
      >
        <IcWhatsapp size={26} />
      </a>
    </div>
  )
}
