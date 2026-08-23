import { useCallback, useEffect, useState } from 'react'

const KEY = 'midad.sidebar.collapsed'

/** طيّ الشريط الجانبيّ باختيار المستخدم — لا بعرض الشاشة.
 *  الاختيار يُحفَظ فلا يعود الشريط مفتوحًا كلّما فُتحت صفحة. */
export function useSidebarCollapsed() {
  const [collapsed, setCollapsed] = useState<boolean>(() => {
    try { return localStorage.getItem(KEY) === '1' } catch { return false }
  })

  useEffect(() => {
    const root = document.documentElement
    if (collapsed) root.setAttribute('data-sidebar', 'collapsed')
    else root.removeAttribute('data-sidebar')
    try { localStorage.setItem(KEY, collapsed ? '1' : '0') } catch { /* تصفّح خاصّ */ }
    return () => { root.removeAttribute('data-sidebar') }
  }, [collapsed])

  const toggle = useCallback(() => setCollapsed((v) => !v), [])
  return { collapsed, toggle }
}
