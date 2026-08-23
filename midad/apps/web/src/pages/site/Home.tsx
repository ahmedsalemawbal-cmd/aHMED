import React, { useMemo, useRef } from 'react'
import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fetchTemplates } from '../../lib/data'
import { fmtMoney, fmtNum } from '../../lib/format'
import { Badge, Button, Card, Skeleton } from '../../ui/kit'
import { IcLibrary, IcTable, IcCheck, IcChevron, IcUser, IcTeam, IcBook, IcChart, IcClock } from '../../ui/icons'

const ON_DEEP = 'oklch(1 0 0 / .95)'
const ON_DEEP_2 = 'oklch(1 0 0 / .72)'

export default function Home() {
  const { plans, roles } = useApp()
  const howRef = useRef<HTMLDivElement>(null)
  const { data: templates, loading } = useAsync(fetchTemplates, [])

  const total = templates?.length || 0
  const sample = useMemo(() => (templates || []).slice(0, 6), [templates])
  const school = plans.find((p) => p.account_type === 'school') || null
  const teacher = plans.find((p) => p.account_type === 'teacher') || null

  return (
    <>
      {/* ============ البطل ============ */}
      <section className="mdd-section" style={{ paddingBlockEnd: 'var(--mdd-s-8)' }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)', alignItems: 'flex-start' }}>
          <span className="mdd-eyebrow">خدمتان مستقلّتان في حسابٍ واحد</span>
          <h1 className="mdd-hero-title">ملفّاتك المدرسية وجداول نور — في مكانٍ واحد</h1>
          <p className="mdd-hero-sub">
            املأ قوالب الملفّات المدرسية في المتصفّح وصدّرها PDF أو وورد أو إكسل،
            ونزّل كشوف نور إلى حسابك بضغطة واحدة. بلا برامج تُثبَّت على جهازك.
          </p>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)' }}>
            <Link to="/join"><Button auto size="lg" variant="primary">جرّب سبعة أيّام مجّانًا</Button></Link>
            <Button
              auto
              size="lg"
              variant="secondary"
              onClick={() => howRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
            >
              شاهد كيف يعمل
            </Button>
          </div>

          {/* شريط الثقة */}
          <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-5)', marginBlockStart: 'var(--mdd-s-3)' }}>
            <TrustItem icon={<IcCheck size={15} />} text="بلا بطاقة" />
            <TrustItem icon={<IcClock size={15} />} text="سبعة أيّام كاملة" />
            <TrustItem icon={<IcCheck size={15} />} text="يعمل على الجوّال" />
          </div>
        </div>
      </section>

      {/* ============ الخدمتان ============ */}
      <section className="mdd-section" style={{ background: 'var(--mdd-sunken)' }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <div className="mdd-col" style={{ gap: 10 }}>
            <h2 className="mdd-h2">خدمتان لا تلتقيان — تستعمل ما تحتاجه</h2>
            <p className="mdd-prose" style={{ fontSize: 14.5 }}>
              كلّ خدمةٍ لها شاشاتها وطريقتها. لا يُجبرك مِداد على استعمال إحداهما لتصل إلى الأخرى.
            </p>
          </div>

          <div className="mdd-grid mdd-grid--2">
            <BigService
              icon={<IcLibrary size={24} />}
              title="قوالب الملفّات المدرسية"
              line="قوالب جاهزة تُملأ داخل المتصفّح ثمّ تُصدَّر بصيغة رسمية جاهزة للطباعة أو الرفع."
              points={[
                'حقولٌ مرتّبة بدل الصفحة البيضاء',
                'تحسين الصياغة التربوية بالذكاء الاصطناعيّ',
                'تصدير PDF ووورد وإكسل من الملفّ نفسه',
              ]}
              to="/service/templates"
              foot={loading
                ? <Skeleton h={13} w={130} />
                : <><span className="mdd-num">{fmtNum(total)}</span> قالبًا منشورًا في المكتبة</>}
            />
            <BigService
              icon={<IcTable size={24} />}
              title="جداول نور"
              line="إضافة متصفّح تنقل الكشف المعروض أمامك في نور إلى حسابك في مِداد، ثمّ تعرضه وتصدّره."
              points={[
                'تعمل حين تضغط أنت — لا قبل ذلك',
                'لا تطلب كلمة مرور نور ولا تقرأها',
                'الجدول يبقى في حسابك تفتحه متى شئت',
              ]}
              to="/service/noor"
              foot={<>كشف الطلاب · كشف الدرجات · كشف الحضور</>}
            />
          </div>
        </div>
      </section>

      {/* ============ كيف يعمل ============ */}
      <section className="mdd-section" ref={howRef as any}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <h2 className="mdd-h2">كيف يعمل — ثلاث خطوات</h2>
          <div className="mdd-grid mdd-grid--3">
            <Step n={1} title="اختر قالبًا" line="افتح المكتبة، صفّها بدورك — مدير أو وكيل أو معلّم أو موجّه — واختر الملفّ الذي تحتاجه." />
            <Step n={2} title="املأه" line="حقولٌ واضحة بالعربية، وحفظٌ تلقائيّ كلّما كتبت، ومعاينةٌ حيّة للورقة كما ستُطبع." />
            <Step n={3} title="صدّره" line="اضغط تصدير واختر PDF للطباعة، أو وورد للتعديل، أو إكسل للجداول والأرقام." />
          </div>
        </div>
      </section>

      {/* ============ لمن مِداد ============ */}
      <section className="mdd-section" style={{ background: 'var(--mdd-sunken)' }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <div className="mdd-col" style={{ gap: 10 }}>
            <h2 className="mdd-h2">لمن مِداد؟</h2>
            <p className="mdd-prose" style={{ fontSize: 14.5 }}>
              المكتبة مقسّمة على <span className="mdd-num">{fmtNum(roles.length)}</span> فئاتٍ مهنيّة،
              فلا ترى إلّا ما يخصّ عملك.
            </p>
          </div>
          <div className="mdd-grid mdd-grid--4">
            <Who icon={<IcChart size={20} />} title="مدير المدرسة" line="خطط التشغيل، محاضر الاجتماعات، تقارير الأداء، وخطابات التكليف." />
            <Who icon={<IcTeam size={20} />} title="الوكيل" line="جداول الإشراف والانتظار، متابعة الغياب، وتنظيم الاختبارات." />
            <Who icon={<IcBook size={20} />} title="المعلّم" line="توزيع المنهج، تحضير الدروس، سجلّ المتابعة، وخطط علاج التأخّر الدراسيّ." />
            <Who icon={<IcUser size={20} />} title="الموجّه الطلابيّ" line="دراسة الحالة، بطاقات المتابعة السلوكية، ومحاضر مقابلة أولياء الأمور." />
          </div>
        </div>
      </section>

      {/* ============ عيّنة من المكتبة ============ */}
      <section className="mdd-section">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)' }}>
            <div className="mdd-col" style={{ gap: 8 }}>
              <h2 className="mdd-h2">عيّنة من المكتبة</h2>
              <p className="mdd-prose" style={{ fontSize: 14.5 }}>
                {loading
                  ? 'جارٍ تحميل القوالب…'
                  : <>ستّة قوالب من أصل <span className="mdd-num">{fmtNum(total)}</span> قالبًا منشورًا.</>}
              </p>
            </div>
            <Link to="/service/templates"><Button auto variant="secondary">كلّ ما في المكتبة</Button></Link>
          </div>

          {loading ? (
            <div className="mdd-grid mdd-grid--3">
              {Array.from({ length: 6 }).map((_, i) => (
                <Card key={i} className="mdd-col"><Skeleton h={15} w="72%" /><Skeleton h={11} w="46%" /></Card>
              ))}
            </div>
          ) : sample.length === 0 ? (
            <Card className="mdd-col" style={{ gap: 10 }}>
              <h3 style={{ fontSize: 15 }}>المكتبة تُبنى الآن</h3>
              <p className="mdd-prose" style={{ fontSize: 13 }}>
                نُضيف القوالب تباعًا. اشترك في التجربة وستصلك القوالب فور نشرها.
              </p>
            </Card>
          ) : (
            <div className="mdd-grid mdd-grid--3">
              {sample.map((t) => (
                <Card key={t.id} className="mdd-col" style={{ gap: 10 }}>
                  <div className="mdd-row" style={{ gap: 8 }}>
                    <Badge tone="accent">{roles.find((r) => r.key === t.category_key)?.name_ar || 'عامّ'}</Badge>
                    {t.is_new && <Badge tone="info">جديد</Badge>}
                  </div>
                  <h3 style={{ fontSize: 14.5 }}>{t.title}</h3>
                  <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
                    <span className="mdd-num">{t.fields?.length || 0}</span> حقلًا · نحو{' '}
                    <span className="mdd-num">{t.estimated_minutes}</span> دقائق
                  </span>
                </Card>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* ============ الأسعار مختصرةً ============ */}
      <section className="mdd-section" style={{ background: 'var(--mdd-sunken)' }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <div className="mdd-col" style={{ gap: 10 }}>
            <h2 className="mdd-h2">باقتان، لا أكثر</h2>
            <p className="mdd-prose" style={{ fontSize: 14.5 }}>
              سبعة أيّام تجربة كاملة بلا بطاقة، ثمّ تختار ما يناسبك.
            </p>
          </div>

          {plans.length === 0 ? (
            <Card className="mdd-col" style={{ gap: 12 }}>
              <h3 style={{ fontSize: 15 }}>الأسعار تُحدَّث الآن</h3>
              <p className="mdd-prose" style={{ fontSize: 13 }}>راسِلنا وسنرسل لك تفاصيل الباقات مباشرةً.</p>
              <Link to="/contact"><Button auto variant="primary">تواصل معنا</Button></Link>
            </Card>
          ) : (
            <div className="mdd-grid mdd-grid--2">
              {[school, teacher].filter(Boolean).map((p) => (
                <Card key={p!.id} className="mdd-col" style={{ gap: 14 }}>
                  <div className="mdd-row mdd-row--between">
                    <h3 style={{ fontSize: 17 }}>{p!.name_ar}</h3>
                    {p!.account_type === 'school' && <Badge tone="accent">الأكثر طلبًا</Badge>}
                  </div>
                  <div className="mdd-row" style={{ gap: 8, alignItems: 'baseline' }}>
                    <span className="mdd-num" style={{ fontSize: 30, fontWeight: 700 }}>{fmtMoney(p!.price_sar)}</span>
                    <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>
                      {p!.period_months === 12 ? 'سنويًّا' : <>لكلّ <span className="mdd-num">{p!.period_months}</span> أشهر</>}
                    </span>
                  </div>
                  <ul className="mdd-col" style={{ gap: 8, listStyle: 'none', padding: 0, margin: 0 }}>
                    {(p!.features_ar || []).slice(0, 3).map((f, i) => (
                      <li key={i} className="mdd-row" style={{ gap: 9, fontSize: 13, color: 'var(--mdd-text-2)' }}>
                        <span style={{ color: 'var(--mdd-success-fg)', display: 'flex' }}><IcCheck size={14} /></span>
                        <span>{f}</span>
                      </li>
                    ))}
                  </ul>
                </Card>
              ))}
            </div>
          )}

          <div>
            <Link to="/pricing"><Button auto variant="secondary" icon={<IcChevron size={14} />}>كلّ التفاصيل</Button></Link>
          </div>
        </div>
      </section>

      {/* ============ الدعوة الأخيرة ============ */}
      <section style={{ background: 'var(--mdd-accent-deep)', paddingBlock: 'var(--mdd-s-8)' }}>
        <div
          className="mdd-site-wrap mdd-row mdd-row--between mdd-row--wrap"
          style={{ gap: 'var(--mdd-s-5)', paddingBlock: 'var(--mdd-s-6)' }}
        >
          <div className="mdd-col" style={{ gap: 10, minWidth: 0 }}>
            <h2 className="mdd-h2" style={{ color: ON_DEEP }}>ابدأ اليوم — سبعة أيّام كاملة</h2>
            <p style={{ color: ON_DEEP_2, fontSize: 14.5, lineHeight: 1.8, maxWidth: '52ch' }}>
              بلا بطاقة، وبلا التزام. جرّب المكتبة وجداول نور، ثمّ قرّر.
            </p>
          </div>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)' }}>
            <Link to="/join"><Button auto size="lg" variant="primary">أنشئ حسابك</Button></Link>
            <Link to="/contact"><Button auto size="lg" variant="secondary">تحدّث إلينا</Button></Link>
          </div>
        </div>
      </section>
    </>
  )
}

/* ---------------- أجزاء الصفحة ---------------- */

function TrustItem({ icon, text }: { icon: React.ReactNode; text: string }) {
  return (
    <span className="mdd-row" style={{ gap: 8, fontSize: 13, fontWeight: 600, color: 'var(--mdd-text-2)' }}>
      <span style={{ color: 'var(--mdd-success-fg)', display: 'flex' }}>{icon}</span>
      {text}
    </span>
  )
}

function BigService({ icon, title, line, points, to, foot }: {
  icon: React.ReactNode; title: string; line: string; points: string[]; to: string; foot: React.ReactNode
}) {
  return (
    <Card className="mdd-col mdd-card--pad-lg" style={{ gap: 'var(--mdd-s-4)' }}>
      <span
        style={{
          width: 52, height: 52, borderRadius: 15, display: 'grid', placeItems: 'center',
          background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
        }}
      >
        {icon}
      </span>
      <div>
        <h3 style={{ fontSize: 19 }}>{title}</h3>
        <p className="mdd-prose" style={{ fontSize: 13.5, marginBlockStart: 8 }}>{line}</p>
      </div>
      <ul className="mdd-col" style={{ gap: 9, listStyle: 'none', padding: 0, margin: 0 }}>
        {points.map((p) => (
          <li key={p} className="mdd-row" style={{ gap: 9, fontSize: 13, color: 'var(--mdd-text-2)' }}>
            <span style={{ color: 'var(--mdd-accent)', display: 'flex' }}><IcCheck size={14} /></span>
            <span>{p}</span>
          </li>
        ))}
      </ul>
      <div
        className="mdd-row mdd-row--between mdd-row--wrap"
        style={{ gap: 'var(--mdd-s-3)', marginBlockStart: 'auto', paddingBlockStart: 'var(--mdd-s-3)' }}
      >
        <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{foot}</span>
        <Link to={to}><Button auto size="sm" variant="soft">تفاصيل</Button></Link>
      </div>
    </Card>
  )
}

function Step({ n, title, line }: { n: number; title: string; line: string }) {
  return (
    <Card className="mdd-col" style={{ gap: 12 }}>
      <span
        className="mdd-num"
        style={{
          width: 40, height: 40, borderRadius: 'var(--mdd-r-pill)', display: 'grid', placeItems: 'center',
          background: 'var(--mdd-accent)', color: 'var(--mdd-on-accent)', fontWeight: 700, fontSize: 16,
        }}
      >
        {n}
      </span>
      <h3 style={{ fontSize: 16 }}>{title}</h3>
      <p className="mdd-prose" style={{ fontSize: 13 }}>{line}</p>
    </Card>
  )
}

function Who({ icon, title, line }: { icon: React.ReactNode; title: string; line: string }) {
  return (
    <Card className="mdd-col" style={{ gap: 11 }}>
      <span style={{ color: 'var(--mdd-accent)', display: 'flex' }}>{icon}</span>
      <h3 style={{ fontSize: 15 }}>{title}</h3>
      <p className="mdd-prose" style={{ fontSize: 12.5 }}>{line}</p>
    </Card>
  )
}
