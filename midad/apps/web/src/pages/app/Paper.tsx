import React, { useEffect, useRef, useState } from 'react'
import type { Template } from '../../lib/types'
import { renderBody } from '../../lib/template'
import { fmtBoth } from '../../lib/format'

export interface PaperProps {
  template: Pick<Template, 'body' | 'fields'>
  data: Record<string, any>
  title: string
  schoolName?: string | null
  educationDept?: string | null
  academicYear?: string | null
  semester?: string | null
  logoUrl?: string | null
  watermark?: string | null
  zoom?: number
  highlightKey?: string | null
}

export default function Paper({
  template, data, title, schoolName, educationDept, academicYear, semester,
  logoUrl, watermark, zoom = 1, highlightKey,
}: PaperProps) {
  const html = renderBody(template, data)
  const paperRef = useRef<HTMLDivElement>(null)
  const [natural, setNatural] = useState(0)

  useEffect(() => {
    const el = paperRef.current
    if (!el) return
    const measure = () => setNatural(el.offsetHeight)
    measure()
    const ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(measure) : null
    ro?.observe(el)
    return () => ro?.disconnect()
  }, [html])

  return (
    <div className="mdd-paper-scale" style={{ height: natural ? natural * zoom : undefined, overflow: 'hidden' }}>
      <div className="mdd-paper" ref={paperRef} style={{ transform: `scale(${zoom})` }}
        data-highlight={highlightKey || undefined}>
        {watermark && <div className="mdd-watermark"><span>{watermark}</span></div>}

        <div className="mdd-paper__head">
          <div className="mdd-paper__head-col">
            <strong>المملكة العربية السعودية</strong>
            <span>وزارة التعليم</span>
            {educationDept && <span>{educationDept}</span>}
          </div>
          {logoUrl
            ? <img className="mdd-paper__logo" src={logoUrl} alt="" />
            : <div className="mdd-paper__logo-ph">شعار المدرسة</div>}
          <div className="mdd-paper__head-col">
            <strong>{schoolName || 'اسم المدرسة'}</strong>
            {academicYear && <span>العام الدراسي {academicYear}</span>}
            {semester && <span>{semester}</span>}
          </div>
        </div>

        <div className="mdd-paper__title">{title}</div>
        <div className="mdd-paper__body" dangerouslySetInnerHTML={{ __html: html }} />

        <div className="mdd-paper__foot">
          <span>{fmtBoth(new Date())}</span>
          <span>مِداد</span>
        </div>
      </div>
    </div>
  )
}
