import React, { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import type { Template, TemplateFolder } from '../../lib/types'
import { fmtRelative } from '../../lib/format'
import {
  Alert, Badge, Button, Field, IconButton, Input, Modal, Select, Skeleton, Textarea,
} from '../../ui/kit'
import {
  IcBack, IcCheck, IcSpinner, IcAlert, IcSave, IcSettings, IcEye, IcDownload, IcPrint,
} from '../../ui/icons'
import ExportModal from '../app/ExportModal'

const DocEditor = React.lazy(() => import('../app/DocEditor'))
import type { DocEditorHandle } from '../app/DocEditor'

type SaveState = 'idle' | 'saving' | 'saved' | 'error'

/**
 * محرّر القالب — نفس محرّر المعلّم بالضبط.
 *
 * المالك يصمّم هنا بما سيراه المعلّم لا بشيءٍ يشبهه: فما يخرج من هذه الشاشة
 * هو ما يُفتح عندهم حرفًا بحرف. ولا نشرَ إلّا بزرٍّ صريح، فالمسوّدة لا يراها أحد.
 */
export default function TemplateEditor() {
  const { id } = useParams()
  const nav = useNavigate()
  const { toast } = useApp()

  const [tpl, setTpl] = useState<Template | null>(null)
  const [folders, setFolders] = useState<TemplateFolder[]>([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [html, setHtml] = useState('')
  const [save, setSave] = useState<SaveState>('idle')
  const [savedAt, setSavedAt] = useState<Date | null>(null)
  const [settings, setSettings] = useState(false)
  const [exportOpen, setExportOpen] = useState(false)

  // إعدادات القالب — تُحرَّر في لوحةٍ جانبيّة وتُحفظ صراحةً
  const [meta, setMeta] = useState({
    title: '', slug: '', description: '', folder_id: '',
    orientation: 'portrait' as 'portrait' | 'landscape',
    estimated_minutes: 5,
  })

  const handle = useRef<DocEditorHandle | null>(null)
  const dirty = useRef(false)
  const timer = useRef<any>(null)
  const latest = useRef(html)
  latest.current = html

  useEffect(() => {
    let alive = true
    ;(async () => {
      setLoading(true); setLoadError(null)
      const [t, f] = await Promise.all([
        supabase.from('templates').select('*').eq('id', id).maybeSingle(),
        supabase.from('template_folders').select('*').order('sort').order('name'),
      ])
      if (!alive) return
      if (t.error || !t.data) { setLoadError(t.error?.message || 'لم نجد هذا القالب'); setLoading(false); return }
      const row = t.data as any
      setTpl(row as Template)
      setFolders((f.data || []) as TemplateFolder[])
      setHtml(row.content_html || '<h1>عنوان المستند</h1><p></p>')
      setMeta({
        title: row.title || '',
        slug: row.slug || '',
        description: row.description || '',
        folder_id: row.folder_id || '',
        orientation: row.page?.orientation === 'landscape' ? 'landscape' : 'portrait',
        estimated_minutes: row.estimated_minutes ?? 5,
      })
      setLoading(false)
    })()
    return () => { alive = false }
  }, [id])

  const persist = useCallback(async () => {
    if (!id || !dirty.current) return
    setSave('saving')
    const { error } = await supabase.from('templates')
      .update({ content_html: latest.current }).eq('id', id)
    if (error) { setSave('error'); return }
    dirty.current = false
    setSave('saved'); setSavedAt(new Date())
  }, [id])

  const markDirty = useCallback(() => {
    dirty.current = true
    setSave('idle')
    clearTimeout(timer.current)
    timer.current = setTimeout(persist, 1400)
  }, [persist])

  useEffect(() => () => clearTimeout(timer.current), [])
  useEffect(() => {
    const onLeave = (e: BeforeUnloadEvent) => { if (dirty.current) { e.preventDefault(); e.returnValue = '' } }
    window.addEventListener('beforeunload', onLeave)
    return () => window.removeEventListener('beforeunload', onLeave)
  }, [])
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault(); clearTimeout(timer.current); persist()
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [persist])

  const onChange = useCallback((next: string) => { setHtml(next); markDirty() }, [markDirty])

  const saveMeta = async () => {
    if (!id || !tpl) return
    const slug = meta.slug.trim().toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/-+/g, '-')
    if (!slug) { toast('المفتاح مطلوب', 'danger'); return }
    if (!meta.title.trim()) { toast('العنوان مطلوب', 'danger'); return }
    const { error } = await supabase.from('templates').update({
      title: meta.title.trim(),
      slug,
      description: meta.description.trim() || null,
      folder_id: meta.folder_id || null,
      estimated_minutes: Math.max(1, Math.min(60, Number(meta.estimated_minutes) || 5)),
      page: {
        size: 'A4',
        orientation: meta.orientation,
        margins: (tpl as any).page?.margins ?? { top: 16, right: 14, bottom: 16, left: 14 },
      },
    }).eq('id', id)
    if (error) {
      toast(/duplicate|unique/i.test(error.message) ? 'المفتاح مستعملٌ في قالبٍ آخر' : error.message, 'danger')
      return
    }
    toast('حُفظت الإعدادات')
    setTpl({ ...(tpl as any), ...meta, slug })
    setSettings(false)
  }

  const togglePublish = async () => {
    if (!id || !tpl) return
    const next = tpl.status === 'published' ? 'draft' : 'published'
    // ننتظر حفظ المتن قبل النشر — لئلّا يُنشر ما لم يُحفظ بعد
    clearTimeout(timer.current)
    await persist()
    const { error } = await supabase.from('templates').update({ status: next }).eq('id', id)
    if (error) { toast(error.message, 'danger'); return }
    setTpl({ ...(tpl as any), status: next })
    toast(next === 'published' ? 'نُشر — صار يظهر لكلّ المعلّمين' : 'سُحب من المعلّمين')
  }

  if (loading) {
    return <div style={{ padding: 24 }} className="mdd-col"><Skeleton h={44} /><Skeleton h={420} /></div>
  }
  if (loadError || !tpl) {
    return (
      <div style={{ padding: 40, textAlign: 'center' }} className="mdd-col">
        <h2>لم نجد هذا القالب</h2>
        <Button auto variant="primary" onClick={() => nav('/admin/templates')}
          style={{ margin: '0 auto' }}>عُد إلى المكتبة</Button>
      </div>
    )
  }

  const published = tpl.status === 'published'

  return (
    <div className="mdd-ed">
      <div className="mdd-ed-head mdd-noprint">
        <IconButton label="رجوع" onClick={() => nav('/admin/templates')}><IcBack size={17} /></IconButton>

        <span className="mdd-ed-title" style={{ fontWeight: 700, fontSize: 16 }}>{meta.title}</span>
        <Badge tone={published ? 'success' : 'neutral'}>{published ? 'منشور' : 'مسوّدة'}</Badge>
        <SaveDot state={save} at={savedAt} />

        <span className="mdd-ed-head-spacer" />

        <IconButton label="الإعدادات" onClick={() => setSettings(true)}><IcSettings size={16} /></IconButton>
        <Button variant="secondary" size="sm" onClick={() => window.print()}>
          <IcPrint size={15} /><span>اطبع</span>
        </Button>
        <Button variant="secondary" size="sm" onClick={() => setExportOpen(true)}>
          <IcDownload size={15} /><span>صدّر</span>
        </Button>
        <Button variant={published ? 'secondary' : 'primary'} size="sm" onClick={togglePublish}>
          {published ? 'اسحب من المعلّمين' : 'انشر للمعلّمين'}
        </Button>
      </div>

      {!published && (
        <div className="mdd-noprint" style={{ marginBlockEnd: 12 }}>
          <Alert tone="info">
            مسوّدة — لا يراها المعلّمون. اضغط «انشر» حين يجهز القالب.
          </Alert>
        </div>
      )}

      <React.Suspense fallback={<div style={{ padding: 40 }}><Skeleton h={420} /></div>}>
        <DocEditor
          key={id}
          value={html}
          page={(tpl as any).page}
          onChange={onChange}
          onReady={(h) => { handle.current = h }}
          placeholder="صمّم القالب هنا — هذا ما سيراه المعلّم بالضبط"
        />
      </React.Suspense>

      {settings && (
        <Modal open onClose={() => setSettings(false)} title="إعدادات القالب" wide
          footer={
            <>
              <Button variant="secondary" onClick={() => setSettings(false)} block>إلغاء</Button>
              <Button variant="primary" onClick={saveMeta} block>احفظ</Button>
            </>
          }>
          <div className="mdd-col" style={{ gap: 14 }}>
            <Field label="اسم القالب" help="ما يراه المعلّم في المكتبة">
              <Input value={meta.title} onChange={(e) => setMeta({ ...meta, title: e.target.value })} />
            </Field>
            <Field label="المفتاح" help="يظهر في الرابط — حروفٌ لاتينيّة وشُرَط فقط">
              <Input value={meta.slug} onChange={(e) => setMeta({ ...meta, slug: e.target.value })}
                dir="ltr" style={{ textAlign: 'left' }} />
            </Field>
            <Field label="الوصف" help="سطرٌ يشرح متى يُستعمل هذا القالب">
              <Textarea rows={2} value={meta.description}
                onChange={(e) => setMeta({ ...meta, description: e.target.value })} />
            </Field>
            <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
              <Field label="المجلّد">
                <Select value={meta.folder_id}
                  onChange={(e) => setMeta({ ...meta, folder_id: e.target.value })}>
                  <option value="">بلا مجلّد</option>
                  {folders.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                </Select>
              </Field>
              <Field label="اتّجاه الصفحة">
                <Select value={meta.orientation}
                  onChange={(e) => setMeta({ ...meta, orientation: e.target.value as any })}>
                  <option value="portrait">رأسيّ</option>
                  <option value="landscape">أفقيّ</option>
                </Select>
              </Field>
            </div>
            <Field label="الوقت المتوقّع للتعبئة (دقائق)">
              <Input type="number" min={1} max={60} value={meta.estimated_minutes}
                onChange={(e) => setMeta({ ...meta, estimated_minutes: Number(e.target.value) })} />
            </Field>
            {(tpl as any).source_pdf_path && (
              <Alert tone="info">
                مستوردٌ من ملفٍّ من {(tpl as any).source_pages || '؟'} صفحة. الأصل محفوظٌ
                للرجوع ولا يراه أحدٌ سواك.
              </Alert>
            )}
          </div>
        </Modal>
      )}

      {exportOpen && (
        <ExportModal
          open onClose={() => setExportOpen(false)}
          title={meta.title}
          html={html}
          page={(tpl as any).page ?? null}
          watermark={null}
        />
      )}
    </div>
  )
}

function SaveDot({ state, at }: { state: SaveState; at: Date | null }) {
  if (state === 'saving') return <span className="mdd-ed-save"><IcSpinner size={13} className="mdd-spin" /> يُحفظ…</span>
  if (state === 'error') return <span className="mdd-ed-save is-err"><IcAlert size={13} /> تعذّر الحفظ</span>
  if (state === 'saved' || at) {
    return <span className="mdd-ed-save is-ok"><IcCheck size={13} /> حُفظ {at ? fmtRelative(at.toISOString()) : ''}</span>
  }
  return <span className="mdd-ed-save"><IcSave size={13} /> الحفظ تلقائيّ</span>
}
