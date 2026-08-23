import React from 'react'
import { Link } from 'react-router-dom'
import { Badge, Button, Card } from '../../ui/kit'
import {
  IcShield, IcKey, IcPuzzle, IcTable, IcDownload, IcPrint, IcFiles, IcChevron, IcUser, IcClock, IcChart,
} from '../../ui/icons'

const ON_DEEP = 'oklch(1 0 0 / .95)'
const ON_DEEP_2 = 'oklch(1 0 0 / .72)'

export default function ServiceNoor() {
  return (
    <>
      {/* ============ البطل ============ */}
      <section className="mdd-section mdd-section--tight">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-5)', alignItems: 'flex-start' }}>
          <span className="mdd-eyebrow">الخدمة الثانية · نور</span>
          <h1 className="mdd-hero-title" style={{ fontSize: 36 }}>جداول نور في حسابك — بضغطة واحدة</h1>
          <p className="mdd-hero-sub">
            بدل نسخ الكشف خليّةً خليّة، تفتح الكشف في نور كما تفعل دائمًا، وتضغط زرًّا واحدًا،
            فيصل الجدول إلى حسابك في مِداد جاهزًا للعرض والتصدير.
          </p>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)' }}>
            <Link to="/join"><Button auto size="lg" variant="primary">جرّب سبعة أيّام مجّانًا</Button></Link>
            <Link to="/pricing"><Button auto size="lg" variant="secondary">الأسعار</Button></Link>
          </div>
        </div>
      </section>

      {/* ============ ثلاث خطوات ============ */}
      <section className="mdd-section" style={{ background: 'var(--mdd-sunken)' }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <h2 className="mdd-h2">ثلاث خطوات — مرّةً واحدة ثمّ تنسى الإعداد</h2>
          <div className="mdd-grid mdd-grid--3">
            <StepCard n={1} title="ثبّت الإضافة" art={<ArtPuzzle />}
              line="نزّل إضافة مِداد لمتصفّح كروم أو إيدج، وألصق مفتاح الربط الذي يظهر في حسابك مرّةً واحدة." />
            <StepCard n={2} title="افتح نور" art={<ArtBrowser />}
              line="ادخل نظام نور بحسابك المعتاد، وافتح الكشف الذي تريده كما تفتحه كلّ يوم." />
            <StepCard n={3} title="اضغط أرسل" art={<ArtSend />}
              line="يظهر زرّ «أرسل إلى مِداد» فوق الجدول. اضغطه، فيصل الكشف إلى حسابك خلال ثوانٍ." />
          </div>
          <p className="mdd-prose" style={{ fontSize: 13 }}>
            الإضافة تقرأ الجدول المعروض أمامك في الصفحة فقط — لا تفتح صفحاتٍ أخرى ولا تتصفّح النظام نيابةً عنك.
          </p>
        </div>
      </section>

      {/* ============ ما الذي يُنزَّل ============ */}
      <section className="mdd-section">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <div className="mdd-col" style={{ gap: 10 }}>
            <h2 className="mdd-h2">ما الذي يُنزَّل؟</h2>
            <p className="mdd-prose" style={{ fontSize: 14.5 }}>
              الكشوف التي تستعملها في عملك اليوميّ، بأعمدتها وصفوفها كما هي في نور.
            </p>
          </div>
          <div className="mdd-grid mdd-grid--3">
            <WhatCard
              icon={<IcUser size={20} />}
              title="كشف الطلاب"
              line="أسماء الطلاب وأرقامهم وصفوفهم، جاهزةً لأيّ ملفٍّ يحتاج قائمة الفصل."
              cols={['رقم الطالب', 'اسم الطالب', 'الصفّ', 'الفصل']}
            />
            <WhatCard
              icon={<IcChart size={20} />}
              title="كشف الدرجات"
              line="درجات المادّة موزّعةً على بنودها، بلا إعادة إدخالٍ ولا أخطاء نسخ."
              cols={['اسم الطالب', 'أعمال السنة', 'الاختبار', 'المجموع']}
            />
            <WhatCard
              icon={<IcClock size={20} />}
              title="كشف الحضور"
              line="سجلّ الحضور والغياب والتأخّر بتواريخه، لمتابعة المواظبة وإعداد الإشعارات."
              cols={['اسم الطالب', 'التاريخ', 'الحالة', 'الملاحظة']}
            />
          </div>
        </div>
      </section>

      {/* ============ الأمان ============ */}
      <section className="mdd-section" style={{ background: 'var(--mdd-sunken)' }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <div className="mdd-col" style={{ gap: 10 }}>
            <span className="mdd-eyebrow">الأمان</span>
            <h2 className="mdd-h2">ثلاث حقائق صريحة عن الإضافة</h2>
            <p className="mdd-prose" style={{ fontSize: 14.5 }}>
              حسابك في نور أمانةٌ لا نقترب منها. هذا بالضبط ما تفعله الإضافة وما لا تفعله.
            </p>
          </div>

          <div className="mdd-grid mdd-grid--3">
            <SafetyCard
              icon={<IcPuzzle size={20} />}
              title="لا تعمل إلّا حين تضغط"
              line="الإضافة ساكنة تمامًا حتّى تفتح كشفًا وتضغط «أرسل إلى مِداد» بنفسك. لا تعمل في الخلفية، ولا تنقل شيئًا دون ضغطتك."
            />
            <SafetyCard
              icon={<IcShield size={20} />}
              title="لا تقرأ كلمة مرور نور"
              line="تسجيل دخولك إلى نور يتمّ في صفحة نور نفسها. الإضافة لا تطلب كلمة المرور ولا تقرأ حقولها ولا تخزّنها."
            />
            <SafetyCard
              icon={<IcKey size={20} />}
              title="المفتاح يُلغى في أيّ لحظة"
              line="الربط يقوم على مفتاحٍ تولّده أنت من حسابك، وتُلغيه بضغطة واحدة متى شئت، فتتوقّف الإضافة عن الإرسال فورًا."
            />
          </div>
        </div>
      </section>

      {/* ============ التصدير ============ */}
      <section className="mdd-section">
        <div className="mdd-site-wrap mdd-grid mdd-grid--2" style={{ gap: 'var(--mdd-s-7)', alignItems: 'start' }}>
          <div className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
            <h2 className="mdd-h2">بعد أن يصل الجدول</h2>
            <p className="mdd-prose" style={{ fontSize: 14.5 }}>
              يُحفظ الكشف في حسابك باسمه وتاريخه وعدد صفوفه. تفتحه متى شئت، وتعرضه على الشاشة،
              وتبحث فيه، ثمّ تصدّره بالصيغة التي يطلبها منك العمل.
            </p>
            <ul className="mdd-col" style={{ gap: 10, listStyle: 'none', padding: 0, margin: 0 }}>
              <ExportLine icon={<IcTable size={16} />} title="إكسل"
                line="جدولٌ حقيقيّ بأعمدةٍ وصفوف، يقبل الفرز والمعادلات ورفعه في الأنظمة." />
              <ExportLine icon={<IcPrint size={16} />} title="PDF"
                line="نسخةٌ ثابتة جاهزة للطباعة والاعتماد والتوقيع." />
              <ExportLine icon={<IcFiles size={16} />} title="وورد"
                line="ملفٌّ قابل للتحرير حين تحتاج إلى إضافة ملاحظاتٍ أو ترويسة." />
            </ul>
          </div>

          <Card className="mdd-col mdd-card--pad-lg" style={{ gap: 14 }}>
            <div className="mdd-row mdd-row--between">
              <h3 style={{ fontSize: 15 }}>كشف طلاب — الصفّ الأوّل / فصل <span className="mdd-num">2</span></h3>
              <Badge tone="success" dot>وصل</Badge>
            </div>
            <div className="mdd-table-wrap mdd-table-wrap--cards">
              <table className="mdd-table">
                <thead>
                  <tr>
                    <th>رقم الطالب</th>
                    <th>اسم الطالب</th>
                    <th>الفصل</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td data-label="رقم الطالب"><span className="mdd-num">1042</span></td>
                    <td data-label="اسم الطالب">أحمد سالم الغامدي</td>
                    <td data-label="الفصل"><span className="mdd-num">1</span>/<span className="mdd-num">2</span></td>
                  </tr>
                  <tr>
                    <td data-label="رقم الطالب"><span className="mdd-num">1043</span></td>
                    <td data-label="اسم الطالب">نورة عبدالله القحطاني</td>
                    <td data-label="الفصل"><span className="mdd-num">1</span>/<span className="mdd-num">2</span></td>
                  </tr>
                  <tr>
                    <td data-label="رقم الطالب"><span className="mdd-num">1044</span></td>
                    <td data-label="اسم الطالب">فيصل ناصر الشهري</td>
                    <td data-label="الفصل"><span className="mdd-num">1</span>/<span className="mdd-num">2</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
              <Button auto size="sm" variant="secondary" icon={<IcDownload size={13} />} disabled>إكسل</Button>
              <Button auto size="sm" variant="secondary" icon={<IcDownload size={13} />} disabled>PDF</Button>
              <Button auto size="sm" variant="secondary" icon={<IcDownload size={13} />} disabled>وورد</Button>
            </div>
            <p style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
              نموذج توضيحيّ لشكل الجدول بعد وصوله — التصدير يعمل داخل حسابك.
            </p>
          </Card>
        </div>
      </section>

      {/* ============ الدعوة ============ */}
      <section style={{ background: 'var(--mdd-accent-deep)' }}>
        <div
          className="mdd-site-wrap mdd-row mdd-row--between mdd-row--wrap"
          style={{ gap: 'var(--mdd-s-5)', paddingBlock: 'var(--mdd-s-8)' }}
        >
          <div className="mdd-col" style={{ gap: 10, minWidth: 0 }}>
            <h2 className="mdd-h2" style={{ color: ON_DEEP }}>جرّب أوّل كشفٍ اليوم</h2>
            <p style={{ color: ON_DEEP_2, fontSize: 14.5, lineHeight: 1.8, maxWidth: '52ch' }}>
              أنشئ حسابك، ولّد مفتاح الربط، ونزّل الإضافة. الخطوات كلّها لا تتجاوز دقائق.
            </p>
          </div>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)' }}>
            <Link to="/join"><Button auto size="lg" variant="primary">ابدأ التجربة</Button></Link>
            <Link to="/faq">
              <Button auto size="lg" variant="secondary" icon={<IcChevron size={14} />}>أسئلة نور</Button>
            </Link>
          </div>
        </div>
      </section>
    </>
  )
}

