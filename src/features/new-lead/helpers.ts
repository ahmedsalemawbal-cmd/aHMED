import type { LeadIndexRow } from '@/data/catalog';
import { findDuplicates } from '@/lib/duplicates';
import { formatNumber } from '@/lib/format';
import { normalizePhone } from '@/lib/phone';
import type { Checklist } from '@/lib/scoring';
import type { CatalogService } from '@/lib/suggest';
import type { NewLeadDraft } from './draft';

/** Phone duplicates block (unless confirmed as another branch); name matches only warn. */
export function step1Problems(d: NewLeadDraft, index: LeadIndexRow[]) {
  const phone = normalizePhone(d.phone);
  const dups = phone.ok
    ? findDuplicates({ businessName: d.businessName, phoneE164: phone.e164, lat: d.location?.lat ?? null, lng: d.location?.lng ?? null }, index)
    : [];
  const phoneDup = dups.find((x) => x.reason === 'phone') ?? null;
  const nameDup = dups.find((x) => x.reason !== 'phone') ?? null;
  return { phone, phoneDup, nameDup };
}

/** Groups general items by their axis, in the order they appear (NewLead2.dc.html). */
export function groupItems(checklist: Checklist): { title: string; items: Checklist['general'] }[] {
  const groups: { title: string; items: Checklist['general'] }[] = [];
  for (const item of checklist.general) {
    const g = groups.find((x) => x.title === item.group);
    if (g) g.items.push(item);
    else groups.push({ title: item.group, items: [item] });
  }
  return groups;
}

export function priceText(s: CatalogService): string {
  if (s.priceFrom == null) return 'السعر يُحدد لاحقاً';
  return s.billing === 'monthly' ? `من ${formatNumber(s.priceFrom)} ر.س شهرياً` : `${formatNumber(s.priceFrom)} ر.س مرة واحدة`;
}

