import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fetchSettings } from '../../lib/data'
import { Container } from '../../ui/kit'
import { IcCheck } from '../../ui/icons'

const PILLARS = [
  { h: 'التوريد من المصنع مباشرة',
    d: 'نصنع ونستورد بمواصفاتٍ معتمدة، فلا وسيطَ يرفع السعر ولا مورّدَ يتأخّر علينا فنتأخّر عليك.' },
  { h: 'التوريد والتركيب من جهةٍ واحدة',
    d: 'حين تتوزّع المسؤولية بين مورّدٍ ومقاولٍ تركيب، يضيع الضمانُ بينهما عند أوّل خطأ. عندنا الجهةُ واحدة.' },
  { h: 'مطابقةٌ للمواصفات لا قربٌ منها',
    d: 'الأبواب المقاومة للحريق تُعتمد بمدّةٍ مكتوبة (30 إلى 120 دقيقة)، والبروفايلات بسماكةِ جلفنةٍ معلومة (Z120).' },
  { h: 'ما بعد التسليم جزءٌ من العقد',
    d: 'صيانةٌ دوريّةٌ وطارئة، وفحصٌ وتفتيشٌ فنّيّ — لأنّ البابَ الذي لا يُصان لا يُقاوم حريقًا بعد سنتين.' },
]

export default function About() {
  const { t, lang } = useApp()
  const { data: s } = useAsync(fetchSettings, [])
  const g = s?.general ?? {}
  const q = s?.quote_doc ?? {}
  const name = lang === 'ar' ? g.company_name_ar : (g.company_name_en || g.company_name_ar)

  return (
    <>
      <section className="osl-phead">
        <Container wide>
          <h1 className="osl-phead__h">{t('من نحن')}</h1>
          <p className="osl-phead__p">
            {name || t('شركة أصول البناء للصناعة')} — {t('توريدٌ وتركيبٌ للأبواب وبروفايلات الجبسوم وأنظمة التثبيت، في جدة ومنها إلى مشاريع المملكة.')}
          </p>
        </Container>
      </section>

      <section className="osl-sec">
        <Container wide>
          <div className="osl-grid osl-grid--2">
            {PILLARS.map((p) => (
              <div key={p.h} className="osl-why">
                <span className="osl-why__tick" aria-hidden="true"><IcCheck size={16} /></span>
                <h2 className="osl-why__h">{t(p.h)}</h2>
                <p className="osl-why__p">{t(p.d)}</p>
              </div>
            ))}
          </div>
        </Container>
      </section>

      <section className="osl-sec osl-sec--alt">
        <Container wide>
          <h2 className="osl-sec__h">{t('بياناتُ المنشأة')}</h2>
          <dl className="osl-facts">
            <div><dt>{t('الاسم النظامي')}</dt><dd>{name || '—'}</dd></div>
            <div><dt>{t('الرقم الضريبي')}</dt><dd className="osl-num" dir="ltr">{q.vat_number || '—'}</dd></div>
            <div><dt>{t('السجل التجاري')}</dt><dd className="osl-num" dir="ltr">{q.cr_number || '—'}</dd></div>
            <div><dt>{t('العنوان الوطني')}</dt>
                 <dd>{lang === 'ar' ? q.national_address_ar : (q.national_address_en || q.national_address_ar) || '—'}</dd></div>
          </dl>
          <div className="osl-sec__cta">
            <Link to="/contact" className="osl-btn osl-btn--primary osl-btn--lg">{t('تواصل معنا')}</Link>
          </div>
        </Container>
      </section>
    </>
  )
}
