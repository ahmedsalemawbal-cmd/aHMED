import React, { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { supabase } from '../../lib/supabase'
import { fetchDocuments, fetchTeam, fetchTemplates, templateLocked, visibleForRole } from '../../lib/data'
import type { DocumentRow, Profile, Template } from '../../lib/types'
import { fmtRelative, fmtShort } from '../../lib/format'
import {
  Badge, Button, Card, Checkbox, ConfirmModal, EmptyState, ErrorState, Field, IconButton,
  Input, Modal, PageHead, SearchInput, Select, SkeletonRows,
} from '../../ui/kit'
import { IcCopy, IcEdit, IcFiles, IcGrid, IcList, IcPlus, IcTrash } from '../../ui/icons'
import TemplateThumb from './TemplateThumb'

type SortKey = 'recent' | 'name'
type StatusKey = 'all' | 'draft' | 'complete'
type ViewKey = 'grid' | 'table'

export default function MyFiles() {
  const { subscriber, profile, plan, roles, toast } = useApp()
  const nav = useNavigate()
  const sid = subscriber?.id
  const isSchool = subscriber?.account_type === 'school'

  const [q, setQ] = useState('')
  const [cat, setCat] = useState('all')
  const [status, setStatus] = useState<StatusKey>('all')
  const [sort, setSort] = useState<SortKey>('recent')
  const [view, setView] = useState<ViewKey>('table')
  const [picked, setPicked] = useState<string[]>([])

  const [renaming, setRenaming] = useState<DocumentRow | null>(null)
  const [renameText, setRenameText] = useState('')
  const [deleting, setDeleting] = useState<DocumentRow | null>(null)
  const [bulkDelete, setBulkDelete] = useState(false)
  const [busy, setBusy] = useState(false)

  const dq = useDebounced(q)

  const { data, loading, error, reload } = useAsync(async () => {
    if (!sid) return null
    const [docs, templates, team] = await Promise.all([fetchDocuments(sid), fetchTemplates(), fetchTeam(sid)])
    return { docs, templates, team }
  }, [sid])

  const docs = data?.docs || []
  const templates = data?.templates || []
  const team: Profile[] = data?.team || []

  const tplById = useMemo(() => {
    const m = new Map<string, Template>()
    for (const t of templates) m.set(t.id, t)
    return m
  }, [templates])

  const nameById = useMemo(() => {
    const m = new Map<string, string>()
    for (const p of team) m.set(p.id, p.full_name)
    return m
  }, [team])

  const cats = useMemo(() => {
    const keys = new Set<string>()
    for (const d of docs) {
      const t = d.template_id ? tplById.get(d.template_id) : null
      if (t) keys.add(t.category_key)
    }
    return Array.from(keys)
  }, [docs, tplById])

  const filtered = useMemo(() => {
    const term = dq.trim()
    let list = docs.filter((d) => {
      const t = d.template_id ? tplById.get(d.template_id) : null
      if (cat !== 'all' && t?.category_key !== cat) return false
      if (status !== 'all' && d.status !== status) return false
      if (term && !(d.title.includes(term) || (t?.title || '').includes(term))) return false
      return true
    })
    list = [...list].sort((a, b) =>
      sort === 'name'
        ? a.title.localeCompare(b.title, 'ar')
        : new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime())
    return list
  }, [docs, tplById, cat, status, dq, sort])

  const suggestions = useMemo(() => {
    const roleKey = profile?.role_key || 'teacher'
    return templates
      .filter((t) => visibleForRole(t, roleKey))
      .filter((t) => !templateLocked(t, plan))
      .slice(0, 3)
  }, [templates, plan, profile?.role_key])

  const allPicked = filtered.length > 0 && picked.length === filtered.length
  const togglePick = (id: string) =>
    setPicked((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]))

  async function duplicate(d: DocumentRow) {
    if (!sid || !profile) return
    const { data: copy, error: e } = await supabase.from('documents').insert({
      subscriber_id: sid, owner_id: profile.id, template_id: d.template_id,
      title: `نسخة من ${d.title}`, data: d.data || {}, status: 'draft',
    }).select().single()
    if (e || !copy) { toast('تعذّر نسخ الملفّ', 'danger'); return }
    toast('نُسخ الملفّ')
    reload()
  }

  async function doRename() {
    if (!renaming) return
    const title = renameText.trim()
    if (title.length < 2) { toast('اكتب اسمًا واضحًا للملفّ', 'danger'); return }
    setBusy(true)
    const { error: e } = await supabase.from('documents').update({ title }).eq('id', renaming.id)
    setBusy(false)
    if (e) { toast('تعذّر تغيير الاسم', 'danger'); return }
    setRenaming(null); toast('غُيّر الاسم'); reload()
  }

  async function doDelete() {
    if (!deleting) return
    setBusy(true)
    const { error: e } = await supabase.from('documents').delete().eq('id', deleting.id)
    setBusy(false)
    if (e) { toast('تعذّر حذف الملفّ', 'danger'); return }
    setPicked((cur) => cur.filter((x) => x !== deleting.id))
    setDeleting(null); toast('حُذف الملفّ'); reload()
  }

  async function doBulkDelete() {
    setBusy(true)
    const { error: e } = await supabase.from('documents').delete().in('id', picked)
    setBusy(false)
    if (e) { toast('تعذّر حذف الملفّات', 'danger'); return }
    toast(`حُذف ${picked.length} ملفًّا`)
    setPicked([]); setBulkDelete(false); reload()
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  const noneAtAll = !loading && docs.length === 0
  const noResults = !loading && docs.length > 0 && filtered.length === 0

  return (
    <>
      <PageHead
        title="ملفّاتي"
        sub={loading ? 'جارٍ التحميل…' : `${docs.length} ملفًّا · ${docs.filter((d) => d.status === 'complete').length} مكتمل`}
        actions={<Button auto variant="primary" icon={<IcPlus size={16} />} onClick={() => nav('/app/library')}>ملفّ جديد</Button>}
      />

      {!noneAtAll && (
        <div className="mdd-col" style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-5)' }}>
          <SearchInput value={q} onChange={setQ} placeholder="ابحث باسم الملفّ أو القالب" />
          <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
            <Select value={cat} onChange={(e) => setCat(e.target.value)} aria-label="الفئة">
              <option value="all">كلّ الفئات</option>
              {cats.map((c) => (
                <option key={c} value={c}>{roles.find((r) => r.key === c)?.name_ar || c}</option>
              ))}
            </Select>
            <Select value={status} onChange={(e) => setStatus(e.target.value as StatusKey)} aria-label="الحالة">
              <option value="all">كلّ الحالات</option>
              <option value="draft">مسوّدة</option>
              <option value="complete">مكتمل</option>
            </Select>
            <Select value={sort} onChange={(e) => setSort(e.target.value as SortKey)} aria-label="الترتيب">
              <option value="recent">الأحدث أوّلًا</option>
              <option value="name">حسب الاسم</option>
            </Select>
            <div className="mdd-row" style={{ gap: 6, marginInlineStart: 'auto' }}>
              <IconButton label="عرض شبكة" aria-pressed={view === 'grid'} onClick={() => setView('grid')}><IcGrid size={15} /></IconButton>
              <IconButton label="عرض جدول" aria-pressed={view === 'table'} onClick={() => setView('table')}><IcList size={15} /></IconButton>
            </div>
          </div>
        </div>
      )}

      {picked.length > 0 && (
        <Card className="mdd-row mdd-row--between mdd-row--wrap"
          style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-4)', background: 'var(--mdd-accent-soft)', borderColor: 'var(--mdd-accent)' }}>
          <span style={{ fontWeight: 700, fontSize: 13.5, color: 'var(--mdd-accent-soft-fg)' }}>
            حُدِّد <span className="mdd-num">{picked.length}</span> ملفًّا
          </span>
          <div className="mdd-row mdd-row--wrap" style={{ gap: 8 }}>
            <Button auto size="sm" variant="secondary" onClick={() => setPicked([])}>ألغِ التحديد</Button>
            <Button auto size="sm" variant="danger" icon={<IcTrash size={14} />} onClick={() => setBulkDelete(true)}>احذف المحدّد</Button>
          </div>
        </Card>
      )}

      {loading ? (
        <SkeletonRows n={6} />
      ) : noneAtAll ? (
        <EmptyState
          art={<ArtFiles />}
          title="لم تُنشئ ملفًّا بعد"
          line="اختر قالبًا من المكتبة، املأه، وصدّره."
          action={<Button variant="primary" onClick={() => nav('/app/library')}>تصفّح المكتبة</Button>}
          extra={suggestions.length > 0 && (
            <div className="mdd-col" style={{ gap: 12, marginBlockStart: 'var(--mdd-s-6)', width: '100%' }}>
              <span style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--mdd-text-3)' }}>قوالب تناسب دورك</span>
              <div className="mdd-grid mdd-grid--3">
                {suggestions.map((t) => (
                  <Link key={t.id} to={`/app/template/${t.slug}`} className="mdd-card mdd-card--action mdd-col"
                    style={{ gap: 8, textAlign: 'start' }}>
                    <h4 style={{ fontSize: 13.5 }}>{t.title}</h4>
                    <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
                      <span className="mdd-num">{t.fields?.length || 0}</span> حقلًا · نحو <span className="mdd-num">{t.estimated_minutes}</span> دقائق
                    </span>
                  </Link>
                ))}
              </div>
            </div>
          )}
        />
      ) : noResults ? (
        <EmptyState
          title={q.trim() ? `لا ملفّ يطابق «${q.trim()}»` : 'لا ملفّ بهذه الفلاتر'}
          line="جرّب كلمةً أقصر أو أعد الفلاتر إلى الكلّ."
          action={<Button variant="primary" onClick={() => { setQ(''); setCat('all'); setStatus('all') }}>أعد الفلاتر</Button>}
        />
      ) : view === 'table' ? (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead>
              <tr>
                <th style={{ width: 42 }}>
                  <Checkbox checked={allPicked} onChange={(v) => setPicked(v ? filtered.map((d) => d.id) : [])}><span className="mdd-muted">الكلّ</span></Checkbox>
                </th>
                <th>الاسم</th>
                <th>القالب</th>
                <th>آخر تعديل</th>
                <th>الحالة</th>
                {isSchool && <th>صاحبه</th>}
                <th style={{ width: 130 }}>أفعال</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((d) => {
                const t = d.template_id ? tplById.get(d.template_id) : null
                return (
                  <tr key={d.id}>
                    <td data-label="تحديد">
                      <Checkbox checked={picked.includes(d.id)} onChange={() => togglePick(d.id)}><span className="mdd-muted">حدّد</span></Checkbox>
                    </td>
                    <td data-label="الاسم">
                      <Link to={`/app/doc/${d.id}`} style={{ fontWeight: 600, color: 'var(--mdd-text)' }}>{d.title}</Link>
                    </td>
                    <td data-label="القالب"><span className="mdd-muted">{t?.title || '—'}</span></td>
                    <td data-label="آخر تعديل">
                      <span title={fmtShort(d.updated_at)}>{fmtRelative(d.updated_at)}</span>
                    </td>
                    <td data-label="الحالة">
                      <Badge tone={d.status === 'complete' ? 'success' : 'neutral'}>{d.status === 'complete' ? 'مكتمل' : 'مسوّدة'}</Badge>
                    </td>
                    {isSchool && <td data-label="صاحبه"><span className="mdd-muted">{nameById.get(d.owner_id) || '—'}</span></td>}
                    <td data-label="أفعال">
                      <div className="mdd-row" style={{ gap: 6 }}>
                        <Button auto size="sm" variant="secondary" onClick={() => nav(`/app/doc/${d.id}`)}>فتح</Button>
                        <IconButton label="نسخ" onClick={() => duplicate(d)}><IcCopy size={14} /></IconButton>
                        <IconButton label="إعادة تسمية" onClick={() => { setRenaming(d); setRenameText(d.title) }}><IcEdit size={14} /></IconButton>
                        <IconButton label="حذف" onClick={() => setDeleting(d)}><IcTrash size={14} /></IconButton>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="mdd-grid mdd-grid--3">
          {filtered.map((d) => {
            const t = d.template_id ? tplById.get(d.template_id) : null
            return (
              <Card key={d.id} className="mdd-col" style={{ gap: 12 }}>
                <div className="mdd-row mdd-row--between">
                  <Checkbox checked={picked.includes(d.id)} onChange={() => togglePick(d.id)}><span className="mdd-muted">حدّد</span></Checkbox>
                  <Badge tone={d.status === 'complete' ? 'success' : 'neutral'}>{d.status === 'complete' ? 'مكتمل' : 'مسوّدة'}</Badge>
                </div>
                {t ? <TemplateThumb template={t} height={124} /> : <div style={{
                  height: 124, borderRadius: 'var(--mdd-r-md)', border: '1px solid var(--mdd-border)',
                  background: 'var(--mdd-sunken)', display: 'grid', placeItems: 'center', color: 'var(--mdd-text-3)',
                }}><IcFiles size={26} /></div>}
                <div style={{ minHeight: 46 }}>
                  <h3 style={{ fontSize: 14 }}>{d.title}</h3>
                  <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>
                    {t?.title || 'بلا قالب'} · {fmtRelative(d.updated_at)}
                  </span>
                </div>
                {isSchool && (
                  <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>صاحبه: {nameById.get(d.owner_id) || '—'}</span>
                )}
                <div className="mdd-row mdd-row--between" style={{ marginBlockStart: 'auto' }}>
                  <Button auto size="sm" variant="primary" onClick={() => nav(`/app/doc/${d.id}`)}>فتح</Button>
                  <div className="mdd-row" style={{ gap: 6 }}>
                    <IconButton label="نسخ" onClick={() => duplicate(d)}><IcCopy size={14} /></IconButton>
                    <IconButton label="إعادة تسمية" onClick={() => { setRenaming(d); setRenameText(d.title) }}><IcEdit size={14} /></IconButton>
                    <IconButton label="حذف" onClick={() => setDeleting(d)}><IcTrash size={14} /></IconButton>
                  </div>
                </div>
              </Card>
            )
          })}
        </div>
      )}

      <Modal open={!!renaming} onClose={() => setRenaming(null)} title="إعادة تسمية الملفّ"
        footer={
          <>
            <Button variant="secondary" onClick={() => setRenaming(null)} block>إلغاء</Button>
            <Button variant="primary" onClick={doRename} loading={busy} block>احفظ الاسم</Button>
          </>
        }>
        <Field label="اسم الملفّ" help="الاسم يظهر لك في القائمة، وعنوان الورقة المطبوعة يبقى كما هو.">
          <Input value={renameText} onChange={(e) => setRenameText(e.target.value)} autoFocus />
        </Field>
      </Modal>

      <ConfirmModal
        open={!!deleting} onClose={() => setDeleting(null)} onConfirm={doDelete} loading={busy} danger
        title="حذف الملفّ" confirmLabel="احذف"
        body={`سيُحذف «${deleting?.title || ''}» نهائيًّا ولا يمكن استرجاعه.`}
      />
      <ConfirmModal
        open={bulkDelete} onClose={() => setBulkDelete(false)} onConfirm={doBulkDelete} loading={busy} danger
        title="حذف الملفّات المحدّدة" confirmLabel="احذف الكلّ"
        body={`سيُحذف ${picked.length} ملفًّا نهائيًّا ولا يمكن استرجاعها.`}
      />
    </>
  )
}

function ArtFiles() {
  return (
    <svg width="96" height="96" viewBox="0 0 96 96" fill="none" aria-hidden="true">
      <rect x="14" y="16" width="46" height="60" rx="6" stroke="currentColor" strokeWidth="2.4" />
      <path d="M24 32h26M24 44h26M24 56h16" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" />
      <rect x="38" y="26" width="44" height="56" rx="6" fill="var(--mdd-card)" stroke="currentColor" strokeWidth="2.4" />
      <path d="M48 42h24M48 54h24M48 66h14" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" />
    </svg>
  )
}
