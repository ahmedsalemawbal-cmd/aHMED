import React, { useEffect, useRef, useState } from 'react'
import type { Editor } from '@tiptap/react'
import { FONTS, SIZES, INK, MARKERS } from '../../lib/editor'
import {
  IcBold, IcItalic, IcUnderline, IcStrike, IcAlignRight, IcAlignCenter, IcAlignLeft,
  IcAlignJustify, IcListBullet, IcListNumber, IcQuote, IcRule, IcUndo, IcRedo,
  IcRowAdd, IcColAdd, IcRowDel, IcColDel, IcMerge, IcSplit, IcMarker, IcInk,
  IcClear, IcLink, IcTableAdd, IcTable, IcChevronDown, IcSpark, IcImageAdd, IcSpinner,
  IcCellFill, IcRowFill, IcPageBreak,
} from '../../ui/icons'
import { readStyleProp, setStyleProp } from '../../lib/styleSafe'
import { pickImage, uploadDocImage, imageError } from '../../lib/docImages'
import { useApp } from '../../lib/store'

/**
 * شريط الأدوات — شبيه الوورد، بالأساسيّات وحدها.
 *
 * قاعدةٌ واحدة حكمت الاختيار: كلّ زرٍّ هنا يفعل شيئًا يحتاجه معلّمٌ يكتب
 * وثيقةً مدرسيّة. ما لا يحتاجه (شيفرة برمجيّة، معادلات، مراجع) ليس هنا.
 */

type Grp = { id: string; title: string }

/**
 * ألوان الخلفيّات: بلا، وأربعة رماديّات فاتحة للترويسات، وألوان الهويّة.
 * قليلةٌ ومقصودة — منتقي ألوانٍ لا نهائيّ يُنتج مستنداتٍ لا تُطبع.
 */
const FILLS = [
  { v: '', label: 'بلا' },
  { v: '#f7f8fc', label: 'رماديّ فاتح' },
  { v: '#eef0f6', label: 'رماديّ' },
  { v: '#e3e6ef', label: 'رماديّ داكن' },
  { v: '#443c86', label: 'بنفسجيّ' },
  { v: '#2f8b9b', label: 'فيروزيّ' },
  { v: '#dd8a3e', label: 'كهرمانيّ' },
  { v: '#3d9b6d', label: 'أخضر' },
  { v: '#b03c53', label: 'أحمر' },
  { v: '#ECE9FC', label: 'بنفسجيّ فاتح' },
  { v: '#E2F6EE', label: 'أخضر فاتح' },
  { v: '#FBF0DC', label: 'كهرمانيّ فاتح' },
]

