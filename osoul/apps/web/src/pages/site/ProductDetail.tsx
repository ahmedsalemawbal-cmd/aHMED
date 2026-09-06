import { Link, useParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { pick } from '../../lib/i18n'
import { useAsync } from '../../lib/hooks'
import { fetchProductBySlug } from '../../lib/data'
import { useCart } from '../../lib/cart'
import { GROUP_TITLES } from './ProductArchive'
import { Container, EmptyState, ErrorState, Skeleton } from '../../ui/kit'
import { IcCart, IcCheck, IcWhatsapp } from '../../ui/icons'

export default function ProductDetail() {
  const { slug = '' } = useParams()
  const { t } = useApp()
  const cart = useCart()
  const { data: p, loading, error, reload } = useAsync(() => fetchProductBySlug(slug), [slug])

  if (loading) {
    return (
      <Container>
        <div className="osl-pdetail">
          <Skeleton h={340} /><div><Skeleton h={28} /><Skeleton h={16} w="60%" /><Skeleton h={120} /></div>
        </div>
      </Container>
    )
  }
  if (error) return <Container><ErrorState message={error} onRetry={reload} /></Container>
  if (!p) {
    return (
      <Container>
        <EmptyState
          title={t('لم نجد هذا المنتج')}
          line={t('ربّما تغيّر رابطه. تصفّح العائلات الثلاث من القائمة.')}
          action={<Link to="/doors" className="osl-btn osl-btn--primary">{t('الأبواب')}</Link>}
        />
      </Container>
    )
  }

  const inCart = cart.lines.some((l) => l.slug === p.slug)
  const specs = (pick(p, 'specs') as unknown as string[]) || []
  const list = Array.isArray(specs) ? specs : []

  return (
    <Container>
      <nav className="osl-crumbs" aria-label={t('مسار الصفحة')}>
        <Link to="/">{t('الرئيسية')}</Link>
        <span aria-hidden="true">/</span>
        <Link to={`/${p.group_key}`}>{t(GROUP_TITLES[p.group_key].ar)}</Link>
      </nav>

      <div className="osl-pdetail">
        <div className="osl-pdetail__media">
          {p.image_url
            ? <img src={p.image_url} alt={pick(p, 'name')} />
            : <span className="osl-pcard__noimg" aria-hidden="true" />}
        </div>

        <div className="osl-pdetail__body">
          {pick(p, 'badge') ? <span className="osl-badge osl-badge--accent">{pick(p, 'badge')}</span> : null}
          <h1 className="osl-pdetail__h">{pick(p, 'name')}</h1>
          <p className="osl-pdetail__sub">{pick(p, 'sub')}</p>
          {pick(p, 'desc') ? <p className="osl-pdetail__desc">{pick(p, 'desc')}</p> : null}

          {list.length ? (
            <>
              <h2 className="osl-pdetail__h2">{t('المواصفات الفنية')}</h2>
              <ul className="osl-specs">
                {list.map((s, i) => (
                  <li key={i}><IcCheck size={15} /><span>{s}</span></li>
                ))}
              </ul>
            </>
          ) : null}

          <div className="osl-pdetail__acts">
            <button
              type="button"
              className={'osl-btn osl-btn--primary osl-btn--lg' + (inCart ? ' is-in' : '')}
              onClick={() => cart.add({ slug: p.slug, name_ar: p.name_ar, name_en: p.name_en })}
            >
              {inCart ? <IcCheck size={17} /> : <IcCart size={17} />}
              <span>{t(inCart ? 'أُضيف — أضِف مرّة أخرى' : 'أضِف لطلب التسعير')}</span>
            </button>
            <a
              className="osl-btn osl-btn--secondary osl-btn--lg"
              href={`https://wa.me/966556847029?text=${encodeURIComponent(t('أرغب في الاستفسار عن') + ': ' + pick(p, 'name'))}`}
              target="_blank" rel="noopener noreferrer"
            >
              <IcWhatsapp size={17} /><span>{t('استفسار واتساب')}</span>
            </a>
          </div>
        </div>
      </div>
    </Container>
  )
}
