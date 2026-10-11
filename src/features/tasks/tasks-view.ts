import type { TaskRow } from '@/data/tasks';
import { arDays, calendarDaysBetween, formatDayLong, formatTime, formatWeekday } from '@/lib/dates';
import { needsDay7Decision } from '@/lib/followups';

export type SectionId = 'late' | 'today' | 'next';

export const SECTION_TITLE: Record<SectionId, string> = { late: 'متأخرة', today: 'اليوم', next: 'قادمة' };

/** Row background per section, so a swiped row stays opaque over its strip. */
export const SECTION_SURFACE: Record<SectionId, string> = { late: 'bg-warning-soft', today: 'bg-surface-raised', next: 'bg-surface-raised' };

/** The section box (Tasks.dc.html): overdue in the warning colour, the rest plain. */
export function sectionBox(id: SectionId): string {
  return id === 'late' ? 'border-[1.5px] border-warning bg-warning-soft' : 'border border-line bg-surface-raised';
}

/** Riyadh day of the due date: before today, today, or later. */
export function sectionOf(due: Date, now: Date): SectionId {
  const d = calendarDaysBetween(now, due);
  return d < 0 ? 'late' : d === 0 ? 'today' : 'next';
}

/**
 * Tasks.dc.html sections, each by due time. A task completed today stays in
 * its section, struck through, so it never jumps away from under the finger.
 */
export function groupTasks(tasks: TaskRow[], now: Date): Record<SectionId, TaskRow[]> {
  const out: Record<SectionId, TaskRow[]> = { late: [], today: [], next: [] };
  for (const t of [...tasks].sort((a, b) => a.dueAt.getTime() - b.dueAt.getTime())) out[sectionOf(t.dueAt, now)].push(t);
  return out;
}

/** «أمس»، «منذ يومين»، «منذ 3 أيام»، «منذ 11 يوماً» */
export function lateText(due: Date, now: Date): string {
  const days = calendarDaysBetween(due, now);
  if (days <= 1) return 'أمس';
  return `منذ ${arDays(days)}`;
}

/** Upcoming: «غداً 10:00 ص»، «الخميس 4:30 م» within the week, then «السبت 17 أكتوبر». */
export function upcomingText(due: Date, now: Date): string {
  const days = calendarDaysBetween(now, due);
  if (days <= 1) return `غداً ${formatTime(due)}`;
  if (days < 7) return `${formatWeekday(due)} ${formatTime(due)}`;
  return formatDayLong(due);
}

/** The time part of a row: overdue in words, today as a time, upcoming as a day. */
export function whenText(t: Pick<TaskRow, 'dueAt'>, now: Date): string {
  const s = sectionOf(t.dueAt, now);
  if (s === 'late') return lateText(t.dueAt, now);
  if (s === 'today') return formatTime(t.dueAt);
  return upcomingText(t.dueAt, now);
}

export function doneTodayCount(tasks: Pick<TaskRow, 'doneAt'>[]): number {
  return tasks.filter((t) => t.doneAt !== null).length;
}

/** Open tasks per lead: a lead with none has no next step. */
export function openCountByLead(tasks: Pick<TaskRow, 'doneAt' | 'lead'>[]): Map<string, number> {
  const m = new Map<string, number>();
  for (const t of tasks) if (t.doneAt === null) m.set(t.lead.id, (m.get(t.lead.id) ?? 0) + 1);
  return m;
}

/**
 * brief.md flow 2.4 on a «متابعة ثانية وأخيرة» row (needsDay7Decision): when
 * it is still open its day has passed; when done, the lead has nothing else
 * planned (after «أجّل 30 يوماً» the new «أعد المحاولة» answers it).
 */
export function showsDecision(t: Pick<TaskRow, 'kind' | 'doneAt' | 'dueAt' | 'lead'>, openForLead: number, now: Date): boolean {
  if (t.kind !== 'followup_7') return false;
  if (!needsDay7Decision(t.lead, { doneAt: t.doneAt, dueAt: t.dueAt }, now)) return false;
  return t.doneAt === null || openForLead === 0;
}

/**
 * The last follow-ups done before today that still wait for the decision:
 * one per lead (its latest), none for a lead already shown in the list.
 */
export function pendingDecisions(last: TaskRow[], tasks: TaskRow[], now: Date): TaskRow[] {
  const open = openCountByLead(tasks);
  const inList = new Set(tasks.filter((t) => t.kind === 'followup_7').map((t) => t.lead.id));
  const seen = new Set<string>();
  const out: TaskRow[] = [];
  for (const t of [...last].sort((a, b) => (b.doneAt?.getTime() ?? 0) - (a.doneAt?.getTime() ?? 0))) {
    if (seen.has(t.lead.id) || inList.has(t.lead.id)) continue;
    seen.add(t.lead.id);
    if (showsDecision(t, open.get(t.lead.id) ?? 0, now)) out.push(t);
  }
  return out;
}

/** «آخر متابعة أمس»، «آخر متابعة قبل 3 أيام» for a decision row. */
export function lastFollowupText(doneAt: Date, now: Date): string {
  const days = calendarDaysBetween(doneAt, now);
  if (days <= 0) return 'آخر متابعة اليوم';
  if (days === 1) return 'آخر متابعة أمس';
  return `آخر متابعة قبل ${arDays(days)}`;
}

/** Desktop header: «لا مهام مفتوحة»، «مهمة واحدة مفتوحة»، «مهمتان مفتوحتان»، «3 مهام مفتوحة»، «11 مهمة مفتوحة». */
export function openTasksText(n: number): string {
  if (n === 0) return 'لا مهام مفتوحة';
  if (n === 1) return 'مهمة واحدة مفتوحة';
  if (n === 2) return 'مهمتان مفتوحتان';
  const r = n % 100;
  return r >= 3 && r <= 10 ? `${n.toString()} مهام مفتوحة` : `${n.toString()} مهمة مفتوحة`;
}
