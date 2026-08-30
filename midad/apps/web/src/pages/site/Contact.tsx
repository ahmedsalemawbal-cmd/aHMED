import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { supabase } from '../../lib/supabase'
import { isValidPhone, normalizePhone } from '../../lib/config'
import { Alert, Button, Card, Field, Input, Select, Textarea } from '../../ui/kit'
import { IcWhatsapp, IcMail, IcClock, IcCheck, IcShield } from '../../ui/icons'

const SUBJECTS = [
  'استفسار عن الاشتراك',
  'طلب عرضٍ لمدرسة',
  'مشكلة في القوالب أو التصدير',
  'مشكلة في إضافة نور',
  'اقتراح أو ملاحظة',
  'موضوع آخر',
]

type Errs = { name?: string; phone?: string; email?: string; message?: string }

export default function Contact() {
  const { general } = useApp()

  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [subject, setSubject] = useState(SUBJECTS[0])
  const [message, setMessage] = useState('')

  const [errs, setErrs] = useState<Errs>({})
  const [sending, setSending] = useState(false)
  const [failed, setFailed] = useState<string | null>(null)
  const [done, setDone] = useState(false)

  const wa = (general.whatsapp || '').replace(/[^\d]/g, '')

  function validate(): boolean {
    const e: Errs = {}
    if (name.trim().length < 3) e.name = 'اكتب اسمك الثلاثيّ أو اسم مدرستك.'
    if (!isValidPhone(phone)) e.phone = 'رقم جوّال سعوديّ يبدأ بـ 05 ومكوّن من عشر خانات.'
    if (email.trim() && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.trim())) e.email = 'صيغة البريد غير صحيحة.'
    if (message.trim().length < 10) e.message = 'اشرح طلبك في سطرٍ على الأقلّ.'
    setErrs(e)
    return Object.keys(e).length === 0
  }

  async function submit(ev: React.FormEvent) {
    ev.preventDefault()
    setFailed(null)
    if (!validate()) return
    setSending(true)
    try {
      const { error } = await supabase.from('contact_messages').insert({
        kind: 'contact',
        name: name.trim(),
        phone: normalizePhone(phone),
        email: email.trim() || null,
        subject,
        message: message.trim(),
      })
      if (error) throw new Error(error.message)
      setDone(true)
    } catch (err: any) {
      // القيم تبقى كما هي حتّى لا يُعيد الزائر كتابتها.
      setFailed(err?.message || 'تعذّر إرسال رسالتك. تحقّق من اتّصالك ثمّ حاول مرّة أخرى.')
    } finally {
      setSending(false)
    }
  }

  return (
    <>
      <section className="mdd-section mdd-section--tight">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-4)', alignItems: 'flex-start' }}>
          <span className="mdd-eyebrow">تواصل</span>
          <h1 className="mdd-hero-title" style={{ fontSize: 38 }}>اكتب لنا — نردّ في وقت العمل</h1>
          <p className="mdd-hero-sub">
            سؤالٌ عن الاشتراك، أو طلب عرضٍ لمدرستك، أو مشكلةٌ تقنية — أرسِلها وسنعود إليك على الجوّال أو البريد.
          </p>
        </div>
      </section>

      <section className="mdd-section" style={{ paddingBlockStart: 0 }}>
        <div className="mdd-site-wrap mdd-grid mdd-grid--2" style={{ gap: 'var(--mdd-s-6)', alignItems: 'start' }}>
          {/* ---------- النموذج ---------- */}
          <Card className="mdd-col mdd-card--pad-lg" style={{ gap: 'var(--mdd-s-4)' }}>
            {done ? (
              <div className="mdd-col" style={{ gap: 'var(--mdd-s-4)', alignItems: 'flex-start' }}>
                <span
                  style={{
                    width: 52, height: 52, borderRadius: 15, display: 'grid', placeItems: 'center',
                    background: 'var(--mdd-success-soft)', color: 'var(--mdd-success-fg)',
                  }}
                >
                  <IcCheck size={24} />
                </span>
                <h2 style={{ fontSize: 19 }}>وصلتنا رسالتك — شكرًا لك</h2>
                <p className="mdd-prose" style={{ fontSize: 13.5 }}>
                  سنعود إليك على الرقم <span className="mdd-num">{normalizePhone(phone)}</span> في وقت العمل:
                  {' '}{general.working_hours}. إن كان الأمر عاجلًا فراسِلنا على الواتساب مباشرةً.
                </p>
                <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
                  <a href={`https://wa.me/${wa}`} target="_blank" rel="noreferrer">
                    <Button auto variant="primary" icon={<IcWhatsapp size={15} />}>واتساب</Button>
                  </a>
                  <Button
                    auto
                    variant="secondary"
                    onClick={() => {
                      setDone(false); setName(''); setPhone(''); setEmail('')
                      setSubject(SUBJECTS[0]); setMessage(''); setErrs({})
                    }}
                  >
                    أرسِل رسالةً أخرى
                  </Button>
                </div>
              </div>
            ) : (
              <form className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }} onSubmit={submit} noValidate>
                {failed && (
                  <Alert tone="danger">
                    {failed}
                    <div style={{ marginBlockStart: 6, fontSize: 12.5 }}>
                      لم نفقد ما كتبتَه — اضغط «أرسِل» مرّةً أخرى.
                    </div>
                  </Alert>
                )}

                <h2 style={{ fontSize: 18 }}>نموذج التواصل</h2>

                <Field label="الاسم" error={errs.name}>
                  <Input
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="أحمد سالم الغامدي"
                    error={!!errs.name}
                    autoComplete="name"
                  />
                </Field>

                <Field label="الجوّال" help="اسم الدخول في مِداد هو الجوّال، ونردّ عليه." error={errs.phone}>
                  <Input
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    placeholder="05xxxxxxxx"
                    error={!!errs.phone}
                    inputMode="tel"
                    ltr
                    autoComplete="tel"
                  />
                </Field>

                <Field label="البريد الإلكترونيّ (اختياريّ)" error={errs.email}>
                  <Input
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="name@example.com"
                    error={!!errs.email}
                    inputMode="email"
                    ltr
                    autoComplete="email"
                  />
                </Field>

                <Field label="الموضوع">
                  <Select value={subject} onChange={(e) => setSubject(e.target.value)}>
                    {SUBJECTS.map((s) => <option key={s} value={s}>{s}</option>)}
                  </Select>
                </Field>

                <Field label="الرسالة" error={errs.message}>
                  <Textarea
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    placeholder="اشرح طلبك بإيجاز — اسم المدرسة، وعدد المستفيدين، وما تحتاجه بالضبط."
                    error={!!errs.message}
                    rows={6}
                  />
                </Field>

                <Button type="submit" variant="primary" block loading={sending}>
                  {sending ? 'جارٍ الإرسال…' : 'أرسِل الرسالة'}
                </Button>

                <span className="mdd-row" style={{ gap: 8, fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
                  <IcShield size={13} />
                  نستعمل بياناتك للردّ على رسالتك فقط.
                </span>
              </form>
            )}
          </Card>

          {/* ---------- القنوات المباشرة ---------- */}
          <div className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
            <Card className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
              <h2 style={{ fontSize: 18 }}>قنواتٌ مباشرة</h2>

              <a className="mdd-row" style={{ gap: 12 }} href={`https://wa.me/${wa}`} target="_blank" rel="noreferrer">
                <ChannelIcon><IcWhatsapp size={18} /></ChannelIcon>
                <span style={{ minWidth: 0 }}>
                  <span style={{ display: 'block', fontWeight: 700, fontSize: 13.5 }}>واتساب</span>
                  <span className="mdd-num" style={{ display: 'block', fontSize: 12.5, color: 'var(--mdd-text-2)' }}>
                    {general.whatsapp}
                  </span>
                </span>
              </a>

              <a className="mdd-row" style={{ gap: 12 }} href={`mailto:${general.email}`}>
                <ChannelIcon><IcMail size={18} /></ChannelIcon>
                <span style={{ minWidth: 0 }}>
                  <span style={{ display: 'block', fontWeight: 700, fontSize: 13.5 }}>البريد الإلكترونيّ</span>
                  <span className="mdd-mono" style={{ display: 'block', fontSize: 12.5, color: 'var(--mdd-text-2)' }}>
                    {general.email}
                  </span>
                </span>
              </a>

              <div className="mdd-row" style={{ gap: 12 }}>
                <ChannelIcon><IcClock size={18} /></ChannelIcon>
                <span style={{ minWidth: 0 }}>
                  <span style={{ display: 'block', fontWeight: 700, fontSize: 13.5 }}>ساعات العمل</span>
                  <span style={{ display: 'block', fontSize: 12.5, color: 'var(--mdd-text-2)' }}>
                    {general.working_hours}
                  </span>
                </span>
              </div>
            </Card>

            <Card className="mdd-col" style={{ gap: 12 }}>
              <h3 style={{ fontSize: 15 }}>قبل أن ترسل</h3>
              <p className="mdd-prose" style={{ fontSize: 13 }}>
                كثيرٌ من الأسئلة مُجابٌ عنه في صفحة الأسئلة الشائعة — الاشتراك والدفع، والقوالب والتصدير،
                وإضافة نور وأمانها.
              </p>
              <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
                <Link to="/faq"><Button auto size="sm" variant="secondary">الأسئلة الشائعة</Button></Link>
                <Link to="/pricing"><Button auto size="sm" variant="ghost">الأسعار</Button></Link>
              </div>
            </Card>
          </div>
        </div>
      </section>
    </>
  )
}

function ChannelIcon({ children }: { children: React.ReactNode }) {
  return (
    <span
      style={{
        width: 42, height: 42, borderRadius: 12, display: 'grid', placeItems: 'center', flex: 'none',
        background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
      }}
    >
      {children}
    </span>
  )
}
