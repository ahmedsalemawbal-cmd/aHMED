import { describe, expect, it } from 'vitest';
import type { ActivityType } from '@/data/catalog';
import type { CatalogService } from '@/lib/suggest';
import { emptyDraft, type NewLeadDraft } from './draft';
import { buildPayload, derive } from './payload';

const ACT: ActivityType = {
  id: 'rest',
  name: 'مطعم',
  icon: 'restaurant',
  defaultServiceIds: ['maps'],
  checklist: {
    general: [
      { id: 's3', label: 'ينشر فيديو أو ريلز', weight: 60, group: 'س', weakness: 'لا ينشر فيديو أو ريلز' },
      { id: 'g3', label: 'يرد على المراجعات', weight: 40, group: 'ق', weakness: 'لا يرد على مراجعات قوقل' },
    ],
    specific: [{ id: 'r1', label: 'صور احترافية للأطباق' }],
  },
};
const SVC: CatalogService[] = [
  { id: 'video', name: 'تصوير', billing: 'monthly', priceFrom: 1500, activityTypeIds: ['rest'], weaknessItemIds: ['s3'], isActive: true },
  { id: 'maps', name: 'قوقل', billing: 'monthly', priceFrom: 800, activityTypeIds: ['rest'], weaknessItemIds: ['g3'], isActive: true },
  { id: 'landing', name: 'صفحة هبوط', billing: 'one_time', priceFrom: 2000, activityTypeIds: ['rest'], weaknessItemIds: [], isActive: true },
];

function draft(over: Partial<NewLeadDraft> = {}): NewLeadDraft {
  return {
    ...emptyDraft('11111111-1111-4111-8111-111111111111'),
    businessName: ' مطعم ريدان ',
    activityTypeId: 'rest',
    contactName: 'خالد',
    contactRole: 'owner',
    phone: '055 123 4567',
    waConsent: true,
    bestTime: 'evening',
    answers: { s3: 'no', g3: 'partial', r1: 'yes' },
    keyObservation: 'أطباقهم ممتازة وحسابهم بدون فيديو',
    ...over,
  };
}

describe('derive', () => {
  it('score, weaknesses and suggestions follow the answers', () => {
    const x = derive(draft(), [ACT], SVC);
    expect(x.score).toBe(20); // partial on 40 = 20 out of 100
    expect(x.weaknesses.map((w) => w.id)).toEqual(['s3', 'g3']);
    expect(x.shown.map((s) => [s.service.id, s.selected, s.reason])).toEqual([
      ['video', true, 'لأنه: لا ينشر فيديو أو ريلز'],
      ['maps', true, 'لأنه: لا يرد على مراجعات قوقل'],
    ]);
    expect(x.expected).toBe(2300);
  });
  it('user choices override the pre-selection, catalogue additions are selected', () => {
    const x = derive(draft({ selected: { video: false, maps: true, landing: true }, addedServiceIds: ['landing'] }), [ACT], SVC);
    expect(x.shown.map((s) => [s.service.id, s.selected])).toEqual([
      ['video', false],
      ['maps', true],
      ['landing', true],
    ]);
    expect(x.expected).toBe(800); // one-time services are not monthly value
  });
});

describe('buildPayload', () => {
  it('normalises the phone, computes priority and keeps every service with its status', () => {
    const d = draft({ selected: { video: true, maps: false } });
    const r = buildPayload(d, derive(d, [ACT], SVC));
    if (!r.ok) throw new Error(r.error);
    expect(r.payload).toMatchObject({
      id: '11111111-1111-4111-8111-111111111111',
      business_name: 'مطعم ريدان',
      phone_e164: '966551234567',
      is_decision_maker: true,
      score: 20,
      priority: 'hot',
      expected_value: 1500,
      weaknesses: ['s3', 'g3'],
      services: [
        { service_id: 'video', status: 'suggested', note: 'لأنه: لا ينشر فيديو أو ريلز' },
        { service_id: 'maps', status: 'dropped', note: 'لأنه: لا يرد على مراجعات قوقل' },
      ],
    });
  });
  it('a manager is not a decision maker: low score is warm', () => {
    const d = draft({ contactRole: 'manager' });
    const r = buildPayload(d, derive(d, [ACT], SVC));
    expect(r.ok && r.payload.priority).toBe('warm');
  });
  it('typed expected value wins over the computed one', () => {
    const d = draft({ expectedValue: '2,500' });
    const r = buildPayload(d, derive(d, [ACT], SVC));
    expect(r.ok && r.payload.expected_value).toBe(2500);
  });
  it.each([
    [{ businessName: '  ' }, 1, 'اكتب اسم المحل.'],
    [{ activityTypeId: null }, 1, 'اختر نوع النشاط.'],
    [{ phone: '055' }, 1, 'الرقم ناقص. اكتب 10 أرقام تبدأ بـ 05'],
    [{ keyObservation: ' ' }, 3, 'اكتب الملاحظة الأبرز. سطر واحد محدد من الزيارة.'],
  ] as const)('refuses %o and points to the step', (over, step, error) => {
    const d = draft(over);
    expect(buildPayload(d, derive(d, [ACT], SVC))).toEqual({ ok: false, step, error });
  });
  it('no phone is allowed', () => {
    const d = draft({ phone: '' });
    const r = buildPayload(d, derive(d, [ACT], SVC));
    expect(r.ok && r.payload.phone_e164).toBe('');
  });
});
