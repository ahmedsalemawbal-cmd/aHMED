import React, { useId, useState } from 'react'
import { IcChevronDown } from '../../ui/icons'

/** سطرٌ يُفتح فيظهر جوابه — يستعمله الأسئلة الشائعة وأسئلة الشراء في الأسعار. */
export default function Accordion({ q, children, defaultOpen }: {
  q: string; children: React.ReactNode; defaultOpen?: boolean
}) {
  const [open, setOpen] = useState(!!defaultOpen)
  const id = useId()

  return (
    <div
      style={{
        border: '1px solid var(--mdd-border)',
        borderRadius: 'var(--mdd-r-md)',
        background: 'var(--mdd-card)',
        overflow: 'hidden',
      }}
    >
      <button
        type="button"
        aria-expanded={open}
        aria-controls={id}
        onClick={() => setOpen((v) => !v)}
        className="mdd-row mdd-row--between"
        style={{
          width: '100%',
          gap: 'var(--mdd-s-3)',
          padding: '15px var(--mdd-s-4)',
          background: 'transparent',
          border: 'none',
          cursor: 'pointer',
          textAlign: 'start',
          font: 'inherit',
          color: 'var(--mdd-text)',
          fontWeight: 600,
          fontSize: 14,
        }}
      >
        <span style={{ minWidth: 0 }}>{q}</span>
        <span
          style={{
            display: 'flex',
            flex: 'none',
            color: 'var(--mdd-text-3)',
            transform: open ? 'rotate(180deg)' : 'none',
            transition: 'transform var(--mdd-dur) var(--mdd-ease)',
          }}
        >
          <IcChevronDown size={15} />
        </span>
      </button>

      {open && (
        <div
          id={id}
          className="mdd-prose"
          style={{
            fontSize: 13.5,
            padding: '0 var(--mdd-s-4) var(--mdd-s-4)',
            borderBlockStart: '1px solid var(--mdd-border)',
            paddingBlockStart: 'var(--mdd-s-4)',
          }}
        >
          {children}
        </div>
      )}
    </div>
  )
}
