/**
 * يُشغّل الفحوص كلَّها: يُقيم الخادم إن لم يكن قائمًا، ثمّ يُجريها تباعًا.
 *
 * وتُجرى تباعًا لا معًا: كلٌّ منها يفتح متصفّحًا ويحمّل قالبًا من ستّ
 * صفحات، وتزاحمها على المعالج يُبطّئ الرسم فتُقاس أعرضٌ وارتفاعاتٌ ليست
 * هي — فيسقط فحصٌ صحيحٌ ويُظنّ عطبًا.
 *
 *   node tests/run.mjs            الكلّ
 *   node tests/run.mjs fidelity   واحدٌ بعينه
 */

import { spawn, spawnSync } from 'node:child_process'
import path from 'node:path'
import { APP, PORT, ORIGIN } from './lib/harness.mjs'

const ALL = ['fidelity', 'roundtrip', 'editor']
const want = process.argv.slice(2).filter((a) => !a.startsWith('-'))
const suites = want.length ? want : ALL

const bad = suites.filter((s) => !ALL.includes(s))
if (bad.length) {
  console.error(`فحصٌ غير معروف: ${bad.join(', ')}\nالمتاح: ${ALL.join(' · ')}`)
  process.exit(2)
}

async function alive() {
  try {
    const r = await fetch(ORIGIN, { signal: AbortSignal.timeout(1500) })
    return r.ok
  } catch { return false }
}

let server = null
if (!(await alive())) {
  console.log(`— يُقام الخادم على ${PORT} —`)
  server = spawn(path.join(APP, 'node_modules/.bin/vite'),
    ['--port', String(PORT), '--strictPort'],
    { cwd: APP, stdio: 'ignore', detached: false })
  for (let i = 0; i < 40; i++) {
    await new Promise((r) => setTimeout(r, 500))
    if (await alive()) break
  }
  if (!(await alive())) {
    console.error('تعذّر إقامة الخادم')
    server.kill()
    process.exit(2)
  }
}

let failed = 0
try {
  for (const s of suites) {
    console.log(`\n\n████ ${s} ████\n`)
    const r = spawnSync(process.execPath, [path.join(APP, 'tests', `${s}.mjs`)],
      { cwd: APP, stdio: 'inherit' })
    if (r.status !== 0) failed++
  }
} finally {
  if (server) server.kill()
}

console.log(`\n${failed === 0 ? '✅ الفحوص كلّها ناجحة' : `✗ سقط ${failed} من ${suites.length}`}`)
process.exit(failed === 0 ? 0 : 1)
