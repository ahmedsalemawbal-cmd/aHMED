import type { KeyboardEvent } from 'react';
import { Button } from './Button';
import { cx } from './cx';
import { Icon } from './Icon';
import { PriorityBadge } from './PriorityBadge';
import { StageBadge } from './StageBadge';
import type { Priority, Stage } from './stages';

export interface LeadCardProps {
  variant?: 'full' | 'compact';
  businessName: string;
  activity: string;
  contactName?: string;
  stage?: Stage;
  score?: number | null;
  priority?: Priority | null;
  lastContact?: string;
  nextAction?: string;
  nextActionAt?: string;
  overdue?: boolean;
  /** compact only */
  value?: string;
  daysInStage?: number;
  /** compact: more than 7 days without movement */
  stale?: boolean;
  onClick?: () => void;
  /** never pass for a lead with do_not_contact */
  onWhatsApp?: () => void;
  className?: string;
}

export function LeadCard(p: LeadCardProps) {
  const compact = p.variant === 'compact';
  const flags = cx('md-lead', compact && 'md-lead-compact', p.stale && 'is-stale', p.className);
  const head = (
    <div className="md-lead-head">
      <span className="md-lead-icon" aria-hidden="true">
        <Icon name="store" size={20} />
      </span>
      <div className="md-lead-titles">
        <div className="md-lead-name">{p.businessName}</div>
        <div className="md-lead-activity">
          {p.activity}
          {p.contactName ? ` · ${p.contactName}` : ''}
        </div>
      </div>
      {compact ? null : (
        <div className="md-lead-score" title="الدرجة">
          <b>{p.score ?? '—'}</b>
          <span>/100</span>
        </div>
      )}
    </div>
  );

  if (compact) {
    const onKeyDown = (e: KeyboardEvent<HTMLElement>) => {
      if (p.onClick && (e.key === 'Enter' || e.key === ' ')) {
        e.preventDefault();
        p.onClick();
      }
    };
    return (
      <article
        className={flags}
        onClick={p.onClick}
        onKeyDown={p.onClick ? onKeyDown : undefined}
        tabIndex={p.onClick ? 0 : undefined}
        role={p.onClick ? 'button' : undefined}
      >
        {head}
        <div className="md-lead-meta">
          {p.value != null ? <span className="md-lead-value">{p.value}</span> : null}
          <span className={cx('md-lead-days', p.stale && 'is-stale')}>
            {p.stale ? <Icon name="alert" size={14} /> : <Icon name="clock" size={14} />}
            {`${(p.daysInStage ?? 0).toString()} يوم`}
          </span>
        </div>
      </article>
    );
  }

  return (
    <article className={flags}>
      <button type="button" className="md-lead-main" onClick={p.onClick}>
        {head}
        <div className="md-lead-badges">
          <StageBadge stage={p.stage ?? 'not_visited'} size="sm" />
          {p.priority ? <PriorityBadge priority={p.priority} size="sm" /> : null}
        </div>
        {p.lastContact ? <div className="md-lead-last">{`آخر تواصل: ${p.lastContact}`}</div> : null}
      </button>
      <div className={cx('md-lead-next', p.overdue && 'is-overdue')}>
        <Icon name={p.overdue ? 'alert' : 'clock'} size={18} />
        <div className="md-lead-next-text">
          <span>{p.nextAction || 'بدون خطوة قادمة'}</span>
          {p.nextActionAt ? <small>{p.nextActionAt}</small> : null}
        </div>
        {p.onWhatsApp ? (
          <Button variant="whatsapp" size="sm" onClick={p.onWhatsApp} aria-label={`واتساب ${p.businessName}`}>
            واتساب
          </Button>
        ) : null}
      </div>
    </article>
  );
}
