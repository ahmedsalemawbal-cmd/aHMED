import React, { useState } from 'react'
import { Linking, View } from 'react-native'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import {
  Alert, Avatar, Badge, Button, Card, Divider, Input, Progress, Row, Screen, T,
} from '../ui/kit'
import { IcCard, IcSettings, IcLock, IcLogout, IcExternal } from '../ui/icons'
import { SPACE, RADIUS } from '../lib/theme'
import { daysLabel, fmtBoth, fmtMoney } from '../lib/format'
import { WEB_APP_URL } from '../lib/config'

export default function Account() {
  const { c, profile, subscriber, plan, roles, access, trialDays, themeMode, setThemeMode, signOut } = useApp()
  const [pw, setPw] = useState({ next: '', confirm: '' })
  const [busy, setBusy] = useState(false)
  const [msg, setMsg] = useState<{ text: string; tone: 'success' | 'danger' } | null>(null)

  const roleName = roles.find((r) => r.key === profile?.role_key)?.name_ar || '—'

  const changePassword = async () => {
    if (pw.next.length < 8) { setMsg({ text: 'كلمة المرور 8 أحرف على الأقلّ', tone: 'danger' }); return }
    if (pw.next !== pw.confirm) { setMsg({ text: 'التأكيد لا يطابق كلمة المرور الجديدة', tone: 'danger' }); return }
    setBusy(true); setMsg(null)
    const { error } = await supabase.auth.updateUser({ password: pw.next })
    setBusy(false)
    if (error) { setMsg({ text: 'تعذّر التغيير — سجّل الدخول مرّة أخرى ثمّ حاول', tone: 'danger' }); return }
    setPw({ next: '', confirm: '' })
    setMsg({ text: 'غُيّرت كلمة المرور', tone: 'success' })
  }

  const stateLabel = access === 'trial' ? 'تجربة' : access === 'active' ? 'ساري'
    : access === 'expired' ? 'منتهٍ' : access === 'suspended' ? 'موقوف' : '—'
  const stateTone = access === 'active' ? 'success' : access === 'trial' ? 'info' : 'danger'

  return (
    <Screen>
      <Card style={{ gap: 14 }}>
        <Row gap={14}>
          <Avatar name={profile?.full_name || ''} size={62} />
          <View style={{ flex: 1, gap: 4 }}>
            <T size={18} weight="700">{profile?.full_name}</T>
            <T size={12.5} color={c.text2}>{roleName} · {subscriber?.name}</T>
            <Row gap={8}>
              <Badge label={stateLabel} tone={stateTone as any} />
              {profile?.is_owner && <Badge label="صاحب الحساب" tone="primary" />}
            </Row>
          </View>
        </Row>
        <Divider />
        <KV k="الجوّال" v={profile?.phone || '—'} />
        <KV k="البريد" v={profile?.email || '—'} />
        <T size={11.5} color={c.text3}>الجوّال هو اسم الدخول — ولتغييره تواصل معنا.</T>
      </Card>

      <Card style={{ gap: 12 }}>
        <Row gap={9}><IcCard size={18} color={c.text2} /><T size={16} weight="700">الاشتراك</T></Row>
        <KV k="الباقة" v={plan?.name_ar || '— لا باقة —'} />
        {plan ? <KV k="السعر السنويّ" v={fmtMoney(plan.price_sar)} /> : null}
        {access === 'trial' ? (
          <>
            <KV k="تنتهي التجربة" v={fmtBoth(subscriber?.trial_ends_at)} />
            <Progress value={trialDays} max={7} tone={trialDays <= 2 ? 'warn' : undefined} />
            <T size={12.5} color={c.text2}>بقي {daysLabel(trialDays)} — والملفّات تخرج بعلامة مائية حتى تشترك.</T>
          </>
        ) : null}
        {access === 'expired' && (
          <Alert tone="danger">انتهى اشتراكك. ملفّاتك محفوظة كلّها وتعود إليك فور التجديد.</Alert>
        )}
        <Button label="افتح الاشتراك والفواتير" variant="primary"
          icon={<IcExternal size={16} color={c.onPrimary} />}
          onPress={() => Linking.openURL(WEB_APP_URL + '/#/app/subscription')} />
        <T size={11.5} color={c.text3}>
          الدفع والفواتير من الموقع — يفتح في متصفّح جوّالك بحسابك نفسه.
        </T>
      </Card>

      <Card style={{ gap: 12 }}>
        <Row gap={9}><IcSettings size={18} color={c.text2} /><T size={16} weight="700">المظهر</T></Row>
        <Row gap={8}>
          {([
            { k: 'light' as const, l: 'فاتح' },
            { k: 'dark' as const, l: 'داكن' },
            { k: 'auto' as const, l: 'تلقائيّ' },
          ]).map((o) => {
            const on = themeMode === o.k
            return (
              <Button key={o.k} label={o.l} small variant={on ? 'primary' : 'secondary'}
                onPress={() => setThemeMode(o.k)} style={{ flex: 1 }} />
            )
          })}
        </Row>
      </Card>

      <Card style={{ gap: 12 }}>
        <Row gap={9}><IcLock size={18} color={c.text2} /><T size={16} weight="700">كلمة المرور</T></Row>
        {msg && <Alert tone={msg.tone === 'success' ? 'success' : 'danger'}>{msg.text}</Alert>}
        <Input label="كلمة المرور الجديدة" value={pw.next} secureTextEntry autoCapitalize="none"
          onChangeText={(v) => setPw((p) => ({ ...p, next: v }))} />
        <Input label="تأكيد كلمة المرور" value={pw.confirm} secureTextEntry autoCapitalize="none"
          onChangeText={(v) => setPw((p) => ({ ...p, confirm: v }))} />
        <Button label="غيّر كلمة المرور" variant="secondary" onPress={changePassword} loading={busy} />
      </Card>

      <Button label="خروج" variant="danger" icon={<IcLogout size={16} color={c.danger} />} onPress={signOut} />
      <T size={11} color={c.text3} align="center">مِداد · الإصدار 1.0.0</T>
    </Screen>
  )
}

function KV({ k, v }: { k: string; v: string }) {
  const { c } = useApp()
  return (
    <Row style={{ justifyContent: 'space-between' }} gap={12}>
      <T size={12.5} weight="600" color={c.text3}>{k}</T>
      <T size={13} weight="600" style={{ flex: 1 }} align="left" numberOfLines={1}>{v}</T>
    </Row>
  )
}
