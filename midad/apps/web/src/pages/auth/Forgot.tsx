import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import AuthShell from './AuthShell'
import { Alert, Button, Field, Input } from '../../ui/kit'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { IcWhatsapp, IcMail } from '../../ui/icons'

export default function Forgot() {
  const { general } = useApp()
  const [id, setId] = useState('')
  const [busy, setBusy] = useState(false)
  const [sent, setSent] = useState(false)
  const [err, setErr] = useState<string | null>(null)

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!id.trim()) { setErr('اكتب جوّالك أو بريدك'); return }
    setBusy(true); setErr(null)
    // لا نكشف هل الحساب موجود أم لا — نسجّل الطلب دائمًا ونعرض الرسالة نفسها.
    const { error } = await supabase.from('contact_messages').insert({
      kind: 'password_reset',
      phone: /^\d/.test(id.trim()) ? id.trim() : null,
      email: /@/.test(id) ? id.trim() : null,
      subject: 'طلب استعادة كلمة المرور',
      message: `طلب استعادة لـ: ${id.trim()}`,
    })
    setBusy(false)
    if (error) { setErr('تعذّر إرسال الطلب — حاول مرّة أخرى.'); return }
    setSent(true)
  }

  return (
    <AuthShell>
      <div className="mdd-col" style={{ gap: 18 }}>
        <div>
          <h1 style={{ fontSize: 24 }}>نسيت كلمة المرور</h1>
          <p className="mdd-sub" style={{ fontSize: 13, marginBlockStart: 6 }}>
            أدخل جوّالك أو بريدك، ويتواصل معك فريق مِداد لإعادة تعيينها.
          </p>
        </div>

        {sent ? (
          <>
            <Alert tone="success">
              استلمنا طلبك. إن كان لهذه البيانات حسابٌ في مِداد فسيتواصل معك فريقنا خلال ساعات العمل لإعادة تعيين كلمة مرورك.
            </Alert>
            <div className="mdd-card mdd-col" style={{ gap: 12 }}>
              <span style={{ fontSize: 13, fontWeight: 700 }}>تريد تعجيل الأمر؟</span>
              <a className="mdd-row" style={{ gap: 10, fontSize: 13, color: 'var(--mdd-accent)', fontWeight: 600 }}
                href={`https://wa.me/${general.whatsapp}`} target="_blank" rel="noreferrer">
                <IcWhatsapp size={17} /> واتساب
              </a>
              <a className="mdd-row" style={{ gap: 10, fontSize: 13, color: 'var(--mdd-accent)', fontWeight: 600 }}
                href={`mailto:${general.email}`}>
                <IcMail size={17} /> {general.email}
              </a>
              <span className="mdd-field__help">{general.working_hours}</span>
            </div>
            <Link to="/login"><Button block>عُد إلى الدخول</Button></Link>
          </>
        ) : (
          <form className="mdd-col" style={{ gap: 18 }} onSubmit={submit} noValidate>
            {err && <Alert tone="danger">{err}</Alert>}
            <Field label="الجوّال أو البريد">
              <Input value={id} onChange={(e) => setId(e.target.value)} ltr placeholder="05xxxxxxxx" autoFocus />
            </Field>
            <Button type="submit" variant="primary" size="lg" block loading={busy}>أرسل الطلب</Button>
            <p style={{ textAlign: 'center', fontSize: 13 }}>
              <Link to="/login" style={{ color: 'var(--mdd-accent)', fontWeight: 700 }}>رجوع إلى الدخول</Link>
            </p>
          </form>
        )}
      </div>
    </AuthShell>
  )
}
