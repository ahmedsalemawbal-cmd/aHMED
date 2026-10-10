import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { FlowFrame } from '@/components/app/FlowFrame';
import { Segmented } from '@/components/app/Segmented';
import { Skeleton } from '@/components/app/Skeleton';
import { StickyFooter } from '@/components/app/StickyFooter';
import { useToast } from '@/components/app/toast/context';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { StageBadge } from '@/components/ui/StageBadge';
import {
  generateMessage,
  saveDraft,
  undoSent,
  useConfirmSent,
  useMessageContext,
  useRecordConsent,
  type Drafts,
  type MessageContext,
  type SentResult,
  type Tone,
} from '@/data/messages';
import { followupSchedule } from '@/lib/followups';
import { lineCount, waLink, waLinkTooLong } from '@/lib/whatsapp';
import { SentConfirmSheet } from './SentConfirmSheet';
import { confirmIntro, contactParts, counterText, firstMovesStage, isOverLimit, sentToast, weaknessesSentence } from './text';
import { clearPending, hasPending, setPending, useReturnPrompt } from './useReturnPrompt';

const TONE_OPTIONS: { value: Tone; label: string }[] = [
  { value: 'friendly', label: 'ودّي' },
  { value: 'formal', label: 'رسمي' },
  { value: 'short', label: 'مختصر' },
];

const P = {
  back: 'M9 6l6 6-6 6',
  sparkle: 'M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8L12 3zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15z',
  alert: 'M12 8v5M12 16.5v.5M10.3 3.9L2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
  regen: 'M20 12a8 8 0 1 1-2.3-5.7M20 4v4h-4',
  copy: 'M9 9h10v11H9zM5 15V4h10',
  chat: 'M4 19.5l1.4-3.6A8 8 0 1 1 8.2 18.7L4 19.5z',
};

function Svg({ d, size = 20, className }: { d: string; size?: number; className?: string }) {
  return (
    <svg className={className} width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={d} />
    </svg>
  );
}

/** Back to where the user came from, or to a sensible page when opened directly. */
function useGoBack() {
  const navigate = useNavigate();
  return useCallback(
    (fallback: string) => {
      const idx = (window.history.state as { idx?: number } | null)?.idx ?? 0;
      if (idx > 0) void navigate(-1);
      else void navigate(fallback, { replace: true });
    },
    [navigate],
  );
}

function Frame({ header, children, footer }: { header: ReactNode; children: ReactNode; footer?: ReactNode }) {
  return (
    <FlowFrame section="leads">
      {header}
      <main className="flex grow flex-col gap-4 p-4 desk:px-6 desk:py-5">{children}</main>
      {footer}
    </FlowFrame>
  );
}

function Header({ onBack, title, sub, badge }: { onBack: () => void; title: string | null; sub?: ReactNode; badge?: ReactNode }) {
  return (
    <header className="sticky top-0 z-20 flex items-center gap-1 border-b border-line bg-surface-raised px-4 pb-3 pt-4">
      <button type="button" onClick={onBack} aria-label="رجوع" className="-ms-3 grid size-touch flex-none place-items-center border-0 bg-transparent text-ink">
        <Svg d={P.back} size={24} />
      </button>
      <div className="flex min-w-0 grow flex-col">
        {title === null ? <Skeleton className="h-[26px] w-40" /> : <h1 className="m-0 truncate text-title-3 font-bold">{title}</h1>}
        {sub ? <span className="truncate text-body-sm leading-5 text-ink-muted">{sub}</span> : null}
      </div>
      {badge}
    </header>
  );
}

