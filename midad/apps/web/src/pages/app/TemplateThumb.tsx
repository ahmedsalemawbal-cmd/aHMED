import React from 'react'
import type { Template } from '../../lib/types'

/** مصغّرة تُظهر شكل الورقة فعلًا — لا أيقونة عامّة. */
export default function TemplateThumb({ template, height = 132 }: { template: Template; height?: number }) {
  const fields = template.fields || []
  const hasTable = fields.some((f) => f.type === 'table')
  const lines = Math.min(6, Math.max(3, Math.ceil(fields.length / 2)))
  return (
    <div style={{
      height, borderRadius: 'var(--mdd-r-md)', border: '1px solid var(--mdd-border)',
      background: 'var(--mdd-sunken)', display: 'grid', placeItems: 'center', overflow: 'hidden', flex: 'none',
    }} aria-hidden="true">
      <svg width="104" height={height - 18} viewBox="0 0 104 118" style={{ display: 'block' }}>
        <rect x="0.5" y="0.5" width="103" height="117" rx="4" fill="#fff" stroke="var(--mdd-border-strong)" />
        <rect x="9" y="9" width="34" height="4.5" rx="2" fill="var(--mdd-accent)" opacity=".85" />
        <rect x="9" y="17" width="58" height="3" rx="1.5" fill="var(--mdd-border-strong)" opacity=".6" />
        <rect x="26" y="27" width="52" height="5" rx="2" fill="var(--mdd-text-3)" opacity=".55" />
        {Array.from({ length: lines }).map((_, i) => (
          <g key={i}>
            <rect x="9" y={40 + i * 8} width={i % 3 === 0 ? 86 : 68} height="3" rx="1.5" fill="var(--mdd-border-strong)" opacity=".5" />
          </g>
        ))}
        {hasTable && (
          <g>
            <rect x="9" y={44 + lines * 8} width="86" height="9" fill="var(--mdd-accent)" opacity=".18" />
            {[0, 1, 2].map((r) => (
              <rect key={r} x="9" y={53 + lines * 8 + r * 8} width="86" height="7.4" fill="none" stroke="var(--mdd-border-strong)" strokeWidth=".7" opacity=".55" />
            ))}
            {[31, 53, 75].map((x) => (
              <line key={x} x1={x} y1={44 + lines * 8} x2={x} y2={53 + lines * 8 + 24} stroke="var(--mdd-border-strong)" strokeWidth=".7" opacity=".45" />
            ))}
          </g>
        )}
        <rect x="9" y="104" width="26" height="3" rx="1.5" fill="var(--mdd-border-strong)" opacity=".45" />
        <rect x="69" y="104" width="26" height="3" rx="1.5" fill="var(--mdd-border-strong)" opacity=".45" />
      </svg>
    </div>
  )
}
