import React, { useEffect, useMemo, useRef, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { EditorContent, useEditor, type Editor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import { TableKit } from '@tiptap/extension-table'
import { TextAlign } from '@tiptap/extension-text-align'
import { Highlight } from '@tiptap/extension-highlight'
import { TextStyleKit } from '@tiptap/extension-text-style'
import Placeholder from '@tiptap/extension-placeholder'
import { Extension } from '@tiptap/core'
import { DOMSerializer } from '@tiptap/pm/model'
import './style.css'

/* ══════════════════════════════════════════════════════════════════
   نسخةٌ مستقلّة من محرّر مِداد — تعمل بلا خادمٍ ولا حساب.
   نفس المحرّك ونفس الشريط ونفس الورقة الموجودة في المنصّة.
   ══════════════════════════════════════════════════════════════════ */

const ListTab = Extension.create({
  name: 'midadListTab',
  addKeyboardShortcuts() {
    return {
      Tab: () => this.editor.isActive('listItem')
        ? this.editor.commands.sinkListItem('listItem') : false,
      'Shift-Tab': () => this.editor.isActive('listItem')
        ? this.editor.commands.liftListItem('listItem') : false,
    }
  },
})

const FONTS = [
  { k: '', l: 'الافتراضيّ' },
  { k: '"Noto Naskh Arabic", serif', l: 'نسخ' },
  { k: '"Cairo", sans-serif', l: 'القاهرة' },
  { k: '"Amiri", serif', l: 'أميري' },
  { k: 'Arial, sans-serif', l: 'Arial' },
]
const SIZES = ['11px','12px','13px','14px','16px','18px','20px','24px','28px','32px']
const INK = [
  { v: '', l: 'الافتراضيّ' }, { v: '#191733', l: 'أسود' }, { v: '#5B4BD6', l: 'بنفسجيّ' },
  { v: '#0E9F6E', l: 'أخضر' }, { v: '#D64545', l: 'أحمر' }, { v: '#2E7BD6', l: 'أزرق' },
  { v: '#B4791B', l: 'كهرمانيّ' }, { v: '#5B5878', l: 'رماديّ' },
]
const MARKERS = ['#FFF3A3','#C9F7E5','#FBD5D5','#DCE7FB','#EDE7FE']

/* ─────────────── أيقونات ─────────────── */
const S = { stroke: 'currentColor', strokeWidth: 1.7, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, fill: 'none' as const }
const I = (d: string, extra?: React.ReactNode) => (p: { size?: number }) => (
  <svg width={p.size || 18} height={p.size || 18} viewBox="0 0 22 22" fill="none">
    <path d={d} {...S} />{extra}
  </svg>
)
const IcBold = I('M6.6 4h4.9a3.4 3.4 0 0 1 0 6.8H6.6Zm0 6.8h5.6a3.6 3.6 0 0 1 0 7.2H6.6Z')
const IcItalic = I('M13.8 4H8.6M13.4 18H8.2M12.6 4 9.4 18')
const IcUnder = I('M6.4 3.8v6.6a4.6 4.6 0 0 0 9.2 0V3.8M5.4 18.4h11.2')
const IcStrike = I('M4.4 11h13.2M14.8 6.8A3.4 3.4 0 0 0 11.4 4.6c-2 0-3.6 1-3.6 2.8 0 1.2.8 2.1 2.2 2.7M7.6 15a3.6 3.6 0 0 0 3.7 2.4c2.2 0 3.8-1 3.8-2.9 0-.9-.4-1.6-1.2-2.1')
const IcAR = I('M4 5.6h14M8.4 10.2h9.6M4 14.8h14M11 19h7')
const IcAC = I('M4 5.6h14M6.6 10.2h8.8M4 14.8h14M7.6 19h6.8')
const IcAL = I('M4 5.6h14M4 10.2h9.6M4 14.8h14M4 19h7')
const IcAJ = I('M4 5.6h14M4 10.2h14M4 14.8h14M4 19h14')
const IcUL = I('M8.6 6h9.4M8.6 11h9.4M8.6 16h9.4', <><circle cx="4.8" cy="6" r="1.2" fill="currentColor"/><circle cx="4.8" cy="11" r="1.2" fill="currentColor"/><circle cx="4.8" cy="16" r="1.2" fill="currentColor"/></>)
const IcOL = I('M9.2 6h8.8M9.2 11h8.8M9.2 16h8.8', <path d="M4 4.6h1.1V7.6M3.8 10.2h1.6L3.8 12.2h1.7M3.8 14.6h1.6l-1.2 1.4h.6a.7.7 0 0 1 0 1.4H3.9" {...S} strokeWidth={1.4}/>)
const IcQuote = I('M9.6 6.4C7.2 7 5.8 8.8 5.8 11v4.6h4.4V11H8c0-1.5.6-2.4 1.6-2.8ZM18 6.4c-2.4.6-3.8 2.4-3.8 4.6v4.6h4.4V11h-2.2c0-1.5.6-2.4 1.6-2.8Z')
const IcRule = I('M3.6 11h14.8', <path d="M6 6h10M6 16h10" {...S} strokeWidth={1.1} strokeDasharray="2 2.6"/>)
const IcUndo = I('M4.2 9.4h8.4a4.6 4.6 0 1 1 0 9.2H8', <path d="M7.4 5.8 3.8 9.4l3.6 3.6" {...S}/>)
const IcRedo = I('M17.8 9.4H9.4a4.6 4.6 0 1 0 0 9.2H14', <path d="m14.6 5.8 3.6 3.6-3.6 3.6" {...S}/>)
const IcTblAdd = I('M3.6 4.6h14.8v5.8H3.6ZM3.6 10.4h6.6v7H3.6Z', <path d="M15 12.4v5M12.5 14.9h5" {...S}/>)
const IcRowAdd = I('M3.4 13.4h15.2M11 11v4.8', <><rect x="3.4" y="3.6" width="15.2" height="5.4" rx="1.3" {...S}/><path d="M11 17.2v-4.6M8.7 14.9h4.6" {...S}/></>)
const IcColAdd = I('M8.6 3.4v15.2', <><rect x="13" y="3.4" width="5.4" height="15.2" rx="1.3" {...S}/><path d="M4.8 11h4.6M7.1 8.7v4.6" {...S}/></>)
const IcRowDel = I('M3.4 13.4h15.2', <><rect x="3.4" y="3.6" width="15.2" height="5.4" rx="1.3" {...S}/><path d="M8.7 15.9h4.6" {...S}/></>)
const IcColDel = I('M8.6 3.4v15.2', <><rect x="13" y="3.4" width="5.4" height="15.2" rx="1.3" {...S}/><path d="M4.8 11h4.6" {...S}/></>)
const IcMerge = I('M11 5.4v3M11 13.6v3', <><rect x="3.4" y="5.4" width="15.2" height="11.2" rx="1.4" {...S}/><path d="m8.4 11 2.6-2.4 2.6 2.4-2.6 2.4Z" {...S} strokeWidth={1.4}/></>)
const IcSplit = I('M11 5.4v11.2', <rect x="3.4" y="5.4" width="15.2" height="11.2" rx="1.4" {...S}/>)
const IcTable = I('M3.5 9h15M8.5 9v8.5', <rect x="3.5" y="4.5" width="15" height="13" rx="1.6" {...S}/>)
const IcClear = I('M8.8 16.4H18M4.6 16.4l7.2-11.2 5 3.2-6.8 10.6', <path d="m7.6 10.4 5.4 3.4" {...S} strokeWidth={1.3}/>)
const IcInk = I('M11 3.6c2.6 3 4.6 5.4 4.6 7.9A4.6 4.6 0 0 1 6.4 11.5c0-2.5 2-4.9 4.6-7.9Z')
const IcMark = I('m5.4 14.6 6-9 4.4 3-6 9H5.4Z', <path d="M4 18.6h14" {...S} strokeWidth={2.2}/>)
const IcSpark = I('M11 3.2 12.6 8 17.4 9.6 12.6 11.2 11 16 9.4 11.2 4.6 9.6 9.4 8Z', <path d="M16.6 14.2 17.3 16.1 19.2 16.8 17.3 17.5 16.6 19.4 15.9 17.5 14 16.8 15.9 16.1Z" {...S}/>)
const IcDown = I('M11 3.6v9.8M6.8 9.8 11 14l4.2-4.2M4 17.5h14')
const IcPrint = I('M6 8V3.6h10V8M6 15.5H4.5v-6h13v6H16M6 12.5h10v6H6Z')
const IcSun = I('M11 2.5V5M11 17v2.5M3.5 11H6M16 11h2.5M5.6 5.6 7.4 7.4M16.4 5.6 14.6 7.4M5.6 16.4 7.4 14.6M16.4 16.4 14.6 14.6', <circle cx="11" cy="11" r="4" {...S}/>)
const IcMoon = (p: {size?: number}) => <svg width={p.size||18} height={p.size||18} viewBox="0 0 22 22"><path d="M15 12.5A6.5 6.5 0 0 1 8.5 6 6.5 6.5 0 1 0 15 12.5Z" fill="currentColor"/></svg>
const IcChev = I('m4.4 7.8 6.6 6.4 6.6-6.4')
const IcZin = I('m13.8 13.8 4.2 4.2M9.6 7.2v4.8M7.2 9.6h4.8', <circle cx="9.6" cy="9.6" r="5.6" {...S}/>)
const IcZout = I('m13.8 13.8 4.2 4.2M7.2 9.6h4.8', <circle cx="9.6" cy="9.6" r="5.6" {...S}/>)

/* ─────────────── مستندٌ نموذجيّ ─────────────── */
const DOC = `<h1>سجلّ متابعة الطالب</h1>
<p style="text-align:center">وزارة التعليم · إدارة التعليم بجدة · العام الدراسيّ <strong>1448هـ</strong> — الفصل الأوّل</p>
<h2>أوّلًا: بيانات الطالب</h2>
<table><thead><tr><th>اسم الطالب</th><th>رقم الهوية</th><th>الصفّ</th><th>الشعبة</th></tr></thead>
<tbody><tr><td>عبدالله محمّد الغامدي</td><td>1098765432</td><td>أوّل ثانوي</td><td>٣</td></tr></tbody></table>
<h2>ثانيًا: الدرجات</h2>
<table><thead>
<tr><th rowspan="2">المادة</th><th colspan="3">الفصل الأوّل</th><th rowspan="2">المجموع</th></tr>
<tr><th>أعمال السنة</th><th>العمليّ</th><th>النهائيّ</th></tr></thead>
<tbody>
<tr><td>الرياضيّات</td><td>18</td><td>9</td><td>28</td><td><strong>55</strong></td></tr>
<tr><td>الإحصاء</td><td>14</td><td>8</td><td>21</td><td><strong>43</strong></td></tr>
<tr><td colspan="4" style="text-align:center">المتوسّط</td><td><strong>49</strong></td></tr>
</tbody></table>
<h2>ثالثًا: ملاحظات المعلّم</h2>
<ul>
<li>الحضور <strong>منتظم</strong> ولا غياب بلا عذر.</li>
<li>المشاركة الصفّية:
  <ul><li>الرياضيّات: <span style="color:#0E9F6E">ممتازة</span></li>
      <li>الإحصاء: <mark data-color="#FFF3A3">تحتاج متابعة</mark></li></ul></li>
<li>الواجبات مكتملة.</li>
</ul>
<h2>رابعًا: الخطّة العلاجيّة</h2>
<ol><li>حصّة تقويةٍ أسبوعيّة في الإحصاء.</li>
<li>تواصلٌ مع وليّ الأمر كلّ أسبوعين.</li>
<li>متابعةُ الواجبات اليوميّة.</li></ol>
<blockquote>الطالب مجتهد، ويحتاج متابعةً في الإحصاء وحده.</blockquote>
<h2>خامسًا: الاعتماد</h2>
<table><thead><tr><th>معلّم المادة</th><th>وكيل الشؤون التعليميّة</th><th>مدير المدرسة</th></tr></thead>
<tbody><tr><td><br></td><td><br></td><td><br></td></tr></tbody></table>`

/* ─────────────── الشريط ─────────────── */
function TB({ icon: Ic, title, onClick, on, disabled, danger }: any) {
  return (
    <button type="button" title={title} aria-label={title} aria-pressed={on || undefined}
      disabled={disabled} onMouseDown={(e) => e.preventDefault()} onClick={onClick}
      className={`tb-b${on ? ' on' : ''}${danger ? ' dn' : ''}`}><Ic size={17} /></button>
  )
}

function Pick({ value, options, onPick, width, title }: any) {
  return (
    <span className="tb-pick" style={{ width }}>
      <select value={value} title={title} aria-label={title} onChange={(e) => onPick(e.target.value)}>
        {options.map((o: any) => <option key={o.v} value={o.v}>{o.l}</option>)}
      </select>
      <IcChev size={12} />
    </span>
  )
}

function Swatch({ icon: Ic, title, items, current, onPick }: any) {
  const [open, setOpen] = useState(false)
  const box = useRef<HTMLSpanElement>(null)
  useEffect(() => {
    if (!open) return
    const away = (e: MouseEvent) => { if (!box.current?.contains(e.target as Node)) setOpen(false) }
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false)
    document.addEventListener('mousedown', away); document.addEventListener('keydown', esc)
    return () => { document.removeEventListener('mousedown', away); document.removeEventListener('keydown', esc) }
  }, [open])
  return (
    <span className="tb-sw" ref={box}>
      <button type="button" title={title} aria-label={title} className={`tb-b${current ? ' on' : ''}`}
        onMouseDown={(e) => e.preventDefault()} onClick={() => setOpen((v) => !v)}>
        <Ic size={17} />{current ? <i className="dot" style={{ background: current }} /> : null}
      </button>
      {open && (
        <div className="tb-pop">
          {items.map((it: any) => (
            <button key={it.v || 'n'} type="button" title={it.l || it.v}
              className={`tb-si${current === it.v ? ' on' : ''}`}
              onMouseDown={(e) => e.preventDefault()}
              onClick={() => { onPick(it.v); setOpen(false) }}>
              {it.v ? <i style={{ background: it.v }} /> : <span className="none">بلا</span>}
            </button>
          ))}
        </div>
      )}
    </span>
  )
}

function Toolbar({ editor, onImprove, canImprove }: { editor: Editor | null; onImprove: () => void; canImprove: boolean }) {
  const [, bump] = useState(0)
  useEffect(() => {
    if (!editor) return
    const on = () => bump((n) => n + 1)
    editor.on('transaction', on); editor.on('selectionUpdate', on)
    return () => { editor.off('transaction', on); editor.off('selectionUpdate', on) }
  }, [editor])
  if (!editor) return <div className="tb" style={{ minHeight: 44 }} />
  const inTable = editor.isActive('table')
  const c = () => editor.chain().focus()
  return (
    <div className="tb" role="toolbar" aria-label="أدوات التحرير">
      <span className="g">
        <TB icon={IcUndo} title="تراجع" disabled={!editor.can().undo()} onClick={() => c().undo().run()} />
        <TB icon={IcRedo} title="إعادة" disabled={!editor.can().redo()} onClick={() => c().redo().run()} />
      </span>
      <span className="g">
        <Pick title="النمط" width={100}
          value={editor.isActive('heading', { level: 1 }) ? 'h1' : editor.isActive('heading', { level: 2 }) ? 'h2' : editor.isActive('heading', { level: 3 }) ? 'h3' : 'p'}
          options={[{ v: 'p', l: 'نصّ عاديّ' }, { v: 'h1', l: 'عنوان رئيسيّ' }, { v: 'h2', l: 'عنوان' }, { v: 'h3', l: 'عنوان فرعيّ' }]}
          onPick={(v: string) => v === 'p' ? c().setParagraph().run() : c().setHeading({ level: Number(v[1]) as 1 | 2 | 3 }).run()} />
        <Pick title="الخطّ" width={92} value={editor.getAttributes('textStyle').fontFamily || ''}
          options={FONTS.map((f) => ({ v: f.k, l: f.l }))}
          onPick={(v: string) => v ? c().setFontFamily(v).run() : c().unsetFontFamily().run()} />
        <Pick title="الحجم" width={62} value={editor.getAttributes('textStyle').fontSize || ''}
          options={[{ v: '', l: 'تلقائيّ' }, ...SIZES.map((s) => ({ v: s, l: s.replace('px', '') }))]}
          onPick={(v: string) => v ? c().setFontSize(v).run() : c().unsetFontSize().run()} />
      </span>
      <span className="g">
        <TB icon={IcBold} title="عريض" on={editor.isActive('bold')} onClick={() => c().toggleBold().run()} />
        <TB icon={IcItalic} title="مائل" on={editor.isActive('italic')} onClick={() => c().toggleItalic().run()} />
        <TB icon={IcUnder} title="تحته خطّ" on={editor.isActive('underline')} onClick={() => c().toggleUnderline().run()} />
        <TB icon={IcStrike} title="يتوسّطه خطّ" on={editor.isActive('strike')} onClick={() => c().toggleStrike().run()} />
        <Swatch icon={IcInk} title="لون النصّ" items={INK.map((i) => ({ v: i.v, l: i.l }))}
          current={editor.getAttributes('textStyle').color || ''}
          onPick={(v: string) => v ? c().setColor(v).run() : c().unsetColor().run()} />
        <Swatch icon={IcMark} title="تظليل" items={[{ v: '', l: 'بلا' }, ...MARKERS.map((m) => ({ v: m, l: '' }))]}
          current={editor.getAttributes('highlight').color || ''}
          onPick={(v: string) => v ? c().setHighlight({ color: v }).run() : c().unsetHighlight().run()} />
      </span>
      <span className="g">
        <TB icon={IcAR} title="يمين" on={editor.isActive({ textAlign: 'right' })} onClick={() => c().setTextAlign('right').run()} />
        <TB icon={IcAC} title="وسط" on={editor.isActive({ textAlign: 'center' })} onClick={() => c().setTextAlign('center').run()} />
        <TB icon={IcAL} title="يسار" on={editor.isActive({ textAlign: 'left' })} onClick={() => c().setTextAlign('left').run()} />
        <TB icon={IcAJ} title="ضبط" on={editor.isActive({ textAlign: 'justify' })} onClick={() => c().setTextAlign('justify').run()} />
      </span>
      <span className="g">
        <TB icon={IcUL} title="قائمة نقطيّة" on={editor.isActive('bulletList')} onClick={() => c().toggleBulletList().run()} />
        <TB icon={IcOL} title="قائمة مرقّمة" on={editor.isActive('orderedList')} onClick={() => c().toggleOrderedList().run()} />
        <TB icon={IcQuote} title="اقتباس" on={editor.isActive('blockquote')} onClick={() => c().toggleBlockquote().run()} />
        <TB icon={IcRule} title="خطٌّ فاصل" onClick={() => c().setHorizontalRule().run()} />
      </span>
      <span className="g">
        <TB icon={IcTblAdd} title="أدرج جدولًا" onClick={() => c().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()} />
        {inTable && <>
          <TB icon={IcRowAdd} title="صفٌّ أسفل" onClick={() => c().addRowAfter().run()} />
          <TB icon={IcColAdd} title="عمودٌ يمين" onClick={() => c().addColumnBefore().run()} />
          <TB icon={IcRowDel} title="احذف الصفّ" onClick={() => c().deleteRow().run()} />
          <TB icon={IcColDel} title="احذف العمود" onClick={() => c().deleteColumn().run()} />
          <TB icon={IcMerge} title="ادمج الخلايا" onClick={() => c().mergeCells().run()} />
          <TB icon={IcSplit} title="افصل الخليّة" onClick={() => c().splitCell().run()} />
          <TB icon={IcTable} title="صفّ العنوان" onClick={() => c().toggleHeaderRow().run()} />
          <TB icon={IcClear} title="احذف الجدول" danger onClick={() => c().deleteTable().run()} />
        </>}
      </span>
      <span className="g">
        <TB icon={IcClear} title="أزل التنسيق" onClick={() => c().unsetAllMarks().clearNodes().run()} />
      </span>
      <span className="sp" />
      <button type="button" className="tb-ai" disabled={!canImprove} onClick={onImprove}
        onMouseDown={(e) => e.preventDefault()}
        title={canImprove ? 'حسِّن النصّ المحدَّد' : 'حدّد نصًّا أوّلًا'}>
        <IcSpark size={15} /><span>حسِّن</span>
      </button>
    </div>
  )
}

/* ─────────────── التطبيق ─────────────── */
function App() {
  const [zoom, setZoom] = useState(1)
  const [sel, setSel] = useState({ empty: true, text: '' })
  const [improve, setImprove] = useState(false)
  const [dark, setDark] = useState(() => {
    try { return localStorage.getItem('midad.demo.dark') === '1' } catch { return false }
  })

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light')
    try { localStorage.setItem('midad.demo.dark', dark ? '1' : '0') } catch {}
  }, [dark])

  const editor = useEditor({
    extensions: [
      StarterKit.configure({ heading: { levels: [1, 2, 3] }, codeBlock: false, code: false,
        link: { openOnClick: false, autolink: true } }),
      TableKit.configure({ table: { resizable: true, allowTableNodeSelection: true } }),
      TextAlign.configure({ types: ['heading', 'paragraph'], defaultAlignment: 'right' }),
      Highlight.configure({ multicolor: true }),
      TextStyleKit.configure({ lineHeight: false }),
      Placeholder.configure({ placeholder: 'اكتب هنا… أو أدرج جدولًا من شريط الأدوات' }),
      ListTab,
    ],
    content: DOC,
    immediatelyRender: true,
    editorProps: { attributes: { class: 'doc', dir: 'rtl', lang: 'ar', spellcheck: 'false' } },
    onSelectionUpdate: ({ editor: e }) => {
      if (e.isDestroyed) return
      const { from, to, empty } = e.state.selection
      setSel({ empty, text: empty ? '' : e.state.doc.textBetween(from, to, '\n', ' ') })
    },
  })

  const words = useMemo(() => {
    if (!editor || editor.isDestroyed) return 0
    return editor.state.doc.textContent.trim().split(/\s+/).filter(Boolean).length
  }, [editor, sel])

  return (
    <div className="wrap">
      <header className="top">
        <span className="brand">
          <span className="logo">
            <svg width="20" height="20" viewBox="0 0 40 40" fill="none">
              <path d="M9.5 27.5V15.2c0-1 1.2-1.6 2-1L16 17.6c.5.4 1.2.4 1.7 0l4.6-3.4c.8-.6 2 0 2 1v12.3"
                stroke="currentColor" strokeWidth={2.8} strokeLinecap="round" strokeLinejoin="round" />
              <path d="M28.4 12.5v13.2c0 1 .8 1.8 1.8 1.8" stroke="currentColor" strokeWidth={2.8} strokeLinecap="round" />
            </svg>
          </span>
          <span>
            <b>مِداد للتحرير</b>
            <i>نسخةٌ للتجربة — تعمل بلا حساب. جرّبها كما لو كانت الوورد.</i>
          </span>
        </span>
        <span className="sp" />
        <button className="btn" onClick={() => window.print()} title="اطبع أو احفظ PDF">
          <IcPrint size={15} /><span className="hide-s">اطبع</span>
        </button>
        <button className="btn ghost" onClick={() => setDark((v) => !v)} title="بدّل المظهر">
          {dark ? <IcSun size={15} /> : <IcMoon size={15} />}
        </button>
      </header>

      <Toolbar editor={editor} canImprove={!sel.empty} onImprove={() => setImprove(true)} />

      <div className="stage">
        <div className="sheet" style={{ transform: zoom === 1 ? undefined : `scale(${zoom})` }}>
          <EditorContent editor={editor} />
        </div>
      </div>

      <footer className="status">
        <span>A4 · رأسيّ</span>
        <span className="dim">{words} كلمة</span>
        {!sel.empty && <span className="hl">{sel.text.trim().split(/\s+/).length} كلمة محدَّدة</span>}
        <span className="sp" />
        <span className="zoom">
          <button onClick={() => setZoom((z) => Math.max(0.5, Math.round((z - 0.1) * 10) / 10))} title="تصغير"><IcZout size={14} /></button>
          <b>{Math.round(zoom * 100)}%</b>
          <button onClick={() => setZoom((z) => Math.min(1.6, Math.round((z + 0.1) * 10) / 10))} title="تكبير"><IcZin size={14} /></button>
        </span>
      </footer>

      {improve && editor && <Improve editor={editor} onClose={() => setImprove(false)} />}
    </div>
  )
}

