import type { ReactNode } from 'react';
import { cx } from './cx';
import { Icon } from './Icon';
import { isIconName } from './icons';

export interface EmptyStateProps {
  title: ReactNode;
  message?: ReactNode;
  /** one Button */
  action?: ReactNode;
  /** pin, store, chat, check, clock */
  icon?: string;
  className?: string;
}

export function EmptyState({ title, message, action, icon, className }: EmptyStateProps) {
  return (
    <div className={cx('md-empty', className)}>
      <span className="md-empty-icon" aria-hidden="true">
        <Icon name={icon && isIconName(icon) ? icon : 'pin'} size={28} />
      </span>
      <h3 className="md-empty-title">{title}</h3>
      {message ? <p className="md-empty-msg">{message}</p> : null}
      {action ? <div className="md-empty-action">{action}</div> : null}
    </div>
  );
}
