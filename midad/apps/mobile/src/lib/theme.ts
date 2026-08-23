import { Appearance } from 'react-native'

export interface Palette {
  bg: string; card: string; sunken: string; border: string; borderStrong: string
  text: string; text2: string; text3: string
  accent: string; accentHover: string; accentSoft: string; accentSoftFg: string; accentDeep: string; onAccent: string
  success: string; successSoft: string; warn: string; warnSoft: string
  danger: string; dangerSoft: string; info: string; infoSoft: string
}

/** نفس رموز نظام تصميم الويب — محوّلةً إلى sRGB لأنّ React Native لا يفهم oklch. */
export const LIGHT: Palette = {
  bg: '#f2f5f1', card: '#ffffff', sunken: '#eef1ed', border: '#dfe4dd', borderStrong: '#c3ccc0',
  text: '#222924', text2: '#5d6660', text3: '#727b75',
  accent: '#1f7a4d', accentHover: '#186640', accentSoft: '#e2f4e8', accentSoftFg: '#155e3c', accentDeep: '#0f3524',
  onAccent: '#ffffff',
  success: '#1c7346', successSoft: '#e1f4e7', warn: '#8a6410', warnSoft: '#f8efd9',
  danger: '#b32d20', dangerSoft: '#fae5e2', info: '#2b6cb0', infoSoft: '#e4eefa',
}

export const DARK: Palette = {
  bg: '#151b17', card: '#1d241f', sunken: '#191f1b', border: '#333c36', borderStrong: '#454f48',
  text: '#e9efe9', text2: '#b3bdb6', text3: '#909a94',
  accent: '#4ec98a', accentHover: '#63d698', accentSoft: '#26402f', accentSoftFg: '#a9e8c4',
  accentDeep: '#1d3327', onAccent: '#0d1410',
  success: '#5ad294', successSoft: '#22402f', warn: '#e0b552', warnSoft: '#3a3120',
  danger: '#e1705f', dangerSoft: '#3d2622', info: '#7ab4ee', infoSoft: '#1f3245',
}

/* أنصاف الأقطار وسلّم الخطّ من ملفّ تصميم المالك «تطبيق مداد للجوال» */
export const RADIUS = { xs: 9, sm: 10, md: 12, lg: 14, xl: 17, xxl: 20, pill: 999 }
export const SPACE = { s1: 4, s2: 8, s3: 12, s4: 16, s5: 20, s6: 24, s7: 32, s8: 40 }

/** أسماء عائلات Cairo كما تُحمَّل — تُستعمل بدل fontWeight
 *  لأنّ أندرويد لا يربط الأوزان بأسماء العائلة تلقائيًّا. */
export const FAMILY = {
  regular: 'Cairo_400Regular',
  medium: 'Cairo_500Medium',
  semi: 'Cairo_600SemiBold',
  bold: 'Cairo_700Bold',
} as const

/** سلّم الخطّ من التصميم: 6 → 14، ثمّ مقاسات العناوين */
export const TYPE = {
  micro: 10,
  caption: 11,
  small: 11.5,
  body: 12.5,
  bodyLg: 13.5,
  base: 14,
  lead: 15,
  h3: 16,
  h2: 18,
  h1: 22,
  display: 26,
} as const

export function fontFor(weight?: '400' | '500' | '600' | '700'): string {
  if (weight === '700') return FAMILY.bold
  if (weight === '600') return FAMILY.semi
  if (weight === '500') return FAMILY.medium
  return FAMILY.regular
}

export function systemIsDark(): boolean {
  return Appearance.getColorScheme() === 'dark'
}
