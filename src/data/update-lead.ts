import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { TablesUpdate } from '@/lib/database.types';
import { supabase } from '@/lib/supabase';
import { OfflineError } from './create-lead';
import { refreshAll } from './refresh';

export type LeadPatch = TablesUpdate<'leads'>;

/** The row is gone or belongs to someone else (RLS hides it): nothing was updated. */
export class LeadNotFoundError extends Error {
  constructor() {
    super('lead not found');
  }
}

/**
 * «تعديل البيانات»: one UPDATE with only the columns that changed, under RLS.
 * Turning on do_not_contact cancels the open message tasks in the database
 * (trigger leads_dnc_cancel_tasks, migration 9).
 */
export async function updateLead(id: string, patch: LeadPatch): Promise<void> {
  if (!navigator.onLine) throw new OfflineError();
  const { data, error } = await supabase.from('leads').update(patch).eq('id', id).select('id');
  if (error) throw new Error(error.message);
  if (data.length === 0) throw new LeadNotFoundError();
}

export function useUpdateLead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, patch }: { id: string; patch: LeadPatch }) => updateLead(id, patch),
    onSuccess: () => {
      refreshAll(qc);
    },
  });
}
