import React, { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import type { Template, TemplateColumn, TemplateField, FieldType } from '../../lib/types'
import { orphanKeys, unusedFields } from '../../lib/template'
import {
  Alert, Badge, Button, Card, ErrorState, Field, IconButton, Input, Modal,
  PageHead, Select, SkeletonRows, Switch, Tabs, Textarea,
} from '../../ui/kit'
import { IcBack, IcPlus, IcTrash, IcChevron, IcChevronDown } from '../../ui/icons'
import Paper from '../app/Paper'

const TYPES: { key: FieldType; label: string }[] = [
  { key: 'text', label: 'نصّ قصير' },
  { key: 'textarea', label: 'نصّ طويل' },
  { key: 'number', label: 'رقم' },
  { key: 'date', label: 'تاريخ' },
  { key: 'select', label: 'قائمة' },
  { key: 'radio', label: 'اختيارات' },
  { key: 'table', label: 'جدول' },
]

/** قيم تجريبية تُملأ من نوع كلّ حقل — لتظهر المعاينة كما سيراها المشترك. */
function sampleData(fields: TemplateField[]): Record<string, any> {
  const out: Record<string, any> = {}
  for (const f of fields || []) {
    if (f.type === 'table') {
      const cols = f.columns || []
      out[f.key] = Array.from({ length: 3 }).map((_, i) => {
        const row: Record<string, string> = {}
        for (const c of cols) {
          row[c.key] = c.type === 'number' ? String((i + 1) * 5)
            : c.type === 'date' ? '2026-01-14'
            : c.type === 'select' ? (c.options?.[0] || '—')
            : ['أحمد سالم الغامدي', 'نورة عبدالله القحطاني', 'محمد فهد الشهري'][i] || 'قيمة'
        }
        return row
      })
    } else if (f.type === 'number') out[f.key] = '24'
    else if (f.type === 'date') out[f.key] = '2026-01-14'
    else if (f.type === 'select' || f.type === 'radio') out[f.key] = f.options?.[0] || '—'
    else if (f.type === 'textarea') out[f.key] = 'رفع مستوى تحصيل الطلاب في المهارات الأساسية عبر خطة علاجية أسبوعية يتابعها المعلّم ويوثّقها في السجلّ.'
    else out[f.key] = 'ابتدائية الأمل'
  }
  return out
}

export default function TemplateEditor() {
  const { id } = useParams()
  const nav = useNavigate()
  const { roles, toast, subscriber } = useApp()

  const [tpl, setTpl] = useState<Template | null>(null)
  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState<string | null>(null)
  const [tab, setTab] = useState<'fields' | 'body' | 'preview'>('fields')
  const [busy, setBusy] = useState(false)
  const [insertOpen, setInsertOpen] = useState(false)
  const [openField, setOpenField] = useState<number | null>(0)
  const bodyRef = useRef<HTMLTextAreaElement>(null)

  useEffect(() => {
    let alive = true
    ;(async () => {
      setLoading(true)
      const { data, error } = await supabase.from('templates').select('*').eq('id', id).maybeSingle()
      if (!alive) return
      if (error || !data) { setErr(error?.message || 'لم نجد هذا القالب'); setLoading(false); return }
      setTpl(data as Template); setLoading(false)
    })()
    return () => { alive = false }
  }, [id])

  const fields = tpl?.fields || []
  const orphans = useMemo(() => orphanKeys(tpl?.body || '', fields), [tpl?.body, fields])
  const unused = useMemo(() => unusedFields(tpl?.body || '', fields), [tpl?.body, fields])

  const patch = (p: Partial<Template>) => setTpl((t) => (t ? { ...t, ...p } : t))
  const setFields = (f: TemplateField[]) => patch({ fields: f })

  const save = async (publish?: boolean) => {
    if (!tpl) return
    if (!tpl.title.trim() || !tpl.slug.trim()) { toast('العنوان والمفتاح مطلوبان', 'danger'); return }
    if (publish && orphans.length) { toast('أصلح المفاتيح اليتيمة قبل النشر', 'danger'); return }
    setBusy(true)
    const { error } = await supabase.from('templates').update({
      slug: tpl.slug.trim(), title: tpl.title.trim(), category_key: tpl.category_key,
      description: tpl.description, body: tpl.body, fields: tpl.fields,
      outputs: tpl.outputs, estimated_minutes: tpl.estimated_minutes,
      status: publish ? 'published' : tpl.status,
      version: publish ? (tpl.version || 1) + 1 : tpl.version,
    }).eq('id', tpl.id)
    setBusy(false)
    if (error) { toast(error.message, 'danger'); return }
    toast(publish ? 'نُشر القالب' : 'حُفظت المسوّدة')
    if (publish) patch({ status: 'published', version: (tpl.version || 1) + 1 })
  }

  const addField = () => {
    const n = fields.length + 1
    setFields([...fields, {
      key: `field_${n}`, label: `حقل ${n}`, type: 'text', required: false,
      section: 'البيانات', placeholder: '', help: '', options: [], columns: [],
    }])
    setOpenField(fields.length)
  }
  const updateField = (i: number, p: Partial<TemplateField>) =>
    setFields(fields.map((f, fi) => (fi === i ? { ...f, ...p } : f)))
  const move = (i: number, dir: -1 | 1) => {
    const j = i + dir
    if (j < 0 || j >= fields.length) return
    const copy = [...fields]
    ;[copy[i], copy[j]] = [copy[j], copy[i]]
    setFields(copy); setOpenField(j)
  }

  const insertKey = (key: string, table: boolean) => {
    const ta = bodyRef.current
    const token = table ? `\n{{table:${key}}}\n` : `{{${key}}}`
    const body = tpl?.body || ''
    const pos = ta ? ta.selectionStart : body.length
    patch({ body: body.slice(0, pos) + token + body.slice(pos) })
    setInsertOpen(false)
    setTimeout(() => { ta?.focus(); ta?.setSelectionRange(pos + token.length, pos + token.length) }, 30)
  }

  if (loading) return <SkeletonRows n={6} />
  if (err || !tpl) return <ErrorState message={err || undefined} />

  return (
    <>
      <Button auto size="sm" icon={<IcBack size={14} />} onClick={() => nav('/admin/templates')}
        style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>كلّ القوالب</Button>

      <Card className="mdd-col" style={{ gap: 14, marginBlockEnd: 'var(--mdd-s-4)' }}>
        <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 12 }}>
          <div className="mdd-row" style={{ gap: 10 }}>
            <Badge tone={tpl.status === 'published' ? 'success' : 'neutral'} dot>
              {tpl.status === 'published' ? 'منشور' : 'مسوّدة'}
            </Badge>
            <Badge>الإصدار <span className="mdd-num">{tpl.version}</span></Badge>
          </div>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 8 }}>
            <Button auto size="sm" loading={busy} onClick={() => save(false)}>احفظ المسوّدة</Button>
            <Button auto size="sm" variant="primary" loading={busy} disabled={orphans.length > 0}
              onClick={() => save(true)}>انشر</Button>
          </div>
        </div>

        <div className="mdd-grid mdd-grid--3" style={{ gap: 12 }}>
          <Field label="العنوان">
            <Input value={tpl.title} onChange={(e) => patch({ title: e.target.value })} />
          </Field>
          <Field label="المفتاح (slug)">
            <Input ltr value={tpl.slug} onChange={(e) => patch({ slug: e.target.value })} />
          </Field>
          <Field label="الفئة">
            <Select value={tpl.category_key} onChange={(e) => patch({ category_key: e.target.value })}>
              {roles.map((r) => <option key={r.key} value={r.key}>{r.name_ar}</option>)}
            </Select>
          </Field>
        </div>
        <Field label="الوصف — سطر يشرح متى يُستعمل الملفّ">
          <Input value={tpl.description || ''} onChange={(e) => patch({ description: e.target.value })} />
        </Field>
      </Card>

      {orphans.length > 0 && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
          <Alert tone="danger">
            <strong>مفاتيح في المتن بلا تعريف:</strong>{' '}
            <span className="mdd-mono">{orphans.map((k) => `{{${k}}}`).join('  ')}</span>
            <br />ستخرج حرفيًّا في ملفٍّ رسميّ يطبعه المشترك. عرّفها في الحقول أو احذفها من المتن — والنشر مقفل حتى تُصلح.
          </Alert>
        </div>
      )}
      {unused.length > 0 && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
          <Alert tone="warn">
            حقول معرَّفة ولا تظهر في المتن:{' '}
            <span className="mdd-mono">{unused.join('، ')}</span> — سيملؤها المشترك ولا تُطبع.
          </Alert>
        </div>
      )}

      <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
        <Tabs value={tab} onChange={setTab} tabs={[
          { key: 'fields', label: 'الحقول', count: fields.length },
          { key: 'body', label: 'المتن' },
          { key: 'preview', label: 'المعاينة' },
        ]} />
      </div>

      {tab === 'fields' && (
        <div className="mdd-col" style={{ gap: 12 }}>
          {fields.map((f, i) => (
            <div className="mdd-fieldset" key={i}>
              <button className="mdd-fieldset__head" aria-expanded={openField === i}
                onClick={() => setOpenField(openField === i ? null : i)}>
                <span className="mdd-row" style={{ gap: 10 }}>
                  <span className="mdd-num" style={{ color: 'var(--mdd-text-3)', fontSize: 12 }}>{i + 1}</span>
                  <span>{f.label}</span>
                  <Badge>{TYPES.find((t) => t.key === f.type)?.label}</Badge>
                  <span className="mdd-mono" style={{ fontSize: 10.5, color: 'var(--mdd-text-3)' }}>{f.key}</span>
                </span>
                <IcChevronDown size={14} />
              </button>
              {openField === i && (
                <div className="mdd-fieldset__body">
                  <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
                    <Field label="الاسم المعروض">
                      <Input value={f.label} onChange={(e) => updateField(i, { label: e.target.value })} />
                    </Field>
                    <Field label="المفتاح">
                      <Input ltr value={f.key} onChange={(e) => updateField(i, { key: e.target.value.replace(/[^a-zA-Z0-9_]/g, '_') })} />
                    </Field>
                    <Field label="النوع">
                      <Select value={f.type} onChange={(e) => updateField(i, { type: e.target.value as FieldType })}>
                        {TYPES.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                      </Select>
                    </Field>
                    <Field label="القسم" help="لتجميع الحقول في المحرّر">
                      <Input value={f.section || ''} onChange={(e) => updateField(i, { section: e.target.value })} />
                    </Field>
                    <Field label="نصّ إرشاديّ">
                      <Input value={f.placeholder || ''} onChange={(e) => updateField(i, { placeholder: e.target.value })} />
                    </Field>
                    <Field label="سطر توضيحيّ">
                      <Input value={f.help || ''} onChange={(e) => updateField(i, { help: e.target.value })} />
                    </Field>
                  </div>

                  {(f.type === 'select' || f.type === 'radio') && (
                    <Field label="الخيارات — سطر لكلّ خيار">
                      <Textarea rows={3} value={(f.options || []).join('\n')}
                        onChange={(e) => updateField(i, { options: e.target.value.split('\n').map((x) => x.trim()).filter(Boolean) })} />
                    </Field>
                  )}

                  {f.type === 'table' && (
                    <div className="mdd-col" style={{ gap: 8 }}>
                      <span className="mdd-field__label">الأعمدة</span>
                      {(f.columns || []).map((c, ci) => (
                        <div className="mdd-row" style={{ gap: 8 }} key={ci}>
                          <Input placeholder="العنوان" value={c.label}
                            onChange={(e) => updateField(i, {
                              columns: (f.columns || []).map((x, xi) => xi === ci ? { ...x, label: e.target.value } : x),
                            })} />
                          <Input ltr placeholder="key" value={c.key}
                            onChange={(e) => updateField(i, {
                              columns: (f.columns || []).map((x, xi) => xi === ci ? { ...x, key: e.target.value.replace(/[^a-zA-Z0-9_]/g, '_') } : x),
                            })} />
                          <Select value={c.type} onChange={(e) => updateField(i, {
                            columns: (f.columns || []).map((x, xi) => xi === ci ? { ...x, type: e.target.value as TemplateColumn['type'] } : x),
                          })}>
                            <option value="text">نصّ</option><option value="number">رقم</option>
                            <option value="date">تاريخ</option><option value="select">قائمة</option>
                          </Select>
                          <IconButton label="حذف العمود" onClick={() => updateField(i, {
                            columns: (f.columns || []).filter((_, xi) => xi !== ci),
                          })}><IcTrash size={14} /></IconButton>
                        </div>
                      ))}
                      <Button size="sm" auto icon={<IcPlus size={13} />} style={{ alignSelf: 'flex-start' }}
                        onClick={() => updateField(i, {
                          columns: [...(f.columns || []), { key: `col_${(f.columns?.length || 0) + 1}`, label: 'عمود', type: 'text', options: [] }],
                        })}>أضف عمودًا</Button>
                    </div>
                  )}

                  <div className="mdd-row mdd-row--between" style={{ paddingBlockStart: 6 }}>
                    <Switch checked={!!f.required} onChange={(v) => updateField(i, { required: v })} label="مطلوب" />
                    <div className="mdd-row" style={{ gap: 6 }}>
                      <IconButton label="أعلى" onClick={() => move(i, -1)}>↑</IconButton>
                      <IconButton label="أسفل" onClick={() => move(i, 1)}>↓</IconButton>
                      <Button size="sm" auto variant="danger" icon={<IcTrash size={13} />}
                        onClick={() => { setFields(fields.filter((_, fi) => fi !== i)); setOpenField(null) }}>احذف الحقل</Button>
                    </div>
                  </div>
                </div>
              )}
            </div>
          ))}
          <Button auto variant="soft" icon={<IcPlus size={15} />} onClick={addField}
            style={{ alignSelf: 'flex-start' }}>أضف حقلًا</Button>
        </div>
      )}

      {tab === 'body' && (
        <Card className="mdd-col" style={{ gap: 12 }}>
          <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 10 }}>
            <span className="mdd-field__help">
              HTML بسيط: h2 · h3 · p · ul/li · وجدول التواقيع بـ div.mdd-sign-row.
              أدرج الحقول بالزرّ لا بالكتابة اليدوية.
            </span>
            <Button auto size="sm" variant="soft" icon={<IcPlus size={13} />} onClick={() => setInsertOpen(true)}>أدرج حقلًا</Button>
          </div>
          <Textarea ref={bodyRef as any} rows={22} value={tpl.body}
            onChange={(e) => patch({ body: e.target.value })}
            style={{ fontFamily: 'var(--mdd-mono)', fontSize: 12.5, direction: 'ltr', textAlign: 'start' }} />
        </Card>
      )}

      {tab === 'preview' && (
        <div className="mdd-paper-shell">
          <Paper
            template={tpl} data={sampleData(fields)} title={tpl.title}
            schoolName="ابتدائية الأمل" educationDept="إدارة تعليم الرياض"
            academicYear="1447 هـ" semester="الفصل الأول" zoom={0.68}
          />
        </div>
      )}

      <Modal open={insertOpen} onClose={() => setInsertOpen(false)} title="أدرج حقلًا في المتن">
        {fields.length === 0 && <p className="mdd-muted" style={{ fontSize: 13 }}>عرّف حقلًا أوّلًا من تبويب «الحقول».</p>}
        <div className="mdd-col" style={{ gap: 8 }}>
          {fields.map((f) => (
            <button key={f.key} className="mdd-card mdd-card--action mdd-row mdd-row--between"
              style={{ padding: 12 }} onClick={() => insertKey(f.key, f.type === 'table')}>
              <span>
                <span style={{ display: 'block', fontWeight: 700, fontSize: 13.5 }}>{f.label}</span>
                <span className="mdd-mono" style={{ fontSize: 11, color: 'var(--mdd-text-3)' }}>
                  {f.type === 'table' ? `{{table:${f.key}}}` : `{{${f.key}}}`}
                </span>
              </span>
              <IcChevron size={14} />
            </button>
          ))}
        </div>
      </Modal>
    </>
  )
}
