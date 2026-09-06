import React from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import AuthShell from './AuthShell'
import { Button, Card } from '../../ui/kit'
import { IcCheck, IcLibrary, IcTable, IcSettings } from '../../ui/icons'
import { useApp } from '../../lib/store'
import { daysLabel, fmtBoth } from '../../lib/format'

export default function Welcome() {
  const { session, subscriber, trialDays, profile, roles } = useApp()
  const nav = useNavigate()
  if (!session) return <Navigate to="/login" replace />

  const roleName = roles.find((r) => r.key === profile?.role_key)?.name_ar || 'دورك'
  const steps = [
    { icon: IcLibrary, title: 'ابدأ ملفّك الأوّل', line: `افتح المكتبة وستجد ملفّات ${roleName} والملفّات العامّة.`, to: '/app/library', cta: 'تصفّح المكتبة' },
    { icon: IcSettings, title: 'اضبط ترويسة ملفّاتك', line: 'اسم المدرسة وشعارها والعام الدراسيّ — تُطبع في أعلى كلّ ملفّ.', to: '/app/settings', cta: 'اضبط الترويسة' },
    { icon: IcTable, title: 'اربط جداول نور', line: 'ثبّت الإضافة وانسخ مفتاح الربط لتنزيل كشوفك من نور.', to: '/app/noor', cta: 'افتح جداول نور' },
  ]

  return (
    <AuthShell wide>
      <div className="mdd-col" style={{ gap: 24 }}>
        <div style={{ textAlign: 'center' }}>
          <span style={{
            width: 62, height: 62, borderRadius: 18, display: 'inline-grid', placeItems: 'center',
            background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)', marginBlockEnd: 16,
          }}><IcCheck size={30} /></span>
          <h1 style={{ fontSize: 27 }}>بدأت تجربتك — {daysLabel(trialDays || 7)}</h1>
          <p className="mdd-sub" style={{ fontSize: 14, marginBlockStart: 10, lineHeight: 1.8 }}>
            أهلًا {(profile?.full_name || '').split(' ')[0]}. المنتج مفتوحٌ لك كاملًا حتى{' '}
            <strong style={{ color: 'var(--mdd-text)' }}>{fmtBoth(subscriber?.trial_ends_at)}</strong>.
            <br />ملفّاتك تخرج بعلامة مائية أثناء التجربة، وتزول فور اشتراكك.
          </p>
        </div>

        <div className="mdd-grid mdd-grid--3">
          {steps.map((s, i) => (
            <Card key={s.title} className="mdd-col" style={{ gap: 12 }}>
              <span className="mdd-row" style={{ gap: 10 }}>
                <span style={{
                  width: 26, height: 26, borderRadius: 8, display: 'grid', placeItems: 'center',
                  background: 'var(--mdd-accent)', color: 'var(--mdd-on-accent)', fontSize: 12, fontWeight: 700,
                }} className="mdd-num">{i + 1}</span>
                <s.icon size={18} />
              </span>
              <div>
                <h3 style={{ fontSize: 14.5 }}>{s.title}</h3>
                <p className="mdd-prose" style={{ fontSize: 12.5, marginBlockStart: 6 }}>{s.line}</p>
              </div>
              <Link to={s.to} style={{ marginBlockStart: 'auto' }}>
                <Button size="sm" auto variant="soft">{s.cta}</Button>
              </Link>
            </Card>
          ))}
        </div>

        <Button variant="primary" size="lg" block onClick={() => nav('/app')}>ادخل إلى لوحتي</Button>
      </div>
    </AuthShell>
  )
}
