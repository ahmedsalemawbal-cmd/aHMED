import React, { useEffect, useId, useRef, useState } from 'react'
import { IcChevronDown, IcSearch, IcSpinner, IcClose, IcAlert, IcCheck } from './icons'

/* ---------------- زرّ ---------------- */
type BtnVariant = 'primary' | 'secondary' | 'soft' | 'danger' | 'danger-solid' | 'ghost'
export function Button({
  variant = 'secondary', size, block, loading, icon, children, className = '', auto, ...rest
}: React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: BtnVariant; size?: 'sm' | 'lg'; block?: boolean; loading?: boolean; icon?: React.ReactNode; auto?: boolean
}) {
  return (
    <button
      {...rest}
      disabled={rest.disabled || loading}
      className={[
        'mdd-btn', `mdd-btn--${variant}`,
        size ? `mdd-btn--${size}` : '', block ? 'mdd-btn--block' : '', auto ? 'mdd-btn--auto' : '', className,
      ].filter(Boolean).join(' ')}
    >
      {loading ? <IcSpinner size={15} /> : icon}
      {children}
    </button>
  )
}

export function IconButton({ label, children, className = '', ...rest }: React.ButtonHTMLAttributes<HTMLButtonElement> & { label: string }) {
  return (
    <button {...rest} title={label} aria-label={label} className={`mdd-btn mdd-btn--secondary mdd-btn--icon ${className}`}>
      {children}
    </button>
  )
}

/* ---------------- حقول ---------------- */
export function Field({ label, help, error, children, style }: {
  label?: string; help?: string; error?: string; children: React.ReactNode; style?: React.CSSProperties
}) {
  return (
    <label className="mdd-field" style={style}>
      {label && <span className="mdd-field__label">{label}</span>}
      {children}
      {error ? <span className="mdd-field__error">{error}</span> : help ? <span className="mdd-field__help">{help}</span> : null}
    </label>
  )
}

export function Input({ error, ltr, className = '', ...rest }: React.InputHTMLAttributes<HTMLInputElement> & { error?: boolean; ltr?: boolean }) {
  return <input {...rest} className={['mdd-input', error ? 'mdd-input--error' : '', ltr ? 'mdd-input--ltr' : '', className].filter(Boolean).join(' ')} />
}
export const Textarea = React.forwardRef<HTMLTextAreaElement, React.TextareaHTMLAttributes<HTMLTextAreaElement> & { error?: boolean }>(
  function Textarea({ error, className = '', ...rest }, ref) {
    return <textarea ref={ref} {...rest} className={['mdd-textarea', error ? 'mdd-textarea--error' : '', className].filter(Boolean).join(' ')} />
  })
export function Select({ error, children, className = '', ...rest }: React.SelectHTMLAttributes<HTMLSelectElement> & { error?: boolean }) {
  return (
    <span className="mdd-select-wrap">
      <select {...rest} className={['mdd-select', error ? 'mdd-select--error' : '', className].filter(Boolean).join(' ')}>{children}</select>
      <IcChevronDown size={13} />
    </span>
  )
}
export function SearchInput({ value, onChange, placeholder, autoFocus }: {
  value: string; onChange: (v: string) => void; placeholder?: string; autoFocus?: boolean
}) {
  return (
    <span className="mdd-search" style={{ display: 'block' }}>
      <IcSearch size={15} />
      <input className="mdd-input" value={value} autoFocus={autoFocus}
        onChange={(e) => onChange(e.target.value)} placeholder={placeholder} type="search" />
    </span>
  )
}
export function Switch({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <div className="mdd-row" style={{ gap: 10 }}>
      <button type="button" role="switch" aria-checked={checked} aria-label={label}
        className="mdd-switch" data-on={checked} onClick={() => onChange(!checked)}><span /></button>
      <span style={{ fontSize: 13, fontWeight: 500 }}>{label}</span>
    </div>
  )
}
export function Checkbox({ checked, onChange, children }: { checked: boolean; onChange: (v: boolean) => void; children: React.ReactNode }) {
  return (
    <label className="mdd-check">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
      <span>{children}</span>
    </label>
  )
}

