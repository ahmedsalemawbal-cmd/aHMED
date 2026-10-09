import { addDays, startOfDay } from './dates';
import { toDateInput } from './postpone';

const OFFSET_MS = 3 * 60 * 60 * 1000;

/** «2026-10-12» + «16:30» in Riyadh → the moment, or null when incomplete. */
export function riyadhAt(date: string, time: string): Date | null {
  const d = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
  const t = /^(\d{2}):(\d{2})$/.exec(time);
  if (!d || !t) return null;
  const h = Number(t[1]);
  const m = Number(t[2]);
  if (h > 23 || m > 59) return null;
  return new Date(Date.UTC(Number(d[1]), Number(d[2]) - 1, Number(d[3]), h, m) - OFFSET_MS);
}

/** HH:MM of a moment in Riyadh, for <input type="time">. */
export function toTimeInput(d: Date): string {
  return new Date(d.getTime() + OFFSET_MS).toISOString().slice(11, 16);
}

export type RetryOption = 'none' | '30' | '90' | 'date';

/** «أعد المحاولة» at 10:00 Riyadh: after 30 or 90 days, or on a chosen date. */
export function retryAt(option: RetryOption, now: Date, date = ''): Date | null {
  if (option === 'none') return null;
  if (option === 'date') return riyadhAt(date, '10:00');
  return new Date(addDays(startOfDay(now), Number(option)).getTime() + 10 * 3_600_000);
}

/** Suggested meeting slot: tomorrow at 16:30 (after Asr, when shops are calm). */
export function defaultMeetingSlot(now: Date): { date: string; time: string } {
  return { date: toDateInput(addDays(startOfDay(now), 1)), time: '16:30' };
}
