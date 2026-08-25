/**
 * إعداد محرّك التحرير — TipTap ٣ فوق ProseMirror.
 *
 * لماذا محرّكٌ ناضج لا `contenteditable` عاريًا؟ لأنّ `document.execCommand`
 * مهجورٌ ومعطوبٌ تحديدًا في ما يهمّ المعلّم: إضافة صفٍّ إلى جدول، وإدخال
 * قائمةٍ داخل خليّة. ProseMirror يحمل مخطَّطًا يتحقّق من كلّ تغيير، فلا يمكن
 * أصلًا أن يُنتَج مستندٌ فاسد. هذا هو الجواب على «حاول ما يكون في أخطاء».
 *
 * كلّ الأسماء المستعملة هنا مقروءةٌ من ملفّات `.d.ts` المثبَّتة في
 * node_modules، لا من ذاكرةٍ عن الإصدار الثاني — فالثالث غيّر أشياء.
 */
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import {
  StyledTable, StyledTableRow, StyledTableCell, StyledTableHeader,
  StyledParagraph, PageBreak, PageBox,
} from './editorStyled'
import { TextAlign } from '@tiptap/extension-text-align'
import { Highlight } from '@tiptap/extension-highlight'
import { TextStyleKit } from '@tiptap/extension-text-style'
import Placeholder from '@tiptap/extension-placeholder'
import { Extension } from '@tiptap/core'
import { DOMSerializer } from '@tiptap/pm/model'
import type { Editor } from '@tiptap/react'

/**
 * Tab داخل القائمة يُزيح العنصر مستوًى، وShift+Tab يُرجعه — كما في الوورد.
 *
 * StarterKit لا يربط Tab بهذا (يربط listKeymap مفاتيح الحذف وحدها)، فكان
 * Tab في القائمة لا يفعل شيئًا. ونُعيد false إن لم نكن في قائمة كي يبقى
 * Tab في الجدول للانتقال بين الخلايا — ولا نسرق المفتاح من غيرنا.
 */
const ListTab = Extension.create({
  name: 'midadListTab',
  addKeyboardShortcuts() {
    return {
      Tab: () => {
        if (!this.editor.isActive('listItem')) return false
        return this.editor.commands.sinkListItem('listItem')
      },
      'Shift-Tab': () => {
        if (!this.editor.isActive('listItem')) return false
        return this.editor.commands.liftListItem('listItem')
      },
    }
  },
})

/** الخطوط المتاحة — عربيّةٌ أوّلًا، وكلّها موجودةٌ على أجهزة المستخدمين */
export const FONTS = [
  { key: '', label: 'الافتراضيّ' },
  { key: '"Noto Naskh Arabic", "Traditional Arabic", serif', label: 'نسخ' },
  { key: '"Cairo", "Segoe UI", sans-serif', label: 'القاهرة' },
  { key: '"Amiri", "Times New Roman", serif', label: 'أميري' },
  { key: 'Arial, Helvetica, sans-serif', label: 'Arial' },
] as const

export const SIZES = ['11px', '12px', '13px', '14px', '16px', '18px', '20px', '24px', '28px', '32px'] as const

/** ألوان النصّ — قليلةٌ ومقصودة، لا منتقي ألوانٍ لا نهائيّ يُربك */
export const INK = [
  { v: '', label: 'الافتراضيّ' },
  { v: '#191733', label: 'أسود' },
  { v: '#5B4BD6', label: 'بنفسجيّ' },
  { v: '#0E9F6E', label: 'أخضر' },
  { v: '#D64545', label: 'أحمر' },
  { v: '#2E7BD6', label: 'أزرق' },
  { v: '#B4791B', label: 'كهرمانيّ' },
  { v: '#5B5878', label: 'رماديّ' },
] as const

export const MARKERS = ['#FFF3A3', '#C9F7E5', '#FBD5D5', '#DCE7FB', '#EDE7FE'] as const

/**
 * الامتدادات.
 *
 * ملحوظتان تمنعان عطبًا:
 * ١) StarterKit في الإصدار ٣ يضمّ underline وlink وlistKeymap وundoRedo أصلًا،
 *    فإضافتها منفصلةً تُنتج تكرارَ اسمٍ وتحذيرًا. لذا لا تُثبَّت منفصلة.
 * ٢) TableKit يجمع Table وTableRow وTableCell وTableHeader في امتدادٍ واحد،
 *    فلا يُضاف أيٌّ منها منفصلًا.
 */