/* ---------------- بطاقات ---------------- */

function StepCard({ n, title, line, art }: { n: number; title: string; line: string; art: React.ReactNode }) {
  return (
    <Card className="mdd-col" style={{ gap: 14 }}>
      <div
        style={{
          background: 'var(--mdd-sunken)', border: '1px solid var(--mdd-border)',
          borderRadius: 'var(--mdd-r-md)', padding: 'var(--mdd-s-4)',
          display: 'grid', placeItems: 'center', color: 'var(--mdd-accent)',
        }}
      >
        {art}
      </div>
      <div className="mdd-row" style={{ gap: 10 }}>
        <span
          className="mdd-num"
          style={{
            width: 28, height: 28, borderRadius: 'var(--mdd-r-pill)', display: 'grid', placeItems: 'center',
            background: 'var(--mdd-accent)', color: 'var(--mdd-on-accent)', fontWeight: 700, fontSize: 12.5,
          }}
        >
          {n}
        </span>
        <h3 style={{ fontSize: 15.5 }}>{title}</h3>
      </div>
      <p className="mdd-prose" style={{ fontSize: 12.5 }}>{line}</p>
    </Card>
  )
}

function WhatCard({ icon, title, line, cols }: {
  icon: React.ReactNode; title: string; line: string; cols: string[]
}) {
  return (
    <Card className="mdd-col" style={{ gap: 12 }}>
      <span
        style={{
          width: 44, height: 44, borderRadius: 13, display: 'grid', placeItems: 'center',
          background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
        }}
      >
        {icon}
      </span>
      <h3 style={{ fontSize: 15.5 }}>{title}</h3>
      <p className="mdd-prose" style={{ fontSize: 12.5 }}>{line}</p>
      <div className="mdd-row mdd-row--wrap" style={{ gap: 6 }}>
        {cols.map((c) => <Badge key={c} tone="neutral">{c}</Badge>)}
      </div>
    </Card>
  )
}

