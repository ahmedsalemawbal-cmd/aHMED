import React, { useMemo } from 'react'
import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fetchTemplates } from '../../lib/data'
import { fmtNum } from '../../lib/format'
import { Badge, Button, Card, ErrorState, Skeleton } from '../../ui/kit'
import { IcCheck, IcSpark, IcPrint, IcEdit, IcTable, IcLibrary, IcFiles, IcChevron } from '../../ui/icons'

const ON_DEEP = 'oklch(1 0 0 / .95)'
const ON_DEEP_2 = 'oklch(1 0 0 / .72)'

const BEFORE = `الطالب ما ينتبه في الحصة ويطلع كثير برا الفصل ودرجاته نازلة في الرياضيات، كلمت أبوه مرة وما تغير شي، أحتاج خطة له.`

const AFTER = `يُظهر الطالب تشتّتًا في الانتباه خلال الحصّة، مع تكرار الاستئذان بالخروج، وانخفاضٍ ملحوظ في مستوى التحصيل بمادّة الرياضيات.
جرى التواصل مع وليّ الأمر مرّةً واحدة دون أثرٍ ملموس على السلوك.
وعليه تُقترح خطّة علاجية تقوم على: تقريب مجلس الطالب من المعلّم، وتجزئة المهمّة إلى خطواتٍ قصيرة، وتدوين متابعةٍ أسبوعية تُرسل لوليّ الأمر، مع إعادة تقييم الأثر بعد أربعة أسابيع.`

