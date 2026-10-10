import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PostponeSheet } from '@/components/app/PostponeSheet';
import { useToast } from '@/components/app/toast/context';
import type { Stage } from '@/components/ui/stages';
import type { LeadData, LeadTask } from '@/data/lead';
import { actionErrorMessage, DIRECT_STAGES, stageRoute, useMarkLost, useMarkReplied, useMarkWon, useSetMeeting, useSetStage } from '@/data/lead-actions';
import { useCompleteTask, usePostponeTask } from '@/data/task-mutations';
import { formatDayLong } from '@/lib/dates';
import { LostSheet, type LostInput } from './sheets/LostSheet';
import { MeetingSheet } from './sheets/MeetingSheet';
import { MenuSheet, type MenuItem } from './sheets/MenuSheet';
import { RepliedSheet } from './sheets/RepliedSheet';
import { StageSheet } from './sheets/StageSheet';
import { WonSheet } from './sheets/WonSheet';
import { canMarkReplied, hasOpenMeetingTask, lostToast, meetingToast, openFollowupCount, repliedIntro, repliedToast, stageToast, wonToast } from './text';

export type LeadSheet = 'menu' | 'stage' | 'replied' | 'meeting' | 'won' | 'lost';

/**
 * Every action of the lead page: which sheet is open, the RPCs behind them,
 * and the toasts. A failed action keeps its sheet open so it can be retried.
 */
