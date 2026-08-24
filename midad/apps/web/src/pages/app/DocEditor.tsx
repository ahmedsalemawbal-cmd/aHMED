import React, { useCallback, useEffect, useRef, useState } from 'react'
import { EditorContent, useEditor } from '@tiptap/react'
import { extensions, readSelection, pageStyle, normalizePage, type PageSetup } from '../../lib/editor'
import DocToolbar from './DocToolbar'
import { IcZoomIn, IcZoomOut, IcPage } from '../../ui/icons'

/**
 * مِداد للتحرير — صفحةٌ بمقاس A4 تُحرَّر من داخلها، وشريط أدوات فوقها.
 *
 * ما يجعلها تشبه الوورد فعلًا:
 * ١) الورقة مرئيّةٌ بأبعادها الحقيقيّة (مليمترات لا بكسلات)، فما تراه يُطبع.
 * ٢) التحرير داخل الورقة نفسها — لا استمارةٌ جانبيّة ولا معاينةٌ منفصلة.
 * ٣) الجداول والقوائم يديرها ProseMirror بمخطَّطٍ متحقَّق، فلا تنكسر.
 */

export interface DocEditorHandle {
  getHTML: () => string
  focus: () => void
  isEmpty: () => boolean
}

export default function DocEditor({
  value, page, editable = true, onChange, onReady, zoomable = true, placeholder,
}: {
  value: string
  page?: any
  editable?: boolean
  onChange?: (html: string) => void
  onReady?: (h: DocEditorHandle) => void
  zoomable?: boolean
  placeholder?: string
}) {
  const setup: PageSetup = normalizePage(page)

  /* ‎@page‎ لا يقرأ الأصناف، فالورقة الأفقيّة تُطبع رأسيّةً ما لم نحقن
     القاعدة نفسها. عنصرٌ واحدٌ يُزال مع المكوّن. */
  useEffect(() => {
    if (setup.orientation !== 'landscape') return
    const el = document.createElement('style')
    el.setAttribute('data-mdd-page', 'landscape')
    el.textContent = '@media print { @page { size: A4 landscape; } }'
    document.head.appendChild(el)
    return () => { el.remove() }
  }, [setup.orientation])
  const [zoom, setZoom] = useState(1)
  const [sel, setSel] = useState({ empty: true, text: '' })
  const [improve, setImprove] = useState(false)

  // نُبقي onChange في مرجعٍ كي لا يُعاد إنشاء المحرّر عند كلّ رسم
  const cb = useRef(onChange)
  cb.current = onChange

  /* آخر ما أصدره المحرّر نفسه. الأب يحفظه في حالته ثمّ يُعيده إلينا في
     value — فلو لم نميّز الصادر عن الوارد لأعدنا بناء المستند عند كلّ
     حرف، ولقفزت المؤشّرة إلى آخر الصفحة. */
  const emitted = useRef<string | null>(null)

  const editor = useEditor({
    extensions: extensions(placeholder),
    content: value || '<p></p>',
    editable,
    // React ١٨ بلا تصيير على الخادم: الرسم الفوريّ هو الصحيح،
    // وإغفاله يُطلق تحذيرًا في وضع التطوير
    immediatelyRender: true,
    editorProps: {
      attributes: {
        class: 'mdd-doc-body',
        dir: 'rtl',
        lang: 'ar',
        spellcheck: 'false',
      },
    },
    onUpdate: ({ editor: e }) => {
      if (e.isDestroyed) return
      const html = e.getHTML()
      emitted.current = html
      cb.current?.(html)
    },
    onSelectionUpdate: ({ editor: e }) => {
      if (e.isDestroyed) return
      const s = readSelection(e)
      setSel({ empty: s.empty, text: s.text })
    },
  })

  /* المحتوى الابتدائيّ يُمرَّر إلى useEditor عند الإنشاء. وهذا التأثير
     لِحالةٍ واحدة: محتوًى يأتي من الخارج بعد الإنشاء (تحميلٌ متأخّر، أو
     استعادة نسخة). وما يعود إلينا من كتابتنا نحن نتجاوزه.

     العطب الذي كان هنا: كنّا نُقارن value بآخر ما بُذر وحده، فكلّ حرفٍ
     يكتبه المستخدم يخرج في onUpdate إلى الأب، ويعود في value مختلفًا،
     فنُعيد بناء المستند كلّه — والمؤشّرة تقفز إلى آخر الصفحة، والمستخدم
     الواقف في خليّة يجد نفسه خارج الجدول. ظهر في الاختبار: صفٌّ يُضاف
     ثمّ تختفي أدوات الجدول.

     ولا نستدعي getHTML هنا: المحرّر قد يوجد ومخطَّطه لم يجهز، فتنهار
     الصفحة — وهذا وقع أيضًا. */
  const seeded = useRef(value)
  useEffect(() => {
    if (!editor || editor.isDestroyed) return
    if (value === seeded.current) return
    seeded.current = value
    if (value === emitted.current) return       // صادرٌ منّا، لا وارد
    editor.commands.setContent(value || '<p></p>', { emitUpdate: false })
  }, [editor, value])

  useEffect(() => {
    if (!editor || editor.isDestroyed) return
    editor.setEditable(editable)
  }, [editor, editable])

  /* منفذُ تشخيص: يُفتح بوضع midad.debug=1 في التخزين المحلّي وحده.
     يُغني عن التخمين حين يشتكي مستخدمٌ من سلوكٍ لا نراه. */
  useEffect(() => {
    if (!editor) return
    try { if (localStorage.getItem('midad.debug') !== '1') return } catch { return }
    ;(window as any).__mddEditor = editor
    return () => { try { delete (window as any).__mddEditor } catch {} }
  }, [editor])

  useEffect(() => {
    if (!editor || !onReady) return
    onReady({
      getHTML: () => (editor.isDestroyed ? '' : editor.getHTML()),
      focus: () => { if (!editor.isDestroyed) editor.chain().focus().run() },
      isEmpty: () => (editor.isDestroyed ? true : editor.isEmpty),
    })
  }, [editor, onReady])

  const applyImproved = useCallback((html: string) => {
    if (!editor || editor.isDestroyed) return
    editor.chain().focus().insertContent(html).run()
  }, [editor])

  return (
    <div className="mdd-doc">
      <DocToolbar editor={editor} canImprove={!sel.empty} onImprove={() => setImprove(true)} />

      <div className="mdd-doc-stage">
        <div
          className="mdd-doc-sheet"
          style={{ ...pageStyle(setup), transform: zoom === 1 ? undefined : `scale(${zoom})` }}>
          <EditorContent editor={editor} />
        </div>
      </div>

      <div className="mdd-doc-status">
        <span className="mdd-doc-status-l">
          <IcPage size={14} />
          <span>A4 · {setup.orientation === 'landscape' ? 'أفقيّ' : 'رأسيّ'}</span>
        </span>
        {!sel.empty && (
          <span className="mdd-doc-status-sel">{sel.text.trim().split(/\s+/).length} كلمة محدَّدة</span>
        )}
        <span className="mdd-doc-status-spacer" />
        {zoomable && (
          <span className="mdd-doc-zoom">
            <button type="button" title="تصغير" aria-label="تصغير"
              onClick={() => setZoom((z) => Math.max(0.5, Math.round((z - 0.1) * 10) / 10))}>
              <IcZoomOut size={14} />
            </button>
            <b>{Math.round(zoom * 100)}%</b>
            <button type="button" title="تكبير" aria-label="تكبير"
              onClick={() => setZoom((z) => Math.min(1.6, Math.round((z + 0.1) * 10) / 10))}>
              <IcZoomIn size={14} />
            </button>
          </span>
        )}
      </div>

      {improve && editor && (
        <ImproveSelection
          editor={editor}
          onClose={() => setImprove(false)}
          onApply={(html) => { applyImproved(html); setImprove(false) }}
        />
      )}
    </div>
  )
}

