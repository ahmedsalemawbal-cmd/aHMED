import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { signUpPartner } from '../../lib/auth'
import { Alert, Button, Card, Container, Field, Input } from '../../ui/kit'

/**
 * تسجيلُ شريك.
 *
 * والحسابُ يُنشأ **غيرَ معتمَد**، فلا يكتب في القاعدة شيئًا حتّى يعتمده
 * المدير — و`app.active()` تشترط `approved` للشريك وحده. فليس هذا
 * إخفاءَ زرٍّ في الواجهة بل منعًا في القاعدة.
 *
 *     الحسابُ الذي لم يُعتمَد لا يُخفى زرُّه، بل تُمنع كتابتُه.
 */
export default function PartnerRegister() {
  const { t } = useApp()
  const [f, setF] = useState({ company: '', email: '', phone: '', password: '', confirm: '' })
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')
  const [done, setDone] = useState(false)

  const set = (k: keyof typeof f) => (e: any) => setF({ ...f, [k]: e.target.value })

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setErr('')
    if (!f.company.trim() || !f.email.trim() || !f.phone.trim()) {
      setErr(t('اسم الشركة والبريد ورقم الجوال مطلوبة.')); return
    }
    if (f.password.length < 6) { setErr(t('كلمة المرور يجب أن تكون ستة أحرف على الأقل.')); return }
    if (f.password !== f.confirm) { setErr(t('كلمتا المرور غير متطابقتين.')); return }
    setBusy(true)
    try {
      await signUpPartner({ email: f.email, password: f.password,
                            company: f.company, phone: f.phone })
      setDone(true)
    } catch (e: any) { setErr(e.message) } finally { setBusy(false) }
  }

  return (
    <Container>
      <div className="osl-auth">
        <Card pad>
          <h1 className="osl-auth__h">{t('انضم كشريك')}</h1>
          <p className="osl-auth__p">
            {t('تشتري بسعر التكلفة وتبيع لعملائك بعلامتك أنت — بعروض أسعار تحمل شعارك ورقمك التسلسلي.')}
          </p>

          {done ? (
            <Alert tone="success">
              {t('وصل طلبك. يراجعه المدير ثمّ يُفعَّل حسابك — وسنبلغك على بريدك.')}
            </Alert>
          ) : (
            <form onSubmit={submit} noValidate className="osl-auth__form">
              <Field label={t('اسم الشركة')} required>
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
              <Button type="submit" block loading={busy}>{t('أرسل طلب الانضمام')}</Button>
              <p className="osl-field__help">
                {t('يبقى الحساب موقوفًا حتى يعتمده المدير — وهذا يمنع الكتابة في النظام لا يُخفي الأزرار فقط.')}
              </p>
            </form>
          )}

          <p className="osl-auth__alt">
            {t('لديك حساب؟')} <Link to="/login">{t('دخول')}</Link>
          </p>
        </Card>
      </div>
    </Container>
  )
}
