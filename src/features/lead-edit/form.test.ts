import { describe, expect, it } from 'vitest';
import type { LeadDetail } from '@/data/lead';
import { buildLeadPatch, formFromLead, isDirty, normalizeInstagram, phoneDuplicate, type EditForm } from './form';

const lead: LeadDetail = {
  id: 'l1',
  businessName: 'مطعم ريدان',
  activityId: 'a-rest',
  activity: 'مطعم',
  checklist: { general: [], specific: [] },
  contactName: 'خالد',
  contactRole: 'owner',
  isDecisionMaker: true,
  phone: '966551234567',
  bestTime: 'evening',
  waConsent: false,
  doNotContact: false,
  address: null,
  instagramUrl: null,
  source: 'visit',
  stage: 'contacted',
  score: 48,
  priority: 'hot',
  expectedValue: 2500,
  lostReason: null,
  lostNote: null,
  wonValue: null,
  wonBilling: null,
  createdAt: new Date('2026-10-07T07:30:00Z'),
  stageChangedAt: new Date('2026-10-07T08:20:00Z'),
  lastContactAt: null,
};

describe('«تعديل البيانات» form', () => {
  const initial = formFromLead(lead);

  it('starts from the lead, phone in the local form', () => {
    expect(initial).toMatchObject({ businessName: 'مطعم ريدان', phone: '0551234567', contactRole: 'owner', bestTime: 'evening', address: '', instagram: '' });
    expect(isDirty(initial, initial)).toBe(false);
  });

  it('sends only the changed columns, normalised', () => {
    const form: EditForm = { ...initial, businessName: '  مطعم ريدان الجديد ', phone: '055 999 8877', waConsent: true, address: ' الحمدانية ', instagram: '@riyadan' };
    expect(buildLeadPatch(initial, form)).toEqual({
      ok: true,
      patch: { business_name: 'مطعم ريدان الجديد', phone_e164: '966559998877', wa_consent: true, address: 'الحمدانية', instagram_url: 'https://instagram.com/riyadan' },
    });
    expect(isDirty(initial, form)).toBe(true);
  });

  it('clearing optional fields writes null; same phone typed differently is not a change', () => {
    const withData: EditForm = { ...initial, contactName: 'خالد', address: 'الحمدانية' };
    expect(buildLeadPatch(withData, { ...withData, contactName: ' ', address: '' })).toEqual({ ok: true, patch: { contact_name: null, address: null } });
    expect(buildLeadPatch(initial, { ...initial, phone: '+966 55 123 4567' })).toEqual({ ok: true, patch: {} });
    expect(buildLeadPatch(initial, { ...initial, phone: '' })).toEqual({ ok: true, patch: { phone_e164: null } });
  });

  it('do-not-contact, role and decision maker are their own columns', () => {
    expect(buildLeadPatch(initial, { ...initial, doNotContact: true, contactRole: 'manager', isDecisionMaker: false })).toEqual({
      ok: true,
      patch: { do_not_contact: true, contact_role: 'manager', is_decision_maker: false },
    });
  });

  it('says what is wrong and where', () => {
    expect(buildLeadPatch(initial, { ...initial, businessName: ' ' })).toMatchObject({ ok: false, field: 'businessName' });
    expect(buildLeadPatch(initial, { ...initial, activityTypeId: null })).toMatchObject({ ok: false, field: 'activity' });
    expect(buildLeadPatch(initial, { ...initial, phone: '0551' })).toMatchObject({ ok: false, field: 'phone' });
    expect(buildLeadPatch(initial, { ...initial, instagram: 'not a link' })).toMatchObject({ ok: false, field: 'instagram' });
    expect(isDirty(initial, { ...initial, phone: '0551' })).toBe(true);
  });

  it('instagram: handle, link, empty', () => {
    expect(normalizeInstagram('riyadan')).toEqual({ ok: true, url: 'https://instagram.com/riyadan' });
    expect(normalizeInstagram('https://www.instagram.com/riyadan/')).toEqual({ ok: true, url: 'https://www.instagram.com/riyadan/' });
    expect(normalizeInstagram('')).toEqual({ ok: true, url: null });
    expect(normalizeInstagram('ftp://x')).toEqual({ ok: false });
  });

  it('duplicate number among the other leads only', () => {
    const index = [
      { id: 'l1', businessName: 'مطعم ريدان', phoneE164: '966551234567', lat: null, lng: null },
      { id: 'l2', businessName: 'كافيه سحابة', phoneE164: '966500000001', lat: null, lng: null },
    ];
    expect(phoneDuplicate(initial, index, 'l1')).toBeNull();
    expect(phoneDuplicate({ ...initial, phone: '0500000001' }, index, 'l1')?.businessName).toBe('كافيه سحابة');
    expect(phoneDuplicate({ ...initial, phone: '05' }, index, 'l1')).toBeNull();
  });
});
