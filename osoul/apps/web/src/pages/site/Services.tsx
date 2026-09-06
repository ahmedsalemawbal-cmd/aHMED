import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { SERVICES } from '../../lib/siteContent'
import { Container } from '../../ui/kit'

export default function Services() {
  const { t, lang } = useApp()
  const ar = lang === 'ar'

  return (
    <>
      <section className="osl-phead">
        <Container wide>
          <h1 className="osl-phead__h">{t('خدماتنا')}</h1>
          <p className="osl-phead__p">
            {t('من قراءة المخطّط إلى التسليم والصيانة — جهةٌ واحدة تتحمّل المسؤولية كاملة.')}
          </p>
        </Container>
      </section>

      <section className="osl-sec">
        <Container wide>
          <div className="osl-grid osl-grid--3">
            {SERVICES.map((s) => (
              <article key={s.slug} className="osl-gcard">
                <span className="osl-gcard__icon" aria-hidden="true">
                  {/* مسارُ الأيقونة كما هو في الأصل — لا شبيهٌ له */}
                  <svg width="26" height="26" viewBox="0 0 24 24" fill="none"
                       stroke="currentColor" strokeWidth="1.7"
                       strokeLinecap="round" strokeLinejoin="round"
                       dangerouslySetInnerHTML={{ __html: s.ico }} />
                </span>
                <h2 className="osl-gcard__h">{ar ? s.h : (s.h_en || s.h)}</h2>
                <p className="osl-gcard__p">{ar ? s.d : (s.d_en || s.d)}</p>
              </article>
            ))}
          </div>

          <div className="osl-sec__cta">
            <Link to="/quote" className="osl-btn osl-btn--primary osl-btn--lg">
              {t('اطلب عرض سعر')}
            </Link>
          </div>
        </Container>
      </section>
    </>
  )
}
