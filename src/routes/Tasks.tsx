import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AppShell } from '@/components/app/AppShell';
import { DeskHeader } from '@/components/app/DeskHeader';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { Skeleton } from '@/components/app/Skeleton';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { useTasks, type TaskRow, type TasksData } from '@/data/tasks';
import { Glyph } from '@/features/lead/Glyph';
import { DecisionPanel, TaskRowView } from '@/features/tasks/TaskRowView';
import { doneTodayCount, groupTasks, openCountByLead, openTasksText, pendingDecisions, sectionBox, SECTION_TITLE, showsDecision, type SectionId } from '@/features/tasks/tasks-view';
import { useTaskScreen, type TaskScreen } from '@/features/tasks/useTaskScreen';
import { useIsDesktop } from '@/hooks/useMediaQuery';

/** Screen 8 · المهام — design/screens/Tasks.dc.html; on the desktop inside AppShell with two columns. */
export default function Tasks() {
  const desktop = useIsDesktop();
  const [now] = useState(() => new Date());
  const q = useTasks(now);
  const screen = useTaskScreen(now);
  return (
    <AppShell section="tasks">
      {desktop ? <DeskTasks q={q} now={now} screen={screen} /> : <MobileTasks q={q} now={now} screen={screen} />}
      {screen.sheets}
    </AppShell>
  );
}

type Q = ReturnType<typeof useTasks>;

interface View {
  sections: { id: SectionId; tasks: TaskRow[] }[];
  decisions: TaskRow[];
  done: number;
  open: number;
  /** the rows that show the day-7 decision under them */
  withDecision: Set<string>;
}

function useView(d: TasksData | undefined, now: Date): View | null {
  return useMemo(() => {
    if (!d) return null;
    const g = groupTasks(d.tasks, now);
    const openByLead = openCountByLead(d.tasks);
    const withDecision = new Set(d.tasks.filter((t) => showsDecision(t, openByLead.get(t.lead.id) ?? 0, now)).map((t) => t.id));
    return {
      sections: (['late', 'today', 'next'] as const).map((id) => ({ id, tasks: g[id] })).filter((s) => s.tasks.length > 0),
      decisions: pendingDecisions(d.lastFollowups, d.tasks, now),
      done: doneTodayCount(d.tasks),
      open: d.tasks.length - doneTodayCount(d.tasks),
      withDecision,
    };
  }, [d, now]);
}

function NewLeadButton() {
  const navigate = useNavigate();
  return (
    <Button
      variant="primary"
      icon="plus"
      onClick={() => {
        void navigate('/leads/new');
      }}
    >
      عميل جديد
    </Button>
  );
}

function NoTasks() {
  return <EmptyState icon="check" title="لا مهام" message="لا متابعات مفتوحة. انزل للميدان وسجّل محلاً جديداً." action={<NewLeadButton />} />;
}

function Section({ id, tasks, now, desktop, screen, withDecision }: { id: SectionId; tasks: TaskRow[]; now: Date; desktop: boolean; screen: TaskScreen; withDecision: Set<string> }) {
  const late = id === 'late';
  return (
    <section aria-labelledby={`tasks-${id}`} className="flex flex-col gap-[10px]">
      <h2 id={`tasks-${id}`} className={`m-0 text-title-3 font-bold tabular-nums ${late ? 'text-warning' : 'text-ink'}`}>
        {`${SECTION_TITLE[id]} · ${tasks.length.toString()}`}
      </h2>
      <div className={`flex flex-col overflow-hidden rounded-lg ${sectionBox(id)}`}>
        {tasks.map((t, i) => (
          <TaskRowView key={t.id} t={t} section={id} now={now} desktop={desktop} screen={screen} first={i === 0} decision={withDecision.has(t.id)} />
        ))}
      </div>
    </section>
  );
}

/** Last follow-ups done earlier that still wait for «أجّل 30 يوماً» or «نقل إلى خسارة». */
function Decisions({ rows, now, desktop, screen }: { rows: TaskRow[]; now: Date; desktop: boolean; screen: TaskScreen }) {
  if (!rows.length) return null;
  return (
    <section aria-labelledby="tasks-decide" className="flex flex-col gap-[10px]">
      <h2 id="tasks-decide" className="m-0 text-title-3 font-bold tabular-nums">{`تحتاج قراراً · ${rows.length.toString()}`}</h2>
      <div className={`grid gap-3 ${desktop ? 'grid-cols-[repeat(auto-fill,minmax(340px,1fr))]' : ''}`}>
        {rows.map((t) => (
          <div key={t.id} className="flex flex-col overflow-hidden rounded-lg border border-line bg-surface-raised">
            <div className="app-row flex min-h-16 items-center px-[14px] py-[10px]">
              <Link to={`/leads/${t.lead.id}`} className="app-stretched text-label font-bold text-ink no-underline">
                {t.lead.businessName}
              </Link>
            </div>
            <DecisionPanel t={t} screen={screen} desktop={desktop} now={now} />
          </div>
        ))}
      </div>
    </section>
  );
}

