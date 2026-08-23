import React, { useState } from 'react'
import { ActivityIndicator, Linking, Modal, Pressable, View } from 'react-native'
import { useSafeAreaInsets } from 'react-native-safe-area-context'
import { useApp } from '../lib/store'
import { supabase } from '../lib/supabase'
import { pickAndUploadAvatar, removeAvatar } from '../lib/avatar'
import { AppHeader } from '../ui/AppHeader'
import {
  Alert, Avatar, Badge, Button, Card, Divider, Input, Progress, Row, Screen, T,
} from '../ui/kit'
import {
  IcCard, IcSettings, IcLock, IcLogout, IcExternal, IcCamera, IcImage, IcTrash,
  IcSun, IcMoon,
} from '../ui/icons'
import { SPACE, RADIUS, TYPE, elevation } from '../lib/theme'
import { daysLabel, fmtBoth, fmtMoney } from '../lib/format'
import { WEB_APP_URL } from '../lib/config'

export default function Account() {
  const { c, profile, subscriber, plan, roles, access, trialDays, themeMode, setThemeMode, signOut, refresh } = useApp()
  const [pw, setPw] = useState({ next: '', confirm: '' })
  const [busy, setBusy] = useState(false)
  const [msg, setMsg] = useState<{ text: string; tone: 'success' | 'danger' } | null>(null)
  const [photoSheet, setPhotoSheet] = useState(false)
  const [uploading, setUploading] = useState(false)

  const changePhoto = async (mode: 'camera' | 'library' | 'remove') => {
    setPhotoSheet(false)
    if (!profile?.id) return
    setUploading(true); setMsg(null)
    try {
      if (mode === 'remove') { await removeAvatar(profile.id); setMsg({ text: 'حُذفت صورتك', tone: 'success' }) }
      else {
        const url = await pickAndUploadAvatar(mode, profile.id)
        if (url) setMsg({ text: 'حُدّثت صورتك', tone: 'success' })
      }
      await refresh()
    } catch (e: any) {
      setMsg({ text: e?.message || 'تعذّر تحديث الصورة', tone: 'danger' })
    } finally { setUploading(false) }
  }

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
    <Screen header={<AppHeader title="حسابي" />}>
      <Card style={{ gap: 14 }}>
        <Row gap={14}>
          <Pressable onPress={() => setPhotoSheet(true)} disabled={uploading}>
            <Avatar name={profile?.full_name || ''} size={64} uri={profile?.avatar_url} ring />
            <View style={{
              position: 'absolute', bottom: -2, left: -2,
              width: 24, height: 24, borderRadius: 12, backgroundColor: c.primary,
              alignItems: 'center', justifyContent: 'center',
              borderWidth: 2, borderColor: c.card,
            }}>
              {uploading
                ? <ActivityIndicator size="small" color={c.onPrimary} />
                : <IcCamera size={13} color={c.onPrimary} />}
            </View>
          </Pressable>
          <View style={{ flex: 1, gap: 4 }}>
            <T size={TYPE.h2} weight="700">{profile?.full_name}</T>
            <T size={TYPE.body} color={c.text2}>{roleName} · {subscriber?.name}</T>
            <Row gap={8}>
              <Badge label={stateLabel} tone={stateTone as any} />
              {profile?.is_owner && <Badge label="صاحب الحساب" tone="primary" />}
            </Row>
          </View>
        </Row>
        <Divider />
        <KV k="الجوّال" v={profile?.phone || '—'} />
        <KV k="البريد" v={profile?.email || '—'} />
        <T size={TYPE.small} color={c.text3}>الجوّال هو اسم الدخول — ولتغييره تواصل معنا.</T>
      </Card>

      <Card style={{ gap: 12 }}>
        <Row gap={9}><IcCard size={18} color={c.text2} /><T size={TYPE.h3} weight="700">الاشتراك</T></Row>
        <KV k="الباقة" v={plan?.name_ar || '— لا باقة —'} />
        {plan ? <KV k="السعر السنويّ" v={fmtMoney(plan.price_sar)} /> : null}
        {access === 'trial' ? (
          <>
            <KV k="تنتهي التجربة" v={fmtBoth(subscriber?.trial_ends_at)} />
            <Progress value={trialDays} max={7} tone={trialDays <= 2 ? 'warn' : undefined} />
            <T size={TYPE.body} color={c.text2}>بقي {daysLabel(trialDays)} — والملفّات تخرج بعلامة مائية حتى تشترك.</T>
          </>
        ) : null}
        {access === 'expired' && (
          <Alert tone="danger">انتهى اشتراكك. ملفّاتك محفوظة كلّها وتعود إليك فور التجديد.</Alert>
        )}
        <Button label="افتح الاشتراك والفواتير" variant="primary"
          icon={<IcExternal size={16} color={c.onPrimary} />}
          onPress={() => Linking.openURL(WEB_APP_URL + '/#/app/subscription')} />
        <T size={TYPE.small} color={c.text3}>
          الدفع والفواتير من الموقع — يفتح في متصفّح جوّالك بحسابك نفسه.
        </T>
      </Card>

      <Card style={{ gap: 12 }}>
        <Row gap={9}><IcSettings size={18} color={c.text2} /><T size={TYPE.h3} weight="700">المظهر</T></Row>
        <Row gap={8}>
          {([
            { k: 'light' as const, l: 'فاتح', icon: <IcSun size={15} /> },
            { k: 'dark' as const, l: 'داكن', icon: <IcMoon size={15} /> },
            { k: 'auto' as const, l: 'تلقائيّ', icon: <IcSettings size={15} /> },
          ]).map((o) => {
            const on = themeMode === o.k
            return (
              <Pressable
                key={o.k} onPress={() => setThemeMode(o.k)}
                style={({ pressed }) => ({
                  flex: 1, backgroundColor: on ? c.primarySoft : c.sunken,
                  borderRadius: RADIUS.sm, paddingVertical: 11, gap: 5,
                  alignItems: 'center', opacity: pressed ? 0.75 : 1,
                })}>
                {React.cloneElement(o.icon, { color: on ? c.primarySoftFg : c.text3 })}
                <T size={TYPE.small} weight={on ? '700' : '500'} color={on ? c.primarySoftFg : c.text3}>
                  {o.l}
                </T>
              </Pressable>
            )
          })}
        </Row>
      </Card>

      <Card style={{ gap: 12 }}>
        <Row gap={9}><IcLock size={18} color={c.text2} /><T size={TYPE.h3} weight="700">كلمة المرور</T></Row>
        {msg && <Alert tone={msg.tone === 'success' ? 'success' : 'danger'}>{msg.text}</Alert>}
        <Input label="كلمة المرور الجديدة" value={pw.next} secureTextEntry autoCapitalize="none"
          onChangeText={(v) => setPw((p) => ({ ...p, next: v }))} />
        <Input label="تأكيد كلمة المرور" value={pw.confirm} secureTextEntry autoCapitalize="none"
          onChangeText={(v) => setPw((p) => ({ ...p, confirm: v }))} />
        <Button label="غيّر كلمة المرور" variant="secondary" onPress={changePassword} loading={busy} />
      </Card>

      <Button label="خروج" variant="danger" icon={<IcLogout size={16} color={c.danger} />} onPress={signOut} />
      <T size={TYPE.small} color={c.text3} align="center">مِداد · الإصدار 1.0.0</T>

      <PhotoSheet
        open={photoSheet} onClose={() => setPhotoSheet(false)}
        onPick={changePhoto} hasPhoto={!!profile?.avatar_url}
      />
    </Screen>
  )
}

