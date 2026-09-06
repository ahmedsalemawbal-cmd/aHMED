import { DICT_EN, DICT_UR } from './dict'

/**
 * ══ اللغة ══
 *
 * ثلاثٌ: العربيّةُ أصلًا، والإنجليزيّةُ والأردو ترجمة. والأردو **من اليمين
 * كالعربيّة** — فليست اللغةُ هي التي تحدّد الاتّجاه بل الخطُّ الذي تُكتب به.
 * ومن ظنّ أنّ «غيرَ العربيّة يساريّة» قلب الأردو خطأً.
 *
 *     الاتّجاهُ للخطّ لا للّغة.
 */
export type Lang = 'ar' | 'en' | 'ur'

export const LANGS: Lang[] = ['ar', 'en', 'ur']

export const LANG_NAMES: Record<Lang, string> = {
  ar: 'العربية',
  en: 'English',
  ur: 'اردو',
}

const KEY = 'osoul.lang'

/** اتّجاهُ الكتابة — والأردو يمينيّةٌ مثل العربيّة. */
export function dirOf(lang: Lang): 'rtl' | 'ltr' {
  return lang === 'en' ? 'ltr' : 'rtl'
}

export function readLang(): Lang {
  try {
    const v = localStorage.getItem(KEY)
    if (v === 'ar' || v === 'en' || v === 'ur') return v
  } catch {
    /* التخزينُ يرمي في التصفّح الخاصّ — والعربيّةُ هي الأصل فلا ضرر */
  }
  return 'ar'
}

/**
 * اللغةُ الحاليّة في وحدةٍ واحدة.
 *
 * ولمَ متغيّرٌ على مستوى الوحدة ولم يُكتفَ بحالة React؟ لأنّ `t()` تُنادى من
 * شيفرةٍ ليست مكوّنًا — من مُنسّقِ التواريخ، ومن رسائل الخطأ في `lib/`. ولو
 * لزمها سياقٌ لَما استُعملت إلّا داخل شجرة React.
 */
let current: Lang = 'ar'

export function getLang(): Lang {
  return current
}

/** يُطبّق اللغةَ على الوثيقة ويحفظها. لا يُعيد الرسمَ — ذاك عملُ المزوّد. */
export function applyLang(lang: Lang) {
  current = lang
  try {
    localStorage.setItem(KEY, lang)
  } catch {
    /* لا شيء */
  }
  const el = document.documentElement
  el.lang = lang
  el.dir = dirOf(lang)
  el.classList.toggle('lang-en', lang === 'en')
  el.classList.toggle('lang-ur', lang === 'ur')
}

/**
 * الترجمة — والمفتاحُ هو النصُّ العربيّ.
 *
 * وسلسلةُ الرجوع: أردو ← إنجليزيّ ← عربيّ. فالمفتاحُ الذي لم يُترجَم إلى
 * الأردو يظهر إنجليزيًّا (وهو مفهومٌ لقارئها غالبًا) لا فارغًا، والذي لم
 * يُترجَم أصلًا يظهر عربيًّا صحيحًا. **ولا يظهر مفتاحٌ خامٌ للزائر أبدًا.**
 */
export function t(ar: string): string {
  if (current === 'en') return DICT_EN[ar] ?? ar
  if (current === 'ur') return DICT_UR[ar] ?? DICT_EN[ar] ?? ar
  return ar
}

/**
 * حقلٌ ثنائيُّ اللغة من القاعدة — مثل `name` و`name_en` في جدول المنتجات.
 *
 * وهذه غيرُ `t()`: تلك تترجم نصًّا **في الشيفرة**، وهذه تختار عمودًا **من
 * الصفّ**. والمنتجاتُ لا تُترجَم في الشيفرة لأنّ أسماءها بيانٌ يحرّره أهلُه.
 */
export function pick(row: Record<string, any>, base: string): string {
  // والعربيّةُ عمودٌ باسمه أيضًا (`name_ar`) لا العمودَ المجرّد (`name`).
  // وكانت تُقرأ `row[base]` فترجع فارغةً دائمًا — والبطاقاتُ تُرسم بعددها
  // الصحيح وهي **خالية**. كشفتها الصورةُ لا العدّ:
  //
  //     العدُّ يشهد بالوجود، والصورةُ تشهد بالمحتوى.
  //
  // و`?? row[base]` تبقى لصفٍّ أحاديِّ اللغة يأتي من مصدرٍ آخر.
  const ar = row[`${base}_ar`] ?? row[base] ?? ''
  if (current === 'ar') return ar
  const v = row[`${base}_${current}`]
  if (v) return v
  // الأردو ترجع إلى الإنجليزيّة ثمّ العربيّة، كسلسلة `t()` نفسِها
  if (current === 'ur' && row[`${base}_en`]) return row[`${base}_en`]
  return ar
}

/**
 * نصٌّ ثنائيُّ اللغة من ملفّات المحتوى (`{ ar, en }`).
 *
 * وهذه ثالثةُ ثلاث: `t()` تترجم نصًّا **في الشيفرة**، و`pick()` تختار
 * عمودًا **من صفّ**، وهذه تختار من **نصٍّ منقولٍ عن الأصل**. وفصلُها
 * مقصود: ما نُقل عن صاحب العمل لا يدخل قاموسَ الترجمة، فلا يُبدَّل
 * بترجمةٍ آليّةٍ يومًا.
 */
export function bt(v: { ar: string; en: string } | undefined): string {
  if (!v) return ''
  if (current === 'ar') return v.ar
  return v.en || v.ar
}
