import { describe, expect, it } from 'vitest';
import { distanceMeters, duplicateMessage, findDuplicates, normalizeName, type DupLead } from './duplicates';
import { followupSchedule, needsDay7Decision, quoteFollowup } from './followups';
import { computeQuote, formatQuoteNumber, type QuoteItem } from './quote';
import { expectedMonthly, suggestServices, type CatalogService } from './suggest';
import { lineCount, waLink, waLinkTooLong } from './whatsapp';

describe('duplicate detection: same phone, or same name at the same place', () => {
  const RIYADH = { lat: 21.6, lng: 39.2 };
  const existing: DupLead[] = [
    { id: 'a', businessName: 'مطعم ريدان', phoneE164: '966551234567', lat: RIYADH.lat, lng: RIYADH.lng },
    { id: 'b', businessName: 'كافيه سحابة', phoneE164: null, lat: null, lng: null },
  ];
  it('same phone', () => {
    expect(findDuplicates({ businessName: 'محل آخر', phoneE164: '966551234567', lat: null, lng: null }, existing)).toEqual([{ lead: existing[0], reason: 'phone' }]);
  });
  it('same name within 150 m, spelling variants folded', () => {
    const near = { lat: RIYADH.lat + 0.0005, lng: RIYADH.lng }; // ~55 m
    expect(findDuplicates({ businessName: 'مطعم الريدان', phoneE164: null, ...near }, existing).map((d) => d.reason)).toEqual(['name_location']);
  });
  it('same name farther than 150 m is another branch', () => {
    const far = { lat: RIYADH.lat + 0.01, lng: RIYADH.lng }; // ~1.1 km
    expect(findDuplicates({ businessName: 'مطعم ريدان', phoneE164: null, ...far }, existing)).toEqual([]);
  });
  it('same name without a location is a soft warning', () => {
    expect(findDuplicates({ businessName: 'كافيه سحابه', phoneE164: null, lat: 1, lng: 1 }, existing).map((d) => d.reason)).toEqual(['name']);
  });
  it('different name, different phone: none', () => {
    expect(findDuplicates({ businessName: 'مخبز الحي', phoneE164: '966500000000', lat: null, lng: null }, existing)).toEqual([]);
  });
  it('normalizeName folds hamza, ta marbuta, alef maqsura, tashkeel and «ال»', () => {
    expect(normalizeName('مَطْعَمُ  الرَّيْدان!')).toBe(normalizeName('مطعم ريدان'));
    expect(normalizeName('مقهى ليلى')).toBe(normalizeName('مقهي ليلي'));
    expect(normalizeName('حلويات السنبلة')).toBe(normalizeName('حلويات سنبله'));
  });
  it('distance is in metres', () => {
    expect(Math.round(distanceMeters({ lat: 0, lng: 0 }, { lat: 0.001, lng: 0 }))).toBe(111);
  });
  it('messages say what happened and what to do', () => {
    expect(duplicateMessage('phone', 'مطعم ريدان')).toBe('هذا الرقم مسجّل لمحل آخر: مطعم ريدان. افتحه بدل إنشاء عميل جديد.');
  });
});

describe('wa.me link', () => {
  it('encodes the message with encodeURIComponent', () => {
    const msg = 'السلام عليكم أستاذ خالد،\nيناسبك؟ & 100%';
    expect(waLink('966551234567', msg)).toBe(`https://wa.me/966551234567?text=${encodeURIComponent(msg)}`);
    expect(waLink('966551234567', 'a b')).toBe('https://wa.me/966551234567?text=a%20b');
  });
  it('refuses a local-format number', () => {
    expect(() => waLink('0551234567', 'x')).toThrow();
  });
  it('flags very long messages', () => {
    expect(waLinkTooLong('966551234567', 'قصيرة')).toBe(false);
    expect(waLinkTooLong('966551234567', 'ا'.repeat(400))).toBe(true);
  });
  it('counts non-empty lines', () => {
    expect(lineCount('سطر\n\nسطر ثاني\n  \nثالث')).toBe(3);
  });
});

describe('follow-ups after «نعم، أرسلتها»: 3 and 7 days', () => {
  const sentAt = new Date('2026-10-07T08:20:00Z'); // Wed 11:20 Riyadh
  it('Saturday 10 and Wednesday 14 October at the preferred time', () => {
    const f = followupSchedule(sentAt, { bestTime: 'evening', doNotContact: false });
    expect(f.map((x) => [x.kind, x.dueAt.toISOString()])).toEqual([
      ['followup_3', '2026-10-10T16:00:00.000Z'], // Sat 19:00 Riyadh
      ['followup_7', '2026-10-14T16:00:00.000Z'], // Wed 19:00 Riyadh
    ]);
  });
  it('10:00 Riyadh when no preferred time', () => {
    expect(followupSchedule(sentAt, { bestTime: null, doNotContact: false })[0]?.dueAt.toISOString()).toBe('2026-10-10T07:00:00.000Z');
  });
  it('a message confirmed late at night still counts calendar days in Riyadh', () => {
    const late = new Date('2026-10-07T20:30:00Z'); // Wed 23:30 Riyadh
    expect(followupSchedule(late, { bestTime: 'morning', doNotContact: false })[0]?.dueAt.toISOString()).toBe('2026-10-10T07:00:00.000Z');
  });
  it('no follow-ups for do_not_contact', () => {
    expect(followupSchedule(sentAt, { bestTime: null, doNotContact: true })).toEqual([]);
  });
  it('quote follow-up 3 days later', () => {
    expect(quoteFollowup(sentAt, 'afternoon').toISOString()).toBe('2026-10-10T13:00:00.000Z');
  });
  it('day-7 decision only for contacted leads whose 7-day follow-up is done or past', () => {
    const now = new Date('2026-10-15T08:00:00Z');
    expect(needsDay7Decision({ stage: 'contacted' }, { doneAt: null, dueAt: new Date('2026-10-14T16:00:00Z') }, now)).toBe(true);
    expect(needsDay7Decision({ stage: 'contacted' }, { doneAt: null, dueAt: new Date('2026-10-15T16:00:00Z') }, now)).toBe(false);
    expect(needsDay7Decision({ stage: 'replied' }, { doneAt: now, dueAt: now }, now)).toBe(false);
    expect(needsDay7Decision({ stage: 'contacted' }, null, now)).toBe(false);
  });
});

