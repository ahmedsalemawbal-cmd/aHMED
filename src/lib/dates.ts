/**
 * Dates in Riyadh time (UTC+3, no daylight saving). The Saudi work week runs
 * Sunday to Thursday; weekly goals count from Sunday 00:00.
 */
const OFFSET_MS = 3 * 60 * 60 * 1000;
const DAY_MS = 24 * 60 * 60 * 1000;
const LOCALE = 'ar-SA-u-ca-gregory-nu-latn';
const TZ = 'Asia/Riyadh';

/** Riyadh calendar day index (days since epoch in Riyadh time). */
function dayIndex(d: Date): number {
  return Math.floor((d.getTime() + OFFSET_MS) / DAY_MS);
}

export function startOfDay(d: Date): Date {
  return new Date(dayIndex(d) * DAY_MS - OFFSET_MS);
}

export function endOfDay(d: Date): Date {
  return new Date(startOfDay(d).getTime() + DAY_MS - 1);
}

export function addDays(d: Date, n: number): Date {
  return new Date(d.getTime() + n * DAY_MS);
}

/** 0 = Sunday … 6 = Saturday, in Riyadh. */
export function weekday(d: Date): number {
  return (dayIndex(d) + 4) % 7; // 1970-01-01 was a Thursday
}

export function startOfWeek(d: Date): Date {
  return addDays(startOfDay(d), -weekday(d));
}

/** Calendar days from a to b in Riyadh (b later → positive). */
export function calendarDaysBetween(a: Date, b: Date): number {
  return dayIndex(b) - dayIndex(a);
}

export function isSameDay(a: Date, b: Date): boolean {
  return dayIndex(a) === dayIndex(b);
}

function fmt(d: Date, opts: Intl.DateTimeFormatOptions): string {
  return new Intl.DateTimeFormat(LOCALE, { timeZone: TZ, ...opts }).format(d).replace(/[\u202f\u00a0]/g, ' ');
}

/** «الأربعاء 7 أكتوبر» */
export function formatDayLong(d: Date): string {
  return fmt(d, { weekday: 'long', day: 'numeric', month: 'long' }).replace('،', '');
}

/** «7 أكتوبر 2026» */
export function formatDate(d: Date): string {
  return fmt(d, { day: 'numeric', month: 'long', year: 'numeric' });
}

/** «10:45 ص» */
export function formatTime(d: Date): string {
  return fmt(d, { hour: 'numeric', minute: '2-digit' });
}

/** «الأربعاء» */
export function formatWeekday(d: Date): string {
  return fmt(d, { weekday: 'long' });
}

/**
 * Arabic counted noun for days with correct agreement:
 * 1 يوم، 2 يومين، 3–10 أيام، 11+ يوماً.
 */
export function arDays(n: number): string {
  const a = Math.abs(n);
  if (a === 1) return 'يوم';
  if (a === 2) return 'يومين';
  if (a >= 3 && a <= 10) return `${a.toString()} أيام`;
  return `${a.toString()} يوماً`;
}

/** «متأخرة منذ يومين» */
export function overdueText(due: Date, now: Date): string {
  const days = calendarDaysBetween(due, now);
  if (days <= 0) return `متأخرة منذ ${formatTime(due)}`;
  return `متأخرة منذ ${arDays(days)}`;
}

/** Past moment for «آخر تواصل»: «اليوم 11:20 ص»، «أمس»، «قبل يومين»، «قبل 4 أيام». */
export function agoText(at: Date, now: Date): string {
  const days = calendarDaysBetween(at, now);
  if (days <= 0) return `اليوم ${formatTime(at)}`;
  if (days === 1) return 'أمس';
  return `قبل ${arDays(days)}`;
}

/** Upcoming moment: «اليوم 4:00 م»، «غداً 10:00 ص»، «السبت 10 أكتوبر». */
export function whenText(at: Date, now: Date): string {
  const days = calendarDaysBetween(now, at);
  if (days === 0) return `اليوم ${formatTime(at)}`;
  if (days === 1) return `غداً ${formatTime(at)}`;
  return formatDayLong(at);
}
