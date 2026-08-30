/**
 * المجلّدات تخصّ نوع الحساب، والرفع يسأل لمن قبل أين — والحقل لا يفقد تركيزه.
 *
 * كانت سبعة مجلّداتٍ يراها كلّ مشترك. وصارت ثلاثة: «ملفّات المدرسة»
 * و«قوالب الشهادات» للمدرسة، و«ملفّاتي» للمعلّم. والشهاداتُ لمالك
 * الحساب وحده — لا يراها معلّمو المدرسة.
 *
 * وثلاثة أمورٍ تُفحص هنا لأنّ عطبها **صامت**: يُحفظ ويُنشر ولا يظهر،
 * فلا يشتكي أحدٌ إلّا بعد أن يبحث المعلّم عن قالبٍ وُعد به.
 *
 *   ① المدرسة ترى اثنين، والمعلّم واحدًا — ولا يرى أحدهما مجلّد الآخر.
 *   ② «قريبًا» يظهر حيث أُعلن، لا حيث فرغ المجلّد.
 *   ③ تبديل الجمهور في الرفع يُبدّل المجلّد معه — وإلّا حُفظ القالب
 *      في مجلّدٍ لجمهورٍ لا يراه.
 *
 * والترشيح في الواجهة ليس الحارس: السياسة في قاعدة البيانات هي التي
 * تمنع، وهذا الفحص يتحقّق ممّا يراه المشترك.
 */

import { launch, seedContext, ORIGIN, tally } from './lib/harness.mjs'

const F = (o) => ({
  blurb: null, icon: 'folder', is_active: true, owner_only: false, coming_soon: false, is_general: false,
  created_at: '2026-08-01', updated_at: '2026-08-01', ...o,
})
const FOLDERS = [
  F({ id: 'f1', slug: 'school-files', name: 'ملفّات المدرسة', accent: '#4285F4', sort: 10, audience: 'school', is_general: true }),
  F({ id: 'f2', slug: 'certificates', name: 'قوالب الشهادات', accent: '#a855f7', sort: 20, audience: 'school', owner_only: true, coming_soon: true }),
  F({ id: 'f3', slug: 'my-files', name: 'ملفّاتي', accent: '#0F9D58', sort: 30, audience: 'teacher', is_general: true }),
]

/** يُهيّئ سياقًا يرى مجلّدات نوع حسابه — كما تفعل السياسة في الخادم. */
async function withAccount(browser, kind, { admin = false } = {}) {
  const ctx = await seedContext(browser, '<p></p>', undefined, { viewport: { width: 1180, height: 900 } })
  const mine = FOLDERS.filter((f) => f.audience === kind)
  await ctx.route('**/rest/v1/**', (route) => {
    const t = new URL(route.request().url()).pathname.split('/rest/v1/')[1] || ''
    const J = (d) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(d) })
    if (t === 'template_folders') return J(admin ? FOLDERS : mine)
    if (t === 'template_folder_counts') return J(kind === 'school' ? [{ folder_id: 'f1', template_count: 1 }] : [])
    if (t === 'templates') return J([])
    if (t === 'platform_admins') return J(admin ? [{ user_id: 'u1' }] : [])
    if (t === 'profiles') return J([{ id: 'u1', subscriber_id: 's1', full_name: 'أحمد', phone: '05', email: null, role_key: admin ? 'owner' : 'teacher', is_owner: true, status: 'active' }])
    if (t === 'subscribers') return J([{ id: 's1', name: 'مِداد', account_type: kind, status: 'active', plan_id: 'p1', trial_ends_at: null }])
    if (t === 'plans') return J([{ id: 'p1', key: kind, name_ar: 'ب', account_type: kind, price_sar: kind === 'school' ? 749 : 99, seats: 10, template_categories: [], ai_quota_monthly: 1000, features_ar: [] }])
    return J([])
  })
  return ctx
}

const T = tally('المجلّدات')
const browser = await launch()
const errors = []

