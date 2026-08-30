import React, { useMemo, useState } from 'react'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { supabase } from '../../lib/supabase'
import {
  SLOT_TIMES, STUDENT_STATUS_AR, WEEKDAYS,
  fetchClasses, fetchPeriods, fetchStudents,
} from '../../lib/classroom'
import { fetchTeam } from '../../lib/data'
import type { ClassRow, Period, Profile, Student } from '../../lib/types'
import {
  Alert, Badge, Button, Card, ConfirmModal, EmptyState, ErrorState, Field,
  Input, Modal, PageHead, SearchInput, Select, SkeletonRows, Tabs,
} from '../../ui/kit'
import { IcPlus, IcTrash, IcEdit, IcTable, IcTeam } from '../../ui/icons'
import { fmtNum } from '../../lib/format'

type Tab = 'classes' | 'students' | 'timetable'

export default function Classroom() {
  const { subscriber, profile, toast } = useApp()
  const [tab, setTab] = useState<Tab>('classes')
  const sid = subscriber?.id

  const { data, loading, error, reload } = useAsync(async () => {
    if (!sid) return null
    const [classes, students, periods, team] = await Promise.all([
      fetchClasses(sid), fetchStudents(sid), fetchPeriods(sid), fetchTeam(sid),
    ])
    return { classes, students, periods, team }
  }, [sid])

  if (error) return <ErrorState onRetry={reload} message={error} />

  const classes = data?.classes || []
  const students = data?.students || []
  const periods = data?.periods || []
  const team = data?.team || []

  return (
    <>
      <PageHead
        title="الفصول والطلاب"
        sub={loading ? 'جارٍ التحميل…'
          : `${fmtNum(classes.length)} فصلًا · ${fmtNum(students.length)} طالبًا · ${fmtNum(periods.length)} حصّة`}
      />

      <div style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
        <Tabs value={tab} onChange={setTab} tabs={[
          { key: 'classes', label: 'الفصول', count: classes.length },
          { key: 'students', label: 'الطلاب', count: students.length },
          { key: 'timetable', label: 'الجدول', count: periods.length },
        ]} />
      </div>

      {loading ? <SkeletonRows n={6} /> : (
        <>
          {tab === 'classes' && <Classes classes={classes} students={students} sid={sid!} reload={reload} toast={toast} />}
          {tab === 'students' && <Students classes={classes} students={students} sid={sid!} reload={reload} toast={toast} />}
          {tab === 'timetable' && <Timetable periods={periods} classes={classes} team={team} sid={sid!} me={profile} reload={reload} toast={toast} />}
        </>
      )}
    </>
  )
}

