/**
 * جولة على كل شاشات النظام: تسجيل دخول بكل دور، فتح كل قسم، تصوير،
 * وجمع كل خطأ — خطأ جافاسكربت، طلب فاشل، ورسائل PHP في نص الصفحة.
 *
 * الهدف: تقرير مصوّر يُقرأ بالعين، لا وصف أعطال بالكلام.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.SCH_BASE || 'http://localhost:8080';
const OUT = process.env.SCH_OUT || './shots';
// مسار كروميوم: من البيئة، أو ما تجده Playwright بنفسها
const EXE = process.env.SCH_CHROME || undefined;

// الأقسام الـ47 من SCH_Dashboard::SECTIONS
const SECTIONS = [
  'overview', 'custody', 'alerts', 'students', 'guardians', 'classes', 'badges',
  'certificates', 'attendance', 'attendance-report', 'timetable', 'subjects',
  'homework', 'exams', 'content', 'leaves', 'kg', 'staff-day', 'staff-report',
  'clinic', 'meds', 'inbox', 'messages', 'library', 'nerve', 'deputy', 'perms',
  'transport', 'finance', 'accounts', 'journal', 'ledger', 'reports', 'hr',
  'payroll', 'assets', 'inventory', 'employees', 'staff-badges', 'visitors',
  'safety', 'import', 'settings', 'settings-years', 'settings-day', 'rollover', 'audit',
];

// الواجهات الخمسة + بوابة الدخول الموحّدة
const APPS = [
  { slug: 'portal', url: '/login/', user: null },
  { slug: 'app-parent', url: '/app/', user: 'guardian' },
  { slug: 'teacher', url: '/teacher/', user: 'teacher' },
  { slug: 'driver', url: '/driver/', user: 'driver' },
  { slug: 'gate', url: '/gate/', user: 'guard' },
  { slug: 'student', url: '/student/', user: 'student' },
];

const PHP_ERR = /(Fatal error|Parse error|Warning:|Notice:|Deprecated:|WordPress database error|There has been a critical error)/i;

const findings = [];

function record(screen, kind, detail) {
  findings.push({ screen, kind, detail: String(detail).slice(0, 400) });
}

async function newPage(ctx, screen) {
  const page = await ctx.newPage();
  page.on('console', (m) => {
    if (m.type() === 'error') record(screen, 'console', m.text());
  });
  page.on('pageerror', (e) => record(screen, 'jserror', e.message));
  page.on('requestfailed', (r) => {
    const u = r.url();
    if (u.startsWith('data:')) return;
    record(screen, 'reqfail', `${r.failure()?.errorText || '?'} ${u}`);
  });
  page.on('response', (r) => {
    if (r.status() >= 400) record(screen, 'http' + r.status(), r.url());
  });
  return page;
}

async function shoot(page, screen, url, opts = {}) {
  const target = url.startsWith('http') ? url : BASE + url;
  try {
    const resp = await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 30000 });
    if (resp && resp.status() >= 400) record(screen, 'status', `${resp.status()} on ${url}`);
    await page.waitForTimeout(opts.wait || 700);

    const body = await page.evaluate(() => document.body ? document.body.innerText : '');
    const m = body.match(PHP_ERR);
    if (m) {
      const i = body.indexOf(m[0]);
      record(screen, 'phperror', body.slice(i, i + 300).replace(/\s+/g, ' '));
    }
    // نص فارغ = شاشة بيضاء
    if (body.trim().length < 40) record(screen, 'blank', `body text length=${body.trim().length}`);

    // تجاوز أفقي: الصفحة لا يجوز أن تُمرَّر أفقيًا
    const overflow = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow > 4) record(screen, 'hoverflow', `${overflow}px horizontal overflow`);

    await page.screenshot({ path: path.join(OUT, `${screen}.png`), fullPage: opts.full !== false });
    return true;
  } catch (e) {
    record(screen, 'crash', e.message);
    try { await page.screenshot({ path: path.join(OUT, `${screen}.FAILED.png`) }); } catch (_) {}
    return false;
  }
}

async function loginWp(ctx, login, pass, screen) {
  const page = await newPage(ctx, screen + '#login');
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', login);
  await page.fill('#user_pass', pass);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('#wp-submit'),
  ]);
  await page.close();
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({
    ...(EXE ? { executablePath: EXE } : {}),
    args: ['--no-sandbox'],
  });

  // ---------- الداشبورد كمدير ----------
  const admin = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'ar-SA',
    ignoreHTTPSErrors: true,
  });
  await loginWp(admin, process.env.SCH_ADMIN || 'admin', process.env.SCH_ADMIN_PASS || 'Admin!2345', 'admin');

  const page = await newPage(admin, 'dash');
  let ok = 0;
  for (const s of SECTIONS) {
    const done = await shoot(page, `dash-${s}`, `/dashboard/${s}/`);
    if (done) ok++;
    process.stdout.write(`  ${done ? '·' : 'X'} ${s}\n`);
  }
  // الشاشات الفرعية (تفصيل سجل)
  await shoot(page, 'dash-students-single', '/dashboard/students/1/');
  await shoot(page, 'dash-guardians-single', '/dashboard/guardians/1/');
  await shoot(page, 'dash-transport-single', '/dashboard/transport/1/');
  // الوضع الداكن
  await page.evaluate(() => { try { localStorage.setItem('sch-theme', 'dark'); } catch (e) {} });
  await shoot(page, 'dash-overview-DARK', '/dashboard/overview/');
  await shoot(page, 'dash-students-DARK', '/dashboard/students/');
  await page.evaluate(() => { try { localStorage.setItem('sch-theme', 'light'); } catch (e) {} });
  // جوال
  const mob = await admin.newPage();
  await mob.setViewportSize({ width: 390, height: 844 });
  const mp = mob;
  mp.on('pageerror', (e) => record('dash-mobile', 'jserror', e.message));
  await shoot(mp, 'dash-overview-MOBILE', '/dashboard/overview/');
  await shoot(mp, 'dash-students-MOBILE', '/dashboard/students/');
  await mob.close();
  await page.close();
  await admin.close();
  console.log(`dashboard: ${ok}/${SECTIONS.length} rendered`);

  // ---------- الواجهات ----------
  const credPath = process.env.SCH_ACCOUNTS || './accounts.json';
  const raw = fs.existsSync(credPath) ? JSON.parse(fs.readFileSync(credPath, 'utf8')) : {};
  // accounts.json من dev.sh مفاتيحه أسماء الأدوار — نحوّلها لأسماء التطبيقات
  const ROLE_KEY = { guardian: 'sch_guardian', teacher: 'sch_teacher', driver: 'sch_driver', guard: 'sch_guard', student: 'sch_student' };
  const creds = {};
  for (const [k, role] of Object.entries(ROLE_KEY)) {
    const login = raw[k]?.login || raw[role];
    if (login) creds[k] = { login, pass: raw[k]?.pass || process.env.SCH_PASS || 'Test!12345' };
  }
  for (const app of APPS) {
    const ctx = await browser.newContext({
      viewport: { width: 414, height: 896 },
      locale: 'ar-SA',
      isMobile: true,
      hasTouch: true,
    });
    if (app.user && creds[app.user]) {
      await loginWp(ctx, creds[app.user].login, creds[app.user].pass, app.slug);
    }
    const p = await newPage(ctx, app.slug);
    await shoot(p, `app-${app.slug}`, app.url);
    await ctx.close();
    console.log(`  app ${app.slug}`);
  }

  await browser.close();

  fs.writeFileSync(path.join(OUT, 'findings.json'), JSON.stringify(findings, null, 2));

  // ملخص
  const byKind = {};
  for (const f of findings) byKind[f.kind] = (byKind[f.kind] || 0) + 1;
  console.log('\n=== FINDINGS BY KIND ===');
  for (const [k, v] of Object.entries(byKind).sort((a, b) => b[1] - a[1])) console.log(`  ${k}: ${v}`);
  console.log(`\ntotal findings: ${findings.length}`);
  console.log(`screenshots: ${fs.readdirSync(OUT).filter((f) => f.endsWith('.png')).length}`);
})();
