import React, { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { callFunction, supabase } from '../../lib/supabase'
import { fetchNoorTables, fetchTeam } from '../../lib/data'
import type { LinkKey, NoorTable, Profile } from '../../lib/types'
import { fmtNum, fmtRelative, fmtShort } from '../../lib/format'
import { buildXlsx, download, safeFileName } from '../../lib/export'
import {
  Alert, Badge, Button, Card, ConfirmModal, CopyButton, ErrorState, IconButton,
  PageHead, SearchInput, Select, SkeletonRows,
} from '../../ui/kit'
import { IcTable, IcTrash, IcFileExcel } from '../../ui/icons'
import PasteTable from './PasteTable'

type SortKey = 'recent' | 'name' | 'rows'
type SrcKey = 'all' | 'noor' | 'madrasati'

/** أسماء البوّابات وألوانها — في موضعٍ واحد فلا تختلف بين شاشةٍ وأخرى. */
export const SOURCES: Record<'noor' | 'madrasati', { name: string; tone: 'accent' | 'info' }> = {
  noor: { name: 'نور', tone: 'accent' },
  madrasati: { name: 'مدرستي', tone: 'info' },
}

/** وسمُ البوّابة. والقديم بلا مصدرٍ محفوظ من نور — فذاك ما كان يُدعم. */
export function SourceTag({ source }: { source?: string }) {
  const s = SOURCES[(source === 'madrasati' ? 'madrasati' : 'noor')]
  return <Badge tone={s.tone}>{s.name}</Badge>
}

/** ملفّ الإضافة يُنزَّل من المنصّة نفسها. */
export const EXTENSION_FILE = '/downloads/midad-noor.zip'

function browserKind(): 'ok' | 'safari' | 'firefox' {
  const ua = typeof navigator !== 'undefined' ? navigator.userAgent : ''
  if (/firefox|fxios/i.test(ua)) return 'firefox'
  if (/safari/i.test(ua) && !/chrome|chromium|crios|edg|opr|android/i.test(ua)) return 'safari'
  return 'ok'
}

export default function NoorList() {
  const { subscriber, profile, toast } = useApp()
  const nav = useNavigate()
  const sid = subscriber?.id

  const [q, setQ] = useState('')
  const [sort, setSort] = useState<SortKey>('recent')
  const [src, setSrc] = useState<SrcKey>('all')
  const [pasting, setPasting] = useState(false)
  const [deleting, setDeleting] = useState<NoorTable | null>(null)
  const [busy, setBusy] = useState(false)
  const [making, setMaking] = useState(false)
  const dq = useDebounced(q)
  const browser = browserKind()

  const { data, loading, error, reload, setData } = useAsync(async () => {
    if (!sid || !profile) return null
    const [tables, team, keyRes] = await Promise.all([
      fetchNoorTables(sid),
      fetchTeam(sid),
      supabase.from('link_keys').select('*')
        .eq('user_id', profile.id).is('revoked_at', null)
        .gt('expires_at', new Date().toISOString())
        .order('created_at', { ascending: false }).limit(1),
    ])
    return { tables, team, key: ((keyRes.data || [])[0] as LinkKey) || null }
  }, [sid, profile?.id])

  const tables = data?.tables || []
  const team: Profile[] = data?.team || []
  const linkKey = data?.key || null

  const nameById = useMemo(() => {
    const m = new Map<string, string>()
    for (const p of team) m.set(p.id, p.full_name)
    return m
  }, [team])

  const filtered = useMemo(() => {
    const term = dq.trim()
    let list = term ? tables.filter((t) => t.title.includes(term)) : tables
    // القديم بلا مصدرٍ محفوظ يُحسب من نور — فذاك ما كان يُدعم يوم حُفظ
    if (src !== 'all') list = list.filter((t) => (t.source || 'noor') === src)
    list = [...list].sort((a, b) =>
      sort === 'name' ? a.title.localeCompare(b.title, 'ar')
        : sort === 'rows' ? (b.row_count || 0) - (a.row_count || 0)
          : new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
    return list
  }, [tables, dq, sort, src])

  function exportXlsx(t: NoorTable) {
    const blob = buildXlsx(t.title, t.columns || [], (t.rows || []) as string[][])
    download(blob, `${safeFileName(t.title)}.xlsx`)
  }

  async function doDelete() {
    if (!deleting) return
    setBusy(true)
    const { error: e } = await supabase.from('noor_tables').delete().eq('id', deleting.id)
    setBusy(false)
    if (e) { toast('تعذّر حذف الجدول', 'danger'); return }
    setDeleting(null); toast('حُذف الجدول'); reload()
  }

  async function createKey() {
    setMaking(true)
    try {
      const res = await callFunction<{ link_key: LinkKey }>('noor', { action: 'create_key' })
      setData({ tables, team, key: res.link_key })
      toast('أُنشئ مفتاح الربط')
    } catch (e: any) {
      toast(e?.message || 'تعذّر إنشاء المفتاح', 'danger')
    } finally {
      setMaking(false)
    }
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  const noneAtAll = !loading && tables.length === 0
  const noResults = !loading && tables.length > 0 && filtered.length === 0

  return (
    <>
      <PageHead
        title="جداول نور ومدرستي"
        sub={loading ? 'جارٍ التحميل…' : `${fmtNum(tables.length)} جدولًا · ${fmtNum(tables.reduce((a, t) => a + (t.row_count || 0), 0))} صفًّا`}
        actions={
          <div className="mdd-row" style={{ gap: 8 }}>
            <Button auto variant="secondary" onClick={() => nav('/app/noor/key')}>كيف أنزّل جدولًا؟</Button>
            {/* بابٌ لا يحتاج تثبيتًا: يُظلَّل الجدول في نور ويُنسخ ويُلصق هنا. */}
            <Button auto variant="primary" onClick={() => setPasting(true)}>الصق جدولًا</Button>
          </div>
        }
      />

      {browser !== 'ok' && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
          <Alert tone="warn">
            <strong>متصفّحك لا يشغّل إضافة مِداد.</strong>
            <div style={{ marginBlockStart: 6, fontSize: 13, lineHeight: 1.9 }}>
              أنت تتصفّح بـ {browser === 'safari' ? 'سفاري' : 'فايرفوكس'}، والإضافة تعمل على كروم أو إيدج وحدهما.
              افتح مِداد في كروم أو إيدج، ثبّت الإضافة هناك، ثمّ نزّل جداولك.
              الجداول التي نزّلتها من قبل تبقى معروضةً هنا في أيّ متصفّح.
            </div>
          </Alert>
        </div>
      )}

      {!noneAtAll && !loading && (
        <div className="mdd-row mdd-row--wrap" style={{ gap: 10, marginBlockEnd: 'var(--mdd-s-5)' }}>
          <div style={{ flex: 1, minWidth: 220 }}>
            <SearchInput value={q} onChange={setQ} placeholder="ابحث باسم الجدول" />
          </div>
          <Select value={src} onChange={(e) => setSrc(e.target.value as SrcKey)} aria-label="البوّابة">
            <option value="all">البوّابتان</option>
            <option value="noor">نور</option>
            <option value="madrasati">مدرستي</option>
          </Select>
          <Select value={sort} onChange={(e) => setSort(e.target.value as SortKey)} aria-label="الترتيب">
            <option value="recent">الأحدث تنزيلًا</option>
            <option value="name">حسب الاسم</option>
            <option value="rows">الأكثر صفوفًا</option>
          </Select>
        </div>
      )}

      {loading ? (
        <SkeletonRows n={5} />
      ) : noneAtAll ? (
        <ConnectSteps
          linkKey={linkKey}
          making={making}
          onCreate={createKey}
        />
      ) : noResults ? (
        <Card className="mdd-col" style={{ alignItems: 'center', gap: 10, padding: 'var(--mdd-s-7)' }}>
          <h3 style={{ fontSize: 16 }}>لا جدول يطابق «{q.trim()}»</h3>
          <p className="mdd-prose" style={{ fontSize: 13 }}>جرّب كلمةً أقصر، أو امسح البحث لترى كلّ جداولك.</p>
          <Button auto variant="primary" onClick={() => setQ('')}>امسح البحث</Button>
        </Card>
      ) : (
        <div className="mdd-table-wrap mdd-table-wrap--cards">
          <table className="mdd-table">
            <thead>
              <tr>
                <th>الجدول</th>
                <th>البوّابة</th>
                <th>الصفوف</th>
                <th>الأعمدة</th>
                <th>تاريخ التنزيل</th>
                <th>من أنزله</th>
                <th style={{ width: 150 }}>أفعال</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((t) => (
                <tr key={t.id}>
                  <td data-label="الجدول">
                    <Link to={`/app/noor/${t.id}`} className="mdd-row" style={{ gap: 9, fontWeight: 600, color: 'var(--mdd-text)' }}>
                      <IcTable size={16} />{t.title}
                    </Link>
                  </td>
                  <td data-label="البوّابة"><SourceTag source={t.source} /></td>
                  <td data-label="الصفوف"><span className="mdd-num">{fmtNum(t.row_count || 0)}</span></td>
                  <td data-label="الأعمدة"><span className="mdd-num">{fmtNum((t.columns || []).length)}</span></td>
                  <td data-label="تاريخ التنزيل"><span title={fmtShort(t.created_at)}>{fmtRelative(t.created_at)}</span></td>
                  <td data-label="من أنزله"><span className="mdd-muted">{nameById.get(t.owner_id) || '—'}</span></td>
                  <td data-label="أفعال">
                    <div className="mdd-row" style={{ gap: 6 }}>
                      <Button auto size="sm" variant="secondary" onClick={() => nav(`/app/noor/${t.id}`)}>فتح</Button>
                      <IconButton label="تصدير إكسل" onClick={() => exportXlsx(t)}><IcFileExcel size={16} /></IconButton>
                      <IconButton label="حذف" onClick={() => setDeleting(t)}><IcTrash size={14} /></IconButton>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <ConfirmModal
        open={!!deleting} onClose={() => setDeleting(null)} onConfirm={doDelete} loading={busy} danger
        title="حذف الجدول" confirmLabel="احذف"
        body={`سيُحذف «${deleting?.title || ''}» من مِداد. لا يتأثّر شيءٌ في نظام نور، ويمكنك تنزيله مرّة أخرى متى شئت.`}
      />
    {pasting && (
        <PasteTable onClose={() => setPasting(false)}
          onSaved={() => { setPasting(false); reload() }} />
      )}
    </>
  )
}

/* ============ الحالة الفارغة: اربط نور في ثلاث خطوات ============ */
function ConnectSteps({ linkKey, making, onCreate }: {
  linkKey: LinkKey | null; making: boolean; onCreate: () => void
}) {
  return (
    <div className="mdd-col" style={{ gap: 'var(--mdd-s-6)' }}>
      <div className="mdd-col" style={{ gap: 8, textAlign: 'center', alignItems: 'center' }}>
        <h2 style={{ fontSize: 24 }}>اربط نور في ثلاث خطوات</h2>
        <p className="mdd-prose" style={{ fontSize: 14, maxWidth: 560 }}>
          لا نطلب حسابك في نور ولا كلمة مروره. تفتح الكشف بنفسك، وتضغط زرًّا واحدًا، فيصل الجدول إلى مِداد.
        </p>
      </div>

      <div className="mdd-grid mdd-grid--3" style={{ alignItems: 'stretch' }}>
        <StepCard n={1} title="نزّل الإضافة" art={<ArtPuzzle />}
          line="إضافة مِداد لمتصفّح كروم أو إيدج — تُثبَّت مرّةً واحدة.">
          <a className="mdd-btn mdd-btn--primary mdd-btn--sm" href={EXTENSION_FILE} download>نزّل الإضافة</a>
        </StepCard>

        <StepCard n={2} title="انسخ مفتاح الربط" art={<ArtKey />}
          line="ألصقه في الإضافة مرّةً واحدة، فتعرف أنّ الجداول لحسابك.">
          {linkKey ? (
            <div className="mdd-col" style={{ gap: 8, width: '100%' }}>
              <div className="mdd-mono" style={{
                fontSize: 12.5, padding: '10px 12px', borderRadius: 'var(--mdd-r-sm)',
                background: 'var(--mdd-sunken)', border: '1px solid var(--mdd-border)',
                overflowX: 'auto', whiteSpace: 'nowrap',
              }}>{linkKey.key}</div>
              <div className="mdd-row mdd-row--between">
                <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>صالح 90 يومًا</span>
                <CopyButton text={linkKey.key} label="انسخ المفتاح" />
              </div>
            </div>
          ) : (
            <Button auto size="sm" variant="primary" loading={making} onClick={onCreate}>أنشئ مفتاحًا</Button>
          )}
        </StepCard>

        <StepCard n={3} title="افتح نور واضغط «أرسل»" art={<ArtSend />}
          line="افتح الكشف في نور كالمعتاد، ثمّ اضغط «أرسل إلى مِداد» فيظهر هنا.">
          <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>يصل الجدول خلال ثوانٍ</span>
        </StepCard>
      </div>

      <div className="mdd-row" style={{ justifyContent: 'center' }}>
        <Link to="/contact" style={{ fontSize: 13, fontWeight: 700, color: 'var(--mdd-accent)' }}>واجهتني مشكلة</Link>
      </div>
    </div>
  )
}

function StepCard({ n, title, line, art, children }: {
  n: number; title: string; line: string; art: React.ReactNode; children?: React.ReactNode
}) {
  return (
    <Card className="mdd-col" style={{ gap: 14, alignItems: 'center', textAlign: 'center' }}>
      <span style={{
        width: 34, height: 34, borderRadius: 'var(--mdd-r-pill)', display: 'grid', placeItems: 'center',
        background: 'var(--mdd-accent)', color: 'var(--mdd-on-accent)', fontWeight: 700, fontSize: 15,
      }} className="mdd-num">{n}</span>
      <span style={{ color: 'var(--mdd-accent-soft-fg)' }}>{art}</span>
      <div>
        <h3 style={{ fontSize: 16 }}>{title}</h3>
        <p className="mdd-prose" style={{ fontSize: 12.5, marginBlockStart: 6, lineHeight: 1.8 }}>{line}</p>
      </div>
      <div className="mdd-col" style={{ gap: 8, alignItems: 'center', width: '100%', marginBlockStart: 'auto' }}>{children}</div>
    </Card>
  )
}

function ArtPuzzle() {
  return (
    <svg width="82" height="66" viewBox="0 0 82 66" fill="none" aria-hidden="true">
      <rect x="5" y="7" width="72" height="52" rx="8" stroke="currentColor" strokeWidth="2.2" />
      <path d="M5 19h72" stroke="currentColor" strokeWidth="2.2" />
      <circle cx="14" cy="13" r="2" fill="currentColor" />
      <circle cx="22" cy="13" r="2" fill="currentColor" />
      <path d="M34 30h10v-3a4 4 0 1 1 8 0v3h10v10h-3a4 4 0 1 0 0 8h3v10H34v-9a4 4 0 1 1 0-8Z"
        stroke="currentColor" strokeWidth="2.2" strokeLinejoin="round" />
    </svg>
  )
}
function ArtKey() {
  return (
    <svg width="82" height="66" viewBox="0 0 82 66" fill="none" aria-hidden="true">
      <circle cx="22" cy="33" r="12" stroke="currentColor" strokeWidth="2.4" />
      <circle cx="22" cy="33" r="4" fill="currentColor" />
      <path d="M34 33h34v10M58 33v7" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" />
      <rect x="8" y="9" width="66" height="7" rx="3.5" stroke="currentColor" strokeWidth="2" opacity=".5" />
    </svg>
  )
}
function ArtSend() {
  return (
    <svg width="82" height="66" viewBox="0 0 82 66" fill="none" aria-hidden="true">
      <rect x="6" y="9" width="42" height="48" rx="5" stroke="currentColor" strokeWidth="2.2" />
      <path d="M6 21h42M20 21v36" stroke="currentColor" strokeWidth="2.2" />
      <path d="M52 33h20M64 25l8 8-8 8" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}