/* ---------------- شارات وبطاقات ---------------- */
export function Badge({ tone = 'neutral', children, dot }: {
  tone?: 'neutral' | 'success' | 'warn' | 'danger' | 'info' | 'accent'; children: React.ReactNode; dot?: boolean
}) {
  return (
    <span className={'mdd-badge' + (tone !== 'neutral' ? ` mdd-badge--${tone}` : '')}>
      {dot && <span className="mdd-badge__dot" />}{children}
    </span>
  )
}
export function Card({ children, className = '', ...rest }: React.HTMLAttributes<HTMLDivElement>) {
  return <div {...rest} className={`mdd-card ${className}`}>{children}</div>
}
export function Stat({ label, value, hint }: { label: string; value: React.ReactNode; hint?: string }) {
  return (
    <div className="mdd-card mdd-stat">
      <span className="mdd-stat__label">{label}</span>
      <span className="mdd-stat__value">{value}</span>
      {hint && <span className="mdd-stat__hint">{hint}</span>}
    </div>
  )
}

/* ---------------- تنبيه ---------------- */
export function Alert({ tone = 'neutral', children, icon = true }: {
  tone?: 'neutral' | 'info' | 'warn' | 'danger' | 'success' | 'accent'; children: React.ReactNode; icon?: boolean
}) {
  return (
    <div className={'mdd-alert' + (tone !== 'neutral' ? ` mdd-alert--${tone}` : '')} role={tone === 'danger' ? 'alert' : undefined}>
      {icon && (tone === 'success' ? <IcCheck size={16} /> : <IcAlert size={16} />)}
      <div style={{ minWidth: 0 }}>{children}</div>
    </div>
  )
}

/* ---------------- تبويبات ---------------- */
export function Tabs<T extends string>({ tabs, value, onChange }: {
  tabs: { key: T; label: string; count?: number }[]; value: T; onChange: (v: T) => void
}) {
  return (
    <div className="mdd-tabs" role="tablist">
      {tabs.map((t) => (
        <button key={t.key} role="tab" aria-selected={value === t.key} className="mdd-tab" onClick={() => onChange(t.key)}>
          {t.label}{typeof t.count === 'number' && <span className="mdd-num" style={{ opacity: .7, marginInlineStart: 6 }}>{t.count}</span>}
        </button>
      ))}
    </div>
  )
}
export function Chips<T extends string>({ items, value, onChange }: {
  items: { key: T; label: string; count?: number }[]; value: T; onChange: (v: T) => void
}) {
  return (
    <div className="mdd-chips">
      {items.map((c) => (
        <button key={c.key} className="mdd-chip" aria-pressed={value === c.key} onClick={() => onChange(c.key)}>
          {c.label}{typeof c.count === 'number' && <span className="mdd-chip__count mdd-num">{c.count}</span>}
        </button>
      ))}
    </div>
  )
}

/* ---------------- نافذة ---------------- */
export function Modal({ open, onClose, title, children, footer, wide }: {
  open: boolean; onClose: () => void; title: string; children: React.ReactNode; footer?: React.ReactNode; wide?: boolean
}) {
  const ref = useRef<HTMLDivElement>(null)
  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose() }
    document.addEventListener('keydown', onKey)
    const prev = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    ref.current?.focus()
    return () => { document.removeEventListener('keydown', onKey); document.body.style.overflow = prev }
  }, [open, onClose])
  if (!open) return null
  return (
    <div className="mdd-overlay" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose() }}>
      <div className={'mdd-modal' + (wide ? ' mdd-modal--wide' : '')} role="dialog" aria-modal="true" aria-label={title} tabIndex={-1} ref={ref}>
        <div className="mdd-row mdd-row--between">
          <h2 className="mdd-modal__title">{title}</h2>
          <button className="mdd-btn mdd-btn--secondary mdd-btn--icon" onClick={onClose} aria-label="إغلاق"><IcClose size={15} /></button>
        </div>
        <div className="mdd-col">{children}</div>
        {footer && <div className="mdd-row" style={{ gap: 10, paddingBlockStart: 4 }}>{footer}</div>}
      </div>
    </div>
  )
}

export function ConfirmModal({ open, onClose, onConfirm, title, body, confirmLabel = 'تأكيد', danger, loading }: {
  open: boolean; onClose: () => void; onConfirm: () => void; title: string; body: React.ReactNode
  confirmLabel?: string; danger?: boolean; loading?: boolean
}) {
  return (
    <Modal open={open} onClose={onClose} title={title} footer={
      <>
        <Button variant="secondary" onClick={onClose} block>إلغاء</Button>
        <Button variant={danger ? 'danger-solid' : 'primary'} onClick={onConfirm} loading={loading} block>{confirmLabel}</Button>
      </>
    }>
      <p className="mdd-prose" style={{ fontSize: 13.5 }}>{body}</p>
    </Modal>
  )
}

