import React, { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { fmtNum, fmtShort } from '../../lib/format'
import type { Template } from '../../lib/types'
import {
  Badge, Button, Card, ConfirmModal, EmptyState, ErrorState, PageHead,
  SearchInput, Select, SkeletonRows,
} from '../../ui/kit'
import { IcPlus, IcEdit, IcCopy, IcTrash } from '../../ui/icons'
import TemplateThumb from '../app/TemplateThumb'

export default function Templates() {
  const { roles, toast } = useApp()
  const nav = useNavigate()
  const [q, setQ] = useState('')
  const [cat, setCat] = useState('')
  const [status, setStatus] = useState('')
  const [del, setDel] = useState<Template | null>(null)
  const [busy, setBusy] = useState(false)
  const dq = useDebounced(q)

  const { data, loading, error, reload } = useAsync(async () => {
    const { data: t, error: e } = await supabase.from('templates').select('*').order('sort').order('title')
    if (e) throw new Error(e.message)
    return (t || []) as Template[]
  }, [])

  const list = data || []
  const published = list.filter((t) => t.status === 'published').length
  const drafts = list.filter((t) => t.status === 'draft').length

  const shown = useMemo(() => {
    let out = list
    const term = dq.trim()
    if (term) out = out.filter((t) => t.title.includes(term) || t.slug.includes(term.toLowerCase()))
    if (cat) out = out.filter((t) => t.category_key === cat)
    if (status) out = out.filter((t) => t.status === status)
    return out
  }, [list, dq, cat, status])

  const create = async () => {
    setBusy(true)
    const slug = `new-template-${Date.now().toString(36)}`
    const { data: row, error: e } = await supabase.from('templates').insert({
      slug, title: 'قالب جديد', category_key: 'general',
      description: '', body: '<h2>القسم الأوّل</h2>\n<p>اكتب المتن هنا.</p>', fields: [],
      outputs: ['pdf', 'docx'], status: 'draft',
    }).select().single()
    setBusy(false)
    if (e) { toast(e.message, 'danger'); return }
    nav(`/admin/template/${(row as any).id}`)
  }

  const duplicate = async (t: Template) => {
    const { data: row, error: e } = await supabase.from('templates').insert({
      slug: `${t.slug}-copy-${Date.now().toString(36)}`,
      title: `نسخة من ${t.title}`, category_key: t.category_key, description: t.description,
      body: t.body, fields: t.fields, outputs: t.outputs,
      estimated_minutes: t.estimated_minutes, status: 'draft',
    }).select().single()
    if (e) { toast(e.message, 'danger'); return }
    toast('أُنشئت نسخة مسوّدة'); nav(`/admin/template/${(row as any).id}`)
  }

  const togglePublish = async (t: Template) => {
    const next = t.status === 'published' ? 'draft' : 'published'
    const { error: e } = await supabase.from('templates').update({ status: next }).eq('id', t.id)
    if (e) { toast(e.message, 'danger'); return }
    toast(next === 'published' ? 'نُشر القالب' : 'سُحب القالب'); reload()
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
        sub={loading ? 'جارٍ التحميل…' : `${fmtNum(published)} منشورًا · ${fmtNum(drafts)} مسوّدة`}
        actions={<Button auto variant="primary" icon={<IcPlus size={15} />} loading={busy} onClick={create}>قالب جديد</Button>}
      />

      <Card className="mdd-col" style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-4)' }}>
        <SearchInput value={q} onChange={setQ} placeholder="ابحث بالعنوان أو المفتاح" />
        <div className="mdd-grid mdd-grid--2" style={{ gap: 10 }}>
          <Select value={cat} onChange={(e) => setCat(e.target.value)} aria-label="الفئة">
            <option value="">كلّ الفئات</option>
            {roles.map((r) => <option key={r.key} value={r.key}>{r.name_ar}</option>)}
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
          title={list.length ? 'لا نتيجة' : 'المكتبة فارغة'}
          line={list.length ? 'جرّب كلمةً أقصر أو امسح الفلاتر.' : 'ابدأ بقالبٍ جديد — الحقول والمتن والمعاينة في شاشة واحدة.'}
          action={<Button variant="primary" onClick={list.length ? () => { setQ(''); setCat(''); setStatus('') } : create}>
            {list.length ? 'امسح الفلاتر' : 'أنشئ قالبًا'}
          </Button>}
        />
      ) : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead>
              <tr>
                <th style={{ width: 76 }}>المعاينة</th><th>العنوان</th><th>الفئة</th><th>الحقول</th>
                <th>الإصدار</th><th>الاستعمال</th><th>الحالة</th><th aria-label="أفعال" />
              </tr>
            </thead>
            <tbody>
              {shown.map((t) => (
                <tr key={t.id}>
                  <td data-label="المعاينة"><div style={{ width: 64 }}><TemplateThumb template={t} height={70} /></div></td>
                  <td data-label="العنوان">
                    <div style={{ fontWeight: 700 }}>{t.title}</div>
                    <div className="mdd-mono" style={{ fontSize: 10.5, color: 'var(--mdd-text-3)' }}>{t.slug}</div>
                  </td>
                  <td data-label="الفئة">{roles.find((r) => r.key === t.category_key)?.name_ar || t.category_key}</td>
                  <td data-label="الحقول"><span className="mdd-num">{t.fields?.length || 0}</span></td>
                  <td data-label="الإصدار"><span className="mdd-num">{t.version}</span></td>
                  <td data-label="الاستعمال"><span className="mdd-num">{fmtNum(t.usage_count)}</span></td>
                  <td data-label="الحالة">
                    <Badge tone={t.status === 'published' ? 'success' : 'neutral'} dot>
                      {t.status === 'published' ? 'منشور' : 'مسوّدة'}
                    </Badge>
                  </td>
                  <td>
                    <div className="mdd-row mdd-row--wrap" style={{ gap: 6, justifyContent: 'flex-end' }}>
                      <Button size="sm" auto icon={<IcEdit size={13} />} onClick={() => nav(`/admin/template/${t.id}`)}>تعديل</Button>
                      <Button size="sm" auto icon={<IcCopy size={13} />} onClick={() => duplicate(t)}>نسخ</Button>
                      <Button size="sm" auto variant="soft" onClick={() => togglePublish(t)}>
                        {t.status === 'published' ? 'سحب' : 'نشر'}
                      </Button>
                      <Button size="sm" auto variant="danger" icon={<IcTrash size={13} />} onClick={() => setDel(t)}>حذف</Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <ConfirmModal
        open={!!del} onClose={() => setDel(null)} onConfirm={remove} loading={busy} danger confirmLabel="احذف القالب"
        title="حذف قالب؟"
        body={`يُحذف «${del?.title}» من المكتبة نهائيًّا. الملفّات التي أنشأها المشتركون منه تبقى محفوظة، لكنّها تفقد ارتباطها بالقالب.`}
      />
    </>
  )
}
