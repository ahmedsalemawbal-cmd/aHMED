import React, { useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import AuthShell from './AuthShell'
import { Alert, Button, Checkbox, Field, Input } from '../../ui/kit'
import { IcEye, IcEyeOff } from '../../ui/icons'
import { useApp } from '../../lib/store'

export default function Login() {
  const { signIn, session, general } = useApp()
  const nav = useNavigate()
  const loc = useLocation() as any
  const [phone, setPhone] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(true)
  const [show, setShow] = useState(false)
  const [err, setErr] = useState<React.ReactNode>(null)
  const [busy, setBusy] = useState(false)

  if (session) return <Navigate to={loc.state?.from || '/app'} replace />

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErr(null); setBusy(true)
    try {
      await signIn(phone, password)
      nav(loc.state?.from || '/app', { replace: true })
    } catch (e: any) {
      setErr(e?.message || 'بيانات الدخول غير صحيحة')
    } finally { setBusy(false) }
  }

  return (
    <AuthShell aside={
      <>
        <h2 className="mdd-enter mdd-d1" style={{ fontSize: 26, lineHeight: 1.5, color: '#fff' }}>أهلًا بعودتك.</h2>
        <p className="mdd-enter mdd-d2" style={{ marginBlockStart: 14, fontSize: 14.5, lineHeight: 1.9, color: 'oklch(1 0 0 / .78)' }}>
          ملفّاتك وجداولك كما تركتها — تفتحها وتُكمل من حيث وقفت.
        </p>
      </>
    }>
      <form className="mdd-col" style={{ gap: 18 }} onSubmit={submit} noValidate>
        <div>
          <h1 style={{ fontSize: 24 }}>أهلًا بعودتك</h1>
          <p className="mdd-sub" style={{ fontSize: 13, marginBlockStart: 6 }}>ادخل بجوّالك وكلمة المرور.</p>
        </div>

        {err && <Alert tone="danger">{err}</Alert>}

        <Field label="الجوّال">
          <Input value={phone} onChange={(e) => setPhone(e.target.value)} ltr
            placeholder="05xxxxxxxx" inputMode="numeric" autoComplete="username" autoFocus />
        </Field>

        <Field label="كلمة المرور">
          <span style={{ position: 'relative', display: 'block' }}>
            <Input value={password} onChange={(e) => setPassword(e.target.value)}
              type={show ? 'text' : 'password'} autoComplete="current-password" style={{ paddingInlineEnd: 44 }} />
            <button type="button" onClick={() => setShow((v) => !v)} aria-label={show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'}
              style={{
                position: 'absolute', insetInlineEnd: 8, insetBlockStart: '50%', transform: 'translateY(-50%)',
                background: 'none', border: 'none', cursor: 'pointer', color: 'var(--mdd-text-3)', padding: 6,
              }}>
              {show ? <IcEyeOff size={17} /> : <IcEye size={17} />}
            </button>
          </span>
        </Field>

        <div className="mdd-row mdd-row--between">
          <Checkbox checked={remember} onChange={setRemember}>تذكّرني</Checkbox>
          <Link to="/forgot" style={{ fontSize: 12.5, color: 'var(--mdd-accent)', fontWeight: 600 }}>نسيت كلمة المرور؟</Link>
        </div>

        <Button type="submit" variant="primary" size="lg" block loading={busy}>دخول</Button>

        <div className="mdd-row" style={{ gap: 12 }}>
          <span style={{ height: 1, background: 'var(--mdd-border)', flex: 1 }} />
          <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>أو</span>
          <span style={{ height: 1, background: 'var(--mdd-border)', flex: 1 }} />
        </div>

        <p style={{ textAlign: 'center', fontSize: 13, color: 'var(--mdd-text-2)' }}>
          ليس لديك حساب؟ <Link to="/join" style={{ color: 'var(--mdd-accent)', fontWeight: 700 }}>ابدأ تجربتك</Link>
        </p>
      </form>
    </AuthShell>
  )
}
