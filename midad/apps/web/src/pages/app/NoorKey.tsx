import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { callFunction, supabase } from '../../lib/supabase'
import type { LinkKey } from '../../lib/types'
import { daysBetween, daysLabel, fmtBoth, fmtNum, fmtRelative, fmtShort } from '../../lib/format'
import {
  Alert, Badge, Button, Card, ConfirmModal, CopyButton, EmptyState, ErrorState,
  IconButton, PageHead, Skeleton, SkeletonRows,
} from '../../ui/kit'
import { IcDownload, IcEye, IcEyeOff, IcKey, IcPuzzle, IcTable } from '../../ui/icons'
import { EXTENSION_FILE } from './NoorList'

const EXTENSION_VERSION = '1.4'

interface IngestRow { id: string; title: string | null; row_count: number; created_at: string }

export default function NoorKey() {
  const { profile, subscriber, toast } = useApp()
  const nav = useNavigate()
  const sid = subscriber?.id

  const [shown, setShown] = useState(false)
  const [askNew, setAskNew] = useState(false)
  const [askRevoke, setAskRevoke] = useState(false)
  const [busy, setBusy] = useState(false)

  const { data, loading, error, reload } = useAsync(async () => {
    if (!sid || !profile) return null
    const [keyRes, logRes] = await Promise.all([
      supabase.from('link_keys').select('*')
        .eq('user_id', profile.id).is('revoked_at', null)
        .order('created_at', { ascending: false }).limit(1),
      supabase.from('noor_ingest_log').select('id,title,row_count,created_at')
        .eq('subscriber_id', sid).order('created_at', { ascending: false }).limit(10),
    ])
    if (keyRes.error) throw new Error(keyRes.error.message)
    return {
      key: ((keyRes.data || [])[0] as LinkKey) || null,
      log: (logRes.data || []) as IngestRow[],
    }
  }, [sid, profile?.id])

  const key = data?.key || null
  const log = data?.log || []

  const daysLeft = key ? daysBetween(new Date(), key.expires_at) : 0
  const state: 'none' | 'valid' | 'soon' | 'expired' =
    !key ? 'none' : daysLeft <= 0 ? 'expired' : daysLeft < 14 ? 'soon' : 'valid'

  async function createKey() {
    setBusy(true)
    try {
      await callFunction('noor', { action: 'create_key' })
      toast('أُنشئ مفتاح ربطٍ جديد — ألصقه في الإضافة')
      setAskNew(false); setShown(true); reload()
    } catch (e: any) {
      toast(e?.message || 'تعذّر إنشاء المفتاح', 'danger')
    } finally { setBusy(false) }
  }

  async function revokeKey() {
    setBusy(true)
    try {
      await callFunction('noor', { action: 'revoke_key' })
      toast('أُلغي المفتاح — توقّفت الإضافة عن الإرسال')
      setAskRevoke(false); setShown(false); reload()
    } catch (e: any) {
      toast(e?.message || 'تعذّر إلغاء المفتاح', 'danger')
    } finally { setBusy(false) }
  }

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="الإضافة ومفتاح الربط"
        sub="ثبّت الإضافة مرّةً، وألصق المفتاح مرّةً — ثمّ لا تعود إلى هذه الشاشة إلّا للتجديد."
        actions={<Button auto variant="secondary" onClick={() => nav('/app/noor')}>جداول نور</Button>}
      />

      {state === 'expired' && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
          <Alert tone="danger">
            انتهت صلاحية مفتاحك في {fmtShort(key?.expires_at)} — الإضافة لا ترسل شيئًا حتّى تجدّده.
          </Alert>
        </div>
      )}
      {state === 'soon' && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
          <Alert tone="warn">
            يقارب مفتاحك الانتهاء — بقي {daysLabel(daysLeft)}. جدّده الآن حتّى لا تتوقّف عنك الجداول.
          </Alert>
        </div>
      )}

      <div className="mdd-grid mdd-grid--2" style={{ alignItems: 'start' }}>
        {/* بطاقة الإضافة */}
        <Card className="mdd-col" style={{ gap: 14 }}>
          <div className="mdd-row" style={{ gap: 12 }}>
            <span style={{
              width: 44, height: 44, borderRadius: 13, display: 'grid', placeItems: 'center',
              background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)', flex: 'none',
            }}><IcPuzzle size={21} /></span>
            <div style={{ minWidth: 0 }}>
              <h2 style={{ fontSize: 17 }}>إضافة مِداد لنور</h2>
              <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>لمتصفّح كروم أو إيدج</span>
            </div>
          </div>

          <div className="mdd-col" style={{ gap: 10 }}>
            <InfoLine label="الحالة" value={
              loading ? <Skeleton h={14} w={90} />
                : key?.last_used_at
                  ? <Badge tone="success" dot>تعمل — آخر إرسال {fmtRelative(key.last_used_at)}</Badge>
                  : key
                    ? <Badge tone="info" dot>مفتاحٌ جاهز، ولم يصل منها جدولٌ بعد</Badge>
                    : <Badge tone="neutral" dot>غير مربوطة</Badge>
            } />
            <InfoLine label="الإصدار" value={<span className="mdd-num">{EXTENSION_VERSION}</span>} />
          </div>

          <a className="mdd-btn mdd-btn--secondary" href={EXTENSION_FILE} download>
            <IcDownload size={15} /> نزّل مرّة أخرى
          </a>

          <p className="mdd-prose" style={{ fontSize: 12.5 }}>
            الإضافة تقرأ الجدول المعروض أمامك وحده، ولا تطلب كلمة مرور نور ولا تخزّنها.
            <Link to="/service/noor" style={{ color: 'var(--mdd-accent)', fontWeight: 600 }}> اقرأ التفاصيل</Link>
          </p>
        </Card>

        {/* بطاقة المفتاح */}
        <Card className="mdd-col" style={{ gap: 14 }}>
          <div className="mdd-row mdd-row--between">
            <div className="mdd-row" style={{ gap: 12 }}>
              <span style={{
                width: 44, height: 44, borderRadius: 13, display: 'grid', placeItems: 'center',
                background: 'var(--mdd-accent-soft)', color: 'var(--mdd-accent-soft-fg)', flex: 'none',
              }}><IcKey size={21} /></span>
              <div style={{ minWidth: 0 }}>
                <h2 style={{ fontSize: 17 }}>مفتاح الربط</h2>
                <span style={{ fontSize: 12, color: 'var(--mdd-text-3)' }}>خاصٌّ بك وحدك — لا تشاركه</span>
              </div>
            </div>
            {state === 'valid' && <Badge tone="success" dot>ساري</Badge>}
            {state === 'soon' && <Badge tone="warn" dot>يقارب الانتهاء</Badge>}
            {state === 'expired' && <Badge tone="danger" dot>منتهٍ</Badge>}
          </div>

          {loading ? (
            <div className="mdd-col" style={{ gap: 10 }}>
              <Skeleton h={44} style={{ borderRadius: 10 }} />
              <Skeleton h={14} w="70%" /><Skeleton h={14} w="55%" />
            </div>
          ) : !key ? (
            <div className="mdd-col" style={{ gap: 12 }}>
              <p className="mdd-prose" style={{ fontSize: 13 }}>
                لا مفتاح لك بعد. أنشئ مفتاحًا وألصقه في الإضافة مرّةً واحدة، فتبدأ الجداول بالوصول.
              </p>
              <Button variant="primary" loading={busy} onClick={createKey}>أنشئ مفتاحًا</Button>
            </div>
          ) : (
            <>
              <div className="mdd-row" style={{ gap: 8 }}>
                <span className="mdd-mono" style={{
                  flex: 1, minWidth: 0, fontSize: 13, padding: '11px 13px',
                  borderRadius: 'var(--mdd-r-sm)', background: 'var(--mdd-sunken)',
                  border: '1px solid var(--mdd-border)', overflowX: 'auto', whiteSpace: 'nowrap',
                  opacity: state === 'expired' ? 0.6 : 1,
                }}>
                  {shown ? key.key : '•'.repeat(Math.min(28, key.key.length))}
                </span>
                <IconButton label={shown ? 'إخفاء المفتاح' : 'إظهار المفتاح'} onClick={() => setShown((s) => !s)}>
                  {shown ? <IcEyeOff size={15} /> : <IcEye size={15} />}
                </IconButton>
                <CopyButton text={key.key} />
              </div>

              <div className="mdd-col" style={{ gap: 10 }}>
                <InfoLine label="أُصدر في" value={<span>{fmtBoth(key.created_at)}</span>} />
                <InfoLine label="ينتهي في" value={
                  <span style={{ color: state === 'expired' ? 'var(--mdd-danger-fg)' : state === 'soon' ? 'var(--mdd-warn-fg)' : undefined }}>
                    {fmtBoth(key.expires_at)}{state !== 'expired' && ` · بقي ${daysLabel(daysLeft)}`}
                  </span>
                } />
                <InfoLine label="آخر استعمال" value={<span>{key.last_used_at ? fmtRelative(key.last_used_at) : 'لم يُستعمل بعد'}</span>} />
              </div>

              <div className="mdd-row mdd-row--wrap" style={{ gap: 10 }}>
                <Button auto variant={state === 'expired' ? 'primary' : 'secondary'} onClick={() => setAskNew(true)}>
                  {state === 'expired' ? 'جدّد المفتاح' : 'أنشئ مفتاحًا جديدًا'}
                </Button>
                <Button auto variant="danger" onClick={() => setAskRevoke(true)}>ألغِ المفتاح</Button>
              </div>
            </>
          )}
        </Card>
      </div>

      {/* سجلّ التنزيلات */}
      <div className="mdd-col" style={{ gap: 12, marginBlockStart: 'var(--mdd-s-6)' }}>
        <h2 style={{ fontSize: 18 }}>سجلّ التنزيلات</h2>
        {loading ? (
          <SkeletonRows n={4} />
        ) : log.length === 0 ? (
          <EmptyState
            art={<IcTable size={54} />}
            title="لم يصل جدولٌ بعد"
            line="بعد أن تلصق المفتاح في الإضافة وتضغط «أرسل إلى مِداد» من نور، تظهر هنا آخر عشر عمليات."
            action={<Button variant="secondary" onClick={() => nav('/app/noor')}>افتح جداول نور</Button>}
          />
        ) : (
          <div className="mdd-table-wrap mdd-table-wrap--cards">
            <table className="mdd-table">
              <thead>
                <tr>
                  <th>الجدول</th>
                  <th>الوقت</th>
                  <th>عدد الصفوف</th>
                </tr>
              </thead>
              <tbody>
                {log.map((r) => (
                  <tr key={r.id}>
                    <td data-label="الجدول"><span style={{ fontWeight: 600 }}>{r.title || 'جدول من نور'}</span></td>
                    <td data-label="الوقت"><span title={fmtShort(r.created_at)}>{fmtRelative(r.created_at)}</span></td>
                    <td data-label="عدد الصفوف"><span className="mdd-num">{fmtNum(r.row_count || 0)}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <ConfirmModal
        open={askNew} onClose={() => setAskNew(false)} onConfirm={createKey} loading={busy}
        title="مفتاح ربطٍ جديد" confirmLabel="أنشئ المفتاح"
        body="سيتوقّف مفتاحك الحاليّ فورًا، ولن ترسل الإضافة شيئًا حتّى تلصق المفتاح الجديد فيها. جداولك المنزَّلة لا تتأثّر."
      />
      <ConfirmModal
        open={askRevoke} onClose={() => setAskRevoke(false)} onConfirm={revokeKey} loading={busy} danger
        title="إلغاء مفتاح الربط" confirmLabel="ألغِ المفتاح"
        body="تتوقّف الإضافة عن الإرسال في الحال، وتبقى جداولك المنزَّلة كما هي. تستطيع إنشاء مفتاحٍ جديدٍ متى شئت."
      />
    </>
  )
}

function InfoLine({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="mdd-row mdd-row--between" style={{ gap: 12, fontSize: 13 }}>
      <span style={{ color: 'var(--mdd-text-3)', fontSize: 12.5 }}>{label}</span>
      <span style={{ minWidth: 0, textAlign: 'start' }}>{value}</span>
    </div>
  )
}
