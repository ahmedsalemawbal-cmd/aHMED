import type { LeadIndexRow } from '@/data/catalog';
import type { LeadDetail } from '@/data/lead';
import type { LeadPatch } from '@/data/update-lead';
import type { ContactTime } from '@/lib/followups';
import type { Role } from '@/lib/leads-list';
import { normalizePhone, toLocal } from '@/lib/phone';

/** «تعديل البيانات» fields as typed (the phone in its local 05… form). */
export interface EditForm {
  businessName: string;
  activityTypeId: string | null;
  contactName: string;
  contactRole: Role | null;
  isDecisionMaker: boolean;
  phone: string;
  waConsent: boolean;
  doNotContact: boolean;
  bestTime: ContactTime | null;
  address: string;
  instagram: string;
}

export function formFromLead(l: LeadDetail): EditForm {
  return {
    businessName: l.businessName,
    activityTypeId: l.activityId,
    contactName: l.contactName ?? '',
    contactRole: l.contactRole,
    isDecisionMaker: l.isDecisionMaker,
    phone: l.phone ? toLocal(l.phone) : '',
    waConsent: l.waConsent,
    doNotContact: l.doNotContact,
    bestTime: l.bestTime,
    address: l.address ?? '',
    instagram: l.instagramUrl ?? '',
  };
}

export const EDIT_ERRORS = {
  name: 'اكتب اسم المحل.',
  activity: 'اختر نوع النشاط.',
  instagram: 'الرابط غير صحيح. الصق رابط الحساب أو اكتب اسم المستخدم مثل @riyadan.',
} as const;

/**
 * «@riyadan», «riyadan» or a full link → https://instagram.com/riyadan; other
 * http(s) links are kept; empty → null. Anything else is invalid.
 */
export function normalizeInstagram(input: string): { ok: true; url: string | null } | { ok: false } {
  const t = input.trim();
  if (!t) return { ok: true, url: null };
  if (/^https?:\/\/\S+\.\S+$/i.test(t)) return { ok: true, url: t };
  const handle = /^@?([A-Za-z0-9._]{1,30})$/.exec(t)?.[1];
  return handle ? { ok: true, url: `https://instagram.com/${handle}` } : { ok: false };
}

export type EditField = 'businessName' | 'activity' | 'phone' | 'instagram';

export type PatchResult = { ok: true; patch: LeadPatch } | { ok: false; field: EditField; error: string };

/** Validates the form and returns only the columns that changed (one UPDATE under RLS). */
export function buildLeadPatch(initial: EditForm, form: EditForm): PatchResult {
  const name = form.businessName.trim();
  if (!name) return { ok: false, field: 'businessName', error: EDIT_ERRORS.name };
  if (!form.activityTypeId) return { ok: false, field: 'activity', error: EDIT_ERRORS.activity };
  const phone = normalizePhone(form.phone);
  if (!phone.ok) return { ok: false, field: 'phone', error: phone.error };
  const ig = normalizeInstagram(form.instagram);
  if (!ig.ok) return { ok: false, field: 'instagram', error: EDIT_ERRORS.instagram };
  const was = {
    phone: normalizePhone(initial.phone),
    ig: normalizeInstagram(initial.instagram),
  };

  const patch: LeadPatch = {};
  if (name !== initial.businessName.trim()) patch.business_name = name;
  if (form.activityTypeId !== initial.activityTypeId) patch.activity_type_id = form.activityTypeId;
  if (form.contactName.trim() !== initial.contactName.trim()) patch.contact_name = form.contactName.trim() || null;
  if (form.contactRole !== initial.contactRole) patch.contact_role = form.contactRole;
  if (form.isDecisionMaker !== initial.isDecisionMaker) patch.is_decision_maker = form.isDecisionMaker;
  if (phone.e164 !== (was.phone.ok ? was.phone.e164 : null)) patch.phone_e164 = phone.e164;
  if (form.waConsent !== initial.waConsent) patch.wa_consent = form.waConsent;
  if (form.doNotContact !== initial.doNotContact) patch.do_not_contact = form.doNotContact;
  if (form.bestTime !== initial.bestTime) patch.best_contact_time = form.bestTime;
  if (form.address.trim() !== initial.address.trim()) patch.address = form.address.trim() || null;
  if (ig.url !== (was.ig.ok ? was.ig.url : null)) patch.instagram_url = ig.url;
  return { ok: true, patch };
}

export function isDirty(initial: EditForm, form: EditForm): boolean {
  const r = buildLeadPatch(initial, form);
  // an invalid form differs from the saved one by definition
  return !r.ok || Object.keys(r.patch).length > 0;
}

/** Another lead with the same number (this lead excluded). */
export function phoneDuplicate(form: EditForm, index: LeadIndexRow[], leadId: string): LeadIndexRow | null {
  const p = normalizePhone(form.phone);
  if (!p.ok || !p.e164) return null;
  return index.find((r) => r.id !== leadId && r.phoneE164 === p.e164) ?? null;
}
