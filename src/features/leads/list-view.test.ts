import { describe, expect, it } from 'vitest';
import { DEFAULT_QUERY, stageCounts, type LeadRow } from '@/lib/leads-list';
import {
  aboutLead,
  activityOptions,
  ariaSort,
  canWhatsApp,
  chipLabel,
  exportToastText,
  hasFilters,
  lastContactText,
  listKey,
  leadsObjectText,
  leadsSubjectText,
  lostSheetTitle,
  lostToastText,
  messageHref,
  nextSort,
  nextWhen,
  scoreBarClass,
  selectionText,
  SORT_NAME,
  sortChoices,
  stageChipIds,
  stageToastText,
} from './list-view';

const H = 3600_000;
// Thursday 8 Oct 2026, 12:00 in Riyadh
const NOW = new Date('2026-10-08T09:00:00Z');

let n = 0;
function row(over: Partial<LeadRow> = {}): LeadRow {
  n++;
  return {
    id: `l${n.toString()}`,
    businessName: `محل ${n.toString()}`,
    activityId: 'a-rest',
    activity: 'مطعم',
    contactName: null,
    contactRole: null,
    phone: '966551234567',
    stage: 'visited',
    score: 50,
    priority: 'warm',
    source: 'visit',
    expectedValue: null,
    createdAt: new Date(NOW.getTime() - n * H),
    lastContactAt: null,
    stageChangedAt: NOW,
    doNotContact: false,
    waConsent: true,
    next: null,
    ...over,
  };
}

describe('selection bar agreement (DeskLeads «3 عملاء محددون»)', () => {
  it('one, two, 3–10, 11–99, hundreds', () => {
    expect(selectionText(1)).toBe('عميل واحد محدد');
    expect(selectionText(2)).toBe('عميلان محددان');
    expect(selectionText(3)).toBe('3 عملاء محددون');
    expect(selectionText(10)).toBe('10 عملاء محددون');
    expect(selectionText(11)).toBe('11 عميلاً محدداً');
    expect(selectionText(47)).toBe('47 عميلاً محدداً');
    expect(selectionText(100)).toBe('100 عميل محدد');
    expect(selectionText(103)).toBe('103 عملاء محددون');
    expect(selectionText(125)).toBe('125 عميلاً محدداً');
  });
});

const named = (n: number) => Array.from({ length: n }, (_, i) => ({ businessName: `محل ${(i + 1).toString()}` }));
const REDAN = [{ businessName: 'مطعم ريدان' }];

describe('bulk action texts', () => {
  it('a toast names one lead, and counts several (nominative)', () => {
    expect(leadsSubjectText(REDAN)).toBe('مطعم ريدان');
    expect(leadsSubjectText(named(2))).toBe('عميلان');
    expect(leadsSubjectText(named(3))).toBe('3 عملاء');
    expect(leadsSubjectText(named(11))).toBe('11 عميلاً');
  });
  it('stage toast names the stage and who moved', () => {
    expect(stageToastText('proposal', named(3))).toBe('المرحلة الآن: عرض سعر · 3 عملاء');
    expect(stageToastText('visited', REDAN)).toBe('المرحلة الآن: تمت الزيارة · مطعم ريدان');
    expect(stageToastText('contacted', named(12))).toBe('المرحلة الآن: تم الإرسال · 12 عميلاً');
  });
  it('lost: sheet title (object) and toast (subject)', () => {
    expect(lostSheetTitle(REDAN)).toBe('نقل مطعم ريدان إلى خسارة');
    expect(lostSheetTitle(named(2))).toBe('نقل عميلين إلى خسارة');
    expect(leadsObjectText(named(3))).toBe('3 عملاء');
    expect(lostToastText(named(3))).toBe('نُقل 3 عملاء إلى خسارة');
    expect(lostToastText(named(2))).toBe('نُقل عميلان إلى خسارة');
    expect(lostToastText(REDAN)).toBe('نُقل مطعم ريدان إلى خسارة');
  });
  it('export toast', () => {
    expect(exportToastText(REDAN)).toBe('صُدّر مطعم ريدان إلى ملف CSV');
    expect(exportToastText(named(2))).toBe('صُدّر عميلان إلى ملف CSV');
  });
  it('a lead page toast gets the shop name in the list', () => {
    expect(aboutLead({ title: 'حُدد الاجتماع', message: 'الخميس 8 أكتوبر · 4:30 م' }, REDAN[0] ?? { businessName: '' })).toEqual({
      title: 'حُدد الاجتماع · مطعم ريدان',
      message: 'الخميس 8 أكتوبر · 4:30 م',
    });
  });
});

describe('score bar colour', () => {
  it('<50 hot, 50–69 warm, ≥70 cold (design-system.md)', () => {
    expect(scoreBarClass(0)).toBe('bg-priority-hot');
    expect(scoreBarClass(49)).toBe('bg-priority-hot');
    expect(scoreBarClass(50)).toBe('bg-priority-warm');
    expect(scoreBarClass(69)).toBe('bg-priority-warm');
    expect(scoreBarClass(70)).toBe('bg-priority-cold');
    expect(scoreBarClass(100)).toBe('bg-priority-cold');
  });
});

describe('stage chips', () => {
  it('«الكل» then stages with leads in order; the selected one stays even at zero', () => {
    const counts = stageCounts([row({ stage: 'proposal' }), row({ stage: 'visited' }), row({ stage: 'visited' })]);
    expect(stageChipIds(counts, 'all')).toEqual(['all', 'visited', 'proposal']);
    expect(stageChipIds(counts, 'won')).toEqual(['all', 'visited', 'proposal', 'won']);
    expect(chipLabel('all')).toBe('الكل');
    expect(chipLabel('contacted')).toBe('تم الإرسال');
  });
});

