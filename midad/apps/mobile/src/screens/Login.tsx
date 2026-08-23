import React, { useState } from 'react'
import { KeyboardAvoidingView, Platform, ScrollView, View, Linking } from 'react-native'
import { useApp } from '../lib/store'
import { Alert, Button, Input, Row, T } from '../ui/kit'
import { IcLogo, IcEye, IcEyeOff, IcExternal } from '../ui/icons'
import { SPACE, RADIUS } from '../lib/theme'
import { WEB_APP_URL } from '../lib/config'

export default function Login() {
  const { signIn, c } = useApp()
  const [phone, setPhone] = useState('')
  const [password, setPassword] = useState('')
  const [show, setShow] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const submit = async () => {
    setErr(null); setBusy(true)
    try { await signIn(phone, password) }
    catch (e: any) { setErr(e?.message || 'بيانات الدخول غير صحيحة') }
    finally { setBusy(false) }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ flexGrow: 1, justifyContent: 'center', padding: SPACE.s5, gap: SPACE.s5 }}
        keyboardShouldPersistTaps="handled">
        <View style={{ alignItems: 'center', gap: 12, marginBottom: SPACE.s4 }}>
          <View style={{
            width: 78, height: 78, borderRadius: 24, backgroundColor: c.accent,
            alignItems: 'center', justifyContent: 'center',
            shadowColor: c.accentDeep, shadowOpacity: 0.28, shadowRadius: 18,
            shadowOffset: { width: 0, height: 8 }, elevation: 6,
          }}>
            <IcLogo size={46} color={c.onAccent} />
          </View>
          <T size={26} weight="700" align="center">مِـداد</T>
          <T size={13.5} color={c.text2} align="center">
            ملفّاتك المدرسية وجداول نور — في جوّالك.
          </T>
        </View>

        {err ? <Alert tone="danger">{err}</Alert> : null}

        <Input
          label="الجوّال" value={phone} onChangeText={setPhone}
          placeholder="05xxxxxxxx" keyboardType="phone-pad" autoCapitalize="none"
          autoComplete="tel" textContentType="telephoneNumber"
          style={{ textAlign: 'left', writingDirection: 'ltr' }}
        />
        <Input
          label="كلمة المرور" value={password} onChangeText={setPassword}
          secureTextEntry={!show} autoCapitalize="none" autoComplete="password"
          textContentType="password" onSubmitEditing={submit} returnKeyType="go"
        />
        <Button label={show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'} variant="ghost" small
          icon={show ? <IcEyeOff size={15} color={c.accent} /> : <IcEye size={15} color={c.accent} />}
          onPress={() => setShow((v) => !v)} style={{ alignSelf: 'flex-start' }} />

        <Button label="دخول" variant="primary" onPress={submit} loading={busy} />

        <View style={{
          backgroundColor: c.sunken, borderRadius: RADIUS.md, padding: SPACE.s4, gap: 8,
        }}>
          <T size={13} weight="700">ليس لديك حساب؟</T>
          <T size={12.5} color={c.text2}>
            التسجيل والاشتراك يتمّان من موقع مِداد — سبعة أيّام تجربة بلا بطاقة، ثمّ تدخل هنا بجوّالك.
          </T>
          <Button label="افتح موقع مِداد" variant="soft" small
            icon={<IcExternal size={14} color={c.accentSoftFg} />}
            onPress={() => Linking.openURL(WEB_APP_URL + '/#/join')} />
        </View>

        <T size={11.5} color={c.text3} align="center">
          الجوّال هو اسم الدخول. ولاستعادة كلمة المرور تواصل مع إدارة حسابك.
        </T>
      </ScrollView>
    </KeyboardAvoidingView>
  )
}
