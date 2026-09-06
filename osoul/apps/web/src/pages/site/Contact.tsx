import { useState } from 'react'
import { useApp } from '../../lib/store'
import { getLang } from '../../lib/i18n'
import { useAsync } from '../../lib/hooks'
import { fetchSettings, submitLead } from '../../lib/data'
import { Alert, Button, Container, Field, Input, Textarea } from '../../ui/kit'
import { IcMail, IcPhone, IcWhatsapp } from '../../ui/icons'

export default function Contact() {
  const { t, lang } = useApp()
  const { data: s } = useAsync(fetchSettings, [])
  const [form, setForm] = useState({ name: '', phone: '', email: '', subject: '', message: '' })
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState(false)
  const [err, setErr] = useState('')

  const set = (k: keyof typeof form) => (e: any) => setForm({ ...form, [k]: e.target.value })
  const g = s?.general ?? {}
  const c = s?.contact ?? {}
  const addr = lang === 'ar' ? c.address_ar : (c.address_en || c.address_ar)
  const hours = lang === 'ar' ? g.hours_ar : (g.hours_en || g.hours_ar)

  async function send(e: React.FormEvent) {
    e.preventDefault()
    setErr('')
    if (!form.name.trim() || !form.phone.trim()) {
      setErr(t('الاسم ورقم الجوال مطلوبان.')); return
    }
    setBusy(true)
    try {
      await submitLead({ ...form, source: 'contact', lang: getLang() })
      setDone(true)
    } catch (e: any) {
      setErr(e?.message || t('تعذّر الإرسال — حاول مرّة أخرى أو راسلنا على واتساب.'))
    } finally { setBusy(false) }
  }

  return (
    <Container>
      <div className="osl-phead osl-phead--flat">
        <h1 className="osl-phead__h">{t('تواصل معنا')}</h1>
        <p className="osl-phead__p">
          {t('اكتب لنا وسنردّ في نفس يوم العمل. أو اتصل مباشرة إن كان الأمر مستعجلًا.')}
        </p>
      </div>

      <div className="osl-contact">
        <div className="osl-contact__info">
          {g.phone_primary ? (
            <a className="osl-cline" href={`tel:${String(g.phone_primary).replace(/\s/g, '')}`}>
              <span className="osl-cline__ic"><IcPhone size={18} /></span>
              <span>
                <b>{t('هاتف')}</b>
                <i className="osl-num" dir="ltr">{g.phone_primary}</i>
              </span>
            </a>
          ) : null}
          {g.phone_secondary ? (
            <a className="osl-cline" href={`tel:${String(g.phone_secondary).replace(/\s/g, '')}`}>
              <span className="osl-cline__ic"><IcPhone size={18} /></span>
              <span>
                <b>{t('هاتف آخر')}</b>
                <i className="osl-num" dir="ltr">{g.phone_secondary}</i>
              </span>
            </a>
          ) : null}
          {g.email ? (
            <a className="osl-cline" href={`mailto:${g.email}`}>
              <span className="osl-cline__ic"><IcMail size={18} /></span>
              <span><b>{t('البريد الإلكتروني')}</b><i dir="ltr">{g.email}</i></span>
            </a>
          ) : null}
          {g.whatsapp ? (
            <a className="osl-cline" href={`https://wa.me/${g.whatsapp}`} target="_blank" rel="noopener noreferrer">
              <span className="osl-cline__ic"><IcWhatsapp size={18} /></span>
              <span><b>{t('واتساب')}</b><i>{t('راسلنا مباشرة')}</i></span>
            </a>
          ) : null}
          {addr ? <p className="osl-caddr"><b>{t('العنوان')}</b><br />{addr}</p> : null}
          {hours ? <p className="osl-caddr"><b>{t('أوقات العمل')}</b><br />{hours}</p> : null}

          {c.maps_embed ? (
            <iframe
              className="osl-map" src={c.maps_embed}
              title={t('موقعنا على الخريطة')} loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
            />
          ) : null}
        </div>

        <form className="osl-contact__form" onSubmit={send} noValidate>
          {done ? (
            <Alert tone="success">
              {t('وصلتنا رسالتك — سنردّ عليك في أقرب وقت.')}
            </Alert>
          ) : (
            <>
              <Field label={t('الاسم')} required>
                <Input value={form.name} onChange={set('name')} autoComplete="name" />
              </Field>
              <Field label={t('رقم الجوال')} required>
                <Input value={form.phone} onChange={set('phone')} ltr inputMode="tel" autoComplete="tel" placeholder="05xxxxxxxx" />
              </Field>
              <Field label={t('البريد الإلكتروني')}>
                <Input value={form.email} onChange={set('email')} ltr type="email" autoComplete="email" />
              </Field>
              <Field label={t('الموضوع')}>
                <Input value={form.subject} onChange={set('subject')} />
              </Field>
              <Field label={t('الرسالة')} required>
                <Textarea value={form.message} onChange={set('message')} rows={5} />
              </Field>
              {err ? <Alert tone="danger">{err}</Alert> : null}
              <Button type="submit" block loading={busy}>{t('أرسل الرسالة')}</Button>
            </>
          )}
        </form>
      </div>
    </Container>
  )
}
