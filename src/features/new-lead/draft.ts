import type { Answer } from '@/lib/scoring';
import type { ContactTime } from '@/lib/followups';

export type Role = 'owner' | 'manager' | 'employee';

/** Wizard state saved on every change (brief.md: «حفظ تلقائي كمسودة عند كل خطوة»). */
export interface NewLeadDraft {
  v: 1;
  /** client id so a retried save never creates the lead twice */
  id: string;
  step: 1 | 2 | 3 | 4;
  businessName: string;
  activityTypeId: string | null;
  contactName: string;
  contactRole: Role | null;
  phone: string;
  waConsent: boolean;
  bestTime: ContactTime | null;
  location: { lat: number; lng: number; accuracy: number } | null;
  answers: Record<string, Answer>;
  keyObservation: string;
  notes: string;
  /** null until the user changes the suggested selection */
  selected: Record<string, boolean> | null;
  addedServiceIds: string[];
  /** null = follow the selected services */
  expectedValue: string | null;
  /** user confirmed a same-phone shop is another branch */
  allowDuplicate: boolean;
  savedAt: string | null;
}

const KEY = 'maidani-draft-new-lead';

export function emptyDraft(id: string): NewLeadDraft {
  return {
    v: 1,
    id,
    step: 1,
    businessName: '',
    activityTypeId: null,
    contactName: '',
    contactRole: null,
    phone: '',
    waConsent: false,
    bestTime: null,
    location: null,
    answers: {},
    keyObservation: '',
    notes: '',
    selected: null,
    addedServiceIds: [],
    expectedValue: null,
    allowDuplicate: false,
    savedAt: null,
  };
}

export function isStarted(d: NewLeadDraft): boolean {
  return Boolean(d.businessName.trim() || d.activityTypeId || d.phone.trim() || Object.keys(d.answers).length || d.keyObservation.trim());
}

export function loadDraft(storage: Pick<Storage, 'getItem'> = localStorage): NewLeadDraft | null {
  try {
    const raw = storage.getItem(KEY);
    if (!raw) return null;
    const d = JSON.parse(raw) as Partial<NewLeadDraft>;
    if (d.v !== 1 || typeof d.id !== 'string') return null;
    return { ...emptyDraft(d.id), ...d };
  } catch {
    return null;
  }
}

export function saveDraft(d: NewLeadDraft, storage: Pick<Storage, 'setItem'> = localStorage): NewLeadDraft {
  const next = { ...d, savedAt: new Date().toISOString() };
  try {
    storage.setItem(KEY, JSON.stringify(next));
  } catch {
    /* storage full or blocked: the draft stays in memory */
  }
  return next;
}

export function clearDraft(storage: Pick<Storage, 'removeItem'> = localStorage) {
  try {
    storage.removeItem(KEY);
  } catch {
    /* ignore */
  }
}

/** «حُفظت كمسودة قبل لحظات» / «… قبل 5 دقائق» / «… الساعة 10:45 ص» */
export function draftStatusText(savedAt: string | null, now: Date, formatTime: (d: Date) => string): string {
  if (!savedAt) return 'تُحفظ كمسودة تلقائياً';
  const at = new Date(savedAt);
  const mins = Math.floor((now.getTime() - at.getTime()) / 60_000);
  if (mins < 1) return 'حُفظت كمسودة قبل لحظات';
  if (mins === 1) return 'حُفظت كمسودة قبل دقيقة';
  if (mins === 2) return 'حُفظت كمسودة قبل دقيقتين';
  if (mins <= 10) return `حُفظت كمسودة قبل ${mins.toString()} دقائق`;
  return `حُفظت كمسودة الساعة ${formatTime(at)}`;
}
