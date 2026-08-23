import React, { useMemo } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { supabase } from '../../lib/supabase'
import { fetchDocuments, fetchNoorTables, fetchTemplates, fetchTeam, templateLocked, visibleForRole } from '../../lib/data'
import { fmtBoth, fmtRelative, greeting, daysLabel, fmtNum } from '../../lib/format'
import { Badge, Button, Card, EmptyState, PageHead, Skeleton, Stat } from '../../ui/kit'
import { IcFiles, IcTable, IcLibrary, IcChevron, IcSpark } from '../../ui/icons'

export default function Dashboard() {
  const { profile, subscriber, plan, access, trialDays, roles } = useApp()
  const nav = useNavigate()
  const sid = subscriber?.id

  const { data, loading } = useAsync(async () => {
    if (!sid) return null
    const [docs, noor, team, templates] = await Promise.all([
      fetchDocuments(sid), fetchNoorTables(sid), fetchTeam(sid), fetchTemplates(),
    ])
    return { docs, noor, team, templates }
  }, [sid])

  const isSolo = subscriber?.account_type === 'teacher'
  const docs = data?.docs || []
  const noor = data?.noor || []
  const team = data?.team || []

  const suggestions = useMemo(() => {
    if (!data) return []
    const used = new Set(docs.map((d) => d.template_id))
    return data.templates
      .filter((t) => visibleForRole(t, profile?.role_key || 'teacher'))
      .filter((t) => !templateLocked(t, plan))
      .filter((t) => !used.has(t.id))
      .slice(0, 3)
  }, [data, docs, plan, profile?.role_key])

  const daysLeft = access === 'trial'
    ? trialDays
    : subscriber?.status === 'active'
      ? null
      : 0

  const roleName = roles.find((r) => r.key === profile?.role_key)?.name_ar || ''

  return (
    <>
      <PageHead
        title={`${greeting()}، ${(profile?.full_name || '').split(' ')[0] || ''}`}
        sub={fmtBoth(new Date())}
      />

      <div className="mdd-grid mdd-grid--4" style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
        {loading ? (
          Array.from({ length: isSolo ? 3 : 4 }).map((_, i) => <Card key={i}><Skeleton h={62} /></Card>)
        ) : (
          <>
            <Stat label="ملفّاتي" value={fmtNum(docs.length)} hint={docs.length ? `${docs.filter((d) => d.status === 'complete').length} مكتمل` : 'لم تبدأ بعد'} />
            <Stat label="جداول نور" value={fmtNum(noor.length)} hint={noor.length ? `${fmtNum(noor.reduce((a, t) => a + (t.row_count || 0), 0))} صفًّا` : 'لم تُنزّل جدولًا'} />
            {!isSolo && <Stat label="أعضاء الفريق" value={`${team.filter((t) => t.status === 'active').length} / ${plan?.seats ?? '—'}`} hint="مقعدًا مستعملًا" />}
            <Stat
              label={access === 'trial' ? 'أيّام التجربة' : 'حالة الاشتراك'}
              value={access === 'trial' ? daysLabel(trialDays) : access === 'active' ? 'ساري' : 'منتهٍ'}
              hint={access === 'trial' ? 'تفتح كلّ المزايا' : plan?.name_ar || ''}
            />
          </>
        )}
      </div>

      <div className="mdd-grid mdd-grid--2" style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
        <ServiceCard
          icon={<IcLibrary size={22} />}
          title="ابدأ ملفًّا جديدًا"
          line="اختر قالبًا من المكتبة، املأ حقوله، وصدّره PDF أو وورد أو إكسل."
          cta="تصفّح المكتبة"
          onClick={() => nav('/app/library')}
        />
        <ServiceCard
          icon={<IcTable size={22} />}
          title="نزّل جدولًا من نور"
          line="ثبّت إضافة المتصفّح، افتح كشفًا في نور، واضغط «أرسل إلى مِداد»."
          cta="افتح جداول نور"
          onClick={() => nav('/app/noor')}
        />
      </div>

      <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
        <Card className="mdd-col">
          <div className="mdd-row mdd-row--between">
            <h2 className="mdd-card__title">آخر ملفّاتي</h2>
            {docs.length > 0 && <Link to="/app/files" style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--mdd-accent)' }}>عرض الكلّ</Link>}
          </div>
          {loading ? (
            <div className="mdd-col" style={{ gap: 8 }}>{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} h={44} />)}</div>
          ) : docs.length === 0 ? (
            <EmptyState
              title="لم تُنشئ ملفًّا بعد"
              line="اختر قالبًا من المكتبة، املأه، وصدّره. يبقى محفوظًا في حسابك تفتحه متى شئت."
              action={<Button variant="primary" onClick={() => nav('/app/library')}>تصفّح المكتبة</Button>}
            />
          ) : (
            <div className="mdd-col" style={{ gap: 2 }}>
              {docs.slice(0, 5).map((d) => (
                <Link key={d.id} to={`/app/doc/${d.id}`} className="mdd-row"
                  style={{ padding: '11px 10px', borderRadius: 'var(--mdd-r-sm)', gap: 12 }}>
                  <IcFiles size={17} />
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ fontWeight: 600, fontSize: 13.5, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{d.title}</div>
                    <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{fmtRelative(d.updated_at)}</div>
                  </div>
                  <Badge tone={d.status === 'complete' ? 'success' : 'neutral'}>{d.status === 'complete' ? 'مكتمل' : 'مسوّدة'}</Badge>
                  <IcChevron size={14} />
                </Link>
              ))}
            </div>
          )}
        </Card>

        <Card className="mdd-col">
          <h2 className="mdd-card__title">مقترَحٌ لدورك{roleName ? ` — ${roleName}` : ''}</h2>
          {loading ? (
            <div className="mdd-col" style={{ gap: 8 }}>{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} h={62} />)}</div>
          ) : suggestions.length === 0 ? (
            <p className="mdd-prose" style={{ fontSize: 13 }}>فتحتَ كلّ قوالب دورك — تصفّح المكتبة كاملةً لترى الملفّات العامّة.</p>
          ) : (
            <div className="mdd-col" style={{ gap: 8 }}>
              {suggestions.map((t) => (
                <Link key={t.id} to={`/app/template/${t.slug}`}
                  className="mdd-card mdd-card--action mdd-row" style={{ padding: 13, gap: 12 }}>
                  <span style={{ color: 'var(--mdd-accent)' }}><IcSpark size={18} /></span>
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ fontWeight: 700, fontSize: 13.5 }}>{t.title}</div>
                    <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {t.fields?.length || 0} حقلًا · نحو {t.estimated_minutes} دقائق
                    </div>
                  </div>
                  <IcChevron size={14} />
                </Link>
              ))}
            </div>
          )}
        </Card>
      </div>
    </>
  )
}

function ServiceCard({ icon, title, line, cta, onClick }: {
  icon: React.ReactNode; title: string; line: string; cta: string; onClick: () => void
}) {
  return (
    <Card className="mdd-col" style={{ gap: 14 }}>
      <span style={{
        width: 46, height: 46, borderRadius: 13, display: 'grid', placeItems: 'center',
        background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)',
      }}>{icon}</span>
      <div>
        <h2 style={{ fontSize: 17 }}>{title}</h2>
        <p className="mdd-prose" style={{ fontSize: 13, marginBlockStart: 6 }}>{line}</p>
      </div>
      <Button variant="primary" onClick={onClick} auto style={{ alignSelf: 'flex-start' }}>{cta}</Button>
    </Card>
  )
}
