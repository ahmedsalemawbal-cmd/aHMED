import { useCallback, useEffect, useEffectEvent, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { FlowFrame } from '@/components/app/FlowFrame';
import { Skeleton } from '@/components/app/Skeleton';
import { useToast } from '@/components/app/toast/context';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ScoreBar } from '@/components/ui/ScoreBar';
import { useAddVisit, useLastAssessment } from '@/data/add-visit';
import { useCatalog } from '@/data/catalog';
import { OfflineError } from '@/data/create-lead';
import { useLead, type LeadData } from '@/data/lead';
import { useOnline } from '@/hooks/useOnline';
import { formatTime } from '@/lib/dates';
import { answeredCount } from '@/lib/scoring';
import { draftStatusText } from '../new-lead/draft';
import { Step2Assessment } from '../new-lead/Step2Assessment';
import { Step3Notes } from '../new-lead/Step3Notes';
import { Step4Services } from '../new-lead/Step4Services';
import { StepLabels, WizardFooter, WizardHeader } from '../new-lead/WizardChrome';
import { clearVisitDraft, isVisitStarted, loadVisitDraft, saveVisitDraft, type VisitDraft } from './draft';
import { buildVisitPayload, deriveVisit, startVisit, VISIT_STEPS } from './payload';

const NEXT_LABEL = { 1: 'التالي: الملاحظات', 2: 'التالي: الخدمات', 3: 'احفظ الزيارة' } as const;

function useGoBack() {
  const navigate = useNavigate();
  return (fallback: string) => {
    const idx = (window.history.state as { idx?: number } | null)?.idx ?? 0;
    if (idx > 0) void navigate(-1);
    else void navigate(fallback, { replace: true });
  };
}

/** «زيارة جديدة» for a registered lead (brief screen 6 and the list swipe): /leads/:id/visit */
export default function NewVisit() {
  const { id = '' } = useParams();
  const lead = useLead(id);
  const last = useLastAssessment(id, Boolean(id));
  const goBack = useGoBack();
  const header = (title: string) => (
    <WizardHeader
      step={1}
      steps={VISIT_STEPS}
      label="خطوات الزيارة"
      title={title}
      onLead={() => {
        goBack(`/leads/${id}`);
      }}
    />
  );

  if (lead.isError || last.isError) {
    return (
      <FlowFrame section="leads">
        {header('زيارة جديدة')}
        <main className="flex grow flex-col gap-4 p-4 desk:px-8 desk:py-6">
          <ErrorRetry
            title="تعذّر تحميل بيانات العميل"
            onRetry={() => {
              void lead.refetch();
              void last.refetch();
            }}
          />
        </main>
      </FlowFrame>
    );
  }
  if (lead.isPending || last.isPending) {
    return (
      <FlowFrame section="leads">
        {header('زيارة جديدة')}
        <main className="flex grow flex-col gap-5 p-4 desk:px-8 desk:py-6" aria-busy="true" aria-label="جارٍ التحميل">
          <Skeleton className="h-[80px]" />
          <Skeleton className="h-[160px]" />
          <Skeleton className="h-[160px]" />
        </main>
      </FlowFrame>
    );
  }
  if (!lead.data) {
    return (
      <FlowFrame section="leads">
        {header('زيارة جديدة')}
        <main className="flex grow flex-col p-4 desk:px-8 desk:py-6">
          <EmptyState
            icon="store"
            title="لم نجد هذا العميل"
            message="ربما حُذف أو الرابط ناقص. ارجع لقائمة العملاء واختره منها."
            action={
              <Link to="/leads" className="md-btn md-btn-secondary no-underline">
                ارجع للعملاء
              </Link>
            }
          />
        </main>
      </FlowFrame>
    );
  }
  return <VisitScreen key={lead.data.lead.id} data={lead.data} lastAnswers={last.data} />;
}

