/**
 * ══ السمة ══
 *
 * ثلاث حالات لا اثنتان: `light` و`dark` و**`system`** — والثالثةُ ليست
 * زينة. من لم يختر شيئًا يتبع نظامَه، فيُظلم موقعُنا حين يُظلم جهازُه.
 * ويُمثَّل «اتّباعُ النظام» بغياب السمة من `<html>` لا بقيمةٍ ثالثةٍ فيها،
 * كي يعمل استعلامُ `prefers-color-scheme` في الورقة وحدَه.
 */
export type Theme = 'light' | 'dark' | 'system'

const KEY = 'osoul.theme'

export function readTheme(): Theme {
  try {
    const v = localStorage.getItem(KEY)
    if (v === 'light' || v === 'dark') return v
  } catch {
    /* لا شيء */
  }
  return 'system'
}

export function applyTheme(theme: Theme) {
  const el = document.documentElement
  if (theme === 'system') {
    el.removeAttribute('data-theme')
    try { localStorage.removeItem(KEY) } catch { /* لا شيء */ }
  } else {
    el.setAttribute('data-theme', theme)
    try { localStorage.setItem(KEY, theme) } catch { /* لا شيء */ }
  }
  // لونُ شريط المتصفّح على الجوّال يتبع السمة، وإلّا بقي داكنًا فوق صفحةٍ بيضاء
  const dark = theme === 'dark' ||
    (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)
  const meta = document.querySelector('meta[name="theme-color"]')
  if (meta) meta.setAttribute('content', dark ? '#0B1620' : '#00344F')
}