/** Screen 4 · الرسالة — design/screens/Message.dc.html + SentConfirm.dc.html */
export default function MessageRoute() {
  const { id = '' } = useParams();
  const [params] = useSearchParams();
  const taskId = params.get('task');
  const kindParam = params.get('kind');
  const q = useMessageContext(id, taskId, kindParam);
  const goBack = useGoBack();
  const back = () => {
    goBack('/');
  };

  if (q.isError) {
    return (
      <Frame header={<Header onBack={back} title="الرسالة" />}>
        <ErrorRetry
          title="تعذّر تحميل بيانات العميل"
          onRetry={() => {
            void q.refetch();
          }}
        />
      </Frame>
    );
  }
  if (q.isPending) {
    return (
      <Frame header={<Header onBack={back} title={null} />}>
        <div className="flex flex-col gap-4" aria-busy="true" aria-label="جارٍ التحميل">
          <Skeleton className="h-[68px]" />
          <Skeleton className="h-[82px]" />
          <Skeleton className="h-[280px]" />
        </div>
      </Frame>
    );
  }
  if (!q.data) {
    return (
      <Frame header={<Header onBack={back} title="الرسالة" />}>
        <EmptyState
          icon="store"
          title="لم نجد هذا العميل"
          message="ربما حُذف أو الرابط ناقص. ارجع لليوم واختر العميل من القائمة."
          action={
            <Link to="/" className="md-btn md-btn-secondary no-underline">
              ارجع لليوم
            </Link>
          }
        />
      </Frame>
    );
  }
  return <MessageScreen key={`${q.data.lead.id}:${q.data.kind}:${q.data.task?.id ?? ''}`} ctx={q.data} />;
}

interface Editor {
  tone: Tone | null;
  body: string;
  /** all three tones when they came from the function in this visit */
  drafts: Drafts | null;
  generatedBy: 'ai' | 'fallback' | 'manual';
  observation: string;
  weaknesses: string[];
}

function greeting(ctx: MessageContext): string {
  return ctx.lead.contactName ? `السلام عليكم ${ctx.lead.contactName}،\n` : 'السلام عليكم،\n';
}

function initialEditor(ctx: MessageContext): Editor {
  const base = { drafts: null, observation: ctx.observation, weaknesses: ctx.weaknesses };
  if (ctx.draft) return { ...base, tone: ctx.kind === 'custom' ? null : ctx.draft.tone, body: ctx.draft.body, generatedBy: ctx.draft.generatedBy };
  if (ctx.kind === 'custom') return { ...base, tone: null, body: greeting(ctx), generatedBy: 'manual' };
  return { ...base, tone: ctx.defaultTone, body: '', generatedBy: 'fallback' };
}

function needsGeneration(ctx: MessageContext): boolean {
  return !ctx.lead.doNotContact && !ctx.draft && ctx.kind !== 'custom';
}

