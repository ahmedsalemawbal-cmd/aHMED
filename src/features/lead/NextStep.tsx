import type { ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import type { LeadDetail, LeadTask } from '@/data/lead';
import { dueText, nextAction, noTaskView, taskHeadline } from './text';

/**
 * «الخطوة القادمة» (Lead.dc.html, DeskLead.dc.html): the earliest open task and
 * the button that does it; without a task, where the lead stands.
 */
export function NextStep({
  lead,
  task,
  now,
  desktop,
  completing,
  onMeeting,
  onComplete,
  onPostpone,
  onChangeStage,
}: {
  lead: LeadDetail;
  task: LeadTask | null;
  now: Date;
  desktop: boolean;
  completing: boolean;
  onMeeting: () => void;
  onComplete: (t: LeadTask) => void;
  onPostpone: (t: LeadTask) => void;
  onChangeStage: () => void;
}) {
  const navigate = useNavigate();
  const size = desktop ? 'sm' : 'md';

  let title: string;
  let sub: string | null;
  let overdue = false;
  let action: ReactNode;
  if (task) {
    const due = dueText(task.dueAt, now);
    const a = nextAction(task, lead);
    title = taskHeadline(task);
    sub = due.text;
    overdue = due.overdue;
    action =
      a.kind === 'message' || a.kind === 'quote' ? (
        <Button
          variant="primary"
          size={size}
          block={!desktop}
          onClick={() => {
            void navigate(a.to);
          }}
        >
          {a.label}
        </Button>
      ) : a.kind === 'meeting' ? (
        <Button variant="primary" size={size} block={!desktop} onClick={onMeeting}>
          {a.label}
        </Button>
      ) : (
        <Button
          variant="primary"
          size={size}
          block={!desktop}
          icon="check"
          loading={completing}
          onClick={() => {
            onComplete(task);
          }}
        >
          {a.label}
        </Button>
      );
  } else {
    const v = noTaskView(lead);
    title = v.title;
    sub = v.body;
    action =
      lead.stage === 'won' ? null : (
        <Button variant="secondary" size={size} block={!desktop} onClick={onChangeStage}>
          غيّر المرحلة
        </Button>
      );
  }

  return (
    <section aria-labelledby="lead-next" className={`flex flex-col gap-3 rounded-lg border-[1.5px] border-ink bg-surface-raised ${desktop ? 'p-5' : 'p-4'}`}>
      <h2 id="lead-next" className="m-0 text-label-sm font-bold text-ink-muted">
        الخطوة القادمة
      </h2>
      <div className="flex items-center gap-3">
        {desktop ? null : (
          <span aria-hidden="true" className={`grid size-11 flex-none place-items-center rounded-full ${overdue ? 'bg-warning-soft text-warning' : 'bg-surface-sunken text-ink'}`}>
            <Icon name={overdue ? 'alert' : task ? 'clock' : lead.stage === 'won' ? 'check' : 'info'} size={22} />
          </span>
        )}
        <div className="flex min-w-0 flex-col">
          <span className="text-title-3 font-bold">{title}</span>
          {sub ? <span className={`text-body-sm ${overdue ? 'font-bold text-warning' : 'text-ink-muted'}`}>{sub}</span> : null}
        </div>
      </div>
      {action !== null || (task !== null && desktop) ? (
        <div className={desktop ? 'flex flex-wrap gap-2' : 'flex flex-col gap-2'}>
          {action}
          {task && desktop ? (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                onPostpone(task);
              }}
            >
              أجّل
            </Button>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}
