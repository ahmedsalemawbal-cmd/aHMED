import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fetchSettings } from '../../lib/data'
import { Container } from '../../ui/kit'

/**
 * سياسةُ الخصوصيّة تصف **ما يفعله هذا الموقعُ فعلًا** لا نصًّا عامًّا
 * منسوخًا. وما يُذكر هنا يقابله في الشيفرة موضعٌ يُشار إليه:
 * `submitLead` وحدها هي التي تُرسل شيئًا، و`localStorage` وحده هو
 * الذي يحفظ في الجهاز.
 */
export default function Privacy() {
  const { t } = useApp()
  const { data: s } = useAsync(fetchSettings, [])
  const email = s?.general?.email || 'info@osoulalbinaa.com'

  const SECTIONS = [
    { h: 'ما الذي نجمعه',
      d: 'ما تكتبه أنت في نموذج التواصل أو طلب التسعير: الاسم، رقم الجوال، البريد الإلكتروني إن كتبته، ونصّ الرسالة وقائمة المنتجات التي اخترتها. ولا نجمع غير ذلك.' },
    { h: 'لماذا نجمعه',
      d: 'للردّ على طلبك وإرسال عرض السعر ومتابعته. ولا نستعمله في تسويقٍ لم تطلبه.' },
    { h: 'ما يبقى في جهازك وحده',
      d: 'قائمةُ طلب التسعير واختيارُ اللغة والوضع الليلي تُحفظ في متصفّحك أنت، ولا تصل إلينا حتّى تضغط «أرسل».' },
    { h: 'مع من نشاركه',
      d: 'لا نبيع بياناتك ولا نشاركها مع طرفٍ ثالث لأغراض تسويقيّة. ونشاركها فقط مع من يلزم لتنفيذ طلبك داخل الشركة.' },
    { h: 'كم نحتفظ به',
      d: 'نحتفظ بطلبات التسعير والعروض الصادرة مدّةً يقتضيها النظامُ المحاسبيّ والضريبيّ في المملكة، ثمّ تُؤرشف.' },
    { h: 'حقّك',
      d: 'لك أن تطلب نسخةً ممّا لدينا عنك، أو تصحيحه، أو حذفه — إلّا ما يلزم بقاؤه في وثيقةٍ ضريبيّةٍ صادرة.' },
  ]

  return (
    <Container>
      <div className="osl-phead osl-phead--flat">
        <h1 className="osl-phead__h">{t('سياسة الخصوصية')}</h1>
      </div>
      <div className="osl-prose">
        {SECTIONS.map((sec) => (
          <section key={sec.h}>
            <h2>{t(sec.h)}</h2>
            <p>{t(sec.d)}</p>
          </section>
        ))}
        <section>
          <h2>{t('للتواصل بخصوص الخصوصية')}</h2>
          <p><a href={`mailto:${email}`} dir="ltr">{email}</a></p>
        </section>
      </div>
    </Container>
  )
}