/* ═════════════ تحسين التحديد بالذكاء الاصطناعيّ ═════════════ */

import { callFunction } from '../../lib/supabase'
import { Button, Modal, Alert } from '../../ui/kit'
import type { Editor } from '@tiptap/react'

const TONES = [
  { k: 'formal', label: 'أرسمُ أسلوبًا', hint: 'لغةٌ إداريّةٌ رسميّة كما في وثائق الوزارة' },
  { k: 'simple', label: 'أوضح', hint: 'جملٌ أقصر وأصرح، والطابع الرسميّ باقٍ' },
  { k: 'shorter', label: 'أقصر', hint: 'يُحذف الحشو ويبقى المعنى' },
  { k: 'longer', label: 'أوسع', hint: 'تفصيلٌ تربويّ وأمثلةٌ من الميدان' },
] as const

function ImproveSelection({ editor, onClose, onApply }: {
  editor: Editor
  onClose: () => void
  onApply: (html: string) => void
}) {
  const s = readSelection(editor)
  const [tone, setTone] = useState<string>('formal')
  const [busy, setBusy] = useState(false)
  const [out, setOut] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const run = async () => {
    setBusy(true); setErr(null); setOut(null)
    try {
      const r = await callFunction('ai-improve', {
        text: s.text, tone, field_label: 'مقطعٌ من مستند',
      })
      const t = String((r as any)?.text || '').trim()
      if (!t) throw new Error('لم يعد نصٌّ محسَّن')
      setOut(t)
    } catch (e: any) {
      setErr(e?.message || 'تعذّر التحسين')
    } finally { setBusy(false) }
  }

  /** نصٌّ عاديّ ← فقرات HTML، مع تهريب كلّ محرفٍ خطر */
  const toHtml = (t: string) => t
    .split(/\n{2,}/)
    .map((p) => `<p>${p.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' } as any)[c]).replace(/\n/g, '<br>')}</p>`)
    .join('')

  return (
    <Modal open onClose={onClose} title="حسِّن النصّ المحدَّد" wide>
      <div className="mdd-col" style={{ gap: 14 }}>
        <div className="mdd-imp-src">
          <span className="mdd-imp-lab">المحدَّد</span>
          <p>{s.text.length > 700 ? s.text.slice(0, 700) + '…' : s.text}</p>
        </div>

        <div className="mdd-imp-tones">
          {TONES.map((t) => (
            <button
              key={t.k} type="button"
              className={`mdd-imp-tone${tone === t.k ? ' is-on' : ''}`}
              onClick={() => setTone(t.k)}>
              <b>{t.label}</b>
              <span>{t.hint}</span>
            </button>
          ))}
        </div>

        {err && <Alert tone="danger">{err}</Alert>}

        {out !== null && (
          <div className="mdd-imp-out">
            <span className="mdd-imp-lab">المقترح</span>
            <p>{out}</p>
          </div>
        )}

        <div className="mdd-row" style={{ gap: 9, justifyContent: 'flex-start' }}>
          {out === null ? (
            <Button variant="primary" onClick={run} loading={busy}>حسِّن</Button>
          ) : (
            <>
              <Button variant="primary" onClick={() => onApply(toHtml(out))}>استبدل المحدَّد</Button>
              <Button variant="secondary" onClick={run} loading={busy}>اقتراحٌ آخر</Button>
            </>
          )}
          <Button variant="ghost" onClick={onClose}>إلغاء</Button>
        </div>

        <p className="mdd-imp-note">
          التحسين يعيد صياغة ما حدّدتَه ولا يخترع بياناتٍ ولا أرقامًا. راجعه قبل الاستبدال.
        </p>
      </div>
    </Modal>
  )
}
