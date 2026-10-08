/** WhatsApp only through wa.me (CLAUDE.md). No API, no automatic sending. */
export function waLink(phoneE164: string, message: string): string {
  if (!/^9665\d{8}$/.test(phoneE164)) throw new Error('رقم الجوال غير صالح للواتساب');
  return `https://wa.me/${phoneE164}?text=${encodeURIComponent(message)}`;
}

/** Long links can break on some phones; keep messages short (brief.md). */
export const WA_SAFE_URL_LENGTH = 2000;

export function waLinkTooLong(phoneE164: string, message: string): boolean {
  return waLink(phoneE164, message).length > WA_SAFE_URL_LENGTH;
}

/** Non-empty lines, for the «N أسطر · الحد 5» counter. */
export function lineCount(message: string): number {
  return message.split('\n').filter((l) => l.trim()).length;
}
