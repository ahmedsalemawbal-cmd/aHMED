import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { homeFor, resetPassword, signIn } from '../../lib/auth'
import { Alert, Button, Card, Container, Field, Input } from '../../ui/kit'

export default function Login() {
  const { t, session, role, ready } = useApp()
  const nav = useNavigate()
  const [form, setForm] = useState({ email: '', password: '' })
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')
  const [sent, setSent] = useState(false)

  // من دخل لا يُعرض له بابُ الدخول — يُساق إلى بيته
  if (ready && session) return <Navigate to={homeFor(role)} replace />

  const set = (k: 'email' | 'password') => (e: any) => setForm({ ...form, [k]: e.target.value })

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setErr(''); setBusy(true)
    try {
      await signIn(form.email, form.password)
      nav('/dashboard', { replace: true })
    } catch (e: any) { setErr(e.message) } finally { setBusy(false) }
  }

  async function forgot() {
    if (!form.email.trim()) { setErr(t('اكتب بريدك أوّلًا لنرسل رابط التعيين.')); return }
    setErr(''); setBusy(true)
    try { await resetPassword(form.email); setSent(true) }
    catch (e: any) { setErr(e.message) } finally { setBusy(false) }
  }

  return (
    <Container>
      <div className="osl-auth">
        <Card pad>
          <h1 className="osl-auth__h">{t('دخول الموظّفين والشركاء')}</h1>
          <p className="osl-auth__p">
            {t('للمدير والفروع والمناديب والشركاء وموظّفي البريد. والعملاء يدخلون من الحساب نفسِه.')}
          </p>

          {sent ? (
            <Alert tone="success">
              {t('أرسلنا رابط تعيين كلمة مرور إلى بريدك — افتحه من الجهاز نفسه.')}
            </Alert>
          ) : (
            <form onSubmit={submit} noValidate className="osl-auth__form">
              <Field label={t('البريد الإلكتروني')} required>
                <Input value={form.email} onChange={set('email')} ltr type="email"
                       autoComplete="email" autoFocus />
              </Field>
              <Field label={t('كلمة المرور')} required>
                <Input value={form.password} onChange={set('password')} type="password"
                       autoComplete="current-password" />
              </Field>
              {err ? <Alert tone="danger">{err}</Alert> : null}
              <Button type="submit" block loading={busy}>{t('دخول')}</Button>
              <button type="button" className="osl-linkbtn" onClick={forgot} disabled={busy}>
                {t('نسيت كلمة المرور؟')}
              </button>
            </form>
          )}

          <p className="osl-auth__alt">
            {t('ليس لديك حساب؟')} <Link to="/signup">{t('سجّل كعميل')}</Link>
            {' · '}
            <Link to="/partner-register">{t('انضم كشريك')}</Link>
          </p>
        </Card>
      </div>
    </Container>
  )
}
