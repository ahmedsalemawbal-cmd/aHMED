import { Link, useParams } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { pick } from '../../lib/i18n'
import { useAsync } from '../../lib/hooks'
import { fetchProducts } from '../../lib/data'
import { useCart } from '../../lib/cart'
import type { GroupKey } from '../../lib/types'
import { Container, EmptyState, ErrorState, Skeleton } from '../../ui/kit'
import { IcCart, IcCheck } from '../../ui/icons'

export const GROUP_TITLES: Record<GroupKey, { ar: string; line: string }> = {
  doors:  { ar: 'الأبواب بأنواعها',        line: 'أبواب معدنية مقاومة للحريق ومجوفة وتكسوية، وخشبية MDF و WPC، وأبواب مخصّصة وفق المشروع.' },
  gypsum: { ar: 'بروفايلات الجبسوم بورد',  line: 'منظومة كاملة: C-Stud و U-Track و L-Angle و Hat Channel وقنوات الأسقف والإكسسوارات.' },
  strut:  { ar: 'أنظمة Strut للتثبيت',     line: 'أطواق وقنوات وأذرع كونسول ودعامات ومشابك تعليق ووصلات نظام — بمواصفات fischer.' },
}

export default function ProductArchive({ group }: { group?: GroupKey }) {
  const params = useParams()
  const g = (group ?? (params.group as GroupKey)) as GroupKey
  const { t } = useApp()
  const cart = useCart()
  const { data, loading, error, reload } = useAsync(() => fetchProducts(g), [g])

  const head = GROUP_TITLES[g]

  return (
    <>
      <section className="osl-phead">
        <Container wide>
          <h1 className="osl-phead__h">{t(head.ar)}</h1>
          <p className="osl-phead__p">{t(head.line)}</p>
        </Container>
      </section>

      <section className="osl-sec">
        <Container wide>
          {loading ? (
            <div className="osl-grid osl-grid--3">
              {[0, 1, 2, 3, 4, 5].map((i) => (
                <div key={i} className="osl-pcard"><Skeleton h={190} /><Skeleton h={18} /><Skeleton h={14} w="70%" /></div>
              ))}
            </div>
          ) : error ? (
            <ErrorState message={error} onRetry={reload} />
          ) : !data || !data.length ? (
            <EmptyState title={t('لا منتجات في هذه العائلة بعد')}
                        line={t('نضيف المنتجات أوّلًا بأوّل — راسلنا وسنوافيك بالمتاح.')} />
          ) : (
            <div className="osl-grid osl-grid--3">
              {data.map((p) => {
                const inCart = cart.lines.some((l) => l.slug === p.slug)
                return (
                  <article key={p.id} className="osl-pcard">
                    <Link to={`/product/${p.slug}`} className="osl-pcard__img">
                      {/* `loading="lazy"` لأنّ الأرشيفَ يعرض ثلاثةَ عشرَ منتجًا،
                          ولا يرى الزائرُ منها في أوّل شاشةٍ إلّا ثلاثة. */}
                      {p.image_url
                        ? <img src={p.image_url} alt={pick(p, 'name')} loading="lazy" />
                        : <span className="osl-pcard__noimg" aria-hidden="true" />}
                      {pick(p, 'badge') ? <span className="osl-pcard__badge">{pick(p, 'badge')}</span> : null}
                    </Link>
                    <div className="osl-pcard__body">
                      <h2 className="osl-pcard__h">
                        <Link to={`/product/${p.slug}`}>{pick(p, 'name')}</Link>
                      </h2>
                      <p className="osl-pcard__sub">{pick(p, 'sub')}</p>
                    </div>
                    <button
                      type="button"
                      className={'osl-btn osl-btn--sm osl-pcard__add' + (inCart ? ' is-in' : '')}
                      onClick={() => cart.add({ slug: p.slug, name_ar: p.name_ar, name_en: p.name_en })}
                    >
                      {inCart ? <IcCheck size={15} /> : <IcCart size={15} />}
                      <span>{t(inCart ? 'أُضيف — أضِف مرّة أخرى' : 'أضِف لطلب التسعير')}</span>
                    </button>
                  </article>
                )
              })}
            </div>
          )}
        </Container>
      </section>
    </>
  )
}
