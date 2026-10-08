import { describe, expect, it } from 'vitest';
import { clearDraft, draftStatusText, emptyDraft, isStarted, loadDraft, saveDraft } from './draft';

function memory() {
  const m = new Map<string, string>();
  return {
    getItem: (k: string) => m.get(k) ?? null,
    setItem: (k: string, v: string) => {
      m.set(k, v);
    },
    removeItem: (k: string) => {
      m.delete(k);
    },
  };
}

describe('new-lead draft', () => {
  it('round-trips through storage and stamps savedAt', () => {
    const s = memory();
    const saved = saveDraft({ ...emptyDraft('x'), businessName: 'مطعم ريدان', step: 2, answers: { g1: 'yes' } }, s);
    expect(saved.savedAt).not.toBeNull();
    expect(loadDraft(s)).toMatchObject({ id: 'x', businessName: 'مطعم ريدان', step: 2, answers: { g1: 'yes' } });
    clearDraft(s);
    expect(loadDraft(s)).toBeNull();
  });
  it('ignores corrupt or old drafts', () => {
    const s = memory();
    s.setItem('maidani-draft-new-lead', '{broken');
    expect(loadDraft(s)).toBeNull();
    s.setItem('maidani-draft-new-lead', JSON.stringify({ v: 0, id: 'x' }));
    expect(loadDraft(s)).toBeNull();
  });
  it('fills fields added later with defaults', () => {
    const s = memory();
    s.setItem('maidani-draft-new-lead', JSON.stringify({ v: 1, id: 'x', businessName: 'م' }));
    expect(loadDraft(s)?.addedServiceIds).toEqual([]);
  });
  it('started only once something meaningful is entered', () => {
    expect(isStarted(emptyDraft('x'))).toBe(false);
    expect(isStarted({ ...emptyDraft('x'), phone: '05' })).toBe(true);
  });
  it('status text', () => {
    const now = new Date('2026-10-07T07:45:00Z');
    const t = () => '10:30 ص';
    expect(draftStatusText(null, now, t)).toBe('تُحفظ كمسودة تلقائياً');
    expect(draftStatusText('2026-10-07T07:44:40Z', now, t)).toBe('حُفظت كمسودة قبل لحظات');
    expect(draftStatusText('2026-10-07T07:43:00Z', now, t)).toBe('حُفظت كمسودة قبل دقيقتين');
    expect(draftStatusText('2026-10-07T07:40:00Z', now, t)).toBe('حُفظت كمسودة قبل 5 دقائق');
    expect(draftStatusText('2026-10-07T07:15:00Z', now, t)).toBe('حُفظت كمسودة الساعة 10:30 ص');
  });
});
