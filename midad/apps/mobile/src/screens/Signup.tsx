import React, { useState } from 'react'
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, View } from 'react-native'
import { useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { callFunction } from '../lib/supabase'
import { Alert, Button, Input, Row, T } from '../ui/kit'
import { SPACE, RADIUS, TYPE } from '../lib/theme'
import { IcLogo, IcBack, IcCheck } from '../ui/icons'
import { normalizePhone } from '../lib/config'

/**
 * فتحُ حسابٍ من الجوّال — للمعلّم الفرد.
 *
 * المدرسةُ اشتراكٌ بعشرة مقاعدَ وفاتورةٍ وقرارٍ إداريّ، ويُتَّخذ على حاسبٍ
 * لا على جوّالٍ في الطابور. أمّا المعلّمُ فيسمع بمِداد من زميلٍ في
 * الاستراحة، فإن لم يُفتح الحسابُ في تلك الدقيقة لم يُفتح.
 *
 *     ما لا يُفتح في دقيقةٍ يُؤجَّل إلى لا شيء.
 *
 * ولذلك أربعةُ حقولٍ لا أكثر، والتحقّقُ يقع **قبل** النداء: رسالةٌ فوريّةٌ
 * تحت الحقل خيرٌ من رحلةٍ إلى الخادم تعود برفض.
 */
export default function Signup() {
  const nav = useNavigation<any>()
  const { c, signIn } = useApp()
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)

  /* والتحقّقُ نفسُه في الخادم أيضًا — هذا للسرعة لا للأمان. */
  const bad = (() => {
    if (name.trim().length && name.trim().length < 3) return 'اكتب اسمك كاملًا'
    const p = normalizePhone(phone)
    if (phone.length && !/^05\d{8}$/.test(p)) return 'رقمٌ من عشرة أرقام يبدأ بـ ٠٥'
    if (password.length && password.length < 8) return 'كلمة المرور ٨ أحرفٍ على الأقلّ'
    return null
  })()

  const ready = name.trim().length >= 3
    && /^05\d{8}$/.test(normalizePhone(phone))
    && password.length >= 8

  const submit = async () => {
    if (!ready) return
    setBusy(true); setErr(null)
    try {
      await callFunction('auth-signup', {
        account_type: 'teacher',
        full_name: name.trim(),
        phone: normalizePhone(phone),
        password,
        subscriber_name: name.trim(),
        role_key: 'teacher',
      })
      /* والدخولُ يقع هنا لا في شاشةٍ تالية: من سجّل للتوّ لا يُطلب منه
         أن يكتب ما كتبه قبل ثانية. */
      await signIn(normalizePhone(phone), password)
    } catch (e: any) {
      setErr(e?.message || 'تعذّر إنشاء الحساب')
      setBusy(false)
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ padding: SPACE.s5, paddingTop: SPACE.s8, gap: SPACE.s4 }}
        keyboardShouldPersistTaps="handled">

        <Pressable onPress={() => nav.goBack()} hitSlop={12} style={{ alignSelf: 'flex-start' }}>
          <IcBack size={22} color={c.text2} />
        </Pressable>

        <View style={{ alignItems: 'center', gap: 8, paddingVertical: SPACE.s4 }}>
          <IcLogo size={48} color={c.primary} />
          <T size={TYPE.h1} weight="700">جرّب مِداد سبعة أيّام</T>
          <T size={TYPE.body} color={c.text3} align="center">
            بلا بطاقةٍ ولا التزام. حسابُ معلّمٍ فرديّ — والمدارسُ تشترك من الموقع.
          </T>
        </View>

        {err ? <Alert tone="danger">{err}</Alert> : null}

        <Input label="الاسم كاملًا" value={name} onChangeText={setName}
          placeholder="أحمد سالم" autoComplete="name" />

        <Input label="الجوّال" value={phone} onChangeText={setPhone}
          placeholder="05xxxxxxxx" keyboardType="phone-pad"
          autoCapitalize="none" autoComplete="tel" />

        <Input label="كلمة المرور" value={password} onChangeText={setPassword}
          placeholder="٨ أحرفٍ على الأقلّ" secureTextEntry autoComplete="password-new"
          help={bad || undefined} error={bad || undefined} />

        <Button label="أنشئ حسابي" variant="primary" onPress={submit}
          loading={busy} disabled={!ready} />

        <View style={{
          backgroundColor: c.card, borderRadius: RADIUS.lg, padding: SPACE.s4, gap: 8,
        }}>
          {['كلّ القوالب مفتوحة', 'رصدُ الحضور والجدول', 'ملفُّ الإنجاز بالذكاء'].map((line) => (
            <Row key={line} gap={8}>
              <IcCheck size={15} color={c.success} />
              <T size={TYPE.small} color={c.text2}>{line}</T>
            </Row>
          ))}
        </View>

        <Pressable onPress={() => nav.goBack()} style={{ paddingVertical: SPACE.s3 }}>
          <T size={TYPE.body} color={c.primary} align="center">لديّ حسابٌ — ادخل</T>
        </Pressable>
      </ScrollView>
    </KeyboardAvoidingView>
  )
}
