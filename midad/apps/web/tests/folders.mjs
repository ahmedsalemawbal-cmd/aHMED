/**
 * المجلّدات تخصّ نوع الحساب، والرفع يسأل لمن قبل أين.
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
import path from 'node:path'
import { TESTS } from './lib/harness.mjs'

const F = (o) => ({
  blurb: null, icon: 'folder', is_active: true, owner_only: false, coming_soon: false,
  created_at: '2026-08-01', updated_at: '2026-08-01', ...o,
})
const FOLDERS = [
  F({ id: 'f1', slug: 'school-files', name: 'ملفّات المدرسة', accent: '#4285F4', sort: 10, audience: 'school' }),
  F({ id: 'f2', slug: 'certificates', name: 'قوالب الشهادات', accent: '#a855f7', sort: 20, audience: 'school', owner_only: true, coming_soon: true }),
  F({ id: 'f3', slug: 'my-files', name: 'ملفّاتي', accent: '#0F9D58', sort: 30, audience: 'teacher' }),
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

  /* ═════ ② الرفع: لمن، ثمّ أين ═════ */
  const ctx = await withAccount(browser, 'school', { admin: true })
  const p = await ctx.newPage()
  p.on('pageerror', (e) => errors.push(e.message))
  await p.goto(`${ORIGIN}/#/admin/templates`, { waitUntil: 'load' })
  await p.waitForTimeout(2000)

  const open = await p.$('button:has-text("استورد")')
  T('زرّ الاستيراد في اللوحة', !!open)
  if (open) {
    await open.click()
    await p.waitForTimeout(600)
    const input = await p.$('input[type=file]')
    await input.setInputFiles(path.join(TESTS, 'fixtures/nafs.design.html'))
    await p.waitForSelector('select', { timeout: 40000 })
    await p.waitForTimeout(1600)

    const read = () => p.evaluate(() => {
      const s = [...document.querySelectorAll('select')]
      const f = s[s.length - 1], a = s[s.length - 2]
      return {
        audience: a.value,
        folder: f.value,
        folderText: f.options[f.selectedIndex]?.textContent.trim() || '',
        options: [...f.options].map((o) => o.textContent.trim()),
      }
    })

    const school = await read()
    T('يسأل لمن أوّلًا — والمدرسة الافتراض', school.audience === 'school', school.audience)
    T('  مجلّدات المدرسة وحدها',
      JSON.stringify(school.options) === JSON.stringify(['بلا مجلّد', 'ملفّات المدرسة', 'قوالب الشهادات — قريبًا']),
      school.options.join(' · '))

    /* الحاسم: لو بقي مجلّدُ مدرسةٍ مختارًا بعد اختيار «المعلّم» لحُفظ
       القالب حيث لا يراه أحد — ويُنشر ولا يشتكي أحد. */
    const sels = await p.$$('select')
    await sels[sels.length - 2].selectOption('teacher')
    await p.waitForTimeout(500)
    const teacher = await read()
    T('تبديل الجمهور يُبدّل المجلّد معه',
      teacher.folder === 'f3' && teacher.folderText === 'ملفّاتي',
      `${school.folderText} → ${teacher.folderText}`)
    T('  ولا يبقى مجلّد مدرسةٍ في القائمة',
      JSON.stringify(teacher.options) === JSON.stringify(['بلا مجلّد', 'ملفّاتي']),
      teacher.options.join(' · '))
  }

  T('بلا خطأ في الطرفيّة', errors.length === 0, errors[0] || 'نظيف')
} finally {
  await browser.close()
}

process.exit(T.done() === 0 ? 0 : 1)
