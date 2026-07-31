const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  // desktop
  let c = await b.newContext({ viewport:{width:1300,height:640}, deviceScaleFactor:2 });
  let p = await c.newPage();
  await p.goto('file://'+path.join(D,'enroll.html'), {waitUntil:'networkidle', timeout:60000});
  await p.waitForTimeout(1600);
  await p.evaluate(()=>{document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in'));var r=document.querySelector('input[name=section][value="بنين"]');if(r){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}));}var f=document.querySelector('.fk-enroll-grid');if(f)f.scrollIntoView({block:'start'});window.scrollBy(0,-30);});
  await p.waitForTimeout(400);
  await p.screenshot({path:path.join(D,'v4-enroll-desktop.jpg'), fullPage:false, type:'jpeg', quality:80});
  await c.close();
  // mobile (تأكد الستاك سليم)
  c = await b.newContext({ viewport:{width:390,height:720}, deviceScaleFactor:2, isMobile:true });
  p = await c.newPage();
  await p.goto('file://'+path.join(D,'enroll.html'), {waitUntil:'networkidle', timeout:60000});
  await p.waitForTimeout(1600);
  await p.evaluate(()=>{document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in'));var f=document.querySelector('.fk-enroll-grid');if(f)f.scrollIntoView({block:'start'});});
  await p.waitForTimeout(400);
  await p.screenshot({path:path.join(D,'v4-enroll-mobile.jpg'), fullPage:false, type:'jpeg', quality:80});
  await b.close(); console.log('done');
})().catch(e=>{console.error(e);process.exit(1);});
