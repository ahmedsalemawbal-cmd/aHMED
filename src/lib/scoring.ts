/**
 * Digital-presence score out of 100 (brief.md «نظام التقييم»):
 * yes = full weight, partial = half, no = 0. Activity-specific items are
 * yes/no and never count. If the user edits weights so they no longer sum to
 * 100, the score is scaled back to 0–100.
 */
export type Answer = 'yes' | 'partial' | 'no';
export type Answers = Record<string, Answer | undefined>;

export interface ChecklistItem {
  id: string;
  label: string;
  weight: number;
  group: string;
  /** negative phrasing used as a weakness: «لا ينشر فيديو أو ريلز» */
  weakness?: string;
}

export interface SpecificItem {
  id: string;
  label: string;
}

export interface Checklist {
  general: ChecklistItem[];
  specific: SpecificItem[];
}

export function itemPoints(item: ChecklistItem, answer: Answer | undefined): number {
  if (answer === 'yes') return item.weight;
  if (answer === 'partial') return item.weight / 2;
  return 0;
}

export function computeScore(general: ChecklistItem[], answers: Answers): number {
  const total = general.reduce((s, i) => s + Math.max(0, i.weight), 0);
  if (total <= 0) return 0;
  const got = general.reduce((s, i) => s + itemPoints(i, answers[i.id]), 0);
  return Math.max(0, Math.min(100, Math.round((got / total) * 100)));
}

export function answeredCount(general: ChecklistItem[], answers: Answers): number {
  return general.filter((i) => answers[i.id] !== undefined).length;
}

export interface Weakness {
  id: string;
  text: string;
  answer: 'partial' | 'no';
  /** points lost on this item */
  lost: number;
}

/**
 * Every general item answered «لا» or «جزئي» is a weakness, most points lost first.
 * Unanswered items are not weaknesses.
 */
export function weaknesses(general: ChecklistItem[], answers: Answers): Weakness[] {
  return general
    .flatMap((i, idx): (Weakness & { idx: number })[] => {
      const a = answers[i.id];
      if (a !== 'no' && a !== 'partial') return [];
      return [{ id: i.id, text: i.weakness ?? i.label, answer: a, lost: i.weight - itemPoints(i, a), idx }];
    })
    .sort((x, y) => y.lost - x.lost || x.idx - y.idx)
    .map((w) => ({ id: w.id, text: w.text, answer: w.answer, lost: w.lost }));
}

/** Parses the activity_types.checklist JSON safely. */
export function parseChecklist(json: unknown): Checklist {
  const obj = (json ?? {}) as { general?: unknown; specific?: unknown };
  const general = Array.isArray(obj.general) ? obj.general : [];
  const specific = Array.isArray(obj.specific) ? obj.specific : [];
  return {
    general: general.flatMap((g): ChecklistItem[] => {
      if (!g || typeof g !== 'object') return [];
      const r = g as Partial<ChecklistItem>;
      if (typeof r.id !== 'string' || typeof r.label !== 'string' || typeof r.weight !== 'number') return [];
      return [{ id: r.id, label: r.label, weight: r.weight, group: typeof r.group === 'string' ? r.group : '', weakness: typeof r.weakness === 'string' ? r.weakness : undefined }];
    }),
    specific: specific.flatMap((g): SpecificItem[] => {
      if (!g || typeof g !== 'object') return [];
      const r = g as Partial<SpecificItem>;
      return typeof r.id === 'string' && typeof r.label === 'string' ? [{ id: r.id, label: r.label }] : [];
    }),
  };
}