function SafetyCard({ icon, title, line }: { icon: React.ReactNode; title: string; line: string }) {
  return (
    <Card className="mdd-col" style={{ gap: 12 }}>
      <span
        style={{
          width: 44, height: 44, borderRadius: 13, display: 'grid', placeItems: 'center',
          background: 'var(--mdd-success-soft)', color: 'var(--mdd-success-fg)',
        }}
      >
        {icon}
      </span>
      <h3 style={{ fontSize: 15.5 }}>{title}</h3>
      <p className="mdd-prose" style={{ fontSize: 12.5 }}>{line}</p>
    </Card>
  )
}

function ExportLine({ icon, title, line }: { icon: React.ReactNode; title: string; line: string }) {
  return (
    <li className="mdd-row" style={{ gap: 12, alignItems: 'flex-start' }}>
      <span
        style={{
          width: 34, height: 34, borderRadius: 'var(--mdd-r-sm)', display: 'grid', placeItems: 'center', flex: 'none',
          background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
        }}
      >
        {icon}
      </span>
      <span style={{ minWidth: 0 }}>
        <span style={{ display: 'block', fontWeight: 700, fontSize: 13.5 }}>{title}</span>
        <span style={{ display: 'block', fontSize: 12.5, color: 'var(--mdd-text-2)', lineHeight: 1.8 }}>{line}</span>
      </span>
    </li>
  )
}

