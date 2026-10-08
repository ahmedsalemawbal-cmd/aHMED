import { cx } from './cx';
import { Icon } from './Icon';
import { PRIORITY, type Priority } from './stages';

export interface PriorityBadgeProps {
  priority: Priority;
  size?: 'md' | 'sm';
  className?: string;
}

export function PriorityBadge({ priority, size, className }: PriorityBadgeProps) {
  const p = PRIORITY[priority];
  return (
    <span className={cx('md-badge', 'md-prio', `md-prio-${priority}`, size === 'sm' && 'md-badge-sm', className)}>
      <Icon name={p.icon} size={14} />
      {p.label}
    </span>
  );
}