describe('card mapping (like hotCard in Today)', () => {
  it('next action: overdue text once the moment passed, else when', () => {
    const late = row({ next: { id: 't1', title: 'أرسل الرسالة الأولى', kind: 'first_message', dueAt: new Date(NOW.getTime() - 48 * H) } });
    expect(nextWhen(late, NOW)).toEqual({ text: 'متأخرة منذ يومين', overdue: true });
    const soon = row({ next: { id: 't2', title: 'متابعة أولى', kind: 'followup_3', dueAt: new Date(NOW.getTime() + 4 * H) } });
    expect(nextWhen(soon, NOW)).toEqual({ text: 'اليوم 4:00 م', overdue: false });
    expect(nextWhen(row(), NOW)).toBeNull();
  });
  it('last contact', () => {
    expect(lastContactText(row({ lastContactAt: new Date(NOW.getTime() - 24 * H) }), NOW)).toBe('أمس');
    expect(lastContactText(row(), NOW)).toBeNull();
  });
  it('WhatsApp needs a number and no «لا تتواصل»', () => {
    expect(canWhatsApp(row())).toBe(true);
    expect(canWhatsApp(row({ phone: null }))).toBe(false);
    expect(canWhatsApp(row({ doNotContact: true }))).toBe(false);
  });
  it('message link carries the next task only when it is a message', () => {
    expect(messageHref(row({ id: 'x', next: { id: 't9', title: 'متابعة', kind: 'followup_7', dueAt: NOW } }))).toBe('/leads/x/message?task=t9');
    expect(messageHref(row({ id: 'x', next: { id: 't9', title: 'حدد اجتماعاً', kind: 'schedule_meeting', dueAt: NOW } }))).toBe('/leads/x/message');
    expect(messageHref(row({ id: 'x' }))).toBe('/leads/x/message');
  });
});

describe('sorting headers', () => {
  it('same column flips, another starts at its natural direction', () => {
    expect(nextSort({ sort: 'next', dir: 'asc' }, 'score')).toEqual({ sort: 'score', dir: 'asc' });
    expect(nextSort({ sort: 'score', dir: 'asc' }, 'score')).toEqual({ sort: 'score', dir: 'desc' });
    expect(nextSort({ sort: 'score', dir: 'desc' }, 'score')).toEqual({ sort: 'score', dir: 'asc' });
    expect(nextSort({ sort: 'score', dir: 'asc' }, 'value')).toEqual({ sort: 'value', dir: 'desc' });
  });
  it('aria-sort only on the active column', () => {
    expect(ariaSort({ sort: 'score', dir: 'asc' }, 'score')).toBe('ascending');
    expect(ariaSort({ sort: 'score', dir: 'desc' }, 'score')).toBe('descending');
    expect(ariaSort({ sort: 'score', dir: 'asc' }, 'name')).toBe('none');
  });
  it('every sort key has a name', () => {
    expect(SORT_NAME.next).toBe('الإجراء القادم');
    expect(SORT_NAME.recent).toBe('الأحدث');
    expect(SORT_NAME.score).toBe('الدرجة');
    expect(SORT_NAME.value).toBe('القيمة المتوقعة');
  });
  it('the phone sheet offers its three sorts, plus a column sort that came in the link', () => {
    expect(sortChoices('next')).toEqual(['next', 'recent', 'score']);
    expect(sortChoices('score')).toEqual(['next', 'recent', 'score']);
    expect(sortChoices('value')).toEqual(['next', 'recent', 'score', 'value']);
  });
});

describe('filters', () => {
  it('activity options: distinct, named, sorted', () => {
    const opts = activityOptions([
      { activityId: 'a2', activity: 'مطعم' },
      { activityId: 'a1', activity: 'كافيه' },
      { activityId: 'a2', activity: 'مطعم' },
      { activityId: null, activity: '' },
    ]);
    expect(opts).toEqual([
      { id: 'a1', name: 'كافيه' },
      { id: 'a2', name: 'مطعم' },
    ]);
  });
  it('listKey changes with what is shown, not with the page or trailing spaces', () => {
    const base = listKey(DEFAULT_QUERY);
    expect(listKey({ ...DEFAULT_QUERY, page: 3 })).toBe(base);
    expect(listKey({ ...DEFAULT_QUERY, q: 'ريدان ' })).toBe(listKey({ ...DEFAULT_QUERY, q: 'ريدان' }));
    expect(listKey({ ...DEFAULT_QUERY, q: 'ريدان' })).not.toBe(base);
    expect(listKey({ ...DEFAULT_QUERY, stage: 'contacted' })).not.toBe(base);
    expect(listKey({ ...DEFAULT_QUERY, sort: 'score' })).not.toBe(base);
    expect(listKey({ ...DEFAULT_QUERY, dir: 'desc' })).not.toBe(base);
  });
  it('hasFilters', () => {
    expect(hasFilters(DEFAULT_QUERY)).toBe(false);
    expect(hasFilters({ ...DEFAULT_QUERY, q: '  ' })).toBe(false);
    expect(hasFilters({ ...DEFAULT_QUERY, q: 'ريدان' })).toBe(true);
    expect(hasFilters({ ...DEFAULT_QUERY, source: 'import' })).toBe(true);
    expect(hasFilters({ ...DEFAULT_QUERY, sort: 'score' })).toBe(false);
  });
});