export default function DocToolbar({ editor, onImprove, canImprove }: {
  editor: Editor | null
  onImprove: () => void
  canImprove: boolean
}) {
  // إعادة الرسم عند كلّ تغيّرٍ في التحديد أو المحتوى، وإلّا بقيت الأزرار
  // تعرض حالةً قديمة — وهذا أشهر عطبٍ في أشرطة أدوات TipTap
  const { subscriber, toast } = useApp()
  const [busyImg, setBusyImg] = useState(false)
  const [, bump] = useState(0)
  useEffect(() => {
    if (!editor) return
    const on = () => bump((n) => n + 1)
    editor.on('transaction', on)
    editor.on('selectionUpdate', on)
    return () => { editor.off('transaction', on); editor.off('selectionUpdate', on) }
  }, [editor])

  /**
   * إدراج صورة: يُنتقى الملفّ، يُرفع، ثمّ يُدرج رابطه في موضع المؤشّر.
   *
   * والرفع قبل الإدراج عمدًا: لو أدرجنا الصورة بـ`blob:` ثمّ رفعناها،
   * لبقي في المتن رابطٌ يموت بإغلاق التبويب — فيُحفظ المستند بصورةٍ
   * مكسورة ولا يُدرى متى انكسرت.
   */
  const addImage = async () => {
    const file = await pickImage()
    if (!file) return
    const bad = imageError(file)
    if (bad) { toast(bad, 'danger'); return }
    setBusyImg(true)
    try {
      const url = await uploadDocImage(file, subscriber?.id || '')
      editor?.chain().focus().setImage({ src: url, alt: file.name }).run()
      toast('أُدرجت الصورة')
    } catch (e: any) {
      toast(e?.message || 'تعذّر رفع الصورة', 'danger')
    } finally { setBusyImg(false) }
  }

  if (!editor) return <div className="mdd-tb" aria-hidden="true" style={{ minHeight: 46 }} />

  const inTable = editor.isActive('table')

  return (
    <div className="mdd-tb" role="toolbar" aria-label="أدوات التحرير">
      {/* ── تراجع ── */}
      <Grp>
        <TB icon={<IcUndo />} title="تراجع (Ctrl+Z)" disabled={!editor.can().undo()}
          onClick={() => editor.chain().focus().undo().run()} />
        <TB icon={<IcRedo />} title="إعادة (Ctrl+Shift+Z)" disabled={!editor.can().redo()}
          onClick={() => editor.chain().focus().redo().run()} />
      </Grp>

      {/* ── النمط ── */}
      <Grp>
        <Pick
          title="نمط النصّ"
          width={104}
          value={
            editor.isActive('heading', { level: 1 }) ? 'h1'
            : editor.isActive('heading', { level: 2 }) ? 'h2'
            : editor.isActive('heading', { level: 3 }) ? 'h3'
            : 'p'
          }
          options={[
            { v: 'p', label: 'نصّ عاديّ' },
            { v: 'h1', label: 'عنوان رئيسيّ' },
            { v: 'h2', label: 'عنوان' },
            { v: 'h3', label: 'عنوان فرعيّ' },
          ]}
          onPick={(v) => {
            const c = editor.chain().focus()
            if (v === 'p') c.setParagraph().run()
            else c.setHeading({ level: Number(v[1]) as 1 | 2 | 3 }).run()
          }}
        />
        <Pick
          title="الخطّ" width={96}
          value={editor.getAttributes('textStyle').fontFamily || ''}
          options={FONTS.map((f) => ({ v: f.key, label: f.label }))}
          onPick={(v) => {
            const c = editor.chain().focus()
            v ? c.setFontFamily(v).run() : c.unsetFontFamily().run()
          }}
        />
        <Pick
          title="الحجم" width={66}
          value={editor.getAttributes('textStyle').fontSize || ''}
          options={[{ v: '', label: 'تلقائيّ' }, ...SIZES.map((s) => ({ v: s, label: s.replace('px', '') }))]}
          onPick={(v) => {
            const c = editor.chain().focus()
            v ? c.setFontSize(v).run() : c.unsetFontSize().run()
          }}
        />
      </Grp>

      {/* ── التوكيد ── */}
      <Grp>
        <TB icon={<IcBold />} title="عريض (Ctrl+B)" on={editor.isActive('bold')}
          onClick={() => editor.chain().focus().toggleBold().run()} />
        <TB icon={<IcItalic />} title="مائل (Ctrl+I)" on={editor.isActive('italic')}
          onClick={() => editor.chain().focus().toggleItalic().run()} />
        <TB icon={<IcUnderline />} title="تحته خطّ (Ctrl+U)" on={editor.isActive('underline')}
          onClick={() => editor.chain().focus().toggleUnderline().run()} />
        <TB icon={<IcStrike />} title="يتوسّطه خطّ" on={editor.isActive('strike')}
          onClick={() => editor.chain().focus().toggleStrike().run()} />
        <Swatches
          icon={<IcInk />} title="لون النصّ"
          items={INK.map((i) => ({ v: i.v, label: i.label }))}
          current={editor.getAttributes('textStyle').color || ''}
          onPick={(v) => {
            const c = editor.chain().focus()
            v ? c.setColor(v).run() : c.unsetColor().run()
          }}
        />
        <Swatches
          icon={<IcMarker />} title="تظليل"
          items={[{ v: '', label: 'بلا' }, ...MARKERS.map((m) => ({ v: m, label: '' }))]}
          current={editor.getAttributes('highlight').color || ''}
          onPick={(v) => {
            const c = editor.chain().focus()
            v ? c.setHighlight({ color: v }).run() : c.unsetHighlight().run()
          }}
        />
      </Grp>

      {/* ── المحاذاة ── */}
      <Grp>
        <TB icon={<IcAlignRight />} title="يمين" on={editor.isActive({ textAlign: 'right' })}
          onClick={() => editor.chain().focus().setTextAlign('right').run()} />
        <TB icon={<IcAlignCenter />} title="وسط" on={editor.isActive({ textAlign: 'center' })}
          onClick={() => editor.chain().focus().setTextAlign('center').run()} />
        <TB icon={<IcAlignLeft />} title="يسار" on={editor.isActive({ textAlign: 'left' })}
          onClick={() => editor.chain().focus().setTextAlign('left').run()} />
        <TB icon={<IcAlignJustify />} title="ضبط" on={editor.isActive({ textAlign: 'justify' })}
          onClick={() => editor.chain().focus().setTextAlign('justify').run()} />
      </Grp>

      {/* ── القوائم ── */}
      <Grp>
        <TB icon={<IcListBullet />} title="قائمة نقطيّة" on={editor.isActive('bulletList')}
          onClick={() => editor.chain().focus().toggleBulletList().run()} />
        <TB icon={<IcListNumber />} title="قائمة مرقّمة" on={editor.isActive('orderedList')}
          onClick={() => editor.chain().focus().toggleOrderedList().run()} />
        <TB icon={<IcQuote />} title="اقتباس" on={editor.isActive('blockquote')}
          onClick={() => editor.chain().focus().toggleBlockquote().run()} />
        <TB icon={<IcRule />} title="خطٌّ فاصل"
          onClick={() => editor.chain().focus().setHorizontalRule().run()} />
        <TB icon={<IcPageBreak />} title="فاصل صفحة"
          onClick={() => editor.chain().focus().insertPageBreak().run()} />
      </Grp>

      {/* ── الصورة ── */}
      <Grp>
        <TB icon={busyImg ? <IcSpinner className="mdd-spin" /> : <IcImageAdd />}
          title={busyImg ? 'جارٍ الرفع…' : 'أدرج صورة (شعار المدرسة مثلًا)'}
          disabled={busyImg} onClick={addImage} />
      </Grp>

      {/* ── الجدول ── */}
      <Grp>
        <TB icon={<IcTableAdd />} title="أدرج جدولًا"
          onClick={() => editor.chain().focus()
            .insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()} />
        {inTable && (
          <>
            {/* لا نُعطّل هذه بـ can(): الزرّ لا يُرسم إلّا داخل جدول أصلًا،
                وProseMirror يتجاهل الأمر غير الصالح بلا ضرر. وتشغيل سلاسل
                can() في كلّ رسمٍ يُكلّف ويُقلّب حالة الشريط بلا داعٍ. */}
            <TB icon={<IcRowAdd />} title="صفٌّ أسفل"
              onClick={() => editor.chain().focus().addRowAfter().run()} />
            <TB icon={<IcColAdd />} title="عمودٌ يمين"
              onClick={() => editor.chain().focus().addColumnBefore().run()} />
            <TB icon={<IcRowDel />} title="احذف الصفّ"
              onClick={() => editor.chain().focus().deleteRow().run()} />
            <TB icon={<IcColDel />} title="احذف العمود"
              onClick={() => editor.chain().focus().deleteColumn().run()} />
            <TB icon={<IcMerge />} title="ادمج الخلايا"
              onClick={() => editor.chain().focus().mergeCells().run()} />
            <TB icon={<IcSplit />} title="افصل الخليّة"
              onClick={() => editor.chain().focus().splitCell().run()} />
            <TB icon={<IcTable />} title="صفّ العنوان" on={false}
              onClick={() => editor.chain().focus().toggleHeaderRow().run()} />
            <Swatches
              icon={<IcCellFill />} title="تعبئة الخليّة"
              items={FILLS.map((f) => ({ v: f.v, label: f.label }))}
              current={readStyleProp(cellStyle(editor), 'background')}
              onPick={(v) => setCellFill(editor, v)}
            />
            <Swatches
              icon={<IcRowFill />} title="تعبئة الصفّ"
              items={FILLS.map((f) => ({ v: f.v, label: f.label }))}
              current=""
              onPick={(v) => setRowFill(editor, v)}
            />
            <TB icon={<IcClear />} title="احذف الجدول" danger
              onClick={() => editor.chain().focus().deleteTable().run()} />
          </>
        )}
      </Grp>

      {/* ── متنوّع ── */}
      <Grp>
        <LinkBtn editor={editor} />
        <TB icon={<IcClear />} title="أزل التنسيق"
          onClick={() => editor.chain().focus().unsetAllMarks().clearNodes().run()} />
      </Grp>

      <span className="mdd-tb-spacer" />

      <button
        type="button" className="mdd-tb-ai"
        onMouseDown={(e) => e.preventDefault()}
        onClick={onImprove} disabled={!canImprove}
        title={canImprove ? 'حسِّن النصّ المحدَّد بالذكاء الاصطناعيّ' : 'حدّد نصًّا أوّلًا'}>
        <IcSpark size={15} />
        <span>حسِّن</span>
      </button>
    </div>
  )
}

