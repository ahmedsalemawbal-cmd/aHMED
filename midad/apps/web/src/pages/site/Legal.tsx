import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { fmtDate } from '../../lib/format'
import { Badge, Button, Card } from '../../ui/kit'
import { IcShield, IcPuzzle, IcClock, IcMail } from '../../ui/icons'

const UPDATED_AT = '2026-08-01'

type Section = { id: string; title: string; paras: string[]; bullets?: { head: string; body: string }[] }

const TERMS: Section[] = [
  {
    id: 'definitions',
    title: 'التعريفات',
    paras: [
      '«المنصّة» تعني مِداد وموقعها وتطبيقها وإضافتها للمتصفّح. و«المشترك» هو الجهة صاحبة الاشتراك: مدرسةً كانت أو معلّمًا مستقلًّا. و«العضو» هو كلّ مستخدمٍ يعمل تحت حساب المشترك بدورٍ محدّد.',
      '«الخدمتان» هما: قوالب الملفّات المدرسية التي تُملأ وتُصدَّر، وجداول نور التي تُنقل إلى حساب المشترك عبر إضافة المتصفّح. وهما خدمتان مستقلّتان لا يشترط استعمال إحداهما لاستعمال الأخرى.',
    ],
  },
  {
    id: 'service',
    title: 'طبيعة الخدمة',
    paras: [
      'تقدّم المنصّة أدواتٍ لإعداد الملفّات المدرسية وعرض الجداول وتصديرها. وهي أداةٌ مساعِدة لا تحلّ محلّ الأنظمة الرسمية للوزارة أو إدارة التعليم، ولا تُعدّ جهةً معتمِدة لأيّ مستندٍ يصدر عنها.',
      'مسؤولية صحّة البيانات المُدخَلة ومطابقتها للواقع تقع على المشترك. والمنصّة تحفظ ما يُدخِله المستخدم وتُخرجه بالصيغة المطلوبة دون أن تتحقّق من مضمونه.',
      'للمنصّة أن تضيف قوالب أو تعدّلها أو توقف قالبًا لم يعد معتمدًا، على أن تبقى الملفّات التي أنشأها المشترك محفوظةً في حسابه.',
    ],
  },
  {
    id: 'account',
    title: 'الحساب والأعضاء',
    paras: [
      'يُنشأ الحساب برقم جوّالٍ سعوديّ يكون اسم الدخول، ويتحمّل المشترك مسؤولية حفظ كلمة المرور وكلّ ما يجري عبر حسابه.',
      'في اشتراك المدرسة يدعو صاحب الاشتراك أعضاء فريقه بحدود عدد المقاعد في باقته، ويحدّد دور كلّ عضو. وله إيقاف أيّ عضوٍ دون أن تُحذف ملفّاته.',
      'يُمنع مشاركة الحساب الواحد بين أكثر من شخصٍ للتحايل على عدد المقاعد، وللمنصّة إيقاف الحساب عند ثبوت ذلك.',
    ],
  },
  {
    id: 'trial',
    title: 'التجربة والاشتراك',
    paras: [
      'التجربة المجّانية سبعة أيّام من تاريخ إنشاء الحساب، تفتح مزايا الباقة كاملةً، ولا تتطلّب بيانات بطاقة، ولا تتجدّد تلقائيًّا.',
      'عند انتهاء التجربة أو الاشتراك دون تجديد، يتوقّف الإنشاء والتصدير الجديد، وتبقى الملفّات والجداول المحفوظة في الحساب ويمكن الاطّلاع عليها بعد التجديد.',
      'مدّة الاشتراك ومزاياه — من مقاعد وفئات قوالب وحصّة تحسينٍ شهرية — هي المعلنة في صفحة الأسعار وقت الشراء.',
    ],
  },
  {
    id: 'payment',
    title: 'الدفع والفواتير',
    paras: [
      'يتمّ السداد بالتحويل البنكيّ إلى الحساب المعلن في صفحة السداد، ويرفع المشترك إيصال التحويل على الفاتورة الصادرة له.',
      'يُفعّل الاشتراك بعد اعتماد السداد، وتُصدَر فاتورةٌ برقمٍ وتاريخ وقيمةٍ وضريبة تبقى محفوظةً في حساب المشترك.',
      'المبالغ المدفوعة غير مستردّة بعد تفعيل الاشتراك، ما لم يكن التوقّف راجعًا إلى خللٍ جوهريّ من المنصّة يمنع الانتفاع بالخدمة.',
    ],
  },
  {
    id: 'use',
    title: 'الاستعمال المقبول',
    paras: [
      'يلتزم المستخدم باستعمال المنصّة في أغراض العمل المدرسيّ المشروعة، وبعدم رفع محتوًى مخالفٍ للأنظمة أو ماسٍّ بخصوصية الطلاب وأسرهم.',
      'يُمنع محاولة اختراق المنصّة أو تعطيلها أو سحب بياناتها آليًّا، كما يُمنع استعمال إضافة نور بطريقةٍ تخالف أنظمة النظام المصدر أو صلاحيات المستخدم فيه.',
      'المستخدم مسؤول عن ألّا يُدخل في المنصّة بياناتٍ لا يملك صلاحية الاطّلاع عليها في نطاق عمله.',
    ],
  },
  {
    id: 'ip',
    title: 'الملكية الفكرية',
    paras: [
      'تصميم المنصّة وشِفرتها وقوالبها وعلامتها التجارية مملوكةٌ لمِداد، ولا يجوز نسخها أو إعادة بيعها أو توزيعها خارج نطاق الاشتراك.',
      'أمّا البيانات التي يُدخلها المشترك والملفّات التي يُنشئها فهي ملكه، وله تصديرها والاحتفاظ بها. ولا تستعملها المنصّة لغير تشغيل الخدمة له.',
    ],
  },
  {
    id: 'liability',
    title: 'المسؤولية والإنهاء والأنظمة المطبّقة',
    paras: [
      'تُبذل العناية اللازمة لاستمرار الخدمة، دون ضمان خلوّها من الانقطاع أو الخلل. ولا تتحمّل المنصّة أضرارًا غير مباشرة ناشئة عن قرارٍ إداريّ اتُّخذ بناءً على ملفٍّ أُعدّ عبرها.',
      'للمنصّة إيقاف حسابٍ يخالف هذه الشروط بعد إشعار المشترك، وللمشترك إنهاء اشتراكه في أيّ وقت مع بقاء أثر المدّة المدفوعة حتّى نهايتها.',
      'تخضع هذه الشروط للأنظمة المعمول بها في المملكة العربية السعودية، ويُرجع في أيّ نزاعٍ إلى الجهات القضائية المختصّة فيها.',
    ],
  },
]

