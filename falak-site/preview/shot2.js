const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const c = await b.newContext({ viewport: { width: 1440, height: 1200 }, deviceScaleFactor: 2 });
  const p = await c.newPage();
  await p.goto('file://' + path.join(__dirname, 'enroll.html'), { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(2200);
  await p.evaluate(() => document.querySelectorAll('.fk-reveal').forEach(e => e.classList.add('in')));
  await p.waitForTimeout(400);
  await p.screenshot({ path: path.join(__dirname, 'shot-enroll.png'), fullPage: true });
  await b.close(); console.log('enroll shot done');
})().catch(e => { console.error(e); process.exit(1); });
