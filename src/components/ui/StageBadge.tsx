import type { ReactNode } from 'react';
import { cx } from './cx';
import { STAGE_LABEL, type Stage } from './stages';

export interface StageBadgeProps {
  stage: Stage;
  size?: 'md' | 'sm';
  children?: ReactNode;
  className?: string;
}

export function StageBadge({ stage, size, children, className }: StageBadgeProps) {
  return (
    <span className={cx('md-badge', 'md-stage', `md-stage-${stage.replace('_', '-')}`, size === 'sm' && 'md-badge-sm', className)}>
      <span className="md-dot" aria-hidden="true" />
      {children || STAGE_LABEL[stage]}
    </span>
  );
}
