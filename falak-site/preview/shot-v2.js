const { chromium } = require('playwright');
const path = require('path'); const D = __dirname;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  async function shot(file, out, opts){
    opts = opts || {};
    const c = await b.newContext({ viewport: opts.vp || {width:1440,height:1000}, deviceScaleFactor: opts.dsf||1.4, isMobile:(opts.vp&&opts.vp.width<500) });
    const p = await c.newPage();
    await p.goto('file://'+path.join(D,file), {waitUntil:'networkidle', timeout:60000});
    await p.waitForTimeout(2000);
    await p.evaluate(()=>{document.querySelectorAll('.fk-reveal').forEach(e=>e.classList.add('in'));document.querySelectorAll('[data-count]').forEach(e=>{e.textContent=(+e.getAttribute('data-count')).toLocaleString('ar-EG');});});
    if(opts.pre) await p.evaluate(opts.pre);
    await p.waitForTimeout(600);
    await p.screenshot({path:path.join(D,out), fullPage:opts.full!==false, type:'jpeg', quality:72});
    await c.close();
    console.log('shot', out);
  }
  // enroll: اختَر "ولد" لإظهار المراحل + التظليل
  await shot('enroll.html','v2-enroll.jpg',{full:false, vp:{width:1440,height:1150}, pre:()=>{var r=document.querySelector('input[name=section][value="بنين"]');if(r){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}));}}});
  await shot('review.html','v2-review.jpg',{full:false, vp:{width:1440,height:1050}});
  await shot('gallery.html','v2-gallery.jpg',{full:false, vp:{width:1440,height:1050}});
  await shot('dash-login.html','v2-login.jpg',{full:false, vp:{width:1440,height:950}});
  await shot('dash-app.html','v2-dash-students.jpg',{full:false, vp:{width:1440,height:1000}, pre:()=>{var b=document.querySelector('[data-toggle=add-student]');if(b)b.click();}});
  await shot('dash-gallery.html','v2-dash-gallery.jpg',{full:false, vp:{width:1440,height:1000}});
  await shot('home.html','v2-home-hero.jpg',{full:false, vp:{width:1440,height:900}});
  await b.close();
})().catch(e=>{console.error(e);process.exit(1);});
