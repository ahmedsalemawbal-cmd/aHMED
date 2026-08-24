import React, { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { supabase } from '../../lib/supabase'
import type { DocumentRow, Template } from '../../lib/types'
import { fmtRelative } from '../../lib/format'
import { normalizePage } from '../../lib/editor'
import { Alert, Button, IconButton, Modal, Skeleton } from '../../ui/kit'
import { IcBack, IcPrint, IcDownload, IcSpinner, IcCheck, IcCopy, IcTrash, IcSave, IcAlert } from '../../ui/icons'
/* التحميل متأخّرٌ عن قصد: محرّك التحرير لا يُنزَّل إلّا حين يُفتح مستند */
const DocEditor = React.lazy(() => import('./DocEditor'))
import type { DocEditorHandle } from './DocEditor'
import ExportModal from './ExportModal'

type SaveState = 'idle' | 'saving' | 'saved' | 'error'

export default function Editor() {
  const { id } = useParams()
  const nav = useNavigate()
  const { access, toast } = useApp()

  const [doc, setDoc] = useState<DocumentRow | null>(null)
  const [tpl, setTpl] = useState<Template | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [title, setTitle] = useState('')
  const [html, setHtml] = useState('')
  const [save, setSave] = useState<SaveState>('idle')
  const [savedAt, setSavedAt] = useState<Date | null>(null)
  const [exportOpen, setExportOpen] = useState(false)
  const [confirmDel, setConfirmDel] = useState(false)

  const handle = useRef<DocEditorHandle | null>(null)
  const dirty = useRef(false)
  const timer = useRef<any>(null)
  const latest = useRef({ html, title })
  latest.current = { html, title }

  const readOnly = access !== 'trial' && access !== 'active'

  /* ─────────── التحميل ─────────── */
  useEffect(() => {
    let alive = true
    ;(async () => {
      setLoading(true); setLoadError(null)
      const { data: d, error } = await supabase.from('documents').select('*').eq('id', id).maybeSingle()
      if (!alive) return
      if (error || !d) { setLoadError(error?.message || 'لم نجد هذا الملفّ'); setLoading(false); return }

      const row = d as any
      let starting: string = row.content_html || ''
      let t: Template | null = null

      if (row.template_id) {
        const { data: tt } = await supabase.from('templates').select('*').eq('id', row.template_id).maybeSingle()
        if (!alive) return
        t = (tt as Template) || null
      }

      /* ملفٌّ أُنشئ ولم يُكتب فيه بعد: ينسخ متن القالب مرّةً واحدة، ثمّ
         يستقلّ عنه — فتعديلُ القالب لاحقًا لا يعبث بما كتبه المعلّم. */
      if (!starting && t?.content_html) starting = t.content_html
      if (!starting) starting = '<h1>مستندٌ جديد</h1><p></p>'

      setDoc(row as DocumentRow)
      setTpl(t)
      setTitle(row.title || '')
      setHtml(starting)
      setLoading(false)
    })()
    return () => { alive = false }
  }, [id])

  /* ─────────── الحفظ ─────────── */
  const persist = useCallback(async () => {
    if (!id || !dirty.current || readOnly) return
    setSave('saving')
    const { error } = await supabase.from('documents')
      .update({ content_html: latest.current.html, title: latest.current.title })
      .eq('id', id)
    if (error) { setSave('error'); return }
    dirty.current = false
    setSave('saved'); setSavedAt(new Date())
  }, [id, readOnly])

  const markDirty = useCallback(() => {
    if (readOnly) return
    dirty.current = true
    setSave('idle')
    clearTimeout(timer.current)
    timer.current = setTimeout(persist, 1400)
  }, [persist, readOnly])

  useEffect(() => () => clearTimeout(timer.current), [])

  useEffect(() => {
    const onLeave = (e: BeforeUnloadEvent) => { if (dirty.current) { e.preventDefault(); e.returnValue = '' } }
    window.addEventListener('beforeunload', onLeave)
    return () => window.removeEventListener('beforeunload', onLeave)
  }, [])

  // Ctrl+S يحفظ فورًا بدل أن يفتح حفظ الصفحة في المتصفّح
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

  const duplicate = async () => {
    if (!doc) return
    const { data, error } = await supabase.from('documents').insert({
      subscriber_id: (doc as any).subscriber_id,
      owner_id: (doc as any).owner_id,
      template_id: (doc as any).template_id,
      title: `نسخة من ${title}`,
      content_html: latest.current.html,
      page: (doc as any).page ?? null,
    }).select('id').single()
    if (error || !data) { toast('تعذّرت المضاعفة', 'danger'); return }
    toast('أُنشئت نسخة')
    nav(`/app/doc/${(data as any).id}`)
  }

  const remove = async () => {
    if (!id) return
    const { error } = await supabase.from('documents').delete().eq('id', id)
    if (error) { toast('تعذّر الحذف', 'danger'); return }
    toast('حُذف الملفّ')
    nav('/app/files')
  }

  if (loading) {
    return <div style={{ padding: 24 }} className="mdd-col"><Skeleton h={44} /><Skeleton h={420} /></div>
  }
  if (loadError || !doc) {
    return (
      <div style={{ padding: 40, textAlign: 'center' }} className="mdd-col">
        <h2>لم نجد هذا الملفّ</h2>
        <p className="mdd-prose" style={{ margin: '0 auto' }}>ربّما حُذف، أو أنّه ليس ضمن ملفّات مشتركك.</p>
        <Button auto variant="primary" onClick={() => nav('/app/files')} style={{ margin: '0 auto' }}>
          عُد إلى ملفّاتي
        </Button>
      </div>
    )
  }

  return (
    <div className="mdd-ed">
      {/* ─────────── الترويسة ─────────── */}
      <div className="mdd-ed-head mdd-noprint">
        <IconButton label="رجوع" onClick={() => nav('/app/files')}><IcBack size={17} /></IconButton>

        <input
          className="mdd-ed-title" value={title} disabled={readOnly}
          onChange={(e) => { setTitle(e.target.value); markDirty() }}
          placeholder="اسم الملفّ" aria-label="اسم الملفّ"
        />

        <SaveDot state={save} at={savedAt} readOnly={readOnly} />

        <span className="mdd-ed-head-spacer" />

        <IconButton label="مضاعفة" onClick={duplicate}><IcCopy size={16} /></IconButton>
        <IconButton label="حذف" onClick={() => setConfirmDel(true)}><IcTrash size={16} /></IconButton>
        <Button variant="secondary" size="sm" onClick={() => window.print()}>
          <IcPrint size={15} /><span>اطبع</span>
        </Button>
        <Button variant="primary" size="sm" onClick={() => setExportOpen(true)}>
          <IcDownload size={15} /><span>صدّر</span>
        </Button>
      </div>

      {readOnly && (
        <div className="mdd-noprint" style={{ marginBlockEnd: 12 }}>
          <Alert tone="warn">
            انتهى اشتراكك — يمكنك القراءة والطباعة والتصدير، ولا يُحفظ أيّ تعديل.
          </Alert>
        </div>
      )}

      {/* ─────────── المحرّر ─────────── */}
      <React.Suspense fallback={<div style={{ padding: 40 }}><Skeleton h={420} /></div>}>
        <DocEditor
          /* مفتاحٌ بمعرّف الملفّ: فتحُ ملفٍّ آخر يُركّب محرّرًا جديدًا بدل
             أن يُعاد بذر القديم — أنظف وأقلّ عرضةً للخطأ. */
          key={id}
          value={html}
          page={(doc as any).page ?? tpl?.page}
          editable={!readOnly}
          onChange={onChange}
          onReady={(h) => { handle.current = h }}
          placeholder="اكتب هنا… أو أدرج جدولًا من شريط الأدوات"
        />
      </React.Suspense>

      {exportOpen && (
        <ExportModal
          open onClose={() => setExportOpen(false)}
          title={title}
          html={html}
          page={(doc as any).page ?? tpl?.page ?? null}
        />
      )}

      {confirmDel && (
        <Modal open onClose={() => setConfirmDel(false)} title="حذف الملفّ">
          <div className="mdd-col" style={{ gap: 14 }}>
            <Alert tone="danger">
              سيُحذف «{title}» ولا يمكن إرجاعه. صدّره أوّلًا إن أردت الاحتفاظ به.
            </Alert>
            <div className="mdd-row" style={{ gap: 9, justifyContent: 'flex-start' }}>
              <Button variant="danger" onClick={remove}>احذف</Button>
              <Button variant="ghost" onClick={() => setConfirmDel(false)}>تراجَع</Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  )
}

/* ─────────── مؤشّر الحفظ ─────────── */

function SaveDot({ state, at, readOnly }: { state: SaveState; at: Date | null; readOnly: boolean }) {
  if (readOnly) return <span className="mdd-ed-save">للقراءة فقط</span>
  if (state === 'saving') {
    return <span className="mdd-ed-save"><IcSpinner size={13} className="mdd-spin" /> يُحفظ…</span>
  }
  if (state === 'error') {
    return <span className="mdd-ed-save is-err"><IcAlert size={13} /> تعذّر الحفظ</span>
  }
  if (state === 'saved' || at) {
    return <span className="mdd-ed-save is-ok"><IcCheck size={13} /> حُفظ {at ? fmtRelative(at.toISOString()) : ''}</span>
  }
  return <span className="mdd-ed-save"><IcSave size={13} /> الحفظ تلقائيّ</span>
}
