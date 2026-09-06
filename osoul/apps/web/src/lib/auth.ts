import { supabase } from './supabase'
import type { Role } from './store'

/**
 * ══ الدخول ══
 *
 * البريدُ هو اسمُ الدخول هنا لا الجوّال — بخلاف مِداد. والسببُ أنّ
 * حسابات أصول البناء **موجودةٌ أصلًا ببريدٍ** في ووردبريس (الموظّفون
 * بصناديق `@osoulalbinaa.com`، والشركاءُ والفروعُ بحساباتٍ بريديّة).
 * فتبديلُ اسم الدخول يوم التحويل يعني أن يُعاد تعريفُ كلّ حسابٍ من
 * جديد.
 *
 *     ما يعرفه الناسُ لا يُبدَّل في يوم النقل.
 */
export async function signIn(email: string, password: string) {
  const { error } = await supabase.auth.signInWithPassword({
    email: email.trim().toLowerCase(), password,
  })
  if (error) throw new Error(mapAuthError(error.message))
}

export async function signUpCustomer(args: {
  email: string; password: string; fullName: string; phone: string; company?: string
}) {
  const { data, error } = await supabase.auth.signUp({
    email: args.email.trim().toLowerCase(),
    password: args.password,
    options: { data: { full_name: args.fullName.trim(), phone: args.phone.trim() } },
  })
  if (error) throw new Error(mapAuthError(error.message))

  // المُحفِّزُ `app.handle_new_user` أنشأ الملفَّ بدور 'customer'.
  // ولا يُرسَل الدورُ من هنا: **أخطرُ ما في نظام أدوارٍ أن يُمنح الدورُ
  // بلا قائل**، فالترقيةُ من المدير وحده ويحرسها مُحفِّز.
  if (data.user && args.company) {
    await supabase.from('profiles').update({ company: args.company.trim() }).eq('id', data.user.id)
  }
  return data
}

/** تسجيلُ شريكٍ — يُنشأ **غيرَ معتمَد**، فلا يكتب شيئًا حتّى يعتمده المدير. */
export async function signUpPartner(args: {
  email: string; password: string; company: string; phone: string
}) {
  const { data, error } = await supabase.auth.signUp({
    email: args.email.trim().toLowerCase(),
    password: args.password,
    options: { data: { full_name: args.company.trim(), phone: args.phone.trim() } },
  })
  if (error) throw new Error(mapAuthError(error.message))
  if (!data.user) return data

  // `approved` يبقى false — و`app.active()` تشترطه للشريك، فلا يُدرج
  // عرضًا ولا جهةَ اتّصالٍ قبل الاعتماد. والدورُ يرفعه المديرُ من لوحته.
  await supabase.from('profiles')
    .update({ role: 'partner', company: args.company.trim() })
    .eq('id', data.user.id)
  await supabase.from('partner_profiles')
    .insert({ user_id: data.user.id, company_ar: args.company.trim(), phone: args.phone.trim() })
  return data
}

export async function resetPassword(email: string) {
  const { error } = await supabase.auth.resetPasswordForEmail(email.trim().toLowerCase(), {
    redirectTo: `${location.origin}/set-password`,
  })
  if (error) throw new Error(mapAuthError(error.message))
}

export async function setPassword(password: string) {
  const { error } = await supabase.auth.updateUser({ password })
  if (error) throw new Error(mapAuthError(error.message))
}

/**
 * رسائلُ سوبابيز إنجليزيّةٌ وتقنيّة. وتُترجَم هنا إلى ما **يدلّ على
 * الإصلاح** لا إلى ما يصف العطب.
 */
function mapAuthError(msg: string): string {
  const m = msg.toLowerCase()
  if (m.includes('invalid login credentials')) return 'البريد أو كلمة المرور غير صحيحة.'
  if (m.includes('email not confirmed')) return 'لم يُفعَّل بريدك بعد — افتح رسالة التفعيل.'
  if (m.includes('user already registered') || m.includes('already been registered'))
    return 'هذا البريد مسجّل مسبقًا — سجّل الدخول بدلًا من إنشاء حساب.'
  if (m.includes('password should be at least')) return 'كلمة المرور قصيرة — ستة أحرف على الأقل.'
  if (m.includes('rate limit') || m.includes('too many'))
    return 'حاولت مرّات كثيرة — انتظر دقيقة ثمّ أعد المحاولة.'
  if (m.includes('failed to fetch') || m.includes('network'))
    return 'تعذّر الاتّصال — تحقّق من الشبكة.'
  return msg
}

/** الوجهةُ بعد الدخول — لكلّ دورٍ بيتُه. */
export function homeFor(role: Role): string {
  if (role === 'employee') return '/mail'
  if (role === 'customer') return '/my-quotes'
  if (role) return '/dashboard'
  return '/'
}

export const ROLE_LABELS: Record<string, string> = {
  admin: 'المدير', branch: 'فرع', rep: 'مندوب',
  partner: 'شريك', customer: 'عميل', employee: 'موظّف',
}
