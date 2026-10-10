import type { Answer } from '@/lib/scoring';

/**
 * «زيارة جديدة» state, saved on every change under a key per lead, so an
 * interrupted visit comes back (like the new-lead wizard draft).
 */
export interface VisitDraft {
  v: 1;
  /** client visit id: a retried save never adds the visit twice */
  id: string;
  leadId: string;
  step: 1 | 2 | 3;
  answers: Record<string, Answer>;
  keyObservation: string;
  notes: string;
  /** null until the user changes the pre-selected services */
  selected: Record<string, boolean> | null;
  addedServiceIds: string[];
  /** null = follow the selected services */
  expectedValue: string | null;
  savedAt: string | null;
}

export function visitDraftKey(leadId: string): string {
  return `maidani-draft-visit-${leadId}`;
}

/**
 * A fresh visit starts from the last assessment's answers (change what changed)
 * and the lead's expected value as it is.
 */
export function emptyVisitDraft(id: string, leadId: string, start: { answers?: Record<string, Answer>; expectedValue?: number | null } = {}): VisitDraft {
  return {
    v: 1,
    id,
    leadId,
    step: 1,
    answers: { ...(start.answers ?? {}) },
    keyObservation: '',
    notes: '',
    selected: null,
    addedServiceIds: [],
    expectedValue: start.expectedValue == null ? null : String(start.expectedValue),
    savedAt: null,
  };
}

function sameAnswers(a: Record<string, Answer>, b: Record<string, Answer>): boolean {
  const ka = Object.keys(a);
  return ka.length === Object.keys(b).length && ka.every((k) => a[k] === b[k]);
}

/** Something the user would miss: typed notes, changed answers, services or value. */
export function isVisitStarted(d: VisitDraft, start: VisitDraft): boolean {
  return Boolean(
    d.keyObservation.trim() ||
      d.notes.trim() ||
      d.selected ||
      d.addedServiceIds.length ||
      d.expectedValue !== start.expectedValue ||
      !sameAnswers(d.answers, start.answers),
  );
}

export function loadVisitDraft(leadId: string, storage: Pick<Storage, 'getItem'> = localStorage): VisitDraft | null {
  try {
    const raw = storage.getItem(visitDraftKey(leadId));
    if (!raw) return null;
    const d = JSON.parse(raw) as Partial<VisitDraft>;
    if (d.v !== 1 || typeof d.id !== 'string' || d.leadId !== leadId) return null;
    return { ...emptyVisitDraft(d.id, leadId), ...d };
  } catch {
    return null;
  }
}

export function saveVisitDraft(d: VisitDraft, storage: Pick<Storage, 'setItem'> = localStorage): VisitDraft {
  const next = { ...d, savedAt: new Date().toISOString() };
  try {
    storage.setItem(visitDraftKey(d.leadId), JSON.stringify(next));
  } catch {
    /* storage full or blocked: the draft stays in memory */
  }
  return next;
}

export function clearVisitDraft(leadId: string, storage: Pick<Storage, 'removeItem'> = localStorage) {
  try {
    storage.removeItem(visitDraftKey(leadId));
  } catch {
    /* ignore */
  }
}
