import React, { useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { callFunction, supabase } from '../../lib/supabase'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fetchTemplates } from '../../lib/data'
import {
  addItem, axesOf, coverage, fetchPortfolio, removeItem, signedUrl,
  type NewItem,
} from '../../lib/portfolio'
import { fetchTemplateBySlug } from '../../lib/data'
import type { PortfolioItem, PortfolioKind, Template } from '../../lib/types'
import {
  Alert, Badge, Button, Card, ConfirmModal, EmptyState, ErrorState,
  Field, Input, Modal, PageHead, Select, SkeletonRows, Textarea,
} from '../../ui/kit'
import { IcPlus, IcTrash, IcCheck, IcPage, IcSpinner } from '../../ui/icons'

/**
 * ملفّ إنجازي — سجلُّ الشواهد على مدار العام.
 *
 * الموظّف لا يعجز عن كتابة ملفّ إنجازه؛ يعجز في يونيو عن تذكّر ما فعله
 * في أكتوبر، والصورةُ ضائعةٌ بين ثلاثة آلاف صورةٍ في جوّاله.
 *
 *     مِدادٌ لا يكتب الملفّ؛ يمنع الضياع.
 *
 * ولذا كلُّ ما في هذه الشاشة يخدم شيئًا واحدًا: **أن يكون الالتقاط أسرع
 * من نسيانه**. زرٌّ واحدٌ كبير، وحقلٌ واحدٌ إلزاميّ، وما سواه افتراضاتٌ
 * معقولة. فإن بطُؤ الالتقاط لم يكن ثمّة سجلٌّ في آخر العام أصلًا.
 */

const KINDS: { key: PortfolioKind; label: string; accept?: string; camera?: boolean }[] = [
  { key: 'photo', label: 'صورة', accept: 'image/*', camera: true },
  { key: 'certificate', label: 'شهادة', accept: 'image/*,application/pdf' },
  { key: 'file', label: 'مرفق', accept: 'image/*,application/pdf' },
  { key: 'text', label: 'ملاحظة' },
]

