import { describe, expect, it } from 'vitest';
import type { ActivityType } from '@/data/catalog';
import type { LeadDetail, LeadService } from '@/data/lead';
import type { CatalogService } from '@/lib/suggest';
import { buildVisitPayload, deriveVisit, startVisit } from './payload';

const activity: ActivityType = {
  id: 'a-rest',
  name: 'مطعم',
  icon: 'restaurant',
  defaultServiceIds: ['s-maps'],
  checklist: {
    general: [
      { id: 'g1', label: 'التقييم 4.3 أو أعلى', weight: 50, group: 'قوقل', weakness: 'تقييمه أقل من 4.3' },
      { id: 's3', label: 'ينشر فيديو أو ريلز', weight: 50, group: 'سوشيال', weakness: 'لا ينشر فيديو أو ريلز' },
    ],
    specific: [],
  },
};

const catalog: CatalogService[] = [
  { id: 's-maps', name: 'إدارة ملف خرائط قوقل', billing: 'monthly', priceFrom: 800, activityTypeIds: ['a-rest'], weaknessItemIds: ['g1'], isActive: true },
  { id: 's-video', name: 'تصوير فيديو وريلز', billing: 'monthly', priceFrom: 1500, activityTypeIds: ['a-rest'], weaknessItemIds: ['s3'], isActive: true },
  { id: 's-landing', name: 'صفحة هبوط', billing: 'one_time', priceFrom: 2000, activityTypeIds: ['a-rest'], weaknessItemIds: [], isActive: true },
];

const lead = {
  id: 'l1',
  businessName: 'مطعم ريدان',
  activityId: 'a-rest',
  contactName: 'خالد',
  contactRole: 'manager',
  isDecisionMaker: true,
  waConsent: true,
  bestTime: 'evening',
  expectedValue: 1500,
} as const satisfies Partial<LeadDetail>;
const fullLead: LeadDetail = {
  ...lead,
  activity: 'مطعم',
  checklist: activity.checklist,
  phone: '966551234567',
  doNotContact: false,
  address: null,
  instagramUrl: null,
  source: 'visit',
  stage: 'contacted',
  score: 50,
  priority: 'warm',
  lostReason: null,
  lostNote: null,
  wonValue: null,
  wonBilling: null,
  createdAt: new Date('2026-10-01T07:00:00Z'),
  stageChangedAt: new Date('2026-10-01T07:00:00Z'),
  lastContactAt: null,
};

describe('«زيارة جديدة»', () => {
  const existing: LeadService[] = [
    { id: 's-landing', name: 'صفحة هبوط', billing: 'one_time', priceFrom: 2000, status: 'suggested' },
    { id: 's-maps', name: 'إدارة ملف خرائط قوقل', billing: 'monthly', priceFrom: 800, status: 'dropped' },
  ];

  it('starts from the last answers, the lead value, and keeps its suggested services', () => {
    const v = startVisit('v1', fullLead, existing, { answers: { g1: 'yes', s3: 'no' } });
    expect(v).toMatchObject({ id: 'v1', leadId: 'l1', step: 1, answers: { g1: 'yes', s3: 'no' }, expectedValue: '1500', addedServiceIds: ['s-landing'], selected: null });
    const x = deriveVisit(v, fullLead, [activity], catalog);
    expect(x.score).toBe(50);
    expect(x.shown.map((s) => [s.service.id, s.selected])).toEqual([
      ['s-video', true],
      ['s-maps', true],
      ['s-landing', true],
    ]);
  });

  it('needs the key observation, then builds the add_visit payload', () => {
    const v = { ...startVisit('v1', fullLead, existing, { answers: { g1: 'yes', s3: 'no' } }), keyObservation: '' };
    const x = deriveVisit(v, fullLead, [activity], catalog);
    expect(buildVisitPayload(v, fullLead, x)).toEqual({ ok: false, step: 2, error: 'اكتب الملاحظة الأبرز. سطر واحد محدد من الزيارة.' });
    const ready = { ...v, keyObservation: ' بدأوا ينشرون صوراً ', notes: 'المالك موجود بعد العصر', selected: { 's-video': true, 's-maps': false, 's-landing': true } };
    const r = buildVisitPayload(ready, fullLead, deriveVisit(ready, fullLead, [activity], catalog));
    expect(r).toEqual({
      ok: true,
      payload: {
        id: 'v1',
        lead_id: 'l1',
        key_observation: 'بدأوا ينشرون صوراً',
        notes: 'المالك موجود بعد العصر',
        answers: { g1: 'yes', s3: 'no' },
        score: 50,
        weaknesses: ['s3'],
        // score 50 is warm for everyone; the lead's decision maker flag is used, not the role
        priority: 'warm',
        expected_value: 1500,
        services: [
          { service_id: 's-video', status: 'suggested', note: 'لأنه: لا ينشر فيديو أو ريلز' },
          { service_id: 's-maps', status: 'dropped', note: 'تُطلب غالباً لنشاط «مطعم»' },
          { service_id: 's-landing', status: 'suggested', note: 'أضفتها من الكتالوج' },
        ],
        lat: null,
        lng: null,
      },
    });
  });

  it('a low score with a decision maker is hot; a typed value wins', () => {
    const v = { ...startVisit('v2', fullLead, [], { answers: { g1: 'no', s3: 'no' } }), keyObservation: 'ملاحظة', expectedValue: '٣٠٠٠' };
    const r = buildVisitPayload(v, fullLead, deriveVisit(v, fullLead, [activity], catalog));
    expect(r.ok && r.payload.priority).toBe('hot');
    expect(r.ok && r.payload.expected_value).toBe(3000);
  });
});
