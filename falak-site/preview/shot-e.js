const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  async function full(file,out,vp,dsf){
    const c = await b.newContext({ viewport: vp||{width:1300,height:900}, deviceScaleFactor: dsf||1.3, isMobile:(vp&&vp.width<500) });
    const p = await c.newPage();
    await p.goto('file://'+path.join(D,file), {waitUntil:'load', timeout:60000});
    await p.waitForTimeout(2200);
    await p.evaluate(()=>{document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in'));document.querySelectorAll('[data-count]').forEach(e=>{e.textContent=(+e.getAttribute('data-count')).toLocaleString('ar-EG');});});
    await p.waitForTimeout(500);
    await p.screenshot({path:path.join(D,out), fullPage:true, type:'jpeg', quality:70});
    await c.close(); console.log('shot',out);
  }
  await full('home.html','e-home.jpg',{width:1300,height:900},1.2);
  await full('programs.html','e-programs.jpg',{width:1300,height:900},1.2);
  await full('contact.html','e-contact.jpg',{width:1300,height:900},1.2);
  // mobile header (subtitle) — top only
  const c = await b.newContext({ viewport:{width:390,height:300}, deviceScaleFactor:2, isMobile:true });
  const p = await c.newPage();
  await p.goto('file://'+path.join(D,'home.html'), {waitUntil:'load', timeout:60000});
  await p.waitForTimeout(2000);
  await p.screenshot({path:path.join(D,'e-mobile-head.jpg'), fullPage:false, type:'jpeg', quality:84});
  await c.close();
  await b.close();
})().catch(e=>{console.error(e);process.exit(1);});
