import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'

/* ══ اللبنات ══
   مكوّناتٌ صغيرةٌ بلا مكتبةٍ خارجيّة، على نمط `midad/apps/web/src/ui/kit.tsx`.
   وكلُّ لونٍ فيها من الرموز — لا لونَ مكتوبًا في هذا الملفّ. */

export function Container({ children, wide }: { children: ReactNode; wide?: boolean }) {
  return <div className={'osl-container' + (wide ? ' osl-container--wide' : '')}>{children}</div>
}

type BtnProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'soft' | 'ghost' | 'danger'
  size?: 'sm' | 'lg'
  block?: boolean
  loading?: boolean
  icon?: ReactNode
}

export function Button({
  variant = 'primary', size, block, loading, icon, children, className = '', disabled, ...rest
}: BtnProps) {
  const cls = [
    'osl-btn', `osl-btn--${variant}`,
    size ? `osl-btn--${size}` : '',
    block ? 'osl-btn--block' : '',
    loading ? 'is-loading' : '',
    className,
  ].filter(Boolean).join(' ')
  return (
    <button className={cls} disabled={disabled || loading} {...rest}>
      {loading ? <Spinner /> : icon}
      {children ? <span>{children}</span> : null}
    </button>
  )
}

export function Spinner({ size = 16 }: { size?: number }) {
  return (
    <svg className="osl-spin" width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity=".25" strokeWidth="3" />
      <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
    </svg>
  )
}

/** شاشةُ تحميلٍ كاملة — بديلُ التعليق عند تأجيل المسارات. */
export function FullLoader() {
  return (
    <div className="osl-fullloader" role="status" aria-live="polite">
      <Spinner size={28} />
      <span className="osl-sr">جارٍ التحميل</span>
    </div>
  )
}

export function Field({
  label, help, error, required, children,
}: { label: string; help?: string; error?: string; required?: boolean; children: ReactNode }) {
  return (
    <label className="osl-field">
      <span className="osl-field__label">
        {label}
        {required ? <b className="osl-field__req" aria-hidden="true"> *</b> : null}
      </span>
      {children}
      {error ? <span className="osl-field__err">{error}</span>
        : help ? <span className="osl-field__help">{help}</span> : null}
    </label>
  )
}

export function Input({ error, ltr, className = '', ...rest }:
  InputHTMLAttributes<HTMLInputElement> & { error?: boolean; ltr?: boolean }) {
  return <input className={['osl-input', error ? 'is-error' : '', ltr ? 'osl-ltr' : '', className].filter(Boolean).join(' ')} {...rest} />
}

export function Textarea({ error, className = '', ...rest }:
  TextareaHTMLAttributes<HTMLTextAreaElement> & { error?: boolean }) {
  return <textarea className={['osl-input osl-textarea', error ? 'is-error' : '', className].filter(Boolean).join(' ')} {...rest} />
}

export function Select({ error, className = '', children, ...rest }:
  SelectHTMLAttributes<HTMLSelectElement> & { error?: boolean }) {
  return (
    <select className={['osl-input osl-select', error ? 'is-error' : '', className].filter(Boolean).join(' ')} {...rest}>
      {children}
    </select>
  )
}

export function Card({ children, className = '', pad }: { children: ReactNode; className?: string; pad?: boolean }) {
  return <div className={['osl-card', pad ? 'osl-card--pad' : '', className].filter(Boolean).join(' ')}>{children}</div>
}

export type Tone = 'neutral' | 'success' | 'warn' | 'danger' | 'info' | 'accent'

export function Badge({ tone = 'neutral', children }: { tone?: Tone; children: ReactNode }) {
  return <span className={`osl-badge osl-badge--${tone}`}>{children}</span>
}

export function Alert({ tone = 'info', children }: { tone?: Tone; children: ReactNode }) {
  return <div className={`osl-alert osl-alert--${tone}`} role="status">{children}</div>
}

export function EmptyState({ title, line, action }: { title: string; line?: string; action?: ReactNode }) {
  return (
    <div className="osl-empty">
      <p className="osl-empty__title">{title}</p>
      {line ? <p className="osl-empty__line">{line}</p> : null}
      {action}
    </div>
  )
}

export function ErrorState({ message, onRetry }: { message?: string; onRetry?: () => void }) {
  return (
    <div className="osl-empty">
      <p className="osl-empty__title">تعذّر عرضُ هذا القسم</p>
      <p className="osl-empty__line">{message || 'تحقّق من اتّصالك ثمّ أعِد المحاولة.'}</p>
      {onRetry ? <Button variant="secondary" onClick={onRetry}>إعادة المحاولة</Button> : null}
    </div>
  )
}

export function Skeleton({ h = 16, w }: { h?: number; w?: number | string }) {
  return <span className="osl-skel" style={{ height: h, width: w ?? '100%' }} aria-hidden="true" />
}