/* ------------------------------------------------------------------ mobile */

function MobileTasks({ q, now, screen }: { q: Q; now: Date; screen: TaskScreen }) {
  const v = useView(q.data, now);
  return (
    <>
      <header className="flex items-center gap-1 px-4 pb-2 pt-5">
        <Link to="/" aria-label="رجوع إلى اليوم" className="-ms-3 grid size-touch place-items-center rounded-full text-ink">
          <Glyph name="back" size={24} />
        </Link>
        <h1 className="m-0 grow text-title-1 font-bold">المهام</h1>
        {v ? <span className="text-body-sm text-ink-muted tabular-nums">{`${v.done.toString()} منجزة اليوم`}</span> : null}
      </header>
      <main className="flex flex-col gap-6 px-4 pb-6 pt-2">
        <Body q={q} v={v} now={now} desktop={false} screen={screen} />
        {v && (v.open > 0 || v.done > 0) ? (
          <p className="m-0 text-center text-label-sm font-normal text-ink-muted">اسحب المهمة يساراً للإنجاز، ويميناً للتأجيل: غداً، بعد 3 أيام، أو تاريخ محدد.</p>
        ) : null}
      </main>
    </>
  );
}

function Body({ q, v, now, desktop, screen }: { q: Q; v: View | null; now: Date; desktop: boolean; screen: TaskScreen }) {
  if (q.isError && !v)
    return (
      <ErrorRetry
        title="تعذّر تحميل المهام"
        onRetry={() => {
          void q.refetch();
        }}
      />
    );
  if (!v) return <TasksSkeleton />;
  return (
    <>
      <Decisions rows={v.decisions} now={now} desktop={desktop} screen={screen} />
      {v.sections.length === 0 && v.decisions.length === 0 ? <NoTasks /> : null}
      {v.sections.map((s) => (
        <Section key={s.id} id={s.id} tasks={s.tasks} now={now} desktop={desktop} screen={screen} withDecision={v.withDecision} />
      ))}
    </>
  );
}

function TasksSkeleton() {
  return (
    <div className="flex flex-col gap-6" aria-busy="true" aria-label="جارٍ التحميل">
      {['h-[128px]', 'h-[192px]'].map((h) => (
        <div key={h} className="flex flex-col gap-[10px]">
          <Skeleton className="h-[26px] w-28" />
          <Skeleton className={`rounded-lg ${h}`} />
        </div>
      ))}
    </div>
  );
}

/* ----------------------------------------------------------------- desktop */

function DeskTasks({ q, now, screen }: { q: Q; now: Date; screen: TaskScreen }) {
  const v = useView(q.data, now);
  const open = v?.open ?? 0;
  const subtitle = v ? `${openTasksText(open)} · ${v.done.toString()} منجزة اليوم` : undefined;
  const byId = (id: SectionId) => v?.sections.find((s) => s.id === id);
  const late = byId('late');
  const today = byId('today');
  const next = byId('next');
  return (
    <>
      <DeskHeader title="المهام" subtitle={subtitle} search={false} />
      <main className="flex w-full max-w-[1280px] flex-col gap-6 px-10 pb-12 pt-8">
        {q.isError && !v ? (
          <ErrorRetry
            title="تعذّر تحميل المهام"
            onRetry={() => {
              void q.refetch();
            }}
          />
        ) : null}
        {!v && !q.isError ? (
          <div className="grid grid-cols-[repeat(auto-fit,minmax(440px,1fr))] gap-6">
            <TasksSkeleton />
            <TasksSkeleton />
          </div>
        ) : null}
        {v ? (
          <>
            <Decisions rows={v.decisions} now={now} desktop screen={screen} />
            {v.sections.length === 0 && v.decisions.length === 0 ? (
              <div className="rounded-lg border border-line bg-surface-raised py-6">
                <NoTasks />
              </div>
            ) : null}
            {v.sections.length ? (
              <div className="grid grid-cols-[repeat(auto-fit,minmax(440px,1fr))] items-start gap-6">
                <div className="flex min-w-0 flex-col gap-6">
                  {late ? <Section id="late" tasks={late.tasks} now={now} desktop screen={screen} withDecision={v.withDecision} /> : null}
                  {today ? <Section id="today" tasks={today.tasks} now={now} desktop screen={screen} withDecision={v.withDecision} /> : null}
                  {!late && !today ? <p className="m-0 rounded-lg border border-dashed border-line-strong px-5 py-6 text-center text-body-sm text-ink-muted">لا مهام اليوم ولا متأخرة.</p> : null}
                </div>
                <div className="flex min-w-0 flex-col gap-6">
                  {next ? (
                    <Section id="next" tasks={next.tasks} now={now} desktop screen={screen} withDecision={v.withDecision} />
                  ) : (
                    <p className="m-0 rounded-lg border border-dashed border-line-strong px-5 py-6 text-center text-body-sm text-ink-muted">لا مهام قادمة.</p>
                  )}
                </div>
              </div>
            ) : null}
          </>
        ) : null}
      </main>
    </>
  );
}
