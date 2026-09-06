import { useEffect } from 'react'

/** يكشف العناصر ذات mdd-reveal عند دخولها الشاشة — مرّةً واحدة لكلّ عنصر. */
export function useReveal(deps: any[] = []) {
  useEffect(() => {
    const nodes = Array.from(document.querySelectorAll<HTMLElement>('.mdd-reveal:not([data-shown])'))
    if (!nodes.length) return

    const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    if (reduced || typeof IntersectionObserver === 'undefined') {
      nodes.forEach((n) => n.setAttribute('data-shown', 'true'))
      return
    }

    const io = new IntersectionObserver(
      (entries) => {
        for (const e of entries) {
          if (!e.isIntersecting) continue
          const el = e.target as HTMLElement
          const delay = Number(el.dataset.revealDelay || 0)
          window.setTimeout(() => el.setAttribute('data-shown', 'true'), delay)
          io.unobserve(el)
        }
      },
      { rootMargin: '0px 0px -12% 0px', threshold: 0.08 },
    )
    nodes.forEach((n) => io.observe(n))
    return () => io.disconnect()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps)
}
