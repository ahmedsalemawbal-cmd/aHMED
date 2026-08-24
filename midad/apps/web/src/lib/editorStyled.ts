/**
 * توسعة مخطَّط المحرّر ليحفظ تصميم الجداول لا بنيتها وحدها.
 *
 * لماذا؟ لأنّ قوالب مِداد تُصمَّم في أداةٍ خارجيّة، وتصميمُها كلّه في
 * **أنماط الخلايا**: الأشرطة الملوّنة خلايا بخلفيّات، والرسوم البيانيّة
 * أعمدةٌ بارتفاعاتٍ وألوان، وترويسة الجدول خلفيّةٌ فاتحة. ومخطَّط TipTap
 * الافتراضيّ يرمي كلّ ذلك ويُبقي النصّ — فيصل المستند إلى المعلّم عاريًا.
 *
 * فنُضيف سمة `style` إلى الجدول والصفّ والخليّة والفقرة، **مُنقّاةً**
 * بقائمة سماح. والتنقية شرطٌ لا تحسين: القالب يفتحه آلاف المعلّمين.
 */
import { Node, mergeAttributes } from '@tiptap/core'
import { Table, TableRow, TableCell, TableHeader } from '@tiptap/extension-table'
import Paragraph from '@tiptap/extension-paragraph'
import { sanitizeStyle } from './styleSafe'

/** سمةُ نمطٍ مُنقّاة تُقرأ من DOM وتُكتب إليه */
const styleAttr = {
  style: {
    default: null as string | null,
    parseHTML: (el: HTMLElement) => sanitizeStyle(el.getAttribute('style')) || null,
    renderHTML: (attrs: Record<string, any>) =>
      (attrs.style ? { style: attrs.style } : {}),
  },
}

export const StyledTable = Table.extend({
  addAttributes() {
    return { ...this.parent?.(), ...styleAttr }
  },
})

export const StyledTableRow = TableRow.extend({
  addAttributes() {
    return { ...this.parent?.(), ...styleAttr }
  },
})

export const StyledTableCell = TableCell.extend({
  addAttributes() {
    return { ...this.parent?.(), ...styleAttr }
  },
})

export const StyledTableHeader = TableHeader.extend({
  addAttributes() {
    return { ...this.parent?.(), ...styleAttr }
  },
})

/**
 * الفقرة تحفظ نمطها أيضًا.
 *
 * أدوات التصميم تكتب عناوين الأقسام `div` بنمطٍ سطريّ (حجمٌ ولونٌ ووزن)
 * لا `h2`. فلو أسقطنا النمط لصارت كلّ العناوين نصًّا عاديًّا.
 *
 * ولا يتعارض هذا مع `TextAlign`: هو يكتب `textAlign` سمةً مستقلّة، ونحن
 * نكتب `style`. والاثنان يُدمجان في `renderHTML` — فلو تعارضا فازت
 * المحاذاة الصريحة، وهي ما اختاره المستخدم بيده.
 */
export const StyledParagraph = Paragraph.extend({
  addAttributes() {
    return { ...this.parent?.(), ...styleAttr }
  },
})

/**
 * فاصل صفحة.
 *
 * التصميم المستورد يأتي في صفحاتٍ مرقّمة (`section.page`). نُمثّلها بعقدةٍ
 * فارغة بين الأقسام: تُرى في المحرّر خطًّا متقطّعًا، وتُترجَم عند الطباعة
 * إلى `break-after: page` فتُطبع كلّ صفحةٍ على ورقتها.
 */
export const PageBreak = Node.create({
  name: 'pageBreak',
  group: 'block',
  atom: true,
  selectable: true,
  parseHTML() {
    return [
      { tag: 'div[data-page-break]' },
      { tag: 'hr[data-page-break]' },
    ]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, {
      'data-page-break': 'true',
      class: 'mdd-pagebreak',
      contenteditable: 'false',
    })]
  },
  addCommands() {
    return {
      insertPageBreak: () => ({ chain }: any) =>
        chain().insertContent({ type: this.name }).run(),
    } as any
  },
})

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    midadPage: {
      insertPageBreak: () => ReturnType
    }
  }
}
