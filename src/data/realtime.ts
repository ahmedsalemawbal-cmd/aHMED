import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/lib/supabase';

/**
 * A lead saved on the phone must appear on the desktop at once (brief.md):
 * any change to the user's rows (RLS applies to Realtime) refreshes the queries.
 */
export function useRealtimeRefresh(enabled: boolean) {
  const qc = useQueryClient();
  useEffect(() => {
    if (!enabled) return;
    const channel = supabase.channel('maidani-changes');
    for (const table of ['leads', 'tasks', 'messages', 'visits', 'quotes'] as const) {
      channel.on('postgres_changes', { event: '*', schema: 'public', table }, () => {
        void qc.invalidateQueries();
      });
    }
    channel.subscribe();
    return () => {
      void supabase.removeChannel(channel);
    };
  }, [enabled, qc]);
}
