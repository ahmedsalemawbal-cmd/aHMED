export type ThemeMode = 'light' | 'dark' | 'auto'
const KEY = 'midad.theme'

export function getTheme(): ThemeMode {
  try { return (localStorage.getItem(KEY) as ThemeMode) || 'auto' } catch { return 'auto' }
}
export function applyTheme(mode: ThemeMode) {
  const root = document.documentElement
  if (mode === 'auto') root.removeAttribute('data-theme')
  else root.setAttribute('data-theme', mode)
  try { localStorage.setItem(KEY, mode) } catch { /* وضع التصفّح الخاص */ }
  const dark = mode === 'dark' || (mode === 'auto' && window.matchMedia?.('(prefers-color-scheme: dark)').matches)
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? '#12180f' : '#f4f6f2')
}
export function initTheme() { applyTheme(getTheme()) }