/* ============================ الفصول ============================ */
function Classes({ classes, students, sid, reload, toast }: {
  classes: ClassRow[]; students: Student[]; sid: string; reload: () => void; toast: (m: string, k?: 'ok' | 'danger') => void
}) {
  const [editing, setEditing] = useState<any | null>(null)
  const [del, setDel] = useState<ClassRow | null>(null)
  const [busy, setBusy] = useState(false)

  const countOf = (id: string) => students.filter((s) => s.class_id === id && s.status === 'active').length

  const save = async () => {
    if (!editing?.name?.trim()) { toast('اكتب اسم الفصل', 'danger'); return }
    setBusy(true)
    const payload = {
      subscriber_id: sid, name: editing.name.trim(),
      stage: editing.stage || null, room: editing.room || null,
      sort: Number(editing.sort) || 0,
    }
    const res = editing.id
      ? await supabase.from('classes').update(payload).eq('id', editing.id)
      : await supabase.from('classes').insert(payload)
    setBusy(false)
    if (res.error) { toast(res.error.message.includes('duplicate') ? 'هذا الفصل موجود' : res.error.message, 'danger'); return }
    toast(editing.id ? 'حُفظ الفصل' : 'أُضيف الفصل'); setEditing(null); reload()
  }

  const remove = async () => {
    if (!del) return
    setBusy(true)
    const { error } = await supabase.from('classes').delete().eq('id', del.id)
    setBusy(false)
    if (error) { toast('تعذّر الحذف', 'danger'); return }
    toast('حُذف الفصل'); setDel(null); reload()
  }

  return (
    <>
      <div className="mdd-row mdd-row--between" style={{ marginBlockEnd: 'var(--mdd-s-4)' }}>
        <span className="mdd-muted" style={{ fontSize: 13 }}>الفصل هو الشعبة التي يُرصد حضورها.</span>
        <Button auto variant="primary" icon={<IcPlus size={15} />}
          onClick={() => setEditing({ name: '', stage: 'متوسّط', room: '', sort: classes.length + 1 })}>
          فصل جديد
        </Button>
      </div>

      {classes.length === 0 ? (
        <EmptyState art={<IcTable size={64} />} title="لا فصول بعد"
          line="أضف فصولك أوّلًا، ثمّ طلابها، ثمّ ابنِ الجدول — وبعدها يرصد المعلّم من جوّاله."
          action={<Button variant="primary" onClick={() => setEditing({ name: '', stage: 'متوسّط', room: '', sort: 1 })}>أضف أوّل فصل</Button>} />
      ) : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead><tr><th>الفصل</th><th>المرحلة</th><th>القاعة</th><th>الطلاب</th><th aria-label="أفعال" /></tr></thead>
            <tbody>
              {classes.map((k) => (
                <tr key={k.id}>
                  <td data-label="الفصل"><strong>{k.name}</strong></td>
                  <td data-label="المرحلة">{k.stage || '—'}</td>
                  <td data-label="القاعة">{k.room || '—'}</td>
                  <td data-label="الطلاب"><span className="mdd-num">{countOf(k.id)}</span></td>
                  <td>
                    <div className="mdd-row" style={{ gap: 6, justifyContent: 'flex-end' }}>
                      <Button size="sm" auto icon={<IcEdit size={13} />} onClick={() => setEditing({ ...k })}>تعديل</Button>
                      <Button size="sm" auto variant="danger" icon={<IcTrash size={13} />} onClick={() => setDel(k)}>حذف</Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={!!editing} onClose={() => setEditing(null)} title={editing?.id ? 'تعديل فصل' : 'فصل جديد'}
        footer={<>
          <Button variant="secondary" block onClick={() => setEditing(null)}>إلغاء</Button>
          <Button variant="primary" block loading={busy} onClick={save}>احفظ</Button>
        </>}>
        {editing && (
          <>
            <Field label="اسم الفصل" help="كما يُنطق في المدرسة — مثال: الثالث/أ">
              <Input value={editing.name} onChange={(e) => setEditing({ ...editing, name: e.target.value })} placeholder="الثالث/أ" />
            </Field>
            <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
              <Field label="المرحلة">
                <Select value={editing.stage || ''} onChange={(e) => setEditing({ ...editing, stage: e.target.value })}>
                  <option value="">—</option>
                  <option>ابتدائي</option><option>متوسّط</option><option>ثانوي</option>
                </Select>
              </Field>
              <Field label="القاعة">
                <Input value={editing.room || ''} onChange={(e) => setEditing({ ...editing, room: e.target.value })} placeholder="فصل 12" />
              </Field>
            </div>
          </>
        )}
      </Modal>

      <ConfirmModal open={!!del} onClose={() => setDel(null)} onConfirm={remove} loading={busy} danger
        confirmLabel="احذف الفصل" title="حذف فصل؟"
        body={`يُحذف «${del?.name}» وكلّ حصصه. وطلابه يبقون محفوظين بلا فصل، تُعيد إسنادهم متى شئت.`} />
    </>
  )
}

/* ============================ الطلاب ============================ */
function Students({ classes, students, sid, reload, toast }: {
  classes: ClassRow[]; students: Student[]; sid: string; reload: () => void; toast: (m: string, k?: 'ok' | 'danger') => void
}) {
  const [q, setQ] = useState('')
  const [filter, setFilter] = useState('')
  const [editing, setEditing] = useState<any | null>(null)
  const [bulk, setBulk] = useState<{ classId: string; text: string } | null>(null)
  const [del, setDel] = useState<Student | null>(null)
  const [busy, setBusy] = useState(false)

  const shown = useMemo(() => {
    let list = students
    if (filter) list = list.filter((s) => s.class_id === filter)
    const t = q.trim()
    if (t) list = list.filter((s) => s.full_name.includes(t) || (s.national_id || '').includes(t))
    return list
  }, [students, q, filter])

  const className = (id: string | null) => classes.find((k) => k.id === id)?.name || '— بلا فصل —'

  const save = async () => {
    if (!editing?.full_name?.trim()) { toast('اكتب اسم الطالب', 'danger'); return }
    setBusy(true)
    const payload = {
      subscriber_id: sid, class_id: editing.class_id || null,
      full_name: editing.full_name.trim(), national_id: editing.national_id || null,
      guardian_phone: editing.guardian_phone || null,
      status: editing.status || 'active', sort: Number(editing.sort) || 0,
    }
    const res = editing.id
      ? await supabase.from('students').update(payload).eq('id', editing.id)
      : await supabase.from('students').insert(payload)
    setBusy(false)
    if (res.error) { toast(res.error.message, 'danger'); return }
    toast('حُفظ الطالب'); setEditing(null); reload()
  }

  const saveBulk = async () => {
    if (!bulk?.classId) { toast('اختر الفصل', 'danger'); return }
    const names = bulk.text.split('\n').map((x) => x.trim()).filter(Boolean)
    if (!names.length) { toast('الصق الأسماء أوّلًا', 'danger'); return }
    setBusy(true)
    const base = students.filter((s) => s.class_id === bulk.classId).length
    const { error } = await supabase.from('students').insert(
      names.map((n, i) => ({
        subscriber_id: sid, class_id: bulk.classId, full_name: n,
        status: 'active' as const, sort: base + i + 1,
      })),
    )
    setBusy(false)
    if (error) { toast(error.message, 'danger'); return }
    toast(`أُضيف ${names.length} طالبًا`); setBulk(null); reload()
  }

  const remove = async () => {
    if (!del) return
    setBusy(true)
    const { error } = await supabase.from('students').delete().eq('id', del.id)
    setBusy(false)
    if (error) { toast('تعذّر الحذف', 'danger'); return }
    toast('حُذف الطالب'); setDel(null); reload()
  }

  return (
    <>
      <Card className="mdd-col" style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-4)' }}>
        <SearchInput value={q} onChange={setQ} placeholder="ابحث بالاسم أو رقم الهوية" />
        <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
          <Select value={filter} onChange={(e) => setFilter(e.target.value)} aria-label="الفصل">
            <option value="">كلّ الفصول</option>
            {classes.map((k) => <option key={k.id} value={k.id}>{k.name}</option>)}
          </Select>
          <div className="mdd-spacer" />
          <Button auto icon={<IcPlus size={15} />}
            onClick={() => setBulk({ classId: filter || classes[0]?.id || '', text: '' })}
            disabled={!classes.length}>لصق قائمة أسماء</Button>
          <Button auto variant="primary" icon={<IcPlus size={15} />}
            onClick={() => setEditing({ full_name: '', class_id: filter || classes[0]?.id || '', status: 'active' })}
            disabled={!classes.length}>طالب جديد</Button>
        </div>
      </Card>

      {!classes.length ? (
        <Alert tone="warn">أضف فصلًا أوّلًا من تبويب «الفصول»، ثمّ أضف طلابه.</Alert>
      ) : shown.length === 0 ? (
        <EmptyState art={<IcTeam size={64} />} title={q || filter ? 'لا نتيجة' : 'لا طلاب بعد'}
          line={q || filter ? 'جرّب بحثًا أوسع أو امسح الفلتر.' : 'أسرع طريقة: انسخ أسماء الفصل من نور والصقها دفعةً واحدة.'}
          action={<Button variant="primary" onClick={() => setBulk({ classId: filter || classes[0]?.id || '', text: '' })}>لصق قائمة أسماء</Button>} />
      ) : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead><tr><th>م</th><th>الطالب</th><th>الفصل</th><th>رقم الهوية</th><th>الحالة</th><th aria-label="أفعال" /></tr></thead>
            <tbody>
              {shown.map((s, i) => (
                <tr key={s.id}>
                  <td data-label="م"><span className="mdd-num">{i + 1}</span></td>
                  <td data-label="الطالب"><strong>{s.full_name}</strong></td>
                  <td data-label="الفصل">{className(s.class_id)}</td>
                  <td data-label="رقم الهوية"><span className="mdd-mono">{s.national_id || '—'}</span></td>
                  <td data-label="الحالة">
                    <Badge tone={s.status === 'active' ? 'success' : 'neutral'} dot>{STUDENT_STATUS_AR[s.status]}</Badge>
                  </td>
                  <td>
                    <div className="mdd-row" style={{ gap: 6, justifyContent: 'flex-end' }}>
                      <Button size="sm" auto icon={<IcEdit size={13} />} onClick={() => setEditing({ ...s })}>تعديل</Button>
                      <Button size="sm" auto variant="danger" icon={<IcTrash size={13} />} onClick={() => setDel(s)}>حذف</Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={!!editing} onClose={() => setEditing(null)} title={editing?.id ? 'تعديل طالب' : 'طالب جديد'}
        footer={<>
          <Button variant="secondary" block onClick={() => setEditing(null)}>إلغاء</Button>
          <Button variant="primary" block loading={busy} onClick={save}>احفظ</Button>
        </>}>
        {editing && (
          <>
            <Field label="اسم الطالب">
              <Input value={editing.full_name} onChange={(e) => setEditing({ ...editing, full_name: e.target.value })} placeholder="أحمد سالم الغامدي" />
            </Field>
            <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
              <Field label="الفصل">
                <Select value={editing.class_id || ''} onChange={(e) => setEditing({ ...editing, class_id: e.target.value })}>
                  <option value="">— بلا فصل —</option>
                  {classes.map((k) => <option key={k.id} value={k.id}>{k.name}</option>)}
                </Select>
              </Field>
              <Field label="الحالة">
                <Select value={editing.status} onChange={(e) => setEditing({ ...editing, status: e.target.value })}>
                  {Object.entries(STUDENT_STATUS_AR).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </Select>
              </Field>
              <Field label="رقم الهوية">
                <Input ltr value={editing.national_id || ''} onChange={(e) => setEditing({ ...editing, national_id: e.target.value })} />
              </Field>
              <Field label="جوّال ولي الأمر">
                <Input ltr value={editing.guardian_phone || ''} onChange={(e) => setEditing({ ...editing, guardian_phone: e.target.value })} placeholder="05xxxxxxxx" />
              </Field>
            </div>
          </>
        )}
      </Modal>

      <Modal open={!!bulk} onClose={() => setBulk(null)} title="لصق قائمة أسماء" wide
        footer={<>
          <Button variant="secondary" block onClick={() => setBulk(null)}>إلغاء</Button>
          <Button variant="primary" block loading={busy} onClick={saveBulk}>أضف الكلّ</Button>
        </>}>
        {bulk && (
          <>
            <Field label="الفصل">
              <Select value={bulk.classId} onChange={(e) => setBulk({ ...bulk, classId: e.target.value })}>
                {classes.map((k) => <option key={k.id} value={k.id}>{k.name}</option>)}
              </Select>
            </Field>
            <Field label="الأسماء — اسمٌ في كلّ سطر"
              help="انسخ عمود الأسماء من كشف نور والصقه هنا. تُضاف بالترتيب نفسه.">
              <textarea className="mdd-textarea" rows={12} value={bulk.text}
                onChange={(e) => setBulk({ ...bulk, text: e.target.value })}
                placeholder={'أحمد سالم الغامدي\nمحمد فهد الشهري\nعبدالله ناصر الدوسري'} />
            </Field>
            <p className="mdd-field__help">
              سيُضاف <strong className="mdd-num">{bulk.text.split('\n').filter((x) => x.trim()).length}</strong> طالبًا.
            </p>
          </>
        )}
      </Modal>

      <ConfirmModal open={!!del} onClose={() => setDel(null)} onConfirm={remove} loading={busy} danger
        confirmLabel="احذف" title="حذف طالب؟"
        body={`يُحذف «${del?.full_name}» وسجلّ حضوره كلّه. ولا يمكن التراجع.`} />
    </>
  )
}

/* ============================ الجدول ============================ */
function Timetable({ periods, classes, team, sid, me, reload, toast }: {
  periods: Period[]; classes: ClassRow[]; team: Profile[]; sid: string
  me: Profile | null; reload: () => void; toast: (m: string, k?: 'ok' | 'danger') => void
}) {
  const [day, setDay] = useState(0)
  const [editing, setEditing] = useState<any | null>(null)
  const [del, setDel] = useState<Period | null>(null)
  const [busy, setBusy] = useState(false)

  const ofDay = periods.filter((p) => p.weekday === day).sort((a, b) => a.slot - b.slot)
  const className = (id: string | null) => classes.find((k) => k.id === id)?.name || '—'
  const teacherName = (id: string | null) => team.find((t) => t.id === id)?.full_name || '— بلا معلّم —'

  const save = async () => {
    if (!editing?.subject?.trim()) { toast('اكتب المادّة', 'danger'); return }
    if (!editing.class_id) { toast('اختر الفصل', 'danger'); return }
    setBusy(true)
    const [s, e] = SLOT_TIMES[Number(editing.slot)] || ['07:00', '07:45']
    const payload = {
      subscriber_id: sid, teacher_id: editing.teacher_id || null, class_id: editing.class_id,
      subject: editing.subject.trim(), weekday: Number(editing.weekday), slot: Number(editing.slot),
      starts_at: editing.starts_at || s, ends_at: editing.ends_at || e,
      room: editing.room || classes.find((k) => k.id === editing.class_id)?.room || null,
    }
    const res = editing.id
      ? await supabase.from('periods').update(payload).eq('id', editing.id)
      : await supabase.from('periods').insert(payload)
    setBusy(false)
    if (res.error) {
      toast(res.error.message.includes('duplicate') || res.error.message.includes('periods_no_clash')
        ? 'المعلّم عنده حصّة أخرى في هذا اليوم والوقت' : res.error.message, 'danger')
      return
    }
    toast('حُفظت الحصّة'); setEditing(null); reload()
  }

  const remove = async () => {
    if (!del) return
    setBusy(true)
    const { error } = await supabase.from('periods').delete().eq('id', del.id)
    setBusy(false)
    if (error) { toast('تعذّر الحذف', 'danger'); return }
    toast('حُذفت الحصّة'); setDel(null); reload()
  }

  const openNew = () => setEditing({
    weekday: day, slot: (ofDay.length ? ofDay[ofDay.length - 1].slot : 0) + 1 || 1,
    subject: '', class_id: classes[0]?.id || '', teacher_id: me?.id || '',
    starts_at: '', ends_at: '', room: '',
  })

  return (
    <>
      <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-4)' }}>
        <Tabs value={String(day)} onChange={(v) => setDay(Number(v))}
          tabs={WEEKDAYS.map((w, i) => ({
            key: String(i), label: w, count: periods.filter((p) => p.weekday === i).length,
          }))} />
        <Button auto variant="primary" icon={<IcPlus size={15} />} onClick={openNew} disabled={!classes.length}>
          حصّة جديدة
        </Button>
      </div>

      {!classes.length ? (
        <Alert tone="warn">أضف فصلًا أوّلًا، ثمّ ابنِ الجدول.</Alert>
      ) : ofDay.length === 0 ? (
        <EmptyState art={<IcTable size={64} />} title={`لا حصص ${WEEKDAYS[day]}`}
          line="أضف حصص هذا اليوم — ويظهر الجدول في جوّال المعلّم فورًا."
          action={<Button variant="primary" onClick={openNew}>أضف حصّة</Button>} />
      ) : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead><tr><th>الحصّة</th><th>الوقت</th><th>المادّة</th><th>الفصل</th><th>المعلّم</th><th>القاعة</th><th aria-label="أفعال" /></tr></thead>
            <tbody>
              {ofDay.map((p) => (
                <tr key={p.id}>
                  <td data-label="الحصّة"><span className="mdd-num">{p.slot}</span></td>
                  <td data-label="الوقت"><span className="mdd-num">{p.starts_at?.slice(0, 5)} — {p.ends_at?.slice(0, 5)}</span></td>
                  <td data-label="المادّة"><strong>{p.subject}</strong></td>
                  <td data-label="الفصل">{className(p.class_id)}</td>
                  <td data-label="المعلّم">{teacherName(p.teacher_id)}</td>
                  <td data-label="القاعة">{p.room || '—'}</td>
                  <td>
                    <div className="mdd-row" style={{ gap: 6, justifyContent: 'flex-end' }}>
                      <Button size="sm" auto icon={<IcEdit size={13} />} onClick={() => setEditing({ ...p })}>تعديل</Button>
                      <Button size="sm" auto variant="danger" icon={<IcTrash size={13} />} onClick={() => setDel(p)}>حذف</Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={!!editing} onClose={() => setEditing(null)} wide title={editing?.id ? 'تعديل حصّة' : 'حصّة جديدة'}
        footer={<>
          <Button variant="secondary" block onClick={() => setEditing(null)}>إلغاء</Button>
          <Button variant="primary" block loading={busy} onClick={save}>احفظ</Button>
        </>}>
        {editing && (
          <>
            <div className="mdd-grid mdd-grid--2" style={{ gap: 12 }}>
              <Field label="اليوم">
                <Select value={editing.weekday} onChange={(e) => setEditing({ ...editing, weekday: e.target.value })}>
                  {WEEKDAYS.map((w, i) => <option key={w} value={i}>{w}</option>)}
                </Select>
              </Field>
              <Field label="رقم الحصّة" help="الوقت يُملأ تلقائيًّا ويمكن تعديله">
                <Select value={editing.slot}
                  onChange={(e) => {
                    const slot = Number(e.target.value)
                    const [s, en] = SLOT_TIMES[slot] || ['', '']
                    setEditing({ ...editing, slot, starts_at: s, ends_at: en })
                  }}>
                  {Object.keys(SLOT_TIMES).map((n) => <option key={n} value={n}>الحصّة {n}</option>)}
                </Select>
              </Field>
              <Field label="من">
                <Input ltr type="time" value={editing.starts_at?.slice(0, 5) || ''}
                  onChange={(e) => setEditing({ ...editing, starts_at: e.target.value })} />
              </Field>
              <Field label="إلى">
                <Input ltr type="time" value={editing.ends_at?.slice(0, 5) || ''}
                  onChange={(e) => setEditing({ ...editing, ends_at: e.target.value })} />
              </Field>
              <Field label="المادّة">
                <Input value={editing.subject} onChange={(e) => setEditing({ ...editing, subject: e.target.value })} placeholder="الرياضيات" />
              </Field>
              <Field label="الفصل">
                <Select value={editing.class_id} onChange={(e) => setEditing({ ...editing, class_id: e.target.value })}>
                  {classes.map((k) => <option key={k.id} value={k.id}>{k.name}</option>)}
                </Select>
              </Field>
              <Field label="المعلّم" help="هو من يرصد الحضور من جوّاله">
                <Select value={editing.teacher_id || ''} onChange={(e) => setEditing({ ...editing, teacher_id: e.target.value })}>
                  <option value="">— بلا معلّم —</option>
                  {team.map((t) => <option key={t.id} value={t.id}>{t.full_name}</option>)}
                </Select>
              </Field>
              <Field label="القاعة">
                <Input value={editing.room || ''} onChange={(e) => setEditing({ ...editing, room: e.target.value })} placeholder="فصل 12" />
              </Field>
            </div>
          </>
        )}
      </Modal>

      <ConfirmModal open={!!del} onClose={() => setDel(null)} onConfirm={remove} loading={busy} danger
        confirmLabel="احذف الحصّة" title="حذف حصّة؟"
        body="تُحذف الحصّة وسجلّ حضورها. ولا يمكن التراجع." />
    </>
  )
}
