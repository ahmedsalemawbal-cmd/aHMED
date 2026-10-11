import { useState } from 'react';
import { useToast } from '@/components/app/toast/context';
import { STAGE_LABEL, type Stage } from '@/components/ui/stages';
import { actionErrorMessage, DIRECT_STAGES, stageRoute, useMarkLost, useMarkReplied, useMarkWon, useSetMeeting } from '@/data/lead-actions';
import { useMoveStage } from '@/data/pipeline';
import { LostSheet, type LostInput } from '@/features/lead/sheets/LostSheet';
import { MeetingSheet } from '@/features/lead/sheets/MeetingSheet';
import { StageSheet } from '@/features/lead/sheets/StageSheet';
import { WonSheet } from '@/features/lead/sheets/WonSheet';
import { canMarkReplied, meetingToast, repliedToast, wonToast } from '@/features/lead/text';
import { aboutLead, lostSheetTitle, lostToastText, stageToastText } from '@/features/leads/list-view';
import { RowRepliedSheet } from '@/features/leads/RowRepliedSheet';
import type { LeadRow } from '@/lib/leads-list';

type Sheet = 'stage' | 'replied' | 'meeting' | 'won' | 'lost';

/**
 * Moving a card, from a drop, a long press or «تغيير المرحلة». A direct stage
 * moves the card at once and goes back with an error toast if saving fails.
 * «رد»، «اجتماع»، «تم الإغلاق» and «خسارة» open the lead page's own sheets,
 * which stay open on failure so the user can retry.
 */
export function usePipelineActions() {
  const toast = useToast();
  const mover = useMoveStage();
  const markReplied = useMarkReplied();
  const setMeeting = useSetMeeting();
  const markWon = useMarkWon();
  const markLost = useMarkLost();
  const [open, setOpen] = useState<{ lead: LeadRow; sheet: Sheet } | null>(null);

  const close = () => {
    setOpen(null);
  };
  const done = (title: string, message?: string) => {
    setOpen(null);
    toast.show({ tone: 'success', title, message });
  };
  const fail = (title: string, err: unknown) => {
    toast.show({ tone: 'error', title, message: actionErrorMessage(err) });
  };

  const moveDirect = (lead: LeadRow, stage: Stage) => {
    const direct = DIRECT_STAGES.find((s) => s === stage);
    if (!direct) return;
    void mover.move(lead, direct).then((r) => {
      if (r.ok) {
        toast.show({ tone: 'success', title: stageToastText(direct, [lead]) });
        return;
      }
      toast.show({
        tone: 'error',
        title: `تعذّر نقل ${lead.businessName} إلى «${STAGE_LABEL[direct]}»`,
        message: actionErrorMessage(r.error),
        action: 'أعد المحاولة',
        onAction: () => {
          moveDirect(lead, direct);
        },
      });
    });
  };

  /** The card's new stage: saved at once, or the stage's own sheet. */
  const moveTo = (lead: LeadRow, stage: Stage) => {
    if (stage === lead.stage) {
      setOpen(null);
      return;
    }
    const route = stageRoute(stage);
    if (route === 'direct') {
      setOpen(null);
      moveDirect(lead, stage);
      return;
    }
    if (route === 'replied' && !canMarkReplied(lead.stage)) {
      setOpen(null);
      toast.show({ tone: 'info', title: 'العميل في مرحلة مغلقة', message: 'انقله إلى «تم الإرسال» أولاً، ثم سجّل الرد.' });
      return;
    }
    setOpen({ lead, sheet: route });
  };

  const confirmReplied = (lead: LeadRow) => {
    markReplied.mutate(lead.id, {
      onSuccess: (r) => {
        const t = aboutLead(repliedToast(r), lead);
        done(t.title, t.message);
      },
      onError: (err) => {
        fail('تعذّر تسجيل الرد', err);
      },
    });
  };

  const saveMeeting = (lead: LeadRow, input: { at: Date; title: string }) => {
    setMeeting.mutate(
      { leadId: lead.id, ...input },
      {
        onSuccess: () => {
          const t = aboutLead(meetingToast(input.at), lead);
          done(t.title, t.message);
        },
        onError: (err) => {
          fail('تعذّر حفظ الاجتماع', err);
        },
      },
    );
  };

  const saveWon = (lead: LeadRow, input: { value: number; billing: 'monthly' | 'one_time' }) => {
    markWon.mutate(
      { leadId: lead.id, ...input },
      {
        onSuccess: () => {
          const t = aboutLead(wonToast(input.value, input.billing), lead);
          done(t.title, t.message);
        },
        onError: (err) => {
          fail('تعذّر حفظ الإغلاق', err);
        },
      },
    );
  };

  const saveLost = (lead: LeadRow, input: LostInput) => {
    markLost.mutate(
      { leadIds: [lead.id], ...input },
      {
        onSuccess: () => {
          done(lostToastText([lead]));
        },
        onError: (err) => {
          fail('تعذّر النقل إلى خسارة', err);
        },
      },
    );
  };

  const lead = open?.lead;
  const sheets = lead ? (
    <>
      {open.sheet === 'stage' ? (
        <StageSheet
          open
          current={lead.stage}
          onClose={close}
          onPick={(s) => {
            moveTo(lead, s);
          }}
        />
      ) : null}
      {open.sheet === 'replied' ? (
        <RowRepliedSheet
          row={lead}
          busy={markReplied.isPending}
          onClose={close}
          onConfirm={() => {
            confirmReplied(lead);
          }}
        />
      ) : null}
      {open.sheet === 'meeting' ? (
        <MeetingSheet
          open
          busy={setMeeting.isPending}
          onClose={close}
          onSubmit={(input) => {
            saveMeeting(lead, input);
          }}
        />
      ) : null}
      {open.sheet === 'won' ? (
        <WonSheet
          open
          expectedValue={lead.expectedValue}
          busy={markWon.isPending}
          onClose={close}
          onSubmit={(input) => {
            saveWon(lead, input);
          }}
        />
      ) : null}
      {open.sheet === 'lost' ? (
        <LostSheet
          open
          title={lostSheetTitle([lead])}
          busy={markLost.isPending}
          onClose={close}
          onSubmit={(input) => {
            saveLost(lead, input);
          }}
        />
      ) : null}
    </>
  ) : null;

  return {
    moveTo,
    openStageSheet: (l: LeadRow) => {
      setOpen({ lead: l, sheet: 'stage' });
    },
    sheets,
  };
}
