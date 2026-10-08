const NUM = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });

/** Latin digits with thousands separators: 28,500 */
export function formatNumber(n: number): string {
  return NUM.format(n);
}

/** «2,500 ر.س» */
export function formatSAR(n: number): string {
  return `${formatNumber(n)} ر.س`;
}

/** First word of a full name for greetings: «أحمد». */
export function firstName(fullName: string | null | undefined): string {
  return (fullName ?? '').trim().split(/\s+/)[0] ?? '';
}

/** «صباح الخير» before noon (Riyadh), «مساء الخير» after. */
export function greeting(now: Date): string {
  const hour = Number(new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Riyadh', hour: 'numeric', hourCycle: 'h23' }).format(now));
  return hour < 12 ? 'صباح الخير' : 'مساء الخير';
}
