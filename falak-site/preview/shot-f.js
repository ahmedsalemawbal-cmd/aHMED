const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  // mobile testimonials (arrows below)
  let c = await b.newContext({ viewport:{width:390,height:900}, deviceScaleFactor:2, isMobile:true });
  let p = await c.newPage();
  await p.goto('file://'+path.join(D,'home.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(2000);
  await p.evaluate(()=>document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')));
  const t = await p.$('.fk-testi'); if (t) await t.screenshot({path:path.join(D,'v8-testi-mob.jpg'), type:'jpeg', quality:82});
  await c.close();
  // desktop about (image slot)
  c = await b.newContext({ viewport:{width:1300,height:900}, deviceScaleFactor:1.5 });
  p = await c.newPage();
  await p.goto('file://'+path.join(D,'home.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(1800);
  await p.evaluate(()=>document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')));
  const a = await p.$('.fk-about'); if (a) await a.screenshot({path:path.join(D,'v8-about.jpg'), type:'jpeg', quality:76});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