export default function Portfolio() {
  const { subscriber, profile, toast } = useApp()
  const [adding, setAdding] = useState(false)
  const [del, setDel] = useState<PortfolioItem | null>(null)
  const nav = useNavigate()
  const [busy, setBusy] = useState(false)
  const [making, setMaking] = useState(false)
  const [makeErr, setMakeErr] = useState<string | null>(null)

  const year = subscriber?.academic_year || ''

  const { data, loading, error, reload } = useAsync(async () => {
    if (!profile) return null
    /* قالبُ الدور يُجلب لأجل محاوره وحدها — والمحاور من القالب لا من
       جدول إعدادات، فلكلّ دورٍ محاورُه الصحيحة بلا سطر إعداد. */
    const all = await fetchTemplates()
    const mine = pickPortfolioTemplate(all, profile.role_key)
    const full = mine ? await fetchTemplateBySlug(mine.slug) : null
    const items = await fetchPortfolio(profile.id, year)
    return { tpl: full, items, axes: full ? axesOf(full) : [] }
  }, [profile?.id, profile?.role_key, year])

  const items = data?.items || []
  const axes = data?.axes || []
  const cov = useMemo(() => coverage(axes, items), [axes, items])

  /**
   * إصدارُ ملفّ الإنجاز — نداءٌ واحدٌ يردّ المستند مُركَّبًا.
   *
   * والتركيبُ في الخادم لا هنا: لو بُني في المتصفّح وفي الجوّال معًا
   * لتفارقا عند أوّل إصلاحٍ يقع في أحدهما — فيخرج ملفُّ المعلّم من
   * حاسبه غيرَ ملفّه من جوّاله.
   *
   *     ما يُبنى مرّتين يتفارق مرّةً.
   *
   * والناتجُ مستندٌ عاديّ، فيُفتح في محرّر مِداد الكامل: ألوانٌ وجداولُ
   * وخطوطٌ ورفعُ سطرٍ وإنزاله — وهو ما لا يقدر عليه الجوّال.
   */
  const compose = async () => {
    if (!data?.tpl || !subscriber || !profile) return
    setMaking(true); setMakeErr(null)
    try {
      const plan = await callFunction<{ html: string; counted: number }>(
        'portfolio-compose', { template_id: data.tpl.id, year })
      const { data: doc, error: e } = await supabase.from('documents').insert({
        subscriber_id: subscriber.id,
        owner_id: profile.id,
        template_id: data.tpl.id,
        title: `${data.tpl.title} — ${year}`,
        content_html: plan.html,
        status: 'draft',
      }).select('id').single()
      if (e || !doc) throw new Error(e?.message || 'تعذّر حفظ الملفّ')
      nav(`/app/doc/${(doc as any).id}`)
    } catch (err: any) {
      setMakeErr(err?.message || 'تعذّر إصدار الملفّ')
      setMaking(false)
    }
  }

  const save = async (item: NewItem) => {
    if (!subscriber || !profile) return
    await addItem(subscriber.id, profile.id, year, item)
    setAdding(false)
    toast('أُضيف الشاهد')
    reload()
  }

  const remove = async () => {
    if (!del) return
    setBusy(true)
    try {
      await removeItem(del)
      toast('حُذف الشاهد')
      setDel(null); reload()
    } catch (e: any) {
      toast(e?.message || 'تعذّر الحذف', 'danger')
    } finally { setBusy(false) }
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="ملفّ إنجازي"
        sub={loading ? 'جارٍ التحميل…'
          : `${cov.items} شاهدًا${year ? ` · العام ${year}` : ''}`}
        actions={
          <div className="mdd-row" style={{ gap: 8 }}>
            {data?.tpl && items.length > 0 && (
              <Button auto variant="soft" loading={making} onClick={compose}>
                أصدِر إنجازي
              </Button>
            )}
            <Button auto variant="primary" icon={<IcPlus size={15} />}
              onClick={() => setAdding(true)}>أضف شاهدًا</Button>
          </div>
        }
      />

      {makeErr ? <Alert tone="danger">{makeErr}</Alert> : null}

      {loading ? <SkeletonRows n={5} /> : (
        <>
          {/* الاكتمال أوّلًا: هو ما يجعل الموظّف يعود شهريًّا. ورقمُ
              «٦ من ٩» أبلغُ من قائمةٍ لا تقول أين النقص. */}
          {cov.total > 0 && <Coverage cov={cov} />}

          {!data?.tpl && (
            <Alert tone="info">
              لم نجد ملفّ إنجازٍ مخصّصًا لدورك بعد. يمكنك جمع شواهدك من الآن،
              وسيُرتَّب الملفّ عليها متى أُضيف.
            </Alert>
          )}

          {items.length === 0 ? (
            <EmptyState
              art={<IcPage size={58} />}
              title="السجلّ فارغ"
              line="أضف شاهدك الأوّل الآن — صورةً من الكاميرا أو مرفقًا أو ملاحظة.
                    وفي يونيو ستجد سنتك كاملةً أمامك بدل أن تبحث عنها."
              action={<Button variant="primary" onClick={() => setAdding(true)}>أضف شاهدًا</Button>}
            />
          ) : (
            <div className="mdd-pf-list">
              {items.map((it) => (
                <ItemCard key={it.id} item={it} onDelete={() => setDel(it)} />
              ))}
            </div>
          )}
        </>
      )}

      {adding && (
        <AddItem axes={axes} onClose={() => setAdding(false)} onSave={save} />
      )}

      {del && (
        <ConfirmModal
          open onClose={() => setDel(null)} onConfirm={remove} loading={busy}
          title="حذف الشاهد" danger confirmLabel="احذف"
          body={`سيُحذف «${del.title}» وملفُّه معه، ولا يمكن استرجاعه.`}
        />
      )}
    </>
  )
}

/**
 * أيُّ قالبٍ هو ملفّ إنجاز هذا الدور؟
 *
 * الأدقّ ما أُسند إلى الدور صراحةً في `role_keys`. فإن لم يُسنَد شيءٌ
 * بعد، بحثنا بالعنوان — فالمالك يسمّيها «ملفّ إنجاز ...». وهذا احتياطٌ
 * ينفع اليوم ويسقط متى أسند المالك أدواره، وهو الصواب.
 */
function pickPortfolioTemplate(all: Template[], roleKey: string): Template | null {
  const isPf = (t: Template) => /إنجاز|انجاز/.test(t.title)
  const byRole = all.find((t) => isPf(t) && (t.role_keys || []).includes(roleKey))
  if (byRole) return byRole
  const loose = all.find((t) => isPf(t) && (t.role_keys || []).length === 0)
  return loose || null
}

