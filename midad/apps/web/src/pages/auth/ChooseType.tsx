import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import AuthShell from './AuthShell'
import { Button } from '../../ui/kit'
import { IcTeam, IcUser, IcCheck } from '../../ui/icons'

const CARDS = [
  {
    key: 'school' as const, title: 'مدرسة', icon: IcTeam,
    line: 'للمدارس التي تريد أن يدخل فريقها كلّه',
    points: ['عدّة مستخدمين بأدوار مختلفة', 'إدارة مركزية لملفّات المدرسة', 'فاتورة واحدة باسم المدرسة'],
  },
  {
    key: 'teacher' as const, title: 'معلّم', icon: IcUser,
    line: 'لمعلّمٍ أو معلّمة يشترك لنفسه',
    points: ['حساب واحد باسمك', 'ملفّات دورك والملفّات العامّة', 'أرخص — مقعد واحد'],
  },
]

export default function ChooseType() {
  const nav = useNavigate()
  const [sel, setSel] = useState<'school' | 'teacher' | null>(null)

  return (
    <AuthShell wide>
      <div className="mdd-col" style={{ gap: 22 }}>
        <div style={{ textAlign: 'center' }}>
          <h1 style={{ fontSize: 26 }}>كيف ستستعمل مِداد؟</h1>
          <p className="mdd-sub" style={{ fontSize: 14, marginBlockStart: 8 }}>
            الاختيار يحدّد باقتك وما تراه من الملفّات — ويمكن تغييره لاحقًا بالتواصل معنا.
          </p>
        </div>

        <div className="mdd-grid mdd-grid--2">
          {CARDS.map((c) => (
            <button
              key={c.key}
              type="button"
              onClick={() => { setSel(c.key); nav(`/join/${c.key}`) }}
              onMouseEnter={() => setSel(c.key)}
              className={'mdd-card mdd-card--action mdd-col' + (sel === c.key ? ' mdd-card--selected' : '')}
              style={{ gap: 14, padding: 'var(--mdd-s-6)', alignItems: 'stretch' }}
            >
              <span className="mdd-row mdd-row--between">
                <span style={{
                  width: 46, height: 46, borderRadius: 13, display: 'grid', placeItems: 'center',
                  background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
                }}><c.icon size={22} /></span>
                {sel === c.key && <span style={{ color: 'var(--mdd-accent)' }}><IcCheck size={20} /></span>}
              </span>
              <span>
                <span style={{ display: 'block', fontSize: 19, fontWeight: 700 }}>{c.title}</span>
                <span style={{ display: 'block', fontSize: 13, color: 'var(--mdd-text-2)', marginBlockStart: 5, lineHeight: 1.7 }}>{c.line}</span>
              </span>
              <span className="mdd-col" style={{ gap: 9 }}>
                {c.points.map((p) => (
                  <span key={p} className="mdd-row" style={{ gap: 9, fontSize: 12.5, color: 'var(--mdd-text-2)' }}>
                    <span style={{ color: 'var(--mdd-accent)' }}><IcCheck size={14} /></span>{p}
                  </span>
                ))}
              </span>
            </button>
          ))}
        </div>

        <p style={{ textAlign: 'center', fontSize: 13.5, color: 'var(--mdd-text-2)' }}>
          لديك حساب؟ <Link to="/login" style={{ color: 'var(--mdd-accent)', fontWeight: 700 }}>سجّل الدخول</Link>
        </p>
      </div>
    </AuthShell>
  )
}
