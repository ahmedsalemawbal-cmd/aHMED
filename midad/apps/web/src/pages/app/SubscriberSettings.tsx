import React, { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { supabase } from '../../lib/supabase'
import { fmtBoth } from '../../lib/format'
import {
  Alert, Button, Card, EmptyState, Field, Input, PageHead, Select,
} from '../../ui/kit'
import { IcBack, IcLock, IcTrash } from '../../ui/icons'

const SCHOOL_TYPES = ['ابتدائية', 'متوسطة', 'ثانوية', 'مجمّع تعليميّ', 'روضة']
const SEMESTERS = ['الفصل الدراسيّ الأوّل', 'الفصل الدراسيّ الثاني', 'الفصل الدراسيّ الثالث']

interface FormState {
  name: string; logo_url: string; city: string; education_dept: string; school_type: string
  academic_year: string; semester: string; principal_name: string; contact_phone: string
}

export default function SubscriberSettings() {
  const { subscriber, profile, refresh, toast } = useApp()
  const nav = useNavigate()

  const [form, setForm] = useState<FormState>({
    name: '', logo_url: '', city: '', education_dept: '', school_type: '',
    academic_year: '', semester: '', principal_name: '', contact_phone: '',
  })
  const [saving, setSaving] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [logoBroken, setLogoBroken] = useState(false)

  useEffect(() => {
    if (!subscriber) return
    setForm({
      name: subscriber.name || '',
      logo_url: subscriber.logo_url || '',
      city: subscriber.city || '',
      education_dept: subscriber.education_dept || '',
      school_type: subscriber.school_type || '',
      academic_year: subscriber.academic_year || '',
      semester: subscriber.semester || '',
      principal_name: subscriber.principal_name || '',
      contact_phone: subscriber.contact_phone || '',
    })
  }, [subscriber?.id, subscriber?.name, subscriber?.logo_url, subscriber?.city, subscriber?.education_dept,
    subscriber?.school_type, subscriber?.academic_year, subscriber?.semester, subscriber?.principal_name,
    subscriber?.contact_phone])

  const set = (k: keyof FormState, v: string) => setForm((f) => ({ ...f, [k]: v }))

  if (profile && !profile.is_owner) {
    return (
      <>
        <PageHead title="إعدادات المشترك" />
        <EmptyState
          art={<IcLock size={62} />}
          title="هذه الشاشة لصاحب الاشتراك"
          line="بيانات المدرسة تُطبع في ترويسة كلّ ملفّ، فيحرّرها صاحب الاشتراك وحده. اطلب منه التعديل إن لزم."
          action={<Button variant="primary" onClick={() => nav('/app')}>رجوع إلى الرئيسة</Button>}
        />
      </>
    )
  }

  async function save() {
    if (!subscriber) return
    setErr(null)
    if (form.name.trim().length < 3) { setErr('اكتب اسم المدرسة كاملًا'); return }
    setSaving(true)
    const { error } = await supabase.from('subscribers').update({
      name: form.name.trim(),
      logo_url: form.logo_url.trim() || null,
      city: form.city.trim() || null,
      education_dept: form.education_dept.trim() || null,
      school_type: form.school_type || null,
      academic_year: form.academic_year.trim() || null,
      semester: form.semester || null,
      principal_name: form.principal_name.trim() || null,
      contact_phone: form.contact_phone.trim() || null,
    }).eq('id', subscriber.id)
    setSaving(false)
    if (error) { setErr('تعذّر حفظ البيانات — حاول مرّة أخرى'); return }
    await refresh()
    toast('حُفظت بيانات المدرسة')
  }

  const logo = form.logo_url.trim()

  return (
    <>
      <PageHead
        title="إعدادات المشترك"
        sub="هذه البيانات تُطبع في ترويسة كلّ ملفّ يخرج من مِداد"
        actions={<Button auto variant="primary" onClick={save} loading={saving}>{saving ? 'جارٍ الحفظ…' : 'احفظ'}</Button>}
      />

      {err && <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}><Alert tone="danger">{err}</Alert></div>}

      <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
        {/* الحقول */}
        <div className="mdd-col" style={{ gap: 'var(--mdd-s-4)' }}>
          <Card className="mdd-col" style={{ gap: 14 }}>
            <h2 className="mdd-card__title">الهوية</h2>

            <Field label="اسم المدرسة" help="كما تريده مطبوعًا في الترويسة.">
              <Input value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="مدرسة الأمل الابتدائية" />
            </Field>

            <Field label="شعار المدرسة" help="ألصق رابط صورة الشعار (PNG أو JPG).">
              <Input value={form.logo_url} onChange={(e) => { set('logo_url', e.target.value); setLogoBroken(false) }}
                ltr placeholder="https://…" />
            </Field>
            {logo && (
              <div className="mdd-row" style={{ gap: 12 }}>
                <span style={{
                  width: 66, height: 66, borderRadius: 'var(--mdd-r-sm)', border: '1px solid var(--mdd-border)',
                  background: 'var(--mdd-sunken)', display: 'grid', placeItems: 'center', overflow: 'hidden', flex: 'none',
                }}>
                  {logoBroken
                    ? <span style={{ fontSize: 10.5, color: 'var(--mdd-text-3)' }}>تعذّر العرض</span>
                    : <img src={logo} alt="شعار المدرسة" onError={() => setLogoBroken(true)}
                        style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} />}
                </span>
                <div className="mdd-col" style={{ gap: 6, minWidth: 0 }}>
                  <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>
                    {logoBroken ? 'الرابط لا يفتح صورة — تحقّق منه.' : 'هكذا يظهر في وسط الترويسة.'}
                  </span>
                  <Button auto size="sm" variant="danger" icon={<IcTrash size={14} />}
                    onClick={() => { set('logo_url', ''); setLogoBroken(false) }}>احذف الشعار</Button>
                </div>
              </div>
            )}

            <Field label="المدينة">
              <Input value={form.city} onChange={(e) => set('city', e.target.value)} placeholder="الرياض" />
            </Field>
            <Field label="إدارة التعليم">
              <Input value={form.education_dept} onChange={(e) => set('education_dept', e.target.value)}
                placeholder="الإدارة العامّة للتعليم بمنطقة الرياض" />
            </Field>
            <Field label="نوع المدرسة">
              <Select value={form.school_type} onChange={(e) => set('school_type', e.target.value)}>
                <option value="">— اختر —</option>
                {SCHOOL_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
              </Select>
            </Field>
          </Card>

          <Card className="mdd-col" style={{ gap: 14 }}>
            <h2 className="mdd-card__title">بيانات الترويسة</h2>
            <Field label="العام الدراسيّ">
              <Input value={form.academic_year} onChange={(e) => set('academic_year', e.target.value)} placeholder="1447 هـ" />
            </Field>
            <Field label="الفصل">
              <Select value={form.semester} onChange={(e) => set('semester', e.target.value)}>
                <option value="">— اختر —</option>
                {SEMESTERS.map((s) => <option key={s} value={s}>{s}</option>)}
              </Select>
            </Field>
            <Field label="اسم المدير" help="يظهر في خانة التوقيع أسفل الملفّات التي تطلبه.">
              <Input value={form.principal_name} onChange={(e) => set('principal_name', e.target.value)}
                placeholder="أحمد سالم الغامدي" />
            </Field>
            <Field label="رقم التواصل">
              <Input value={form.contact_phone} onChange={(e) => set('contact_phone', e.target.value)}
                ltr inputMode="tel" placeholder="0112345678" />
            </Field>
          </Card>

          <Button variant="primary" size="lg" onClick={save} loading={saving}>
            {saving ? 'جارٍ الحفظ…' : 'احفظ بيانات المدرسة'}
          </Button>
        </div>

        {/* المعاينة الحيّة */}
        <div className="mdd-col" style={{ gap: 10, position: 'sticky', insetBlockStart: 'calc(var(--mdd-header-h) + 12px)' }}>
          <span style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--mdd-text-3)' }}>
            الترويسة كما تُطبع — تتحدّث مع كتابتك
          </span>
          <div className="mdd-paper-shell">
            <div style={{ height: 190, overflow: 'hidden' }}>
              <div className="mdd-paper" style={{
                minHeight: 0, paddingBlockEnd: '6mm', transform: 'scale(.46)', transformOrigin: 'top center',
              }}>
                <div className="mdd-paper__head">
                  <div className="mdd-paper__head-col">
                    <strong>المملكة العربية السعودية</strong>
                    <span>وزارة التعليم</span>
                    {form.education_dept && <span>{form.education_dept}</span>}
                    {form.city && <span>{form.city}</span>}
                  </div>
                  {logo && !logoBroken
                    ? <img className="mdd-paper__logo" src={logo} alt="" onError={() => setLogoBroken(true)} />
                    : <div className="mdd-paper__logo-ph">شعار المدرسة</div>}
                  <div className="mdd-paper__head-col">
                    <strong>{form.name || 'اسم المدرسة'}{form.school_type ? ` — ${form.school_type}` : ''}</strong>
                    {form.academic_year && <span>العام الدراسي {form.academic_year}</span>}
                    {form.semester && <span>{form.semester}</span>}
                  </div>
                </div>
                <div className="mdd-paper__title">سجلّ متابعة الطالب</div>
              </div>
            </div>
          </div>

          <Card className="mdd-col" style={{ gap: 8 }}>
            <span style={{ fontSize: 12.5, fontWeight: 700 }}>ما يظهر أسفل الملفّ</span>
            <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>
              مدير المدرسة: {form.principal_name || '—'}
            </span>
            <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>
              للتواصل: <span className="mdd-num mdd-mono">{form.contact_phone || '—'}</span>
            </span>
            <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>تاريخ الطباعة: {fmtBoth(new Date())}</span>
          </Card>

          <div className="mdd-row">
            <Link to="/app/library" className="mdd-row" style={{ gap: 7, fontSize: 12.5, fontWeight: 600, color: 'var(--mdd-text-2)' }}>
              <IcBack size={14} /> جرّبها على قالبٍ من المكتبة
            </Link>
          </div>
        </div>
      </div>
    </>
  )
}