/* ═════════════ تعبئة الخلايا ═════════════ */

/** نمط الخليّة التي فيها المؤشّر — لعرض اللون الحاليّ في الأداة */
function cellStyle(editor: Editor): string {
  const a = editor.getAttributes('tableCell')
  const h = editor.getAttributes('tableHeader')
  return (a?.style || h?.style || '') as string
}

/**
 * يضبط خلفيّة كلّ خليّةٍ محدَّدة.
 *
 * `setCellAttribute` من prosemirror-tables يسري على التحديد كلّه — خليّةً
 * كانت أو مدًى منها. فنقرأ نمط كلٍّ ونكتب فوقه، لئلّا نمحو حشوها وحدودها
 * مع تغيير لونها.
 */
function setCellFill(editor: Editor, color: string): void {
  const { state } = editor
  const { from, to } = state.selection
  const edits: { pos: number; style: string }[] = []
  state.doc.nodesBetween(from, to, (node, pos) => {
    if (node.type.name !== 'tableCell' && node.type.name !== 'tableHeader') return
    edits.push({ pos, style: setStyleProp(node.attrs.style, 'background', color) })
  })
  if (!edits.length) return
  const tr = state.tr
  for (const e of edits) {
    const node = tr.doc.nodeAt(e.pos)
    if (node) tr.setNodeMarkup(e.pos, undefined, { ...node.attrs, style: e.style || null })
  }
  editor.view.dispatch(tr)
  editor.commands.focus()
}

