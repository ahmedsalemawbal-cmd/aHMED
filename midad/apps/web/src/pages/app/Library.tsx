import React, { useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { fetchFolders, fetchTemplates } from '../../lib/data'
import type { Template, TemplateFolder } from '../../lib/types'
import {
  Badge, Button, Card, EmptyState, ErrorState, PageHead, SearchInput, SkeletonCards,
} from '../../ui/kit'
import { IcFolderFill, IcChevron, IcPage, IcSpark, IcBack, IcSearch } from '../../ui/icons'
import { counted, TPL, FLD } from '../../lib/format'

/**
 * مكتبة القوالب — مجلّداتٌ أوّلًا، ثمّ قوالب المجلّد.
 *
 * لماذا المجلّدات لا الفئات المسطّحة؟ لأنّ خمسين قالبًا في شبكةٍ واحدة
 * كومةٌ يُبحث فيها ولا تُتصفَّح. والمجلّد يحمل لونه الثابت فتُعرفه العين
 * قبل أن تقرأ اسمه.
 */
export default function Library() {
  const { slug } = useParams()
  return slug ? <FolderView slug={slug} /> : <FoldersHome />
}

/* ═════════════════ الصفحة الأولى: المجلّدات ═════════════════ */

function FoldersHome() {
  const nav = useNavigate()
  const [q, setQ] = useState('')
  const dq = useDebounced(q)

  const { data: folders, loading: lf, error: ef, reload: rf } = useAsync(fetchFolders, [])
  const { data: templates, loading: lt, error: et, reload: rt } = useAsync(fetchTemplates, [])

  const searching = dq.trim().length > 0

  const hits = useMemo(() => {
    const term = dq.trim()
    if (!term) return []
    return (templates || []).filter((t) =>
      t.title.includes(term) || (t.description || '').includes(term) || t.slug.includes(term.toLowerCase()))
  }, [templates, dq])

  const loose = useMemo(
    () => (templates || []).filter((t) => !t.folder_id),
    [templates],
  )

  if (ef || et) return <ErrorState onRetry={() => { rf(); rt() }} message={ef || et || ''} />

  const total = templates?.length ?? 0

  return (
    <>
      <PageHead
        title="مكتبة القوالب"
        sub={lt ? 'جارٍ التحميل…' : `${counted(total, TPL)} في ${counted(folders?.length ?? 0, FLD)}`}
      />

      <div style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
        <SearchInput value={q} onChange={setQ} placeholder="ابحث في القوالب كلّها — مثال: تقرير متابعة" />
      </div>

      {searching ? (
        <SearchResults term={dq} hits={hits} loading={lt} onClear={() => setQ('')} />
      ) : lf ? (
        <SkeletonCards n={6} />
      ) : !folders?.length ? (
        <EmptyState
          art={<IcFolderFill size={62} />}
          title="لا مجلّدات بعد"
          line="القوالب تُرتَّب في مجلّدات من لوحة الإدارة. أضف مجلّدًا ثمّ ضع فيه قوالبك."
        />
      ) : (
        <>
          <h2 className="mdd-lib-h">المجلّدات</h2>
          <div className="mdd-folders">
            {folders.map((f) => (
              <FolderCard key={f.id} f={f} onOpen={() => nav(`/app/library/${f.slug}`)} />
            ))}
          </div>

          {loose.length > 0 && (
            <>
              <h2 className="mdd-lib-h" style={{ marginBlockStart: 'var(--mdd-s-7)' }}>
                بلا مجلّد
              </h2>
              <div className="mdd-grid mdd-grid--3">
                {loose.map((t) => <TemplateCard key={t.id} t={t} />)}
              </div>
            </>
          )}
        </>
      )}
    </>
  )
}

/* ═════════════════ بطاقة المجلّد ═════════════════ */

function FolderCard({ f, onOpen }: { f: TemplateFolder; onOpen: () => void }) {
  const n = f.template_count ?? 0
  return (
    <button
      type="button" className="mdd-folder" onClick={onOpen}
      style={{ ['--fc' as any]: f.accent }}
      aria-label={`افتح مجلّد ${f.name}`}>
      <span className="mdd-folder-ic"><IcFolderFill size={26} /></span>
      <span className="mdd-folder-txt">
        <span className="mdd-folder-name">{f.name}</span>
        <span className="mdd-folder-meta">{counted(n, TPL)}</span>
      </span>
      <span className="mdd-folder-go"><IcChevron size={15} /></span>
    </button>
  )
}

/* ═════════════════ داخل المجلّد ═════════════════ */

function FolderView({ slug }: { slug: string }) {
  const nav = useNavigate()
  const [q, setQ] = useState('')
  const dq = useDebounced(q)

  const { data: folders, loading: lf, error: ef, reload: rf } = useAsync(fetchFolders, [])
  const { data: templates, loading: lt, error: et, reload: rt } = useAsync(fetchTemplates, [])

  const folder = useMemo(() => (folders || []).find((f) => f.slug === slug) || null, [folders, slug])

  const list = useMemo(() => {
    let out = (templates || []).filter((t) => t.folder_id && folder && t.folder_id === folder.id)
    const term = dq.trim()
    if (term) out = out.filter((t) => t.title.includes(term) || (t.description || '').includes(term))
    return out
  }, [templates, folder, dq])

  if (ef || et) return <ErrorState onRetry={() => { rf(); rt() }} message={ef || et || ''} />

  if (!lf && !folder) {
    return (
      <EmptyState
        art={<IcFolderFill size={62} />}
        title="لم نجد هذا المجلّد"
        line="ربّما أُزيل أو غُيّر اسمه."
        action={<Button variant="primary" onClick={() => nav('/app/library')}>عُد إلى المكتبة</Button>}
      />
    )
  }

  return (
    <>
      <div className="mdd-crumb mdd-noprint">
        <button type="button" onClick={() => nav('/app/library')} className="mdd-crumb-back">
          <IcBack size={15} /><span>المكتبة</span>
        </button>
        {folder && (
          <>
            <IcChevron size={13} className="mdd-crumb-sep" />
            <span className="mdd-crumb-now" style={{ ['--fc' as any]: folder.accent }}>
              <IcFolderFill size={15} />
              <span>{folder.name}</span>
            </span>
          </>
        )}
      </div>

      <PageHead
        title={folder?.name || '…'}
        sub={folder?.blurb || (lt ? 'جارٍ التحميل…' : counted(list.length, TPL))}
      />

      {(templates?.filter((t) => folder && t.folder_id === folder.id).length ?? 0) > 8 && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
          <SearchInput value={q} onChange={setQ} placeholder={`ابحث في ${folder?.name || 'المجلّد'}`} />
        </div>
      )}

      {lt ? (
        <SkeletonCards n={6} />
      ) : list.length === 0 ? (
        <EmptyState
          art={<IcSearch size={58} />}
          title={dq ? `لا نتائج لـ «${dq}»` : 'المجلّد فارغ'}
          line={dq ? 'جرّب كلمةً أقصر.' : 'تُضاف القوالب إلى هذا المجلّد من لوحة الإدارة.'}
          action={dq ? <Button variant="primary" onClick={() => setQ('')}>امسح البحث</Button> : undefined}
        />
      ) : (
        <div className="mdd-grid mdd-grid--3">
          {list.map((t) => <TemplateCard key={t.id} t={t} accent={folder?.accent} />)}
        </div>
      )}
    </>
  )
}

