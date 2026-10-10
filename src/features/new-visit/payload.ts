import type { ActivityType } from '@/data/catalog';
import type { LeadDetail, LeadService } from '@/data/lead';
import type { Json } from '@/lib/database.types';
import { computePriority } from '@/lib/priority';
import type { Answer } from '@/lib/scoring';
import type { CatalogService } from '@/lib/suggest';
import { emptyDraft, type NewLeadDraft } from '../new-lead/draft';
import { derive, parseExpected, type Derived } from '../new-lead/payload';
import { emptyVisitDraft, type VisitDraft } from './draft';

/** «زيارة جديدة» steps: the wizard's last three (NewLead2–4). */
export const VISIT_STEPS = ['التقييم', 'الملاحظات', 'الخدمات'] as const;

/**
 * A fresh visit starts from the last assessment's answers and the lead's
 * expected value, and keeps the services already suggested to this lead.
 */
export function startVisit(id: string, lead: LeadDetail, services: LeadService[], last: { answers: Record<string, Answer> } | null): VisitDraft {
  const d = emptyVisitDraft(id, lead.id, { answers: last?.answers ?? {}, expectedValue: lead.expectedValue });
  return { ...d, addedServiceIds: services.filter((s) => s.status === 'suggested').map((s) => s.id) };
}

/** The visit seen through the wizard's model, so scoring and suggestions are the same code. */
export function asLeadDraft(v: VisitDraft, lead: LeadDetail): NewLeadDraft {
  return {
    ...emptyDraft(v.id),
    step: 4,
    businessName: lead.businessName,
    activityTypeId: lead.activityId,
    contactName: lead.contactName ?? '',
    contactRole: lead.contactRole,
    waConsent: lead.waConsent,
    bestTime: lead.bestTime,
    answers: v.answers,
    keyObservation: v.keyObservation,
    notes: v.notes,
    selected: v.selected,
    addedServiceIds: v.addedServiceIds,
    expectedValue: v.expectedValue,
  };
}

export function deriveVisit(v: VisitDraft, lead: LeadDetail, activities: ActivityType[], catalog: CatalogService[]): Derived {
  return derive(asLeadDraft(v, lead), activities, catalog);
}

export type VisitPayloadResult = { ok: true; payload: { [key: string]: Json } } | { ok: false; step: 2; error: string };

/** The JSON sent to add_visit (migration 10). Priority follows the lead's decision maker. */
export function buildVisitPayload(v: VisitDraft, lead: LeadDetail, x: Derived): VisitPayloadResult {
  if (!v.keyObservation.trim()) return { ok: false, step: 2, error: 'اكتب الملاحظة الأبرز. سطر واحد محدد من الزيارة.' };
  const expected = v.expectedValue === null ? x.expected : parseExpected(v.expectedValue);
  return {
    ok: true,
    payload: {
      id: v.id,
      lead_id: lead.id,
      key_observation: v.keyObservation.trim(),
      notes: v.notes.trim(),
      answers: v.answers,
      score: x.score,
      weaknesses: x.weaknesses.map((w) => w.id),
      priority: computePriority(x.score, lead.isDecisionMaker),
      expected_value: expected,
      services: x.shown.map((s) => ({ service_id: s.service.id, status: s.selected ? 'suggested' : 'dropped', note: s.reason })),
      lat: null,
      lng: null,
    },
  };
}
