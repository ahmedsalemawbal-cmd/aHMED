import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { Container } from '../../ui/kit'
import { IcCheck, IcDoor, IcPanel, IcStrut } from '../../ui/icons'

const GROUPS = [
  { to: '/doors',  icon: IcDoor,  title: 'الأبواب بأنواعها',
    line: 'أبواب مقاومة للحريق ومعدنية مجوفة وتكسوية وخشبية MDF و WPC ومخصّصة.', n: 6 },
  { to: '/gypsum', icon: IcPanel, title: 'بروفايلات الجبسوم بورد',
    line: 'C-Stud و U-Track و L-Angle و Hat Channel وقنوات الأسقف وإكسسواراتها.', n: 13 },
  { to: '/strut',  icon: IcStrut, title: 'أنظمة Strut للتثبيت',
    line: 'أطواق وقنوات وأذرع كونسول ودعامات ومشابك تعليق ووصلات نظام.', n: 9 },
]

const WHY = [
  { t: 'توريدٌ من المصنع', l: 'منتجاتٌ نصنعها ونستوردها بمواصفاتٍ معتمدة، لا وسيطَ يرفع السعر.' },
  { t: 'تركيبٌ ينفّذه فريقُنا', l: 'التوريد والتركيب من جهةٍ واحدة — فلا تتوزّع المسؤولية عند الخطأ.' },
  { t: 'ضمانٌ شامل', l: 'ضمانٌ مكتوبٌ على المنتج والتركيب، وصيانةٌ دوريّةٌ وطارئة بعد التسليم.' },
  { t: 'استشارةٌ هندسيّة', l: 'نقرأ المخطّط ونقترح المواصفة المناسبة قبل أن تشتري.' },
]

export default function Home() {
  const { t } = useApp()

  return (
    <>
      <section className="osl-hero">
        <Container wide>
          <div className="osl-hero__in">
            <p className="osl-hero__eyebrow">{t('شركة أصول البناء للصناعة')}</p>
            <h1 className="osl-hero__h">
              {t('نصنع ما يقوم عليه المبنى')}
            </h1>
            <p className="osl-hero__p">
              {t('أبوابٌ مقاومةٌ للحريق، وبروفايلاتُ جبسوم بورد، وأنظمةُ Strut للتثبيت — توريدًا وتركيبًا وضمانًا، لمشاريع المملكة.')}
            </p>
            <div className="osl-hero__acts">
              <Link to="/quote" className="osl-btn osl-btn--primary osl-btn--lg">
                {t('اطلب عرض سعر')}
              </Link>
              <Link to="/doors" className="osl-btn osl-btn--secondary osl-btn--lg">
                {t('تصفّح المنتجات')}
              </Link>
            </div>
          </div>
        </Container>
      </section>

      <section className="osl-sec">
        <Container wide>
          <h2 className="osl-sec__h">{t('ثلاث عائلات من المنتجات')}</h2>
          <div className="osl-grid osl-grid--3">
            {GROUPS.map((g) => {
              const Icon = g.icon
              return (
                <Link key={g.to} to={g.to} className="osl-gcard">
                  <span className="osl-gcard__icon" aria-hidden="true"><Icon size={26} /></span>
                  <h3 className="osl-gcard__h">{t(g.title)}</h3>
                  <p className="osl-gcard__p">{t(g.line)}</p>
                  <span className="osl-gcard__n">
                    <span className="osl-num">{g.n}</span> {t('منتجًا')}
                  </span>
                </Link>
              )
            })}
          </div>
        </Container>
      </section>

      <section className="osl-sec osl-sec--alt">
        <Container wide>
          <h2 className="osl-sec__h">{t('لماذا أصول البناء')}</h2>
          <div className="osl-grid osl-grid--4">
            {WHY.map((w) => (
              <div key={w.t} className="osl-why">
                <span className="osl-why__tick" aria-hidden="true"><IcCheck size={16} /></span>
                <h3 className="osl-why__h">{t(w.t)}</h3>
                <p className="osl-why__p">{t(w.l)}</p>
              </div>
            ))}
          </div>
        </Container>
      </section>

      <section className="osl-cta">
        <Container>
          <h2 className="osl-cta__h">{t('عندك مشروع؟ أرسل لنا قائمتك')}</h2>
          <p className="osl-cta__p">
            {t('اختر المنتجات والكمّيات، وسنرسل لك عرضًا مسعّرًا خلال يوم عمل.')}
          </p>
          <Link to="/quote" className="osl-btn osl-btn--lg osl-cta__btn">
            {t('اطلب عرض سعر')}
          </Link>
        </Container>
      </section>
    </>
  )
}
