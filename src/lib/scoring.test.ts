import { describe, expect, it } from 'vitest';
import { answeredCount, computeScore, parseChecklist, weaknesses, type ChecklistItem } from './scoring';
import { computePriority, defaultDecisionMaker } from './priority';

// The 13 general items and weights from brief.md (sum 100)
const GENERAL: ChecklistItem[] = [
  { id: 'g1', label: 'التقييم 4.3 أو أعلى', weight: 10, group: 'خرائط قوقل' },
  { id: 'g2', label: 'عدد مراجعات كافٍ', weight: 5, group: 'خرائط قوقل' },
  { id: 'g3', label: 'يرد على المراجعات', weight: 5, group: 'خرائط قوقل', weakness: 'لا يرد على مراجعات قوقل' },
  { id: 'g4', label: 'صور حديثة', weight: 5, group: 'خرائط قوقل' },
  { id: 's1', label: 'حساب نشط', weight: 10, group: 'السوشيال ميديا' },
  { id: 's2', label: 'جودة التصوير', weight: 10, group: 'السوشيال ميديا' },
  { id: 's3', label: 'ينشر فيديو أو ريلز', weight: 10, group: 'السوشيال ميديا', weakness: 'لا ينشر فيديو أو ريلز' },
  { id: 's4', label: 'هوية موحدة', weight: 5, group: 'السوشيال ميديا' },
  { id: 'ad', label: 'يعلن', weight: 10, group: 'إعلانات' },
  { id: 'web', label: 'موقع', weight: 10, group: 'موقع' },
  { id: 'wa', label: 'واتساب أعمال', weight: 5, group: 'واتساب' },
  { id: 'shop', label: 'لوحة ومنيو', weight: 10, group: 'داخل المحل' },
  { id: 'off', label: 'عروض', weight: 5, group: 'داخل المحل' },
];

describe('computeScore: yes = weight, partial = half, no = 0', () => {
  it('all yes = 100, all no = 0, nothing answered = 0', () => {
    expect(computeScore(GENERAL, Object.fromEntries(GENERAL.map((i) => [i.id, 'yes'])))).toBe(100);
    expect(computeScore(GENERAL, Object.fromEntries(GENERAL.map((i) => [i.id, 'no'])))).toBe(0);
    expect(computeScore(GENERAL, {})).toBe(0);
  });
  it('all partial = 50', () => {
    expect(computeScore(GENERAL, Object.fromEntries(GENERAL.map((i) => [i.id, 'partial'])))).toBe(50);
  });
  it('a single partial on a 10-point item = 5', () => {
    expect(computeScore(GENERAL, { g1: 'partial' })).toBe(5);
  });
  it('the NewLead2 design example scores 48', () => {
    // g1 yes 10, g2 partial 2.5, g3 no, g4 partial 2.5, s1 partial 5, s2 yes 10, s3 no, s4 partial 2.5,
    // ad no, web no, wa yes 5, shop yes 10, off no  → 47.5 → 48
    const a = { g1: 'yes', g2: 'partial', g3: 'no', g4: 'partial', s1: 'partial', s2: 'yes', s3: 'no', s4: 'partial', ad: 'no', web: 'no', wa: 'yes', shop: 'yes', off: 'no' } as const;
    expect(computeScore(GENERAL, a)).toBe(48);
    expect(answeredCount(GENERAL, a)).toBe(13);
  });
  it('specific (activity) answers never change the score', () => {
    expect(computeScore(GENERAL, { g1: 'yes', r1: 'yes', r2: 'no' })).toBe(10);
  });
  it('weights that do not sum to 100 are scaled to 0–100', () => {
    const two: ChecklistItem[] = [
      { id: 'a', label: 'a', weight: 30, group: '' },
      { id: 'b', label: 'b', weight: 30, group: '' },
    ];
    expect(computeScore(two, { a: 'yes' })).toBe(50);
    expect(computeScore(two, { a: 'yes', b: 'partial' })).toBe(75);
  });
});

describe('weaknesses', () => {
  it('no and partial become weaknesses, most points lost first, using the weakness phrase', () => {
    const w = weaknesses(GENERAL, { g1: 'yes', g3: 'no', s3: 'no', s1: 'partial', g4: 'partial' });
    expect(w.map((x) => x.id)).toEqual(['s3', 'g3', 's1', 'g4']);
    expect(w[0]).toEqual({ id: 's3', text: 'لا ينشر فيديو أو ريلز', answer: 'no', lost: 10 });
    expect(w.find((x) => x.id === 'g4')?.text).toBe('صور حديثة');
  });
  it('unanswered items are not weaknesses', () => {
    expect(weaknesses(GENERAL, {})).toEqual([]);
  });
});

describe('computePriority', () => {
  it.each([
    [0, true, 'hot'],
    [49, true, 'hot'],
    [49, false, 'warm'],
    [50, true, 'warm'],
    [69, false, 'warm'],
    [70, true, 'cold'],
    [100, false, 'cold'],
  ] as const)('score %i, decision maker %s → %s', (score, dm, p) => {
    expect(computePriority(score, dm)).toBe(p);
  });
  it('a reply makes any lead hot', () => {
    expect(computePriority(90, false, true)).toBe('hot');
  });
  it('the owner decides by default', () => {
    expect(defaultDecisionMaker('owner')).toBe(true);
    expect(defaultDecisionMaker('manager')).toBe(false);
    expect(defaultDecisionMaker(null)).toBe(false);
  });
});

describe('parseChecklist', () => {
  it('keeps valid items and drops malformed ones', () => {
    const c = parseChecklist({ general: [{ id: 'g1', label: 'x', weight: 10, group: 'g' }, { id: 2 }], specific: [{ id: 'r1', label: 'y' }, null] });
    expect(c.general).toHaveLength(1);
    expect(c.specific).toEqual([{ id: 'r1', label: 'y' }]);
  });
  it('handles null', () => {
    expect(parseChecklist(null)).toEqual({ general: [], specific: [] });
  });
});
