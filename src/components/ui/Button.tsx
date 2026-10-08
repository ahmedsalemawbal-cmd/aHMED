import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cx } from './cx';
import { Icon } from './Icon';
import { isIconName } from './icons';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  /** default 'secondary' */
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'whatsapp';
  /** 'md' (52px, mobile) | 'sm' (40px, desktop only) */
  size?: 'md' | 'sm';
  block?: boolean;
  /** internal glyph: plus, chat, phone, check, x, clock, pin, store */
  icon?: string;
  loading?: boolean;
  children?: ReactNode;
}

export function Button({
  variant = 'secondary',
  size = 'md',
  block,
  icon,
  loading,
  className,
  children,
  disabled,
  type = 'button',
  ...rest
}: ButtonProps) {
  const glyph = icon ?? (variant === 'whatsapp' ? 'chat' : undefined);
  return (
    <button
      type={type}
      {...rest}
      className={cx('md-btn', `md-btn-${variant}`, `md-btn-${size}`, block && 'md-btn-block', loading && 'is-loading', className)}
      disabled={disabled || loading}
      aria-busy={loading ? 'true' : undefined}
    >
      {loading ? <span className="md-spinner" aria-hidden="true" /> : glyph && isIconName(glyph) ? <Icon name={glyph} /> : null}
      <span>{children}</span>
    </button>
  );
}