/** يضبط خلفيّة كلّ خلايا الصفّ الذي فيه المؤشّر */
function setRowFill(editor: Editor, color: string): void {
  const { state } = editor
  const $from = state.selection.$from
  let rowPos = -1
  for (let d = $from.depth; d > 0; d--) {
    if ($from.node(d).type.name === 'tableRow') { rowPos = $from.before(d); break }
  }
  if (rowPos < 0) return
  const row = state.doc.nodeAt(rowPos)
  if (!row) return
  const tr = state.tr
  let off = rowPos + 1
  row.forEach((cell) => {
    const node = tr.doc.nodeAt(off)
    if (node) {
      tr.setNodeMarkup(off, undefined, {
        ...node.attrs,
        style: setStyleProp(node.attrs.style, 'background', color) || null,
      })
    }
    off += cell.nodeSize
  })
  editor.view.dispatch(tr)
  editor.commands.focus()
}

/* ═════════════ القطع ═════════════ */

function Grp({ children }: { children: React.ReactNode }) {
  return <div className="mdd-tb-g">{children}</div>
}

/**
 * زرّ الشريط.
 *
 * `onMouseDown` يمنع الافتراضيّ: بغيره ينتقل التركيز من الورقة إلى الزرّ
 * عند الضغط، فيضيع موضع المؤشّر — والزرّ الذي لا يُرسم إلّا داخل جدول
 * يختفي من تحت الإصبع بين ضغطةٍ وأخرى. هذا أشهر عطبٍ في أشرطة أدوات
 * المحرّرات، ووقعتُ فيه هنا فعلًا قبل أن أُصلحه.
 */
function TB({ icon, title, onClick, on, disabled, danger }: {
  icon: React.ReactNode; title: string; onClick: () => void
  on?: boolean; disabled?: boolean; danger?: boolean
}) {
  return (
    <button
      type="button"
      className={`mdd-tb-b${on ? ' is-on' : ''}${danger ? ' is-danger' : ''}`}
      onMouseDown={(e) => e.preventDefault()}
      onClick={onClick} disabled={disabled} title={title}
      aria-label={title} aria-pressed={on ? true : undefined}>
      {icon}
    </button>
  )
}

/** قائمةٌ منسدلة — عنصر select أصليّ: يعمل بلوحة المفاتيح ولا يحتاج شيفرة إغلاق */
function Pick({ title, value, options, onPick, width }: {
  title: string; value: string; width?: number
  options: { v: string; label: string }[]
  onPick: (v: string) => void
}) {
  return (
    <span className="mdd-tb-pick" style={{ width }}>
      <select value={value} onChange={(e) => onPick(e.target.value)} title={title} aria-label={title}>
        {options.map((o) => <option key={o.v} value={o.v}>{o.label}</option>)}
      </select>
      <IcChevronDown size={13} />
    </span>
  )
}