export function useLeadActions(data: LeadData, desktop: boolean) {
  const { lead, tasks } = data;
  const navigate = useNavigate();
  const toast = useToast();
  const [sheet, setSheet] = useState<LeadSheet | null>(null);
  const [postponing, setPostponing] = useState<LeadTask | null>(null);
  const replied = useMarkReplied();
  const meeting = useSetMeeting();
  const won = useMarkWon();
  const lost = useMarkLost();
  const stage = useSetStage();
  const complete = useCompleteTask();
  const postpone = usePostponeTask();

  const close = () => {
    setSheet(null);
  };
  const fail = (title: string, err: unknown) => {
    toast.show({ tone: 'error', title, message: actionErrorMessage(err) });
  };

  const pickStage = async (to: Stage) => {
    const route = stageRoute(to);
    if (route === 'replied' && !canMarkReplied(lead.stage)) {
      toast.show({ tone: 'info', title: 'العميل في مرحلة مغلقة', message: 'انقله إلى «تم الإرسال» أولاً، ثم سجّل الرد.' });
      return;
    }
    if (route !== 'direct') {
      setSheet(route);
      return;
    }
    const direct = DIRECT_STAGES.find((s) => s === to);
    if (!direct) return;
    try {
      await stage.mutateAsync({ leadIds: [lead.id], stage: direct });
    } catch (err) {
      fail('تعذّر تغيير المرحلة', err);
      return;
    }
    setSheet(null);
    toast.show({ tone: 'success', title: stageToast(direct) });
  };

  const confirmReplied = async () => {
    try {
      const r = await replied.mutateAsync(lead.id);
      setSheet(null);
      toast.show({ tone: 'success', ...repliedToast(r) });
    } catch (err) {
      fail('تعذّر تسجيل الرد', err);
    }
  };

  const saveMeeting = async (input: { at: Date; title: string }) => {
    try {
      await meeting.mutateAsync({ leadId: lead.id, at: input.at, title: input.title });
    } catch (err) {
      fail('تعذّر حفظ الاجتماع', err);
      return;
    }
    setSheet(null);
    toast.show({ tone: 'success', ...meetingToast(input.at) });
  };

  const saveWon = async (input: { value: number; billing: 'monthly' | 'one_time' }) => {
    try {
      await won.mutateAsync({ leadId: lead.id, ...input });
    } catch (err) {
      fail('تعذّر حفظ الإغلاق', err);
      return;
    }
    setSheet(null);
    toast.show({ tone: 'success', ...wonToast(input.value, input.billing) });
  };

  const saveLost = async (input: LostInput) => {
    try {
      await lost.mutateAsync({ leadIds: [lead.id], ...input });
    } catch (err) {
      fail('تعذّر النقل إلى خسارة', err);
      return;
    }
    setSheet(null);
    toast.show({ tone: 'success', ...lostToast(input.retryAt) });
  };

  const completeTask = (t: LeadTask) => {
    complete.mutate(
      { id: t.id, done: true },
      {
        onSuccess: () =>
          toast.show({
            tone: 'success',
            title: 'أُنجزت المهمة',
            message: t.title,
            action: 'تراجع',
            onAction: () => {
              complete.mutate({ id: t.id, done: false });
            },
          }),
        onError: (err) => {
          fail('تعذّر إنهاء المهمة', err);
        },
      },
    );
  };

  const postponeTo = (task: LeadTask, to: Date) => {
    setPostponing(null);
    postpone.mutate(
      { id: task.id, dueAt: to },
      {
        onSuccess: () => toast.show({ tone: 'success', title: `أُجّلت المهمة إلى ${formatDayLong(to)}` }),
        onError: (err) => {
          fail('تعذّر تأجيل المهمة', err);
        },
      },
    );
  };

  const go = (path: string) => () => {
    setSheet(null);
    void navigate(path);
  };
  const open = (s: LeadSheet) => () => {
    setSheet(s);
  };
  const edit: MenuItem = { id: 'edit', label: 'تعديل البيانات', icon: 'edit', onSelect: go(`/leads/${lead.id}/edit`) };
  const visit: MenuItem = { id: 'visit', label: 'زيارة جديدة', icon: 'store', onSelect: go(`/leads/${lead.id}/visit`) };
  const toLost: MenuItem | null = lead.stage === 'lost' ? null : { id: 'lost', label: 'نقل إلى خسارة', icon: 'lost', danger: true, onSelect: open('lost') };
  const reply: MenuItem | null = canMarkReplied(lead.stage) ? { id: 'replied', label: 'العميل رد', icon: 'chat', onSelect: open('replied') } : null;
  const menu = (
    desktop ? [reply, visit, edit, toLost] : [edit, { id: 'stage', label: 'تغيير المرحلة', icon: 'stage', onSelect: open('stage') } satisfies MenuItem, toLost, visit]
  ).filter((x): x is MenuItem => x !== null);

  const sheets = (
    <>
      {sheet === 'menu' ? <MenuSheet open items={menu} onClose={close} /> : null}
      {sheet === 'stage' ? <StageSheet open current={lead.stage} busy={stage.isPending} onClose={close} onPick={(s) => void pickStage(s)} /> : null}
      {sheet === 'replied' ? (
        <RepliedSheet open intro={repliedIntro(lead.stage, openFollowupCount(tasks), !hasOpenMeetingTask(tasks))} busy={replied.isPending} onClose={close} onConfirm={() => void confirmReplied()} />
      ) : null}
      {sheet === 'meeting' ? <MeetingSheet open busy={meeting.isPending} onClose={close} onSubmit={(v) => void saveMeeting(v)} /> : null}
      {sheet === 'won' ? <WonSheet open expectedValue={lead.expectedValue} busy={won.isPending} onClose={close} onSubmit={(v) => void saveWon(v)} /> : null}
      {sheet === 'lost' ? <LostSheet open title={`نقل ${lead.businessName} إلى خسارة`} busy={lost.isPending} onClose={close} onSubmit={(v) => void saveLost(v)} /> : null}
      {postponing ? (
        <PostponeSheet
          open
          due={postponing.dueAt}
          onClose={() => {
            setPostponing(null);
          }}
          onConfirm={(to) => {
            postponeTo(postponing, to);
          }}
        />
      ) : null}
    </>
  );

  return {
    open: (s: LeadSheet) => {
      setSheet(s);
    },
    postpone: (t: LeadTask) => {
      setPostponing(t);
    },
    completeTask,
    /** id of the task being marked done */
    completing: complete.isPending ? (complete.variables.id) : null,
    sheets,
  };
}
