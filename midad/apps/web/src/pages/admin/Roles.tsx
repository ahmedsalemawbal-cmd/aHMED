import React, { useState } from 'react'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fmtNum } from '../../lib/format'
import type { Role } from '../../lib/types'
import {
  Alert, Badge, Button, ConfirmModal, ErrorState, Field, Input, Modal,
  PageHead, SkeletonRows,
} from '../../ui/kit'
import { IcPlus, IcEdit, IcTrash } from '../../ui/icons'

export default function Roles() {
  const { toast, refresh } = useApp()
  const [editing, setEditing] = useState<any | null>(null)
  const [del, setDel] = useState<Role | null>(null)
  const [busy, setBusy] = useState(false)

  const { data, loading, error, reload } = useAsync(async () => {
    const [roles, tpls, profs] = await Promise.all([
      supabase.from('roles').select('*').order('sort'),
      supabase.from('templates').select('category_key'),
      supabase.from('profiles').select('role_key'),
    ])
    if (roles.error) throw new Error(roles.error.message)
    const t = new Map<string, number>(); const u = new Map<string, number>()
    for (const x of (tpls.data || []) as any[]) t.set(x.category_key, (t.get(x.category_key) || 0) + 1)
    for (const x of (profs.data || []) as any[]) u.set(x.role_key, (u.get(x.role_key) || 0) + 1)
    return { roles: (roles.data || []) as Role[], tplCount: t, userCount: u }
  }, [])

  const save = async () => {
    if (!editing?.name_ar?.trim() || !editing?.key?.trim()) { toast('الاسم والمفتاح مطلوبان', 'danger'); return }
    if (!/^[a-z_]+$/.test(editing.key)) { toast('المفتاح بحروف إنجليزية صغيرة وشرطة سفلية فقط', 'danger'); return }
    setBusy(true)
    const payload = {
      key: editing.key.trim(), name_ar: editing.name_ar.trim(),
      blurb_ar: editing.blurb_ar || null, sort: Number(editing.sort) || 0,
    }
    const res = editing.id
      ? await supabase.from('roles').update(payload).eq('id', editing.id)
      : await supabase.from('roles').insert(payload)
    setBusy(false)
    if (res.error) { toast(res.error.message, 'danger'); return }
    toast('حُفظ الدور'); setEditing(null); reload(); refresh()
  }

  const remove = async () => {
    if (!del) return
    setBusy(true)
    const { error: e } = await supabase.from('roles').delete().eq('id', del.id)
    setBusy(false)
    if (e) { toast('تعذّر الحذف — الدور مستعمل', 'danger'); return }
    toast('حُذف الدور'); setDel(null); reload(); refresh()
  }

  const inUse = (r: Role) => (data?.tplCount.get(r.key) || 0) + (data?.userCount.get(r.key) || 0)

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="الأدوار" sub="الدور يحدّد ما يراه المشترك من القوالب — لا ما يقدر عليه في النظام."
        actions={<Button auto variant="primary" icon={<IcPlus size={15} />}
          onClick={() => setEditing({ key: '', name_ar: '', blurb_ar: '', sort: 10 })}>دور جديد</Button>}
      />

      {loading ? <SkeletonRows n={5} /> : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead>
              <tr><th>الاسم</th><th>المفتاح</th><th>الترتيب</th><th>القوالب</th><th>المستخدمون</th><th aria-label="أفعال" /></tr>
            </thead>
            <tbody>
              {(data?.roles || []).map((r) => (
                <tr key={r.id}>
                  <td data-label="الاسم">
                    <div style={{ fontWeight: 700 }}>{r.name_ar} {r.is_system && <Badge>نظاميّ</Badge>}</div>
                    {r.blurb_ar && <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{r.blurb_ar}</div>}
                  </td>
                  <td data-label="المفتاح"><span className="mdd-mono">{r.key}</span></td>
                  <td data-label="الترتيب"><span className="mdd-num">{r.sort}</span></td>
                  <td data-label="القوالب"><span className="mdd-num">{fmtNum(data?.tplCount.get(r.key) || 0)}</span></td>
                  <td data-label="المستخدمون"><span className="mdd-num">{fmtNum(data?.userCount.get(r.key) || 0)}</span></td>
                  <td>
                    <div className="mdd-row" style={{ gap: 6, justifyContent: 'flex-end' }}>
                      <Button size="sm" auto icon={<IcEdit size={13} />}
                        onClick={() => setEditing({ ...r })}>تعديل</Button>
                      <Button size="sm" auto variant="danger" icon={<IcTrash size={13} />}
                        disabled={inUse(r) > 0} onClick={() => setDel(r)}>حذف</Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={!!editing} onClose={() => setEditing(null)} title={editing?.id ? 'تعديل دور' : 'دور جديد'}
        footer={<>
          <Button variant="secondary" block onClick={() => setEditing(null)}>إلغاء</Button>
          <Button variant="primary" block loading={busy} onClick={save}>احفظ</Button>
        </>}>
        {editing && (
          <>
            <Field label="الاسم المعروض">
              <Input value={editing.name_ar} onChange={(e) => setEditing({ ...editing, name_ar: e.target.value })} placeholder="مشرف المختبر" />
            </Field>
            <Field label="المفتاح (إنجليزي)" help="حروف صغيرة وشرطة سفلية فقط — ولا يُغيَّر بعد استعماله في القوالب.">
              <Input ltr value={editing.key} onChange={(e) => setEditing({ ...editing, key: e.target.value })} placeholder="lab_supervisor" />
            </Field>
            <Field label="وصف مختصر">
              <Input value={editing.blurb_ar || ''} onChange={(e) => setEditing({ ...editing, blurb_ar: e.target.value })} />
            </Field>
            <Field label="الترتيب">
              <Input ltr type="number" value={editing.sort} onChange={(e) => setEditing({ ...editing, sort: e.target.value })} />
            </Field>
          </>
        )}
      </Modal>

      <ConfirmModal
        open={!!del} onClose={() => setDel(null)} onConfirm={remove} loading={busy} danger confirmLabel="احذف"
        title="حذف الدور؟"
        body={del && inUse(del) > 0
          ? `لا يمكن الحذف: ${data?.userCount.get(del.key) || 0} مستخدمًا و${data?.tplCount.get(del.key) || 0} قالبًا على هذا الدور.`
          : 'يُحذف الدور نهائيًّا. ولا يمكن التراجع.'}
      />
    </>
  )
}
