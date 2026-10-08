import { useQuery } from '@tanstack/react-query';
import { isStage, type Stage } from '@/components/ui/stages';
import { endOfDay, startOfDay, startOfWeek } from '@/lib/dates';
import { supabase } from '@/lib/supabase';
import { isTaskKind, type TaskItem } from './tasks-meta';

export interface HotLead {
  id: string;
  businessName: string;
  activity: string;
  contactName: string | null;
  stage: Stage;
  score: number | null;
  phone: string | null;
  doNotContact: boolean;
  lastContactAt: Date | null;
  nextTask: { title: string; dueAt: Date } | null;
}

export interface TodayData {
  profile: { fullName: string; brandName: string | null; weeklyGoal: number };
  visitsToday: number;
  lastVisit: { businessName: string; at: Date } | null;
  sentToday: number;
  unsentDrafts: number;
  overdue: TaskItem[];
  today: TaskItem[];
  weekVisits: number;
  hot: HotLead[];
  week: { visits: number; sent: number; replies: number; meetings: number; won: number };
  /** share of first messages that got a reply, or null before any was sent */
  firstReplyRate: number | null;
}

interface RawTask {
  id: string;
  title: string;
  kind: string;
  due_at: string;
  lead: { id: string; business_name: string; stage: string; phone_e164: string | null; do_not_contact: boolean; contact_name: string | null } | null;
  messages: { body: string; status: string; created_at: string }[];
}

export function toTaskItem(r: RawTask): TaskItem | null {
  if (!r.lead || !isStage(r.lead.stage)) return null;
  const draft = [...r.messages].filter((m) => m.status === 'draft').sort((a, b) => b.created_at.localeCompare(a.created_at))[0];
  return {
    id: r.id,
    title: r.title,
    kind: isTaskKind(r.kind) ? r.kind : 'custom',
    dueAt: new Date(r.due_at),
    preview: draft ? (draft.body.split('\n').find((l) => l.trim()) ?? null) : null,
    lead: {
      id: r.lead.id,
      businessName: r.lead.business_name,
      stage: r.lead.stage,
      phone: r.lead.phone_e164,
      doNotContact: r.lead.do_not_contact,
      contactName: r.lead.contact_name,
    },
  };
}

/** Splits open tasks due up to the end of today into overdue (before today) and today. */
export function splitDue(tasks: TaskItem[], now: Date): { overdue: TaskItem[]; today: TaskItem[] } {
  const start = startOfDay(now).getTime();
  const sorted = [...tasks].sort((a, b) => a.dueAt.getTime() - b.dueAt.getTime());
  return { overdue: sorted.filter((t) => t.dueAt.getTime() < start), today: sorted.filter((t) => t.dueAt.getTime() >= start) };
}

/** Weekly goal text: «باقي 6 زيارات حتى الخميس» (week = Sunday–Thursday). */
export function goalRemainingText(done: number, goal: number, weekdayIndex: number): string {
  const left = goal - done;
  if (left <= 0) return 'حققت هدف الأسبوع.';
  if (weekdayIndex >= 5) return `باقي ${visitsWord(left)} من هدف هذا الأسبوع`;
  return `باقي ${visitsWord(left)} حتى الخميس`;
}

/** Arabic counted noun for visits: 1 زيارة، 2 زيارتان، 3–10 زيارات، 11+ زيارة. */
export function visitsWord(n: number): string {
  if (n === 1) return 'زيارة واحدة';
  if (n === 2) return 'زيارتان';
  if (n >= 3 && n <= 10) return `${n.toString()} زيارات`;
  return `${n.toString()} زيارة`;
}

function throwIf(error: { message: string } | null) {
  if (error) throw new Error(error.message);
}

