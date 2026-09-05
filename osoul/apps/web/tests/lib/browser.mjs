/**
 * ══ إيجادُ المتصفّح ══
 *
 * بلايرايت يبحث عن نسخةٍ برقمِ بناءٍ يطابق نسختَه هو، وبيئاتُ البناء
 * تحمل نسخةً مثبّتةً مسبقًا برقمٍ آخر. فيسقط بـ«شغّل playwright install»
 * والمتصفّحُ موجودٌ على القرص.
 *
 *     فحصٌ لا يعمل إلّا على جهازٍ واحدٍ ليس حارسًا.
 *
 * فيُبحث عن الملفّ التنفيذيّ نفسِه لا عن رقم البناء.
 */
import { existsSync, readdirSync } from 'node:fs'
import { join } from 'node:path'

export function findChromium() {
  const named = process.env.OSOUL_CHROMIUM
  if (named && existsSync(named)) return named

  const roots = [process.env.PLAYWRIGHT_BROWSERS_PATH, '/opt/pw-browsers',
                 join(process.env.HOME || '', '.cache/ms-playwright')].filter(Boolean)

  for (const root of roots) {
    if (!existsSync(root)) continue
    let dirs = []
    try { dirs = readdirSync(root) } catch { continue }
    // النسخةُ الكاملةُ قبل الصَّدَفة: الأخيرةُ لا ترسم الخطوطَ نفسَها
    const ordered = [...dirs.filter((d) => d.startsWith('chromium-')),
                     ...dirs.filter((d) => d.startsWith('chromium_headless_shell-')),
                     ...dirs.filter((d) => d === 'chromium')]
    for (const d of ordered) {
      for (const rel of ['chrome-linux/chrome', 'chrome-linux/headless_shell',
                         'chrome-mac/Chromium.app/Contents/MacOS/Chromium']) {
        const p = join(root, d, rel)
        if (existsSync(p)) return p
      }
    }
  }
  return null
}

export async function launchBrowser() {
  const { chromium } = await import('playwright')
  const exe = findChromium()
  return chromium.launch(exe ? { executablePath: exe } : {})
}
