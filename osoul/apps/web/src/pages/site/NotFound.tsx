import { Link } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { Container, EmptyState } from '../../ui/kit'

export default function NotFound() {
  const { t } = useApp()
  return (
    <Container>
      <EmptyState
        title={t('لم نجد هذه الصفحة')}
        line={t('ربّما تغيّر العنوان أو حُذفت الصفحة. جرّب البدء من الرئيسية.')}
        action={<Link to="/" className="osl-btn osl-btn--primary">{t('الرئيسية')}</Link>}
      />
    </Container>
  )
}