/* ═══════════════ الاكتمال ═══════════════ */

function Coverage({ cov }: { cov: ReturnType<typeof coverage> }) {
  const pct = cov.total ? Math.round((cov.covered / cov.total) * 100) : 0
  return (
    <Card className="mdd-pf-cov">
      <div className="mdd-pf-cov__top">
        <div>
          <b>{cov.covered} من {cov.total} محاور</b>
          <span>{cov.items} شاهدًا{cov.untagged ? ` · ${cov.untagged} بلا محور` : ''}</span>
        </div>
        <span className="mdd-pf-cov__pct">{pct}٪</span>
      </div>
      <div className="mdd-pf-bar" role="img"
        aria-label={`أُنجز ${cov.covered} من ${cov.total} محاور`}>
        <span style={{ inlineSize: `${pct}%` }} />
      </div>
      <div className="mdd-pf-axes">
        {cov.axes.map((a) => (
          <span key={a.name} className={a.count ? 'on' : ''} title={`${a.count} شاهدًا`}>
            {a.count ? <IcCheck size={12} /> : null}{a.name}
            {a.count > 1 && <b>{a.count}</b>}
          </span>
        ))}
      </div>
    </Card>
  )
}

/* ═══════════════ بطاقةُ شاهد ═══════════════ */

function ItemCard({ item, onDelete }: { item: PortfolioItem; onDelete: () => void }) {
  const [url, setUrl] = useState('')
  const isImg = !!item.file_path && /^image\//.test(item.file_mime || '')

  /* الرابط موقَّتٌ ويُطلب عند الحاجة: الدلو خاصٌّ فيه صورُ طلّاب، ولا
     رابطَ دائمًا يُنسخ ويُرسل. */
  React.useEffect(() => {
    let alive = true
    if (isImg && item.file_path) {
      signedUrl(item.file_path).then((u) => { if (alive) setUrl(u) })
    }
    return () => { alive = false }
  }, [isImg, item.file_path])

  return (
    <Card className="mdd-pf-item">
      {isImg && (
        <div className="mdd-pf-thumb">
          {url ? <img src={url} alt="" loading="lazy" /> : <IcSpinner size={18} className="mdd-spin" />}
        </div>
      )}
      <div className="mdd-pf-body">
        <div className="mdd-pf-head">
          <b>{item.title}</b>
          <span className="mdd-dim">{item.happened_on}</span>
        </div>
        {item.note && <p className="mdd-pf-note">{item.note}</p>}
        <div className="mdd-pf-tags">
          {item.axis
            ? <Badge tone="success">{item.axis}</Badge>
            : <Badge tone="warn">بلا محور</Badge>}
          <Badge tone="neutral">{KINDS.find((k) => k.key === item.kind)?.label || item.kind}</Badge>
        </div>
      </div>
      <Button auto size="sm" variant="ghost" title="حذف" onClick={onDelete}>
        <IcTrash size={15} />
      </Button>
    </Card>
  )
}

/* ═══════════════ الالتقاط ═══════════════ */

