import { useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/lib/supabase';

export function usePostponeTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, dueAt }: { id: string; dueAt: Date }) => {
      const { error } = await supabase.from('tasks').update({ due_at: dueAt.toISOString() }).eq('id', id);
      if (error) throw new Error(error.message);
    },
    onSettled: () => {
      void qc.invalidateQueries();
    },
  });
}

export function useCompleteTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, done }: { id: string; done: boolean }) => {
      const { error } = await supabase
        .from('tasks')
        .update({ done_at: done ? new Date().toISOString() : null })
        .eq('id', id);
      if (error) throw new Error(error.message);
    },
    onSettled: () => {
      void qc.invalidateQueries();
    },
  });
}
