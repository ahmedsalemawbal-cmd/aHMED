import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { bt } from '../../lib/i18n'
import { HOME } from '../../lib/pageContent'
import { PROJECT_IMG_BASE } from '../../lib/siteContent'
import { Container } from '../../ui/kit'
import { IcCheck, IcChevron, IcWhatsapp } from '../../ui/icons'

export default function Home() {
  const { t } = useApp()
  const H = HOME

  return (
    <>
      {/* ═══ الواجهة ═══ */}
      <section className="osl-hero">
        <Container wide>
          <div className="osl-hero__in">
            <p className="osl-hero__eyebrow">{bt(H.hero.place)}</p>
            <h1 className="osl-hero__h">
              {bt(H.hero.lead)} <em>{bt(H.hero.hi1)}</em>{' '}
              {bt(H.hero.mid)} <em>{bt(H.hero.hi2)}</em>
            </h1>
            <p className="osl-hero__p">{bt(H.hero.sub)}</p>

            <div className="osl-hero__acts">
              <Link to="/quote" className="osl-btn osl-btn--primary osl-btn--lg">
                {bt(H.hero.ctaQuote)}
              </Link>
              <Link to="/doors" className="osl-btn osl-btn--secondary osl-btn--lg">
                {bt(H.hero.ctaProducts)}
              </Link>
            </div>

            <ul className="osl-hero__chips">
              <li>{bt(H.hero.badgeCert)}</li>
              <li>{bt(H.hero.badgeYears)}</li>
            </ul>
          </div>
        </Container>
      </section>

      {/* ═══ شركة سعودية تصنع الفرق ═══ */}
      <section className="osl-sec">
        <Container wide>
          <div className="osl-split">
            <div>
              <p className="osl-kicker">{bt(H.intro.kicker)}</p>
              <h2 className="osl-sec__h">{bt(H.intro.title)}</h2>
              <p className="osl-lead">{bt(H.intro.body)}</p>
              <Link to="/about" className="osl-btn osl-btn--soft">
                {bt(H.intro.cta)} <IcChevron size={15} />
              </Link>
            </div>
            <ul className="osl-checks">
              {H.intro.points.map((p, i) => (
                <li key={i}>
                  <span className="osl-checks__tick" aria-hidden="true"><IcCheck size={15} /></span>
                  <span>{bt(p)}</span>
                </li>
              ))}
            </ul>
          </div>
        </Container>
      </section>

      {/* ═══ حلول صناعية متكاملة ═══ */}
      <section className="osl-sec osl-sec--alt">
        <Container wide>
          <p className="osl-kicker">{bt(H.solutions.kicker)}</p>
          <h2 className="osl-sec__h">{bt(H.solutions.title)}</h2>
          <p className="osl-lead osl-lead--wide">{bt(H.solutions.sub)}</p>

          <div className="osl-grid osl-grid--3">
            {H.solutions.cards.map((c) => (
              <Link key={c.to} to={c.to} className="osl-gcard">
                <p className="osl-gcard__kicker">{bt(c.kicker)}</p>
                <h3 className="osl-gcard__h">{bt(c.t1)}<br />{bt(c.t2)}</h3>
                <p className="osl-gcard__p">{bt(c.d)}</p>
                <span className="osl-gcard__n">
                  {bt(HOME.solutions.cardCta)} <IcChevron size={14} />
                </span>
              </Link>
            ))}
          </div>
        </Container>
      </section>

      {/* ═══ إنجازات نفخر بها ═══ */}
      <section className="osl-sec">
        <Container wide>
          <p className="osl-kicker">{bt(H.work.kicker)}</p>
          <h2 className="osl-sec__h">{bt(H.work.title)}</h2>
          <p className="osl-lead osl-lead--wide">{bt(H.work.sub)}</p>

          <div className="osl-feat">
            <div className="osl-feat__media">
              <img src={PROJECT_IMG_BASE + 'DSC_2387-scaled.jpg'}
                   alt={bt(H.work.featured.t1) + ' ' + bt(H.work.featured.t2)} loading="lazy" />
            </div>
            <div className="osl-feat__body">
              <p className="osl-kicker">{bt(H.work.featured.kicker)}</p>
              <h3 className="osl-feat__h">{bt(H.work.featured.t1)}<br />{bt(H.work.featured.t2)}</h3>
              <p className="osl-feat__p">{bt(H.work.featured.d)}</p>
              <p className="osl-feat__meta">
                <b className="osl-num">{H.work.featured.count}</b>
                <span>{bt(H.work.featured.tag)}</span>
                <span className="osl-pmeta__sep" aria-hidden="true" />
                <span>{bt(H.work.featured.loc)}</span>
              </p>
              <ul className="osl-feat__others">
                {H.work.others.map((o, i) => <li key={i}>{bt(o)}</li>)}
              </ul>
              <Link to="/projects" className="osl-btn osl-btn--secondary">
                {bt(H.work.cta)}
              </Link>
            </div>
          </div>
        </Container>
      </section>

      {/* ═══ نقدم أكثر من منتج ═══ */}
      <section className="osl-sec osl-sec--alt">
        <Container wide>
          <p className="osl-kicker">{bt(H.features.kicker)}</p>
          <h2 className="osl-sec__h">{bt(H.features.title)}</h2>
          <div className="osl-grid osl-grid--4">
            {H.features.items.map((f, i) => (
              <div key={i} className="osl-why">
                <span className="osl-why__tick" aria-hidden="true"><IcCheck size={16} /></span>
                <h3 className="osl-why__h">{bt(f.h)}</h3>
                <p className="osl-why__p">{bt(f.d)}</p>
              </div>
            ))}
          </div>
        </Container>
      </section>

      {/* ═══ مشروعك القادم يبدأ من هنا ═══ */}
      <section className="osl-cta">
        <Container>
          <h2 className="osl-cta__h">{bt(H.cta.t1)} <em>{bt(H.cta.t2)}</em></h2>
          <p className="osl-cta__p">{bt(H.cta.d)}</p>
          <div className="osl-cta__acts">
            <Link to="/quote" className="osl-btn osl-btn--lg osl-cta__btn">{bt(H.cta.quote)}</Link>
            <a className="osl-btn osl-btn--lg osl-cta__wa"
               href="https://wa.me/966556847029" target="_blank" rel="noopener noreferrer">
              <IcWhatsapp size={18} /><span>{bt(H.cta.wa)}</span>
            </a>
          </div>
        </Container>
      </section>
    </>
  )
}
