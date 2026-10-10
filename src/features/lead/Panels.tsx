import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import type { LeadMessage, LeadQuote, LeadTask } from '@/data/lead';
import { formatSAR } from '@/lib/format';
import { MESSAGE_KIND_LABEL, type EventTone, type TimelineEvent } from '@/lib/timeline';
import { draftPath, dueText, eventTime, messageStatus, quoteStatus, taskClosedText } from './text';

const DOT: Record<EventTone, string> = {
  not_visited: 'bg-stage-not-visited',
  visited: 'bg-stage-visited',
  contacted: 'bg-stage-contacted',
  replied: 'bg-stage-replied',
  meeting: 'bg-stage-meeting',
  proposal: 'bg-stage-proposal',
  won: 'bg-stage-won',
  lost: 'bg-stage-lost',
  neutral: 'bg-line-strong',
};

/** «الخط الزمني»: newest first. The desktop also shows the quoted message or observation. */
export function TimelinePanel({ events, now, desktop }: { events: TimelineEvent[]; now: Date; desktop: boolean }) {
  return (
    <ol aria-label="الخط الزمني" className={`m-0 flex list-none flex-col p-0 ${desktop ? '' : 'pt-4'}`}>
      {events.map((e, i) => {
        const last = i === events.length - 1;
        const time = eventTime(e.at, now);
        return (
          <li key={e.id} className={`flex ${desktop ? 'gap-[14px]' : 'gap-3'}`}>
            <div className={`flex flex-none flex-col items-center ${desktop ? 'w-5' : 'w-6'}`}>
              <span
                aria-hidden="true"
                data-tone={e.tone}
                className={`flex-none rounded-full ${DOT[e.tone]} ${desktop ? 'mt-[5px] size-3 ring-4 ring-surface-raised' : 'mt-1 size-[14px] ring-4 ring-surface'}`}
              />
              {last ? null : <span aria-hidden="true" className="min-h-6 w-[2px] grow bg-line" />}
            </div>
            {desktop ? (
              <div className="flex min-w-0 grow flex-col gap-[6px] pb-6">
                <div className="flex justify-between gap-3">
                  <b className="text-[15px] leading-[22px]">{e.title}</b>
                  <span className="whitespace-nowrap text-label-sm text-ink-muted">{time}</span>
                </div>
                {e.body ? <span className="text-body-sm text-ink-muted">{e.body}</span> : null}
                {e.quote ? <div className="whitespace-pre-line rounded-md bg-surface-sunken px-[14px] py-3 text-body-sm leading-[23px]">{e.quote}</div> : null}
              </div>
            ) : (
              <div className="flex min-w-0 flex-col gap-[2px] pb-5">
                <span className="text-label-sm text-ink-muted">{time}</span>
                <span className="text-label font-bold">{e.title}</span>
                {e.body ? <span className="text-body-sm text-ink-muted">{e.body}</span> : null}
              </div>
            )}
          </li>
        );
      })}
    </ol>
  );
}

const STATUS_TONE = { draft: 'bg-warning-soft text-warning', sent: 'bg-stage-contacted-soft text-stage-contacted', replied: 'bg-stage-replied-soft text-stage-replied' } as const;

/** «الرسائل»: every message, newest first; a draft can be finished (copied when there is no phone). */
export function MessagesPanel({ leadId, messages, now, desktop, doNotContact }: { leadId: string; messages: LeadMessage[]; now: Date; desktop: boolean; doNotContact: boolean }) {
  if (messages.length === 0) {
    return (
      <EmptyState
        icon="chat"
        title="لا رسائل بعد"
        message={doNotContact ? 'طلب عدم التواصل. لا رسائل ولا متابعات.' : 'تظهر هنا الرسائل التي تجهّزها وترسلها.'}
      />
    );
  }
  return (
    <ul aria-label="الرسائل" className={`m-0 flex list-none flex-col gap-3 p-0 ${desktop ? '' : 'pt-4'}`}>
      {messages.map((m) => {
        const st = messageStatus(m, now);
        return (
          <li key={m.id} className="flex flex-col gap-2 rounded-lg border border-line bg-surface-raised p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <b className="text-[15px] leading-[22px]">{MESSAGE_KIND_LABEL[m.kind]}</b>
              <span className={`rounded-full px-[10px] py-px text-label-sm font-bold ${STATUS_TONE[st.tone]}`}>{st.text}</span>
            </div>
            <p className="m-0 whitespace-pre-line text-body-sm leading-[23px] text-ink">{m.body}</p>
            {m.status === 'draft' && !doNotContact ? (
              <Link to={draftPath(leadId, m)} className={`md-btn md-btn-secondary no-underline ${desktop ? 'md-btn-sm self-start' : 'md-btn-block'}`}>
                أكمل المسودة
              </Link>
            ) : null}
          </li>
        );
      })}
    </ul>
  );
}