function PhotoSheet({ open, onClose, onPick, hasPhoto }: {
  open: boolean; onClose: () => void
  onPick: (m: 'camera' | 'library' | 'remove') => void; hasPhoto: boolean
}) {
  const { c } = useApp()
  const insets = useSafeAreaInsets()
  const opts: { m: 'camera' | 'library' | 'remove'; label: string; icon: React.ReactNode; danger?: boolean }[] = [
    { m: 'camera', label: 'التقط صورة', icon: <IcCamera size={20} color={c.primarySoftFg} /> },
    { m: 'library', label: 'اختر من الصور', icon: <IcImage size={20} color={c.primarySoftFg} /> },
    ...(hasPhoto ? [{ m: 'remove' as const, label: 'احذف الصورة', icon: <IcTrash size={20} color={c.danger} />, danger: true }] : []),
  ]
  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <Pressable style={{ flex: 1, backgroundColor: '#0006' }} onPress={onClose} />
      <View style={{
        position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: c.card,
        borderTopLeftRadius: RADIUS.xxl, borderTopRightRadius: RADIUS.xxl,
        paddingTop: SPACE.s4, paddingBottom: insets.bottom + SPACE.s5,
        paddingHorizontal: SPACE.s5, gap: 9,
      }}>
        <View style={{ width: 40, height: 4, borderRadius: 2, backgroundColor: c.border, alignSelf: 'center' }} />
        <T size={TYPE.h3} weight="700" style={{ paddingVertical: SPACE.s3 }}>صورتك الشخصيّة</T>
        {opts.map((o) => (
          <Pressable
            key={o.m} onPress={() => onPick(o.m)}
            style={({ pressed }) => ({
              backgroundColor: pressed ? c.sunken : c.cardAlt, borderRadius: RADIUS.md,
              padding: 13, flexDirection: 'row-reverse', alignItems: 'center', gap: 12,
            })}>
            <View style={{
              width: 40, height: 40, borderRadius: RADIUS.sm,
              backgroundColor: o.danger ? c.dangerSoft : c.primarySoft,
              alignItems: 'center', justifyContent: 'center',
            }}>{o.icon}</View>
            <T size={TYPE.base} weight="600" color={o.danger ? c.danger : c.text} style={{ flex: 1 }}>
              {o.label}
            </T>
          </Pressable>
        ))}
      </View>
    </Modal>
  )
}

function KV({ k, v }: { k: string; v: string }) {
  const { c } = useApp()
  return (
    <Row style={{ justifyContent: 'space-between' }} gap={12}>
      <T size={TYPE.body} weight="600" color={c.text3}>{k}</T>
      <T size={TYPE.bodyLg} weight="600" style={{ flex: 1 }} align="left" numberOfLines={1}>{v}</T>
    </Row>
  )
}
