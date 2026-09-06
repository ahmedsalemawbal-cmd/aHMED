import React from 'react'
import Svg, { Path, Circle, Rect, Line } from 'react-native-svg'
import { useApp } from '../lib/store'

/**
 * مجموعة أيقونات مِداد للجوّال — مقاسٌ واحد، سماكة واحدة، وتأخذ اللون من نصّها.
 * مرسومةٌ بـ SVG كما في تصميم المالك (91 أيقونة، صفر إيموجي).
 */
export interface IconProps {
  size?: number
  color?: string
  strokeWidth?: number
  filled?: boolean
}

const V = 22
const useColor = (c?: string) => {
  const { c: pal } = useApp()
  return c || pal.text2
}

function Base({ size = 22, children }: { size?: number; children: React.ReactNode }) {
  return <Svg width={size} height={size} viewBox={`0 0 ${V} ${V}`} fill="none">{children}</Svg>
}

const S = (w?: number) => ({
  strokeWidth: w ?? 1.7,
  strokeLinecap: 'round' as const,
  strokeLinejoin: 'round' as const,
  fill: 'none' as const,
})

/* ---------- التنقّل السفليّ ---------- */
export const IcHome = ({ size, color, strokeWidth, filled }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M3.5 9.2 11 3.5l7.5 5.7V18a1 1 0 0 1-1 1h-4v-5h-5v5h-4a1 1 0 0 1-1-1Z"
        stroke={s} {...S(strokeWidth)} fill={filled ? s : 'none'} fillOpacity={filled ? 0.14 : 0} />
    </Base>
  )
}