try {
  /* ═════ ① كلٌّ يرى مجلّدات حسابه ═════ */
  for (const [kind, want] of [
    ['school', ['ملفّات المدرسة', 'قوالب الشهادات']],
    ['teacher', ['ملفّاتي']],
  ]) {
    const ctx = await withAccount(browser, kind)
    const p = await ctx.newPage()
    p.on('pageerror', (e) => errors.push(e.message))
    await p.goto(`${ORIGIN}/#/app/library`, { waitUntil: 'load' })
    await p.waitForSelector('.mdd-folder', { timeout: 20000 })
    await p.waitForTimeout(900)

    const seen = await p.evaluate(() => [...document.querySelectorAll('.mdd-folder')].map((f) => ({
      name: f.querySelector('.mdd-folder-name').textContent.trim(),
      soon: !!f.querySelector('.mdd-folder-soon'),
    })))
    T(`حساب ${kind === 'school' ? 'المدرسة' : 'المعلّم'} يرى ${want.length}`,
      JSON.stringify(seen.map((s) => s.name)) === JSON.stringify(want),
      seen.map((s) => s.name).join(' · ') || 'لا شيء')

    if (kind === 'school') {
      T('  «قريبًا» على الشهادات وحدها',
        seen.filter((s) => s.soon).map((s) => s.name).join() === 'قوالب الشهادات',
        seen.filter((s) => s.soon).map((s) => s.name).join(' · ') || 'لا وسم')
    }
    await ctx.close()
  }

  /* ═════ ② الرفع ═════
   *
   * انتقل فحصُ حوار الرفع إلى `tests/admin.mjs`: الجمهور صار على الملفّ
   * لا على المجلّد، فسقط سؤال «في أيّ مجلّد؟» — يُشتقّ من الصلاحيّة.
   * ويبقى هنا ما يخصّ المجلّدات وحدها. */

  /* ═════ ③ الحقل لا يفقد تركيزه ═════
   *
   * كانت النافذة تُعيد تركيز نفسها في كلّ رسمة، لأنّ أثرها يعتمد على
   * `onClose` — ودالّةٌ سطريّةٌ جديدةٌ في كلّ رسمة. فكلّ حرفٍ يُعيد رسم
   * الأب فيُسحب التركيز، ويضطرّ المعلّم إلى الضغط بالفأرة بين حرفٍ وحرف.
   *
   * وهذا عطبٌ لا يظهر في سجلٍّ ولا في لقطة: الحقل سليمٌ في الصورة،
   * والقيمة تُحفظ إن أصرّ المستخدم. فلا يُمسَك إلّا بكتابةٍ حقيقيّة. */
  {
    const ctx2 = await withAccount(browser, 'school')
    const p2 = await ctx2.newPage()
    p2.on('pageerror', (e) => errors.push(e.message))
    await p2.goto(`${ORIGIN}/#/app/classroom`, { waitUntil: 'load' })
    await p2.waitForTimeout(1800)

    const add = await p2.$('button:has-text("فصل جديد")')
    T('حوار «فصل جديد» يُفتح', !!add)
    if (add) {
      await add.click()
      await p2.waitForTimeout(600)

      /* التركيز في أوّل حقلٍ فور الفتح — بلا فأرة */
      const auto = await p2.evaluate(() => {
        const a = document.activeElement
        return a?.tagName === 'INPUT'
      })
      T('  التركيز في الحقل فور الفتح', auto)

      await p2.keyboard.type('الثالث/أ')
      await p2.waitForTimeout(300)
      const st = await p2.evaluate(() => {
        const i = document.querySelector('.mdd-modal input')
        return { v: i?.value || '', kept: document.activeElement === i }
      })
      T('  لا يفقد التركيز أثناء الكتابة', st.kept,
        st.kept ? `«${st.v}»` : `ضاع إلى ${'غير الحقل'}`)
      T('  والنصّ كامل', st.v === 'الثالث/أ', `«${st.v}»`)
    }
    await ctx2.close()
  }

  T('بلا خطأ في الطرفيّة', errors.length === 0, errors[0] || 'نظيف')
} finally {
  await browser.close()
}

process.exit(T.done() === 0 ? 0 : 1)
