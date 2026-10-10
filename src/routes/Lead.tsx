import type { ReactNode } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { AppShell } from '@/components/app/AppShell';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { OfflineBanner } from '@/components/app/OfflineBanner';
import { Skeleton } from '@/components/app/Skeleton';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Icon } from '@/components/ui/Icon';
import { nextTask, useLead, type LeadData } from '@/data/lead';
import { useRealtimeRefresh } from '@/data/realtime';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import { buildTimeline } from '@/lib/timeline';
import { DeskHeaderStub, DeskLeadHeader, MobileHeader, MobileHeaderStub } from '@/features/lead/Header';
import { LeadTabs } from '@/features/lead/LeadTabs';
import { NextStep } from '@/features/lead/NextStep';
import { ContactCard, ScoreCard, ServicesCard } from '@/features/lead/Overview';
import { MessagesPanel, QuotesPanel, TasksPanel, TimelinePanel } from '@/features/lead/Panels';
import { canMarkReplied, leadTabs, tabFromParam, weaknessTexts, type LeadTab } from '@/features/lead/text';
import { useLeadActions } from '@/features/lead/useLeadActions';

/** Screen 6 · صفحة العميل — design/screens/Lead.dc.html (390, full screen) and DeskLead.dc.html (two columns). */
export default function LeadRoute() {
  const { id = '' } = useParams();
  const desktop = useIsDesktop();
  const q = useLead(id);
  // the desktop shell keeps the page live; the full-screen mobile page does it itself
  useRealtimeRefresh(!desktop);

  let body: ReactNode;
  if (q.data) {
    // relative times («اليوم»، «بعد 3 أيام») are read at the last fetch, so a refresh keeps them right
    body = <LeadScreen key={q.data.lead.id} data={q.data} desktop={desktop} now={new Date(q.dataUpdatedAt)} />;
  } else if (q.isError) {
    body = (
      <Frame desktop={desktop} header={desktop ? <DeskHeaderStub title="العميل" /> : <MobileHeaderStub loading={false} />}>
        <ErrorRetry
          title="تعذّر تحميل العميل"
          onRetry={() => {
            void q.refetch();
          }}
        />
      </Frame>
    );
  } else if (q.isPending) {
    body = (
      <Frame desktop={desktop} header={desktop ? <DeskHeaderStub title={null} /> : <MobileHeaderStub loading />}>
        <PageSkeleton desktop={desktop} />
      </Frame>
    );
  } else {
    body = (
      <Frame desktop={desktop} header={desktop ? <DeskHeaderStub title="العميل" /> : <MobileHeaderStub loading={false} />}>
        <EmptyState
          icon="store"
          title="لم نجد هذا العميل"
          message="ربما حُذف أو الرابط ناقص. ارجع للعملاء واختره من القائمة."
          action={
            <Link to="/leads" className="md-btn md-btn-secondary no-underline">
              ارجع للعملاء
            </Link>
          }
        />
      </Frame>
    );
  }

  if (desktop) return <AppShell section="leads">{body}</AppShell>;
  return (
    <div className="mx-auto flex min-h-dvh max-w-[560px] flex-col bg-surface text-ink">
      <OfflineBanner />
      {body}
    </div>
  );
}

function Frame({ desktop, header, children }: { desktop: boolean; header: ReactNode; children: ReactNode }) {
  return (
    <>
      {header}
      <main className={desktop ? 'flex w-full max-w-[1240px] flex-col gap-6 px-10 pb-12 pt-[28px]' : 'flex grow flex-col gap-4 p-4'}>{children}</main>
    </>
  );
}

function PageSkeleton({ desktop }: { desktop: boolean }) {
  if (desktop) {
    return (
      <div className="flex flex-wrap items-start gap-6" aria-busy="true" aria-label="جارٍ التحميل">
        <div className="flex min-w-0 flex-[1_1_360px] flex-col gap-5">
          <Skeleton className="h-[150px] rounded-lg" />
          <Skeleton className="h-[170px] rounded-lg" />
          <Skeleton className="h-[120px] rounded-lg" />
        </div>
        <Skeleton className="h-[480px] min-w-0 flex-[1.4_1_480px] rounded-lg" />
      </div>
    );
  }
  return (
    <div className="flex flex-col gap-4" aria-busy="true" aria-label="جارٍ التحميل">
      <Skeleton className="h-[190px] rounded-lg" />
      <Skeleton className="h-12" />
      <Skeleton className="h-[170px] rounded-lg" />
      <Skeleton className="h-[140px] rounded-lg" />
    </div>
  );
}

