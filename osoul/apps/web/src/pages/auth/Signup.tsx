import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { signUpCustomer } from '../../lib/auth'
import { Alert, Button, Card, Container, Field, Input } from '../../ui/kit'

export default function Signup() {
  const { t } = useApp()
  const nav = useNavigate()
  const [f, setF] = useState({ name: '', company: '', email: '', phone: '', password: '', confirm: '' })
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')

  const set = (k: keyof typeof f) => (e: any) => setF({ ...f, [k]: e.target.value })

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setErr('')
    if (!f.name.trim() || !f.email.trim() || !f.phone.trim()) {
      setErr(t('الاسم والبريد ورقم الجوال مطلوبة.')); return
    }
    if (f.password.length < 6) { setErr(t('كلمة المرور يجب أن تكون ستة أحرف على الأقل.')); return }
    if (f.password !== f.confirm) { setErr(t('كلمتا المرور غير متطابقتين.')); return }
    setBusy(true)
    try {
      await signUpCustomer({ email: f.email, password: f.password, fullName: f.name,
                             phone: f.phone, company: f.company })
      nav('/dashboard', { replace: true })
    } catch (e: any) { setErr(e.message) } finally { setBusy(false) }
  }

  return (
    <Container>
      <div className="osl-auth">
        <Card pad>
          <h1 className="osl-auth__h">{t('حساب عميل')}</h1>
          <p className="osl-auth__p">
            {t('بحسابك تتابع عروض أسعارك ومراحلها، وتراسل الإدارة مباشرة.')}
          </p>
          <form onSubmit={submit} noValidate className="osl-auth__form">
            <Field label={t('الاسم')} required>
              <Input value={f.name} onChange={set('name')} autoComplete="name" />
            </Field>
            <Field label={t('الشركة')} help={t('اختياري')}>
              <Input value={f.company} onChange={set('company')} autoComplete="organization" />
            </Field>
            <Field label={t('البريد الإلكتروني')} required>
              <Input value={f.email} onChange={set('email')} ltr type="email" autoComplete="email" />
            </Field>
            <Field label={t('رقم الجوال')} required>
              <Input value={f.phone} onChange={set('phone')} ltr inputMode="tel"
                     autoComplete="tel" placeholder="05xxxxxxxx" />
            </Field>
            <Field label={t('كلمة المرور')} required help={t('ستة أحرف على الأقل')}>
              <Input value={f.password} onChange={set('password')} type="password"
                     autoComplete="new-password" />
            </Field>
            <Field label={t('تأكيد كلمة المرور')} required>
              <Input value={f.confirm} onChange={set('confirm')} type="password"
                     autoComplete="new-password" />
            </Field>
            {err ? <Alert tone="danger">{err}</Alert> : null}
            <Button type="submit" block loading={busy}>{t('إنشاء الحساب')}</Button>
          </form>
          <p className="osl-auth__alt">
            {t('لديك حساب؟')} <Link to="/login">{t('دخول')}</Link>
          </p>
        </Card>
      </div>
    </Container>
  )
}
