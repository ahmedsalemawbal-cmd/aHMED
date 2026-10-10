import type { MouseEvent, ReactNode } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Skeleton } from '@/components/app/Skeleton';
import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { PriorityBadge } from '@/components/ui/PriorityBadge';
import { StageBadge } from '@/components/ui/StageBadge';
import type { LeadDetail, LeadTask } from '@/data/lead';
import { Glyph } from './Glyph';
import { headerSub, whatsappPath } from './text';

/** Back to the list the user came from, or to «العملاء» when the page was opened directly. */
function BackLink() {
  const navigate = useNavigate();
  const onClick = (e: MouseEvent) => {
    const idx = (window.history.state as { idx?: number } | null)?.idx ?? 0;
    if (idx > 0) {
      e.preventDefault();
      void navigate(-1);
    }
  };
  return (
    <Link to="/leads" onClick={onClick} aria-label="رجوع إلى العملاء" className="-ms-3 grid size-touch flex-none place-items-center text-ink">
      <Glyph name="back" size={24} />
    </Link>
  );
}

function Badges({ lead, size }: { lead: LeadDetail; size?: 'sm' }) {
  return (
    <>
      <StageBadge stage={lead.stage} size={size} />
      {lead.priority ? <PriorityBadge priority={lead.priority} size={size} /> : null}
    </>
  );
}

function CallLink({ phone, desktop }: { phone: string; desktop: boolean }) {
  return (
    <a href={`tel:+${phone}`} className={`md-btn md-btn-secondary no-underline ${desktop ? 'md-btn-sm' : 'md-btn-block px-2'}`}>
      <Icon name="phone" />
      <span>اتصال</span>
    </a>
  );
}

function QuoteLink({ leadId, desktop }: { leadId: string; desktop: boolean }) {
  return (
    <Link to={`/leads/${leadId}/quotes/new`} className={`md-btn md-btn-secondary no-underline ${desktop ? 'md-btn-sm' : 'md-btn-block px-2'}`}>
      عرض سعر
    </Link>
  );
}

const GRID: Record<number, string> = { 1: 'grid-cols-1', 2: 'grid-cols-2', 3: 'grid-cols-[2fr_1fr_1fr]' };

/** Lead.dc.html header: back, «⋮», name, sub line, badges and the action row. */
export function MobileHeader({ lead, next, now, onMore }: { lead: LeadDetail; next: LeadTask | null; now: Date; onMore: () => void }) {
  const navigate = useNavigate();
  const actions: ReactNode[] = [];
  if (lead.phone && !lead.doNotContact)
    actions.push(
      <Button
        key="wa"
        variant="whatsapp"
        block
        onClick={() => {
          void navigate(whatsappPath(lead.id, next));
        }}
      >
        واتساب
      </Button>,
    );
  if (lead.phone) actions.push(<CallLink key="call" phone={lead.phone} desktop={false} />);
  actions.push(<QuoteLink key="quote" leadId={lead.id} desktop={false} />);

  return (
    <header className="flex flex-col gap-[14px] border-b border-line bg-surface-raised px-4 pb-4 pt-3">
      <div className="flex items-center justify-between">
        <BackLink />
        <button type="button" aria-label="المزيد: تعديل البيانات، تغيير المرحلة" aria-haspopup="dialog" onClick={onMore} className="-me-3 grid size-touch place-items-center border-0 bg-transparent text-ink">
          <Glyph name="more" size={24} strokeWidth={2.4} />
        </button>
      </div>
      <div className="flex flex-col gap-2">
        <h1 className="m-0 text-title-1 font-bold">{lead.businessName}</h1>
        <span className="text-body-sm text-ink-muted">{headerSub(lead, now, false)}</span>
        <div className="flex flex-wrap gap-2">
          <Badges lead={lead} />
        </div>
      </div>
      {lead.doNotContact ? (
        <p role="note" className="m-0 rounded-md bg-danger-soft px-[14px] py-[10px] text-body-sm font-bold text-danger">
          طلب عدم التواصل. لا رسائل ولا متابعات.
        </p>
      ) : !lead.phone ? (
        <p role="note" className="m-0 rounded-md bg-warning-soft px-[14px] py-[10px] text-body-sm text-ink">
          لا يوجد رقم جوال. أضفه من «تعديل البيانات».
        </p>
      ) : null}
      <div className={`grid gap-2 ${GRID[actions.length] ?? 'grid-cols-1'}`}>{actions}</div>
    </header>
  );
}

/** Mobile header while loading or when the lead is missing. */
export function MobileHeaderStub({ loading }: { loading: boolean }) {
  return (
    <header className="flex flex-col gap-[14px] border-b border-line bg-surface-raised px-4 pb-4 pt-3">
      <div className="flex items-center">
        <BackLink />
      </div>
      {loading ? (
        <div className="flex flex-col gap-2">
          <Skeleton className="h-[34px] w-48" />
          <Skeleton className="h-[22px] w-64" />
          <Skeleton className="h-7 w-40 rounded-full" />
          <Skeleton className="mt-[6px] h-control" />
        </div>
      ) : null}
    </header>
  );
}

/** DeskLead.dc.html header: breadcrumb, name with badges, sub line and the sm actions. */
export function DeskLeadHeader({ lead, next, now, onStage, onMore }: { lead: LeadDetail; next: LeadTask | null; now: Date; onStage: () => void; onMore: () => void }) {
  const navigate = useNavigate();
  return (
    <header className="flex flex-col gap-3 border-b border-line bg-surface-raised px-10 py-5">
      <nav aria-label="مسار التنقل" className="text-body-sm text-ink-muted">
        <Link to="/leads" className="text-ink-muted">
          العملاء
        </Link>
        {' / '}
        <span aria-current="page">{lead.businessName}</span>
      </nav>
      <div className="flex flex-wrap items-center gap-4">
        <div className="flex grow flex-col gap-[6px]">
          <div className="flex flex-wrap items-center gap-[10px]">
            <h1 className="m-0 text-title-1 font-bold">{lead.businessName}</h1>
            <Badges lead={lead} />
          </div>
          <span className="text-body-sm text-ink-muted">{headerSub(lead, now, true)}</span>
          {lead.doNotContact ? <span className="text-body-sm font-bold text-danger">طلب عدم التواصل. لا رسائل ولا متابعات.</span> : null}
        </div>
        <div className="flex flex-wrap gap-2">
          {lead.phone && !lead.doNotContact ? (
            <Button
              variant="whatsapp"
              size="sm"
              onClick={() => {
                void navigate(whatsappPath(lead.id, next));
              }}
            >
              واتساب
            </Button>
          ) : null}
          {lead.phone ? <CallLink phone={lead.phone} desktop /> : null}
          <QuoteLink leadId={lead.id} desktop />
          <Button variant="secondary" size="sm" onClick={onStage}>
            تغيير المرحلة
          </Button>
          <Button variant="secondary" size="sm" aria-haspopup="dialog" onClick={onMore}>
            المزيد
          </Button>
        </div>
      </div>
    </header>
  );
}

/** Desktop header while loading or when the lead is missing. */
export function DeskHeaderStub({ title }: { title: string | null }) {
  return (
    <header className="flex flex-col gap-3 border-b border-line bg-surface-raised px-10 py-5">
      <nav aria-label="مسار التنقل" className="text-body-sm text-ink-muted">
        <Link to="/leads" className="text-ink-muted">
          العملاء
        </Link>
      </nav>
      {title === null ? <Skeleton className="h-[34px] w-64" /> : <h1 className="m-0 text-title-1 font-bold">{title}</h1>}
    </header>
  );
}
