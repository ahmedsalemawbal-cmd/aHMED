type P = { size?: number; className?: string }

/* أيقوناتٌ مرسومةٌ بالخطّ لا بالملء — تتبع لونَ النصّ وحدَها،
   فلا يلزم لكلّ لونٍ ملفٌّ ثانٍ. و`currentColor` هي المفتاح. */
const S = (p: P) => ({
  width: p.size ?? 20, height: p.size ?? 20,
  viewBox: '0 0 24 24', fill: 'none',
  stroke: 'currentColor', strokeWidth: 1.7,
  strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const,
  className: p.className, 'aria-hidden': true,
})

export const IcMenu = (p: P) => <svg {...S(p)}><path d="M4 6h16M4 12h16M4 18h16" /></svg>
export const IcClose = (p: P) => <svg {...S(p)}><path d="M18 6 6 18M6 6l12 12" /></svg>
export const IcSun = (p: P) => <svg {...S(p)}><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
export const IcMoon = (p: P) => <svg {...S(p)}><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" /></svg>
export const IcGlobe = (p: P) => <svg {...S(p)}><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18Z" /></svg>
export const IcPhone = (p: P) => <svg {...S(p)}><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.5 2.8.6a2 2 0 0 1 1.7 2Z" /></svg>
export const IcMail = (p: P) => <svg {...S(p)}><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m2 7 10 6 10-6" /></svg>
export const IcWhatsapp = (p: P) => <svg {...S(p)}><path d="M21 11.5a8.4 8.4 0 0 1-12.3 7.5L3 20.5l1.6-5.5A8.4 8.4 0 1 1 21 11.5Z" /><path d="M8.6 9.2c.2 1.6 2.6 4 4.2 4.2l1-.9 1.6.7v1.3c-2.6.6-6.5-3.3-5.9-5.9h1.3l.7 1.6-.9 1" /></svg>
export const IcChevron = (p: P) => <svg {...S(p)}><path d="m9 6 6 6-6 6" /></svg>
export const IcCheck = (p: P) => <svg {...S(p)}><path d="m5 12 5 5L20 7" /></svg>
export const IcCart = (p: P) => <svg {...S(p)}><circle cx="9" cy="20" r="1.4" /><circle cx="18" cy="20" r="1.4" /><path d="M2 3h2.5l2.3 11.4a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 2-1.6L21 7H5.2" /></svg>
export const IcDoor = (p: P) => <svg {...S(p)}><path d="M4 21h16M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17" /><circle cx="14.5" cy="12" r="1" /></svg>
export const IcPanel = (p: P) => <svg {...S(p)}><rect x="3" y="4" width="18" height="16" rx="1.5" /><path d="M9 4v16M15 4v16" /></svg>
export const IcStrut = (p: P) => <svg {...S(p)}><path d="M4 7h16v10H4z" /><path d="M4 11h16M8 7v10M16 7v10" /></svg>
