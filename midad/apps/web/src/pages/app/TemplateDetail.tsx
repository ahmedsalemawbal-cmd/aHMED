import React, { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { supabase } from '../../lib/supabase'
import { fetchTemplateBySlug, fetchTemplates, templateLocked } from '../../lib/data'
import type { Template } from '../../lib/types'
import { Badge, Button, Card, EmptyState, ErrorState, Skeleton } from '../../ui/kit'
import { IcBack, IcCheck, IcChevron, IcClock, IcLock, IcPrint } from '../../ui/icons'
import Paper from './Paper'
import TemplateThumb from './TemplateThumb'

const OUTPUT_LABEL: Record<string, string> = {
  pdf: 'PDF للطباعة',
  docx: 'ملفّ وورد',
  doc: 'ملفّ وورد',
  word: 'ملفّ وورد',
  xlsx: 'جدول إكسل',
  excel: 'جدول إكسل',
  print: 'طباعة مباشرة',
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
    return { tpl, similar }
  }, [slug])

  const tpl = data?.tpl || null
  const similar = data?.similar || []
  const locked = tpl ? templateLocked(tpl, plan) : false
  const roleName = roles.find((r) => r.key === tpl?.category_key)?.name_ar || tpl?.category_key || ''

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
              <Badge tone="accent">{roleName}</Badge>
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
            <h2 className="mdd-card__title">ما ستملأ</h2>
            {fields.length === 0 ? (
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

        {/* العمود الثاني (يسارًا) — معاينة الورقة */}
        <div className="mdd-col" style={{ gap: 10 }}>
          <span style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--mdd-text-3)' }}>معاينة الورقة قبل الملء</span>
          <div className="mdd-paper-shell">
            <Paper
              template={tpl}
              data={{}}
              title={tpl.title}
              schoolName={subscriber?.name}
              educationDept={subscriber?.education_dept}
              academicYear={subscriber?.academic_year}
              semester={subscriber?.semester}
              logoUrl={subscriber?.logo_url}
              zoom={0.52}
            />
          </div>
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
