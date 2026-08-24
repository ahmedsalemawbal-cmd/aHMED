import React, { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { fmtNum, fmtShort, counted, TPL } from '../../lib/format'
import type { Template, TemplateFolder } from '../../lib/types'
import {
  Badge, Button, Card, ConfirmModal, EmptyState, ErrorState, PageHead,
  SearchInput, Select, SkeletonRows,
} from '../../ui/kit'
import { IcPlus, IcEdit, IcCopy, IcTrash, IcFolderFill, IcPage, IcEye } from '../../ui/icons'
import ImportTemplate from './ImportTemplate'

/**
 * مكتبة القوالب — لوحة المالك.
 *
 * القالب هنا مستندٌ يُصمَّم مرّةً فيظهر لكلّ المعلّمين: يفتحونه فيحرّرونه،
 * ويحسّنونه بالذكاء الاصطناعيّ، ويُنزّلونه PDF أو وورد. فالنشر من هذه
 * الشاشة هو ما يُوصله إليهم، والمسوّدة لا يراها أحدٌ سواك.
 */
export default function Templates() {
  const { toast } = useApp()
  const nav = useNavigate()
  const [q, setQ] = useState('')
  const [folder, setFolder] = useState('')
  const [status, setStatus] = useState('')
  const [del, setDel] = useState<Template | null>(null)
  const [busy, setBusy] = useState(false)
  const [importing, setImporting] = useState(false)
  const dq = useDebounced(q)

  const { data, loading, error, reload } = useAsync(async () => {
    const [t, f] = await Promise.all([
      supabase.from('templates').select('*').order('sort').order('title'),
      supabase.from('template_folders').select('*').order('sort').order('name'),
    ])
    if (t.error) throw new Error(t.error.message)
    if (f.error) throw new Error(f.error.message)
    return { list: (t.data || []) as Template[], folders: (f.data || []) as TemplateFolder[] }
  }, [])

  const list = data?.list || []
  const folders = data?.folders || []
  const byId = useMemo(() => new Map(folders.map((f) => [f.id, f])), [folders])

  const published = list.filter((t) => t.status === 'published').length
  const drafts = list.filter((t) => t.status === 'draft').length

  const shown = useMemo(() => {
    let out = list
    const term = dq.trim()
    if (term) out = out.filter((t) => t.title.includes(term) || t.slug.includes(term.toLowerCase()))
    if (folder) out = out.filter((t) => (folder === '_none' ? !t.folder_id : t.folder_id === folder))
    if (status) out = out.filter((t) => t.status === status)
    return out
  }, [list, dq, folder, status])

  const create = async () => {
    setBusy(true)
    const slug = `template-${Date.now().toString(36)}`
    const { data: row, error: e } = await supabase.from('templates').insert({
      slug,
      title: 'قالب جديد',
      category_key: 'general',
      description: '',
      kind: 'doc',
      folder_id: folders[0]?.id ?? null,
      content_html: '<h1>عنوان المستند</h1>\n<p>اكتب هنا، أو أدرج جدولًا من شريط الأدوات.</p>',
      status: 'draft',
      outputs: ['pdf', 'docx'],
    }).select('id').single()
    setBusy(false)
    if (e) { toast(e.message, 'danger'); return }
    nav(`/admin/template/${(row as any).id}`)
  }

  const duplicate = async (t: Template) => {
    const { data: row, error: e } = await supabase.from('templates').insert({
      slug: `${t.slug}-copy-${Date.now().toString(36)}`,
      title: `نسخة من ${t.title}`,
      category_key: t.category_key,
      description: t.description,
      kind: t.kind ?? 'doc',
      folder_id: t.folder_id,
      content_html: t.content_html ?? '',
      page: t.page,
      outputs: t.outputs,
      estimated_minutes: t.estimated_minutes,
      status: 'draft',
    }).select('id').single()
    if (e) { toast(e.message, 'danger'); return }
    toast('أُنشئت نسخة مسوّدة')
    nav(`/admin/template/${(row as any).id}`)
  }

  const togglePublish = async (t: Template) => {
    const next = t.status === 'published' ? 'draft' : 'published'
    const { error: e } = await supabase.from('templates').update({ status: next }).eq('id', t.id)
    if (e) { toast(e.message, 'danger'); return }
    toast(next === 'published' ? 'نُشر القالب — صار يظهر للمعلّمين' : 'سُحب القالب من المعلّمين')
    reload()
  }

  const remove = async () => {
    if (!del) return
    setBusy(true)
    const { error: e } = await supabase.from('templates').delete().eq('id', del.id)
    setBusy(false)
    if (e) { toast('تعذّر الحذف — قد تكون هناك ملفّات مبنيّة عليه', 'danger'); return }
    toast('حُذف القالب'); setDel(null); reload()
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="مكتبة القوالب"
        sub={loading ? 'جارٍ التحميل…'
          : `${fmtNum(published)} منشورًا يراه المعلّمون · ${fmtNum(drafts)} مسوّدة عندك`}
        actions={
          <div className="mdd-row" style={{ gap: 8 }}>
            <Button auto variant="secondary" icon={<IcPage size={15} />}
              onClick={() => setImporting(true)}>استورد ملفًّا</Button>
            <Button auto variant="primary" icon={<IcPlus size={15} />} loading={busy}
              onClick={create}>قالب جديد</Button>
          </div>
        }
      />

      <Card className="mdd-col" style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-4)' }}>
        <SearchInput value={q} onChange={setQ} placeholder="ابحث بالعنوان أو المفتاح" />
        <div className="mdd-grid mdd-grid--2" style={{ gap: 10 }}>
          <Select value={folder} onChange={(e) => setFolder(e.target.value)} aria-label="المجلّد">
            <option value="">كلّ المجلّدات</option>
            {folders.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
            <option value="_none">بلا مجلّد</option>
          </Select>
          <Select value={status} onChange={(e) => setStatus(e.target.value)} aria-label="الحالة">
            <option value="">كلّ الحالات</option>
            <option value="published">منشور</option>
            <option value="draft">مسوّدة</option>
          </Select>
        </div>
      </Card>

      {loading ? <SkeletonRows n={8} /> : shown.length === 0 ? (
        <EmptyState
          art={<IcFolderFill size={58} />}
          title={list.length ? 'لا نتيجة' : 'المكتبة فارغة'}
          line={list.length
            ? 'جرّب كلمةً أقصر أو امسح الفلاتر.'
            : 'صمّم قالبًا في المحرّر، أو استورد ملفًّا جاهزًا — ثمّ انشره فيظهر للمعلّمين.'}
          action={
            <Button variant="primary"
              onClick={list.length ? () => { setQ(''); setFolder(''); setStatus('') } : create}>
              {list.length ? 'امسح الفلاتر' : 'أنشئ قالبًا'}
            </Button>}
        />
      ) : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead>
              <tr>
                <th>القالب</th>
                <th>المجلّد</th>
                <th>الحالة</th>
                <th>آخر تحديث</th>
                <th aria-label="إجراءات" />
              </tr>
            </thead>
            <tbody>
              {shown.map((t) => {
                const f = t.folder_id ? byId.get(t.folder_id) : null
                const empty = !(t.content_html || '').replace(/<[^>]*>/g, '').trim()
                return (
                  <tr key={t.id}>
                    <td data-label="القالب">
                      <button className="mdd-linkish" onClick={() => nav(`/admin/template/${t.id}`)}>
                        {t.title}
                      </button>
                      <span className="mdd-dim" style={{ display: 'block', fontSize: 11.5 }}>
                        {t.slug}{t.source_pages ? ` · ${t.source_pages} صفحة من الأصل` : ''}
                      </span>
                      {empty && <Badge tone="warn">فارغ</Badge>}
                    </td>
                    <td data-label="المجلّد">
                      {f ? (
                        <span className="mdd-row" style={{ gap: 6, color: f.accent }}>
                          <IcFolderFill size={14} /><span>{f.name}</span>
                        </span>
                      ) : <span className="mdd-dim">—</span>}
                    </td>
                    <td data-label="الحالة">
                      <Badge tone={t.status === 'published' ? 'success' : 'neutral'}>
                        {t.status === 'published' ? 'منشور' : 'مسوّدة'}
                      </Badge>
                    </td>
                    <td data-label="آخر تحديث" className="mdd-dim">{fmtShort(t.updated_at)}</td>
                    <td>
                      <div className="mdd-row" style={{ gap: 6, justifyContent: 'flex-end' }}>
                        <Button auto size="sm" variant="ghost" title="تحرير"
                          onClick={() => nav(`/admin/template/${t.id}`)}><IcEdit size={15} /></Button>
                        <Button auto size="sm" variant="ghost" title="مضاعفة"
                          onClick={() => duplicate(t)}><IcCopy size={15} /></Button>
                        <Button auto size="sm" variant={t.status === 'published' ? 'secondary' : 'primary'}
                          onClick={() => togglePublish(t)}>
                          {t.status === 'published' ? 'اسحب' : 'انشر'}
                        </Button>
                        <Button auto size="sm" variant="ghost" title="حذف"
                          onClick={() => setDel(t)}><IcTrash size={15} /></Button>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      {del && (
        <ConfirmModal
          open onClose={() => setDel(null)} onConfirm={remove} loading={busy}
          title="حذف القالب" danger confirmLabel="احذف"
          body={`سيُحذف «${del.title}» ولن يظهر للمعلّمين. الملفّات التي أنشأوها منه تبقى عندهم.`}
        />
      )}

      {importing && (
        <ImportTemplate
          folders={folders}
          onClose={() => setImporting(false)}
          onDone={(id) => { setImporting(false); reload(); if (id) nav(`/admin/template/${id}`) }}
        />
      )}
    </>
  )
}
