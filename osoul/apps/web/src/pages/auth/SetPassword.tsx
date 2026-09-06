import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { setPassword } from '../../lib/auth'
import { Alert, Button, Card, Container, Field, Input } from '../../ui/kit'

/**
 * تعيينُ كلمة المرور — من رابط الدعوة أو من «نسيت كلمة المرور».
 *
 * ولا كلمةَ مرورٍ تُكتب في شيفرةٍ ولا تُرسَل في رسالة: الحسابُ يُنشأ
 * بكلمةٍ عشوائيّةٍ طويلة، ويختار صاحبُه كلمتَه من هذا الرابط.
 *
 *     ما يُرسَل في رسالةٍ يبقى في رسالة.
 */
export default function SetPassword() {
  const { t, session, ready } = useApp()
  const nav = useNavigate()
  const [f, setF] = useState({ password: '', confirm: '' })
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setErr('')
    if (f.password.length < 6) { setErr(t('كلمة المرور يجب أن تكون ستة أحرف على الأقل.')); return }
    if (f.password !== f.confirm) { setErr(t('كلمتا المرور غير متطابقتين.')); return }
    setBusy(true)
    try { await setPassword(f.password); nav('/dashboard', { replace: true }) }
    catch (e: any) { setErr(e.message) } finally { setBusy(false) }
  }

  return (
    <Container>
      <div className="osl-auth">
        <Card pad>
          <h1 className="osl-auth__h">{t('تعيين كلمة المرور')}</h1>
          {ready && !session ? (
            <Alert tone="warn">
              {t('انتهت صلاحية الرابط أو فُتح من جهاز آخر. اطلب رابطًا جديدًا من صفحة الدخول.')}
            </Alert>
          ) : (
            <form onSubmit={submit} noValidate className="osl-auth__form">
              <Field label={t('كلمة المرور الجديدة')} required help={t('ستة أحرف على الأقل')}>
                <Input value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })}
                       type="password" autoComplete="new-password" autoFocus />
              </Field>
              <Field label={t('تأكيد كلمة المرور')} required>
                <Input value={f.confirm} onChange={(e) => setF({ ...f, confirm: e.target.value })}
                       type="password" autoComplete="new-password" />
              </Field>
              {err ? <Alert tone="danger">{err}</Alert> : null}
              <Button type="submit" block loading={busy}>{t('حفظ ودخول')}</Button>
            </form>
          )}
        </Card>
      </div>
    </Container>
  )
}