function VisitScreen({ data, lastAnswers }: { data: LeadData; lastAnswers: { answers: VisitDraft['answers'] } | null }) {
  const { lead } = data;
  const navigate = useNavigate();
  const goBack = useGoBack();
  const toast = useToast();
  const online = useOnline();
  const catalog = useCatalog();
  const save = useAddVisit();

  const [fresh] = useState(() => startVisit(crypto.randomUUID(), lead, data.services, lastAnswers));
  const [restored] = useState(() => loadVisitDraft(lead.id));
  const [draft, setDraft] = useState<VisitDraft>(() => restored ?? fresh);
  const [media, setMedia] = useState<File[]>([]);
  const [keyError, setKeyError] = useState<string | undefined>();
  const [waitingForNetwork, setWaitingForNetwork] = useState(false);
  const [, setTick] = useState(0);

  useEffect(() => {
    if (restored && isVisitStarted(restored, fresh)) {
      toast.show({
        tone: 'info',
        title: 'أكملنا من المسودة',
        action: 'ابدأ من جديد',
        onAction: () => {
          clearVisitDraft(lead.id);
          setDraft(fresh);
          setMedia([]);
        },
      });
    }
  }, [restored, fresh, lead.id, toast]);

  useEffect(() => {
    const t = setInterval(() => {
      setTick((n) => n + 1);
    }, 30_000);
    return () => {
      clearInterval(t);
    };
  }, []);

  const update = useCallback((patch: Partial<VisitDraft>) => {
    setDraft((d) => saveVisitDraft({ ...d, ...patch }));
    setKeyError(undefined);
  }, []);

  const activities = useMemo(() => catalog.data?.activities ?? [], [catalog.data]);
  const services = useMemo(() => catalog.data?.services ?? [], [catalog.data]);
  const x = useMemo(() => deriveVisit(draft, lead, activities, services), [draft, lead, activities, services]);

  const goTo = (step: 1 | 2 | 3) => {
    update({ step });
    window.scrollTo({ top: 0 });
  };

  const submit = async () => {
    const r = buildVisitPayload(draft, lead, x);
    if (!r.ok) {
      setKeyError(r.error);
      goTo(r.step);
      return;
    }
    try {
      const res = await save.mutateAsync({ payload: r.payload, media });
      setWaitingForNetwork(false);
      clearVisitDraft(lead.id);
      const score = res.score ?? x.score;
      toast.show({
        tone: 'success',
        title: 'سُجّلت الزيارة',
        message: res.mediaFailed
          ? `الدرجة الآن ${score.toString()}. تعذّر رفع ${res.mediaFailed.toString()} من الملفات، أعد رفعها لاحقاً.`
          : `الدرجة الآن ${score.toString()}.`,
      });
      void navigate(`/leads/${lead.id}`, { replace: true });
    } catch (err) {
      if (err instanceof OfflineError) {
        setWaitingForNetwork(true);
        toast.show({ tone: 'info', title: 'بدون اتصال', message: 'المسودة محفوظة. سنحفظ الزيارة تلقائياً عند عودة الشبكة.' });
        return;
      }
      toast.show({
        tone: 'error',
        title: 'تعذّر حفظ الزيارة',
        message: 'المسودة محفوظة على جهازك. تأكد من الشبكة ثم أعد المحاولة.',
        action: 'أعد المحاولة',
        onAction: () => {
          void submit();
        },
      });
    }
  };

  const retrySave = useEffectEvent(() => {
    void submit();
  });
  useEffect(() => {
    if (waitingForNetwork && online && !save.isPending) retrySave();
  }, [online, waitingForNetwork, save.isPending]);

  const next = () => {
    if (draft.step === 1) goTo(2);
    else if (draft.step === 2) {
      if (!draft.keyObservation.trim()) {
        setKeyError('اكتب الملاحظة الأبرز. سطر واحد محدد من الزيارة.');
        return;
      }
      goTo(3);
    } else void submit();
  };

  const back = () => {
    if (draft.step === 1) goBack(`/leads/${lead.id}`);
    else goTo((draft.step - 1) as 1 | 2);
  };

  const title = draft.step === 1 ? `زيارة ${lead.businessName}` : draft.step === 2 ? 'ملاحظات الزيارة' : 'الخدمات المقترحة';
  const total = x.checklist.general.length;
  const answered = answeredCount(x.checklist.general, draft.answers);
  const unanswered = total - answered;

  return (
    <FlowFrame section="leads">
      <WizardHeader step={draft.step} steps={VISIT_STEPS} label="خطوات الزيارة" title={title} onLead={back}>
        {draft.step === 1 ? (
          <>
            <StepLabels step={1} steps={VISIT_STEPS} />
            <ScoreBar score={x.score} label={`الدرجة · ${answered.toString()} من ${total.toString()} بنود`} />
          </>
        ) : null}
      </WizardHeader>

      <main key={draft.step} className="app-step-in flex grow flex-col px-4 pb-6 pt-5 desk:px-8 desk:pb-8 desk:pt-6">
        {catalog.isError ? (
          <ErrorRetry
            title="تعذّر تحميل الأنشطة والخدمات"
            onRetry={() => {
              void catalog.refetch();
            }}
          />
        ) : !catalog.data ? (
          <div className="flex flex-col gap-5" aria-busy="true" aria-label="جارٍ التحميل">
            <Skeleton className="h-[80px]" />
            <Skeleton className="h-[110px]" />
          </div>
        ) : draft.step === 1 ? (
          x.checklist.general.length ? (
            <Step2Assessment
              checklist={x.checklist}
              activityName={x.activity?.name ?? ''}
              answers={draft.answers}
              onAnswer={(itemId, v) => {
                update({ answers: { ...draft.answers, [itemId]: v } });
              }}
            />
          ) : (
            <EmptyState
              icon="store"
              title="لا بنود تقييم لهذا النشاط"
              message="اختر نشاط المحل من «تعديل البيانات» ثم ارجع للزيارة."
              action={
                <Link to={`/leads/${lead.id}/edit`} className="md-btn md-btn-secondary no-underline">
                  تعديل البيانات
                </Link>
              }
            />
          )
        ) : draft.step === 2 ? (
          <Step3Notes
            draft={draft}
            update={update}
            media={media}
            setMedia={setMedia}
            keyError={keyError}
            uploadNote="يُرفع الملف إلى مجلد خاص بك عند حفظ الزيارة، ولا يراه أحد غيرك."
          />
        ) : (
          <Step4Services
            x={x}
            catalog={services}
            expectedValue={draft.expectedValue}
            intro={`اخترناها من نقاط ضعف هذه الزيارة، وبقيت الخدمات المقترحة من قبل لـ${lead.businessName}. ألغِ أو أضف ما يناسب.`}
            onToggle={(serviceId, sel) => {
              const current = Object.fromEntries(x.shown.map((s) => [s.service.id, s.selected]));
              update({ selected: { ...current, [serviceId]: sel } });
            }}
            onAdd={(serviceId) => {
              const current = Object.fromEntries(x.shown.map((s) => [s.service.id, s.selected]));
              update({ addedServiceIds: [...draft.addedServiceIds, serviceId], selected: { ...current, [serviceId]: true } });
            }}
            onExpected={(v) => {
              update({ expectedValue: v });
            }}
          />
        )}
      </main>

      <WizardFooter
        note={
          draft.step === 1 && unanswered > 0
            ? `${unanswered.toString()} من البنود بلا إجابة وتُحسب صفراً`
            : waitingForNetwork
              ? 'بدون اتصال. سنحفظ الزيارة عند عودة الشبكة.'
              : draftStatusText(draft.savedAt, new Date(), formatTime)
        }
      >
        {draft.step > 1 ? (
          <Button variant="secondary" onClick={back}>
            السابق
          </Button>
        ) : null}
        <Button variant="primary" className="grow" block={draft.step === 1} onClick={next} loading={save.isPending} disabled={!catalog.data}>
          {save.isPending ? 'جارٍ الحفظ' : NEXT_LABEL[draft.step]}
        </Button>
      </WizardFooter>
    </FlowFrame>
  );
}
