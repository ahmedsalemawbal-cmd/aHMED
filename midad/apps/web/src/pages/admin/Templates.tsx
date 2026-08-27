import React, { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { fmtNum, fmtShort } from '../../lib/format'
import type { Template, TemplateFolder, TemplateAudience, FolderAudience } from '../../lib/types'
import {
  Badge, Button, Card, ConfirmModal, EmptyState, ErrorState, PageHead,
  SearchInput, Select, SkeletonRows,
} from '../../ui/kit'
import { IcPlus, IcEdit, IcCopy, IcTrash, IcFolderFill, IcPage } from '../../ui/icons'
import ImportTemplate from './ImportTemplate'

/**
 * مكتبة القوالب — لوحة المالك.
 *
 * القالب هنا مستندٌ يُصمَّم مرّةً فيظهر للمشتركين: يفتحونه فيحرّرونه،
 * ويحسّنونه بالذكاء الاصطناعيّ، ويُنزّلونه PDF أو وورد. فالنشر من هذه
 * الشاشة هو ما يُوصله إليهم، والمسوّدة لا يراها أحدٌ سواك.
 *
 * وقائمتان لا قائمة: قوالب المدرسة وقوالب المعلّم. وكان جدولٌ واحدٌ
 * يخلطهما، فلا يرى المالك ما سيراه كلُّ جمهورٍ على حدة — وهو الذي يبني
 * عليه ترتيب الأولويّات. وقالبُ «الكلّ» يظهر في القائمتين، وهو ملفٌّ
 * واحدٌ لا نسختان: تغييرُ صلاحيّته في إحداهما يُخرجه من الأخرى.
 */

const AUDIENCES: { key: FolderAudience; name: string; dot: string; col: 'sort_school' | 'sort_teacher' }[] = [
  { key: 'school', name: 'قوالب المدرسة', dot: 'oklch(0.55 0.16 245)', col: 'sort_school' },
  { key: 'teacher', name: 'قوالب المعلّم', dot: 'var(--mdd-accent)', col: 'sort_teacher' },
]

const AUD_LABEL: Record<TemplateAudience, string> = {
  all: 'الكلّ', school: 'المدرسة', teacher: 'المعلّم',
}

export default function Templates() {
  const { toast } = useApp()
  const nav = useNavigate()
  const [q, setQ] = useState('')
  const [aud, setAud] = useState('')
  const [status, setStatus] = useState('')
  const [del, setDel] = useState<Template | null>(null)
  const [busy, setBusy] = useState(false)
  const [importing, setImporting] = useState(false)
  const dq = useDebounced(q)

  const { data, loading, error, reload } = useAsync(async () => {
    const [t, f] = await Promise.all([
      /* بلا `content_html`: جدولُ العناوين لا يعرض متنًا، و`select('*')`
         كان يجلب ثلاثةَ عشرَ ميغابايتًا لصفٍّ فيه عنوانٌ وتاريخ.
         و`body_len` يشتقّه المُحفِّز، ويكفي لمعرفة أفارغٌ هو أم لا. */
      supabase.from('templates').select(
        'id,slug,title,category_key,description,outputs,estimated_minutes,version,' +
        'status,usage_count,is_new,sort,sort_school,sort_teacher,created_at,updated_at,' +
        'kind,folder_id,audience,page,source_pdf_path,source_pages,body_len',
      ).order('title'),
      supabase.from('template_folders').select('*').order('sort').order('name'),
    ])
    if (t.error) throw new Error(t.error.message)
    if (f.error) throw new Error(f.error.message)
    return { list: (t.data || []) as unknown as Template[], folders: (f.data || []) as TemplateFolder[] }
  }, [])

  const folders = data?.folders || []

  /* نسخةٌ محلّيّةٌ تُحرَّك بالسحب فورًا ثمّ تُحفظ. ولو انتظرنا ردّ الخادم
     لرأى المالك الصفّ يقفز إلى مكانه القديم ثمّ يعود — وارتجافةٌ تُرى
     تجعله يشكّ أنّ السحب لم يُحفظ، فيسحب مرّةً أخرى. */
  const [list, setList] = useState<Template[]>([])
  useEffect(() => { setList(data?.list || []) }, [data])

  const published = list.filter((t) => t.status === 'published').length
  const drafts = list.filter((t) => t.status === 'draft').length

  const match = useMemo(() => {
    const term = dq.trim()
    return (t: Template) =>
      (!term || t.title.includes(term) || t.slug.includes(term.toLowerCase())) &&
      (!aud || t.audience === aud) &&
      (!status || t.status === status)
  }, [dq, aud, status])

  /** ما يراه كلّ جمهور: قوالبه وقوالب «الكلّ»، بترتيب عموده هو. */
  const sectionOf = (a: FolderAudience, col: 'sort_school' | 'sort_teacher') =>
    list.filter((t) => t.audience === a || t.audience === 'all')
      .sort((x, y) => (x[col] ?? 0) - (y[col] ?? 0) || x.title.localeCompare(y.title, 'ar'))

  /**
   * يُثبّت ترتيب قائمةٍ في عمودها.
   *
   * ويُكتب الفهرس لكلّ صفٍّ تغيّر موضعه فقط — لا للقائمة كلّها. فالقالب
   * الذي لم يتحرّك لا يحتاج طلبًا، وخمسون قالبًا تعني خمسين طلبًا في كلّ
   * سحبة.
   */
  const persist = async (ordered: Template[], col: 'sort_school' | 'sort_teacher') => {
    const moved = ordered.filter((t, i) => (t[col] ?? 0) !== i)
    if (!moved.length) return
    setList((prev) => prev.map((t) => {
      const i = ordered.findIndex((o) => o.id === t.id)
      return i < 0 ? t : { ...t, [col]: i }
    }))
    const rs = await Promise.all(moved.map((t, ) =>
      supabase.from('templates').update({ [col]: ordered.findIndex((o) => o.id === t.id) }).eq('id', t.id)))
    const bad = rs.find((r) => r.error)
    if (bad?.error) { toast('تعذّر حفظ الترتيب: ' + bad.error.message, 'danger'); reload() }
  }

  /** ينقل صفًّا من موضعٍ إلى موضعٍ داخل قائمته، ثمّ يحفظ. */
  const move = (a: FolderAudience, col: 'sort_school' | 'sort_teacher', from: number, to: number) => {
    const rows = sectionOf(a, col)
    if (from === to || to < 0 || to >= rows.length) return
    const next = rows.slice()
    next.splice(to, 0, next.splice(from, 1)[0])
    persist(next, col)
  }

  const setAudience = async (t: Template, next: TemplateAudience) => {
    if (next === t.audience) return
    /* المجلّد يتبع الصلاحيّة: العامُّ من جمهوره، و«الكلّ» بلا مجلّد. */
    const folder_id = next === 'all'
      ? null
      : (folders.find((f) => f.is_general && f.audience === next)?.id ?? null)
    setList((p) => p.map((x) => (x.id === t.id ? { ...x, audience: next, folder_id } : x)))
    const { error: e } = await supabase.from('templates')
      .update({ audience: next, folder_id }).eq('id', t.id)
    if (e) { toast(e.message, 'danger'); reload(); return }
    toast(`صار «${t.title}» لـ${AUD_LABEL[next]}`)
  }

  const create = async () => {
    setBusy(true)
    const slug = `template-${Date.now().toString(36)}`
    const { data: row, error: e } = await supabase.from('templates').insert({
      slug,
      title: 'قالب جديد',
      category_key: 'general',
      description: '',
      kind: 'doc',
      audience: 'school',
      folder_id: folders.find((f) => f.is_general && f.audience === 'school')?.id ?? null,
      content_html: '<h1>عنوان المستند</h1>\n<p>اكتب هنا، أو أدرج جدولًا من شريط الأدوات.</p>',
      status: 'draft',
      outputs: ['pdf', 'docx'],
    }).select('id').single()
    setBusy(false)
    if (e) { toast(e.message, 'danger'); return }
    nav(`/admin/template/${(row as any).id}`)
  }

  const duplicate = async (t: Template) => {
    /* المتن يُجلب هنا لا في القائمة: تُضاعَف واحدةٌ في المرّة، ولا يُحمَّل
       متنُ خمسةَ عشرَ قالبًا لأنّ واحدًا منها قد يُضاعَف. */
    const { data: full, error: fe } = await supabase.from('templates')
      .select('content_html').eq('id', t.id).maybeSingle()
    if (fe) { toast(fe.message, 'danger'); return }
    const { data: row, error: e } = await supabase.from('templates').insert({
      slug: `${t.slug}-copy-${Date.now().toString(36)}`,
      title: `نسخة من ${t.title}`,
      category_key: t.category_key,
      description: t.description,
      kind: t.kind ?? 'doc',
      audience: t.audience,
      folder_id: t.folder_id,
      content_html: (full as any)?.content_html ?? '',
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
    toast(next === 'published' ? 'نُشر القالب — صار يظهر للمشتركين' : 'سُحب القالب من المشتركين')
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

  const anyShown = AUDIENCES.some((a) => sectionOf(a.key, a.col).filter(match).length > 0)

  return (
    <>
      <PageHead
        title="مكتبة القوالب"
        sub={loading ? 'جارٍ التحميل…'
          : `${fmtNum(published)} منشورًا يراه المشتركون · ${fmtNum(drafts)} مسوّدة عندك`}
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
          <Select value={aud} onChange={(e) => setAud(e.target.value)} aria-label="الصلاحيّة">
            <option value="">كلّ الصلاحيّات</option>
            <option value="all">الكلّ</option>
            <option value="school">المدرسة</option>
            <option value="teacher">المعلّم</option>
          </Select>
          <Select value={status} onChange={(e) => setStatus(e.target.value)} aria-label="الحالة">
            <option value="">كلّ الحالات</option>
            <option value="published">منشور</option>
            <option value="draft">مسوّدة</option>
          </Select>
        </div>
      </Card>

      {loading ? <SkeletonRows n={8} /> : !anyShown ? (
        <EmptyState
          art={<IcFolderFill size={58} />}
          title={list.length ? 'لا نتيجة' : 'المكتبة فارغة'}
          line={list.length
            ? 'جرّب كلمةً أقصر أو امسح الفلاتر.'
            : 'صمّم قالبًا في المحرّر، أو استورد ملفًّا جاهزًا — ثمّ انشره فيظهر للمشتركين.'}
          action={
            <Button variant="primary"
              onClick={list.length ? () => { setQ(''); setAud(''); setStatus('') } : create}>
              {list.length ? 'امسح الفلاتر' : 'أنشئ قالبًا'}
            </Button>}
        />
      ) : (
        AUDIENCES.map((a) => (
          <Section
            key={a.key}
            audience={a}
            rows={sectionOf(a.key, a.col)}
            visible={match}
            onMove={(from, to) => move(a.key, a.col, from, to)}
            onAudience={setAudience}
            onOpen={(t) => nav(`/admin/template/${t.id}`)}
            onDuplicate={duplicate}
            onPublish={togglePublish}
            onDelete={setDel}
          />
        ))
      )}

      {del && (
        <ConfirmModal
          open onClose={() => setDel(null)} onConfirm={remove} loading={busy}
          title="حذف القالب" danger confirmLabel="احذف"
          body={`سيُحذف «${del.title}» ولن يظهر للمشتركين. الملفّات التي أنشأوها منه تبقى عندهم.`}
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

/**
 * قائمةُ جمهورٍ واحد، تُرتَّب بالسحب أو بالسهمين.
 *
 * والسهمان ليسا زينةً بجانب السحب: السحب على الجوّال يزاحم تمرير
 * الصفحة، وباللمس يُمسك الصفّ فتتحرّك الشاشة تحته. والسهم يعمل حيثما
 * يعمل الزرّ — بالفأرة وباللمس وبلوحة المفاتيح.
 *
 * ولا تُرتَّب قائمةٌ مُرشَّحة: البحث يُخفي صفوفًا، فيصير «فوق» يعني موضعًا
 * لا يراه المالك، ويقع القالب حيث لم يقصد. فيُعطَّل الترتيب حتّى يُمسح
 * الفلتر، ويُقال له لماذا.
 */
function Section({ audience, rows, visible, onMove, onAudience, onOpen, onDuplicate, onPublish, onDelete }: {
  audience: { key: FolderAudience; name: string; dot: string }
  rows: Template[]
  visible: (t: Template) => boolean
  onMove: (from: number, to: number) => void
  onAudience: (t: Template, a: TemplateAudience) => void
  onOpen: (t: Template) => void
  onDuplicate: (t: Template) => void
  onPublish: (t: Template) => void
  onDelete: (t: Template) => void
}) {
  const [drag, setDrag] = useState<number | null>(null)
  const [over, setOver] = useState<number | null>(null)

  const shown = rows.filter(visible)
  const filtered = shown.length !== rows.length
  if (!shown.length) return null

  return (
    <Card className="mdd-col" style={{ gap: 0, padding: 0, marginBlockEnd: 'var(--mdd-s-4)' }}>
      <div className="mdd-tsec">
        <span className="mdd-tsec__dot" style={{ background: audience.dot }} />
        <b>{audience.name}</b>
        <span className="mdd-tsec__n">
          {rows.length} · {filtered ? 'امسح البحث لترتّبها' : 'رتّبها بالسحب أو بالسهمين'}
        </span>
      </div>

      <div className="mdd-table-wrap mdd-table-wrap--cards">
        <table className="mdd-table">
          <thead>
            <tr>
              <th aria-label="ترتيب" style={{ inlineSize: 78 }} />
              <th>القالب</th>
              <th>الصلاحيّة</th>
              <th>الحالة</th>
              <th>آخر تحديث</th>
              <th aria-label="إجراءات" />
            </tr>
          </thead>
          <tbody>
            {shown.map((t) => {
              const i = rows.indexOf(t)
              const empty = (t.body_len ?? 0) === 0
              return (
                <tr
                  key={t.id}
                  draggable={!filtered}
                  onDragStart={() => setDrag(i)}
                  onDragEnd={() => { setDrag(null); setOver(null) }}
                  onDragOver={(e) => { if (drag !== null) { e.preventDefault(); setOver(i) } }}
                  onDrop={(e) => {
                    e.preventDefault()
                    if (drag !== null) onMove(drag, i)
                    setDrag(null); setOver(null)
                  }}
                  className={[
                    drag === i ? 'mdd-trow--drag' : '',
                    over === i && drag !== null && drag !== i ? 'mdd-trow--over' : '',
                  ].filter(Boolean).join(' ')}
                >
                  <td data-label="ترتيب">
                    <div className="mdd-ord">
                      <span className="mdd-ord__grip" aria-hidden="true"
                        title={filtered ? 'امسح البحث لترتّب' : 'اسحب لتُرتّب'}>⠿</span>
                      <div className="mdd-ord__arrows">
                        <button type="button" className="mdd-ord__btn" disabled={filtered || i === 0}
                          aria-label={`ارفع ${t.title}`} onClick={() => onMove(i, i - 1)}>▲</button>
                        <button type="button" className="mdd-ord__btn" disabled={filtered || i === rows.length - 1}
                          aria-label={`أنزل ${t.title}`} onClick={() => onMove(i, i + 1)}>▼</button>
                      </div>
                    </div>
                  </td>

                  <td data-label="القالب">
                    <button className="mdd-linkish" onClick={() => onOpen(t)}>{t.title}</button>
                    <span className="mdd-dim" style={{ display: 'block', fontSize: 11.5 }}>
                      {t.slug}{t.source_pages ? ` · ${t.source_pages} صفحة من الأصل` : ''}
                    </span>
                    {empty && <Badge tone="warn">فارغ</Badge>}
                  </td>

                  <td data-label="الصلاحيّة">
                    <Select value={t.audience} aria-label={`صلاحيّة ${t.title}`}
                      onChange={(e) => onAudience(t, e.target.value as TemplateAudience)}>
                      <option value="all">الكلّ</option>
                      <option value="school">المدرسة</option>
                      <option value="teacher">المعلّم</option>
                    </Select>
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
                        onClick={() => onOpen(t)}><IcEdit size={15} /></Button>
                      <Button auto size="sm" variant="ghost" title="مضاعفة"
                        onClick={() => onDuplicate(t)}><IcCopy size={15} /></Button>
                      <Button auto size="sm" variant={t.status === 'published' ? 'secondary' : 'primary'}
                        onClick={() => onPublish(t)}>
                        {t.status === 'published' ? 'اسحب' : 'انشر'}
                      </Button>
                      <Button auto size="sm" variant="ghost" title="حذف"
                        onClick={() => onDelete(t)}><IcTrash size={15} /></Button>
                    </div>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </Card>
  )
}
