import React, { useMemo, useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import AuthShell from './AuthShell'
import { Alert, Badge, Button, Checkbox, Field, Input, Select } from '../../ui/kit'
import { IcEye, IcEyeOff } from '../../ui/icons'
import { useApp } from '../../lib/store'
import { callFunction } from '../../lib/supabase'
import { isValidPhone, normalizePhone } from '../../lib/config'

const CITIES = ['الرياض', 'جدة', 'مكة المكرمة', 'المدينة المنورة', 'الدمام', 'الخبر', 'أبها', 'بريدة', 'تبوك', 'حائل', 'جازان', 'نجران', 'الطائف', 'الأحساء', 'أخرى']

function strength(pw: string): { score: number; label: string; tone: 'danger' | 'warn' | 'success' } {
  let s = 0
  if (pw.length >= 8) s++
  if (pw.length >= 12) s++
  if (/[A-Za-z]/.test(pw) && /\d/.test(pw)) s++
  if (/[^A-Za-z0-9]/.test(pw)) s++
  if (s <= 1) return { score: 1, label: 'ضعيفة', tone: 'danger' }
  if (s === 2) return { score: 2, label: 'متوسّطة', tone: 'warn' }
  return { score: 3, label: 'قويّة', tone: 'success' }
}

export default function Signup({ type }: { type: 'school' | 'teacher' }) {
  const { roles, plans, session, refresh, toast } = useApp()
  const nav = useNavigate()

  const [f, setF] = useState({
    schoolName: '', schoolType: 'حكومية', educationDept: '',
    fullName: '', phone: '', email: '', city: 'الرياض',
    roleKey: type === 'school' ? 'principal' : 'teacher',
    password: '',
  })
  const [agree, setAgree] = useState(false)
  const [show, setShow] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<React.ReactNode>(null)
  const [busy, setBusy] = useState(false)

  const set = (k: keyof typeof f) => (e: any) => setF((s) => ({ ...s, [k]: e.target.value }))
  const pw = useMemo(() => strength(f.password), [f.password])
  const roleBlurb = roles.find((r) => r.key === f.roleKey)?.blurb_ar

  if (session) return <Navigate to="/app" replace />

  const validate = () => {
    const e: Record<string, string> = {}
    if (type === 'school' && f.schoolName.trim().length < 3) e.schoolName = 'اكتب اسم المدرسة كاملًا'
    if (f.fullName.trim().split(/\s+/).length < 2) e.fullName = 'اكتب الاسم الأول واسم العائلة'
    if (!isValidPhone(f.phone)) e.phone = 'أدخل رقم جوّال صحيحًا من 10 أرقام يبدأ بـ 05'
    if (f.email && !/^\S+@\S+\.\S+$/.test(f.email)) e.email = 'أدخل بريدًا صحيحًا'
    if (f.password.length < 8) e.password = 'كلمة المرور 8 أحرف على الأقلّ'
    if (!agree) e.agree = 'وافق على الشروط للمتابعة'
    setErrors(e)
    return Object.keys(e).length === 0
  }

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault()
    setFormError(null)
    if (!validate()) return
    setBusy(true)
    try {
      await callFunction('auth-signup', {
        account_type: type,
        subscriber_name: type === 'school' ? f.schoolName.trim() : f.fullName.trim(),
        school_type: type === 'school' ? f.schoolType : null,
        education_dept: f.educationDept.trim() || null,
        city: f.city,
        full_name: f.fullName.trim(),
        phone: normalizePhone(f.phone),
        email: f.email.trim() || null,
        role_key: f.roleKey,
        password: f.password,
      })
      const { supabase } = await import('../../lib/supabase')
      const { phoneToAuthEmail } = await import('../../lib/config')
      const { error } = await supabase.auth.signInWithPassword({
        email: phoneToAuthEmail(f.phone), password: f.password,
      })
      if (error) throw new Error('أُنشئ حسابك — سجّل الدخول بجوّالك وكلمة المرور.')
      await refresh()
      nav('/welcome', { replace: true })
    } catch (err: any) {
      const msg = String(err?.message || '')
      if (/مسجّل|registered|exists/.test(msg)) {
        setFormError(<>هذا الجوّال مسجّل مسبقًا. <Link to="/login" style={{ textDecoration: 'underline', fontWeight: 700 }}>سجّل الدخول</Link></>)
      } else setFormError(msg || 'تعذّر إنشاء الحساب — حاول مرّة أخرى.')
    } finally { setBusy(false) }
  }

  const planForType = plans.find((p) => p.account_type === type && p.is_default)

  return (
    <AuthShell>
      <form className="mdd-col" style={{ gap: 18 }} onSubmit={submit} noValidate>
        <div className="mdd-row mdd-row--between">
          <Badge tone="accent">{type === 'school' ? 'تسجيل مدرسة' : 'تسجيل معلّم'}</Badge>
          <Link to="/join" style={{ fontSize: 12.5, color: 'var(--mdd-accent)', fontWeight: 700 }}>تغيير</Link>
        </div>

        <div>
          <h1 style={{ fontSize: 23 }}>ابدأ تجربتك المجانية</h1>
          <p className="mdd-sub" style={{ fontSize: 13, marginBlockStart: 6 }}>
            سبعة أيّام · بلا بطاقة · يمكنك الإلغاء في أيّ وقت.
          </p>
        </div>

        {formError && <Alert tone="danger">{formError}</Alert>}

        {type === 'school' && (
          <>
            <Field label="اسم المدرسة" error={errors.schoolName}>
              <Input value={f.schoolName} onChange={set('schoolName')} error={!!errors.schoolName}
                placeholder="ابتدائية الأمل" autoComplete="organization" />
            </Field>
            <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
              <Field label="نوع المدرسة">
                <Select value={f.schoolType} onChange={set('schoolType')}>
                  <option>حكومية</option><option>أهلية</option><option>عالمية</option>
                </Select>
              </Field>
              <Field label="إدارة التعليم" help="تظهر في ترويسة ملفّاتك">
                <Input value={f.educationDept} onChange={set('educationDept')} placeholder="إدارة تعليم الرياض" />
              </Field>
            </div>
          </>
        )}

        <Field label="اسمك كاملًا" error={errors.fullName}>
          <Input value={f.fullName} onChange={set('fullName')} error={!!errors.fullName}
            placeholder="أحمد سالم الغامدي" autoComplete="name" />
        </Field>

        <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
          <Field label="الجوّال" error={errors.phone} help="الجوّال هو اسم الدخول">
            <Input value={f.phone} onChange={set('phone')} error={!!errors.phone} ltr
              placeholder="05xxxxxxxx" inputMode="numeric" autoComplete="tel" />
          </Field>
          <Field label="المدينة">
            <Select value={f.city} onChange={set('city')}>
              {CITIES.map((c) => <option key={c}>{c}</option>)}
            </Select>
          </Field>
        </div>

        <Field label="البريد الإلكتروني (اختياريّ)" error={errors.email} help="لإشعارات الفواتير والتجديد">
          <Input value={f.email} onChange={set('email')} error={!!errors.email} ltr
            type="email" placeholder="name@example.com" autoComplete="email" />
        </Field>

        <Field label={type === 'school' ? 'دورك في المدرسة' : 'دورك'} help={roleBlurb ? `ستجد: ${roleBlurb} — والملفّات العامّة.` : undefined}>
          <Select value={f.roleKey} onChange={set('roleKey')}>
            {roles.filter((r) => r.key !== 'general').map((r) => <option key={r.key} value={r.key}>{r.name_ar}</option>)}
          </Select>
        </Field>

        <Field label="كلمة المرور" error={errors.password}>
          <span style={{ position: 'relative', display: 'block' }}>
            <Input value={f.password} onChange={set('password')} error={!!errors.password}
              type={show ? 'text' : 'password'} autoComplete="new-password" style={{ paddingInlineEnd: 44 }} />
            <button type="button" onClick={() => setShow((v) => !v)} aria-label={show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'}
              style={{
                position: 'absolute', insetInlineEnd: 8, insetBlockStart: '50%', transform: 'translateY(-50%)',
                background: 'none', border: 'none', cursor: 'pointer', color: 'var(--mdd-text-3)', padding: 6,
              }}>
              {show ? <IcEyeOff size={17} /> : <IcEye size={17} />}
            </button>
          </span>
        </Field>
        {f.password && (
          <div className="mdd-row" style={{ gap: 10, marginBlockStart: -8 }}>
            <div className="mdd-progress" style={{ flex: 1 }}>
              <div className={'mdd-progress__bar' + (pw.tone !== 'success' ? ` mdd-progress__bar--${pw.tone}` : '')}
                style={{ width: `${(pw.score / 3) * 100}%` }} />
            </div>
            <span style={{ fontSize: 11.5, fontWeight: 600, color: `var(--mdd-${pw.tone}-fg)` }}>{pw.label}</span>
          </div>
        )}

        <div className="mdd-col" style={{ gap: 4 }}>
          <Checkbox checked={agree} onChange={setAgree}>
            أوافق على <Link to="/terms" style={{ color: 'var(--mdd-accent)' }}>الشروط والأحكام</Link> و
            <Link to="/privacy" style={{ color: 'var(--mdd-accent)' }}> سياسة الخصوصية</Link>
          </Checkbox>
          {errors.agree && <span className="mdd-field__error">{errors.agree}</span>}
        </div>

        <Button type="submit" variant="primary" size="lg" block loading={busy}>ابدأ التجربة المجانية</Button>

        <p style={{ textAlign: 'center', fontSize: 12.5, color: 'var(--mdd-text-3)' }}>
          سبعة أيّام · بلا بطاقة · يمكنك الإلغاء
          {planForType ? ` · بعدها ${planForType.name_ar}` : ''}
        </p>
        <p style={{ textAlign: 'center', fontSize: 13, color: 'var(--mdd-text-2)' }}>
          لديك حساب؟ <Link to="/login" style={{ color: 'var(--mdd-accent)', fontWeight: 700 }}>سجّل الدخول</Link>
        </p>
      </form>
    </AuthShell>
  )
}
