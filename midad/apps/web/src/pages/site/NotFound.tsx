import React, { useMemo, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { Button, Card, SearchInput } from '../../ui/kit'
import { IcLogo, IcChevron, IcBack, IcSearch } from '../../ui/icons'

type Dest = { to: string; label: string; line: string; words: string }

const DESTS: Dest[] = [
  { to: '/', label: 'الصفحة الرئيسية', line: 'نظرةٌ عامّة على الخدمتين', words: 'رئيسية بداية مِداد' },
  { to: '/service/templates', label: 'قوالب الملفّات المدرسية', line: 'املأ القالب وصدّره PDF أو وورد أو إكسل', words: 'قوالب ملفّات تصدير وورد اكسل pdf تحضير' },
  { to: '/service/noor', label: 'جداول نور', line: 'نزّل كشوف نور بضغطة واحدة', words: 'نور جداول اضافة كشف درجات حضور طلاب' },
  { to: '/pricing', label: 'الأسعار', line: 'باقتان وجدول مقارنةٍ كامل', words: 'اسعار باقة اشتراك سعر فاتورة دفع' },
  { to: '/faq', label: 'الأسئلة الشائعة', line: 'إجاباتٌ عن الاشتراك والقوالب ونور', words: 'اسئلة استفسار مساعدة' },
  { to: '/contact', label: 'تواصل معنا', line: 'واتساب وبريد وساعات العمل', words: 'تواصل دعم واتساب بريد اتصال' },
  { to: '/login', label: 'تسجيل الدخول', line: 'ادخل بحسابك برقم الجوّال', words: 'دخول حساب كلمة مرور' },
  { to: '/join', label: 'إنشاء حساب', line: 'سبعة أيّام تجربة بلا بطاقة', words: 'تسجيل حساب جديد تجربة اشتراك' },
]

export default function NotFound() {
  const { session } = useApp()
  const nav = useNavigate()
  const loc = useLocation()
  const [q, setQ] = useState('')

  const term = q.trim()
  const results = useMemo(() => {
    if (!term) return []
    return DESTS.filter((d) => d.label.includes(term) || d.line.includes(term) || d.words.includes(term.toLowerCase()))
  }, [term])

  return (
    <div style={{ minHeight: '100%', display: 'flex', flexDirection: 'column' }}>
      <header className="mdd-site-header">
        <div className="mdd-site-wrap mdd-row mdd-row--between" style={{ height: 64 }}>
          <Link to="/" className="mdd-row" style={{ gap: 10 }} aria-label="مِداد — الصفحة الرئيسية">
            <span style={{ color: 'var(--mdd-accent)', display: 'flex' }}><IcLogo size={30} /></span>
            <span style={{ fontWeight: 700, fontSize: 18 }}>مِداد</span>
          </Link>
          <Link to={session ? '/app' : '/join'}>
            <Button auto size="sm" variant="primary">{session ? 'افتح لوحتي' : 'جرّب مجّانًا'}</Button>
          </Link>
        </div>
      </header>

      <main style={{ flex: 1 }}>
        <section className="mdd-section">
          <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)', maxWidth: 720 }}>
            <div className="mdd-col" style={{ gap: 'var(--mdd-s-3)', alignItems: 'flex-start' }}>
              <span
                className="mdd-num"
                style={{ fontSize: 64, fontWeight: 700, lineHeight: 1, color: 'var(--mdd-accent)' }}
              >
                404
              </span>
              <h1 className="mdd-hero-title" style={{ fontSize: 32 }}>لا توجد صفحةٌ بهذا العنوان</h1>
              <p className="mdd-hero-sub">
                ربّما تغيّر الرابط أو كُتب خطأً. ابحث عمّا تريده، أو اختر من الروابط الشائعة تحت.
              </p>
              <span className="mdd-mono" style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>
                {loc.pathname}
              </span>
            </div>

            <div className="mdd-col" style={{ gap: 'var(--mdd-s-3)' }}>
              <SearchInput value={q} onChange={setQ} placeholder="ابحث — مثال: الأسعار، نور، تصدير" autoFocus />
              {term && (
                results.length === 0 ? (
                  <Card className="mdd-col" style={{ gap: 10 }}>
                    <span className="mdd-row" style={{ gap: 9, fontWeight: 700, fontSize: 14 }}>
                      <IcSearch size={15} /> لا نتيجة لـ «{term}»
                    </span>
                    <p className="mdd-prose" style={{ fontSize: 13 }}>
                      جرّب كلمةً أقصر، أو راسِلنا وسنوصلك بما تبحث عنه.
                    </p>
                    <Link to="/contact"><Button auto size="sm" variant="primary">تواصل معنا</Button></Link>
                  </Card>
                ) : (
                  <div className="mdd-col" style={{ gap: 8 }}>
                    {results.map((d) => (
                      <Link
                        key={d.to}
                        to={d.to}
                        className="mdd-card mdd-card--action mdd-row"
                        style={{ padding: 13, gap: 12 }}
                      >
                        <div style={{ minWidth: 0, flex: 1 }}>
                          <div style={{ fontWeight: 700, fontSize: 13.5 }}>{d.label}</div>
                          <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{d.line}</div>
                        </div>
                        <IcChevron size={14} />
                      </Link>
                    ))}
                  </div>
                )
              )}
            </div>

            <div className="mdd-col" style={{ gap: 'var(--mdd-s-3)' }}>
              <h2 style={{ fontSize: 16 }}>روابط شائعة</h2>
              <div className="mdd-grid mdd-grid--2">
                {DESTS.slice(1, 7).map((d) => (
                  <Link key={d.to} to={d.to} className="mdd-card mdd-card--action mdd-col" style={{ padding: 14, gap: 5 }}>
                    <span style={{ fontWeight: 700, fontSize: 13.5 }}>{d.label}</span>
                    <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{d.line}</span>
                  </Link>
                ))}
              </div>
            </div>

            <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)' }}>
              <Link to="/"><Button auto size="lg" variant="primary" icon={<IcBack size={15} />}>العودة إلى الرئيسية</Button></Link>
              <Button auto size="lg" variant="secondary" onClick={() => nav(-1)}>الصفحة السابقة</Button>
            </div>
          </div>
        </section>
      </main>
    </div>
  )
}