/* ---------------- رسوم الخطوات ---------------- */

const sv = { stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, fill: 'none' }

function ArtPuzzle() {
  return (
    <svg width="132" height="84" viewBox="0 0 132 84" aria-hidden="true">
      <rect x="16" y="12" width="100" height="60" rx="8" {...sv} />
      <path d="M16 26h100" {...sv} />
      <circle cx="26" cy="19" r="2.2" {...sv} />
      <circle cx="34" cy="19" r="2.2" {...sv} />
      <path d="M60 40h12v6a5 5 0 0 0 10 0v-6h6v22H60Z" {...sv} />
      <path d="M42 46h10M42 54h14" {...sv} />
    </svg>
  )
}

function ArtBrowser() {
  return (
    <svg width="132" height="84" viewBox="0 0 132 84" aria-hidden="true">
      <rect x="16" y="12" width="100" height="60" rx="8" {...sv} />
      <path d="M16 26h100" {...sv} />
      <circle cx="26" cy="19" r="2.2" {...sv} />
      <path d="M34 36h64M34 46h64M34 56h44" {...sv} />
      <path d="M56 30v34M78 30v34" {...sv} opacity=".5" />
    </svg>
  )
}

function ArtSend() {
  return (
    <svg width="132" height="84" viewBox="0 0 132 84" aria-hidden="true">
      <rect x="16" y="12" width="70" height="60" rx="8" {...sv} />
      <path d="M16 26h70" {...sv} />
      <path d="M28 38h46M28 48h46M28 58h30" {...sv} />
      <path d="M92 42h26M100 34l-8 8 8 8" {...sv} />
      <circle cx="118" cy="42" r="8" {...sv} />
      <path d="m114.5 42 2.5 2.5 4.5-5" {...sv} />
    </svg>
  )
}