function MessageScreen({ ctx }: { ctx: MessageContext }) {
  const navigate = useNavigate();
  const goBack = useGoBack();
  const toast = useToast();
  const qc = useQueryClient();
  const confirm = useConfirmSent();
  const consent = useRecordConsent();
  const { lead } = ctx;

  const [ed, setEd] = useState<Editor>(() => initialEditor(ctx));
  const [generating, setGenerating] = useState(() => needsGeneration(ctx));
  const [consented, setConsented] = useState(lead.waConsent);
  /** when the sheet opened: the follow-up dates shown are the ones created */
  const [sheetAt, setSheetAt] = useState<Date | null>(() => (!lead.doNotContact && hasPending(lead.id) ? new Date() : null));
  const messageId = useRef<string | null>(ctx.draft?.id ?? null);
  const saved = useRef<{ body: string; tone: Tone | null }>({ body: ctx.draft?.body ?? '', tone: ctx.draft?.tone ?? null });
  const started = useRef(false);

  const generate = useCallback(
    async (tone: Tone) => {
      setGenerating(true);
      try {
        const g = await generateMessage(ctx, tone, { messageId: messageId.current });
        messageId.current = g.messageId ?? messageId.current;
        saved.current = { body: g.drafts[g.tone], tone: g.tone };
        setEd({ tone: g.tone, body: g.drafts[g.tone], drafts: g.drafts, generatedBy: g.generatedBy, observation: g.observation, weaknesses: g.weaknesses });
      } finally {
        setGenerating(false);
      }
    },
    [ctx],
  );

  useEffect(() => {
    if (started.current || !needsGeneration(ctx)) return;
    started.current = true;
    void generate(ctx.defaultTone);
  }, [ctx, generate]);

  /** Keeps the edited text as the open draft (Today shows it; «ليس بعد» resumes it). */
  const persist = useCallback(
    async (body: string, tone: Tone | null): Promise<string | null> => {
      if (!body.trim()) return messageId.current;
      if (messageId.current && body === saved.current.body && tone === saved.current.tone) return messageId.current;
      if (!messageId.current && ctx.kind === 'custom' && body === greeting(ctx)) return null;
      try {
        messageId.current = await saveDraft(ctx, { messageId: messageId.current, body, tone, generatedBy: ed.generatedBy });
        saved.current = { body, tone };
      } catch {
        // kept on screen; saved again before confirming
      }
      return messageId.current;
    },
    [ctx, ed.generatedBy],
  );

  const armReturn = useReturnPrompt(() => {
    setSheetAt(new Date());
  });

  const pickTone = (tone: Tone) => {
    if (ed.drafts) {
      const body = ed.drafts[tone];
      setEd({ ...ed, tone, body });
      void persist(body, tone);
    } else {
      void generate(tone);
    }
  };

  const lines = lineCount(ed.body);
  const over = isOverLimit(lines, ctx.kind);
  const empty = !ed.body.trim();
  const canWhatsApp = Boolean(lead.phone) && consented && !lead.doNotContact;
  const longLink = canWhatsApp && lead.phone ? waLinkTooLong(lead.phone, ed.body) : false;
  const contact = contactParts(lead);
  const followups = sheetAt && ctx.kind === 'first' ? followupSchedule(sheetAt, { bestTime: lead.bestTime, doNotContact: lead.doNotContact }) : [];

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(ed.body);
    } catch {
      toast.show({ tone: 'error', title: 'تعذّر النسخ', message: 'حدّد النص في المربع وانسخه يدوياً.' });
      return;
    }
    toast.show({ tone: 'success', title: 'نُسخت الرسالة', message: 'الصقها في واتساب ثم ارجع هنا لتأكيد الإرسال.' });
    void persist(ed.body, ed.tone);
    setPending(lead.id);
    armReturn();
  };

  const openWhatsApp = () => {
    void persist(ed.body, ed.tone);
    setPending(lead.id);
    armReturn(2500);
  };

  const notYet = () => {
    clearPending();
    setSheetAt(null);
  };

  const yes = async () => {
    const at = sheetAt ?? new Date();
    const fu = ctx.kind === 'first' ? followupSchedule(at, { bestTime: lead.bestTime, doNotContact: lead.doNotContact }) : [];
    let result: SentResult;
    try {
      const id = (await persist(ed.body, ed.tone)) ?? (await saveDraft(ctx, { messageId: null, body: ed.body, tone: ed.tone, generatedBy: ed.generatedBy }));
      messageId.current = id;
      result = await confirm.mutateAsync({ messageId: id, body: ed.body, tone: ed.tone, followups: fu });
    } catch {
      toast.show({ tone: 'error', title: 'تعذّر تسجيل الإرسال', message: 'تأكد من الشبكة ثم اضغط «نعم، أرسلتها» مرة أخرى.' });
      return;
    }
    clearPending();
    setSheetAt(null);
    if (result.alreadySent) {
      toast.show({ tone: 'info', title: 'الرسالة مسجّلة كمرسلة من قبل' });
    } else {
      const t = sentToast({
        movedStage: result.stageBefore ? firstMovesStage(ctx.kind, result.stageBefore) : false,
        followups: result.createdTaskIds.length,
        taskTitle: ctx.task?.title ?? null,
      });
      toast.show({
        tone: 'success',
        ...t,
        action: 'تراجع',
        onAction: () => {
          // the screen is gone by now: plain call, then refresh everything
          undoSent(result)
            .then(() => {
              toast.show({ tone: 'info', title: 'تراجعنا عن الإرسال', message: 'عادت الرسالة مسودة والمرحلة كما كانت.' });
            })
            .catch(() => {
              toast.show({ tone: 'error', title: 'تعذّر التراجع', message: 'تأكد من الشبكة ثم غيّر المرحلة من صفحة العميل.' });
            })
            .finally(() => {
              void qc.invalidateQueries();
            });
        },
      });
    }
    if (ctx.kind === 'first') void navigate(`/leads/${lead.id}`, { replace: true });
    else goBack(`/leads/${lead.id}`);
  };

  const recordConsent = async () => {
    try {
      await consent.mutateAsync(lead.id);
    } catch {
      toast.show({ tone: 'error', title: 'تعذّر حفظ الموافقة', message: 'تأكد من الشبكة ثم أعد المحاولة.' });
      return;
    }
    setConsented(true);
    toast.show({ tone: 'success', title: 'سُجّلت موافقته', message: 'زر الواتساب جاهز.' });
  };

  const header = (
    <Header
      onBack={() => {
        void persist(ed.body, ed.tone);
        goBack(`/leads/${lead.id}`);
      }}
      title={lead.businessName}
      sub={
        contact.text || contact.phone ? (
          <>
            {contact.text}
            {contact.text && contact.phone ? ' · ' : null}
            {contact.phone ? <span dir="ltr">{contact.phone}</span> : null}
          </>
        ) : undefined
      }
      badge={<StageBadge stage={lead.stage} size="sm" />}
    />
  );

  if (lead.doNotContact) {
    return (
      <Frame header={header}>
        <div role="note" className="flex flex-col gap-1 rounded-md bg-danger-soft px-4 py-3 text-danger">
          <b className="text-[15px] leading-[22px]">طلب عدم التواصل</b>
          <span className="text-body-sm text-ink">لا نرسل لهذا المحل رسائل ولا ننشئ له متابعات.</span>
        </div>
        <Link to={`/leads/${lead.id}`} className="md-btn md-btn-secondary md-btn-block no-underline">
          افتح صفحة العميل
        </Link>
      </Frame>
    );
  }

  return (
    <Frame
      header={header}
      footer={
        <StickyFooter>
          <button type="button" className={`md-btn md-btn-secondary${canWhatsApp ? '' : ' grow'}`} aria-label="نسخ الرسالة" disabled={generating || empty} onClick={() => void copy()}>
            <Svg d={P.copy} />
            <span>نسخ</span>
          </button>
          {canWhatsApp && lead.phone && !generating && !empty ? (
            <a href={waLink(lead.phone, ed.body)} target="_blank" rel="noopener noreferrer" className="md-btn md-btn-whatsapp grow no-underline" onClick={openWhatsApp}>
              <Svg d={P.chat} />
              <span>إرسال عبر واتساب</span>
            </a>
          ) : canWhatsApp ? (
            <button type="button" className="md-btn md-btn-whatsapp grow" disabled>
              <Svg d={P.chat} />
              <span>إرسال عبر واتساب</span>
            </button>
          ) : null}
        </StickyFooter>
      }
    >
      {!lead.phone ? (
        <div role="note" className="flex items-start gap-[10px] rounded-md bg-warning-soft px-[14px] py-3 text-body-sm text-ink">
          <Svg d={P.alert} className="mt-px flex-none text-warning" />
          <span>لا يوجد رقم جوال لهذا المحل. انسخ الرسالة، أو أضف الرقم من صفحة العميل.</span>
        </div>
      ) : !consented ? (
        <div role="note" className="flex flex-col gap-3 rounded-md bg-warning-soft px-[14px] py-3 text-body-sm text-ink">
          <span className="flex items-start gap-[10px]">
            <Svg d={P.alert} className="mt-px flex-none text-warning" />
            <span>لم تُسجَّل موافقته على رسائل واتساب. اسأله أولاً، ثم سجّل موافقته ليظهر زر الإرسال.</span>
          </span>
          <Button variant="secondary" block loading={consent.isPending} onClick={() => void recordConsent()}>
            سجّلت موافقته
          </Button>
        </div>
      ) : null}

      <div className="flex items-start gap-[10px] rounded-md bg-surface-sunken px-[14px] py-3 text-body-sm text-ink-muted" aria-live="polite">
        {generating ? (
          <>
            <Svg d={P.sparkle} className="mt-px flex-none" />
            <span>جارٍ كتابة المسودة من ملاحظات الزيارة</span>
          </>
        ) : ed.generatedBy === 'ai' ? (
          <>
            <Svg d={P.sparkle} className="mt-px flex-none" />
            <span>
              {ed.observation ? (
                <>
                  مسودة مبنية على ملاحظتك: <b className="text-ink">{ed.observation}</b>
                </>
              ) : (
                'مسودة مبنية على تقييم الزيارة'
              )}
              {weaknessesSentence(ed.weaknesses)}
            </span>
          </>
        ) : ed.generatedBy === 'fallback' ? (
          <>
            <Svg d={P.alert} className="mt-px flex-none text-warning" />
            <span className="text-ink">تعذّر توليد الرسالة. استخدمنا القالب الجاهز بدلاً منها.</span>
          </>
        ) : (
          <>
            <Svg d={P.sparkle} className="mt-px flex-none" />
            <span>رسالة حرة بدون قالب. اكتبها بنفسك.</span>
          </>
        )}
      </div>

      {ctx.kind !== 'custom' ? (
        <div className="flex flex-col gap-2">
          <span className="text-[15px] font-bold leading-[22px]">النبرة</span>
          <Segmented label="النبرة" value={ed.tone} options={TONE_OPTIONS} onChange={pickTone} />
        </div>
      ) : null}

      <div className="flex flex-col gap-2">
        <label htmlFor="msg" className="text-[15px] font-bold leading-[22px]">
          نص الرسالة
        </label>
        {generating ? (
          <Skeleton className="h-[250px]" />
        ) : (
          <textarea
            id="msg"
            className="md-input min-h-[250px] resize-none px-4 py-[14px] text-[16px] leading-[27px]"
            rows={9}
            value={ed.body}
            aria-describedby="msg-count"
            aria-invalid={over || undefined}
            onChange={(e) => {
              setEd({ ...ed, body: e.target.value });
            }}
            onBlur={() => void persist(ed.body, ed.tone)}
          />
        )}
        <div className="flex items-center justify-between">
          <span id="msg-count" className={`text-label-sm tabular-nums ${over ? 'font-bold text-danger' : 'text-ink-muted'}`}>
            {counterText(lines, ctx.kind)}
            {over ? '. اختصرها' : ''}
          </span>
          {ctx.kind !== 'custom' ? (
            <button type="button" className="md-btn md-btn-ghost md-btn-sm px-2" disabled={generating} onClick={() => void generate(ed.tone ?? ctx.defaultTone)}>
              <Svg d={P.regen} size={18} />
              <span>أعد الصياغة</span>
            </button>
          ) : null}
        </div>
        {longLink ? <p className="m-0 text-label-sm text-warning">الرسالة طويلة وقد لا تصل كاملة عبر رابط واتساب. اختصرها أو استخدم «نسخ».</p> : null}
      </div>

      <SentConfirmSheet
        open={sheetAt !== null && !generating}
        intro={confirmIntro({ kind: ctx.kind, stage: lead.stage, businessName: lead.businessName, followups: followups.length, taskTitle: ctx.task?.title ?? null })}
        followups={followups.map((f) => ({ dueAt: f.dueAt, days: f.kind === 'followup_3' ? 3 : 7 }))}
        busy={confirm.isPending}
        onYes={() => void yes()}
        onNotYet={notYet}
      />
    </Frame>
  );
}
