const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const c = await b.newContext({ viewport:{width:390,height:844}, deviceScaleFactor:2, isMobile:true });
  const p = await c.newPage();
  await p.goto('file://'+path.join(D,'home.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(2000);
  await p.evaluate(()=>document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')));
  await p.waitForTimeout(400);
  const el = await p.$('.fk-testi');
  if (el) await el.screenshot({path:path.join(D,'v7-testi-mob2.jpg'), type:'jpeg', quality:84});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