function LeadScreen({ data, desktop, now }: { data: LeadData; desktop: boolean; now: Date }) {
  const { lead } = data;
  const [params, setParams] = useSearchParams();
  const tab = tabFromParam(params.get('tab'), desktop);
  const act = useLeadActions(data, desktop);
  const next = nextTask(data.tasks);
  const weaknesses = weaknessTexts(data.weaknessIds, lead.checklist);

  const pickTab = (t: LeadTab) => {
    setParams(
      (p) => {
        const n = new URLSearchParams(p);
        n.set('tab', t);
        return n;
      },
      { replace: true },
    );
  };

  const nextStep = (
    <NextStep
      lead={lead}
      task={next}
      now={now}
      desktop={desktop}
      completing={next !== null && act.completing === next.id}
      onMeeting={() => {
        act.open('meeting');
      }}
      onComplete={act.completeTask}
      onPostpone={act.postpone}
      onChangeStage={() => {
        act.open('stage');
      }}
    />
  );

  let panel: ReactNode;
  if (tab === 'timeline') panel = <TimelinePanel events={buildTimeline(data)} now={now} desktop={desktop} />;
  else if (tab === 'messages') panel = <MessagesPanel leadId={lead.id} messages={data.messages} now={now} desktop={desktop} doNotContact={lead.doNotContact} />;
  else if (tab === 'tasks') panel = <TasksPanel tasks={data.tasks} now={now} desktop={desktop} completing={act.completing} onComplete={act.completeTask} onPostpone={act.postpone} />;
  else if (tab === 'quotes') panel = <QuotesPanel quotes={data.quotes} now={now} desktop={desktop} />;
  else
    panel = (
      <div className="flex flex-col gap-4 pt-4">
        <ScoreCard score={lead.score} weaknesses={weaknesses} desktop={false} />
        <ServicesCard services={data.services} expectedValue={lead.expectedValue} desktop={false} />
        <ContactCard lead={lead} desktop={false} />
        <div className={`grid gap-2 ${canMarkReplied(lead.stage) ? 'grid-cols-2' : 'grid-cols-1'}`}>
          {canMarkReplied(lead.stage) ? (
            <Button
              variant="secondary"
              icon="chat"
              block
              onClick={() => {
                act.open('replied');
              }}
            >
              العميل رد
            </Button>
          ) : null}
          <Link to={`/leads/${lead.id}/visit`} className="md-btn md-btn-secondary md-btn-block no-underline">
            <Icon name="store" />
            <span>زيارة جديدة</span>
          </Link>
        </div>
      </div>
    );

  const tabs = (
    <LeadTabs tabs={leadTabs(desktop)} active={tab} desktop={desktop} onChange={pickTab}>
      {panel}
    </LeadTabs>
  );

  if (desktop) {
    return (
      <>
        <DeskLeadHeader
          lead={lead}
          next={next}
          now={now}
          onStage={() => {
            act.open('stage');
          }}
          onMore={() => {
            act.open('menu');
          }}
        />
        <main className="flex w-full max-w-[1240px] flex-wrap items-start gap-6 px-10 pb-12 pt-[28px]">
          <div className="flex min-w-0 flex-[1_1_360px] flex-col gap-5">
            {nextStep}
            <ScoreCard score={lead.score} weaknesses={weaknesses} desktop />
            <ServicesCard services={data.services} expectedValue={lead.expectedValue} desktop />
            <ContactCard lead={lead} desktop />
          </div>
          <div className="flex min-w-0 flex-[1.4_1_480px] flex-col overflow-hidden rounded-lg border border-line bg-surface-raised">{tabs}</div>
        </main>
        {act.sheets}
      </>
    );
  }

  return (
    <>
      <MobileHeader
        lead={lead}
        next={next}
        now={now}
        onMore={() => {
          act.open('menu');
        }}
      />
      <main className="flex grow flex-col gap-4 p-4">
        {nextStep}
        <div className="flex flex-col">{tabs}</div>
      </main>
      {act.sheets}
    </>
  );
}
