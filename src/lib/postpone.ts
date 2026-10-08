import { addDays, startOfDay } from './dates';

export type PostponeOption = { kind: 'tomorrow' } | { kind: 'in3' } | { kind: 'date'; date: string /* YYYY-MM-DD */ };

const OFFSET_MS = 3 * 60 * 60 * 1000;

/** Time of day of `d` in Riyadh, in ms since Riyadh midnight. */
function timeOfDay(d: Date): number {
  return d.getTime() - startOfDay(d).getTime();
}

/**
 * New due moment for a postponed task. The task keeps its time of day; the day
 * counts from today (an overdue task postponed «غداً» lands tomorrow, not the
 * day after its old date).
 */
export function postponeTo(due: Date, option: PostponeOption, now: Date): Date {
  const tod = timeOfDay(due);
  if (option.kind === 'tomorrow') return new Date(addDays(startOfDay(now), 1).getTime() + tod);
  if (option.kind === 'in3') return new Date(addDays(startOfDay(now), 3).getTime() + tod);
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(option.date);
  if (!m) throw new Error('صيغة التاريخ غير صحيحة');
  const midnightRiyadh = Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])) - OFFSET_MS;
  return new Date(midnightRiyadh + tod);
}

/** YYYY-MM-DD of a moment in Riyadh, for <input type="date">. */
export function toDateInput(d: Date): string {
  return new Date(d.getTime() + OFFSET_MS).toISOString().slice(0, 10);
}
