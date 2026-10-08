import type { ReactNode } from 'react';
import { cx } from './cx';
import { Icon } from './Icon';

export interface ToastProps {
  tone?: 'success' | 'error' | 'info';
  title: ReactNode;
  message?: ReactNode;
  action?: string;
  onAction?: () => void;
  className?: string;
}

const TOAST_ICON = { success: 'check', error: 'alert', info: 'info' } as const;

export function Toast({ tone = 'info', title, message, action, onAction, className }: ToastProps) {
  return (
    <div className={cx('md-toast', `md-toast-${tone}`, className)} role={tone === 'error' ? 'alert' : 'status'}>
      <span className="md-toast-icon">
        <Icon name={TOAST_ICON[tone]} size={20} />
      </span>
      <div className="md-toast-text">
        <strong>{title}</strong>
        {message ? <span>{message}</span> : null}
      </div>
      {action ? (
        <button type="button" className="md-toast-action" onClick={onAction}>
          {action}
        </button>
      ) : null}
    </div>
  );
}
