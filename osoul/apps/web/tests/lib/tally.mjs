/**
 * عدّادٌ مكتوبٌ بيدٍ بلا إطار — على نمط `midad/apps/web/tests/lib/harness.mjs`.
 * وإطارُ اختبارٍ لثلاثة ملفّاتٍ تبعيّةٌ تُثبَّت وتُحدَّث ولا تُضيف شيئًا.
 */
export function tally(label) {
  let pass = 0, fail = 0
  const T = (l, ok, extra = '') => {
    ok ? pass++ : fail++
    console.log(`${ok ? '✅' : '✗ '} ${l}${extra ? ' — ' + extra : ''}`)
  }
  T.done = () => {
    console.log(`\n═══ ${label}: ${pass} ناجحًا · ${fail} فاشلًا ═══`)
    return fail
  }
  return T
}