const PRIVACY: Section[] = [
  {
    id: 'collect',
    title: 'ما الذي نجمعه',
    paras: [
      'بيانات الحساب: الاسم ورقم الجوّال والدور الوظيفيّ، والبريد الإلكترونيّ إن أضفتَه اختياريًّا، وبيانات المشترك كاسم المدرسة ومدينتها وإدارتها التعليمية.',
      'محتوى عملك: الملفّات التي تُنشئها من القوالب، والجداول التي تصل من نور، وحالة كلٍّ منها وتواريخ تعديلها.',
      'بيانات تشغيلية محدودة: وقت آخر دخول، والفواتير وحالتها، وسجلّ الأحداث الإدارية داخل حساب مشتركك.',
    ],
  },
  {
    id: 'why',
    title: 'لماذا نجمعها',
    paras: [
      'لتشغيل الخدمة نفسها: حفظ ملفّاتك، وتصديرها، وربط أعضاء فريقك بمشتركهم، وتحديد ما تفتحه باقتك.',
      'لإصدار الفواتير وإدارة الاشتراك، وللردّ على رسائلك حين تراسلنا.',
      'لا نبيع بياناتك، ولا نستعملها في إعلاناتٍ موجّهة، ولا نطّلع على محتوى ملفّاتك إلّا بطلبٍ منك لحلّ مشكلةٍ تقنية.',
    ],
  },
  {
    id: 'noor',
    title: 'إضافة نور — بالتفصيل',
    paras: [
      'إضافة المتصفّح هي الجزء الأكثر حساسية، ولذلك نفصّل عملها هنا صراحةً:',
    ],
    bullets: [
      {
        head: 'ما الذي تقرؤه',
        body: 'تقرأ الجدول المعروض في الصفحة التي تفتحها أنت في نظام نور — رؤوس الأعمدة والصفوف الظاهرة أمامك — وترسله إلى حسابك في مِداد مربوطًا بمفتاح الربط الخاصّ بك.',
      },
      {
        head: 'متى تعمل',
        body: 'لا تعمل إلّا في اللحظة التي تضغط فيها زرّ «أرسل إلى مِداد» بنفسك. لا تعمل في الخلفية، ولا عند فتح المتصفّح، ولا في أيّ موقعٍ آخر غير صفحات النظام المصدر.',
      },
      {
        head: 'ما الذي لا تفعله',
        body: 'لا تطلب كلمة مرور نور ولا تقرأ حقولها ولا تخزّنها، ولا تتصفّح النظام نيابةً عنك، ولا تفتح صفحاتٍ لم تفتحها أنت، ولا تنقل شيئًا بعد إلغائك لمفتاح الربط — والإلغاء يسري فورًا.',
      },
    ],
  },
  {
    id: 'files',
    title: 'ملفّاتك وجداولك',
    paras: [
      'ملفّاتك وجداولك محصورة في حساب مشتركك، وتحكمها سياسات وصولٍ مطبّقة على مستوى قاعدة البيانات، فلا يطّلع عليها مشتركٌ آخر.',
      'داخل مدرستك يرى العضو ما يخصّ دوره وما شاركه معه صاحب الاشتراك. وإيقاف عضوٍ يمنعه من الدخول دون أن يحذف ما أنشأه.',
    ],
  },
  {
    id: 'sharing',
    title: 'المشاركة مع أطراف ثالثة',
    paras: [
      'نستعين بمزوّدي بنيةٍ تقنية لتشغيل قاعدة البيانات والاستضافة، ويعالجون البيانات نيابةً عنّا وبتعليماتنا فقط.',
      'عند استعمال مساعد تحسين الصياغة، يُرسَل النصّ الذي تحدّده أنت إلى مزوّد خدمة الذكاء الاصطناعيّ لإعادة صياغته، ولا تُرسل بقيّة ملفّك ولا بيانات حسابك.',
      'قد نُفصح عن بياناتٍ محدّدة إذا طلبتها جهةٌ نظامية مختصّة وفق الأنظمة المعمول بها في المملكة.',
    ],
  },
  {
    id: 'retention',
    title: 'مدّة الحفظ',
    paras: [
      'تُحفظ ملفّاتك وجداولك ما دام حسابك قائمًا، حتّى بعد انتهاء الاشتراك، لتجدها كما تركتها عند التجديد.',
      'الفواتير وسجلّات الأحداث المالية تُحفظ للمدّة التي تقتضيها الأنظمة المحاسبية والضريبية.',
      'عند طلبك حذف الحساب تُحذف بياناتك ومحتواك خلال مدّةٍ معقولة، ما لم يوجد التزامٌ نظاميّ بحفظ بعضها.',
    ],
  },
  {
    id: 'rights',
    title: 'حقوقك',
    paras: [
      'لك الاطّلاع على بياناتك وتصحيحها من صفحة الحساب، وتصدير ملفّاتك بصيغها الثلاث في أيّ وقت.',
      'لك إلغاء مفتاح ربط نور فورًا، ولك طلب حذف حسابك ومحتواه بالتواصل معنا من صفحة التواصل.',
    ],
  },
  {
    id: 'security',
    title: 'الأمان والتواصل',
    paras: [
      'الاتّصال بالمنصّة مشفّر، وكلمات المرور مخزّنة بصيغةٍ لا تُقرأ، والوصول إلى البيانات محكومٌ بسياساتٍ على مستوى الصفوف لا على مستوى الواجهة فقط.',
      'لأيّ سؤالٍ عن هذه السياسة أو طلبٍ يتعلّق ببياناتك، راسِلنا عبر صفحة التواصل أو على بريد الدعم، ونردّ في أوقات العمل المعلنة.',
    ],
  },
]

