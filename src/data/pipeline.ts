import { useQueryClient, type QueryClient } from '@tanstack/react-query';
import type { Stage } from '@/components/ui/stages';
import type { LeadRow } from '@/lib/leads-list';
import { useSetStage, type DirectStage } from './lead-actions';

/** The cache of useLeads() (src/data/leads.ts), shared by «العملاء» and Pipeline. */
export const LEADS_KEY = ['leads'] as const;

/** Puts one lead in `stage` in the cached list, as if the server had already answered. */
export function moveInCache(qc: QueryClient, leadId: string, stage: Stage, at: Date): void {
  qc.setQueryData<LeadRow[]>(LEADS_KEY, (rows) => rows?.map((r) => (r.id === leadId ? { ...r, stage, stageChangedAt: at } : r)));
}

/**
 * A direct stage change (لم يُزر، تمت الزيارة، تم الإرسال، عرض سعر) that moves
 * the card at once. On failure only this lead goes back where it was (another
 * card moved meanwhile stays put) and the list is reloaded from the server.
 * Resolves true when saved, false when it failed (the caller shows the toast).
 */
export function useMoveStage() {
  const qc = useQueryClient();
  const setStage = useSetStage();
  const move = async (lead: LeadRow, stage: DirectStage): Promise<{ ok: true } | { ok: false; error: unknown }> => {
    // a fetch already on its way would put the old stage back
    await qc.cancelQueries({ queryKey: LEADS_KEY });
    moveInCache(qc, lead.id, stage, new Date());
    try {
      await setStage.mutateAsync({ leadIds: [lead.id], stage });
      return { ok: true };
    } catch (error) {
      qc.setQueryData<LeadRow[]>(LEADS_KEY, (rows) =>
        rows?.map((r) => (r.id === lead.id && r.stage === stage ? { ...r, stage: lead.stage, stageChangedAt: lead.stageChangedAt } : r)),
      );
      void qc.invalidateQueries({ queryKey: LEADS_KEY });
      return { ok: false, error };
    }
  };
  return { move, isPending: setStage.isPending };
}
