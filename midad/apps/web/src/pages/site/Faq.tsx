import React, { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { fmtNum } from '../../lib/format'
import { Button, EmptyState, SearchInput } from '../../ui/kit'
import { IcSearch, IcWhatsapp } from '../../ui/icons'
import Accordion from './Accordion'

type Item = { q: string; a: string }
type Group = { key: string; title: string; items: Item[] }

const GROUPS: Group[] = [
  {
    key: 'billing',
    title: 'الاشتراك والدفع',
    items: [
      {
        q: 'كم مدّة التجربة؟ وهل تحتاج بطاقة؟',
        a: 'التجربة سبعة أيّام كاملة تفتح كلّ المزايا، ولا نطلب فيها أيّ بيانات بطاقة. تُنشئ الحساب برقم جوّالك وتبدأ فورًا، وإذا انتهت المدّة ولم تشترك بقيت ملفّاتك محفوظةً في حسابك.',
      },
      {
        q: 'كيف أدفع قيمة الاشتراك؟',
        a: 'الدفع بالتحويل البنكيّ إلى حساب المنصّة المعروض في صفحة السداد. بعد التحويل ترفع صورة الإيصال على الفاتورة، فتُراجع وتُعتمد ويُفعّل اشتراكك.',
      },
      {
        q: 'هل تصلني فاتورة رسمية؟',
        a: 'نعم. تُصدَر فاتورةٌ برقمٍ وتاريخ وقيمةٍ وضريبة عند طلب الاشتراك، وتبقى محفوظةً في صفحة الفواتير داخل حسابك، تفتحها وتطبعها متى شئت.',
      },
      {
        q: 'ما الفرق بين باقة المدرسة وباقة المعلّم؟',
        a: 'باقة المدرسة متعدّدة المقاعد: يدعو صاحبها فريقه — الوكيل والمعلّمين والموجّه — ولكلٍّ حسابه وقوالب فئته. باقة المعلّم مقعدٌ واحد لمن يعمل وحده. تفاصيل المقاعد والفئات والحصص كلّها في جدول المقارنة بصفحة الأسعار.',
      },
    ],
  },
  {
    key: 'templates',
    title: 'القوالب',
    items: [
      {
        q: 'ما الذي أحصل عليه في القالب؟',
        a: 'تحصل على ملفٍّ مدرسيّ مبنيّ مسبقًا بحقولٍ مسمّاة بالعربية بدل الصفحة البيضاء. تملأ الحقول فتتكوّن الورقة أمامك بمعاينةٍ حيّة كما ستُطبع، ثمّ تصدّرها.',
      },
      {
        q: 'بأيّ صيغةٍ أصدّر الملفّ؟',
        a: 'ثلاث صيغ من الملفّ نفسه: PDF للطباعة والاعتماد، ووورد حين تحتاج تعديلًا يدويًّا قبل الاعتماد، وإكسل حين يكون الملفّ جدولًا يحتاج فرزًا أو معادلات.',
      },
      {
        q: 'هل يُحفظ عملي إذا أغلقت المتصفّح؟',
        a: 'نعم. الحفظ تلقائيّ كلّما كتبت، والملفّ يبقى في «ملفّاتي» بحالته الأخيرة — مسوّدةً أو مكتملًا — تفتحه من أيّ جهازٍ بحسابك نفسه.',
      },
      {
        q: 'ما دور الذكاء الاصطناعيّ في القوالب؟',
        a: 'مساعد الصياغة يعيد كتابة الفقرة التي تحدّدها بلغةٍ تربوية رسمية دون أن يضيف واقعةً لم تذكرها. يعرض عليك النصّ المقترح، وأنت تقبله أو ترفضه. لكلّ باقة حصّةٌ شهرية معلومة من التحسينات.',
      },
    ],
  },
  {
    key: 'noor',
    title: 'جداول نور',
    items: [
      {
        q: 'كيف ينتقل الجدول من نور إلى مِداد؟',
        a: 'تثبّت إضافة مِداد في متصفّح كروم أو إيدج مرّةً واحدة وتلصق فيها مفتاح الربط من حسابك. بعدها تفتح الكشف في نور كالمعتاد وتضغط «أرسل إلى مِداد»، فيصل الجدول إلى حسابك خلال ثوانٍ.',
      },
      {
        q: 'هل تعرف الإضافة كلمة مرور نور؟',
        a: 'لا. تسجيل الدخول يتمّ في صفحة نور نفسها، والإضافة لا تطلب كلمة المرور ولا تقرأ حقولها ولا تخزّنها. كذلك لا تعمل الإضافة إلّا في اللحظة التي تضغط فيها الزرّ بنفسك، ويمكنك إلغاء مفتاح الربط في أيّ وقتٍ فتتوقّف فورًا.',
      },
      {
        q: 'أيّ الكشوف يمكن تنزيلها؟',
        a: 'كشف الطلاب وكشف الدرجات وكشف الحضور، بأعمدتها وصفوفها كما تظهر أمامك في نور. وبعد وصول الكشف تعرضه وتبحث فيه وتصدّره إكسل أو PDF أو وورد.',
      },
    ],
  },
  {
    key: 'account',
    title: 'الحساب والأمان',
    items: [
      {
        q: 'بماذا أسجّل الدخول؟',
        a: 'برقم جوّالك وكلمة المرور. الجوّال هو اسم الدخول في مِداد، ولا نطلب بريدًا إلكترونيًّا لإنشاء الحساب — البريد اختياريّ للتنبيهات فقط.',
      },
      {
        q: 'كيف أضيف أعضاء فريق المدرسة؟',
        a: 'من صفحة «الفريق» في حسابك يضيف صاحب الاشتراك عضوًا برقم جوّاله ودوره — وكيل أو معلّم أو موجّه — فيرى كلٌّ قوالب فئته. عدد الأعضاء محكومٌ بعدد مقاعد باقتك، ويمكن إيقاف عضوٍ دون حذف ملفّاته.',
      },
      {
        q: 'من يستطيع الاطّلاع على ملفّاتي؟',
        a: 'ملفّاتك وجداولك محصورة في حساب مشتركك، وتحكمها سياسات وصولٍ على مستوى قاعدة البيانات: لا يرى بياناتك مشتركٌ آخر. أعضاء فريق مدرستك يرون ما يخصّ عملهم داخل المشترك نفسه.',
      },
    ],
  },
]

const ALL_COUNT = GROUPS.reduce((n, g) => n + g.items.length, 0)

export default function Faq() {
  const [q, setQ] = useState('')

  const term = q.trim()
  const groups = useMemo(() => {
    if (!term) return GROUPS
    return GROUPS
      .map((g) => ({ ...g, items: g.items.filter((it) => it.q.includes(term) || it.a.includes(term)) }))
      .filter((g) => g.items.length > 0)
  }, [term])

  const found = groups.reduce((n, g) => n + g.items.length, 0)

  return (
    <>
      <section className="mdd-section mdd-section--tight">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-5)', alignItems: 'flex-start' }}>
          <span className="mdd-eyebrow">الأسئلة الشائعة</span>
          <h1 className="mdd-hero-title" style={{ fontSize: 38 }}>إجاباتٌ مباشرة قبل أن تسأل</h1>
          <p className="mdd-hero-sub">
            <span className="mdd-num">{fmtNum(ALL_COUNT)}</span> سؤالًا عن الاشتراك والقوالب وجداول نور والحساب.
            اكتب كلمةً في البحث لتصل إلى سؤالك مباشرةً.
          </p>
          <div style={{ width: '100%', maxWidth: 520 }}>
            <SearchInput value={q} onChange={setQ} placeholder="ابحث في الأسئلة — مثال: فاتورة، نور، تصدير" />
          </div>
          {term && (
            <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>
              <span className="mdd-num">{fmtNum(found)}</span> نتيجة من أصل <span className="mdd-num">{fmtNum(ALL_COUNT)}</span>
            </span>
          )}
        </div>
      </section>

      <section className="mdd-section" style={{ paddingBlockStart: 0 }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-7)' }}>
          {found === 0 ? (
            <EmptyState
              art={<IcSearch size={62} />}
              title={`لم نجد سؤالًا يطابق «${term}»`}
              line="جرّب كلمةً أقصر، أو اسأَلنا مباشرةً وسنجيبك في وقت العمل."
              action={
                <div className="mdd-row mdd-row--wrap" style={{ gap: 10, justifyContent: 'center' }}>
                  <Button auto variant="secondary" onClick={() => setQ('')}>امسح البحث</Button>
                  <Link to="/contact"><Button auto variant="primary" icon={<IcWhatsapp size={14} />}>اسأَلنا مباشرةً</Button></Link>
                </div>
              }
            />
          ) : (
            groups.map((g) => (
              <div key={g.key} className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
                <div className="mdd-row" style={{ gap: 10 }}>
                  <h2 className="mdd-h2" style={{ fontSize: 22 }}>{g.title}</h2>
                  <span className="mdd-num" style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>{fmtNum(g.items.length)}</span>
                </div>
                <div className="mdd-col" style={{ gap: 10, maxWidth: 880 }}>
                  {g.items.map((it) => (
                    <Accordion key={it.q} q={it.q} defaultOpen={!!term}>{it.a}</Accordion>
                  ))}
                </div>
              </div>
            ))
          )}
        </div>
      </section>

      <section className="mdd-section mdd-section--tight" style={{ background: 'var(--mdd-sunken)' }}>
        <div
          className="mdd-site-wrap mdd-row mdd-row--between mdd-row--wrap"
          style={{ gap: 'var(--mdd-s-4)' }}
        >
          <div className="mdd-col" style={{ gap: 8, minWidth: 0 }}>
            <h2 style={{ fontSize: 19 }}>لم أجد جوابي</h2>
            <p className="mdd-prose" style={{ fontSize: 13.5 }}>
              اكتب لنا سؤالك ونردّ عليك في أوقات العمل — على الواتساب أو البريد.
            </p>
          </div>
          <Link to="/contact"><Button auto size="lg" variant="primary">تواصل معنا</Button></Link>
        </div>
      </section>
    </>
  )
}
