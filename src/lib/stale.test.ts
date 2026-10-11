import { describe, expect, it } from 'vitest';
import { STAGES } from '@/components/ui/stages';
import { daysInStage, isStale, STALE_AFTER_DAYS } from './stale';

// Saturday 10 October 2026, 10:00 in Riyadh
const NOW = new Date('2026-10-10T07:00:00Z');

describe('daysInStage', () => {
  it('counts Riyadh calendar days, not 24-hour blocks', () => {
    // Friday 23:30 Riyadh is «yesterday» although only 10.5 hours ago
    expect(daysInStage(new Date('2026-10-09T20:30:00Z'), NOW)).toBe(1);
    // Saturday 00:10 Riyadh (Friday 21:10 UTC) is today
    expect(daysInStage(new Date('2026-10-09T21:10:00Z'), NOW)).toBe(0);
  });

  it('is zero on the day of the change and grows by one each day', () => {
    expect(daysInStage(new Date('2026-10-10T06:59:00Z'), NOW)).toBe(0);
    expect(daysInStage(new Date('2026-10-03T07:00:00Z'), NOW)).toBe(7);
    expect(daysInStage(new Date('2026-10-01T07:00:00Z'), NOW)).toBe(9);
  });

  it('never goes negative when the clock of the device is behind the server', () => {
    expect(daysInStage(new Date('2026-10-11T07:00:00Z'), NOW)).toBe(0);
  });
});

describe('isStale', () => {
  it('more than 7 days: 7 is not stale, 8 is', () => {
    expect(STALE_AFTER_DAYS).toBe(7);
    expect(isStale('contacted', 7)).toBe(false);
    expect(isStale('contacted', 8)).toBe(true);
    expect(isStale('proposal', 11)).toBe(true);
  });

  it('only open stages go stale', () => {
    const stale = STAGES.filter((s) => isStale(s.id, 30)).map((s) => s.id);
    expect(stale).toEqual(['visited', 'contacted', 'replied', 'meeting', 'proposal']);
  });
});
