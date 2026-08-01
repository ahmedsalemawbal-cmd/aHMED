const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const c = await b.newContext({ viewport:{width:1360,height:900}, deviceScaleFactor:2 });
  const p = await c.newPage();
  await p.goto('file://'+path.join(D,'home.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(2200);
  await p.evaluate(()=>document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')));
  const cta = await p.$('.fk-cta');
  if (cta) await cta.screenshot({path:path.join(D,'e-cta2.jpg'), type:'jpeg', quality:84});
  const hero = await p.$('.fk-hero');
  if (hero) await hero.screenshot({path:path.join(D,'e-hero2.jpg'), type:'jpeg', quality:80});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
