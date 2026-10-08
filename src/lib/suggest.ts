import type { Weakness } from './scoring';

export interface CatalogService {
  id: string;
  name: string;
  billing: 'monthly' | 'one_time';
  priceFrom: number | null;
  activityTypeIds: string[];
  weaknessItemIds: string[];
  isActive: boolean;
}

export interface Suggestion {
  service: CatalogService;
  /** «لأنه: لا ينشر فيديو أو ريلز» or «تُطلب غالباً لنشاط «مطعم»» */
  reason: string;
  selected: boolean;
}

/** How many suggestions are pre-selected (NewLead4.dc.html shows 3 checked). */
export const PRESELECT = 3;

/**
 * Services suggested from the weaknesses (most points lost first), then the
 * activity's usual services. Inactive services and services not linked to the
 * activity are skipped. The first PRESELECT are pre-selected.
 */
export function suggestServices(
  catalog: CatalogService[],
  activity: { id: string; name: string; defaultServiceIds: string[] } | null,
  weak: Weakness[],
): Suggestion[] {
  const fits = (s: CatalogService) => s.isActive && (!activity || s.activityTypeIds.length === 0 || s.activityTypeIds.includes(activity.id));
  const out: Suggestion[] = [];
  const seen = new Set<string>();
  for (const w of weak) {
    for (const s of catalog) {
      if (seen.has(s.id) || !fits(s) || !s.weaknessItemIds.includes(w.id)) continue;
      seen.add(s.id);
      out.push({ service: s, reason: `لأنه: ${w.text}`, selected: false });
    }
  }
  if (activity) {
    for (const id of activity.defaultServiceIds) {
      const s = catalog.find((x) => x.id === id);
      if (!s || seen.has(s.id) || !fits(s)) continue;
      seen.add(s.id);
      out.push({ service: s, reason: `تُطلب غالباً لنشاط «${activity.name}»`, selected: false });
    }
  }
  return out.map((s, i) => ({ ...s, selected: i < PRESELECT }));
}

/** Default expected value: the monthly prices of the selected services. */
export function expectedMonthly(selected: CatalogService[]): number {
  return selected.filter((s) => s.billing === 'monthly').reduce((sum, s) => sum + (s.priceFrom ?? 0), 0);
}
