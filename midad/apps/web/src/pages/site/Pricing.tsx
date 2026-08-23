import React from 'react'
import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { fmtMoney, fmtNum } from '../../lib/format'
import type { Plan } from '../../lib/types'
import { Alert, Badge, Button, Card, EmptyState } from '../../ui/kit'
import { IcCheck, IcClose, IcClock } from '../../ui/icons'
import Accordion from './Accordion'

export default function Pricing() {
  const { plans, roles, session, subscriber, access } = useApp()

  const school = plans.find((p) => p.account_type === 'school') || null
  const teacher = plans.find((p) => p.account_type === 'teacher') || null
  const shown = [school, teacher].filter(Boolean) as Plan[]

  const onTrial = access === 'trial' || access === 'active'
  const myType = onTrial ? subscriber?.account_type : undefined

  const catLabel = (p: Plan) => {
    if (!p.template_categories?.length) return 'كلّ الفئات'
    return p.template_categories
      .map((k) => roles.find((r) => r.key === k)?.name_ar || k)
      .join(' · ')
  }

  return (
    <>
      {/* ============ العنوان ============ */}
      <section className="mdd-section mdd-section--tight">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-4)', alignItems: 'flex-start' }}>
          <span className="mdd-eyebrow">الأسعار</span>
          <h1 className="mdd-hero-title" style={{ fontSize: 38 }}>باقتان — تختار ما يشبه عملك</h1>
          <p className="mdd-hero-sub">
            سبعة أيّام تجربة، بلا بطاقة. تفتح كلّ المزايا خلالها، ثمّ تقرّر الاشتراك أو تتركه.
          </p>
          <span className="mdd-row" style={{ gap: 8, fontSize: 13, fontWeight: 600, color: 'var(--mdd-text-2)' }}>
            <span style={{ color: 'var(--mdd-success-fg)', display: 'flex' }}><IcClock size={15} /></span>
            لا نطلب بيانات بطاقةٍ لبدء التجربة
          </span>
        </div>
      </section>

      {/* ============ البطاقتان ============ */}
      <section className="mdd-section mdd-section--tight" style={{ paddingBlockStart: 0 }}>
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
          {shown.length === 0 ? (
            <EmptyState
              title="لم تُنشر الباقات بعد"
              line="نُحدّث الأسعار الآن. راسِلنا وسنرسل لك التفاصيل مباشرةً."
              action={<Link to="/contact"><Button auto variant="primary">تواصل معنا</Button></Link>}
            />
          ) : (
            <div className="mdd-grid mdd-grid--2">
              {shown.map((p) => {
                const mine = !!myType && myType === p.account_type
                const popular = p.account_type === 'school'
                return (
                  <Card
                    key={p.id}
                    className="mdd-col mdd-card--pad-lg"
                    style={{ gap: 'var(--mdd-s-4)', borderColor: popular ? 'var(--mdd-accent)' : undefined }}
                  >
                    <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 10 }}>
                      <h2 style={{ fontSize: 19 }}>{p.name_ar}</h2>
                      <div className="mdd-row" style={{ gap: 6 }}>
                        {mine && <Badge tone="success" dot>باقتك الحالية</Badge>}
                        {popular && <Badge tone="accent">الأكثر طلبًا</Badge>}
                      </div>
                    </div>

                    <div className="mdd-col" style={{ gap: 4 }}>
                      <div className="mdd-row" style={{ gap: 10, alignItems: 'baseline' }}>
                        <span className="mdd-num" style={{ fontSize: 38, fontWeight: 700, lineHeight: 1.1 }}>
                          {fmtMoney(p.price_sar)}
                        </span>
                        <span style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--mdd-text-2)' }}>
                          {p.period_months === 12
                            ? 'سنويًّا'
                            : <>لكلّ <span className="mdd-num">{p.period_months}</span> أشهر</>}
                        </span>
                      </div>
                      <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>شامل الضريبة</span>
                    </div>

                    <div
                      className="mdd-row mdd-row--wrap"
                      style={{
                        gap: 8,
                        paddingBlock: 'var(--mdd-s-3)',
                        borderBlock: '1px solid var(--mdd-border)',
                      }}
                    >
                      <Badge tone="neutral"><span className="mdd-num">{fmtNum(p.seats)}</span> مقعدًا</Badge>
                      <Badge tone={p.noor_enabled ? 'success' : 'neutral'}>
                        {p.noor_enabled ? 'جداول نور متاحة' : 'بلا جداول نور'}
                      </Badge>
                      <Badge tone="neutral">
                        <span className="mdd-num">{fmtNum(p.ai_quota_monthly)}</span> تحسينًا شهريًّا
                      </Badge>
                    </div>

                    <ul className="mdd-col" style={{ gap: 10, listStyle: 'none', padding: 0, margin: 0 }}>
                      {(p.features_ar || []).map((f, i) => (
                        <li key={i} className="mdd-row" style={{ gap: 10, fontSize: 13.5, color: 'var(--mdd-text-2)', alignItems: 'flex-start' }}>
                          <span style={{ color: 'var(--mdd-success-fg)', display: 'flex', marginBlockStart: 3 }}>
                            <IcCheck size={14} />
                          </span>
                          <span>{f}</span>
                        </li>
                      ))}
                    </ul>

                    <div style={{ marginBlockStart: 'auto', paddingBlockStart: 'var(--mdd-s-3)' }}>
                      {mine ? (
                        <Link to="/app"><Button block variant="secondary">افتح لوحتي</Button></Link>
                      ) : session ? (
                        <Link to="/app/plans"><Button block variant={popular ? 'primary' : 'secondary'}>اختر هذه الباقة</Button></Link>
                      ) : (
                        <Link to={p.account_type === 'school' ? '/join/school' : '/join/teacher'}>
                          <Button block variant={popular ? 'primary' : 'secondary'}>ابدأ التجربة</Button>
                        </Link>
                      )}
                    </div>
                  </Card>
                )
              })}
            </div>
          )}
        </div>
      </section>

      {/* ============ جدول المقارنة ============ */}
      {shown.length > 0 && (
        <section className="mdd-section" style={{ background: 'var(--mdd-sunken)' }}>
          <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-5)' }}>
            <div className="mdd-col" style={{ gap: 10 }}>
              <h2 className="mdd-h2">مقارنةٌ كاملة</h2>
              <p className="mdd-prose" style={{ fontSize: 14.5 }}>
                الأرقام كلّها من الباقات المعتمدة في المنصّة — لا تقديرات.
              </p>
            </div>

            <div className="mdd-table-wrap mdd-table-wrap--cards">
              <table className="mdd-table mdd-table--zebra">
                <thead>
                  <tr>
                    <th style={{ width: '34%' }}>الميزة</th>
                    {shown.map((p) => <th key={p.id}>{p.name_ar}</th>)}
                  </tr>
                </thead>
                <tbody>
                  <Row label="السعر" plans={shown}
                    cell={(p) => <span className="mdd-num" style={{ fontWeight: 700 }}>{fmtMoney(p.price_sar)}</span>} />
                  <Row label="مدّة الاشتراك" plans={shown}
                    cell={(p) => (p.period_months === 12
                      ? 'سنة كاملة'
                      : <><span className="mdd-num">{p.period_months}</span> أشهر</>)} />
                  <Row label="المقاعد (عدد المستخدمين)" plans={shown}
                    cell={(p) => <><span className="mdd-num">{fmtNum(p.seats)}</span> مقعدًا</>} />
                  <Row label="فئات القوالب المتاحة" plans={shown} cell={(p) => catLabel(p)} />
                  <Row label="جداول نور" plans={shown}
                    cell={(p) => (p.noor_enabled
                      ? <span className="mdd-row" style={{ gap: 7, color: 'var(--mdd-success-fg)', fontWeight: 600 }}>
                          <IcCheck size={14} /> متاحة
                        </span>
                      : <span className="mdd-row" style={{ gap: 7, color: 'var(--mdd-text-3)' }}>
                          <IcClose size={14} /> غير متاحة
                        </span>)} />
                  <Row label="حصّة تحسين الصياغة شهريًّا" plans={shown}
                    cell={(p) => (p.ai_quota_monthly > 0
                      ? <><span className="mdd-num">{fmtNum(p.ai_quota_monthly)}</span> تحسينًا</>
                      : <span style={{ color: 'var(--mdd-text-3)' }}>غير مشمولة</span>)} />
                  <Row label="التجربة المجّانية" plans={shown} cell={() => 'سبعة أيّام بلا بطاقة'} />
                  <Row label="التصدير" plans={shown} cell={() => 'PDF · وورد · إكسل'} />
                </tbody>
              </table>
            </div>

            <Alert tone="info">
              الاشتراك يبدأ من يوم اعتماد الدفع، ولا تتأثّر ملفّاتك المحفوظة عند انتهاء المدّة — تبقى في حسابك.
            </Alert>
          </div>
        </section>
      )}

      {/* ============ أسئلة الشراء ============ */}
      <section className="mdd-section">
        <div className="mdd-site-wrap mdd-col" style={{ gap: 'var(--mdd-s-5)' }}>
          <h2 className="mdd-h2">أسئلة قبل الشراء</h2>
          <div className="mdd-col" style={{ gap: 10, maxWidth: 820 }}>
            <Accordion q="هل أحتاج بطاقةً لبدء التجربة؟">
              لا. تُنشئ الحساب برقم جوّالك فقط، وتبدأ التجربة فورًا لسبعة أيّام كاملة بكلّ المزايا.
              لا نطلب أيّ بيانات دفعٍ قبل أن تقرّر الاشتراك.
            </Accordion>
            <Accordion q="كيف أدفع؟ وهل تصلني فاتورة؟">
              الدفع بالتحويل البنكيّ إلى حساب المنصّة. تُصدَر لك فاتورةٌ برقمٍ وتاريخ فور طلب الاشتراك،
              ترفع عليها إيصال التحويل، ثمّ تُعتمد ويُفعّل الاشتراك. الفواتير كلّها محفوظة في حسابك.
            </Accordion>
            <Accordion q="ماذا يحدث لملفّاتي إذا انتهى الاشتراك؟">
              تبقى ملفّاتك وجداولك في حسابك كما هي. يتوقّف الإنشاء والتصدير الجديد حتّى تجدّد،
              ثمّ يعود كلّ شيء إلى ما كان عليه دون فقد شيء.
            </Accordion>
            <Accordion q="هل أستطيع ترقية باقتي أو تغيير نوع الحساب لاحقًا؟">
              نعم. الترقية من باقة المعلّم إلى باقة المدرسة متاحة من صفحة الاشتراك في حسابك،
              ويُحتسب لك ما تبقّى من مدّتك الحالية. راسِلنا إن احتجت تغييرًا لا تجده في الشاشة.
            </Accordion>
          </div>

          <div className="mdd-row mdd-row--wrap" style={{ gap: 'var(--mdd-s-3)', marginBlockStart: 'var(--mdd-s-3)' }}>
            <Link to="/faq"><Button auto variant="secondary">كلّ الأسئلة الشائعة</Button></Link>
            <Link to="/contact"><Button auto variant="ghost">لديّ سؤالٌ آخر</Button></Link>
          </div>
        </div>
      </section>
    </>
  )
}

function Row({ label, plans, cell }: {
  label: string; plans: Plan[]; cell: (p: Plan) => React.ReactNode
}) {
  return (
    <tr>
      <td data-label="الميزة" style={{ fontWeight: 600 }}>{label}</td>
      {plans.map((p) => (
        <td key={p.id} data-label={p.name_ar}>{cell(p)}</td>
      ))}
    </tr>
  )
}