/* ─────────────── نافذة التحسين ─────────────── */
const TONES = [
  { k: 'formal', l: 'أرسمُ أسلوبًا', h: 'لغةٌ إداريّةٌ رسميّة كما في وثائق الوزارة' },
  { k: 'simple', l: 'أوضح', h: 'جملٌ أقصر وأصرح، والطابع الرسميّ باقٍ' },
  { k: 'shorter', l: 'أقصر', h: 'يُحذف الحشو ويبقى المعنى' },
  { k: 'longer', l: 'أوسع', h: 'تفصيلٌ تربويّ وأمثلةٌ من الميدان' },
]

function Improve({ editor, onClose }: { editor: Editor; onClose: () => void }) {
  const { from, to } = editor.state.selection
  const text = editor.state.doc.textBetween(from, to, '\n', ' ')
  const [tone, setTone] = useState('formal')
  return (
    <div className="ov" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <h3>حسِّن النصّ المحدَّد</h3>
        <div className="src"><span className="lab">المحدَّد</span><p>{text.slice(0, 600)}</p></div>
        <div className="tones">
          {TONES.map((t) => (
            <button key={t.k} className={`tone${tone === t.k ? ' on' : ''}`} onClick={() => setTone(t.k)}>
              <b>{t.l}</b><span>{t.h}</span>
            </button>
          ))}
        </div>
        <div className="note">
          في المنصّة يُرسل المحدَّد إلى الذكاء الاصطناعيّ فيعيده مصوغًا، ثمّ
          تُوافق أو تطلب اقتراحًا آخر. وهو لا يخترع بياناتٍ ولا أرقامًا.
          <br /><b>هذه نسخةُ تجربةٍ بلا حساب، فالتحسين معروضٌ ولا يُنفَّذ.</b>
        </div>
        <div className="row">
          <button className="btn pri" onClick={onClose}>فهمت</button>
        </div>
      </div>
    </div>
  )
}

createRoot(document.getElementById('root')!).render(<App />)
