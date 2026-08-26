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
import Image from '@tiptap/extension-image'
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
 * الصورة تحفظ نمطها أيضًا.
 *
 * وامتداد الصورة الافتراضيّ يحفظ `src` و`alt` و`title` لا غير. والتصميم
 * يكتب مقاسها في سمتها:
 *     <img style="display:block; width:64px; height:64px">
 * فتُسقَط، وتُرسم الصورة بحجمها الطبيعيّ. ورفع المالك عرضًا فيه شعاران
 * صغيران فخرجا يملآن نصف الصفحة — وطالت الورقة من ١١٢٣ إلى ١٤٥٦.
 *
 * وهو صنف العطب نفسه الذي أصاب الجداول والفقرات: ما لا يعرفه المخطّط
 * يُسقَط صامتًا، ولا شيء يُنبّه.
 */
export const StyledImage = Image.extend({
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
/**
 * صندوق الصفحة — يحمل حشوَ صفحته وخطَّها ولونها.
 *
 * ولمَ عقدةٌ في المخطّط لا مجرّد `<div>`؟ لأنّ كلّ صفحةٍ في تصاميم كلود
 * ديزاين تُعلن حشوها بنفسها، ويختلف بين صفحةٍ وأخرى: الغلاف بلا حشوٍ
 * إطلاقًا، وصفحات المتن `34px 44px 30px`. فلو رفعنا حشوًا واحدًا إلى
 * مستوى الورقة — وهو ما كنّا نفعل — لألبسنا الغلافَ حشوًا لم يُصمَّم به،
 * فيتزحزح تصميمه ويبدو «متخلبطًا» وكلُّ عنصرٍ في موضعه.
 *
 * ولا يكفي `<div>` عاديّ: `divsToParagraphs` يحوّله فقرةً، وProseMirror
 * يُعيد بناء المستند من مخطّطه فيُسقط ما ليس عقدةً فيه. فالحشو يضيع عند
 * أوّل تحرير — وهذا أسوأ من ألّا يُحفظ أصلًا، إذ يبدو صحيحًا ثمّ ينهار.
 */
/**
 * كتلةٌ منسَّقة — غلافُ تخطيطٍ يحمل حشوَه وهوامشه.
 *
 * تصاميم كلود ديزاين تبني تخطيطها بأغلفة `<div>` متداخلة: غلافٌ بحشو
 * `48px 52px`، وداخله مباعِدٌ بهامش `118px`، وداخله النصّ. ولا شيء من
 * ذلك في سمة العنصر النصّيّ نفسه.
 *
 * وكان المستورد يسحق هذه الأغلفة فقراتٍ — لأنّ مخطّط المحرّر لم يكن فيه
 * ما يقبل `<div>`، وما لا يقبله المخطّط يُسقَط بنصّه. فالسحق كان يُنقذ
 * النصّ ويُضيّع التخطيط: الغلاف يذهب ومعه ٥٢px من الحشو و١١٨px من
 * الهامش، فيلتصق المتن بحافّة الورقة ويعلو عن موضعه.
 *
 * فصارت الكتلة عقدةً في المخطّط: تُقبل، وتحفظ نمطها، وتنجو من التحرير.
 * وأولويّة تحليلها دنيا كي يفوز عليها `div[data-page]` و`[data-page-break]`
 * — فهما أخصّ منها وقد يُطابقان `div` أيضًا.
 */
export const StyledBlock = Node.create({
  name: 'styledBlock',
  group: 'block',
  content: 'block+',
  defining: true,
  addAttributes() {
    return { ...styleAttr }
  },
  parseHTML() {
    return [{ tag: 'div', priority: 10 }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes), 0]
  },
})

export const PageBox = Node.create({
  name: 'pageBox',
  group: 'block',
  content: 'block+',
  defining: true,
  addAttributes() {
    return { ...styleAttr }
  },
  parseHTML() {
    return [{ tag: 'div[data-page]' }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, {
      'data-page': 'true',
      class: 'mdd-pagebox',
    }), 0]
  },
})

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