function useNarrow(px = 900) {
  const [narrow, setNarrow] = useState(() => {
    try { return window.matchMedia(`(max-width: ${px}px)`).matches } catch { return false }
  })
  useEffect(() => {
    let mq: MediaQueryList
    try { mq = window.matchMedia(`(max-width: ${px}px)`) } catch { return }
    const on = () => setNarrow(mq.matches)
    on()
    mq.addEventListener?.('change', on)
    return () => mq.removeEventListener?.('change', on)
  }, [px])
  return narrow
}

export default function Legal({ kind }: { kind: 'terms' | 'privacy' }) {
  const { general } = useApp()
  const narrow = useNarrow()
  const sections = kind === 'terms' ? TERMS : PRIVACY
  const title = kind === 'terms' ? 'الشروط والأحكام' : 'سياسة الخصوصية'
  const lead = kind === 'terms'
    ? 'الشروط التي تحكم استعمالك لمنصّة مِداد بخدمتيها: قوالب الملفّات المدرسية، وجداول نور.'
    : 'ما الذي نجمعه من بياناتك، ولماذا، وكيف تعمل إضافة نور بالضبط — مكتوبًا بلا مواربة.'

  const go = (id: string) => {
    const el = document.getElementById(id)
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  const index = (
    <nav
      aria-label="فهرس الأقسام"
      style={{
        position: narrow ? 'static' : 'sticky',
        insetBlockStart: 84,
        alignSelf: 'start',
      }}
    >
      <Card className="mdd-col" style={{ gap: 4 }}>
        <span style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--mdd-text-3)', padding: '2px 8px 6px' }}>
          الأقسام
        </span>
        {sections.map((s, i) => (
          <a
            key={s.id}
            href={`#${s.id}`}
            onClick={(e) => { e.preventDefault(); go(s.id) }}
            className="mdd-row"
            style={{
              gap: 9, padding: '9px 8px', borderRadius: 'var(--mdd-r-sm)',
              fontSize: 12.5, color: 'var(--mdd-text-2)', lineHeight: 1.5,
            }}
          >
            <span className="mdd-num" style={{ color: 'var(--mdd-accent)', fontWeight: 700, flex: 'none' }}>{i + 1}</span>
            <span style={{ minWidth: 0 }}>{s.title}</span>
          </a>
        ))}
      </Card>
    </nav>
  )

  return (
    <>
      <section className="mdd-section mdd-section--tight" style={{ paddingBlockEnd: 0 }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-4)', alignItems: 'flex-start' }}>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
            <span className="mdd-eyebrow">{kind === 'terms' ? 'وثيقة قانونية' : 'الخصوصية'}</span>
            <Badge tone="neutral" dot>آخر تحديث: {fmtDate(UPDATED_AT)}</Badge>
          </div>
          <h1 className="mdd-hero-title" style={{ fontSize: 36 }}>{title}</h1>
          <p className="mdd-hero-sub">{lead}</p>
        </div>
      </section>

      <section className="mdd-section">
        <div
          className="mdd-site-wrap"
          style={{
            display: 'grid',
            gap: 'var(--mdd-s-6)',
            gridTemplateColumns: narrow ? 'minmax(0, 1fr)' : '230px minmax(0, 1fr)',
            alignItems: 'start',
          }}
        >
          {index}

          <div className="mdd-col" style={{ gap: 'var(--mdd-s-7)' }}>
            {sections.map((s, i) => (
              <article key={s.id} id={s.id} style={{ scrollMarginBlockStart: 84 }}>
                <h2 style={{ fontSize: 21, marginBlockEnd: 'var(--mdd-s-3)' }}>
                  <span className="mdd-num" style={{ color: 'var(--mdd-accent)' }}>{i + 1}</span>
                  <span style={{ marginInlineStart: 10 }}>{s.title}</span>
                </h2>

                <div className="mdd-col" style={{ gap: 'var(--mdd-s-3)' }}>
                  {s.paras.map((p, j) => (
                    <p key={j} className="mdd-prose" style={{ fontSize: 14, maxWidth: '75ch' }}>{p}</p>
                  ))}

                  {s.bullets && (
                    <div className="mdd-col" style={{ gap: 12, marginBlockStart: 4 }}>
                      {s.bullets.map((b, j) => (
                        <Card key={j} className="mdd-col" style={{ gap: 8 }}>
                          <span className="mdd-row" style={{ gap: 9, fontWeight: 700, fontSize: 14 }}>
                            <span style={{ color: 'var(--mdd-accent)', display: 'flex' }}>
                              {j === 0 ? <IcPuzzle size={16} /> : j === 1 ? <IcClock size={16} /> : <IcShield size={16} />}
                            </span>
                            {b.head}
                          </span>
                          <p className="mdd-prose" style={{ fontSize: 13.5, maxWidth: '75ch' }}>{b.body}</p>
                        </Card>
                      ))}
                    </div>
                  )}
                </div>
              </article>
            ))}

            <Card className="mdd-col" style={{ gap: 12 }}>
              <h3 style={{ fontSize: 15 }}>سؤالٌ عن هذه الوثيقة؟</h3>
              <p className="mdd-prose" style={{ fontSize: 13.5, maxWidth: '75ch' }}>
                راسِلنا وسنوضّح لك أيّ بندٍ فيها. بريد الدعم:{' '}
                <span className="mdd-mono">{general.email}</span>
              </p>
              <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
                <Link to="/contact"><Button auto variant="primary" icon={<IcMail size={14} />}>تواصل معنا</Button></Link>
                <Link to={kind === 'terms' ? '/privacy' : '/terms'}>
                  <Button auto variant="secondary">
                    {kind === 'terms' ? 'سياسة الخصوصية' : 'الشروط والأحكام'}
                  </Button>
                </Link>
              </div>
            </Card>
          </div>
        </div>
      </section>
    </>
  )
}