/**
 * منتقي لون — بألوانٍ جاهزة **ومنتقٍ حرّ**.
 *
 * الجاهزة تكفي غالبًا وتُبقي المستند متّسقًا. لكنّ القالب قد يأتي بهويّةٍ
 * أخرى، فيحتاج المعلّم لونًا ليس في قائمتنا. فنُضيف منتقي النظام: حرّيّةٌ
 * كاملةٌ لمن أرادها، بلا أن تُثقل الاختيار على من لا يريدها.
 */
function Swatches({ icon, title, items, current, onPick }: {
  icon: React.ReactNode; title: string; current: string
  items: { v: string; label: string }[]
  onPick: (v: string) => void
}) {
  const [open, setOpen] = useState(false)
  const box = useRef<HTMLSpanElement>(null)
  const custom = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (!open) return
    const away = (e: MouseEvent) => { if (!box.current?.contains(e.target as Node)) setOpen(false) }
    const esc = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', away)
    document.addEventListener('keydown', esc)
    return () => { document.removeEventListener('mousedown', away); document.removeEventListener('keydown', esc) }
  }, [open])

  // اللون الحاليّ قد يأتي rgb() من الأنماط المحسوبة — والمنتقي يريد hex
  const asHex = (v: string): string => {
    const m = /^rgba?\((\d+)[,\s]+(\d+)[,\s]+(\d+)/.exec(v || '')
    if (m) return '#' + [1, 2, 3].map((i) => Number(m[i]).toString(16).padStart(2, '0')).join('')
    return /^#[0-9a-f]{6}$/i.test(v || '') ? v : '#5B4BD6'
  }

  return (
    <span className="mdd-tb-sw" ref={box}>
      <button type="button" className={`mdd-tb-b${current ? ' is-on' : ''}`} title={title}
        aria-label={title} aria-expanded={open}
        onMouseDown={(e) => e.preventDefault()}
        onClick={() => setOpen((v) => !v)}>
        {icon}
        {current ? <i className="mdd-tb-sw-dot" style={{ background: current }} /> : null}
      </button>
      {open && (
        <div className="mdd-tb-sw-pop" role="menu">
          <span className="mdd-tb-sw-title">{title}</span>
          <div className="mdd-tb-sw-grid">
            {items.map((i) => (
              <button
                key={i.v || 'none'} type="button" role="menuitem"
                className={`mdd-tb-sw-i${current === i.v ? ' is-on' : ''}`}
                title={i.label || i.v}
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => { onPick(i.v); setOpen(false) }}>
                {i.v
                  ? <i style={{ background: i.v }} />
                  : <span className="mdd-tb-sw-none">بلا</span>}
              </button>
            ))}
          </div>
          <button
            type="button" className="mdd-tb-sw-custom"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => custom.current?.click()}>
            <i style={{ background: current || 'linear-gradient(135deg,#f43f5e,#f59e0b,#22c55e,#3b82f6,#a855f7)' }} />
            <span>لونٌ آخر…</span>
          </button>
          <input
            ref={custom} type="color" className="mdd-tb-sw-input"
            defaultValue={asHex(current)}
            onChange={(e) => { onPick(e.target.value); setOpen(false) }}
          />
        </div>
      )}
    </span>
  )
}

function LinkBtn({ editor }: { editor: Editor }) {
  const on = editor.isActive('link')
  return (
    <TB
      icon={<IcLink />} title={on ? 'أزل الرابط' : 'أضف رابطًا'} on={on}
      onClick={() => {
        if (on) { editor.chain().focus().unsetLink().run(); return }
        const prev = editor.getAttributes('link').href || ''
        const url = window.prompt('عنوان الرابط:', prev)
        if (url === null) return
        const v = url.trim()
        if (!v) { editor.chain().focus().unsetLink().run(); return }
        // نمنع javascript: وما شابهه — إدخالُ مستخدمٍ يصير سمة href
        const safe = /^(https?:|mailto:|tel:)/i.test(v) ? v : `https://${v}`
        editor.chain().focus().extendMarkRange('link').setLink({ href: safe }).run()
      }}
    />
  )
}
