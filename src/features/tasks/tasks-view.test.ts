import { describe, expect, it } from 'vitest';
import type { Stage } from '@/components/ui/stages';
import type { TaskRow } from '@/data/tasks';
import type { TaskKind } from '@/data/tasks-meta';
import { doneTodayCount, groupTasks, lastFollowupText, lateText, openCountByLead, openTasksText, pendingDecisions, sectionOf, showsDecision, upcomingText, whenText } from './tasks-view';

// Saturday 10 October 2026, 10:00 in Riyadh
const NOW = new Date('2026-10-10T07:00:00Z');
/** `days` after today at `hour` (Riyadh) */
const at = (days: number, hour: number) => new Date(Date.UTC(2026, 9, 10 + days) + (hour - 3) * 3_600_000);

function task(id: string, o: { kind?: TaskKind; due?: Date; done?: Date | null; lead?: string; stage?: Stage } = {}): TaskRow {
  return {
    id,
    title: `مهمة ${id}`,
    kind: o.kind ?? 'custom',
    dueAt: o.due ?? at(0, 16),
    doneAt: o.done ?? null,
    preview: null,
    lead: { id: o.lead ?? `lead-${id}`, businessName: 'مطعم ريدان', stage: o.stage ?? 'contacted', phone: '966551234567', doNotContact: false, contactName: null },
  };
}

describe('sections', () => {
  it('before today, today, later — by the Riyadh day', () => {
    expect(sectionOf(at(-1, 23.9), NOW)).toBe('late');
    expect(sectionOf(at(0, 0.1), NOW)).toBe('today');
    expect(sectionOf(at(0, 23.9), NOW)).toBe('today');
    expect(sectionOf(at(1, 0.1), NOW)).toBe('next');
  });

  it('groups by due time; a task done today stays in its section', () => {
    const g = groupTasks([task('c', { due: at(3, 10) }), task('b', { due: at(0, 12), done: NOW }), task('a', { due: at(0, 9) }), task('z', { due: at(-2, 16) })], NOW);
    expect(g.late.map((t) => t.id)).toEqual(['z']);
    expect(g.today.map((t) => t.id)).toEqual(['a', 'b']);
    expect(g.next.map((t) => t.id)).toEqual(['c']);
  });
});

describe('times (Tasks.dc.html)', () => {
  it('overdue in words', () => {
    expect(lateText(at(-1, 16), NOW)).toBe('أمس');
    expect(lateText(at(-2, 16), NOW)).toBe('منذ يومين');
    expect(lateText(at(-3, 16), NOW)).toBe('منذ 3 أيام');
    expect(lateText(at(-12, 16), NOW)).toBe('منذ 12 يوماً');
  });

  it('today as a time, upcoming as a day', () => {
    expect(whenText({ dueAt: at(0, 10) }, NOW)).toBe('10:00 ص');
    expect(whenText({ dueAt: at(0, 16.5) }, NOW)).toBe('4:30 م');
    expect(upcomingText(at(1, 10), NOW)).toBe('غداً 10:00 ص');
    expect(upcomingText(at(5, 16.5), NOW)).toBe('الخميس 4:30 م');
    expect(upcomingText(at(7, 10), NOW)).toBe('السبت 17 أكتوبر');
    expect(whenText({ dueAt: at(-2, 10) }, NOW)).toBe('منذ يومين');
  });

  it('open tasks in the desktop header, with Arabic number agreement', () => {
    expect([0, 1, 2, 3, 10, 11, 103].map(openTasksText)).toEqual([
      'لا مهام مفتوحة',
      'مهمة واحدة مفتوحة',
      'مهمتان مفتوحتان',
      '3 مهام مفتوحة',
      '10 مهام مفتوحة',
      '11 مهمة مفتوحة',
      '103 مهام مفتوحة',
    ]);
  });

  it('counts the tasks completed today', () => {
    expect(doneTodayCount([task('a', { done: NOW }), task('b'), task('c', { done: NOW })])).toBe(2);
  });
});

describe('day-7 decision (flow 2.4)', () => {
  const f7 = (o: Parameters<typeof task>[1] = {}) => task('f7', { kind: 'followup_7', lead: 'L', ...o });

  it('an open «متابعة ثانية» once its day has passed, for a lead still in «تم الإرسال»', () => {
    expect(showsDecision(f7({ due: at(-1, 19) }), 1, NOW)).toBe(true);
    expect(showsDecision(f7({ due: at(0, 9) }), 1, NOW)).toBe(false);
    expect(showsDecision(f7({ due: at(-1, 19), stage: 'replied' }), 1, NOW)).toBe(false);
    expect(showsDecision(task('x', { kind: 'followup_3', due: at(-5, 19) }), 1, NOW)).toBe(false);
  });

  it('a done one while nothing else is planned for the lead', () => {
    expect(showsDecision(f7({ due: at(0, 9), done: NOW }), 0, NOW)).toBe(true);
    // «أجّل 30 يوماً» left a «أعد المحاولة»
    expect(showsDecision(f7({ due: at(0, 9), done: NOW }), 1, NOW)).toBe(false);
  });

  it('earlier last follow-ups: the latest per lead, not twice, not when planned', () => {
    const last = [
      task('old', { kind: 'followup_7', lead: 'A', due: at(-40, 10), done: at(-40, 11) }),
      task('new', { kind: 'followup_7', lead: 'A', due: at(-3, 10), done: at(-3, 11) }),
      task('b', { kind: 'followup_7', lead: 'B', due: at(-2, 10), done: at(-2, 11) }),
      task('c', { kind: 'followup_7', lead: 'C', due: at(-2, 10), done: at(-2, 11) }),
      task('d', { kind: 'followup_7', lead: 'D', due: at(-2, 10), done: at(-2, 11) }),
    ];
    const list = [
      // B already has a retry planned
      task('r', { kind: 'retry', lead: 'B', due: at(28, 10) }),
      // C's follow-up is in the list itself (a new cycle)
      task('c2', { kind: 'followup_7', lead: 'C', due: at(-1, 10) }),
    ];
    expect(pendingDecisions(last, list, NOW).map((t) => t.id)).toEqual(['d', 'new']);
    expect(openCountByLead(list)).toEqual(new Map([['B', 1], ['C', 1]]));
  });

  it('decision rows say when the last follow-up went', () => {
    expect(lastFollowupText(at(0, 8), NOW)).toBe('آخر متابعة اليوم');
    expect(lastFollowupText(at(-1, 8), NOW)).toBe('آخر متابعة أمس');
    expect(lastFollowupText(at(-3, 8), NOW)).toBe('آخر متابعة قبل 3 أيام');
  });
});
