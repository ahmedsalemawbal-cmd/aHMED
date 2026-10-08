import { addDays, startOfDay } from './dates';

export type ContactTime = 'morning' | 'afternoon' | 'evening';

/** Hour of day (Riyadh) for each preferred contact time; 10:00 when unknown. */
export const CONTACT_HOUR: Record<ContactTime, number> = { morning: 10, afternoon: 16, evening: 19 };

const H = 3_600_000;

/**
 * After «نعم، أرسلتها»: two follow-ups, 3 and 7 days later (brief.md «سلسلة المتابعة»),
 * at the lead's preferred contact time. None for do_not_contact.
 */
export function followupSchedule(
  confirmedAt: Date,
  opts: { bestTime: ContactTime | null; doNotContact: boolean },
): { kind: 'followup_3' | 'followup_7'; title: string; dueAt: Date }[] {
  if (opts.doNotContact) return [];
  const hour = opts.bestTime ? CONTACT_HOUR[opts.bestTime] : 10;
  const at = (days: number) => new Date(addDays(startOfDay(confirmedAt), days).getTime() + hour * H);
  return [
    { kind: 'followup_3', title: 'متابعة أولى', dueAt: at(3) },
    { kind: 'followup_7', title: 'متابعة ثانية وأخيرة', dueAt: at(7) },
  ];
}

/** «تابع رد العرض» 3 days after confirming a quote was sent (decisions §2). */
export function quoteFollowup(confirmedAt: Date, bestTime: ContactTime | null): Date {
  const hour = bestTime ? CONTACT_HOUR[bestTime] : 10;
  return new Date(addDays(startOfDay(confirmedAt), 3).getTime() + hour * H);
}

/**
 * brief.md flow 2.4: when the day-7 follow-up ends without a reply, suggest
 * postponing 30 days or moving to lost.
 */
export function needsDay7Decision(lead: { stage: string }, followup7: { doneAt: Date | null; dueAt: Date } | null, now: Date): boolean {
  if (lead.stage !== 'contacted' || !followup7) return false;
  return followup7.doneAt !== null || followup7.dueAt.getTime() < startOfDay(now).getTime();
}

export const RETRY_AFTER_DAYS = 30;
