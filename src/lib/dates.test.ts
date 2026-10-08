import { describe, expect, it } from 'vitest';
import { addDays, agoText, arDays, calendarDaysBetween, endOfDay, formatDayLong, formatTime, overdueText, startOfDay, startOfWeek, weekday, whenText } from './dates';
import { firstName, formatNumber, formatSAR, greeting } from './format';

// Wednesday 7 Oct 2026, 10:45 Riyadh = 07:45 UTC
const NOW = new Date('2026-10-07T07:45:00Z');

describe('Riyadh calendar', () => {
  it('start and end of day are Riyadh midnight', () => {
    expect(startOfDay(NOW).toISOString()).toBe('2026-10-06T21:00:00.000Z');
    expect(endOfDay(NOW).toISOString()).toBe('2026-10-07T20:59:59.999Z');
  });
  it('a moment after 21:00 UTC is already the next Riyadh day', () => {
    expect(startOfDay(new Date('2026-10-07T21:30:00Z')).toISOString()).toBe('2026-10-07T21:00:00.000Z');
  });
  it('weekday: Wednesday = 3, week starts Sunday', () => {
    expect(weekday(NOW)).toBe(3);
    expect(startOfWeek(NOW).toISOString()).toBe('2026-10-03T21:00:00.000Z'); // Sun 4 Oct 00:00 Riyadh
    expect(weekday(startOfWeek(NOW))).toBe(0);
  });
  it('calendar days ignore hours', () => {
    // Tue 23:30 Riyadh → Wed 10:45: one calendar day although only 11 hours apart
    expect(calendarDaysBetween(new Date('2026-10-06T20:30:00Z'), NOW)).toBe(1);
    // Wed 00:30 Riyadh → Wed 10:45: same day
    expect(calendarDaysBetween(new Date('2026-10-06T21:30:00Z'), NOW)).toBe(0);
    expect(calendarDaysBetween(addDays(NOW, -2), NOW)).toBe(2);
  });
});

describe('Arabic formatting with Latin digits', () => {
  it('long day', () => {
    expect(formatDayLong(NOW)).toBe('الأربعاء 7 أكتوبر');
  });
  it('time', () => {
    expect(formatTime(NOW)).toBe('10:45 ص');
    expect(formatTime(new Date('2026-10-07T13:00:00Z'))).toBe('4:00 م');
  });
  it.each([
    [1, 'يوم'],
    [2, 'يومين'],
    [3, '3 أيام'],
    [10, '10 أيام'],
    [11, '11 يوماً'],
    [30, '30 يوماً'],
  ])('arDays(%i)', (n, s) => {
    expect(arDays(n)).toBe(s);
  });
  it('overdue', () => {
    expect(overdueText(addDays(NOW, -2), NOW)).toBe('متأخرة منذ يومين');
    expect(overdueText(addDays(NOW, -1), NOW)).toBe('متأخرة منذ يوم');
  });
  it('ago', () => {
    expect(agoText(new Date('2026-10-07T08:20:00Z'), NOW)).toBe('اليوم 11:20 ص');
    expect(agoText(addDays(NOW, -1), NOW)).toBe('أمس');
    expect(agoText(addDays(NOW, -4), NOW)).toBe('قبل 4 أيام');
  });
  it('when', () => {
    expect(whenText(new Date('2026-10-07T13:00:00Z'), NOW)).toBe('اليوم 4:00 م');
    expect(whenText(addDays(NOW, 3), NOW)).toBe('السبت 10 أكتوبر');
  });
  it('numbers and money', () => {
    expect(formatNumber(28500)).toBe('28,500');
    expect(formatSAR(2500)).toBe('2,500 ر.س');
  });
  it('greeting and first name', () => {
    expect(greeting(NOW)).toBe('صباح الخير');
    expect(greeting(new Date('2026-10-07T15:00:00Z'))).toBe('مساء الخير');
    expect(firstName('أحمد السالم')).toBe('أحمد');
    expect(firstName(null)).toBe('');
  });
});
