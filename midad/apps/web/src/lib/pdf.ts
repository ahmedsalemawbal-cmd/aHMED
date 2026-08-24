/**
 * تنزيل المستند ملفَّ PDF مباشرةً — بلا نافذة طباعة.
 *
 * لماذا لا نكتفي بالطباعة؟ لأنّ «اطبع» و«نزّل» فعلان مختلفان: الأوّل يفتح
 * حوارَ النظام ويطلب من المستخدم أن يختار «حفظ كـPDF» بنفسه، والثاني يجب
 * أن يُنزّل الملفّ في نقرةٍ واحدة. طلبَ المالك الاثنين، وهذا هو الثاني.
 *
 * كيف؟ نُصوّر كلّ صفحةٍ من الورقة ونضعها صفحةً في PDF بمقاس A4.
 *
 * ولماذا صورةٌ لا نصٌّ مُتّجه؟ لأنّ توليد PDF نصّيٍّ بالعربيّة في المتصفّح
 * يقتضي تضمين خطٍّ عربيٍّ في المولّد وتشكيلَ الحروف ووصلَها وضبطَ اتّجاه
 * الفقرة — وكلّ خطوةٍ منها تكسر شيئًا. أمّا التصوير فيلتقط ما رسمه المتصفّح
 * نفسه: الحروف موصولةٌ صحيحةً، والاتّجاه سليم، والألوان كما هي. الثمن أنّ
 * النصّ غير قابلٍ للتحديد داخل الـPDF، وهو ثمنٌ مقبولٌ في وثيقةٍ تُطبع.
 * ومن أراد نصًّا يُحرَّر فالوورد موجود.
 */

import { download } from './export'

const A4 = { w: 210, h: 297 }   // مليمتر

export interface PdfOptions {
  /** دقّة التصوير — ٢ تكفي للطباعة، و٣ للورق الفاخر */
  scale?: number
  landscape?: boolean
  onProgress?: (done: number, total: number) => void
}

/**
 * يقسم متن المستند عند فواصل الصفحات إلى حاوياتٍ مستقلّة.
 *
 * نبني نسخةً خارج الشاشة بدل تصوير المحرّر مباشرةً: المحرّر فيه مؤشّرٌ
 * وحدودُ خلايا للتحرير وتحديدٌ قد يظهر في الصورة. والنسخة تُصوَّر نظيفة.
 */
function buildPages(html: string, widthPx: number, padding: string): HTMLElement[] {
  const holder = document.createElement('div')
  holder.innerHTML = html

  const groups: Node[][] = [[]]
  Array.from(holder.childNodes).forEach((n) => {
    const el = n as Element
    if (el.nodeType === 1 && el.hasAttribute?.('data-page-break')) {
      groups.push([])
      return
    }
    groups[groups.length - 1].push(n)
  })

  return groups
    .filter((g) => g.some((n) => (n.textContent || '').trim() || (n as Element).querySelector?.('table')))
    .map((g) => {
      const page = document.createElement('div')
      page.className = 'mdd-pdf-page mdd-doc-body'
      page.setAttribute('dir', 'rtl')
      page.style.cssText = [
        `width:${widthPx}px`,
        `padding:${padding}`,
        'background:#ffffff',
        'color:#14131f',
        'box-sizing:border-box',
        'position:absolute',
        'inset-block-start:0',
        'inset-inline-start:-100000px',   // خارج الشاشة، لا display:none — المخفيّ لا يُصوَّر
        'z-index:-1',
      ].join(';')
      g.forEach((n) => page.appendChild(n.cloneNode(true)))
      return page
    })
}

export async function downloadPdf(
  html: string,
  fileName: string,
  page: { orientation?: 'portrait' | 'landscape'; margins?: { top: number; right: number; bottom: number; left: number } } | null,
  opts: PdfOptions = {},
): Promise<void> {
  /* html2canvas-pro لا html2canvas: الأصل توقّف عند ٢٠٢٢ فلا يعرف
     `oklch()` ولا `color-mix()`، ويرمي عند أوّل لونٍ منهما — وألوان مِداد
     كلّها oklch. والنسخة الحيّة تفهمها. */
  const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
    import('html2canvas-pro'),
    import('jspdf'),
  ])

  const landscape = opts.landscape ?? page?.orientation === 'landscape'
  const mmW = landscape ? A4.h : A4.w
  const mmH = landscape ? A4.w : A4.h
  const scale = opts.scale ?? 2

  // ٩٦ نقطةً في البوصة هي وحدة CSS، فالعرض بالبكسل يقابل المليمتر هكذا
  const widthPx = Math.round(mmW / (25.4 / 96))
  const m = page?.margins ?? { top: 16, right: 14, bottom: 16, left: 14 }
  const padding = `${m.top}mm ${m.right}mm ${m.bottom}mm ${m.left}mm`

  const pages = buildPages(html, widthPx, padding)
  if (!pages.length) throw new Error('لا محتوى في المستند')

  const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: landscape ? 'landscape' : 'portrait' })

  for (let i = 0; i < pages.length; i++) {
    const el = pages[i]
    document.body.appendChild(el)
    try {
      const canvas = await html2canvas(el, {
        scale,
        backgroundColor: '#ffffff',
        useCORS: true,
        logging: false,
        // الصورة تُلتقط من نسخةٍ خارج الشاشة، فنُلغي أثر تمرير الصفحة
        scrollX: 0,
        scrollY: 0,
        windowWidth: widthPx,
      })
      const img = canvas.toDataURL('image/jpeg', 0.92)

      /* الصفحة قد تطول عن ورقةٍ واحدة (المحتوى بعد التحرير يزيد). فنقصّها
         على ارتفاع الورقة بدل أن نضغطها فتصغر الحروف. */
      const pxPerMm = canvas.width / mmW
      const sliceH = Math.floor(mmH * pxPerMm)
      const slices = Math.max(1, Math.ceil(canvas.height / sliceH))

      for (let s = 0; s < slices; s++) {
        if (i > 0 || s > 0) doc.addPage()
        if (slices === 1) {
          const h = Math.min(mmH, canvas.height / pxPerMm)
          doc.addImage(img, 'JPEG', 0, 0, mmW, h, undefined, 'FAST')
        } else {
          const cut = document.createElement('canvas')
          cut.width = canvas.width
          cut.height = Math.min(sliceH, canvas.height - s * sliceH)
          const ctx = cut.getContext('2d')!
          ctx.fillStyle = '#ffffff'
          ctx.fillRect(0, 0, cut.width, cut.height)
          ctx.drawImage(canvas, 0, -s * sliceH)
          doc.addImage(cut.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0,
            mmW, cut.height / pxPerMm, undefined, 'FAST')
        }
      }
      opts.onProgress?.(i + 1, pages.length)
    } finally {
      el.remove()
    }
  }

  /* لا `doc.save()`: مُنزِّل jsPDF الداخليّ يُسقط الاسم العربيّ أحيانًا
     فينزل الملفّ باسم «download» بلا امتداد. ننزّله بمُنزِّلنا الذي يُنزّل
     الوورد — رابطٌ بسمة `download`، والاسم يصل كما هو. */
  const out = fileName.endsWith('.pdf') ? fileName : `${fileName}.pdf`
  download(doc.output('blob'), out)
}
