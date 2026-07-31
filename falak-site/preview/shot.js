const { chromium } = require('playwright');
const path = require('path');

const FILE = 'file://' + path.join(__dirname, 'index.html');
const OUT = __dirname;

(async () => {
  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  // سطح المكتب
  const desktop = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  const p1 = await desktop.newPage();
  await p1.goto(FILE, { waitUntil: 'networkidle', timeout: 60000 });
  await p1.waitForTimeout(2500); // انتظار الخطوط والأنيميشن
  // كشف كل عناصر الظهور
  await p1.evaluate(() => {
    document.querySelectorAll('.fk-reveal').forEach(e => e.classList.add('in'));
    document.querySelectorAll('[data-count]').forEach(e => { e.textContent = (+e.getAttribute('data-count')).toLocaleString('ar-EG'); });
  });
  await p1.waitForTimeout(600);
  await p1.screenshot({ path: path.join(OUT, 'shot-desktop-full.png'), fullPage: true });
  await p1.screenshot({ path: path.join(OUT, 'shot-desktop-hero.png'), fullPage: false });
  await desktop.close();

  // الجوال
  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true });
  const p2 = await mobile.newPage();
  await p2.goto(FILE, { waitUntil: 'networkidle', timeout: 60000 });
  await p2.waitForTimeout(2500);
  await p2.evaluate(() => document.querySelectorAll('.fk-reveal').forEach(e => e.classList.add('in')));
  await p2.waitForTimeout(600);
  await p2.screenshot({ path: path.join(OUT, 'shot-mobile-full.png'), fullPage: true });
  await mobile.close();

  await browser.close();
  console.log('screenshots done');
})().catch(e => { console.error(e); process.exit(1); });
