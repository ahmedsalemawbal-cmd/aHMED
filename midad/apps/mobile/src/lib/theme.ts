import { Appearance } from 'react-native'

/**
 * نظام تصميم مِداد للجوّال.
 *
 * قاعدة اللون: الهوية **بنفسجيّ نيليّ**، والأخضر **دلالةٌ لا هوية** —
 * يعني «حاضر» و«نجح» و«مكتمل» فقط. وكلّ فئةٍ في الوصول السريع لها لونها،
 * فيتكوّن تسلسلٌ بصريّ بدل لونٍ واحدٍ يعمّ الشاشة.
 */
export interface Palette {
  bg: string; card: string; cardAlt: string; sunken: string
  border: string; borderStrong: string
  text: string; text2: string; text3: string

  primary: string; primaryPress: string; primarySoft: string; primarySoftFg: string
  primaryDeep: string; onPrimary: string

  success: string; successSoft: string
  warn: string; warnSoft: string
  danger: string; dangerSoft: string
  info: string; infoSoft: string

  /** ألوان الفئات — لبلاطات الوصول السريع وشارات الأدوار */
  tint: { violet: string; teal: string; amber: string; blue: string; rose: string; lime: string }
  tintSoft: { violet: string; teal: string; amber: string; blue: string; rose: string; lime: string }

  shadow: string
}

export const LIGHT: Palette = {
  bg: '#F5F4FB', card: '#FFFFFF', cardAlt: '#FBFAFF', sunken: '#EFEEF8',
  border: '#E4E2F0', borderStrong: '#CFCCE4',
  text: '#191733', text2: '#5B5878', text3: '#8B88A6',

  primary: '#5B4BD6', primaryPress: '#4A3BC0', primarySoft: '#ECE9FC',
  primarySoftFg: '#4436B4', primaryDeep: '#241C63', onPrimary: '#FFFFFF',

  success: '#0E9F6E', successSoft: '#E2F6EE',
  warn: '#B4791B', warnSoft: '#FBF0DC',
  danger: '#D64545', dangerSoft: '#FCE9E9',
  info: '#2E7BD6', infoSoft: '#E6F0FC',

  tint: { violet: '#6C5CE7', teal: '#0EA5A5', amber: '#E08D2B', blue: '#3B82F6', rose: '#DB4E8B', lime: '#5AA02C' },
  tintSoft: { violet: '#EEEBFD', teal: '#DFF4F4', amber: '#FBEEDC', blue: '#E6EFFE', rose: '#FBE7F0', lime: '#EAF5E1' },

  shadow: '#231F45',
}

export const DARK: Palette = {
  bg: '#12111C', card: '#1C1A2B', cardAlt: '#211F33', sunken: '#181626',
  border: '#2E2B44', borderStrong: '#403C5C',
  text: '#EDECF7', text2: '#B3B0C9', text3: '#8A87A3',

  primary: '#8B7CF0', primaryPress: '#9C8FF5', primarySoft: '#2A2450',
  primarySoftFg: '#C4BAFB', primaryDeep: '#1A153C', onPrimary: '#141033',

  success: '#3FCB96', successSoft: '#183A2E',
  warn: '#E3B155', warnSoft: '#3A2F1A',
  danger: '#F0736B', dangerSoft: '#3D2028',
  info: '#6BA9F0', infoSoft: '#1B2C45',

  tint: { violet: '#9B8DF5', teal: '#3ECFCF', amber: '#F0AE5C', blue: '#6BA1F8', rose: '#F07AB0', lime: '#8CCB5C' },
  tintSoft: { violet: '#2A2450', teal: '#123536', amber: '#3A2C16', blue: '#16263F', rose: '#3A1B2C', lime: '#1F2E14' },

  shadow: '#000000',
}

/** أنصاف أقطار أكبر قليلًا — كما في المراجع المعتمدة */
export const RADIUS = { xs: 10, sm: 12, md: 14, lg: 18, xl: 22, xxl: 26, pill: 999 }
export const SPACE = { s1: 4, s2: 8, s3: 12, s4: 16, s5: 20, s6: 24, s7: 32, s8: 40 }

export const FAMILY = {
  regular: 'Cairo_400Regular',
  medium: 'Cairo_500Medium',
  semi: 'Cairo_600SemiBold',
  bold: 'Cairo_700Bold',
} as const

/**
 * سلّم الخطّ — خُفّض عن السابق لأنّ العربية أعرض من اللاتينية،
 * فالمقاسات الكبيرة كانت تكسر أسطر الأزرار على شاشة 390 بكسل.
 */
export const TYPE = {
  micro: 9.5,
  caption: 10.5,
  small: 11,
  body: 12,
  bodyLg: 13,
  base: 13.5,
  lead: 14.5,
  h3: 15.5,
  h2: 17,
  h1: 20,
  display: 24,
} as const

export function fontFor(weight?: '400' | '500' | '600' | '700'): string {
  if (weight === '700') return FAMILY.bold
  if (weight === '600') return FAMILY.semi
  if (weight === '500') return FAMILY.medium
  return FAMILY.regular
}

/** ظلٌّ ناعم موحّد — بديل الحدود الثقيلة */
export function elevation(c: Palette, level: 1 | 2 | 3 = 1) {
  const cfg = {
    1: { o: 0.05, r: 8, h: 2, e: 2 },
    2: { o: 0.08, r: 16, h: 6, e: 5 },
    3: { o: 0.12, r: 26, h: 12, e: 9 },
  }[level]
  return {
    shadowColor: c.shadow,
    shadowOpacity: cfg.o,
    shadowRadius: cfg.r,
    shadowOffset: { width: 0, height: cfg.h },
    elevation: cfg.e,
  }
}

export function systemIsDark(): boolean {
  return Appearance.getColorScheme() === 'dark'
}
