import { useApp } from '../../lib/store'
import { PROJECTS, PROJECT_IMG_BASE } from '../../lib/siteContent'
import { Container } from '../../ui/kit'

export default function Projects() {
  const { t, lang } = useApp()
  const ar = lang === 'ar'

  return (
    <>
      <section className="osl-phead">
        <Container wide>
          <h1 className="osl-phead__h">{t('مشاريعنا')}</h1>
          <p className="osl-phead__p">
            {t('جهاتٌ حكوميّة ومستشفياتٌ وجامعاتٌ ومجمّعاتٌ تجاريّة وسكنيّة — نفّذنا لها ونفّذنا معها.')}
          </p>
        </Container>
      </section>

      <section className="osl-sec">
        <Container wide>
          <div className="osl-grid osl-grid--3">
            {PROJECTS.map((p, i) => (
              <article key={i} className="osl-pcard">
                <div className="osl-pcard__img">
                  <img src={PROJECT_IMG_BASE + p.img} alt={ar ? p.h : (p.h_en || p.h)} loading="lazy" />
                  <span className="osl-pcard__badge">{ar ? p.cat : (p.cat_en || p.cat)}</span>
                </div>
                <div className="osl-pcard__body">
                  <h2 className="osl-pcard__h">{ar ? p.h : (p.h_en || p.h)}</h2>
                  <p className="osl-pcard__sub">{ar ? p.d : (p.d_en || p.d)}</p>
                  <p className="osl-pmeta">
                    <span>{ar ? p.loc : (p.loc_en || p.loc)}</span>
                    <span className="osl-pmeta__sep" aria-hidden="true" />
                    <span className="osl-num">{p.year}</span>
                  </p>
                </div>
              </article>
            ))}
          </div>
        </Container>
      </section>
    </>
  )
}
