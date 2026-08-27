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

const ALL = ['fidelity', 'roundtrip', 'editor', 'folders', 'thumb', 'admin', 'paste', 'portfolio', 'speed']
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

  /* `--host 127.0.0.1` صراحةً: vite يربط `localhost` افتراضًا، وهو يُحلّ
     إلى ::1 قبل 127.0.0.1 في بيئاتٍ كثيرة — فيقوم الخادم ولا نجده،
     ونظنّه لم يقم. */
  server = spawn(path.join(APP, 'node_modules/.bin/vite'),
    ['--port', String(PORT), '--strictPort', '--host', '127.0.0.1'],
    { cwd: APP, stdio: ['ignore', 'pipe', 'pipe'] })

  /* ويُلتقط ما يقوله. وكان يُبتلع، فإذا سقط في السير لم نجد إلّا «تعذّر
     إقامة الخادم» — وهي جملةٌ تُخبر أنّ شيئًا وقع ولا تقول ما هو. */
  let log = ''
  const grab = (d) => { log += d.toString() }
  server.stdout.on('data', grab)
  server.stderr.on('data', grab)
  server.on('error', (e) => { log += `\nتعذّر تشغيل vite: ${e.message}` })

  const DEADLINE = 60_000
  const t0 = Date.now()
  while (Date.now() - t0 < DEADLINE) {
    await new Promise((r) => setTimeout(r, 500))
    if (server.exitCode !== null) break
    if (await alive()) break
  }

  if (!(await alive())) {
    console.error(`\n✗ تعذّر إقامة الخادم على ${ORIGIN} خلال ${DEADLINE / 1000}ث`)
    if (server.exitCode !== null) console.error(`  خرج vite بالرمز ${server.exitCode}`)
    console.error(log.trim() ? `\n— ما قاله vite —\n${log.trim()}` : '  ولم يقل شيئًا.')
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
