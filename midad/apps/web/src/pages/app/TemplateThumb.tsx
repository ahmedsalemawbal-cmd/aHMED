import React, { useEffect, useMemo, useRef, useState } from 'react'
import type { Template } from '../../lib/types'
import { splitPages } from '../../lib/pages'

/** عرض ورقة A4 بالبكسل عند ٩٦ نقطةً في البوصة. */
const SHEET = 794

/**
 * مصغّرة القالب — **من متنه الحقيقيّ** لا من رسمٍ وهميّ.
 *
 * كانت هذه تُخرِج شكلًا عامًّا: مستطيلاتٌ رماديّة تُحاكي أسطرًا وجدولًا،
 * وتُبنى من عدد الحقول. فكان المعلّم يرى الرسم نفسه في كلّ قالب، ولا يعرف
 * ما سيفتح إلّا بعد أن يفتحه. والقوالب الآن مستنداتٌ مصمَّمة، فالوهم لم
 * يعد يُغني عن الحقيقة.
 *
 * نرسم أوّل صفحةٍ من المتن مصغَّرةً بـ`transform: scale`. والمعاينة
 * `aria-hidden` وبلا مؤشّر: صورةٌ تُرى لا صفحةٌ تُستعمل.
 */
export default function TemplateThumb({ template, height = 132 }: {
  template: Template
  height?: number
}) {
  const html = useMemo(() => firstPage(template.content_html || ''), [template.content_html])
  const box = useRef<HTMLDivElement>(null)
  const [scale, setScale] = useState(0)

  /* المعامل يُقاس ولا يُخمَّن: البطاقة تتغيّر بتغيّر الشبكة وعرض الشاشة،
     فمعاملٌ ثابت يترك فراغًا في الواسعة ويقصّ في الضيّقة. نقيس ونقسم. */
  useEffect(() => {
    const el = box.current
    if (!el) return
    const fit = () => setScale(el.clientWidth / SHEET)
    fit()
    const ro = new ResizeObserver(fit)
    ro.observe(el)
    return () => ro.disconnect()
  }, [html])

  if (!html) return <Placeholder height={height} />

  const m = template.page?.margins
  return (
    <div ref={box} className="mdd-thumb" style={{ height }} aria-hidden="true" title={template.title}>
      {/* لا نرسم قبل القياس: الرسم بمعاملٍ خاطئٍ ثمّ تصحيحه ارتجافةٌ تُرى */}
      {scale > 0 && (
        <div
          className="mdd-thumb-page"
          style={{
            width: SHEET,
            transform: `scale(${scale})`,
            padding: `${m?.top ?? 14}mm ${m?.right ?? 14}mm`,
          }}
          dangerouslySetInnerHTML={{ __html: html }}
        />
      )}
    </div>
  )
}

function Placeholder({ height }: { height: number }) {
  return (
    <div className="mdd-thumb mdd-thumb--empty" style={{ height }} aria-hidden="true">
      <svg width="52" height="60" viewBox="0 0 52 60" fill="none">
        <rect x="1" y="1" width="50" height="58" rx="4"
          stroke="var(--mdd-border-strong)" strokeDasharray="4 3" />
        <path d="M14 22h24M14 30h24M14 38h15" stroke="var(--mdd-border-strong)"
          strokeWidth="2" strokeLinecap="round" opacity=".6" />
      </svg>
    </div>
  )
}

/**
 * أوّل صفحةٍ من المتن.
 *
 * ويُشطر بـ`splitPages` — وهو نفسه الذي تستعمله المعاينة وتوليد الـPDF.
 * فالثلاثة ترى الصفحة نفسها، ولو تفرّقت لاختلفت.
 *
 * وكان يُشطر بقصّ النصّ:
 *     const i = html.indexOf('data-page-break')
 *     if (out.length > 14000) out = out.slice(0, 14000)
 *
 * والقصّ الأعمى يقع في منتصف وسمٍ أو في منتصف صورةٍ مضمَّنة، فيخرج ترميزٌ
 * مكسورٌ لا يُرسم. ونجا منه قالبٌ أوّلُ صفحةٍ فيه ستّة آلاف حرف؛ وسقط
 * قالبٌ فيه صورٌ مضمَّنة — فخرجت بطاقته **بيضاء**، والمتن سليمٌ كلّه.
 *
 * ولا حدَّ للطول بعدها: الصورة المضمَّنة هي المصغّرة نفسها، وقصّها قصفٌ
 * لما جئنا نعرضه.
 */
function firstPage(html: string): string {
  if (!html.trim()) return ''
  try {
    return splitPages(html)[0] || ''
  } catch {
    return ''
  }
}