export async function fetchToday(now: Date): Promise<TodayData> {
  const dayStart = startOfDay(now).toISOString();
  const dayEnd = endOfDay(now).toISOString();
  const weekStart = startOfWeek(now).toISOString();

  const [profile, tasks, visits, sent, drafts, weekVisits, hot, history, weekSent, firstSent, firstReplied] = await Promise.all([
    supabase.from('profiles').select('full_name, brand_name, weekly_visit_goal').maybeSingle(),
    supabase
      .from('tasks')
      .select('id, title, kind, due_at, lead:leads!inner(id, business_name, stage, phone_e164, do_not_contact, contact_name), messages(body, status, created_at)')
      .is('done_at', null)
      .is('cancelled_at', null)
      .lte('due_at', dayEnd)
      .order('due_at'),
    supabase.from('visits').select('visited_at, lead:leads(business_name)').gte('visited_at', dayStart).order('visited_at', { ascending: false }),
    supabase.from('messages').select('id', { count: 'exact', head: true }).gte('sent_at', dayStart),
    supabase.from('messages').select('id', { count: 'exact', head: true }).eq('status', 'draft').eq('kind', 'first'),
    supabase.from('visits').select('id', { count: 'exact', head: true }).gte('visited_at', weekStart),
    supabase
      .from('leads')
      .select('id, business_name, contact_name, stage, score, phone_e164, do_not_contact, last_contact_at, activity:activity_types(name), tasks(title, due_at, done_at, cancelled_at)')
      .eq('priority', 'hot')
      .in('stage', ['replied', 'meeting', 'proposal'])
      .order('stage_changed_at', { ascending: false })
      .limit(3),
    supabase.from('stage_history').select('to_stage').gte('changed_at', weekStart).in('to_stage', ['replied', 'meeting', 'won']),
    supabase.from('messages').select('id', { count: 'exact', head: true }).gte('sent_at', weekStart),
    supabase.from('messages').select('id', { count: 'exact', head: true }).eq('kind', 'first').in('status', ['sent', 'replied']),
    supabase.from('messages').select('id', { count: 'exact', head: true }).eq('kind', 'first').eq('status', 'replied'),
  ]);
  for (const r of [profile, tasks, visits, sent, drafts, weekVisits, hot, history, weekSent, firstSent, firstReplied]) throwIf(r.error);

  const items = (tasks.data ?? []).map((t) => toTaskItem(t as RawTask)).filter((t): t is TaskItem => t !== null);
  const { overdue, today } = splitDue(items, now);
  const visitRows = visits.data ?? [];
  const last = visitRows[0];
  const hist = history.data ?? [];

  return {
    profile: {
      fullName: profile.data?.full_name ?? '',
      brandName: profile.data?.brand_name ?? null,
      weeklyGoal: profile.data?.weekly_visit_goal ?? 20,
    },
    visitsToday: visitRows.length,
    lastVisit: last?.lead ? { businessName: last.lead.business_name, at: new Date(last.visited_at) } : null,
    sentToday: sent.count ?? 0,
    unsentDrafts: drafts.count ?? 0,
    overdue,
    today,
    weekVisits: weekVisits.count ?? 0,
    hot: (hot.data ?? []).flatMap((l): HotLead[] => {
      if (!isStage(l.stage)) return [];
      const open = l.tasks.filter((t) => !t.done_at && !t.cancelled_at).sort((a, b) => a.due_at.localeCompare(b.due_at))[0];
      return [
        {
          id: l.id,
          businessName: l.business_name,
          activity: l.activity?.name ?? '',
          contactName: l.contact_name,
          stage: l.stage,
          score: l.score,
          phone: l.phone_e164,
          doNotContact: l.do_not_contact,
          lastContactAt: l.last_contact_at ? new Date(l.last_contact_at) : null,
          nextTask: open ? { title: open.title, dueAt: new Date(open.due_at) } : null,
        },
      ];
    }),
    week: {
      visits: weekVisits.count ?? 0,
      sent: weekSent.count ?? 0,
      replies: hist.filter((h) => h.to_stage === 'replied').length,
      meetings: hist.filter((h) => h.to_stage === 'meeting').length,
      won: hist.filter((h) => h.to_stage === 'won').length,
    },
    firstReplyRate: firstSent.count ? Math.round(((firstReplied.count ?? 0) / firstSent.count) * 100) : null,
  };
}

export function useToday(now: Date) {
  return useQuery({
    queryKey: ['today', startOfDay(now).toISOString()],
    queryFn: () => fetchToday(now),
  });
}
