import React, { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { supabase } from '../../lib/supabase'
import { phoneToAuthEmail } from '../../lib/config'
import { fmtBoth } from '../../lib/format'
import type { ThemeMode } from '../../lib/theme'
import {
  Alert, Avatar, Badge, Button, Card, ConfirmModal, Field, Input,
  PageHead, Progress, Select, Switch,
} from '../../ui/kit'
import { IcLogout, IcMoon, IcShield, IcSun, IcUser } from '../../ui/icons'

function strengthOf(p: string): { score: number; label: string; tone?: 'warn' | 'danger' } {
  if (!p) return { score: 0, label: 'اكتب كلمة المرور الجديدة', tone: 'danger' }
  let s = 0
  if (p.length >= 8) s += 34
  if (p.length >= 12) s += 16
  if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s += 20
  if (/\d/.test(p)) s += 15
  if (/[^\w\s]/.test(p)) s += 15
  const score = Math.min(100, s)
  if (score < 45) return { score, label: 'ضعيفة — أضف أحرفًا وأرقامًا', tone: 'danger' }
  if (score < 75) return { score, label: 'متوسّطة — تصلح', tone: 'warn' }
  return { score, label: 'قويّة' }
}

export default function Account() {
  const { profile, subscriber, roles, theme, setTheme, refresh, toast } = useApp()
  const nav = useNavigate()

  const [name, setName] = useState(profile?.full_name || '')
  const [email, setEmail] = useState(profile?.email || '')
  const [savingInfo, setSavingInfo] = useState(false)

  const [curPass, setCurPass] = useState('')
  const [newPass, setNewPass] = useState('')
  const [confirmPass, setConfirmPass] = useState('')
  const [passErr, setPassErr] = useState<string | null>(null)
  const [savingPass, setSavingPass] = useState(false)

  const [notif, setNotif] = useState(!!profile?.email_notifications)
  const [askSignOutAll, setAskSignOutAll] = useState(false)
  const [busy, setBusy] = useState(false)

  const strength = useMemo(() => strengthOf(newPass), [newPass])
  const roleName = roles.find((r) => r.key === profile?.role_key)?.name_ar || profile?.role_key || ''
  const accountLabel = subscriber?.account_type === 'school' ? 'اشتراك مدرسة' : 'اشتراك معلّم'
  const infoDirty = name.trim() !== (profile?.full_name || '') || (email.trim() || '') !== (profile?.email || '')

  async function saveInfo() {
    if (!profile) return
    if (name.trim().length < 3) { toast('اكتب اسمك كاملًا', 'danger'); return }
    setSavingInfo(true)
    const { error } = await supabase.from('profiles')
      .update({ full_name: name.trim(), email: email.trim() || null }).eq('id', profile.id)
    setSavingInfo(false)
    if (error) { toast('تعذّر حفظ بياناتك', 'danger'); return }
    await refresh()
    toast('حُفظت بياناتك')
  }

  async function changePassword() {
    setPassErr(null)
    if (!profile) return
    if (newPass.length < 8) { setPassErr('كلمة المرور الجديدة ثمانية أحرف على الأقلّ'); return }
    if (newPass !== confirmPass) { setPassErr('التأكيد لا يطابق كلمة المرور الجديدة'); return }
    setSavingPass(true)
    try {
      const { error: signErr } = await supabase.auth.signInWithPassword({
        email: phoneToAuthEmail(profile.phone), password: curPass,
      })
      if (signErr) { setPassErr('كلمة المرور الحالية غير صحيحة'); return }
      const { error: upErr } = await supabase.auth.updateUser({ password: newPass })
      if (upErr) { setPassErr('تعذّر تغيير كلمة المرور — حاول بعد قليل'); return }
      setCurPass(''); setNewPass(''); setConfirmPass('')
      toast('غُيّرت كلمة المرور')
    } finally { setSavingPass(false) }
  }

  async function toggleNotif(v: boolean) {
    if (!profile) return
    setNotif(v)
    const { error } = await supabase.from('profiles').update({ email_notifications: v }).eq('id', profile.id)
    if (error) { setNotif(!v); toast('تعذّر حفظ التفضيل', 'danger'); return }
    toast(v ? 'ستصلك إشعارات البريد' : 'أُوقفت إشعارات البريد')
  }

  async function signOutEverywhere() {
    setBusy(true)
    try {
      await supabase.auth.signOut({ scope: 'global' })
      nav('/login')
    } catch {
      toast('تعذّر الخروج من الأجهزة', 'danger')
    } finally { setBusy(false); setAskSignOutAll(false) }
  }

  return (
    <>
      <PageHead title="حسابي" sub="بياناتك وكلمة مرورك وتفضيلاتك" />

      <Card className="mdd-row mdd-row--wrap" style={{ gap: 18, marginBlockEnd: 'var(--mdd-s-5)' }}>
        <Avatar name={profile?.full_name || ''} size="lg" />
        <div style={{ minWidth: 0, flex: 1 }}>
          <h2 style={{ fontSize: 20 }}>{profile?.full_name}</h2>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 8, marginBlockStart: 8 }}>
            <Badge tone="accent">{roleName}</Badge>
            <Badge tone="info">{accountLabel}</Badge>
            {profile?.is_owner && <Badge tone="success">صاحب الاشتراك</Badge>}
          </div>
          <p style={{ fontSize: 12.5, color: 'var(--mdd-text-3)', marginBlockStart: 8 }}>
            انضممتَ في {fmtBoth(profile?.created_at)}
          </p>
        </div>
      </Card>

      <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
        {/* البيانات الشخصية */}
        <Card className="mdd-col" style={{ gap: 14 }}>
          <div className="mdd-row" style={{ gap: 10 }}>
            <span style={{ color: 'var(--mdd-accent)' }}><IcUser size={18} /></span>
            <h2 className="mdd-card__title">بيانات شخصية</h2>
          </div>
          <Field label="الاسم الكامل">
            <Input value={name} onChange={(e) => setName(e.target.value)} />
          </Field>
          <Field label="الجوّال" help="هو اسم دخولك ولا يتغيّر من هنا — للتغيير تواصل معنا.">
            <Input value={profile?.phone || ''} disabled ltr readOnly />
          </Field>
          <Field label="البريد (اختياريّ)" help="نستعمله للإشعارات وحدها، ولا يُستعمل للدخول.">
            <Input value={email} onChange={(e) => setEmail(e.target.value)} ltr type="email" />
          </Field>
          <Button variant="primary" onClick={saveInfo} loading={savingInfo} disabled={!infoDirty}>
            {savingInfo ? 'جارٍ الحفظ…' : 'احفظ البيانات'}
          </Button>
        </Card>

        {/* كلمة المرور */}
        <Card className="mdd-col" style={{ gap: 14 }}>
          <div className="mdd-row" style={{ gap: 10 }}>
            <span style={{ color: 'var(--mdd-accent)' }}><IcShield size={18} /></span>
            <h2 className="mdd-card__title">تغيير كلمة المرور</h2>
          </div>
          {passErr && <Alert tone="danger">{passErr}</Alert>}
          <Field label="كلمة المرور الحالية">
            <Input value={curPass} onChange={(e) => setCurPass(e.target.value)} type="password" ltr autoComplete="current-password" />
          </Field>
          <Field label="كلمة المرور الجديدة">
            <Input value={newPass} onChange={(e) => setNewPass(e.target.value)} type="password" ltr autoComplete="new-password"
              error={!!newPass && newPass.length < 8} />
          </Field>
          <div className="mdd-col" style={{ gap: 6 }}>
            <Progress value={strength.score} tone={strength.tone} />
            <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{strength.label}</span>
          </div>
          <Field label="تأكيد كلمة المرور" error={confirmPass && confirmPass !== newPass ? 'التأكيد لا يطابق' : undefined}>
            <Input value={confirmPass} onChange={(e) => setConfirmPass(e.target.value)} type="password" ltr
              error={!!confirmPass && confirmPass !== newPass} autoComplete="new-password" />
          </Field>
          <Button variant="primary" onClick={changePassword} loading={savingPass}
            disabled={!curPass || !newPass || !confirmPass}>
            {savingPass ? 'جارٍ الحفظ…' : 'غيّر كلمة المرور'}
          </Button>
        </Card>

        {/* التفضيلات */}
        <Card className="mdd-col" style={{ gap: 14 }}>
          <h2 className="mdd-card__title">التفضيلات</h2>
          <Field label="وضع العرض" help="التلقائيّ يتبع إعداد جهازك.">
            <Select value={theme} onChange={(e) => setTheme(e.target.value as ThemeMode)}>
              <option value="light">فاتح</option>
              <option value="dark">داكن</option>
              <option value="auto">تلقائيّ</option>
            </Select>
          </Field>
          <div className="mdd-row" style={{ gap: 10, color: 'var(--mdd-text-3)' }}>
            <IcSun size={16} /><IcMoon size={16} />
            <span style={{ fontSize: 12 }}>يُحفظ الوضع في حسابك، فيتبعك على أجهزتك.</span>
          </div>
          <div style={{ borderBlockStart: '1px solid var(--mdd-border)', paddingBlockStart: 14 }}>
            <Switch checked={notif} onChange={toggleNotif} label="إشعارات البريد — الفواتير وقرب انتهاء الاشتراك" />
          </div>
        </Card>

        {/* منطقة خطرة */}
        <Card className="mdd-col" style={{ gap: 14, borderColor: 'var(--mdd-danger-fg)' }}>
          <div className="mdd-row" style={{ gap: 10, color: 'var(--mdd-danger-fg)' }}>
            <IcLogout size={18} />
            <h2 className="mdd-card__title">منطقة خطرة</h2>
          </div>
          <p className="mdd-prose" style={{ fontSize: 13 }}>
            إن ظننت أنّ أحدًا يستعمل حسابك، أخرج من كلّ الأجهزة ثمّ غيّر كلمة المرور.
            ملفّاتك وجداولك لا تتأثّر.
          </p>
          <Button variant="danger" onClick={() => setAskSignOutAll(true)}>خروج من كلّ الأجهزة</Button>
        </Card>
      </div>

      <ConfirmModal
        open={askSignOutAll} onClose={() => setAskSignOutAll(false)} onConfirm={signOutEverywhere}
        loading={busy} danger title="خروج من كلّ الأجهزة" confirmLabel="أخرجني من الكلّ"
        body="ستُغلق جلساتك على كلّ الأجهزة — بما فيها هذا الجهاز — وتعود إلى شاشة الدخول. لا شيء يُحذف."
      />
    </>
  )
}
