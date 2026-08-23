import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { supabase, callFunction } from '../../lib/supabase'
import type { DocumentRow, Template, TemplateField } from '../../lib/types'
import { fieldSections, filledCount, renderBody } from '../../lib/template'
import { fmtRelative } from '../../lib/format'
import { Alert, Badge, Button, IconButton, Modal, Progress, Skeleton, Tabs } from '../../ui/kit'
import { IcBack, IcChevronDown, IcPrint, IcDownload, IcSpinner, IcSpark, IcCheck, IcCopy, IcTrash } from '../../ui/icons'
import Paper from './Paper'
import FieldInput from './FieldInput'
import ExportModal from './ExportModal'
import ImproveModal from './ImproveModal'

type SaveState = 'idle' | 'saving' | 'saved' | 'error'

export default function Editor() {
  const { id } = useParams()
  const nav = useNavigate()
  const { subscriber, profile, access, toast, plan } = useApp()

  const [doc, setDoc] = useState<DocumentRow | null>(null)
  const [tpl, setTpl] = useState<Template | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [data, setData] = useState<Record<string, any>>({})
  const [title, setTitle] = useState('')
  const [save, setSave] = useState<SaveState>('idle')
  const [savedAt, setSavedAt] = useState<Date | null>(null)
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({})
  const [tab, setTab] = useState<'fields' | 'preview'>('fields')
  const [exportOpen, setExportOpen] = useState(false)
  const [improveField, setImproveField] = useState<TemplateField | null>(null)
  const [menuOpen, setMenuOpen] = useState(false)
  const [zoom, setZoom] = useState(0.62)

  const dirty = useRef(false)
  const timer = useRef<any>(null)
  const latest = useRef({ data, title })
  latest.current = { data, title }

  useEffect(() => {
    let alive = true
    ;(async () => {
      setLoading(true); setLoadError(null)
      const { data: d, error } = await supabase.from('documents').select('*').eq('id', id).maybeSingle()
      if (!alive) return
      if (error || !d) { setLoadError(error?.message || 'لم نجد هذا الملفّ'); setLoading(false); return }
      const { data: t } = await supabase.from('templates').select('*').eq('id', (d as any).template_id).maybeSingle()
      if (!alive) return
      setDoc(d as DocumentRow)
      setTpl((t as Template) || null)
      setData((d as any).data || {})
      setTitle((d as any).title || '')
      setLoading(false)
    })()
    return () => { alive = false }
  }, [id])

  const persist = useCallback(async () => {
    if (!id || !dirty.current) return
    setSave('saving')
    const { error } = await supabase.from('documents')
      .update({ data: latest.current.data, title: latest.current.title })
      .eq('id', id)
    if (error) { setSave('error'); return }
    dirty.current = false
    setSave('saved'); setSavedAt(new Date())
  }, [id])

  const markDirty = useCallback(() => {
    dirty.current = true
    setSave('idle')
    clearTimeout(timer.current)
    timer.current = setTimeout(persist, 1200)
  }, [persist])

  useEffect(() => () => clearTimeout(timer.current), [])

  useEffect(() => {
    const onLeave = (e: BeforeUnloadEvent) => { if (dirty.current) { e.preventDefault(); e.returnValue = '' } }
    window.addEventListener('beforeunload', onLeave)
    return () => window.removeEventListener('beforeunload', onLeave)
  }, [])

  const setField = useCallback((key: string, v: any) => {
    setData((d) => ({ ...d, [key]: v })); markDirty()
  }, [markDirty])

  const sections = useMemo(() => fieldSections(tpl?.fields || []), [tpl])
  const total = tpl?.fields?.length || 0
  const done = useMemo(() => filledCount(tpl?.fields || [], data), [tpl, data])
  const readOnly = access !== 'trial' && access !== 'active'

  if (loading) {
    return (
      <div style={{ padding: 24 }} className="mdd-col">
        <Skeleton h={44} /><Skeleton h={320} />
      </div>
    )
  }
  if (loadError || !doc || !tpl) {
    return (
      <div style={{ padding: 40, textAlign: 'center' }} className="mdd-col">
        <h2>لم نجد هذا الملفّ</h2>
        <p className="mdd-prose" style={{ margin: '0 auto' }}>ربّما حُذف، أو أنّه ليس ضمن ملفّات مشتركك.</p>
        <Button auto variant="primary" onClick={() => nav('/app/files')} style={{ margin: '0 auto' }}>عُد إلى ملفّاتي</Button>
      </div>
    )
  }

  const watermark = access === 'trial' ? 'نسخة تجريبية — مِداد' : null

  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', background: 'var(--mdd-bg)' }}>
      <header className="mdd-header mdd-noprint" style={{ gap: 10 }}>
        <IconButton label="رجوع" onClick={() => { persist(); nav('/app/files') }}><IcBack size={17} /></IconButton>
        <input
          className="mdd-input"
          value={title}
          onChange={(e) => { setTitle(e.target.value); markDirty() }}
          onBlur={persist}
          aria-label="اسم الملفّ"
          style={{ maxWidth: 340, fontWeight: 700, minHeight: 38, padding: '8px 11px' }}
        />
        <SaveIndicator state={save} at={savedAt} onRetry={persist} />
        <div className="mdd-spacer" />
        <Button auto size="sm" onClick={() => setTab(tab === 'fields' ? 'preview' : 'fields')} className="mdd-mobile-only">
          {tab === 'fields' ? 'المعاينة' : 'الحقول'}
        </Button>
        <Button auto size="sm" icon={<IcPrint size={14} />} onClick={() => window.print()}>معاينة</Button>
        <Button auto size="sm" variant="primary" icon={<IcDownload size={14} />} onClick={() => setExportOpen(true)}>تصدير</Button>
        <div style={{ position: 'relative' }}>
          <IconButton label="خيارات" onClick={() => setMenuOpen((v) => !v)}><IcChevronDown size={15} /></IconButton>
          {menuOpen && (
            <>
              <div style={{ position: 'fixed', inset: 0, zIndex: 90 }} onClick={() => setMenuOpen(false)} />
              <div className="mdd-card" style={{
                position: 'absolute', insetInlineEnd: 0, insetBlockStart: 44, zIndex: 91, width: 200,
                padding: 6, boxShadow: 'var(--mdd-shadow-lg)',
              }}>
                <MenuItem icon={<IcCopy size={15} />} label="نسخة من هذا الملفّ" onClick={async () => {
                  setMenuOpen(false)
                  const { data: n, error } = await supabase.from('documents').insert({
                    subscriber_id: doc.subscriber_id, owner_id: profile!.id, template_id: doc.template_id,
                    title: `نسخة من ${title}`, data, status: 'draft',
                  }).select().single()
                  if (error) return toast('تعذّر النسخ', 'danger')
                  toast('أُنشئت نسخة')
                  nav(`/app/doc/${(n as any).id}`)
                }} />
                <MenuItem icon={<IcCheck size={15} />} label={doc.status === 'complete' ? 'أعده مسوّدة' : 'علّمه مكتملًا'} onClick={async () => {
                  setMenuOpen(false)
                  const next = doc.status === 'complete' ? 'draft' : 'complete'
                  await supabase.from('documents').update({ status: next }).eq('id', doc.id)
                  setDoc({ ...doc, status: next as any }); toast('حُدّثت الحالة')
                }} />
                <MenuItem icon={<IcTrash size={15} />} label="حذف الملفّ" danger onClick={async () => {
                  setMenuOpen(false)
                  if (!confirm('حذف الملفّ نهائيًّا؟ لا يمكن التراجع.')) return
                  await supabase.from('documents').delete().eq('id', doc.id)
                  toast('حُذف الملفّ'); nav('/app/files')
                }} />
              </div>
            </>
          )}
        </div>
      </header>

      {readOnly && (
        <div style={{ padding: '10px 16px' }} className="mdd-noprint">
          <Alert tone="warn">انتهى اشتراكك — يمكنك قراءة الملفّ وتصديره، ولا يُحفظ أيّ تعديل حتى تُجدّد.</Alert>
        </div>
      )}

      <div className="mdd-editor-tabs mdd-noprint" style={{ padding: '10px 12px 0' }}>
        <Tabs tabs={[{ key: 'fields', label: `الحقول (${done}/${total})` }, { key: 'preview', label: 'المعاينة' }]}
          value={tab} onChange={setTab} />
      </div>

      <div className="mdd-editor" data-tab={tab}>
        <div className="mdd-editor__fields mdd-noprint">
          <div className="mdd-col" style={{ gap: 8, marginBlockEnd: 16 }}>
            <div className="mdd-row mdd-row--between">
              <span style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--mdd-text-2)' }}>
                <span className="mdd-num">{done}</span> من <span className="mdd-num">{total}</span> حقلًا
              </span>
              <Badge tone={done === total && total > 0 ? 'success' : 'neutral'}>
                {done === total && total > 0 ? 'مكتمل' : 'قيد الملء'}
              </Badge>
            </div>
            <Progress value={done} max={total || 1} />
          </div>

          <div className="mdd-col" style={{ gap: 12 }}>
            {sections.map((sec) => {
              const open = !collapsed[sec.name]
              return (
                <div className="mdd-fieldset" key={sec.name}>
                  <button className="mdd-fieldset__head" aria-expanded={open}
                    onClick={() => setCollapsed((c) => ({ ...c, [sec.name]: open }))}>
                    <span>{sec.name}</span>
                    <span className="mdd-row" style={{ gap: 8 }}>
                      <span className="mdd-num" style={{ fontSize: 11.5, color: 'var(--mdd-text-3)', fontWeight: 500 }}>
                        {sec.fields.filter((f) => filledCount([f], data)).length}/{sec.fields.length}
                      </span>
                      <IcChevronDown size={14} style={{ transform: open ? 'rotate(180deg)' : undefined } as any} />
                    </span>
                  </button>
                  {open && (
                    <div className="mdd-fieldset__body">
                      {sec.fields.map((f) => (
                        <FieldInput key={f.key} field={f} value={data[f.key]}
                          disabled={readOnly}
                          onChange={(v) => setField(f.key, v)}
                          onImprove={f.type === 'textarea' ? () => setImproveField(f) : undefined} />
                      ))}
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        </div>

        <div className="mdd-editor__preview">
          <div className="mdd-row mdd-noprint" style={{ gap: 8, marginBlockEnd: 12, justifyContent: 'flex-end' }}>
            <IconButton label="تصغير" onClick={() => setZoom((z) => Math.max(0.35, +(z - 0.1).toFixed(2)))}>−</IconButton>
            <span className="mdd-num" style={{ fontSize: 12, color: 'var(--mdd-text-3)', minWidth: 44, textAlign: 'center' }}>
              {Math.round(zoom * 100)}%
            </span>
            <IconButton label="تكبير" onClick={() => setZoom((z) => Math.min(1.4, +(z + 0.1).toFixed(2)))}>+</IconButton>
          </div>
          <div className="mdd-paper-shell">
            <Paper
              template={tpl} data={data} title={title}
              schoolName={subscriber?.name} educationDept={subscriber?.education_dept}
              academicYear={subscriber?.academic_year} semester={subscriber?.semester}
              logoUrl={subscriber?.logo_url} watermark={watermark} zoom={zoom}
            />
          </div>
        </div>
      </div>

      <ExportModal
        open={exportOpen} onClose={() => setExportOpen(false)}
        template={tpl} data={data} title={title}
        subscriber={subscriber} watermark={watermark}
      />
      {improveField && (
        <ImproveModal
          field={improveField}
          value={String(data[improveField.key] ?? '')}
          documentId={doc.id}
          onClose={() => setImproveField(null)}
          onAccept={(txt) => { setField(improveField.key, txt); setImproveField(null); toast('استُبدل النصّ') }}
        />
      )}
    </div>
  )
}

function MenuItem({ icon, label, onClick, danger }: { icon: React.ReactNode; label: string; onClick: () => void; danger?: boolean }) {
  return (
    <button onClick={onClick} className="mdd-row" style={{
      width: '100%', padding: '10px 11px', borderRadius: 'var(--mdd-r-sm)', border: 'none',
      background: 'transparent', cursor: 'pointer', gap: 10, fontSize: 13, fontWeight: 600,
      color: danger ? 'var(--mdd-danger-fg)' : 'var(--mdd-text)', textAlign: 'start',
    }}>{icon}{label}</button>
  )
}

function SaveIndicator({ state, at, onRetry }: { state: SaveState; at: Date | null; onRetry: () => void }) {
  if (state === 'saving') return <span className="mdd-row" style={{ fontSize: 12, color: 'var(--mdd-text-3)', gap: 6 }}><IcSpinner size={13} /> جارٍ الحفظ…</span>
  if (state === 'error') return (
    <span className="mdd-row" style={{ fontSize: 12, color: 'var(--mdd-danger-fg)', gap: 6, fontWeight: 600 }}>
      تعذّر الحفظ
      <button onClick={onRetry} style={{ background: 'none', border: 'none', color: 'inherit', textDecoration: 'underline', cursor: 'pointer', font: 'inherit' }}>
        إعادة المحاولة
      </button>
    </span>
  )
  if (state === 'saved' || at) return <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>حُفظ {at ? fmtRelative(at) : ''}</span>
  return null
}
