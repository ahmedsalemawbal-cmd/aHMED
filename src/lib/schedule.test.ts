import { describe, expect, it } from 'vitest';
import { defaultMeetingSlot, retryAt, riyadhAt, toTimeInput } from './schedule';

describe('Riyadh date and time inputs', () => {
  it('date + time → UTC moment (Riyadh is UTC+3, no DST)', () => {
    expect(riyadhAt('2026-10-12', '16:30')?.toISOString()).toBe('2026-10-12T13:30:00.000Z');
    expect(riyadhAt('2026-10-12', '00:15')?.toISOString()).toBe('2026-10-11T21:15:00.000Z');
    expect(riyadhAt('2026-10-12', '')).toBeNull();
    expect(riyadhAt('12/10/2026', '10:00')).toBeNull();
    expect(riyadhAt('2026-10-12', '25:00')).toBeNull();
    expect(toTimeInput(new Date('2026-10-12T13:30:00Z'))).toBe('16:30');
  });
  it('retry: none, 30 or 90 days at 10:00, or a date', () => {
    const now = new Date('2026-10-09T20:00:00Z'); // 23:00 Friday in Riyadh
    expect(retryAt('none', now)).toBeNull();
    expect(retryAt('30', now)?.toISOString()).toBe('2026-11-08T07:00:00.000Z');
    expect(retryAt('90', now)?.toISOString()).toBe('2027-01-07T07:00:00.000Z');
    expect(retryAt('date', now, '2026-12-01')?.toISOString()).toBe('2026-12-01T07:00:00.000Z');
    expect(retryAt('date', now, '')).toBeNull();
  });
  it('meeting defaults to tomorrow 16:30 in Riyadh', () => {
    expect(defaultMeetingSlot(new Date('2026-10-09T22:30:00Z'))).toEqual({ date: '2026-10-11', time: '16:30' });
  });
});