/* ═════════════════ نتائج البحث الشامل ═════════════════ */

function SearchResults({ term, hits, loading, onClear }: {
  term: string; hits: Template[]; loading: boolean; onClear: () => void
}) {
  if (loading) return <SkeletonCards n={6} />
  if (!hits.length) {
    return (
      <EmptyState
        art={<IcSpark size={58} />}
        title={`لم نجد قالبًا باسم «${term}»`}
        line="جرّب كلمةً أقصر، أو تصفّح المجلّدات."
        action={<Button variant="primary" onClick={onClear}>امسح البحث</Button>}
      />
    )
  }
  return (
    <>
      <h2 className="mdd-lib-h">
        {counted(hits.length, { one: 'نتيجةٌ واحدة', two: 'نتيجتان', few: 'نتائج', many: 'نتيجة' })}
      </h2>
      <div className="mdd-grid mdd-grid--3">
        {hits.map((t) => <TemplateCard key={t.id} t={t} />)}
      </div>
    </>
  )
}

/* ═════════════════ بطاقة القالب ═════════════════ */

export function TemplateCard({ t, accent }: { t: Template; accent?: string }) {
  const nav = useNavigate()
  return (
    <Card
      className="mdd-tplc mdd-card--action" onClick={() => nav(`/app/template/${t.slug}`)}
      style={{ ['--fc' as any]: accent || 'var(--mdd-accent)', cursor: 'pointer' }}>
      <span className="mdd-tplc-ic"><IcPage size={20} /></span>
      <h3 className="mdd-tplc-t">{t.title}</h3>
      {t.description && <p className="mdd-tplc-d">{t.description}</p>}
      <span className="mdd-tplc-foot">
        {t.is_new && <Badge tone="info">جديد</Badge>}
        <span className="mdd-tplc-min">
          نحو <span className="mdd-num">{t.estimated_minutes}</span> دقائق
        </span>
        <IcChevron size={14} className="mdd-tplc-go" />
      </span>
    </Card>
  )
}
