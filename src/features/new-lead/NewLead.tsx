import { useCallback, useEffect, useEffectEvent, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { FlowFrame } from '@/components/app/FlowFrame';
import { Skeleton } from '@/components/app/Skeleton';
import { useToast } from '@/components/app/toast/context';
import { Button } from '@/components/ui/Button';
import { ScoreBar } from '@/components/ui/ScoreBar';
import { useCatalog, useLeadIndex } from '@/data/catalog';
import { OfflineError, useCreateLead } from '@/data/create-lead';
import { useOnline } from '@/hooks/useOnline';
import { formatTime } from '@/lib/dates';
import { answeredCount } from '@/lib/scoring';
import { clearDraft, draftStatusText, emptyDraft, isStarted, loadDraft, saveDraft, type NewLeadDraft } from './draft';
import { buildPayload, derive } from './payload';
import { step1Problems } from './helpers';
import { Step1Details, type Step1Errors } from './Step1Details';
import { Step2Assessment } from './Step2Assessment';
import { Step3Notes } from './Step3Notes';
import { Step4Services } from './Step4Services';
import { StepLabels, WizardFooter, WizardHeader } from './WizardChrome';

const NEXT_LABEL = { 1: 'التالي: التقييم', 2: 'التالي: الملاحظات', 3: 'التالي: الخدمات', 4: 'احفظ وولّد الرسالة' } as const;

/** Screen 3 · عميل جديد — design/screens/NewLead1..4.dc.html */
export default function NewLead() {
  const navigate = useNavigate();
  const toast = useToast();
  const online = useOnline();
  const catalog = useCatalog();
  const index = useLeadIndex();
  const create = useCreateLead();

  const [restored] = useState(() => loadDraft());
  const [draft, setDraft] = useState<NewLeadDraft>(() => restored ?? emptyDraft(crypto.randomUUID()));
  const [media, setMedia] = useState<File[]>([]);
  const [errors, setErrors] = useState<Step1Errors & { key?: string }>({});
  const [waitingForNetwork, setWaitingForNetwork] = useState(false);
  const [, setTick] = useState(0);

  // tell the user we picked up where they left off
  useEffect(() => {
    if (restored && isStarted(restored)) {
      toast.show({
        tone: 'info',
        title: 'أكملنا من المسودة',
        action: 'ابدأ من جديد',
        onAction: () => {
          clearDraft();
          setDraft(emptyDraft(crypto.randomUUID()));
          setMedia([]);
        },
      });
    }
  }, [restored, toast]);

  // refresh «قبل لحظات» once a minute
  useEffect(() => {
    const t = setInterval(() => {
      setTick((n) => n + 1);
    }, 30_000);
    return () => {
      clearInterval(t);
    };
  }, []);

  const update = useCallback((patch: Partial<NewLeadDraft>) => {
    setDraft((d) => saveDraft({ ...d, ...patch }));
    setErrors({});
  }, []);

  const activities = useMemo(() => catalog.data?.activities ?? [], [catalog.data]);
  const services = useMemo(() => catalog.data?.services ?? [], [catalog.data]);
  const x = useMemo(() => derive(draft, activities, services), [draft, activities, services]);

  const goTo = (step: 1 | 2 | 3 | 4) => {
    update({ step });
    window.scrollTo({ top: 0 });
  };

  const validateStep1 = (): boolean => {
    const e: Step1Errors = {};
    if (!draft.businessName.trim()) e.businessName = 'اكتب اسم المحل.';
    if (!draft.activityTypeId) e.activity = 'اختر نوع النشاط.';
    const p = step1Problems(draft, index.data ?? []);
    if (!p.phone.ok) e.phone = p.phone.error;
    else if (p.phoneDup && !draft.allowDuplicate) e.phone = undefined; // shown by the field itself
    setErrors(e);
    return !e.businessName && !e.activity && !e.phone && !(p.phoneDup && !draft.allowDuplicate);
  };

  const save = async () => {
    const r = buildPayload(draft, x);
    if (!r.ok) {
      if (r.step === 1) setErrors({ businessName: r.error });
      else setErrors({ key: r.error });
      goTo(r.step);
      return;
    }
    try {
      const res = await create.mutateAsync({ payload: r.payload, media });
      setWaitingForNetwork(false);
      clearDraft();
      toast.show({
        tone: 'success',
        title: `حُفظ ${draft.businessName.trim()}`,
        message: res.mediaFailed ? `المرحلة: تمت الزيارة. تعذّر رفع ${res.mediaFailed.toString()} من الملفات، أعد رفعها من صفحة العميل.` : 'المرحلة: تمت الزيارة.',
      });
      void navigate(`/leads/${res.leadId}/message`, { replace: true });
    } catch (err) {
      if (err instanceof OfflineError) {
        setWaitingForNetwork(true);
        toast.show({ tone: 'info', title: 'بدون اتصال', message: 'المسودة محفوظة. سنحفظ العميل تلقائياً عند عودة الشبكة.' });
        return;
      }
      toast.show({
        tone: 'error',
        title: 'تعذّر حفظ العميل',
        message: 'المسودة محفوظة على جهازك. تأكد من الشبكة ثم أعد المحاولة.',
        action: 'أعد المحاولة',
        onAction: () => {
          void save();
        },
      });
    }
  };

  // deferred save: retry automatically when the network comes back
  const retrySave = useEffectEvent(() => {
    void save();
  });
  useEffect(() => {
    if (waitingForNetwork && online && !create.isPending) retrySave();
  }, [online, waitingForNetwork, create.isPending]);

  const next = () => {
    if (draft.step === 1) {
      if (validateStep1()) goTo(2);
    } else if (draft.step === 2) goTo(3);
    else if (draft.step === 3) {
      if (!draft.keyObservation.trim()) {
        setErrors({ key: 'اكتب الملاحظة الأبرز. سطر واحد محدد من الزيارة.' });
        return;
      }
      goTo(4);
    } else void save();
  };

  const back = () => {
    if (draft.step === 1) void navigate('/');
    else goTo((draft.step - 1) as 1 | 2 | 3);
  };

  const title = draft.step === 1 ? 'عميل جديد' : draft.step === 2 ? `تقييم ${draft.businessName.trim() || 'المحل'}` : draft.step === 3 ? 'ملاحظات الزيارة' : 'الخدمات المقترحة';
  const total = x.checklist.general.length;
  const answered = answeredCount(x.checklist.general, draft.answers);
  const unanswered = total - answered;

  return (
    <FlowFrame section="leads">
      <WizardHeader step={draft.step} title={title} onLead={back}>
        {draft.step === 1 ? <StepLabels step={1} /> : null}
        {draft.step === 2 ? <ScoreBar score={x.score} label={`الدرجة · ${answered.toString()} من ${total.toString()} بنود`} /> : null}
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
            <Skeleton className="h-[80px]" />
          </div>
        ) : draft.step === 1 ? (
          <Step1Details draft={draft} update={update} activities={activities} index={index.data ?? []} errors={errors} />
        ) : draft.step === 2 ? (
          <Step2Assessment
            checklist={x.checklist}
            activityName={x.activity?.name ?? ''}
            answers={draft.answers}
            onAnswer={(id, v) => {
              update({ answers: { ...draft.answers, [id]: v } });
            }}
          />
        ) : draft.step === 3 ? (
          <Step3Notes draft={draft} update={update} media={media} setMedia={setMedia} keyError={errors.key} />
        ) : (
          <Step4Services
            x={x}
            catalog={services}
            expectedValue={draft.expectedValue}
            onToggle={(id, sel) => {
              const current = Object.fromEntries(x.shown.map((s) => [s.service.id, s.selected]));
              update({ selected: { ...current, [id]: sel } });
            }}
            onAdd={(id) => {
              const current = Object.fromEntries(x.shown.map((s) => [s.service.id, s.selected]));
              update({ addedServiceIds: [...draft.addedServiceIds, id], selected: { ...current, [id]: true } });
            }}
            onExpected={(v) => {
              update({ expectedValue: v });
            }}
          />
        )}
      </main>

      <WizardFooter
        note={
          draft.step === 2 && unanswered > 0
            ? `${unanswered.toString()} من البنود بلا إجابة وتُحسب صفراً`
            : waitingForNetwork
              ? 'بدون اتصال. سنحفظ العميل عند عودة الشبكة.'
              : draftStatusText(draft.savedAt, new Date(), formatTime)
        }
      >
        {draft.step > 1 ? (
          <Button variant="secondary" onClick={back}>
            السابق
          </Button>
        ) : null}
        <Button variant="primary" className="grow" block={draft.step === 1} onClick={next} loading={create.isPending} disabled={!catalog.data}>
          {create.isPending ? 'جارٍ الحفظ' : NEXT_LABEL[draft.step]}
        </Button>
      </WizardFooter>
    </FlowFrame>
  );
}
