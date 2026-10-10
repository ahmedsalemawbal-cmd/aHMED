import { latinDigits } from '@/lib/leads-list';
import { normalizePhone } from '@/lib/phone';
import { computePriority, defaultDecisionMaker } from '@/lib/priority';
import { computeScore, weaknesses as findWeaknesses, type Checklist } from '@/lib/scoring';
import { expectedMonthly, suggestServices, type CatalogService, type Suggestion } from '@/lib/suggest';
import type { ActivityType } from '@/data/catalog';
import type { Json } from '@/lib/database.types';
import type { NewLeadDraft } from './draft';

export interface Derived {
  activity: ActivityType | null;
  checklist: Checklist;
  score: number;
  weaknesses: ReturnType<typeof findWeaknesses>;
  suggestions: Suggestion[];
  /** services shown in step 4: suggestions, then services added from the catalogue */
  shown: { service: CatalogService; reason: string; selected: boolean }[];
  expected: number;
}

/** Everything the wizard computes from the draft: score, weaknesses, suggestions, expected value. */
export function derive(d: NewLeadDraft, activities: ActivityType[], services: CatalogService[]): Derived {
  const activity = activities.find((a) => a.id === d.activityTypeId) ?? null;
  const checklist = activity?.checklist ?? { general: [], specific: [] };
  const score = computeScore(checklist.general, d.answers);
  const weak = findWeaknesses(checklist.general, d.answers);
  const suggestions = suggestServices(services, activity ? { id: activity.id, name: activity.name, defaultServiceIds: activity.defaultServiceIds } : null, weak);
  const shownBase = suggestions.map((s) => ({ service: s.service, reason: s.reason, selected: d.selected ? (d.selected[s.service.id] ?? false) : s.selected }));
  const added = d.addedServiceIds
    .filter((id) => !shownBase.some((s) => s.service.id === id))
    .flatMap((id) => {
      const svc = services.find((x) => x.id === id);
      return svc ? [{ service: svc, reason: 'أضفتها من الكتالوج', selected: d.selected ? (d.selected[id] ?? true) : true }] : [];
    });
  const shown = [...shownBase, ...added];
  const expected = expectedMonthly(shown.filter((s) => s.selected).map((s) => s.service));
  return { activity, checklist, score, weaknesses: weak, suggestions, shown, expected };
}

/** «2,500» or «٢٥٠٠» as typed → 2500; empty → null (no expected value). */
export function parseExpected(typed: string): number | null {
  const digits = latinDigits(typed).replace(/[^\d.]/g, '');
  if (!digits) return null;
  const n = Number(digits);
  return Number.isFinite(n) ? n : null;
}

export type LeadPayload = { [key: string]: Json };
export type PayloadResult = { ok: true; payload: LeadPayload } | { ok: false; step: 1 | 3; error: string };

/** The JSON sent to create_lead_from_visit. */
export function buildPayload(d: NewLeadDraft, x: Derived): PayloadResult {
  const name = d.businessName.trim();
  if (!name) return { ok: false, step: 1, error: 'اكتب اسم المحل.' };
  if (!d.activityTypeId) return { ok: false, step: 1, error: 'اختر نوع النشاط.' };
  const phone = normalizePhone(d.phone);
  if (!phone.ok) return { ok: false, step: 1, error: phone.error };
  if (!d.keyObservation.trim()) return { ok: false, step: 3, error: 'اكتب الملاحظة الأبرز. سطر واحد محدد من الزيارة.' };
  const dm = defaultDecisionMaker(d.contactRole);
  const expectedRaw = d.expectedValue === null ? x.expected : parseExpected(d.expectedValue);
  return {
    ok: true,
    payload: {
      id: d.id,
      business_name: name,
      activity_type_id: d.activityTypeId,
      contact_name: d.contactName.trim(),
      contact_role: d.contactRole ?? '',
      phone_e164: phone.e164 ?? '',
      is_decision_maker: dm,
      best_contact_time: d.bestTime ?? '',
      wa_consent: d.waConsent,
      lat: d.location?.lat ?? null,
      lng: d.location?.lng ?? null,
      score: x.score,
      priority: computePriority(x.score, dm),
      expected_value: expectedRaw,
      key_observation: d.keyObservation.trim(),
      notes: d.notes.trim(),
      answers: d.answers,
      weaknesses: x.weaknesses.map((w) => w.id),
      services: x.shown.map((s) => ({ service_id: s.service.id, status: s.selected ? 'suggested' : 'dropped', note: s.reason })),
    },
  };
}
