import { describe, expect, it } from 'vitest';
import { postponeTo, toDateInput } from './postpone';

const NOW = new Date('2026-10-07T07:45:00Z'); // Wed 10:45 Riyadh
const DUE_OVERDUE = new Date('2026-10-05T13:00:00Z'); // Mon 16:00 Riyadh

describe('postponeTo', () => {
  it('tomorrow keeps the time of day and counts from today', () => {
    expect(postponeTo(DUE_OVERDUE, { kind: 'tomorrow' }, NOW).toISOString()).toBe('2026-10-08T13:00:00.000Z');
  });
  it('in 3 days', () => {
    expect(postponeTo(DUE_OVERDUE, { kind: 'in3' }, NOW).toISOString()).toBe('2026-10-10T13:00:00.000Z');
  });
  it('a chosen date in Riyadh', () => {
    expect(postponeTo(DUE_OVERDUE, { kind: 'date', date: '2026-10-20' }, NOW).toISOString()).toBe('2026-10-20T13:00:00.000Z');
  });
  it('rejects a malformed date', () => {
    expect(() => postponeTo(DUE_OVERDUE, { kind: 'date', date: '20/10/2026' }, NOW)).toThrow();
  });
  it('date input value is the Riyadh day', () => {
    expect(toDateInput(new Date('2026-10-07T21:30:00Z'))).toBe('2026-10-08');
  });
});
