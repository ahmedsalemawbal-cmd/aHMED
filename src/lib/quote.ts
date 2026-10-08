/**
 * Quote totals (decisions §2): monthly and one-time totals are separate, the
 * discount applies to both, VAT only when enabled in settings (rate not decided).
 */
export type Discount = 0 | 5 | 10 | 15;
export type Validity = 7 | 14 | 30;
export const DISCOUNTS: Discount[] = [0, 5, 10, 15];
export const VALIDITIES: Validity[] = [7, 14, 30];

export interface QuoteItem {
  serviceId: string | null;
  name: string;
  billing: 'monthly' | 'one_time';
  qty: number;
  unitPrice: number;
}

export interface QuoteTotals {
  lines: (QuoteItem & { total: number })[];
  monthlySubtotal: number;
  onceSubtotal: number;
  discountAmount: number;
  monthly: number;
  once: number;
  vatMonthly: number;
  vatOnce: number;
  /** what the client pays first: one-time + first month (with VAT if enabled) */
  firstPayment: number;
}

const r2 = (n: number) => Math.round(n * 100) / 100;

export function computeQuote(items: QuoteItem[], discount: Discount, vatRate: number | null): QuoteTotals {
  const lines = items.map((i) => ({ ...i, qty: Math.max(1, Math.floor(i.qty)), unitPrice: Math.max(0, i.unitPrice) })).map((i) => ({ ...i, total: r2(i.qty * i.unitPrice) }));
  const monthlySubtotal = r2(lines.filter((l) => l.billing === 'monthly').reduce((s, l) => s + l.total, 0));
  const onceSubtotal = r2(lines.filter((l) => l.billing === 'one_time').reduce((s, l) => s + l.total, 0));
  const f = 1 - discount / 100;
  const monthly = r2(monthlySubtotal * f);
  const once = r2(onceSubtotal * f);
  const discountAmount = r2(monthlySubtotal + onceSubtotal - monthly - once);
  const rate = vatRate ?? 0;
  const vatMonthly = r2((monthly * rate) / 100);
  const vatOnce = r2((once * rate) / 100);
  return { lines, monthlySubtotal, onceSubtotal, discountAmount, monthly, once, vatMonthly, vatOnce, firstPayment: r2(monthly + once + vatMonthly + vatOnce) };
}

export function formatQuoteNumber(year: number, n: number): string {
  return `Q-${year.toString()}-${n.toString().padStart(3, '0')}`;
}
