import { describe, expect, it } from 'vitest';
import { clearVisitDraft, emptyVisitDraft, isVisitStarted, loadVisitDraft, saveVisitDraft, visitDraftKey } from './draft';

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

describe('visit draft', () => {
  const start = emptyVisitDraft('v1', 'l1', { answers: { g1: 'yes' }, expectedValue: 2500 });

  it('starts from the last answers and the lead value; untouched is not «started»', () => {
    expect(start).toMatchObject({ id: 'v1', leadId: 'l1', step: 1, answers: { g1: 'yes' }, expectedValue: '2500', selected: null });
    expect(isVisitStarted(start, start)).toBe(false);
    expect(isVisitStarted({ ...start, answers: { g1: 'no' } }, start)).toBe(true);
    expect(isVisitStarted({ ...start, keyObservation: 'x' }, start)).toBe(true);
    expect(isVisitStarted({ ...start, expectedValue: '3000' }, start)).toBe(true);
  });

  it('saves per lead, restores, ignores another lead and junk, clears', () => {
    const s = memory();
    const saved = saveVisitDraft({ ...start, keyObservation: 'ملاحظة' }, s);
    expect(saved.savedAt).not.toBeNull();
    expect(loadVisitDraft('l1', s)).toMatchObject({ id: 'v1', keyObservation: 'ملاحظة' });
    expect(loadVisitDraft('l2', s)).toBeNull();
    s.setItem(visitDraftKey('l3'), '{bad json');
    expect(loadVisitDraft('l3', s)).toBeNull();
    clearVisitDraft('l1', s);
    expect(loadVisitDraft('l1', s)).toBeNull();
  });
});
