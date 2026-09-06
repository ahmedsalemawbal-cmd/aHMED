import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { getLang } from '../../lib/i18n'
import { useCart } from '../../lib/cart'
import { submitLead } from '../../lib/data'
import { Alert, Button, Container, EmptyState, Field, Input, Textarea } from '../../ui/kit'
import { IcClose, IcWhatsapp } from '../../ui/icons'

export default function Quote() {
  const { t, lang } = useApp()
  const cart = useCart()
  const [form, setForm] = useState({ name: '', phone: '', email: '', message: '' })
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState(false)
  const [err, setErr] = useState('')

  const set = (k: keyof typeof form) => (e: any) => setForm({ ...form, [k]: e.target.value })

  async function send(e: React.FormEvent) {
    e.preventDefault()
    setErr('')
    if (!form.name.trim() || !form.phone.trim()) {
      setErr(t('الاسم ورقم الجوال مطلوبان.')); return
    }
    if (!cart.lines.length) { setErr(t('أضِف منتجًا واحدًا على الأقل.')); return }
    setBusy(true)
    try {
      await submitLead({
        name: form.name.trim(), phone: form.phone.trim(), email: form.email.trim(),
        message: form.message.trim(), source: 'quote', lang: getLang(),
        items: cart.lines.map((l) => ({
          slug: l.slug, name: lang === 'ar' ? l.name_ar : (l.name_en || l.name_ar), qty: l.qty,
        })),
      })
      cart.clear()
      setDone(true)
    } catch (e: any) {
      setErr(e?.message || t('تعذّر الإرسال — حاول مرّة أخرى أو راسلنا على واتساب.'))
    } finally { setBusy(false) }
  }

  if (done) {
    return (
      <Container>
        <EmptyState
          title={t('وصلنا طلبك')}
          line={t('سنراجع القائمة ونرسل لك عرضًا مسعّرًا خلال يوم عمل. إن كان الأمر مستعجلًا فراسلنا على واتساب.')}
          action={<Link to="/" className="osl-btn osl-btn--primary">{t('الرئيسية')}</Link>}
        />
      </Container>
    )
  }

  return (
    <Container>
      <div className="osl-phead osl-phead--flat">
        <h1 className="osl-phead__h">{t('طلب عرض سعر')}</h1>
        <p className="osl-phead__p">
          {t('راجع قائمتك واكتب الكمّيات، ثمّ اترك بيانات التواصل — ونرسل العرض مسعّرًا.')}
        </p>
      </div>

      {!cart.lines.length ? (
        <EmptyState
          title={t('قائمتك فارغة')}
          line={t('تصفّح المنتجات وأضِف ما يخصّ مشروعك، ثمّ ارجع إلى هنا.')}
          action={<Link to="/doors" className="osl-btn osl-btn--primary">{t('تصفّح المنتجات')}</Link>}
        />
      ) : (
        <form className="osl-quote" onSubmit={send} noValidate>
          <div className="osl-quote__list">
            <div className="osl-table-wrap">
              <table className="osl-table">
                <thead>
                  <tr>
                    <th>{t('المنتج')}</th>
                    <th style={{ width: 140 }}>{t('الكمّية')}</th>
                    <th style={{ width: 56 }}><span className="osl-sr">{t('حذف')}</span></th>
                  </tr>
                </thead>
                <tbody>
                  {cart.lines.map((l) => (
                    <tr key={l.slug}>
                      <td data-label={t('المنتج')}>
                        <Link to={`/product/${l.slug}`}>{lang === 'ar' ? l.name_ar : (l.name_en || l.name_ar)}</Link>
                      </td>
                      <td data-label={t('الكمّية')}>
                        <input
                          className="osl-input osl-qty osl-num" type="number" min={1} inputMode="numeric"
                          value={l.qty} aria-label={t('الكمّية')}
                          onChange={(e) => cart.setQty(l.slug, parseInt(e.target.value || '1', 10))}
                        />
                      </td>
                      <td data-label={t('حذف')}>
                        <button type="button" className="osl-iconbtn osl-iconbtn--ghost"
                                onClick={() => cart.remove(l.slug)}
                                aria-label={t('احذف من القائمة')}>
                          <IcClose size={16} />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className="osl-quote__form">
            <Field label={t('الاسم')} required>
              <Input value={form.name} onChange={set('name')} autoComplete="name" />
            </Field>
            <Field label={t('رقم الجوال')} required>
              <Input value={form.phone} onChange={set('phone')} ltr inputMode="tel" autoComplete="tel" placeholder="05xxxxxxxx" />
            </Field>
            <Field label={t('البريد الإلكتروني')} help={t('اختياري — نرسل عليه نسخة من العرض.')}>
              <Input value={form.email} onChange={set('email')} ltr type="email" autoComplete="email" />
            </Field>
            <Field label={t('تفاصيل المشروع')} help={t('الموقع، مدة التنفيذ، أي مواصفة خاصة.')}>
              <Textarea value={form.message} onChange={set('message')} rows={4} />
            </Field>

            {err ? <Alert tone="danger">{err}</Alert> : null}

            <Button type="submit" block loading={busy}>{t('أرسل الطلب')}</Button>
            <a
              className="osl-btn osl-btn--secondary osl-btn--block"
              href={`https://wa.me/966556847029?text=${encodeURIComponent(
                t('طلب عرض سعر') + ':\n' +
                cart.lines.map((l) => `• ${lang === 'ar' ? l.name_ar : (l.name_en || l.name_ar)} × ${l.qty}`).join('\n'))}`}
              target="_blank" rel="noopener noreferrer"
            >
              <IcWhatsapp size={17} /><span>{t('أو أرسلها على واتساب')}</span>
            </a>
          </div>
        </form>
      )}
    </Container>
  )
}