export function extensions(placeholder = 'ابدأ الكتابة…') {
  return [
    StarterKit.configure({
      heading: { levels: [1, 2, 3] },
      // شيفرة البرمجة لا محلّ لها في وثيقةٍ مدرسيّة
      codeBlock: false,
      code: false,
      // الفقرة تُستبدَل بنسخةٍ تحفظ نمطها السطريّ
      paragraph: false,
      link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener' } },
    }),
    StyledParagraph,
    /* الجدول بعُقَدٍ موسَّعة تحفظ الأنماط — لا TableKit الافتراضيّ.
       فتصميم القوالب كلّه في أنماط الخلايا، وإسقاطها يُفرغ المستند. */
    StyledTable.configure({
      resizable: true, allowTableNodeSelection: true,
      HTMLAttributes: { class: 'mdd-doc-table' },
    }),
    StyledTableRow,
    StyledTableHeader.configure({ HTMLAttributes: { class: 'mdd-doc-th' } }),
    StyledTableCell.configure({ HTMLAttributes: { class: 'mdd-doc-td' } }),
    PageBreak,
    PageBox,
    /* الصور: شعار المدرسة أوّلًا، وما يُدرجه المعلّم بعده.
       `inline: false` فالصورة كتلةٌ تقف بنفسها لا حرفٌ في سطر — وهو
       ما يُتوقَّع في وثيقةٍ مدرسيّة. و`allowBase64` لأنّ المستورَد من
       كلود ديزاين قد يحمل صورةً مضمَّنةً في متنه، فلا نُسقطها. */
    Image.configure({
      inline: false,
      allowBase64: true,
      HTMLAttributes: { class: 'mdd-doc-img' },
    }),
    // العربيّة تبدأ يمينًا، فالافتراضيّ right لا left
    TextAlign.configure({ types: ['heading', 'paragraph'], defaultAlignment: 'right' }),
    Highlight.configure({ multicolor: true }),
    TextStyleKit.configure({ lineHeight: false }),
    Placeholder.configure({ placeholder }),
    ListTab,
  ]
}

/* ═════════════════════ قراءة حالة التحديد ═════════════════════ */

export interface Selected {
  empty: boolean
  text: string
  html: string
  inTable: boolean
}

/** يقرأ التحديد نصًّا وHTML — يحتاجه التحسين بالذكاء الاصطناعيّ */
export function readSelection(editor: Editor): Selected {
  if (editor.isDestroyed) return { empty: true, text: '', html: '', inTable: false }
  const { state } = editor
  const { from, to, empty } = state.selection
  const text = empty ? '' : state.doc.textBetween(from, to, '\n', ' ')
  let html = ''
  if (!empty) {
    const slice = state.doc.slice(from, to)
    const div = document.createElement('div')
    // مُسلسِل ProseMirror نفسه — لا قصًّا نصّيًّا من innerHTML
    div.appendChild(DOMSerializer.fromSchema(state.schema).serializeFragment(slice.content))
    html = div.innerHTML
  }
  return { empty, text, html, inTable: editor.isActive('table') }
}

/* ═════════════════════ إعداد الصفحة ═════════════════════ */

export interface PageSetup {
  size: 'A4'
  orientation: 'portrait' | 'landscape'
  margins: { top: number; right: number; bottom: number; left: number }
}

export const DEFAULT_PAGE: PageSetup = {
  size: 'A4',
  orientation: 'portrait',
  margins: { top: 18, right: 16, bottom: 18, left: 16 },
}

export function pageStyle(p: PageSetup): React.CSSProperties {
  const portrait = p.orientation !== 'landscape'
  return {
    width: portrait ? '210mm' : '297mm',
    minHeight: portrait ? '297mm' : '210mm',
    paddingBlock: `${p.margins.top}mm ${p.margins.bottom}mm`,
    paddingInline: `${p.margins.right}mm ${p.margins.left}mm`,
    /* الهامشان الجانبيّان متغيّرين كي يبلغ فاصلُ الصفحة حافّتَي الورقة:
       المتن محشوٌّ بهما، فما فيه لا يصل الحافّة إلّا بهامشٍ سالبٍ يساويهما.
       ولا يُكتبان في الـCSS رقمًا ثابتًا — الهوامش تُضبط لكلّ مستند. */
    ['--mdd-pad-s' as any]: `${p.margins.right}mm`,
    ['--mdd-pad-e' as any]: `${p.margins.left}mm`,
    /* ارتفاع الورقة: يقرأه صندوقُ الصفحة ليملأها كاملةً، فيبدو المستند
       صفحاتِ ورقٍ لا متنًا متّصلًا مضغوطًا. */
    ['--mdd-page-h' as any]: portrait ? '297mm' : '210mm',
  }
}

export function normalizePage(v: any): PageSetup {
  const m = v?.margins || {}
  const num = (x: any, d: number) => {
    const n = Number(x)
    return Number.isFinite(n) && n >= 0 && n <= 60 ? n : d
  }
  return {
    size: 'A4',
    orientation: v?.orientation === 'landscape' ? 'landscape' : 'portrait',
    margins: {
      top: num(m.top, 18), right: num(m.right, 16),
      bottom: num(m.bottom, 18), left: num(m.left, 16),
    },
  }
}
