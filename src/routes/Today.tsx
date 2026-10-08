import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AppShell } from '@/components/app/AppShell';
import { DeskHeader } from '@/components/app/DeskHeader';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { NavIcon } from '@/components/app/NavIcon';
import { PostponeSheet } from '@/components/app/PostponeSheet';
import { Skeleton } from '@/components/app/Skeleton';
import { useToast } from '@/components/app/toast/context';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { LeadCard } from '@/components/ui/LeadCard';
import { StageBadge } from '@/components/ui/StageBadge';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import { agoText, formatDayLong, formatTime, overdueText, weekday, whenText } from '@/lib/dates';
import { firstName, greeting } from '@/lib/format';
import { rowAction, TASK_KIND_LABEL, type TaskItem } from '@/data/tasks-meta';
import { goalRemainingText, useToday, type HotLead, type TodayData } from '@/data/today';
import { usePostponeTask } from '@/data/task-mutations';

/** Screen 2 · اليوم — design/screens/Main.dc.html and DeskToday.dc.html. */
export default function Today() {
  const desktop = useIsDesktop();
  const [now] = useState(() => new Date());
  const q = useToday(now);
  return (
    <AppShell section="today">
      {desktop ? <DeskToday q={q} now={now} /> : <MobileToday q={q} now={now} />}
    </AppShell>
  );
}

type Q = ReturnType<typeof useToday>;

function useTaskActions() {
  const navigate = useNavigate();
  return {
    openLead: (id: string) => {
      void navigate(`/leads/${id}`);
    },
    whatsapp: (t: TaskItem) => {
      void navigate(`/leads/${t.lead.id}/message?task=${t.id}`);
    },
    hotWhatsapp: (l: HotLead) => {
      void navigate(`/leads/${l.id}/message?kind=custom`);
    },
  };
}

function ActionButton({ t }: { t: TaskItem }) {
  const a = useTaskActions();
  const act = rowAction(t);
  if (act === 'whatsapp')
    return (
      <Button
        variant="whatsapp"
        size="sm"
        aria-label={`واتساب ${t.lead.businessName}`}
        onClick={() => {
          a.whatsapp(t);
        }}
      >
        واتساب
      </Button>
    );
  if (act === 'call' && t.lead.phone)
    return (
      <a className="md-btn md-btn-secondary md-btn-sm no-underline" href={`tel:+${t.lead.phone}`} aria-label={`اتصال ب${t.lead.businessName}`}>
        <svg className="md-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M6.6 3.5h3l1.5 4-2 1.3a11 11 0 0 0 6.1 6.1l1.3-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z" />
        </svg>
        <span>اتصال</span>
      </a>
    );
  return null;
}

function hotCard(l: HotLead, now: Date, a: ReturnType<typeof useTaskActions>) {
  return (
    <LeadCard
      key={l.id}
      businessName={l.businessName}
      activity={l.activity}
      contactName={l.contactName ?? undefined}
      stage={l.stage}
      score={l.score}
      priority="hot"
      lastContact={l.lastContactAt ? agoText(l.lastContactAt, now) : undefined}
      nextAction={l.nextTask?.title ?? 'حدد اجتماعاً'}
      nextActionAt={l.nextTask ? whenText(l.nextTask.dueAt, now) : 'لم يُتابَع بعد'}
      overdue={l.nextTask ? l.nextTask.dueAt.getTime() < now.getTime() : false}
      onClick={() => {
        a.openLead(l.id);
      }}
      onWhatsApp={
        l.phone && !l.doNotContact
          ? () => {
              a.hotWhatsapp(l);
            }
          : undefined
      }
    />
  );
}

function isEmpty(d: TodayData) {
  return d.overdue.length === 0 && d.today.length === 0 && d.hot.length === 0;
}

