import { useQuery } from '@tanstack/react-query';
import { endOfDay, startOfDay } from '@/lib/dates';
import { supabase } from '@/lib/supabase';

export interface NavData {
  name: string;
  brand: string | null;
  dueToday: number;
  leads: number;
  overdue: number;
}

export async function fetchNav(now: Date): Promise<NavData> {
  const [profile, due, leads, overdue] = await Promise.all([
    supabase.from('profiles').select('full_name, brand_name').maybeSingle(),
    supabase.from('tasks').select('id', { count: 'exact', head: true }).is('done_at', null).is('cancelled_at', null).lte('due_at', endOfDay(now).toISOString()),
    supabase.from('leads').select('id', { count: 'exact', head: true }),
    supabase.from('tasks').select('id', { count: 'exact', head: true }).is('done_at', null).is('cancelled_at', null).lt('due_at', startOfDay(now).toISOString()),
  ]);
  for (const r of [profile, due, leads, overdue]) if (r.error) throw new Error(r.error.message);
  return {
    name: (profile.data?.full_name ?? '').trim(),
    brand: profile.data?.brand_name ?? null,
    dueToday: due.count ?? 0,
    leads: leads.count ?? 0,
    overdue: overdue.count ?? 0,
  };
}

export function useNavData() {
  return useQuery({ queryKey: ['nav'], queryFn: () => fetchNav(new Date()) });
}