function AddItem({ axes, onClose, onSave }: {
  axes: string[]
  onClose: () => void
  onSave: (i: NewItem) => Promise<void>
}) {
  const [kind, setKind] = useState<PortfolioKind>('photo')
  const [file, setFile] = useState<File | null>(null)
  const [title, setTitle] = useState('')
  const [note, setNote] = useState('')
  const [axis, setAxis] = useState(() => lastAxis())
  const [when, setWhen] = useState(() => new Date().toISOString().slice(0, 10))
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const pick = useRef<HTMLInputElement>(null)

  const spec = KINDS.find((k) => k.key === kind)!
  const needsFile = kind !== 'text'

  const go = async () => {
    setErr(null)
    if (needsFile && !file) { setErr('اختر ملفًّا أو صوّر واحدًا.'); return }
    if (!needsFile && !note.trim()) { setErr('اكتب الملاحظة.'); return }
    setBusy(true)
    try {
      rememberAxis(axis)
      await onSave({ kind, file, title, note, axis, happened_on: when })
    } catch (e: any) {
      setErr(e?.message || 'تعذّر الحفظ')
    } finally { setBusy(false) }
  }

  return (
    <Modal
      open onClose={onClose} title="أضف شاهدًا"
      footer={
        <>
          <Button variant="secondary" onClick={onClose} block>إلغاء</Button>
          <Button variant="primary" onClick={go} block loading={busy}>احفظ</Button>
        </>
      }>
      <div className="mdd-col" style={{ gap: 14 }}>

        <div className="mdd-pf-kinds" role="tablist">
          {KINDS.map((k) => (
            <button key={k.key} type="button" role="tab"
              aria-selected={kind === k.key}
              className={kind === k.key ? 'on' : ''}
              onClick={() => { setKind(k.key); setFile(null); setErr(null) }}>
              {k.label}
            </button>
          ))}
        </div>

        {needsFile && (
          <>
            <button type="button" className="mdd-drop" onClick={() => pick.current?.click()}
              onDragOver={(e) => e.preventDefault()}
              onDrop={(e) => { e.preventDefault(); const f = e.dataTransfer.files?.[0]; if (f) setFile(f) }}>
              <IcPage size={28} />
              <b>{file ? file.name : (spec.camera ? 'صوّر أو اختر صورة' : 'اختر ملفًّا')}</b>
              <span>
                {file
                  ? `${(file.size / 1024 / 1024).toFixed(1)} ميغابايت — تُصغَّر قبل الرفع`
                  : 'الصور تُصغَّر تلقائيًّا فلا تثقل حسابك'}
              </span>
            </button>
            <input ref={pick} type="file" hidden accept={spec.accept}
              {...(spec.camera ? { capture: 'environment' as any } : {})}
              onChange={(e) => { const f = e.target.files?.[0]; if (f) { setFile(f); setErr(null) } }} />
          </>
        )}

        {err && <Alert tone="danger">{err}</Alert>}

        <Field label={kind === 'text' ? 'الملاحظة' : 'وصفٌ قصير'}>
          <Textarea rows={kind === 'text' ? 4 : 2} value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="مثال: تنفيذ درسٍ تطبيقيٍّ في معمل العلوم لصفّ ثاني/أ" />
        </Field>

        {/* المحور اختياريّ: الإلزام يُبطئ الالتقاط فيموت السجلّ، وتركُه
            بلا سؤالٍ يجعل التوزيع تخمينًا. فيُسأل ويجوز تخطّيه. */}
        <Field label="المحور — اختياريّ"
          help={axes.length
            ? 'يجعل ملفّك أدقّ آخرَ العام. وإن تخطّيتَه وُزّع تلقائيًّا ووُسم للمراجعة.'
            : 'ستظهر المحاور متى أُسند ملفّ إنجازٍ لدورك.'}>
          <Select value={axis} onChange={(e) => setAxis(e.target.value)} disabled={!axes.length}>
            <option value="">— بلا محور —</option>
            {axes.map((a) => <option key={a} value={a}>{a}</option>)}
          </Select>
        </Field>

        <div className="mdd-grid mdd-grid--2" style={{ gap: 10 }}>
          <Field label="العنوان — اختياريّ">
            <Input value={title} onChange={(e) => setTitle(e.target.value)}
              placeholder="يُؤخذ من الوصف إن تُرك" />
          </Field>
          {/* تاريخُ الحدث لا تاريخُ الرفع: يُرفع بعد أسبوعٍ أحيانًا،
              والملفّ يُرتَّب بتاريخ وقوعه. */}
          <Field label="تاريخ الحدث">
            <Input type="date" ltr value={when} onChange={(e) => setWhen(e.target.value)} />
          </Field>
        </div>
      </div>
    </Modal>
  )
}

/* آخرُ محورٍ استُعمل — فالمعلّم يرفع عدّة شواهد لمحورٍ واحدٍ في اليوم،
   ولا معنى لأن يختاره في كلّ مرّة. ويُحفظ في الجهاز لا في الخادم:
   تفضيلٌ لحظيٌّ لا بيانات. */
const AXIS_KEY = 'midad.pf.axis'
function lastAxis(): string {
  try { return localStorage.getItem(AXIS_KEY) || '' } catch { return '' }
}
function rememberAxis(a: string) {
  try { a ? localStorage.setItem(AXIS_KEY, a) : localStorage.removeItem(AXIS_KEY) } catch { /* لا شيء */ }
}
