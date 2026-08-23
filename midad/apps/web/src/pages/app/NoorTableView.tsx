import React, { useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { supabase } from '../../lib/supabase'
import type { NoorTable } from '../../lib/types'
import { fmtBoth, fmtNum } from '../../lib/format'
import { buildXlsx, download, safeFileName } from '../../lib/export'
import {
  Button, Card, Checkbox, ConfirmModal, EmptyState, ErrorState, Field,
  Input, Modal, SearchInput, Skeleton,
} from '../../ui/kit'
import { IcBack, IcChevronDown, IcDownload, IcEdit, IcTrash } from '../../ui/icons'

const PAGE_SIZE = 100

export default function NoorTableView() {
  const { id = '' } = useParams()
  const nav = useNavigate()
  const { toast } = useApp()

  const [q, setQ] = useState('')
  const [page, setPage] = useState(0)
  const [hidden, setHidden] = useState<number[]>([])
  const [colsOpen, setColsOpen] = useState(false)
  const [sortCol, setSortCol] = useState<number | null>(null)
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc')
  const [renaming, setRenaming] = useState(false)
  const [renameText, setRenameText] = useState('')
  const [deleting, setDeleting] = useState(false)
  const [busy, setBusy] = useState(false)
  const dq = useDebounced(q)

  const { data: table, loading, error, reload, setData } = useAsync(async () => {
    const { data, error: e } = await supabase.from('noor_tables').select('*').eq('id', id).maybeSingle()
    if (e) throw new Error(e.message)
    return (data as NoorTable) || null
  }, [id])

  const columns = table?.columns || []
  const rows: string[][] = (table?.rows || []) as string[][]

  const shownCols = useMemo(
    () => columns.map((_, i) => i).filter((i) => !hidden.includes(i)),
    [columns, hidden])

  const filtered = useMemo(() => {
    const term = dq.trim()
    if (!term) return rows
    return rows.filter((r) => shownCols.some((i) => String(r[i] ?? '').includes(term)))
  }, [rows, dq, shownCols])

  const sorted = useMemo(() => {
    if (sortCol === null) return filtered
    const dir = sortDir === 'asc' ? 1 : -1
    return [...filtered].sort((a, b) => {
      const x = String(a[sortCol] ?? ''), y = String(b[sortCol] ?? '')
      const nx = Number(x), ny = Number(y)
      if (x !== '' && y !== '' && !isNaN(nx) && !isNaN(ny)) return (nx - ny) * dir
      return x.localeCompare(y, 'ar') * dir
    })
  }, [filtered, sortCol, sortDir])

  const pages = Math.max(1, Math.ceil(sorted.length / PAGE_SIZE))
  const current = Math.min(page, pages - 1)
  const slice = sorted.slice(current * PAGE_SIZE, current * PAGE_SIZE + PAGE_SIZE)

  function toggleSort(i: number) {
    if (sortCol === i) setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'))
    else { setSortCol(i); setSortDir('asc') }
    setPage(0)
  }

  function exportXlsx() {
    if (!table) return
    const heads = shownCols.map((i) => columns[i])
    const body = sorted.map((r) => shownCols.map((i) => String(r[i] ?? '')))
    download(buildXlsx(table.title, heads, body), `${safeFileName(table.title)}.xlsx`)
  }

  async function doRename() {
    if (!table) return
    const title = renameText.trim()
    if (title.length < 2) { toast('اكتب اسمًا واضحًا للجدول', 'danger'); return }
    setBusy(true)
    const { error: e } = await supabase.from('noor_tables').update({ title }).eq('id', table.id)
    setBusy(false)
    if (e) { toast('تعذّر تغيير الاسم', 'danger'); return }
    setData({ ...table, title })
    setRenaming(false); toast('غُيّر الاسم')
  }

  async function doDelete() {
    if (!table) return
    setBusy(true)
    const { error: e } = await supabase.from('noor_tables').delete().eq('id', table.id)
    setBusy(false)
    if (e) { toast('تعذّر حذف الجدول', 'danger'); return }
    toast('حُذف الجدول')
    nav('/app/noor')
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  if (loading) {
    return (
      <div className="mdd-col" style={{ gap: 'var(--mdd-s-5)' }}>
        <Skeleton h={26} w={260} />
        <Skeleton h={44} style={{ borderRadius: 12 }} />
        <Skeleton h={420} style={{ borderRadius: 16 }} />
      </div>
    )
  }

  if (!table) {
    return (
      <EmptyState
        title="لم نجد هذا الجدول"
        line="ربّما حُذف من حسابك. افتح قائمة جداول نور لترى ما هو محفوظٌ لديك."
        action={<Button variant="primary" onClick={() => nav('/app/noor')}>جداول نور</Button>}
      />
    )
  }

  return (
    <>
      <div className="mdd-row" style={{ marginBlockEnd: 'var(--mdd-s-3)' }}>
        <Button auto size="sm" variant="ghost" icon={<IcBack size={15} />} onClick={() => nav('/app/noor')}>
          كلّ الجداول
        </Button>
      </div>

      <div className="mdd-page-head">
        <div>
          <h1 className="mdd-page-head__title">{table.title}</h1>
          <p className="mdd-page-head__sub">
            نُزِّل في {fmtBoth(table.created_at)} · <span className="mdd-num">{fmtNum(rows.length)}</span> صفًّا ·{' '}
            <span className="mdd-num">{fmtNum(columns.length)}</span> عمودًا
          </p>
        </div>
        <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
          <Button auto variant="primary" icon={<IcDownload size={15} />} onClick={exportXlsx}>تصدير إكسل</Button>
          <Button auto variant="secondary" icon={<IcEdit size={15} />}
            onClick={() => { setRenameText(table.title); setRenaming(true) }}>إعادة تسمية</Button>
          <Button auto variant="danger" icon={<IcTrash size={15} />} onClick={() => setDeleting(true)}>حذف</Button>
        </div>
      </div>

      <div className="mdd-col" style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-4)' }}>
        <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
          <div style={{ flex: 1, minWidth: 220 }}>
            <SearchInput value={q} onChange={(v) => { setQ(v); setPage(0) }} placeholder="ابحث في كلّ الخلايا" />
          </div>
          <Button auto variant="secondary" icon={<IcChevronDown size={14} />} onClick={() => setColsOpen((o) => !o)}
            aria-expanded={colsOpen}>
            الأعمدة (<span className="mdd-num">{shownCols.length}</span>/<span className="mdd-num">{columns.length}</span>)
          </Button>
          <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)', alignSelf: 'center' }}>
            <span className="mdd-num">{fmtNum(sorted.length)}</span> صفًّا معروضًا
          </span>
        </div>

        {colsOpen && (
          <Card className="mdd-col" style={{ gap: 10 }}>
            <div className="mdd-row mdd-row--between">
              <span style={{ fontSize: 13, fontWeight: 700 }}>إظهار وإخفاء الأعمدة</span>
              <Button auto size="sm" variant="secondary" onClick={() => setHidden([])}>أظهر الكلّ</Button>
            </div>
            <div className="mdd-row mdd-row--wrap" style={{ gap: 14 }}>
              {columns.map((c, i) => (
                <Checkbox key={i} checked={!hidden.includes(i)}
                  onChange={(v) => setHidden((cur) => (v ? cur.filter((x) => x !== i) : [...cur, i]))}>
                  {c || `عمود ${i + 1}`}
                </Checkbox>
              ))}
            </div>
          </Card>
        )}
      </div>

      {sorted.length === 0 ? (
        <Card className="mdd-col" style={{ alignItems: 'center', gap: 10, padding: 'var(--mdd-s-7)' }}>
          <h3 style={{ fontSize: 16 }}>لا صفّ يطابق «{q.trim()}»</h3>
          <p className="mdd-prose" style={{ fontSize: 13 }}>البحث يمرّ على الأعمدة الظاهرة وحدها — أظهر عمودًا مخفيًّا أو جرّب كلمةً أقصر.</p>
          <Button auto variant="primary" onClick={() => setQ('')}>امسح البحث</Button>
        </Card>
      ) : (
        <>
          <div className="mdd-table-wrap" style={{ overflow: 'auto', maxHeight: '68vh' }}>
            <table className="mdd-table mdd-table--zebra">
              <thead>
                <tr>
                  <th style={{ width: 56 }}>م</th>
                  {shownCols.map((i) => (
                    <th key={i} className="mdd-table__sortable" onClick={() => toggleSort(i)}
                      aria-sort={sortCol === i ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                      <span className="mdd-row" style={{ gap: 5 }}>
                        {columns[i] || `عمود ${i + 1}`}
                        {sortCol === i && (
                          <span style={{ color: 'var(--mdd-accent)', display: 'inline-flex', transform: sortDir === 'asc' ? 'rotate(180deg)' : undefined }}>
                            <IcChevronDown size={12} />
                          </span>
                        )}
                      </span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {slice.map((r, ri) => (
                  <tr key={current * PAGE_SIZE + ri}>
                    <td data-label="م"><span className="mdd-num" style={{ color: 'var(--mdd-text-3)' }}>{current * PAGE_SIZE + ri + 1}</span></td>
                    {shownCols.map((i) => (
                      <td key={i} data-label={columns[i] || `عمود ${i + 1}`}>
                        <Highlight text={String(r[i] ?? '')} term={dq.trim()} />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {pages > 1 && (
            <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 10, marginBlockStart: 'var(--mdd-s-4)' }}>
              <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>
                صفحة <span className="mdd-num">{current + 1}</span> من <span className="mdd-num">{pages}</span>
              </span>
              <div className="mdd-row" style={{ gap: 8 }}>
                <Button auto size="sm" variant="secondary" disabled={current === 0} onClick={() => setPage(current - 1)}>السابقة</Button>
                <Button auto size="sm" variant="secondary" disabled={current >= pages - 1} onClick={() => setPage(current + 1)}>التالية</Button>
              </div>
            </div>
          )}
        </>
      )}

      <Modal open={renaming} onClose={() => setRenaming(false)} title="إعادة تسمية الجدول"
        footer={
          <>
            <Button variant="secondary" onClick={() => setRenaming(false)} block>إلغاء</Button>
            <Button variant="primary" onClick={doRename} loading={busy} block>احفظ الاسم</Button>
          </>
        }>
        <Field label="اسم الجدول" help="الاسم في مِداد وحده — لا يتغيّر شيءٌ في نظام نور.">
          <Input value={renameText} onChange={(e) => setRenameText(e.target.value)} autoFocus />
        </Field>
      </Modal>

      <ConfirmModal
        open={deleting} onClose={() => setDeleting(false)} onConfirm={doDelete} loading={busy} danger
        title="حذف الجدول" confirmLabel="احذف"
        body={`سيُحذف «${table.title}» من مِداد نهائيًّا. نظام نور لا يتأثّر، وتستطيع تنزيله مرّةً أخرى بالإضافة.`}
      />
    </>
  )
}

function Highlight({ text, term }: { text: string; term: string }) {
  if (!term || !text.includes(term)) return <>{text}</>
  const parts = text.split(term)
  return (
    <>
      {parts.map((p, i) => (
        <React.Fragment key={i}>
          {p}
          {i < parts.length - 1 && (
            <mark style={{ background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)', borderRadius: 4, padding: '0 2px' }}>{term}</mark>
          )}
        </React.Fragment>
      ))}
    </>
  )
}