export const IcLibrary = ({ size, color, strokeWidth, filled }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Rect x="3.6" y="4" width="4.6" height="14" rx="1.2" stroke={s} {...S(strokeWidth)} fill={filled ? s : 'none'} fillOpacity={filled ? 0.14 : 0} />
      <Rect x="9.4" y="4" width="4.2" height="14" rx="1.2" stroke={s} {...S(strokeWidth)} />
      <Path d="m15.6 5 3.1.85-2.6 12.3-1.6-.45" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcFiles = ({ size, color, strokeWidth, filled }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M12.5 3H6a1.5 1.5 0 0 0-1.5 1.5v13A1.5 1.5 0 0 0 6 19h10a1.5 1.5 0 0 0 1.5-1.5V8Z"
        stroke={s} {...S(strokeWidth)} fill={filled ? s : 'none'} fillOpacity={filled ? 0.14 : 0} />
      <Path d="M12.5 3v5h5" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcTable = ({ size, color, strokeWidth, filled }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Rect x="3.5" y="4.5" width="15" height="13" rx="1.8" stroke={s} {...S(strokeWidth)} fill={filled ? s : 'none'} fillOpacity={filled ? 0.14 : 0} />
      <Path d="M3.5 9h15M8.6 9v8.5" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcUser = ({ size, color, strokeWidth, filled }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Circle cx="11" cy="7.6" r="3.2" stroke={s} {...S(strokeWidth)} fill={filled ? s : 'none'} fillOpacity={filled ? 0.14 : 0} />
      <Path d="M4.6 18.4c0-3.1 2.9-5 6.4-5s6.4 1.9 6.4 5" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

/* ---------- الأفعال والحالات ---------- */
export const IcSearch = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Circle cx="9.5" cy="9.5" r="5.6" stroke={s} {...S(strokeWidth)} /><Path d="m13.7 13.7 4.4 4.4" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcPlus = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M11 4.6v12.8M4.6 11h12.8" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcCheck = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="m4.4 11.3 4.4 4.4 8.8-9.4" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcClose = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M5.6 5.6 16.4 16.4M16.4 5.6 5.6 16.4" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcTrash = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M4.6 6.2h12.8M9 6.2V4.6h4v1.6M6.4 6.2 7.2 18h7.6l.8-11.8M9.3 9.3v5.6M12.7 9.3v5.6" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcChevron = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="m13.4 4.4-6.4 6.6 6.4 6.6" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcChevronDown = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="m4.4 7.8 6.6 6.4 6.6-6.4" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcBack = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M4 11h14M9.6 5.4 4 11l5.6 5.6" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcDownload = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M11 3.6v9.8M6.8 9.8 11 14l4.2-4.2M4 17.5h14" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcShare = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M11 15V3.8M7.4 7.4 11 3.8l3.6 3.6M4.6 12.6v4.2a1.4 1.4 0 0 0 1.4 1.4h10a1.4 1.4 0 0 0 1.4-1.4v-4.2" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcSpark = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M11 3.2 12.6 8 17.4 9.6 12.6 11.2 11 16 9.4 11.2 4.6 9.6 9.4 8Z" stroke={s} {...S(strokeWidth)} /><Path d="M16.6 14.2 17.3 16.1 19.2 16.8 17.3 17.5 16.6 19.4 15.9 17.5 14 16.8 15.9 16.1Z" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcEye = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M1.9 11S5.3 5.2 11 5.2 20.1 11 20.1 11 16.7 16.8 11 16.8 1.9 11 1.9 11Z" stroke={s} {...S(strokeWidth)} /><Circle cx="11" cy="11" r="2.6" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcEyeOff = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M8.2 5.7A8.4 8.4 0 0 1 11 5.2c5.7 0 9.1 5.8 9.1 5.8a16 16 0 0 1-2.9 3.6M5.5 7.3A15.9 15.9 0 0 0 1.9 11s3.4 5.8 9.1 5.8a8.5 8.5 0 0 0 3.1-.6M4 4l14 14" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcLock = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Rect x="4.8" y="9.5" width="12.4" height="8.5" rx="2" stroke={s} {...S(strokeWidth)} /><Path d="M7.6 9.5V7.2a3.4 3.4 0 0 1 6.8 0v2.3" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcLogout = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M8 4.5H5.5a1.5 1.5 0 0 0-1.5 1.5v10a1.5 1.5 0 0 0 1.5 1.5H8M12.4 14.6 16 11l-3.6-3.6M16 11H7.4" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcClock = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Circle cx="11" cy="11" r="7.4" stroke={s} {...S(strokeWidth)} /><Path d="M11 6.6V11l3 1.8" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcAlert = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Circle cx="11" cy="11" r="7.6" stroke={s} {...S(strokeWidth)} /><Path d="M11 6.8v5M11 15.1v.1" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcKey = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Circle cx="7.4" cy="11" r="3.4" stroke={s} {...S(strokeWidth)} /><Path d="M10.8 11h7.4v2.6M15.2 11v2.2" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcFolder = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M3.6 6.4a1.6 1.6 0 0 1 1.6-1.6h3.3l1.8 2.2h6.1a1.6 1.6 0 0 1 1.6 1.6v7.6a1.6 1.6 0 0 1-1.6 1.6H5.2a1.6 1.6 0 0 1-1.6-1.6Z" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcExternal = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M10.6 5H5.6A1.6 1.6 0 0 0 4 6.6v9.8A1.6 1.6 0 0 0 5.6 18h9.8a1.6 1.6 0 0 0 1.6-1.6v-5M12.4 4h5.6v5.6M9.6 12.4 18 4" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcSettings = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Circle cx="11" cy="11" r="2.9" stroke={s} {...S(strokeWidth)} /><Path d="M17.2 13.1a1.4 1.4 0 0 0 .3 1.6l.1.1a1.7 1.7 0 1 1-2.4 2.4l-.1-.1a1.4 1.4 0 0 0-2.4 1v.2a1.7 1.7 0 1 1-3.4 0v-.1a1.4 1.4 0 0 0-2.5-1l-.1.1a1.7 1.7 0 1 1-2.4-2.4l.1-.1a1.4 1.4 0 0 0-1-2.4H3.2a1.7 1.7 0 1 1 0-3.4h.1a1.4 1.4 0 0 0 1-2.5l-.1-.1a1.7 1.7 0 1 1 2.4-2.4l.1.1a1.4 1.4 0 0 0 2.4-1V3.2a1.7 1.7 0 1 1 3.4 0v.1a1.4 1.4 0 0 0 2.4 1l.1-.1a1.7 1.7 0 1 1 2.4 2.4l-.1.1a1.4 1.4 0 0 0 1 2.4h.2a1.7 1.7 0 1 1 0 3.4h-.1a1.4 1.4 0 0 0-1.3.9Z" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcCard = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Rect x="2.8" y="5" width="16.4" height="12" rx="2" stroke={s} {...S(strokeWidth)} /><Path d="M2.8 9.3h16.4" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcTeam = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Circle cx="8.5" cy="8" r="2.9" stroke={s} {...S(strokeWidth)} /><Path d="M3 18c0-2.7 2.5-4.4 5.5-4.4S14 15.3 14 18M14.5 6.4a2.6 2.6 0 0 1 0 5.1M16 17.8c0-2 .6-3.4-1.5-4.4" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcPrint = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M6.4 8.4V3.6h9.2v4.8" stroke={s} {...S(strokeWidth)} /><Rect x="3.6" y="8.4" width="14.8" height="6.4" rx="1.6" stroke={s} {...S(strokeWidth)} /><Path d="M6.4 12.6h9.2v5.8H6.4Z" stroke={s} {...S(strokeWidth)} /></Base>
}
export const IcRefresh = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return <Base size={size}><Path d="M3.7 11a7.3 7.3 0 1 0 2.2-5.2L3.6 8" stroke={s} {...S(strokeWidth)} /><Path d="M3.4 4.6v3.6h3.6" stroke={s} {...S(strokeWidth)} /></Base>
}

/* ---------- الشعار ---------- */
export const IcLogo = ({ size = 40, color }: IconProps) => {
  const { c } = useApp()
  const mark = color || c.onPrimary
  return (
    <Svg width={size} height={size} viewBox="0 0 40 40" fill="none">
      <Path d="M9.5 27.5V15.2c0-1 1.2-1.6 2-1L16 17.6c.5.4 1.2.4 1.7 0l4.6-3.4c.8-.6 2 0 2 1v12.3"
        stroke={mark} strokeWidth={2.6} strokeLinecap="round" strokeLinejoin="round" />
      <Path d="M28.4 12.5v13.2c0 1 .8 1.8 1.8 1.8" stroke={mark} strokeWidth={2.6} strokeLinecap="round" />
    </Svg>
  )
}

/* ---------- الهيدر والدُّرج ---------- */
export const IcMenu = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M3.6 6.2h14.8" stroke={s} {...S(strokeWidth)} />
      <Path d="M3.6 11h14.8" stroke={s} {...S(strokeWidth)} />
      <Path d="M3.6 15.8h9.4" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcBell = ({ size, color, strokeWidth, filled }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M5.6 9.4a5.4 5.4 0 1 1 10.8 0c0 3 .8 4.5 1.6 5.4H4c.8-.9 1.6-2.4 1.6-5.4Z"
        stroke={s} {...S(strokeWidth)} fill={filled ? s : 'none'} fillOpacity={filled ? 0.14 : 0} />
      <Path d="M9 17.6a2.2 2.2 0 0 0 4 0" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcCamera = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M3 8.4a1.6 1.6 0 0 1 1.6-1.6h1.9l1-1.9h5l1 1.9h1.9A1.6 1.6 0 0 1 17 8.4v7.2a1.6 1.6 0 0 1-1.6 1.6H4.6A1.6 1.6 0 0 1 3 15.6Z"
        stroke={s} {...S(strokeWidth)} />
      <Circle cx="10" cy="11.8" r="3.1" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcImage = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Rect x="3.2" y="4.4" width="15.6" height="13.2" rx="2.4" stroke={s} {...S(strokeWidth)} />
      <Circle cx="8" cy="9" r="1.5" stroke={s} {...S(strokeWidth)} />
      <Path d="M3.6 15 8 11.2l3.2 2.6 3-2.4 3.2 2.8" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcCalendar = ({ size, color, strokeWidth, filled }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Rect x="3.2" y="4.8" width="15.6" height="13.4" rx="2.4" stroke={s} {...S(strokeWidth)}
        fill={filled ? s : 'none'} fillOpacity={filled ? 0.12 : 0} />
      <Path d="M3.2 9.2h15.6M7.4 3.2v3M14.6 3.2v3" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcChart = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M3.4 18.4h15.2" stroke={s} {...S(strokeWidth)} />
      <Path d="M6.4 18.4v-5.2M11 18.4V6.6M15.6 18.4v-8" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcPdf = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M5 3.4h6.6L17 8.8v9.8a1.6 1.6 0 0 1-1.6 1.6H5a1.6 1.6 0 0 1-1.6-1.6V5a1.6 1.6 0 0 1 1.6-1.6Z"
        stroke={s} {...S(strokeWidth)} />
      <Path d="M11.4 3.6v5.2H16.8" stroke={s} {...S(strokeWidth)} />
      <Path d="M6.6 16.4v-3.6h1.2a1.1 1.1 0 0 1 0 2.2H6.6" stroke={s} {...S(strokeWidth)} />
      <Path d="M10.6 16.4v-3.6h1.1a1.8 1.8 0 0 1 0 3.6Z" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcWord = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M5 3.4h6.6L17 8.8v9.8a1.6 1.6 0 0 1-1.6 1.6H5a1.6 1.6 0 0 1-1.6-1.6V5a1.6 1.6 0 0 1 1.6-1.6Z"
        stroke={s} {...S(strokeWidth)} />
      <Path d="M11.4 3.6v5.2H16.8" stroke={s} {...S(strokeWidth)} />
      <Path d="m6.2 12.6.9 3.8 1.1-2.8 1.1 2.8.9-3.8" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcExcel = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M5 3.4h6.6L17 8.8v9.8a1.6 1.6 0 0 1-1.6 1.6H5a1.6 1.6 0 0 1-1.6-1.6V5a1.6 1.6 0 0 1 1.6-1.6Z"
        stroke={s} {...S(strokeWidth)} />
      <Path d="M11.4 3.6v5.2H16.8" stroke={s} {...S(strokeWidth)} />
      <Path d="m6.4 12.6 3.4 3.8M9.8 12.6l-3.4 3.8" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcHelp = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Circle cx="11" cy="11" r="7.7" stroke={s} {...S(strokeWidth)} />
      <Path d="M8.9 8.8a2.1 2.1 0 1 1 2.9 1.9c-.5.3-.8.7-.8 1.3v.4" stroke={s} {...S(strokeWidth)} />
      <Path d="M11 15.4v.05" stroke={s} {...S(strokeWidth)} strokeWidth={2.2} />
    </Base>
  )
}

export const IcMoon = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M17.6 13.4A7.2 7.2 0 0 1 8.6 4.4a7.4 7.4 0 1 0 9 9Z" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcSun = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Circle cx="11" cy="11" r="4" stroke={s} {...S(strokeWidth)} />
      <Path d="M11 1.9v2.2M11 17.9v2.2M1.9 11h2.2M17.9 11h2.2M4.6 4.6l1.6 1.6M15.8 15.8l1.6 1.6M17.4 4.6l-1.6 1.6M6.2 15.8l-1.6 1.6"
        stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}

export const IcFilter = ({ size, color, strokeWidth }: IconProps) => {
  const s = useColor(color)
  return (
    <Base size={size}>
      <Path d="M3.4 5.4h15.2l-5.9 6.9v5.2l-3.4 1.8v-7L3.4 5.4Z" stroke={s} {...S(strokeWidth)} />
    </Base>
  )
}
