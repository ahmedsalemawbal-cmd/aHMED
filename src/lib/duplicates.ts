/**
 * Duplicate check on entry (brief.md): same phone, or the same shop name at the
 * same place. «Same place» = within 150 m (QUESTIONS.md Q9, accepted proposal).
 */
export const SAME_PLACE_METERS = 150;

export interface DupCandidate {
  businessName: string;
  phoneE164: string | null;
  lat: number | null;
  lng: number | null;
}

export interface DupLead extends DupCandidate {
  id: string;
}

export type DupReason = 'phone' | 'name_location' | 'name';

/** Folds Arabic spelling variants so «مطعم الريدان» ≈ «مطعم ريدان». */
export function normalizeName(name: string): string {
  return name
    .normalize('NFKC')
    .replace(/[ً-ٰٟـ]/g, '') // tashkeel + tatweel
    .replace(/[أإآٱ]/g, 'ا')
    .replace(/ى/g, 'ي')
    .replace(/ة/g, 'ه')
    .replace(/ؤ/g, 'و')
    .replace(/ئ/g, 'ي')
    .toLowerCase()
    .split(/[\s\p{P}\p{S}]+/u)
    .filter(Boolean)
    .map((w) => (w.length > 3 && w.startsWith('ال') ? w.slice(2) : w))
    .join('');
}

export function distanceMeters(a: { lat: number; lng: number }, b: { lat: number; lng: number }): number {
  const R = 6_371_000;
  const rad = (d: number) => (d * Math.PI) / 180;
  const dLat = rad(b.lat - a.lat);
  const dLng = rad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}

export function findDuplicates(c: DupCandidate, existing: DupLead[]): { lead: DupLead; reason: DupReason }[] {
  const name = normalizeName(c.businessName);
  const out: { lead: DupLead; reason: DupReason }[] = [];
  for (const l of existing) {
    if (c.phoneE164 && l.phoneE164 === c.phoneE164) {
      out.push({ lead: l, reason: 'phone' });
      continue;
    }
    if (!name || normalizeName(l.businessName) !== name) continue;
    const bothLocated = c.lat != null && c.lng != null && l.lat != null && l.lng != null;
    if (bothLocated) {
      if (distanceMeters({ lat: c.lat as number, lng: c.lng as number }, { lat: l.lat as number, lng: l.lng as number }) <= SAME_PLACE_METERS) {
        out.push({ lead: l, reason: 'name_location' });
      }
    } else {
      out.push({ lead: l, reason: 'name' });
    }
  }
  return out;
}

/** Message for the duplicate warning (TextField error with a link to the other shop). */
export function duplicateMessage(reason: DupReason, otherName: string): string {
  if (reason === 'phone') return `هذا الرقم مسجّل لمحل آخر: ${otherName}. افتحه بدل إنشاء عميل جديد.`;
  if (reason === 'name_location') return `محل بنفس الاسم مسجّل في نفس الموقع: ${otherName}. افتحه بدل إنشاء عميل جديد.`;
  return `محل بنفس الاسم مسجّل: ${otherName}. تأكد أنه محل آخر قبل الحفظ.`;
}
