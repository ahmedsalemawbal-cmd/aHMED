import { useState } from 'react';
import { useToast } from '@/components/app/toast/context';
import type { Stage } from '@/components/ui/stages';
import { actionErrorMessage, DIRECT_STAGES, stageRoute, useMarkLost, useMarkReplied, useMarkWon, useSetMeeting, useSetStage, type DirectStage } from '@/data/lead-actions';
import { LostSheet, type LostInput } from '@/features/lead/sheets/LostSheet';
import { MeetingSheet } from '@/features/lead/sheets/MeetingSheet';
import { StageSheet } from '@/features/lead/sheets/StageSheet';
import { WonSheet } from '@/features/lead/sheets/WonSheet';
import { canMarkReplied, meetingToast, repliedToast, wonToast } from '@/features/lead/text';
import { csvFileName, leadsCsv } from '@/lib/csv';
import { downloadText } from '@/lib/download';
import type { LeadRow } from '@/lib/leads-list';
import { aboutLead, exportToastText, lostSheetTitle, lostToastText, stageToastText } from './list-view';
import { RowRepliedSheet } from './RowRepliedSheet';

type Sheet = 'stage' | 'lost' | 'replied' | 'meeting' | 'won';

/**
 * The selection bar of DeskLeads.dc.html. A direct stage or «خسارة» applies
 * to every selected lead in one RPC. «رد»، «اجتماع» and «تم الإغلاق» need one
 * lead's details: StageSheet offers them for a single row only, and they open
 * the lead page's own sheets here. A failed bulk action closes its sheet and
 * offers «أعد المحاولة»; a failed one-lead sheet stays open to retry.
 */
export function useSelectionActions(chosen: LeadRow[], clearSelection: () => void) {
  const toast = useToast();
  const setStage = useSetStage();
  const markLost = useMarkLost();
  const markReplied = useMarkReplied();
  const setMeeting = useSetMeeting();
  const markWon = useMarkWon();
  const [sheet, setSheet] = useState<Sheet | null>(null);
  const busy = setStage.isPending || markLost.isPending || markReplied.isPending || setMeeting.isPending || markWon.isPending;
  const only = chosen.length === 1 ? chosen[0] : undefined;

  const close = () => {
    setSheet(null);
  };
  const done = (title: string, message?: string) => {
    setSheet(null);
    clearSelection();
    toast.show({ tone: 'success', title, message });
  };
  const failBulk = (title: string, err: unknown, retry: () => void) => {
    setSheet(null);
    toast.show({ tone: 'error', title, message: actionErrorMessage(err), action: 'أعد المحاولة', onAction: retry });
  };
  const failRow = (title: string, err: unknown) => {
    toast.show({ tone: 'error', title, message: actionErrorMessage(err) });
  };

  const applyStage = (target: LeadRow[], stage: DirectStage) => {
    setStage.mutate(
      { leadIds: target.map((r) => r.id), stage },
      {
        onSuccess: () => {
          done(stageToastText(stage, target));
        },
        onError: (err) => {
          failBulk('تعذّر تغيير المرحلة', err, () => {
            applyStage(target, stage);
          });
        },
      },
    );
  };

  const applyLost = (target: LeadRow[], input: LostInput) => {
    markLost.mutate(
      { leadIds: target.map((r) => r.id), ...input },
      {
        onSuccess: () => {
          done(lostToastText(target));
        },
        onError: (err) => {
          failBulk('تعذّر النقل إلى خسارة', err, () => {
            applyLost(target, input);
          });
        },
      },
    );
  };

  const pickStage = (stage: Stage) => {
    const route = stageRoute(stage);
    if (route === 'lost') {
      setSheet('lost');
      return;
    }
    if (route === 'direct') {
      const direct = DIRECT_STAGES.find((s) => s === stage);
      if (direct) applyStage(chosen, direct);
      return;
    }
    // reply, meeting and won: one lead at a time (the sheet disables them for several)
    if (!only) return;
    if (route === 'replied' && !canMarkReplied(only.stage)) {
      toast.show({ tone: 'info', title: 'العميل في مرحلة مغلقة', message: 'انقله إلى «تم الإرسال» أولاً، ثم سجّل الرد.' });
      return;
    }
    setSheet(route);
  };

  const confirmReplied = (row: LeadRow) => {
    markReplied.mutate(row.id, {
      onSuccess: (r) => {
        const t = aboutLead(repliedToast(r), row);
        done(t.title, t.message);
      },
      onError: (err) => {
        failRow('تعذّر تسجيل الرد', err);
      },
    });
  };

  const saveMeeting = (row: LeadRow, input: { at: Date; title: string }) => {
    setMeeting.mutate(
      { leadId: row.id, ...input },
      {
        onSuccess: () => {
          const t = aboutLead(meetingToast(input.at), row);
          done(t.title, t.message);
        },
        onError: (err) => {
          failRow('تعذّر حفظ الاجتماع', err);
        },
      },
    );
  };

  const saveWon = (row: LeadRow, input: { value: number; billing: 'monthly' | 'one_time' }) => {
    markWon.mutate(
      { leadId: row.id, ...input },
      {
        onSuccess: () => {
          const t = aboutLead(wonToast(input.value, input.billing), row);
          done(t.title, t.message);
        },
        onError: (err) => {
          failRow('تعذّر حفظ الإغلاق', err);
        },
      },
    );
  };

  const exportCsv = () => {
    downloadText(csvFileName('عملاء', new Date()), leadsCsv(chosen));
    toast.show({ tone: 'success', title: exportToastText(chosen) });
  };

  // a sheet needs its leads: nothing is open once the selection is gone
  const open = chosen.length > 0 ? sheet : null;
  const sheets = (
    <>
      {open === 'stage' ? <StageSheet open current={only?.stage ?? null} count={chosen.length} busy={busy} onClose={close} onPick={pickStage} /> : null}
      {open === 'lost' ? (
        <LostSheet
          open
          title={lostSheetTitle(chosen)}
          busy={markLost.isPending}
          onClose={close}
          onSubmit={(input) => {
            applyLost(chosen, input);
          }}
        />
      ) : null}
      {open === 'replied' && only ? (
        <RowRepliedSheet
          row={only}
          busy={markReplied.isPending}
          onClose={close}
          onConfirm={() => {
            confirmReplied(only);
          }}
        />
      ) : null}
      {open === 'meeting' && only ? (
        <MeetingSheet
          open
          busy={setMeeting.isPending}
          onClose={close}
          onSubmit={(input) => {
            saveMeeting(only, input);
          }}
        />
      ) : null}
      {open === 'won' && only ? (
        <WonSheet
          open
          expectedValue={only.expectedValue}
          busy={markWon.isPending}
          onClose={close}
          onSubmit={(input) => {
            saveWon(only, input);
          }}
        />
      ) : null}
    </>
  );

  return {
    busy,
    openStageSheet: () => {
      setSheet('stage');
    },
    exportCsv,
    sheets,
  };
}
