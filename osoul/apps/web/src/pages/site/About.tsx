import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { bt } from '../../lib/i18n'
import { ABOUT } from '../../lib/pageContent'
import { useAsync } from '../../lib/hooks'
import { fetchSettings } from '../../lib/data'
import { Container } from '../../ui/kit'
import { IcCheck } from '../../ui/icons'

export default function About() {
  const { t, lang } = useApp()
  const A = ABOUT
  const { data: s } = useAsync(fetchSettings, [])
  const q = s?.quote_doc ?? {}
  const g = s?.general ?? {}

  return (
    <>
      <section className="osl-phead">
        <Container wide>
          <p className="osl-kicker osl-kicker--on-dark">{bt(A.hero.kicker)}</p>
          <h1 className="osl-phead__h">{bt(A.hero.title)}</h1>
          <p className="osl-phead__p">{bt(A.hero.strip)}</p>
          <div className="osl-hero__acts">
            <Link to="/contact" className="osl-btn osl-btn--primary">{bt(A.hero.ctaContact)}</Link>
            <Link to="/doors" className="osl-btn osl-btn--secondary">{bt(A.hero.ctaProducts)}</Link>
          </div>
        </Container>
      </section>

      {/* أرقامٌ تُقال للعملاء — تُنقَل كما هي ولا تُقرَّب */}
      <section className="osl-stats">
        <Container wide>
          <div className="osl-grid osl-grid--4">
            {A.stats.map((st, i) => (
              <div key={i} className="osl-stat">
                <b className="osl-num">{st.n}</b>
                <span>{bt(st.l)}</span>
              </div>
            ))}
          </div>
        </Container>
      </section>

      <section className="osl-sec">
        <Container wide>
          <div className="osl-split">
            <div>
              <p className="osl-kicker">{bt(A.intro.kicker)}</p>
              <h2 className="osl-sec__h">{bt(A.intro.title)}</h2>
              <p className="osl-lead">{bt(A.intro.p1)}</p>
              <p className="osl-lead">{bt(A.intro.p2)}</p>
            </div>
            <div>
              <h3 className="osl-sub-h">{bt(A.lines.title)}</h3>
              <ul className="osl-checks">
                {A.lines.items.map((l, i) => (
                  <li key={i}>
                    <span className="osl-checks__tick" aria-hidden="true"><IcCheck size={15} /></span>
                    <span>{bt(l)}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </Container>
      </section>

      <section className="osl-sec osl-sec--alt">
        <Container wide>
          <div className="osl-grid osl-grid--3">
            {[A.vision, A.mission, A.vision2030].map((v: any, i) => (
              <article key={i} className="osl-card osl-card--pad">
                <p className="osl-kicker">{bt(v.label)}</p>
                <h2 className="osl-sub-h">{bt(v.title)}</h2>
                {v.body ? <p className="osl-why__p">{bt(v.body)}</p> : null}
              </article>
            ))}
          </div>
        </Container>
      </section>

      {/* كلمةُ المدير التنفيذيّ — باسمه كما في الأصل */}
      <section className="osl-sec">
        <Container wide>
          <div className="osl-ceo">
            <p className="osl-kicker">{bt(A.ceo.label)}</p>
            <blockquote className="osl-ceo__q">
              <p>{bt(A.ceo.p1)}</p>
              <p>{bt(A.ceo.p2)}</p>
            </blockquote>
            <footer className="osl-ceo__by">
              <b>{bt(A.ceo.name)}</b>
              <span>{bt(A.ceo.role)}</span>
            </footer>
          </div>
        </Container>
      </section>

      <section className="osl-sec osl-sec--alt">
        <Container wide>
          <h2 className="osl-sec__h">{bt(A.process.title)}</h2>
          <ol className="osl-steps">
            {A.process.steps.map((st, i) => (
              <li key={i}>
                <span className="osl-steps__n osl-num">{i + 1}</span>
                <div>
                  <h3 className="osl-sub-h">{bt(st.h)}</h3>
                  <p className="osl-why__p">{bt(st.d)}</p>
                </div>
              </li>
            ))}
          </ol>
        </Container>
      </section>

      <section className="osl-sec">
        <Container wide>
          <p className="osl-kicker">{bt(A.certs.label)}</p>
          <h2 className="osl-sec__h">{bt(A.certs.title)}</h2>
          <div className="osl-grid osl-grid--3">
            {A.certs.items.map((c, i) => (
              <article key={i} className="osl-card osl-card--pad">
                <h3 className="osl-sub-h">{bt(c.h)}</h3>
                <p className="osl-why__p">{bt(c.d)}</p>
              </article>
            ))}
          </div>

          <h2 className="osl-sec__h" style={{ marginBlockStart: 'var(--osl-s-8)' }}>
            {t('بياناتُ المنشأة')}
          </h2>
          <dl className="osl-facts">
            <div><dt>{t('الاسم النظامي')}</dt>
                 <dd>{(lang === 'ar' ? g.company_name_ar : g.company_name_en) || '—'}</dd></div>
            <div><dt>{t('الرقم الضريبي')}</dt>
                 <dd className="osl-num" dir="ltr">{q.vat_number || '—'}</dd></div>
            <div><dt>{t('السجل التجاري')}</dt>
                 <dd className="osl-num" dir="ltr">{q.cr_number || '—'}</dd></div>
            <div><dt>{t('العنوان الوطني')}</dt>
                 <dd>{(lang === 'ar' ? q.national_address_ar : q.national_address_en) || '—'}</dd></div>
          </dl>
        </Container>
      </section>
    </>
  )
}
