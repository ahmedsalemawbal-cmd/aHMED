import { describe, expect, it } from 'vitest';
import type { Stage } from '@/components/ui/stages';
import type { LeadRow } from '@/lib/leads-list';
import { buildColumns, columnSumText, defaultStage, filterByActivity, lostToggleText, openDealsText, openSummary, parseStage, stageSummaryText, stageValueText, TAB_STAGES, toCard } from './board';

// Saturday 10 October 2026, 10:00 in Riyadh
const NOW = new Date('2026-10-10T07:00:00Z');
const daysAgo = (n: number) => new Date(NOW.getTime() - n * 86_400_000);

function row(id: string, stage: Stage, o: Partial<LeadRow> = {}): LeadRow {
  return {
    id,
    businessName: `محل ${id}`,
    activityId: 'a-rest',
    activity: 'مطعم',
    contactName: null,
    contactRole: null,
    phone: null,
    stage,
    score: null,
    priority: null,
    source: 'visit',
    expectedValue: null,
    createdAt: daysAgo(30),
    lastContactAt: null,
    stageChangedAt: daysAgo(1),
    doNotContact: false,
    waConsent: false,
    next: null,
    ...o,
  };
}

describe('toCard', () => {
  it('value as SAR or a dash, days in stage, and the stale flag', () => {
    expect(toCard(row('1', 'contacted', { expectedValue: 2500, stageChangedAt: daysAgo(9) }), NOW)).toMatchObject({ value: '2,500 ر.س', daysInStage: 9, stale: true });
    expect(toCard(row('2', 'contacted', { stageChangedAt: daysAgo(7) }), NOW)).toMatchObject({ value: '—', daysInStage: 7, stale: false });
    // a closed deal never goes stale
    expect(toCard(row('3', 'won', { stageChangedAt: daysAgo(40) }), NOW).stale).toBe(false);
  });
});

describe('buildColumns', () => {
  const rows = [
    row('a', 'contacted', { expectedValue: 2500, stageChangedAt: daysAgo(3) }),
    row('b', 'contacted', { expectedValue: 1500, stageChangedAt: daysAgo(0) }),
    row('c', 'contacted', { stageChangedAt: daysAgo(9) }),
    row('d', 'won', { expectedValue: 1900 }),
    row('e', 'lost'),
  ];
  const cols = buildColumns(rows, NOW);

  it('has every stage, counted and summed', () => {
    expect(Object.keys(cols)).toHaveLength(8);
    expect(cols.contacted).toMatchObject({ label: 'تم الإرسال', count: 3, sum: 4000 });
    expect(cols.won).toMatchObject({ count: 1, sum: 1900 });
    expect(cols.lost.count).toBe(1);
    expect(cols.replied).toMatchObject({ count: 0, sum: 0, cards: [] });
  });

  it('puts the most recently moved card first', () => {
    expect(cols.contacted.cards.map((c) => c.id)).toEqual(['b', 'a', 'c']);
  });
});

describe('filterByActivity', () => {
  it('«الكل» keeps everything; an id keeps that activity only', () => {
    const rows = [row('1', 'visited'), row('2', 'visited', { activityId: 'a-cafe' })];
    expect(filterByActivity(rows, 'all')).toHaveLength(2);
    expect(filterByActivity(rows, 'a-cafe').map((r) => r.id)).toEqual(['2']);
    expect(filterByActivity(rows, 'gone')).toEqual([]);
  });
});

describe('openSummary', () => {
  it('counts visited … proposal only', () => {
    const rows = (['not_visited', 'visited', 'contacted', 'replied', 'meeting', 'proposal', 'won', 'lost'] as Stage[]).map((s, i) => row(String(i), s, { expectedValue: 1000 }));
    expect(openSummary(rows)).toEqual({ count: 5, value: 5000 });
    expect(openSummary([])).toEqual({ count: 0, value: 0 });
  });
});

describe('texts', () => {
  it('open deals with Arabic number agreement', () => {
    expect(openDealsText(0)).toBe('لا صفقات مفتوحة');
    expect(openDealsText(1)).toBe('صفقة واحدة مفتوحة');
    expect(openDealsText(2)).toBe('صفقتان مفتوحتان');
    expect(openDealsText(3)).toBe('3 صفقات مفتوحة');
    expect(openDealsText(10)).toBe('10 صفقات مفتوحة');
    expect(openDealsText(12)).toBe('12 صفقة مفتوحة');
    expect(openDealsText(100)).toBe('100 صفقة مفتوحة');
    expect(openDealsText(103)).toBe('103 صفقات مفتوحة');
  });

  it('column sums and the phone summary line', () => {
    expect(columnSumText(28500)).toBe('28,500 ر.س متوقعة');
    expect(columnSumText(0)).toBe('بدون قيمة بعد');
    expect(stageSummaryText({ label: 'تم الإرسال', count: 4 })).toBe('تم الإرسال · 4 عملاء');
    expect(stageSummaryText({ label: 'رد', count: 1 })).toBe('رد · عميل واحد');
    expect(stageSummaryText({ label: 'اجتماع', count: 2 })).toBe('اجتماع · عميلان');
    expect(stageSummaryText({ label: 'تمت الزيارة', count: 12 })).toBe('تمت الزيارة · 12 عميلاً');
    expect(stageSummaryText({ label: 'خسارة', count: 0 })).toBe('خسارة · لا عملاء');
    expect(stageValueText(9000)).toBe('9,000');
    expect(stageValueText(0)).toBeNull();
    expect(lostToggleText(6)).toBe('خسارة · 6');
  });
});

describe('stage selection', () => {
  it('?stage= accepts known stages only', () => {
    expect(parseStage('proposal')).toBe('proposal');
    expect(parseStage('lost')).toBe('lost');
    expect(parseStage('nope')).toBeNull();
    expect(parseStage(null)).toBeNull();
  });

  it('opens the first stage that has leads; «خسارة» is the last tab', () => {
    expect(TAB_STAGES.at(-1)).toBe('lost');
    expect(defaultStage(buildColumns([row('1', 'replied'), row('2', 'won')], NOW))).toBe('replied');
    expect(defaultStage(buildColumns([row('1', 'lost')], NOW))).toBe('lost');
    expect(defaultStage(buildColumns([], NOW))).toBe('visited');
  });
});
