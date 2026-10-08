/**
 * Saudi mobile numbers: typed as 05XXXXXXXX, stored as 9665XXXXXXXX (CLAUDE.md).
 * Accepts Arabic-Indic digits, spaces, dashes and the +966 / 00966 / 966 prefixes.
 */
export type PhoneResult = { ok: true; e164: string | null } | { ok: false; error: string };

const ARABIC_INDIC = /[٠-٩۰-۹]/g;

function latinDigits(s: string): string {
  return s.replace(ARABIC_INDIC, (c) => {
    const code = c.charCodeAt(0);
    return String(code >= 0x06f0 ? code - 0x06f0 : code - 0x0660);
  });
}

export const PHONE_ERRORS = {
  short: 'الرقم ناقص. اكتب 10 أرقام تبدأ بـ 05',
  long: 'الرقم أطول من اللازم. اكتب 10 أرقام تبدأ بـ 05',
  prefix: 'الرقم لا يبدأ بـ 05. اكتب رقم جوال سعودي يبدأ بـ 05',
  chars: 'الرقم فيه رموز غير أرقام. اكتب 10 أرقام تبدأ بـ 05',
} as const;

export function normalizePhone(input: string): PhoneResult {
  const raw = latinDigits(input).replace(/[\s\-().]/g, '');
  if (raw === '') return { ok: true, e164: null };
  let digits = raw;
  if (digits.startsWith('+')) digits = digits.slice(1);
  if (!/^\d+$/.test(digits)) return { ok: false, error: PHONE_ERRORS.chars };
  if (digits.startsWith('00966')) digits = digits.slice(5);
  else if (digits.startsWith('966')) digits = digits.slice(3);
  else if (digits.startsWith('0')) digits = digits.slice(1);
  // now the national significant number: 5XXXXXXXX (9 digits)
  if (!digits.startsWith('5')) return { ok: false, error: PHONE_ERRORS.prefix };
  if (digits.length < 9) return { ok: false, error: PHONE_ERRORS.short };
  if (digits.length > 9) return { ok: false, error: PHONE_ERRORS.long };
  return { ok: true, e164: `966${digits}` };
}

/** 9665XXXXXXXX → 05XXXXXXXX (as the user types it). */
export function toLocal(e164: string): string {
  return e164.startsWith('966') ? `0${e164.slice(3)}` : e164;
}

/** 9665XXXXXXXX → «055 123 4567» for display. */
export function formatPhone(e164: string): string {
  const l = toLocal(e164);
  return l.length === 10 ? `${l.slice(0, 3)} ${l.slice(3, 6)} ${l.slice(6)}` : l;
}
