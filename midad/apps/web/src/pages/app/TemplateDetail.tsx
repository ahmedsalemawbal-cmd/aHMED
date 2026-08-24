import React, { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { supabase } from '../../lib/supabase'
import { fetchTemplateBySlug, fetchTemplates, fetchFolders, templateLocked } from '../../lib/data'
import type { Template } from '../../lib/types'
import { Badge, Button, Card, EmptyState, ErrorState, Skeleton } from '../../ui/kit'
import { IcBack, IcCheck, IcChevron, IcClock, IcLock, IcPrint } from '../../ui/icons'
import Paper from './Paper'
import TemplateThumb from './TemplateThumb'
import { splitPages, A4_PX } from '../../lib/pages'

const OUTPUT_LABEL: Record<string, string> = {
  pdf: 'PDF للطباعة',
  docx: 'ملفّ وورد',
  doc: 'ملفّ وورد',
  word: 'ملفّ وورد',
  xlsx: 'جدول إكسل',
  excel: 'جدول إكسل',
  print: 'طباعة مباشرة',
}

/**
 * معاينة القالب — من متنه الحقيقيّ.
 *
 * كانت هذه تعرض نموذج الحقول القديم: ترويسةٌ عامّة وأسطرٌ رماديّة. فيرى
 * المعلّم شكلًا واحدًا لكلّ القوالب ولا يعرف ما يفتح. والقوالب الآن
 * مستنداتٌ مصمَّمة، فنعرضها كما هي.
 */
/**
 * معاينة القالب — صفحاتٌ منفصلة كالوورد، تُقاس فتُلائم، ولا تنزلق.
 *
 * كانت عيبان في واحد. الأوّل أنّ الورقة تُصغَّر بـ`transform: scale` وحده،
 * و`transform` يُغيّر ما يُرسَم ولا يُغيّر ما يَشغل: تبقى ٧٩٤px في الحساب
 * وإن رُسمت ٤٩٢. فيحجز لها الإطار ٧٩٤ ولا يجدها، فيفيض ٢٣٤px — شريط
 * تمريرٍ أفقيّ تُسحب به المعاينة، وفراغٌ حيث ظنّ الحساب ورقة.
 *
 * والثاني أنّ الصفحات الستّ كانت تُعرض متنًا واحدًا متّصلًا يفصله خطٌّ
 * منقّط، فتبدو صفحةً طويلة لا ستّ صفحات — وهي في أصلها ستّ ورقات A4.
 *
 * فنشطر المتن عند الفواصل، ونُصيّر كلّ صفحةٍ ورقةً قائمةً بذاتها بمقاس
 * A4 وظلٍّ وفجوةٍ بينها وبين تاليتها. والمعامل يُقاس من عرض الإطار فتملأه
 * الورقة تمامًا مهما اتّسع أو ضاق، ولا تتزحزح.
 */
function TemplatePreview({ tpl }: { tpl: Template }) {
  const html = (tpl.content_html || '').trim()
  const box = useRef<HTMLDivElement>(null)
  const [scale, setScale] = useState(0)
  const pages = useMemo(() => splitPages(html), [html])

  useEffect(() => {
    const b = box.current
    if (!b || !pages.length) return
    const fit = () => setScale(b.clientWidth / A4_PX.w)
    fit()
    const ro = new ResizeObserver(fit)
    ro.observe(b)
    return () => ro.disconnect()
  }, [pages.length])

  if (!html || !pages.length) {
    return (
      <div className="mdd-paper-shell" style={{ display: 'grid', placeItems: 'center', minHeight: 260 }}>
        <p className="mdd-prose" style={{ fontSize: 13 }}>لا معاينة — القالب فارغ.</p>
      </div>
    )
  }

  const m = tpl.page?.margins ?? { top: 14, right: 14, bottom: 14, left: 14 }
  return (
    <div className="mdd-tplview" ref={box}>
      {pages.map((page, i) => (
        /* `-slot` يحجز الارتفاع بعد التصغير ويُغلق الفيض، والورقة مُطلَقةٌ
           داخله فلا تُملي عليه عرضًا. وبغيره يعود الانزلاق. */
        <div
          key={i} className="mdd-tplview-slot"
          style={{ blockSize: scale ? Math.round(A4_PX.h * scale) : undefined }}>
          <div
            className="mdd-tplview-sheet mdd-doc-body"
            dir="rtl"
            style={{
              padding: `${m.top}mm ${m.right}mm ${m.bottom}mm ${m.left}mm`,
              transform: scale ? `scale(${scale})` : undefined,
              visibility: scale ? undefined : 'hidden',
            }}
            dangerouslySetInnerHTML={{ __html: page }}
          />
          <span className="mdd-tplview-no mdd-num">{i + 1}</span>
        </div>
      ))}
    </div>
  )
}

export default function TemplateDetail() {
  const { slug = '' } = useParams()
  const nav = useNavigate()
  const { subscriber, profile, plan, roles, toast } = useApp()
  const [creating, setCreating] = useState(false)

  const { data, loading, error, reload } = useAsync(async () => {
    const tpl = await fetchTemplateBySlug(slug)
    if (!tpl) return { tpl: null as Template | null, similar: [] as Template[] }
    const all = await fetchTemplates()
    const similar = all.filter((t) => t.category_key === tpl.category_key && t.id !== tpl.id).slice(0, 4)
    /* المجلّد هو اسم القالب عند المعلّم: «المتابعة والتقييم» لا مفتاحُ
       فئةٍ داخليّ. ونُحمّله هنا لا في مكوّنٍ آخر كي لا تُطلب الشبكة مرّتين. */
    const folders = tpl.folder_id ? await fetchFolders() : []
    const folder = folders.find((f) => f.id === tpl.folder_id) || null
    return { tpl, similar, folder }
  }, [slug])

  const tpl = data?.tpl || null
  const similar = data?.similar || []
  const locked = tpl ? templateLocked(tpl, plan) : false
  /**
   * تسمية القالب في الشارة.
   *
   * كانت تسقط إلى `category_key` نفسه حين لا يُطابق دورًا، فتظهر «general»
   * إنجليزيّةً وسط واجهةٍ عربيّة. والمفتاح الداخليّ لا يُعرض على مستخدمٍ
   * أبدًا: إمّا اسمٌ يفهمه، وإمّا لا شارة.
   */
  const folder = data?.folder || null
  const label =
    folder?.name
    || roles.find((r) => r.key === tpl?.category_key)?.name_ar
    || ''

  async function start() {
    if (!tpl || !subscriber || !profile) return
    setCreating(true)
    try {
      const { data: doc, error: insErr } = await supabase.from('documents').insert({
        subscriber_id: subscriber.id,
        owner_id: profile.id,
        template_id: tpl.id,
        title: tpl.title,
        data: {},
        status: 'draft',
      }).select().single()
      if (insErr || !doc) throw new Error(insErr?.message || 'تعذّر إنشاء الملفّ')
      nav(`/app/doc/${(doc as any).id}`)
    } catch (e: any) {
      toast(e?.message || 'تعذّر إنشاء الملفّ', 'danger')
      setCreating(false)
    }
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  if (loading) {
    return (
      <div className="mdd-col" style={{ gap: 'var(--mdd-s-5)' }}>
        <Skeleton h={18} w={180} />
        <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
          <Card className="mdd-col">
            <Skeleton h={22} w="60%" />
            <Skeleton h={13} /><Skeleton h={13} w="85%" />
            <Skeleton h={140} style={{ borderRadius: 12 }} />
            <Skeleton h={44} style={{ borderRadius: 12 }} />
          </Card>
          <Skeleton h={520} style={{ borderRadius: 16 }} />
        </div>
      </div>
    )
  }

  if (!tpl) {
    return (
      <EmptyState
        title="لم نجد هذا القالب"
        line="ربّما غُيّر رابطه أو سُحب من المكتبة. تصفّح المكتبة لتجد ما يقابله."
        action={<Button variant="primary" onClick={() => nav('/app/library')}>تصفّح المكتبة</Button>}
      />
    )
  }

  const fields = tpl.fields || []
  const outputs = tpl.outputs || []
  // القالب المستند: متنُه هو المحتوى، لا حقولٌ تُملأ
  const isDoc = !!(tpl.content_html || '').trim()
  const docStats = {
    tables: ((tpl.content_html || '').match(/<table/g) || []).length,
  }

  return (
    <>
      <div className="mdd-row" style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
        <Link to="/app/library" className="mdd-row" style={{ gap: 7, fontSize: 12.5, fontWeight: 600, color: 'var(--mdd-text-2)' }}>
          <IcBack size={15} /> رجوع إلى المكتبة
        </Link>
      </div>

      <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
        {/* العمود الأوّل (يمينًا في العربية) — التعريف */}
        <div className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
          <Card className="mdd-col" style={{ gap: 14 }}>
            <div className="mdd-row mdd-row--wrap" style={{ gap: 8 }}>
              {label && <Badge tone="accent">{label}</Badge>}
              {tpl.is_new && <Badge tone="info">جديد</Badge>}
              {locked && <Badge tone="neutral"><IcLock size={11} /> باقة المدرسة</Badge>}
            </div>

            <div>
              <h1 style={{ fontSize: 25, letterSpacing: '-.3px' }}>{tpl.title}</h1>
              {tpl.description && (
                <p className="mdd-prose" style={{ fontSize: 14, marginBlockStart: 8, lineHeight: 1.9 }}>{tpl.description}</p>
              )}
            </div>

            {locked ? (
              <Button variant="soft" size="lg" icon={<IcLock size={16} />} onClick={() => nav('/app/plans')}>
                متاح في باقة المدرسة
              </Button>
            ) : (
              <Button variant="primary" size="lg" loading={creating} onClick={start}>
                {creating ? 'جارٍ التجهيز…' : 'ابدأ الملفّ'}
              </Button>
            )}

            <div className="mdd-row" style={{ gap: 8, color: 'var(--mdd-text-3)', fontSize: 12.5 }}>
              <IcClock size={15} />
              <span>كم يستغرق: نحو <span className="mdd-num">{tpl.estimated_minutes}</span> دقائق</span>
            </div>
          </Card>

          <Card className="mdd-col" style={{ gap: 12 }}>
            <h2 className="mdd-card__title">{isDoc ? 'ما فيه' : 'ما ستملأ'}</h2>
            {isDoc ? (
              <>
                <p className="mdd-prose" style={{ fontSize: 13, lineHeight: 1.9 }}>
                  مستندٌ جاهزٌ تفتحه فتحرّره كما تحرّر الوورد: تكتب في مكانه،
                  وتضيف صفوفًا إلى جداوله، وتغيّر ألوانه، وتُحسّن نصّه بالذكاء
                  الاصطناعيّ — ثمّ تُنزّله PDF أو وورد.
                </p>
                <div className="mdd-row mdd-row--wrap" style={{ gap: 8 }}>
                  {tpl.source_pages ? (
                    <span className="mdd-chipish">
                      <span className="mdd-num">{tpl.source_pages}</span> صفحة
                    </span>
                  ) : null}
                  {docStats.tables > 0 && (
                    <span className="mdd-chipish">
                      <span className="mdd-num">{docStats.tables}</span> جدولًا
                    </span>
                  )}
                  <span className="mdd-chipish">
                    {tpl.page?.orientation === 'landscape' ? 'أفقيّ' : 'رأسيّ'} · A4
                  </span>
                </div>
              </>
            ) : fields.length === 0 ? (
              <p className="mdd-prose" style={{ fontSize: 13 }}>هذا القالب جاهزٌ بلا حقول — تفتحه وتطبعه مباشرةً.</p>
            ) : (
              <>
                <p className="mdd-prose" style={{ fontSize: 12.5 }}>
                  <span className="mdd-num">{fields.length}</span> حقلًا، وكلّ حقلٍ يظهر مكانه في الورقة فور كتابته.
                </p>
                <ul className="mdd-col" style={{ gap: 9, listStyle: 'none', padding: 0, margin: 0 }}>
                  {fields.map((f) => (
                    <li key={f.key} className="mdd-row" style={{ gap: 10, alignItems: 'flex-start' }}>
                      <span style={{ color: 'var(--mdd-accent)', marginBlockStart: 2, flex: 'none' }}><IcCheck size={14} /></span>
                      <span style={{ minWidth: 0, fontSize: 13.5 }}>
                        {f.label}
                        {f.type === 'table' && f.columns?.length ? (
                          <span style={{ color: 'var(--mdd-text-3)', fontSize: 11.5 }}>
                            {' '}— جدول بـ <span className="mdd-num">{f.columns.length}</span> أعمدة
                          </span>
                        ) : null}
                        {f.required && <span style={{ color: 'var(--mdd-text-3)', fontSize: 11.5 }}> · إلزاميّ</span>}
                      </span>
                    </li>
                  ))}
                </ul>
              </>
            )}
          </Card>

          <Card className="mdd-col" style={{ gap: 12 }}>
            <h2 className="mdd-card__title">المخارج</h2>
            {outputs.length === 0 ? (
              <p className="mdd-prose" style={{ fontSize: 13 }}>يُصدَّر PDF للطباعة.</p>
            ) : (
              <div className="mdd-row mdd-row--wrap" style={{ gap: 8 }}>
                {outputs.map((o) => (
                  <span key={o} className="mdd-row" style={{
                    gap: 7, fontSize: 12.5, fontWeight: 600, padding: '7px 12px',
                    borderRadius: 'var(--mdd-r-pill)', border: '1px solid var(--mdd-border)',
                    background: 'var(--mdd-sunken)', color: 'var(--mdd-text-2)',
                  }}>
                    <IcPrint size={14} />{OUTPUT_LABEL[String(o).toLowerCase()] || o}
                  </span>
                ))}
              </div>
            )}
          </Card>
        </div>

        {/* العمود الثاني (يسارًا) — معاينة القالب بتصميمه */}
        <div className="mdd-col" style={{ gap: 10 }}>
          <span style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--mdd-text-3)' }}>
            {tpl.content_html ? 'هكذا سيبدو القالب' : 'معاينة الورقة قبل الملء'}
          </span>
          <TemplatePreview tpl={tpl} />
        </div>
      </div>

      {similar.length > 0 && (
        <div className="mdd-col" style={{ gap: 'var(--mdd-s-4)', marginBlockStart: 'var(--mdd-s-7)' }}>
          <h2 style={{ fontSize: 18 }}>قوالب شبيهة</h2>
          <div className="mdd-grid mdd-grid--4">
            {similar.map((t) => (
              <SimilarCard key={t.id} t={t} locked={templateLocked(t, plan)} onOpen={() => nav(`/app/template/${t.slug}`)} />
            ))}
          </div>
        </div>
      )}
    </>
  )
}

function SimilarCard({ t, locked, onOpen }: { t: Template; locked: boolean; onOpen: () => void }) {
  return (
    <Card className="mdd-card--action mdd-col" onClick={onOpen}
      style={{ cursor: 'pointer', gap: 10, opacity: locked ? 0.72 : 1 }}>
      <TemplateThumb template={t} height={112} />
      <h3 style={{ fontSize: 13.5 }}>{t.title}</h3>
      <div className="mdd-row mdd-row--between" style={{ marginBlockStart: 'auto' }}>
        <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
          <span className="mdd-num">{t.fields?.length || 0}</span> حقلًا
        </span>
        {locked ? <IcLock size={13} /> : <IcChevron size={13} />}
      </div>
    </Card>
  )
}
