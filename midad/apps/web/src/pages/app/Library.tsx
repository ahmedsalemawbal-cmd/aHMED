import React, { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { fetchTemplates, templateLocked } from '../../lib/data'
import type { Template } from '../../lib/types'
import { Badge, Button, Card, Chips, EmptyState, ErrorState, PageHead, SearchInput, SkeletonCards } from '../../ui/kit'
import { IcLock, IcSpark } from '../../ui/icons'
import TemplateThumb from './TemplateThumb'

export default function Library() {
  const { plan, roles, profile } = useApp()
  const nav = useNavigate()
  const [q, setQ] = useState('')
  const [cat, setCat] = useState<string>('all')
  const [limit, setLimit] = useState(24)
  const dq = useDebounced(q)

  const { data: templates, loading, error, reload } = useAsync(fetchTemplates, [])

  const counts = useMemo(() => {
    const m = new Map<string, number>()
    for (const t of templates || []) m.set(t.category_key, (m.get(t.category_key) || 0) + 1)
    return m
  }, [templates])

  const chips = useMemo(() => ([
    { key: 'all', label: 'الكلّ', count: templates?.length || 0 },
    ...roles.map((r) => ({ key: r.key, label: r.name_ar, count: counts.get(r.key) || 0 })),
  ].filter((c) => c.key === 'all' || c.count > 0)), [roles, counts, templates])

  const filtered = useMemo(() => {
    let list = templates || []
    if (cat !== 'all') list = list.filter((t) => t.category_key === cat)
    const term = dq.trim()
    if (term) {
      list = list.filter((t) =>
        t.title.includes(term) || (t.description || '').includes(term) ||
        t.slug.includes(term.toLowerCase()))
    }
    return list
  }, [templates, cat, dq])

  const mine = (templates || []).filter((t) => !templateLocked(t, plan)).length

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="مكتبة القوالب"
        sub={loading ? 'جارٍ التحميل…' : `${mine} ملفًّا متاحًا لك من أصل ${templates?.length || 0}`}
      />

      <div className="mdd-col" style={{ gap: 14, marginBlockEnd: 'var(--mdd-s-5)' }}>
        <SearchInput value={q} onChange={(v) => { setQ(v); setLimit(24) }} placeholder="ابحث باسم القالب — مثال: سجلّ متابعة" />
        <Chips items={chips} value={cat} onChange={(v) => { setCat(v); setLimit(24) }} />
      </div>

      {loading ? (
        <SkeletonCards n={9} />
      ) : filtered.length === 0 ? (
        <NoResults term={q} onClear={() => { setQ(''); setCat('all') }} popular={(templates || []).slice(0, 4)} plan={plan} />
      ) : (
        <>
          <div className="mdd-grid mdd-grid--3">
            {filtered.slice(0, limit).map((t) => (
              <TemplateCard key={t.id} t={t} locked={templateLocked(t, plan)}
                roleName={roles.find((r) => r.key === t.category_key)?.name_ar || t.category_key}
                onOpen={() => nav(`/app/template/${t.slug}`)} />
            ))}
          </div>
          {filtered.length > limit && (
            <div className="mdd-row" style={{ justifyContent: 'center', marginBlockStart: 'var(--mdd-s-6)' }}>
              <Button auto onClick={() => setLimit((n) => n + 24)}>حمّل المزيد ({filtered.length - limit})</Button>
            </div>
          )}
        </>
      )}
    </>
  )
}

export function TemplateCard({ t, locked, roleName, onOpen }: {
  t: Template; locked: boolean; roleName: string; onOpen: () => void
}) {
  return (
    <Card className="mdd-col" style={{ gap: 12, opacity: locked ? 0.72 : 1 }}>
      <TemplateThumb template={t} />
      <div className="mdd-row" style={{ gap: 8 }}>
        <Badge tone="accent">{roleName}</Badge>
        {t.is_new && <Badge tone="info">جديد</Badge>}
        {locked && <Badge tone="neutral"><IcLock size={11} /> باقة المدرسة</Badge>}
      </div>
      <div style={{ minHeight: 58 }}>
        <h3 style={{ fontSize: 15 }}>{t.title}</h3>
        <p className="mdd-prose" style={{ fontSize: 12.5, marginBlockStart: 5, lineHeight: 1.7 }}>{t.description}</p>
      </div>
      <div className="mdd-row mdd-row--between" style={{ marginBlockStart: 'auto' }}>
        <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
          <span className="mdd-num">{t.fields?.length || 0}</span> حقلًا · نحو <span className="mdd-num">{t.estimated_minutes}</span> دقائق
        </span>
        {locked
          ? <Link to="/app/plans"><Button size="sm" auto variant="soft">ارفع باقتك</Button></Link>
          : <Button size="sm" auto variant="primary" onClick={onOpen}>ابدأ</Button>}
      </div>
    </Card>
  )
}

function NoResults({ term, onClear, popular, plan }: { term: string; onClear: () => void; popular: Template[]; plan: any }) {
  const nav = useNavigate()
  return (
    <div className="mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
      <EmptyState
        art={<IcSpark size={64} />}
        title={term ? `لم نجد قالبًا باسم «${term}»` : 'لا قوالب في هذه الفئة'}
        line="جرّب كلمةً أقصر · امسح الفئة · أو تصفّح كلّ الفئات."
        action={<Button variant="primary" onClick={onClear}>امسح البحث</Button>}
      />
      {popular.length > 0 && (
        <div className="mdd-col">
          <h3 style={{ fontSize: 15 }}>الأكثر استعمالًا</h3>
          <div className="mdd-grid mdd-grid--4">
            {popular.map((t) => (
              <Card key={t.id} className="mdd-card--action mdd-col" onClick={() => nav(`/app/template/${t.slug}`)}
                style={{ cursor: 'pointer', gap: 8 }}>
                <h4 style={{ fontSize: 13.5 }}>{t.title}</h4>
                <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }} className="mdd-num">{t.fields?.length || 0} حقلًا</span>
              </Card>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
