/**
 * مُشغِّلُ الفحوص — `npm test` أو `npm test routes`.
 * تُجرى تباعًا لا معًا: التزاحمُ على المعالج يُبطّئ الرسمَ فتُقاس أبعادٌ
 * ليست هي (درسُ مِداد في `tests/run.mjs`).
 */
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'

const ALL = ['routes']
const want = process.argv.slice(2).filter((a) => ALL.includes(a))
const list = want.length ? want : ALL

let failed = []
for (const name of list) {
  console.log(`\n──────── ${name} ────────`)
  const r = spawnSync(process.execPath, [fileURLToPath(new URL(`./${name}.mjs`, import.meta.url))],
                      { stdio: 'inherit' })
  if (r.status !== 0) failed.push(name)
}

console.log(failed.length ? `\n✗ سقط: ${failed.join(' · ')}` : `\n✅ الفحوص كلُّها ناجحة (${list.length})`)
process.exit(failed.length ? 1 : 0)
