const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  // gallery grid (desktop)
  let c = await b.newContext({ viewport:{width:1300,height:900}, deviceScaleFactor:1.4 });
  let p = await c.newPage();
  await p.goto('file://'+path.join(D,'gallery.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(1800);
  await p.evaluate(()=>document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in')));
  const gal = await p.$('.fk-gallery');
  if (gal) await gal.screenshot({path:path.join(D,'v7-gallery.jpg'), type:'jpeg', quality:74});
  await c.close();
  // testimonials mobile
  c = await b.newContext({ viewport:{width:390,height:844}, deviceScaleFactor:2, isMobile:true });
  p = await c.newPage();
  await p.goto('file://'+path.join(D,'home.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(2000);
  await p.evaluate(()=>{document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in'));var t=document.querySelector('.fk-testi');if(t)t.scrollIntoView({block:'start'});});
  await p.waitForTimeout(500);
  await p.screenshot({path:path.join(D,'v7-testi-mobile.jpg'), fullPage:false, type:'jpeg', quality:82});
  await c.close();
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
