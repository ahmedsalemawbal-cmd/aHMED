import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync, useDebounced } from '../../lib/hooks'
import { fetchQuotes, SOURCE_LABELS, STAGES } from '../../lib/data'
import { fmtShort } from '../../lib/format'
import type { QuoteStage } from '../../lib/types'
import { Badge, EmptyState, ErrorState, Input, Skeleton } from '../../ui/kit'

const TONE: Record<QuoteStage, any> = {
  new: 'info', pricing: 'warn', sent: 'accent',
  negotiation: 'info', won: 'success', lost: 'danger',
}

export default function Quotes() {
  const { t, lang, role } = useApp()
  const [params, setParams] = useSearchParams()
  const stage = (params.get('stage') || '') as QuoteStage | ''
  const [raw, setRaw] = useState('')
  const search = useDebounced(raw, 300)

  const { data, loading, error, reload } = useAsync(
    () => fetchQuotes({ stage: stage || undefined, search }), [stage, search])

  const label = (s: typeof STAGES[number]) =>
    lang === 'ar' ? s.ar : lang === 'ur' ? s.ur : s.en

  function pick(k: string) {
    const p = new URLSearchParams(params)
    if (k) p.set('stage', k); else p.delete('stage')
    setParams(p, { replace: true })
  }

  return (
    <>
      <div className="osl-pagehead">
        <h1>{t('الطلبات')}</h1>
        <p>{t('ما يخصّك — والباقي لا يصلك من القاعدة أصلًا.')}</p>
      </div>

      <div className="osl-filters">
        <div className="osl-chips">
          <button type="button" className={'osl-chip' + (!stage ? ' is-on' : '')}
                  onClick={() => pick('')}>{t('الكل')}</button>
          {STAGES.map((s) => (
            <button key={s.key} type="button"
                    className={'osl-chip' + (stage === s.key ? ' is-on' : '')}
                    onClick={() => pick(s.key)}>{label(s)}</button>
          ))}
        </div>
        <Input value={raw} onChange={(e) => setRaw(e.target.value)}
               placeholder={t('ابحث بالاسم أو الجوال أو رقم العرض')} />
      </div>

      {loading ? (
        <div className="osl-card osl-card--pad"><Skeleton h={18} /><Skeleton h={18} /><Skeleton h={18} /></div>
      ) : error ? (
        <ErrorState message={error} onRetry={reload} />
      ) : !data?.length ? (
        <EmptyState
          title={t(stage || search ? 'لا نتائج لهذا الترشيح' : 'لا طلبات بعد')}
          line={t(stage || search
            ? 'جرّب مرحلةً أخرى أو امسح البحث.'
            : 'حين يصل طلبٌ من الموقع أو من مندوب، يظهر هنا.')}
        />
      ) : (
        <div className="osl-table-wrap">
          <table className="osl-table">
            <thead>
              <tr>
                <th>{t('العميل')}</th>
                <th>{t('رقم العرض')}</th>
                <th>{t('المصدر')}</th>
                <th>{t('المرحلة')}</th>
                <th>{t('التاريخ')}</th>
              </tr>
            </thead>
            <tbody>
              {data.map((q) => {
                const st = STAGES.find((s) => s.key === q.stage)!
                return (
                  <tr key={q.id}>
                    <td data-label={t('العميل')}>
                      <Link to={`/dashboard/quote/${q.id}`}>
                        <b>{q.customer_name || t('بلا اسم')}</b>
                      </Link>
                      {q.customer_phone
                        ? <><br /><span className="osl-num osl-muted" dir="ltr">{q.customer_phone}</span></>
                        : null}
                    </td>
                    <td data-label={t('رقم العرض')}>
                      {q.number ? <span className="osl-num">{q.number}</span>
                                : <span className="osl-muted">{t('لم يصدر')}</span>}
                    </td>
                    <td data-label={t('المصدر')}>{t(SOURCE_LABELS[q.source] || q.source)}</td>
                    <td data-label={t('المرحلة')}>
                      <Badge tone={TONE[q.stage]}>{label(st)}</Badge>
                    </td>
                    <td data-label={t('التاريخ')}>
                      <span className="osl-num">{fmtShort(q.created_at)}</span>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}