/** «المهام»: open tasks first with «تم» and «أجّل», then the closed ones, muted. */
export function TasksPanel({
  tasks,
  now,
  desktop,
  completing,
  onComplete,
  onPostpone,
}: {
  tasks: LeadTask[];
  now: Date;
  desktop: boolean;
  completing: string | null;
  onComplete: (t: LeadTask) => void;
  onPostpone: (t: LeadTask) => void;
}) {
  if (tasks.length === 0) {
    return <EmptyState icon="clock" title="لا مهام بعد" message="تظهر هنا المتابعات والاجتماعات عند إنشائها." />;
  }
  const open = tasks.filter((t) => !t.doneAt && !t.cancelledAt).sort((a, b) => a.dueAt.getTime() - b.dueAt.getTime());
  const closed = tasks
    .filter((t) => Boolean(t.doneAt ?? t.cancelledAt))
    .sort((a, b) => (b.doneAt ?? b.cancelledAt ?? b.dueAt).getTime() - (a.doneAt ?? a.cancelledAt ?? a.dueAt).getTime());
  return (
    <div className={`flex flex-col gap-5 ${desktop ? '' : 'pt-4'}`}>
      <section aria-labelledby="lead-open-tasks" className="flex flex-col gap-2">
        <h3 id="lead-open-tasks" className="m-0 text-[15px] font-bold leading-[22px]">
          {`مفتوحة · ${open.length.toString()}`}
        </h3>
        {open.length === 0 ? <p className="m-0 text-body-sm text-ink-muted">لا مهام مفتوحة.</p> : null}
        <ul className="m-0 flex list-none flex-col gap-2 p-0">
          {open.map((t) => {
            const due = dueText(t.dueAt, now);
            return (
              <li key={t.id} className={`flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3 ${due.overdue ? 'border-warning bg-warning-soft' : 'border-line bg-surface-raised'}`}>
                <div className="flex min-w-0 grow flex-col">
                  <span className="text-label font-bold">{t.title}</span>
                  <span className={`text-body-sm ${due.overdue ? 'font-bold text-warning' : 'text-ink-muted'}`}>{due.text}</span>
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="secondary"
                    size="sm"
                    icon="check"
                    aria-label={`تم: ${t.title}`}
                    loading={completing === t.id}
                    onClick={() => {
                      onComplete(t);
                    }}
                  >
                    تم
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    aria-label={`أجّل: ${t.title}`}
                    onClick={() => {
                      onPostpone(t);
                    }}
                  >
                    أجّل
                  </Button>
                </div>
              </li>
            );
          })}
        </ul>
      </section>
      {closed.length ? (
        <section aria-labelledby="lead-closed-tasks" className="flex flex-col gap-2">
          <h3 id="lead-closed-tasks" className="m-0 text-[15px] font-bold leading-[22px] text-ink-muted">
            {`منتهية · ${closed.length.toString()}`}
          </h3>
          <ul className="m-0 flex list-none flex-col p-0">
            {closed.map((t) => (
              <li key={t.id} className="flex flex-col border-b border-line py-[10px] last:border-b-0">
                <span className={`text-body-sm ${t.doneAt ? 'text-ink-muted' : 'text-ink-muted line-through'}`}>{t.title}</span>
                <span className="text-label-sm text-ink-muted">{taskClosedText(t, now)}</span>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}

/** «العروض»: number, total and status (decisions §2); built in phase 1g. */
export function QuotesPanel({ quotes, now, desktop }: { quotes: LeadQuote[]; now: Date; desktop: boolean }) {
  if (quotes.length === 0) {
    return <EmptyState icon="clock" title="لا عروض بعد" message="أنشئ العرض من زر «عرض سعر» أعلى الصفحة، ويُبنى من الخدمات المقترحة." />;
  }
  return (
    <ul aria-label="العروض" className={`m-0 flex list-none flex-col gap-2 p-0 ${desktop ? '' : 'pt-4'}`}>
      {quotes.map((q) => (
        <li key={q.id} className="flex items-center justify-between gap-3 rounded-lg border border-line bg-surface-raised px-4 py-3">
          <div className="flex flex-col">
            <b dir="ltr" className="self-start text-[15px] leading-[22px] tabular-nums">
              {q.number}
            </b>
            <span className="text-body-sm text-ink-muted">{quoteStatus(q, now)}</span>
          </div>
          <b className="text-title-3 tabular-nums">{formatSAR(q.total)}</b>
        </li>
      ))}
    </ul>
  );
}
