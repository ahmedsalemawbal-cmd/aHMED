import { STAGE_LABEL, PRIORITY } from '@/components/ui/stages';
import { formatDate } from './dates';
import type { LeadRow } from './leads-list';
import { toLocal } from './phone';

export type Cell = string | number | null | undefined;

/** UTF-8 BOM so Excel opens Arabic text correctly (PLAN 1h.5). */
export const BOM = '﻿';

/**
 * One CSV cell (RFC 4180): quoted when it holds a comma, quote or line break.
 * Text that a spreadsheet would run as a formula (= + - @, tab, CR) is
 * prefixed with an apostrophe so an exported shop name can never execute.
 */
export function csvCell(value: Cell): string {
  if (value === null || value === undefined) return '';
  let s = typeof value === 'number' ? String(value) : value;
  if (typeof value === 'string' && /^[=+\-@\t\r]/.test(s)) s = `'${s}`;
  return /[",\r\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}

export function toCsv(rows: Cell[][]): string {
  return BOM + rows.map((r) => r.map(csvCell).join(',')).join('\r\n') + '\r\n';
}

export const LEADS_CSV_HEADER = ['المحل', 'النشاط', 'المسؤول', 'الجوال', 'المرحلة', 'الدرجة', 'الأولوية', 'آخر تواصل', 'الإجراء القادم', 'موعد الإجراء', 'القيمة المتوقعة', 'تاريخ التسجيل'];

/** The leads table as a spreadsheet: local phone format, Arabic stage and priority names. */
export function leadsCsv(rows: LeadRow[]): string {
  return toCsv([
    LEADS_CSV_HEADER,
    ...rows.map((r) => [
      r.businessName,
      r.activity,
      r.contactName,
      r.phone ? toLocal(r.phone) : null,
      STAGE_LABEL[r.stage],
      r.score,
      r.priority ? PRIORITY[r.priority].label : null,
      r.lastContactAt ? formatDate(r.lastContactAt) : null,
      r.next?.title,
      r.next ? formatDate(r.next.dueAt) : null,
      r.expectedValue,
      formatDate(r.createdAt),
    ]),
  ]);
}

/** «عملاء-2026-10-09.csv» (Riyadh date) */
export function csvFileName(prefix: string, now: Date): string {
  const d = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(now);
  return `${prefix}-${d}.csv`;
}
