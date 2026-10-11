import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { isStage } from '@/components/ui/stages';
import { startOfDay } from '@/lib/dates';
import { supabase } from '@/lib/supabase';
import { refreshAll } from './refresh';
import { useCompleteTask, usePostponeTask } from './task-mutations';
import { isTaskKind, type TaskItem } from './tasks-meta';

/** A task of «المهام»: open, or completed today (it stays, struck through, until tomorrow). */
export interface TaskRow extends TaskItem {
  doneAt: Date | null;
}

export interface TasksData {
  /** every open task, and the tasks completed today */
  tasks: TaskRow[];
  /** «متابعة ثانية وأخيرة» completed before today while the lead is still in «تم الإرسال» */
  lastFollowups: TaskRow[];
}

interface RawTask {
  id: string;
  title: string;
  kind: string;
  due_at: string;
  done_at: string | null;
  lead: { id: string; business_name: string; stage: string; phone_e164: string | null; do_not_contact: boolean; contact_name: string | null } | null;
}

const TASK_COLUMNS = 'id, title, kind, due_at, done_at, lead:leads!inner(id, business_name, stage, phone_e164, do_not_contact, contact_name)';

/** One row; a task of an unknown kind is shown as a plain task, a lead of an unknown stage is dropped. */
export function toTaskRow(r: RawTask): TaskRow | null {
  if (!r.lead || !isStage(r.lead.stage)) return null;
  return {
    id: r.id,
    title: r.title,
    kind: isTaskKind(r.kind) ? r.kind : 'custom',
    dueAt: new Date(r.due_at),
    doneAt: r.done_at ? new Date(r.done_at) : null,
    preview: null,
    lead: {
      id: r.lead.id,
      businessName: r.lead.business_name,
      stage: r.lead.stage,
      phone: r.lead.phone_e164,
      doNotContact: r.lead.do_not_contact,
      contactName: r.lead.contact_name?.trim() || null,
    },
  };
}

function rows(data: RawTask[] | null): TaskRow[] {
  return (data ?? []).flatMap((r) => {
    const t = toTaskRow(r);
    return t ? [t] : [];
  });
}

/**
 * Open tasks (all due dates: overdue, today, upcoming) with tasks completed
 * today, and the last follow-ups done earlier for leads that never replied
 * (flow 2.4 needs a decision for them). Cancelled tasks never show.
 */
export async function fetchTasks(now: Date): Promise<TasksData> {
  const dayStart = startOfDay(now).toISOString();
  const [list, last] = await Promise.all([
    supabase.from('tasks').select(TASK_COLUMNS).is('cancelled_at', null).or(`done_at.is.null,done_at.gte.${dayStart}`).order('due_at'),
    supabase
      .from('tasks')
      .select(TASK_COLUMNS)
      .eq('kind', 'followup_7')
      .is('cancelled_at', null)
      .lt('done_at', dayStart)
      .eq('lead.stage', 'contacted')
      .order('done_at', { ascending: false }),
  ]);
  if (list.error) throw new Error(list.error.message);
  if (last.error) throw new Error(last.error.message);
  const listRows: RawTask[] = list.data;
  const lastRows: RawTask[] = last.data;
  return { tasks: rows(listRows), lastFollowups: rows(lastRows) };
}

export function tasksKey(now: Date) {
  return ['tasks', startOfDay(now).toISOString()] as const;
}

export function useTasks(now: Date) {
  return useQuery({ queryKey: tasksKey(now), queryFn: () => fetchTasks(now) });
}

type Result = { ok: true } | { ok: false; error: unknown };

/**
 * Tick and untick at once: the row is struck through (or back) before the
 * server answers, and returns to what it was if saving fails.
 */
export function useToggleTask(now: Date) {
  const qc = useQueryClient();
  const complete = useCompleteTask();
  const key = tasksKey(now);
  const setDone = (id: string, doneAt: Date | null) => {
    qc.setQueryData<TasksData>(key, (d) => (d ? { ...d, tasks: d.tasks.map((t) => (t.id === id ? { ...t, doneAt } : t)) } : d));
  };
  return async (task: TaskRow, done: boolean): Promise<Result> => {
    await qc.cancelQueries({ queryKey: key });
    setDone(task.id, done ? new Date() : null);
    try {
      await complete.mutateAsync({ id: task.id, done });
      return { ok: true };
    } catch (error) {
      setDone(task.id, task.doneAt);
      return { ok: false, error };
    }
  };
}

/** «أجّل»: the row moves to its new day at once, and back if saving fails. */
export function useMoveTask(now: Date) {
  const qc = useQueryClient();
  const postpone = usePostponeTask();
  const key = tasksKey(now);
  const setDue = (id: string, dueAt: Date) => {
    qc.setQueryData<TasksData>(key, (d) => (d ? { ...d, tasks: d.tasks.map((t) => (t.id === id ? { ...t, dueAt } : t)) } : d));
  };
  return async (task: TaskRow, to: Date): Promise<Result> => {
    await qc.cancelQueries({ queryKey: key });
    setDue(task.id, to);
    try {
      await postpone.mutateAsync({ id: task.id, dueAt: to });
      return { ok: true };
    } catch (error) {
      setDue(task.id, task.dueAt);
      return { ok: false, error };
    }
  };
}

/* ------------------------------------------------------- postpone_lead */

export interface PostponeLeadResult {
  /** the new «أعد المحاولة» task */
  taskId: string | null;
  dueAt: Date;
  cancelledTaskIds: string[];
}

/**
 * Flow 2.4 «أجّل 30 يوماً», in one transaction: the lead's open follow-ups
 * are cancelled and one «أعد المحاولة» task is created at `retryAt`.
 */
export async function postponeLead(input: { leadId: string; retryAt: Date }): Promise<PostponeLeadResult> {
  if (!navigator.onLine) throw new Error('offline');
  const { data, error } = await supabase.rpc('postpone_lead', { p: { lead_id: input.leadId, retry_at: input.retryAt.toISOString() } });
  if (error) throw new Error(error.message);
  if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('استجابة غير متوقعة من الخادم');
  const ids = data.cancelled_task_ids;
  return {
    taskId: typeof data.task_id === 'string' ? data.task_id : null,
    dueAt: typeof data.due_at === 'string' ? new Date(data.due_at) : input.retryAt,
    cancelledTaskIds: Array.isArray(ids) ? ids.filter((x): x is string => typeof x === 'string') : [],
  };
}

export function usePostponeLead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: postponeLead,
    onSuccess: () => {
      refreshAll(qc);
    },
  });
}
