import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import type { TaskRow } from '@/data/tasks';
import { TaskAction } from './TaskAction';
import { lastFollowupText, SECTION_SURFACE, whenText, type SectionId } from './tasks-view';
import { TaskSwipe } from './TaskSwipe';
import type { TaskScreen } from './useTaskScreen';

/**
 * One task: the round checkbox, the title, «shop · when», and the row's
 * action. A tap on the row opens the lead; «أجّل» is a visible button as well
 * as the right swipe, and ticking is the checkbox as well as the left swipe.
 */
export function TaskRowView({
  t,
  section,
  now,
  desktop,
  screen,
  first,
  decision,
}: {
  t: TaskRow;
  section: SectionId;
  now: Date;
  desktop: boolean;
  screen: TaskScreen;
  first: boolean;
  /** flow 2.4: the day-7 decision under this row */
  decision: boolean;
}) {
  const on = t.doneAt !== null;
  const late = section === 'late';
  const label = `${t.title} · ${t.lead.businessName}`;
  return (
    <div className={first ? '' : `border-t ${late ? 'border-warning' : 'border-line'}`} data-task={t.id}>
      <TaskSwipe
        surface={SECTION_SURFACE[section]}
        onComplete={
          on
            ? undefined
            : () => {
                screen.complete(t);
              }
        }
        onPostpone={
          on
            ? undefined
            : () => {
                screen.askPostpone(t);
              }
        }
      >
        <div className="flex min-h-[68px] items-center gap-2 px-[14px] py-[10px]">
          <button
            type="button"
            role="checkbox"
            aria-checked={on}
            aria-label={`${on ? 'إلغاء إنجاز' : 'إنجاز'} ${t.title}`}
            onClick={() => {
              if (on) screen.reopen(t);
              else screen.complete(t);
            }}
            className="-ms-[10px] grid size-touch flex-none cursor-pointer place-items-center rounded-full border-0 bg-transparent p-0"
          >
            <span
              className={`grid size-[26px] place-items-center rounded-full ${on ? 'bg-success text-surface-raised' : 'border-2 border-line-strong bg-surface-raised text-transparent'}`}
            >
              <Icon name="check" size={16} />
            </span>
          </button>
          {/* the text opens the lead: as tall as the row, so the whole middle is the touch target */}
          <Link to={`/leads/${t.lead.id}`} className="flex min-w-0 grow flex-col justify-center self-stretch no-underline">
            <span className={`text-label font-bold ${on ? 'text-ink-muted line-through' : 'text-ink'}`}>{t.title}</span>
            <span className="text-body-sm leading-5 text-ink-muted">
              {`${t.lead.businessName} · `}
              <span className={`whitespace-nowrap tabular-nums ${late && !on ? 'font-bold text-warning' : ''}`}>{on ? 'أُنجزت اليوم' : whenText(t, now)}</span>
            </span>
          </Link>
          {on ? null : (
            <>
              <span className="flex-none">
                <TaskAction t={t} />
              </span>
              {desktop ? (
                <Button
                  variant="secondary"
                  size="sm"
                  className="flex-none"
                  aria-label={`أجّل ${label}`}
                  onClick={() => {
                    screen.askPostpone(t);
                  }}
                >
                  أجّل
                </Button>
              ) : (
                <button
                  type="button"
                  aria-label={`أجّل ${label}`}
                  title="أجّل"
                  onClick={() => {
                    screen.askPostpone(t);
                  }}
                  className="-me-2 grid size-touch flex-none cursor-pointer place-items-center rounded-full border-0 bg-transparent p-0 text-ink-muted"
                >
                  <Icon name="clock" size={20} />
                </button>
              )}
            </>
          )}
        </div>
      </TaskSwipe>
      {decision ? <DecisionPanel t={t} screen={screen} desktop={desktop} /> : null}
    </div>
  );
}

/** brief.md flow 2.4 under the last follow-up: postpone 30 days or move to lost. */
export function DecisionPanel({ t, screen, desktop, now }: { t: TaskRow; screen: TaskScreen; desktop: boolean; now?: Date }) {
  const busy = screen.deciding === t.lead.id;
  return (
    <div role="group" aria-label={`قرار ${t.lead.businessName}`} className="flex flex-col gap-2 border-t border-dashed border-line-strong bg-surface px-[14px] py-3">
      <p className="m-0 flex items-center gap-2 text-body-sm font-bold text-ink">
        <span className="flex-none text-warning">
          <Icon name="alert" size={18} />
        </span>
        {now && t.doneAt ? `لم يرد بعد المتابعة الأخيرة · ${lastFollowupText(t.doneAt, now)}` : 'لم يرد بعد المتابعة الأخيرة'}
      </p>
      <p className="m-0 text-label-sm font-normal text-ink-muted">أجّله 30 يوماً بمهمة «أعد المحاولة»، أو انقله إلى خسارة.</p>
      <div className="flex flex-wrap gap-2">
        <Button
          variant="secondary"
          size={desktop ? 'sm' : 'md'}
          className="grow"
          loading={busy}
          aria-label={`أجّل ${t.lead.businessName} 30 يوماً`}
          onClick={() => {
            screen.postpone30(t);
          }}
        >
          أجّل 30 يوماً
        </Button>
        <Button
          variant="secondary"
          size={desktop ? 'sm' : 'md'}
          className="grow"
          aria-label={`نقل ${t.lead.businessName} إلى خسارة`}
          onClick={() => {
            screen.askLost(t);
          }}
        >
          نقل إلى خسارة
        </Button>
      </div>
    </div>
  );
}
