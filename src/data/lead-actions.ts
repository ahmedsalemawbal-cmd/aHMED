import { useMutation, useQueryClient } from '@tanstack/react-query';
import { isStage, type Stage } from '@/components/ui/stages';
import type { Json } from '@/lib/database.types';
import { supabase } from '@/lib/supabase';
import type { LostReason } from '@/lib/timeline';
import { refreshAll } from './refresh';

/** Stages set directly; the others have their own action (they create tasks or need data). */
export type DirectStage = 'not_visited' | 'visited' | 'contacted' | 'proposal';
export const DIRECT_STAGES: DirectStage[] = ['not_visited', 'visited', 'contacted', 'proposal'];

/** How «تغيير المرحلة» reaches each stage. */
export type StageRoute = 'direct' | 'replied' | 'meeting' | 'won' | 'lost';
export function stageRoute(stage: Stage): StageRoute {
  if (stage === 'replied' || stage === 'meeting' || stage === 'won' || stage === 'lost') return stage;
  return 'direct';
}

function obj(data: Json): Record<string, Json | undefined> {
  if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('استجابة غير متوقعة من الخادم');
  return data;
}

async function rpc(fn: 'mark_replied' | 'set_meeting' | 'mark_won' | 'mark_lost' | 'set_stage', p: { [key: string]: Json }): Promise<Record<string, Json | undefined>> {
  if (!navigator.onLine) throw new Error('offline');
  const { data, error } = await supabase.rpc(fn, { p });
  if (error) throw new Error(error.message);
  return obj(data);
}

export interface RepliedResult {
  stageBefore: Stage | null;
  cancelledTaskIds: string[];
  /** the «حدد اجتماعاً» task, unless one was already open */
  taskId: string | null;
}

export async function markReplied(leadId: string): Promise<RepliedResult> {
  const r = await rpc('mark_replied', { lead_id: leadId });
  const before = r.stage_before;
  const ids = r.cancelled_task_ids;
  return {
    stageBefore: typeof before === 'string' && isStage(before) ? before : null,
    cancelledTaskIds: Array.isArray(ids) ? ids.filter((x): x is string => typeof x === 'string') : [],
    taskId: typeof r.task_id === 'string' ? r.task_id : null,
  };
}

export async function setMeeting(input: { leadId: string; at: Date; title: string }): Promise<void> {
  await rpc('set_meeting', { lead_id: input.leadId, at: input.at.toISOString(), title: input.title });
}

export async function markWon(input: { leadId: string; value: number; billing: 'monthly' | 'one_time' }): Promise<void> {
  await rpc('mark_won', { lead_id: input.leadId, value: input.value, billing: input.billing });
}

export async function markLost(input: { leadIds: string[]; reason: LostReason; note: string; retryAt: Date | null }): Promise<number> {
  const r = await rpc('mark_lost', { lead_ids: input.leadIds, reason: input.reason, note: input.note, retry_at: input.retryAt ? input.retryAt.toISOString() : null });
  return typeof r.updated === 'number' ? r.updated : 0;
}

export async function setStage(input: { leadIds: string[]; stage: DirectStage }): Promise<number> {
  const r = await rpc('set_stage', { lead_ids: input.leadIds, stage: input.stage });
  return typeof r.updated === 'number' ? r.updated : 0;
}

function useAction<I, O>(fn: (input: I) => Promise<O>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: () => {
      refreshAll(qc);
    },
  });
}

export const useMarkReplied = () => useAction(markReplied);
export const useSetMeeting = () => useAction(setMeeting);
export const useMarkWon = () => useAction(markWon);
export const useMarkLost = () => useAction(markLost);
export const useSetStage = () => useAction(setStage);

/** Short error text for a failed action: what happened + what to do. */
export function actionErrorMessage(err: unknown): string {
  if (err instanceof Error && err.message === 'offline') return 'بدون اتصال. أعد المحاولة عند عودة الشبكة.';
  return 'تأكد من الشبكة ثم أعد المحاولة.';
}
