import { describe, expect, it } from 'vitest';
import { formatPhone, normalizePhone, PHONE_ERRORS, toLocal } from './phone';

describe('normalizePhone: 05 → 9665', () => {
  it.each([
    ['0551234567', '966551234567'],
    ['055 123 4567', '966551234567'],
    ['055-123-4567', '966551234567'],
    ['+966551234567', '966551234567'],
    ['00966551234567', '966551234567'],
    ['966551234567', '966551234567'],
    ['551234567', '966551234567'],
    ['٠٥٥١٢٣٤٥٦٧', '966551234567'],
    ['۰۵۵۱۲۳۴۵۶۷', '966551234567'],
    [' (055) 1234567 ', '966551234567'],
  ])('%s → %s', (input, e164) => {
    expect(normalizePhone(input)).toEqual({ ok: true, e164 });
  });

  it('empty is allowed (no phone yet)', () => {
    expect(normalizePhone('  ')).toEqual({ ok: true, e164: null });
  });

  it.each([
    ['055123', PHONE_ERRORS.short],
    ['05512345678', PHONE_ERRORS.long],
    ['0112345678', PHONE_ERRORS.prefix],
    ['+9661123456789', PHONE_ERRORS.prefix],
    ['05a1234567', PHONE_ERRORS.chars],
  ])('%s is rejected with a message that says what to do', (input, error) => {
    expect(normalizePhone(input)).toEqual({ ok: false, error });
  });

  it('result always matches the database check ^9665[0-9]{8}$', () => {
    const r = normalizePhone('0599999999');
    expect(r.ok && r.e164).toMatch(/^9665[0-9]{8}$/);
  });

  it('back to local and display formats', () => {
    expect(toLocal('966551234567')).toBe('0551234567');
    expect(formatPhone('966551234567')).toBe('055 123 4567');
  });
});
