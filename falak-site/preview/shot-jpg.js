const { chromium } = require('playwright');
const path = require('path');
const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  async function shot(file, out, vp, full, dsf=1.5) {
    const c = await b.newContext({ viewport: vp, deviceScaleFactor: dsf, isMobile: vp.width < 500 });
    const p = await c.newPage();
    await p.goto('file://' + path.join(D, file), { waitUntil: 'networkidle', timeout: 60000 });
    await p.waitForTimeout(2200);
    await p.evaluate(() => { document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')); document.querySelectorAll('[data-count]').forEach(e=>{e.textContent=(+e.getAttribute('data-count')).toLocaleString('ar-EG');}); });
    await p.waitForTimeout(500);
    await p.screenshot({ path: path.join(D, out), fullPage: full, type: 'jpeg', quality: 82 });
    await c.close();
  }
  await shot('index.html', 'send-hero.jpg',   { width:1440, height:900 }, false);
  await shot('index.html', 'send-home.jpg',   { width:1440, height:900 }, true);
  await shot('index.html', 'send-mobile.jpg', { width:390,  height:844 }, true, 2);
  await shot('enroll.html','send-enroll.jpg', { width:1440, height:1000 }, true);
  await b.close(); console.log('jpg shots done');
})().catch(e => { console.error(e); process.exit(1); });
