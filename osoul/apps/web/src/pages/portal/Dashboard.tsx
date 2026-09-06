import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { fetchStageCounts, STAGES } from '../../lib/data'
import { fmtNum } from '../../lib/format'
import { ErrorState, Skeleton } from '../../ui/kit'

export default function Dashboard() {
  const { t, profile, role } = useApp()
  const { data, loading, error, reload } = useAsync(fetchStageCounts, [])

  const total = data ? Object.values(data).reduce((a, b) => a + b, 0) : 0

  return (
    <>
      <div className="osl-pagehead">
        <h1>{t('لوحة القيادة')}</h1>
        <p>{t('أهلًا')} {profile?.full_name || ''}</p>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : (
        <div className="osl-grid osl-grid--3 osl-stat-grid">
          {STAGES.map((s) => (
            <Link key={s.key} to={`/dashboard/quotes?stage=${s.key}`} className="osl-statcard">
              <span className="osl-statcard__dot" data-stage={s.key} aria-hidden="true" />
              <b className="osl-num">
                {loading ? <Skeleton h={26} w={44} /> : fmtNum(data?.[s.key] ?? 0)}
              </b>
              <span>{t(s.ar)}</span>
            </Link>
          ))}
        </div>
      )}

      <p className="osl-note">
        {t('المجموع')}: <span className="osl-num">{loading ? '…' : fmtNum(total)}</span> {t('طلبًا')}
        {role !== 'admin' ? ' — ' + t('ما يخصّك وحدك.') : ''}
      </p>
    </>
  )
}