/* ---------------- الحالات ---------------- */
export function EmptyState({ art, title, line, action, extra }: {
  art?: React.ReactNode; title: string; line?: string; action?: React.ReactNode; extra?: React.ReactNode
}) {
  return (
    <div className="mdd-empty">
      <div className="mdd-empty__art">{art || <DefaultArt />}</div>
      <h3 className="mdd-empty__title">{title}</h3>
      {line && <p className="mdd-empty__line">{line}</p>}
      {action && <div style={{ marginBlockStart: 6 }}>{action}</div>}
      {extra}
    </div>
  )
}
function DefaultArt() {
  return (
    <svg width="82" height="82" viewBox="0 0 82 82" fill="none" aria-hidden="true">
      <rect x="16" y="10" width="50" height="62" rx="6" stroke="currentColor" strokeWidth="2.4"/>
      <path d="M27 27h28M27 38h28M27 49h17" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"/>
    </svg>
  )
}

export function Skeleton({ h = 16, w = '100%', style }: { h?: number; w?: number | string; style?: React.CSSProperties }) {
  return <div className="mdd-skel" style={{ height: h, width: w, ...style }} />
}
export function SkeletonCards({ n = 6 }: { n?: number }) {
  return (
    <div className="mdd-grid mdd-grid--3">
      {Array.from({ length: n }).map((_, i) => (
        <div className="mdd-card mdd-col" key={i}>
          <Skeleton h={110} style={{ borderRadius: 12 }} />
          <Skeleton h={14} w="70%" /><Skeleton h={11} w="90%" />
        </div>
      ))}
    </div>
  )
}
export function SkeletonRows({ n = 5 }: { n?: number }) {
  return (
    <div className="mdd-col" style={{ gap: 10 }}>
      {Array.from({ length: n }).map((_, i) => <Skeleton key={i} h={52} style={{ borderRadius: 12 }} />)}
    </div>
  )
}
export function ErrorState({ onRetry, message }: { onRetry?: () => void; message?: string }) {
  return (
    <EmptyState
      title="حدث خلل"
      line={message || 'تعذّر تحميل البيانات. تحقّق من اتّصالك ثمّ حاول مرّة أخرى.'}
      action={onRetry && <Button variant="primary" onClick={onRetry}>حاول مرّة أخرى</Button>}
    />
  )
}

/* ---------------- التقدّم ---------------- */
export function Progress({ value, max = 100, tone }: { value: number; max?: number; tone?: 'warn' | 'danger' }) {
  const pct = Math.max(0, Math.min(100, (value / (max || 1)) * 100))
  return (
    <div className="mdd-progress" role="progressbar" aria-valuenow={Math.round(pct)} aria-valuemin={0} aria-valuemax={100}>
      <div className={'mdd-progress__bar' + (tone ? ` mdd-progress__bar--${tone}` : '')} style={{ width: `${pct}%` }} />
    </div>
  )
}

/* ---------------- الحرفية ---------------- */
export function Avatar({ name, size = 'md' }: { name: string; size?: 'sm' | 'md' | 'lg' }) {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean)
  const txt = !parts.length ? '؟' : parts.length === 1 ? parts[0].slice(0, 2) : (parts[0][0] || '') + (parts[1][0] || '')
  return <span className={'mdd-avatar' + (size !== 'md' ? ` mdd-avatar--${size}` : '')} aria-hidden="true">{txt}</span>
}

/* ---------------- رأس الصفحة ---------------- */
export function PageHead({ title, sub, actions }: { title: string; sub?: string; actions?: React.ReactNode }) {
  return (
    <div className="mdd-page-head">
      <div>
        <h1 className="mdd-page-head__title">{title}</h1>
        {sub && <p className="mdd-page-head__sub">{sub}</p>}
      </div>
      {actions && <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>{actions}</div>}
    </div>
  )
}

/* ---------------- نسخ ---------------- */
export function CopyButton({ text, label = 'نسخ' }: { text: string; label?: string }) {
  const [done, setDone] = useState(false)
  return (
    <Button size="sm" auto variant="secondary" onClick={async () => {
      try { await navigator.clipboard.writeText(text) } catch {
        const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select()
        try { document.execCommand('copy') } catch {} ; ta.remove()
      }
      setDone(true); setTimeout(() => setDone(false), 1800)
    }}>{done ? 'نُسخ ✓' : label}</Button>
  )
}