export default function ServiceTemplates() {
  const { roles } = useApp()
  const { data: templates, loading, error, reload } = useAsync(fetchTemplates, [])

  const total = templates?.length || 0
  const counts = useMemo(() => {
    const m = new Map<string, number>()
    for (const t of templates || []) m.set(t.category_key, (m.get(t.category_key) || 0) + 1)
    return m
  }, [templates])

  return (
    <>
      {/* ============ البطل ============ */}
      <section className="mdd-section mdd-section--tight">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-5)', alignItems: 'flex-start' }}>
          <span className="mdd-eyebrow">الخدمة الأولى · القوالب</span>
          <h1 className="mdd-hero-title" style={{ fontSize: 36 }}>ملفّات مدرسية جاهزة — تُملأ في دقائق</h1>
          <div className="mdd-hero-sub">
            {loading ? (
              <Skeleton h={16} w={280} />
            ) : error ? (
              'مكتبةٌ من القوالب المدرسية الجاهزة، مقسّمةً على الفئات المهنيّة.'
            ) : (
              <>
                <span className="mdd-num">{fmtNum(total)}</span> قالبًا منشورًا موزّعةً على{' '}
                <span className="mdd-num">{fmtNum(roles.length)}</span> فئاتٍ مهنيّة. تفتح القالب، تملأ حقوله،
                وتخرج بملفٍّ رسميّ جاهز للطباعة أو الرفع.
              </>
            )}
          </div>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)' }}>
            <Link to="/join"><Button auto size="lg" variant="primary">جرّب سبعة أيّام مجّانًا</Button></Link>
            <Link to="/pricing"><Button auto size="lg" variant="secondary">الأسعار</Button></Link>
          </div>
        </div>
      </section>

      {/* ============ أربع خطوات ============ */}
      <section className="mdd-section" style={{ background: 'var(--mdd-sunken)' }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <h2 className="mdd-h2">أربع خطوات من الفراغ إلى الملفّ</h2>
          <div className="mdd-grid mdd-grid--4">
            <Step n={1} icon={<IcLibrary size={18} />} title="اختر"
              line="صفِّ المكتبة بدورك أو ابحث باسم الملفّ، وافتح القالب المناسب." />
            <Step n={2} icon={<IcEdit size={18} />} title="املأ"
              line="حقولٌ مسمّاة بالعربية بدل الصفحة البيضاء، وحفظٌ تلقائيّ كلّما كتبت." />
            <Step n={3} icon={<IcSpark size={18} />} title="حسّن"
              line="مرّر أيّ فقرة على مساعد الصياغة ليعيدها بلغةٍ تربوية رسمية دون أن يغيّر معناها." />
            <Step n={4} icon={<IcPrint size={18} />} title="صدّر"
              line="اختر PDF أو وورد أو إكسل، فينزل الملفّ باسم الملفّ وترويسة مدرستك." />
          </div>
        </div>
      </section>

      {/* ============ الفئات ============ */}
      <section className="mdd-section">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <div className="mdd-col" style={{ gap: 10 }}>
            <h2 className="mdd-h2">المكتبة مقسّمة على فئاتك المهنيّة</h2>
            <p className="mdd-prose" style={{ fontSize: 14.5 }}>
              كلّ فئةٍ ترى قوالبها والقوالب العامّة، فلا تضيع بين ملفّات لا تخصّك.
            </p>
          </div>

          {error ? (
            <ErrorState onRetry={reload} message={error} />
          ) : loading ? (
            <div className="mdd-grid mdd-grid--3">
              {Array.from({ length: 6 }).map((_, i) => (
                <Card key={i} className="mdd-col"><Skeleton h={15} w="60%" /><Skeleton h={11} w="40%" /></Card>
              ))}
            </div>
          ) : roles.length === 0 ? (
            <Card className="mdd-col" style={{ gap: 10 }}>
              <h3 style={{ fontSize: 15 }}>الفئات تُضبط الآن</h3>
              <p className="mdd-prose" style={{ fontSize: 13 }}>ستظهر هنا فور اعتمادها. راسِلنا إن أردت فئةً بعينها.</p>
            </Card>
          ) : (
            <div className="mdd-grid mdd-grid--3">
              {roles.map((r) => (
                <Card key={r.id} className="mdd-col" style={{ gap: 10 }}>
                  <div className="mdd-row mdd-row--between">
                    <h3 style={{ fontSize: 15 }}>{r.name_ar}</h3>
                    <Badge tone={counts.get(r.key) ? 'accent' : 'neutral'}>
                      <span className="mdd-num">{fmtNum(counts.get(r.key) || 0)}</span> ملفًّا
                    </Badge>
                  </div>
                  <p className="mdd-prose" style={{ fontSize: 12.5 }}>
                    {r.blurb_ar || 'قوالب معتمدة تخصّ هذه الفئة، تُملأ وتُصدَّر مباشرةً.'}
                  </p>
                </Card>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* ============ قبل / بعد ============ */}
      <section className="mdd-section" style={{ background: 'var(--mdd-sunken)' }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <div className="mdd-col" style={{ gap: 10 }}>
            <span className="mdd-eyebrow">تحسين الصياغة</span>
            <h2 className="mdd-h2">تكتب كما تتكلّم — ويخرج كما يُكتب في التقارير</h2>
            <p className="mdd-prose" style={{ fontSize: 14.5 }}>
              مساعد الصياغة يعيد ترتيب كلامك بلغةٍ تربوية رسمية، ولا يضيف واقعةً لم تذكرها. القرار يبقى لك: تقبل النصّ أو ترفضه.
            </p>
          </div>

          <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'stretch' }}>
            <Card className="mdd-col mdd-card--pad-lg" style={{ gap: 14 }}>
              <div className="mdd-row" style={{ gap: 10 }}>
                <Badge tone="neutral" dot>قبل</Badge>
                <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>ما كتبتَه بلغتك</span>
              </div>
              <p
                style={{
                  fontSize: 16, lineHeight: 2, color: 'var(--mdd-text-2)',
                  background: 'var(--mdd-sunken)', border: '1px solid var(--mdd-border)',
                  borderRadius: 'var(--mdd-r-md)', padding: 'var(--mdd-s-4)', whiteSpace: 'pre-line',
                }}
              >
                {BEFORE}
              </p>
            </Card>

            <Card className="mdd-col mdd-card--pad-lg" style={{ gap: 14, borderColor: 'var(--mdd-accent)' }}>
              <div className="mdd-row" style={{ gap: 10 }}>
                <Badge tone="accent" dot>بعد</Badge>
                <span className="mdd-row" style={{ gap: 6, fontSize: 12, color: 'var(--mdd-accent-soft-fg)' }}>
                  <IcSpark size={13} /> صياغة تربوية
                </span>
              </div>
              <p
                style={{
                  fontSize: 16, lineHeight: 2, color: 'var(--mdd-text)',
                  background: 'var(--mdd-accent-soft)', border: '1px solid var(--mdd-accent-soft)',
                  borderRadius: 'var(--mdd-r-md)', padding: 'var(--mdd-s-4)', whiteSpace: 'pre-line',
                }}
              >
                {AFTER}
              </p>
            </Card>
          </div>
        </div>
      </section>

      {/* ============ المخارج ============ */}
      <section className="mdd-section">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          <div className="mdd-col" style={{ gap: 10 }}>
            <h2 className="mdd-h2">ثلاثة مخارج — ولكلٍّ سببه</h2>
            <p className="mdd-prose" style={{ fontSize: 14.5 }}>
              الملفّ الواحد يُصدَّر بأيّ صيغةٍ منها، والبيانات نفسها لا تُعاد كتابتها.
            </p>
          </div>
          <div className="mdd-grid mdd-grid--3">
            <Output
              icon={<IcPrint size={20} />}
              title="PDF"
              why="حين يكون الملفّ نهائيًّا: للطباعة والتوقيع والرفع في المنصّات الرسمية."
              points={['يحفظ الشكل كما تراه', 'جاهز للطباعة A4', 'لا يتغيّر عند فتحه على جهازٍ آخر']}
            />
            <Output
              icon={<IcFiles size={20} />}
              title="وورد"
              why="حين يحتاج الملفّ إلى إضافةٍ أو تعديلٍ يدويّ قبل اعتماده."
              points={['نصٌّ قابل للتحرير', 'يقبل ختم المدرسة وتوقيعها', 'يُشارَك مع الزملاء للمراجعة']}
            />
            <Output
              icon={<IcTable size={20} />}
              title="إكسل"
              why="حين يكون الملفّ جدولًا: درجات أو حصر أو متابعة رقمية."
              points={['أعمدة وصفوف حقيقية', 'يقبل المعادلات والفرز', 'يُرفع في الأنظمة التي تطلب جدولًا']}
            />
          </div>
        </div>
      </section>

      {/* ============ الدعوة ============ */}
      <section style={{ background: 'var(--mdd-accent-deep)' }}>
        <div
          className="mdd-site-wrap mdd-row mdd-row--between mdd-row--wrap"
          style={{ gap: 'var(--mdd-s-5)', paddingBlock: 'var(--mdd-s-8)' }}
        >
          <div className="mdd-col" style={{ gap: 10, minWidth: 0 }}>
            <h2 className="mdd-h2" style={{ color: ON_DEEP }}>افتح المكتبة وجرّب قالبًا واحدًا</h2>
            <p style={{ color: ON_DEEP_2, fontSize: 14.5, lineHeight: 1.8, maxWidth: '52ch' }}>
              سبعة أيّام كاملة بلا بطاقة. أنشئ حسابك وابدأ من أوّل ملفّ تحتاجه اليوم.
            </p>
          </div>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)' }}>
            <Link to="/join"><Button auto size="lg" variant="primary">ابدأ التجربة</Button></Link>
            <Link to="/service/noor">
              <Button auto size="lg" variant="secondary" icon={<IcChevron size={14} />}>جداول نور</Button>
            </Link>
          </div>
        </div>
      </section>
    </>
  )
}

function Step({ n, icon, title, line }: { n: number; icon: React.ReactNode; title: string; line: string }) {
  return (
    <Card className="mdd-col" style={{ gap: 12 }}>
      <div className="mdd-row" style={{ gap: 10 }}>
        <span
          className="mdd-num"
          style={{
            width: 30, height: 30, borderRadius: 'var(--mdd-r-pill)', display: 'grid', placeItems: 'center',
            background: 'var(--mdd-accent)', color: 'var(--mdd-on-accent)', fontWeight: 700, fontSize: 13,
          }}
        >
          {n}
        </span>
        <span style={{ color: 'var(--mdd-accent)', display: 'flex' }}>{icon}</span>
      </div>
      <h3 style={{ fontSize: 15.5 }}>{title}</h3>
      <p className="mdd-prose" style={{ fontSize: 12.5 }}>{line}</p>
    </Card>
  )
}

function Output({ icon, title, why, points }: {
  icon: React.ReactNode; title: string; why: string; points: string[]
}) {
  return (
    <Card className="mdd-col" style={{ gap: 13 }}>
      <span
        style={{
          width: 44, height: 44, borderRadius: 13, display: 'grid', placeItems: 'center',
          background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
        }}
      >
        {icon}
      </span>
      <h3 style={{ fontSize: 16 }}>{title}</h3>
      <p className="mdd-prose" style={{ fontSize: 13 }}>{why}</p>
      <ul className="mdd-col" style={{ gap: 8, listStyle: 'none', padding: 0, margin: 0 }}>
        {points.map((p) => (
          <li key={p} className="mdd-row" style={{ gap: 9, fontSize: 12.5, color: 'var(--mdd-text-2)' }}>
            <span style={{ color: 'var(--mdd-success-fg)', display: 'flex' }}><IcCheck size={13} /></span>
            <span>{p}</span>
          </li>
        ))}
      </ul>
    </Card>
  )
}
