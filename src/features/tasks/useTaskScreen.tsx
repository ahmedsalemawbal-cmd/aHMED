import { useState } from 'react';
import { PostponeSheet } from '@/components/app/PostponeSheet';
import { useToast } from '@/components/app/toast/context';
import { actionErrorMessage, useMarkLost } from '@/data/lead-actions';
import { useMoveTask, usePostponeLead, useToggleTask, type TaskRow } from '@/data/tasks';
import { LostSheet, type LostInput } from '@/features/lead/sheets/LostSheet';
import { lostToastText } from '@/features/leads/list-view';
import { addDays, formatDayLong, startOfDay } from '@/lib/dates';
import { RETRY_AFTER_DAYS } from '@/lib/followups';

/** «أعد المحاولة» after «أجّل 30 يوماً»: 30 days on, at 10:00 Riyadh (the contact hour when unknown). */
export function retryAfter30(now: Date): Date {
  return new Date(addDays(startOfDay(now), RETRY_AFTER_DAYS).getTime() + 10 * 3_600_000);
}

/**
 * Everything a task row can do, with its toasts: tick and untick (with
 * «تراجع»), «أجّل» through the sheet, and the day-7 decision (flow 2.4):
 * «أجّل 30 يوماً» or «نقل إلى خسارة» with «لم يرد» chosen.
 */
export function useTaskScreen(now: Date) {
  const toast = useToast();
  const toggle = useToggleTask(now);
  const move = useMoveTask(now);
  const postponeLead = usePostponeLead();
  const markLost = useMarkLost();
  const [postponing, setPostponing] = useState<TaskRow | null>(null);
  const [losing, setLosing] = useState<TaskRow | null>(null);
  const [deciding, setDeciding] = useState<string | null>(null);

  const setDone = (t: TaskRow, done: boolean) => {
    void toggle(t, done).then((r) => {
      if (!r.ok) {
        toast.show({
          tone: 'error',
          title: done ? 'تعذّر حفظ الإنجاز' : 'تعذّر إلغاء الإنجاز',
          message: actionErrorMessage(r.error),
          action: 'أعد المحاولة',
          onAction: () => {
            setDone(t, done);
          },
        });
        return;
      }
      if (done)
        toast.show({
          tone: 'success',
          title: 'أُنجزت المهمة',
          message: `${t.title} · ${t.lead.businessName}`,
          action: 'تراجع',
          onAction: () => {
            setDone({ ...t, doneAt: new Date() }, false);
          },
        });
    });
  };

  const postpone = (t: TaskRow, to: Date) => {
    void move(t, to).then((r) => {
      if (r.ok) {
        toast.show({ tone: 'success', title: `أُجّلت المهمة إلى ${formatDayLong(to)}`, message: `${t.title} · ${t.lead.businessName}` });
        return;
      }
      toast.show({
        tone: 'error',
        title: 'تعذّر تأجيل المهمة',
        message: actionErrorMessage(r.error),
        action: 'أعد المحاولة',
        onAction: () => {
          postpone(t, to);
        },
      });
    });
  };

  const postpone30 = (t: TaskRow) => {
    const at = retryAfter30(new Date());
    setDeciding(t.lead.id);
    postponeLead.mutate(
      { leadId: t.lead.id, retryAt: at },
      {
        onSuccess: (r) => {
          toast.show({ tone: 'success', title: `أُجّل ${t.lead.businessName} 30 يوماً`, message: `مهمة «أعد المحاولة» يوم ${formatDayLong(r.dueAt)}.` });
        },
        onError: (err) => {
          toast.show({
            tone: 'error',
            title: 'تعذّر التأجيل',
            message: actionErrorMessage(err),
            action: 'أعد المحاولة',
            onAction: () => {
              postpone30(t);
            },
          });
        },
        onSettled: () => {
          setDeciding(null);
        },
      },
    );
  };

  const saveLost = (t: TaskRow, input: LostInput) => {
    markLost.mutate(
      { leadIds: [t.lead.id], ...input },
      {
        onSuccess: () => {
          setLosing(null);
          toast.show({ tone: 'success', title: lostToastText([t.lead]) });
        },
        onError: (err) => {
          toast.show({ tone: 'error', title: 'تعذّر النقل إلى خسارة', message: actionErrorMessage(err) });
        },
      },
    );
  };

  const sheets = (
    <>
      {postponing ? (
        <PostponeSheet
          open
          due={postponing.dueAt}
          onClose={() => {
            setPostponing(null);
          }}
          onConfirm={(to) => {
            const t = postponing;
            setPostponing(null);
            postpone(t, to);
          }}
        />
      ) : null}
      {losing ? (
        <LostSheet
          open
          title={`نقل ${losing.lead.businessName} إلى خسارة`}
          initialReason="no_reply"
          busy={markLost.isPending}
          onClose={() => {
            setLosing(null);
          }}
          onSubmit={(input) => {
            saveLost(losing, input);
          }}
        />
      ) : null}
    </>
  );

  return {
    complete: (t: TaskRow) => {
      setDone(t, true);
    },
    reopen: (t: TaskRow) => {
      setDone(t, false);
    },
    askPostpone: setPostponing,
    postpone30,
    askLost: setLosing,
    /** the lead whose «أجّل 30 يوماً» is being saved */
    deciding: postponeLead.isPending ? deciding : null,
    sheets,
  };
}

export type TaskScreen = ReturnType<typeof useTaskScreen>;
