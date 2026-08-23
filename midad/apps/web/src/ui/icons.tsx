import React from 'react'
type P = { size?: number; className?: string }
const S = (n?: number) => ({ width: n || 18, height: n || 18, viewBox: '0 0 22 22', fill: 'none' as const })
const st = { stroke: 'currentColor', strokeWidth: 1.7, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const }

export const IcHome = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M3.5 9.2 11 3.5l7.5 5.7V18a1 1 0 0 1-1 1h-4v-5h-5v5h-4a1 1 0 0 1-1-1Z" {...st}/></svg>
export const IcLibrary = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M4 4h5v14H4zM10.5 4h4v14h-4z" {...st}/><path d="m16.2 4.9 3 .8-3.2 12.4-2.4-.7" {...st}/></svg>
export const IcFiles = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M12.5 3H6a1.5 1.5 0 0 0-1.5 1.5v13A1.5 1.5 0 0 0 6 19h10a1.5 1.5 0 0 0 1.5-1.5V8Z" {...st}/><path d="M12.5 3v5h5" {...st}/></svg>
export const IcTable = ({ size, className }: P) => <svg {...S(size)} className={className}><rect x="3.5" y="4.5" width="15" height="13" rx="1.6" {...st}/><path d="M3.5 9h15M8.5 9v8.5" {...st}/></svg>
export const IcTeam = ({ size, className }: P) => <svg {...S(size)} className={className}><circle cx="8.5" cy="8" r="2.9" {...st}/><path d="M3 18c0-2.7 2.5-4.4 5.5-4.4S14 15.3 14 18" {...st}/><path d="M14.5 6.4a2.6 2.6 0 0 1 0 5.1M16 17.8c0-2 .6-3.4-1.5-4.4" {...st}/></svg>
export const IcUser = ({ size, className }: P) => <svg {...S(size)} className={className}><circle cx="11" cy="7.6" r="3.2" {...st}/><path d="M4.5 18.5c0-3.1 2.9-5.1 6.5-5.1s6.5 2 6.5 5.1" {...st}/></svg>
export const IcSettings = ({ size, className }: P) => <svg {...S(size)} className={className}><circle cx="11" cy="11" r="2.8" {...st}/><path d="M17.4 13.3a1.5 1.5 0 0 0 .3 1.6l.1.1a1.7 1.7 0 1 1-2.4 2.4l-.1-.1a1.5 1.5 0 0 0-2.5 1v.2a1.7 1.7 0 1 1-3.4 0v-.1a1.5 1.5 0 0 0-2.6-1l-.1.1a1.7 1.7 0 1 1-2.4-2.4l.1-.1a1.5 1.5 0 0 0-1-2.5H3a1.7 1.7 0 1 1 0-3.4h.1a1.5 1.5 0 0 0 1-2.6l-.1-.1a1.7 1.7 0 1 1 2.4-2.4l.1.1a1.5 1.5 0 0 0 1.6.3h.1a1.5 1.5 0 0 0 .9-1.4V3a1.7 1.7 0 1 1 3.4 0v.1a1.5 1.5 0 0 0 2.5 1l.1-.1a1.7 1.7 0 1 1 2.4 2.4l-.1.1a1.5 1.5 0 0 0 1 2.5h.2a1.7 1.7 0 1 1 0 3.4h-.1a1.5 1.5 0 0 0-1.4.9Z" {...st}/></svg>
export const IcCard = ({ size, className }: P) => <svg {...S(size)} className={className}><rect x="2.8" y="5" width="16.4" height="12" rx="2" {...st}/><path d="M2.8 9.3h16.4" {...st}/></svg>
export const IcInvoice = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M5 3.5h12v15l-2-1.3-2 1.3-2-1.3-2 1.3-2-1.3Z" {...st}/><path d="M8.4 8h5.2M8.4 11.5h5.2" {...st}/></svg>
export const IcChart = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M4 18V9.5M9.3 18V4.5M14.6 18v-6M19.4 18V7" {...st}/></svg>
export const IcSearch = ({ size, className }: P) => <svg {...S(size)} className={className}><circle cx="9.5" cy="9.5" r="5.6" {...st}/><path d="m13.7 13.7 4.4 4.4" {...st}/></svg>
export const IcPlus = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M11 4.6v12.8M4.6 11h12.8" {...st}/></svg>
export const IcCheck = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="m4.4 11.3 4.4 4.4 8.8-9.4" {...st}/></svg>
export const IcClose = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M5.6 5.6 16.4 16.4M16.4 5.6 5.6 16.4" {...st}/></svg>
export const IcMenu = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M4 6.5h14M4 11h14M4 15.5h14" {...st}/></svg>
export const IcChevron = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="m7.8 4.4 6.4 6.6-6.4 6.6" {...st}/></svg>
export const IcChevronDown = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="m4.4 7.8 6.6 6.4 6.6-6.4" {...st}/></svg>
export const IcBack = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M4 11h14M9.6 5.4 4 11l5.6 5.6" {...st}/></svg>
export const IcSun = ({ size, className }: P) => <svg {...S(size)} className={className}><circle cx="11" cy="11" r="4" {...st}/><path d="M11 2.5V5M11 17v2.5M3.5 11H6M16 11h2.5M5.6 5.6 7.4 7.4M16.4 5.6 14.6 7.4M5.6 16.4 7.4 14.6M16.4 16.4 14.6 14.6" {...st}/></svg>
export const IcMoon = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M15 12.5A6.5 6.5 0 0 1 8.5 6 6.5 6.5 0 1 0 15 12.5Z" fill="currentColor"/></svg>
export const IcDownload = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M11 3.6v9.8M6.8 9.8 11 14l4.2-4.2M4 17.5h14" {...st}/></svg>
export const IcCopy = ({ size, className }: P) => <svg {...S(size)} className={className}><rect x="7.5" y="7.5" width="10" height="10" rx="1.8" {...st}/><path d="M14 5.2A1.7 1.7 0 0 0 12.3 4H6a2 2 0 0 0-2 2v6.3c0 .8.5 1.4 1.2 1.6" {...st}/></svg>
export const IcTrash = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M4.6 6.2h12.8M9 6.2V4.6h4v1.6M6.4 6.2 7.2 18h7.6l.8-11.8M9.3 9.3v5.6M12.7 9.3v5.6" {...st}/></svg>
export const IcEdit = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="m13.6 4.6 3.8 3.8L8 17.8l-4.4.6.6-4.4Z" {...st}/></svg>
export const IcSpark = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M11 3.2 12.6 8 17.4 9.6 12.6 11.2 11 16 9.4 11.2 4.6 9.6 9.4 8Z" {...st}/><path d="M16.6 14.2 17.3 16.1 19.2 16.8 17.3 17.5 16.6 19.4 15.9 17.5 14 16.8 15.9 16.1Z" {...st}/></svg>
export const IcEye = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M1.9 11S5.3 5.2 11 5.2 20.1 11 20.1 11 16.7 16.8 11 16.8 1.9 11 1.9 11Z" {...st}/><circle cx="11" cy="11" r="2.6" {...st}/></svg>
export const IcEyeOff = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M8.2 5.7A8.4 8.4 0 0 1 11 5.2c5.7 0 9.1 5.8 9.1 5.8a16 16 0 0 1-2.9 3.6M5.5 7.3A15.9 15.9 0 0 0 1.9 11s3.4 5.8 9.1 5.8a8.5 8.5 0 0 0 3.1-.6M4 4l14 14" {...st}/></svg>
export const IcLock = ({ size, className }: P) => <svg {...S(size)} className={className}><rect x="4.8" y="9.5" width="12.4" height="8.5" rx="2" {...st}/><path d="M7.6 9.5V7.2a3.4 3.4 0 0 1 6.8 0v2.3" {...st}/></svg>
export const IcLogout = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M8 4.5H5.5a1.5 1.5 0 0 0-1.5 1.5v10a1.5 1.5 0 0 0 1.5 1.5H8M12.4 14.6 16 11l-3.6-3.6M16 11H7.4" {...st}/></svg>
export const IcPrint = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M6.4 8.4V3.6h9.2v4.8" {...st}/><rect x="3.6" y="8.4" width="14.8" height="6.4" rx="1.6" {...st}/><path d="M6.4 12.6h9.2v5.8H6.4Z" {...st}/></svg>
export const IcClock = ({ size, className }: P) => <svg {...S(size)} className={className}><circle cx="11" cy="11" r="7.4" {...st}/><path d="M11 6.6V11l3 1.8" {...st}/></svg>
export const IcAlert = ({ size, className }: P) => <svg {...S(size)} className={className}><circle cx="11" cy="11" r="7.6" {...st}/><path d="M11 6.8v5M11 15.1v.1" {...st}/></svg>
export const IcShield = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M11 3.4 17 5.6v5c0 4-2.5 6.6-6 8-3.5-1.4-6-4-6-8v-5Z" {...st}/><path d="m8.4 10.8 1.9 1.9 3.5-3.7" {...st}/></svg>
export const IcKey = ({ size, className }: P) => <svg {...S(size)} className={className}><circle cx="7.4" cy="11" r="3.4" {...st}/><path d="M10.8 11h7.4v2.6M15.2 11v2.2" {...st}/></svg>
export const IcPuzzle = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M8.4 4h5.2v2.1a1.7 1.7 0 1 0 3.4 0V4h.6v13.4H5V4Z" {...st}/></svg>
export const IcBook = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M4 4.6h5.2c1 0 1.8.8 1.8 1.8v11c0-.8-.7-1.4-1.6-1.4H4Z" {...st}/><path d="M18 4.6h-5.2c-1 0-1.8.8-1.8 1.8v11c0-.8.7-1.4 1.6-1.4H18Z" {...st}/></svg>
export const IcSparkList = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M4 6.5h9M4 11h9M4 15.5h6" {...st}/><path d="m16.6 12.6.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8Z" {...st}/></svg>
export const IcHistory = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M3.7 11a7.3 7.3 0 1 0 2.2-5.2L3.6 8" {...st}/><path d="M3.4 4.6v3.6h3.6M11 7v4.3l3 1.7" {...st}/></svg>
export const IcWhatsapp = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M3.6 18.4 4.9 14a7.4 7.4 0 1 1 3 3Z" {...st}/><path d="M8.2 8.4c0 3 2.2 5 4.8 5.4l.9-1.3 1.7.8c-.3 1-1.2 1.4-2.3 1.3-3-.3-5.7-3-6-6-.1-1.1.3-2 1.3-2.3l.8 1.7Z" fill="currentColor" stroke="none"/></svg>
export const IcMail = ({ size, className }: P) => <svg {...S(size)} className={className}><rect x="3" y="5.2" width="16" height="11.6" rx="2" {...st}/><path d="m3.6 6.4 7.4 5 7.4-5" {...st}/></svg>
export const IcExternal = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M10.6 5H5.6A1.6 1.6 0 0 0 4 6.6v9.8A1.6 1.6 0 0 0 5.6 18h9.8a1.6 1.6 0 0 0 1.6-1.6v-5M12.4 4h5.6v5.6M9.6 12.4 18 4" {...st}/></svg>
export const IcGrid = ({ size, className }: P) => <svg {...S(size)} className={className}><rect x="3.6" y="3.6" width="6.2" height="6.2" rx="1.4" {...st}/><rect x="12.2" y="3.6" width="6.2" height="6.2" rx="1.4" {...st}/><rect x="3.6" y="12.2" width="6.2" height="6.2" rx="1.4" {...st}/><rect x="12.2" y="12.2" width="6.2" height="6.2" rx="1.4" {...st}/></svg>
export const IcList = ({ size, className }: P) => <svg {...S(size)} className={className}><path d="M7.4 6h10.2M7.4 11h10.2M7.4 16h10.2M4.2 6v.1M4.2 11v.1M4.2 16v.1" {...st}/></svg>
export const IcSpinner = ({ size, className }: P) => (
  <svg {...S(size)} className={'mdd-spin ' + (className || '')}>
    <circle cx="11" cy="11" r="8" stroke="currentColor" strokeWidth="2.4" opacity=".3"/>
    <path d="M19 11a8 8 0 0 0-8-8" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"/>
  </svg>
)
export const IcLogo = ({ size }: P) => (
  <svg width={size || 30} height={size || 30} viewBox="0 0 40 40" fill="none" aria-hidden="true">
    <rect width="40" height="40" rx="11" fill="currentColor" opacity=".14"/>
    <path d="M9.5 27.5V15.2c0-1 1.2-1.6 2-1L16 17.6c.5.4 1.2.4 1.7 0l4.6-3.4c.8-.6 2 0 2 1v12.3" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/>
    <path d="M28.4 12.5v13.2c0 1 .8 1.8 1.8 1.8" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"/>
  </svg>
)
