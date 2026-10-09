import { describe, expect, it } from 'vitest';
import { BOM, csvCell, csvFileName, leadsCsv, LEADS_CSV_HEADER, toCsv } from './csv';
import type { LeadRow } from './leads-list';

describe('CSV for Excel', () => {
  it('starts with a BOM, uses CRLF, quotes only when needed', () => {
    const s = toCsv([['أ', 'ب,ج'], ['قال "نعم"', 'سطر\nثانٍ']]);
    expect(s.startsWith(BOM)).toBe(true);
    expect(s.slice(1)).toBe('أ,"ب,ج"\r\n"قال ""نعم""","سطر\nثانٍ"\r\n');
  });
  it('never lets a cell run as a formula', () => {
    expect(csvCell('=HYPERLINK("x")')).toBe(`"'=HYPERLINK(""x"")"`);
    expect(csvCell('+966')).toBe("'+966");
    expect(csvCell('@a')).toBe("'@a");
    expect(csvCell(-5)).toBe('-5');
    expect(csvCell(null)).toBe('');
  });
  it('leads: local phone, Arabic names, Latin digits', () => {
    const row: LeadRow = {
      id: 'l1',
      businessName: 'مطعم ريدان',
      activityId: 'a',
      activity: 'مطعم',
      contactName: 'خالد',
      contactRole: 'owner',
      phone: '966551234567',
      stage: 'contacted',
      score: 48,
      priority: 'hot',
      source: 'visit',
      expectedValue: 2500,
      createdAt: new Date('2026-10-07T07:30:00Z'),
      lastContactAt: new Date('2026-10-07T08:20:00Z'),
      stageChangedAt: new Date('2026-10-07T08:20:00Z'),
      doNotContact: false,
      waConsent: true,
      next: { id: 't', title: 'متابعة أولى', kind: 'followup_3', dueAt: new Date('2026-10-10T16:00:00Z') },
    };
    const lines = leadsCsv([row]).slice(1).split('\r\n');
    expect(lines[0]).toBe(LEADS_CSV_HEADER.join(','));
    expect(lines[1]).toBe('مطعم ريدان,مطعم,خالد,0551234567,تم الإرسال,48,حار,7 أكتوبر 2026,متابعة أولى,10 أكتوبر 2026,2500,7 أكتوبر 2026');
  });
  it('file name carries the Riyadh date', () => {
    expect(csvFileName('عملاء', new Date('2026-10-09T22:30:00Z'))).toBe('عملاء-2026-10-10.csv');
  });
});