describe('quote totals and discount', () => {
  const items: QuoteItem[] = [
    { serviceId: 'v', name: 'تصوير وإنتاج فيديو وريلز', billing: 'monthly', qty: 1, unitPrice: 1500 },
    { serviceId: 'm', name: 'إدارة ملف خرائط قوقل', billing: 'monthly', qty: 1, unitPrice: 800 },
    { serviceId: 'l', name: 'صفحة هبوط للطلب', billing: 'one_time', qty: 1, unitPrice: 2000 },
  ];
  it('Quote.dc.html: 10% → monthly 2,070, once 1,800, saving 430', () => {
    const q = computeQuote(items, 10, null);
    expect([q.monthly, q.once, q.discountAmount]).toEqual([2070, 1800, 430]);
    expect(q.monthlySubtotal).toBe(2300);
    expect(q.vatMonthly + q.vatOnce).toBe(0);
  });
  it('no discount', () => {
    const q = computeQuote(items, 0, null);
    expect([q.monthly, q.once, q.discountAmount]).toEqual([2300, 2000, 0]);
  });
  it('quantity multiplies, minimum 1, prices never negative', () => {
    const q = computeQuote([{ serviceId: null, name: 'x', billing: 'monthly', qty: 0, unitPrice: -5 }, { serviceId: null, name: 'y', billing: 'monthly', qty: 3, unitPrice: 333.33 }], 5, null);
    expect(q.lines.map((l) => l.total)).toEqual([0, 999.99]);
    expect(q.monthly).toBe(949.99);
  });
  it('VAT only when a rate is set, on the discounted totals', () => {
    const q = computeQuote(items, 10, 15);
    expect([q.vatMonthly, q.vatOnce]).toEqual([310.5, 270]);
    expect(q.firstPayment).toBe(2070 + 1800 + 310.5 + 270);
  });
  it('quote number Q-YYYY-NNN', () => {
    expect(formatQuoteNumber(2026, 14)).toBe('Q-2026-014');
    expect(formatQuoteNumber(2026, 1234)).toBe('Q-2026-1234');
  });
});

describe('service suggestions', () => {
  const cat: CatalogService[] = [
    { id: 'video', name: 'تصوير وإنتاج فيديو وريلز', billing: 'monthly', priceFrom: 1500, activityTypeIds: ['rest'], weaknessItemIds: ['s2', 's3'], isActive: true },
    { id: 'maps', name: 'إدارة ملف خرائط قوقل والمراجعات', billing: 'monthly', priceFrom: 800, activityTypeIds: ['rest'], weaknessItemIds: ['g3'], isActive: true },
    { id: 'landing', name: 'صفحة هبوط', billing: 'one_time', priceFrom: 2000, activityTypeIds: ['rest'], weaknessItemIds: ['web'], isActive: true },
    { id: 'ads', name: 'إدارة الحملات الإعلانية', billing: 'monthly', priceFrom: 1000, activityTypeIds: ['rest'], weaknessItemIds: ['ad'], isActive: true },
    { id: 'off', name: 'خدمة موقوفة', billing: 'monthly', priceFrom: 1, activityTypeIds: ['rest'], weaknessItemIds: ['s3'], isActive: false },
    { id: 'dental-only', name: 'للأسنان فقط', billing: 'monthly', priceFrom: 1, activityTypeIds: ['dent'], weaknessItemIds: ['s3'], isActive: true },
  ];
  const weak = [
    { id: 's3', text: 'لا ينشر فيديو أو ريلز', answer: 'no' as const, lost: 10 },
    { id: 'web', text: 'بدون رابط طلب أو حجز', answer: 'no' as const, lost: 10 },
    { id: 'ad', text: 'لا يعلن حالياً', answer: 'no' as const, lost: 10 },
    { id: 'g3', text: 'لا يرد على مراجعات قوقل', answer: 'no' as const, lost: 5 },
  ];
  it('from weaknesses with the reason, skipping inactive and other activities; first 3 pre-selected', () => {
    const s = suggestServices(cat, { id: 'rest', name: 'مطعم', defaultServiceIds: ['video', 'maps'] }, weak);
    expect(s.map((x) => [x.service.id, x.reason, x.selected])).toEqual([
      ['video', 'لأنه: لا ينشر فيديو أو ريلز', true],
      ['landing', 'لأنه: بدون رابط طلب أو حجز', true],
      ['ads', 'لأنه: لا يعلن حالياً', true],
      ['maps', 'لأنه: لا يرد على مراجعات قوقل', false],
    ]);
  });
  it('activity defaults fill in when there are few weaknesses', () => {
    const s = suggestServices(cat, { id: 'rest', name: 'مطعم', defaultServiceIds: ['video', 'maps'] }, []);
    expect(s.map((x) => [x.service.id, x.reason])).toEqual([
      ['video', 'تُطلب غالباً لنشاط «مطعم»'],
      ['maps', 'تُطلب غالباً لنشاط «مطعم»'],
    ]);
  });
  it('expected monthly value sums monthly prices only', () => {
    expect(expectedMonthly(cat.filter((c) => ['video', 'maps', 'landing'].includes(c.id)))).toBe(2300);
  });
});