function goalPct(d: TodayData) {
  return Math.min(100, Math.round((d.weekVisits / Math.max(1, d.profile.weeklyGoal)) * 100));
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

function FieldEmpty() {
  return <EmptyState icon="pin" title="انزل للميدان" message="لا متابعات اليوم. سجّل محلاً جديداً من الزيارة." action={<NewLeadButton />} />;
}

/* ------------------------------------------------------------------ mobile */

function MobileToday({ q, now }: { q: Q; now: Date }) {
  const a = useTaskActions();
  const d = q.data;
  return (
    <main className="flex flex-col gap-6 px-4 pb-6 pt-[28px]">
      <header className="flex items-center justify-between gap-3">
        <div className="flex flex-col">
          <span className="text-body-sm text-ink-muted">{formatDayLong(now)}</span>
          <h1 className="m-0 text-title-1 font-bold">{d ? `${greeting(now)}، ${firstName(d.profile.fullName) || 'بك'}` : greeting(now)}</h1>
        </div>
        <Link to="/leads" aria-label="بحث في العملاء" className="grid size-touch place-items-center rounded-full border border-line bg-surface-raised text-ink">
          <NavIcon name="search" size={22} />
        </Link>
      </header>

      {q.isError && !d ? (
        <ErrorRetry
          title="تعذّر تحميل اليوم"
          onRetry={() => {
            void q.refetch();
          }}
        />
      ) : null}

      {!d && !q.isError ? <MobileSkeleton /> : null}

      {d ? (
        <>
          <section aria-label="أرقام اليوم" className="grid grid-cols-3 gap-2">
            <Counter n={d.visitsToday} label="زيارات اليوم" />
            <Counter n={d.sentToday} label="رسائل أُرسلت" />
            <Counter n={d.overdue.length + d.today.length} label="متابعات مستحقة" />
          </section>

          <section aria-label="هدف الأسبوع" className="flex flex-col gap-[10px] rounded-lg border border-line bg-surface-raised p-4">
            <div className="flex items-baseline justify-between">
              <span className="text-[15px] font-bold leading-[22px]">هدف الأسبوع</span>
              <span className="text-body-sm text-ink-muted">
                <b className="text-title-2 text-ink tabular-nums">{d.weekVisits}</b> {`من ${d.profile.weeklyGoal.toString()} زيارة`}
              </span>
            </div>
            <div className="h-[10px] overflow-hidden rounded-full bg-surface-sunken" role="progressbar" aria-valuemin={0} aria-valuemax={d.profile.weeklyGoal} aria-valuenow={d.weekVisits} aria-label="تقدم هدف الأسبوع">
              <div className="h-full rounded-full bg-action" style={{ width: `${goalPct(d).toString()}%` }} />
            </div>
            <span className="text-label-sm font-normal text-ink-muted">{goalRemainingText(d.weekVisits, d.profile.weeklyGoal, weekday(now))}</span>
          </section>

          {isEmpty(d) ? <FieldEmpty /> : null}

          {d.overdue.length ? (
            <section aria-labelledby="late" className="flex flex-col gap-[10px]">
              <h2 id="late" className="m-0 flex items-center gap-2 text-title-3 font-bold text-warning">
                <NavIcon name="alert" size={20} />
                {`متأخرة · ${d.overdue.length.toString()}`}
              </h2>
              {d.overdue.map((t) => (
                <div key={t.id} className="app-row flex items-center gap-3 rounded-lg border-[1.5px] border-warning bg-warning-soft px-4 py-[14px]">
                  <div className="flex min-w-0 grow flex-col">
                    <Link to={`/leads/${t.lead.id}`} className="app-stretched text-label font-bold text-ink no-underline">
                      {t.lead.businessName}
                    </Link>
                    <span className="text-body-sm text-ink">{t.title}</span>
                    <span className="text-label-sm text-warning">{overdueText(t.dueAt, now)}</span>
                  </div>
                  <span className="app-row-action">
                    <ActionButton t={t} />
                  </span>
                </div>
              ))}
            </section>
          ) : null}

          {d.today.length ? (
            <section aria-labelledby="today" className="flex flex-col gap-[10px]">
              <div className="flex items-center justify-between">
                <h2 id="today" className="m-0 text-title-3 font-bold">{`متابعات اليوم · ${d.today.length.toString()}`}</h2>
                <Link to="/tasks" className="flex min-h-touch items-center text-body-sm font-bold text-ink underline-offset-4">
                  كل المهام
                </Link>
              </div>
              <div className="flex flex-col overflow-hidden rounded-lg border border-line bg-surface-raised">
                {d.today.map((t, i) => (
                  <div key={t.id} className={`app-row flex items-center gap-3 px-4 py-[14px] ${i < d.today.length - 1 ? 'border-b border-line' : ''}`}>
                    <div className="flex min-w-0 grow flex-col gap-1">
                      <div className="flex min-w-0 items-center gap-2">
                        <Link to={`/leads/${t.lead.id}`} className="app-stretched truncate text-label font-bold text-ink no-underline">
                          {t.lead.businessName}
                        </Link>
                        <span className="shrink-0 whitespace-nowrap text-caption text-ink-muted">{t.kind === 'custom' ? t.title : TASK_KIND_LABEL[t.kind]}</span>
                      </div>
                      <span className="truncate text-body-sm text-ink-muted">{t.preview ?? t.title}</span>
                    </div>
                    <span className="app-row-action">
                      <ActionButton t={t} />
                    </span>
                  </div>
                ))}
              </div>
            </section>
          ) : null}

          {d.hot.length ? (
            <section aria-labelledby="hot" className="flex flex-col gap-[10px]">
              <h2 id="hot" className="m-0 text-title-3 font-bold">عملاء حارّون</h2>
              {d.hot.map((l) => hotCard(l, now, a))}
            </section>
          ) : null}
        </>
      ) : null}
    </main>
  );
}

function Counter({ n, label }: { n: number; label: string }) {
  return (
    <div className="flex flex-col gap-[2px] rounded-lg border border-line bg-surface-raised px-3 py-[14px]">
      <span className="text-display font-bold tabular-nums">{n}</span>
      <span className="text-label-sm font-normal text-ink-muted">{label}</span>
    </div>
  );
}

function MobileSkeleton() {
  return (
    <div className="flex flex-col gap-6" aria-busy="true" aria-label="جارٍ التحميل">
      <div className="grid grid-cols-3 gap-2">
        <Skeleton className="h-[90px] rounded-lg" />
        <Skeleton className="h-[90px] rounded-lg" />
        <Skeleton className="h-[90px] rounded-lg" />
      </div>
      <Skeleton className="h-[100px] rounded-lg" />
      <Skeleton className="h-6 w-40" />
      <Skeleton className="h-[220px] rounded-lg" />
    </div>
  );
}

/* ----------------------------------------------------------------- desktop */

function DeskToday({ q, now }: { q: Q; now: Date }) {
  const a = useTaskActions();
  const toast = useToast();
  const postpone = usePostponeTask();
  const [postponing, setPostponing] = useState<TaskItem | null>(null);
  const d = q.data;
  const due = d ? d.overdue.length + d.today.length : 0;
  return (
    <>
      <DeskHeader
        title={d ? `${greeting(now)}، ${firstName(d.profile.fullName) || 'بك'}` : greeting(now)}
        subtitle={`${formatDayLong(now)}${d ? ` · ${due ? `${due.toString()} ${due === 1 ? 'متابعة تنتظرك' : due === 2 ? 'متابعتان تنتظرانك' : 'متابعات تنتظرك'}` : 'لا متابعات اليوم'}` : ''}`}
      />
      <main className="flex w-full max-w-[1200px] flex-col gap-[28px] px-10 pb-12 pt-8">
        {q.isError && !d ? (
          <ErrorRetry
            title="تعذّر تحميل اليوم"
            onRetry={() => {
              void q.refetch();
            }}
          />
        ) : null}
        {!d && !q.isError ? <DeskSkeleton /> : null}
        {d ? (
          <>
            <section aria-label="أرقام اليوم" className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
              <Kpi label="زيارات اليوم" n={d.visitsToday} sub={d.lastVisit ? `آخرها ${d.lastVisit.businessName} ${formatTime(d.lastVisit.at)}` : 'لا زيارات بعد'} />
              <Kpi
                label="رسائل أُرسلت"
                n={d.sentToday}
                sub={d.unsentDrafts === 0 ? 'كل الرسائل أُرسلت' : d.unsentDrafts === 1 ? 'رسالة واحدة لم تُرسل بعد' : `${d.unsentDrafts.toString()} رسائل لم تُرسل بعد`}
              />
              <Kpi label="متابعات مستحقة" n={due} sub={d.overdue.length ? `منها ${d.overdue.length.toString()} متأخرة` : 'لا متأخرة'} warn={d.overdue.length > 0} />
              <div className="flex flex-col gap-2 rounded-lg border border-line bg-surface-raised p-5">
                <span className="text-body-sm text-ink-muted">هدف الأسبوع</span>
                <span className="text-body-sm text-ink-muted">
                  <b className="text-display text-ink tabular-nums">{d.weekVisits}</b> {`من ${d.profile.weeklyGoal.toString()} زيارة`}
                </span>
                <div className="h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" aria-valuemin={0} aria-valuemax={d.profile.weeklyGoal} aria-valuenow={d.weekVisits} aria-label="تقدم هدف الأسبوع">
                  <div className="h-full rounded-full bg-action" style={{ width: `${goalPct(d).toString()}%` }} />
                </div>
              </div>
            </section>

            {isEmpty(d) ? <FieldEmpty /> : null}

            <div className="flex flex-wrap items-start gap-6">
              <div className="flex min-w-0 flex-[2_1_520px] flex-col gap-6">
                {d.overdue.map((t) => (
                  <section key={t.id} aria-label={`متأخرة: ${t.title}`} className="flex flex-wrap items-center gap-4 rounded-lg border-[1.5px] border-warning bg-warning-soft px-5 py-4">
                    <span className="text-warning">
                      <NavIcon name="alert" size={24} />
                    </span>
                    <div className="flex flex-[1_1_240px] flex-col">
                      <h2 className="m-0 text-label font-bold">{`متأخرة: ${t.title} ل${t.lead.businessName}`}</h2>
                      <span className="text-body-sm font-bold text-warning">{`كان موعدها ${formatDayLong(t.dueAt)}`}</span>
                    </div>
                    <ActionButton t={t} />
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={() => {
                        setPostponing(t);
                      }}
                    >
                      أجّل
                    </Button>
                  </section>
                ))}

                {d.today.length ? (
                  <section aria-labelledby="d-today" className="flex flex-col overflow-hidden rounded-lg border border-line bg-surface-raised">
                    <div className="flex items-center justify-between border-b border-line px-5 py-4">
                      <h2 id="d-today" className="m-0 text-title-3 font-bold">{`متابعات اليوم · ${d.today.length.toString()}`}</h2>
                      <Link to="/tasks" className="text-body-sm font-bold text-ink underline-offset-4">
                        كل المهام
                      </Link>
                    </div>
                    <div className="overflow-x-auto">
                      <table className="w-full min-w-[560px] border-collapse text-body-sm">
                        <thead>
                          <tr className="text-start text-label-sm text-ink-muted">
                            <th scope="col" className="px-5 py-[10px] text-start font-bold">المحل</th>
                            <th scope="col" className="px-3 py-[10px] text-start font-bold">المرحلة</th>
                            <th scope="col" className="px-3 py-[10px] text-start font-bold">المتابعة</th>
                            <th scope="col" className="px-3 py-[10px] text-start font-bold">الموعد</th>
                            <th scope="col" className="px-5 py-[10px]">
                              <span className="sr-only">إجراء</span>
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          {d.today.map((t) => (
                            <tr key={t.id} className="border-t border-line">
                              <td className="px-5 py-3">
                                <Link to={`/leads/${t.lead.id}`} className="text-[15px] font-bold text-ink no-underline">
                                  {t.lead.businessName}
                                </Link>
                                <div className="max-w-[200px] truncate text-label-sm font-normal text-ink-muted">{t.preview ?? '—'}</div>
                              </td>
                              <td className="whitespace-nowrap p-3">
                                <StageBadge stage={t.lead.stage} size="sm" />
                              </td>
                              <td className="max-w-[120px] p-3 text-ink">{t.title}</td>
                              <td className="whitespace-nowrap p-3 text-ink-muted tabular-nums">{formatTime(t.dueAt)}</td>
                              <td className="whitespace-nowrap px-5 py-3 text-end">
                                <ActionButton t={t} />
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </section>
                ) : null}
              </div>

              <div className="flex min-w-0 flex-[1_1_320px] flex-col gap-6">
                {d.hot.length ? (
                  <section aria-labelledby="d-hot" className="flex flex-col gap-3">
                    <h2 id="d-hot" className="m-0 text-title-3 font-bold">عملاء حارّون</h2>
                    {d.hot.map((l) => hotCard(l, now, a))}
                  </section>
                ) : null}
                <WeekFunnel d={d} />
              </div>
            </div>
          </>
        ) : null}
      </main>
      {postponing ? (
        <PostponeSheet
          open
          due={postponing.dueAt}
          onClose={() => {
            setPostponing(null);
          }}
          onConfirm={(to) => {
            const task = postponing;
            setPostponing(null);
            postpone.mutate(
              { id: task.id, dueAt: to },
              {
                onSuccess: () => toast.show({ tone: 'success', title: `أُجّلت المهمة إلى ${formatDayLong(to)}` }),
                onError: () =>
                  toast.show({
                    tone: 'error',
                    title: 'تعذّر تأجيل المهمة',
                    message: 'تأكد من الشبكة ثم أعد المحاولة.',
                    action: 'أعد المحاولة',
                    onAction: () => {
                      postpone.mutate({ id: task.id, dueAt: to });
                    },
                  }),
              },
            );
          }}
        />
      ) : null}
    </>
  );
}

function Kpi({ label, n, sub, warn = false }: { label: string; n: number; sub: string; warn?: boolean }) {
  return (
    <div className="flex flex-col gap-1 rounded-lg border border-line bg-surface-raised p-5">
      <span className="text-body-sm text-ink-muted">{label}</span>
      <span className="text-display font-bold tabular-nums">{n}</span>
      <span className={`text-label-sm ${warn ? 'font-bold text-warning' : 'font-normal text-ink-muted'}`}>{sub}</span>
    </div>
  );
}

const FUNNEL: { key: keyof TodayData['week']; label: string; bar: string }[] = [
  { key: 'visits', label: 'زيارات', bar: 'bg-stage-visited' },
  { key: 'sent', label: 'رسائل أُرسلت', bar: 'bg-stage-contacted' },
  { key: 'replies', label: 'ردود', bar: 'bg-stage-replied' },
  { key: 'meetings', label: 'اجتماعات', bar: 'bg-stage-meeting' },
  { key: 'won', label: 'إغلاق', bar: 'bg-stage-won' },
];

function WeekFunnel({ d }: { d: TodayData }) {
  const max = Math.max(1, ...FUNNEL.map((f) => d.week[f.key]));
  return (
    <section aria-labelledby="d-week" className="flex flex-col gap-[14px] rounded-lg border border-line bg-surface-raised p-5">
      <h2 id="d-week" className="m-0 text-title-3 font-bold">هذا الأسبوع</h2>
      {FUNNEL.map((f) => (
        <div key={f.key} className="flex flex-col gap-1">
          <div className="flex justify-between text-body-sm">
            <span className="text-ink-muted">{f.label}</span>
            <b className="tabular-nums">{d.week[f.key]}</b>
          </div>
          <div className="h-2 rounded-full bg-surface-sunken">
            <div className={`h-full rounded-full ${f.bar}`} style={{ width: `${Math.round((d.week[f.key] / max) * 100).toString()}%` }} />
          </div>
        </div>
      ))}
      <span className="text-label-sm font-normal text-ink-muted">
        {d.firstReplyRate === null ? 'لم تُرسل رسائل أولى بعد' : `نسبة الرد على الرسالة الأولى ${d.firstReplyRate.toString()}%`}
      </span>
    </section>
  );
}

function DeskSkeleton() {
  return (
    <div className="flex flex-col gap-[28px]" aria-busy="true" aria-label="جارٍ التحميل">
      <div className="grid grid-cols-4 gap-4">
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-[132px] rounded-lg" />
        ))}
      </div>
      <div className="flex gap-6">
        <Skeleton className="h-[320px] flex-[2] rounded-lg" />
        <Skeleton className="h-[320px] flex-1 rounded-lg" />
      </div>
    </div>
  );
}

