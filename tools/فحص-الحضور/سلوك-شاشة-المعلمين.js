const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  const errs = [];
  p.on('pageerror', e => errs.push(String(e)));
  await p.goto('file://' + process.argv[2]);
  await p.waitForTimeout(500);

  let ok = 0, bad = 0;
  const check = (label, got, want) => {
    const g = JSON.stringify(got), w = JSON.stringify(want);
    if (g === w) { ok++; console.log('  ✓ ' + label); }
    else { bad++; console.log(`  ✗ ${label}\n      المتوقَّع: ${w}\n      الناتج:   ${g}`); }
  };

  const shown = () => p.$$eval('[data-row]', rs => rs.filter(r => !r.hidden).length);
  const range = () => p.$eval('#sch-sd-range', e => e.textContent.trim());

  console.log('① الصفحة الأولى');
  check('٢٥ صفًّا معروضًا', await shown(), 25);
  check('سطر المدى', await range(), 'عرض ١–٢٥ من ٦٤ موظفًا');
  check('ثلاث صفحات + سهمان', await p.$$eval('.sch-sd__page', b => b.length), 5);

  console.log('\n② التصفية بالحالة');
  await p.click('.sch-sd__tab[data-f="absent"]');
  await p.waitForTimeout(120);
  const absent = await p.$$eval('[data-row]:not([hidden])', rs => rs.map(r => r.dataset.state));
  check('كل المعروض غائب', [...new Set(absent)], ['absent']);
  check('عددهم ٤', absent.length, 4);
  check('اختفت الصفحات', await p.$$eval('.sch-sd__page', b => b.length), 0);

  console.log('\n③ البحث');
  await p.click('.sch-sd__tab[data-f="all"]');
  await p.fill('#sch-sd-q', 'عوبل');
  await p.waitForTimeout(150);
  const hits = await p.$$eval('[data-row]:not([hidden])', rs => rs.map(r => r.dataset.find));
  check('كل نتيجة تحوي الكلمة', hits.every(h => h.includes('عوبل')), true);
  check('وُجدت نتائج', hits.length > 0, true);

  console.log('\n④ التحديد لا يشمل المخفيّ');
  await p.fill('#sch-sd-q', '');
  await p.waitForTimeout(120);
  await p.click('#sch-sd-all');
  await p.waitForTimeout(120);
  check('شريط التحديد ظهر', await p.$eval('#sch-sd-sel', e => !e.hidden), true);
  check('العدّاد ٢٥ لا ٦٤', await p.$eval('#sch-sd-seln', e => e.textContent.trim()), 'محدد ٢٥ موظفًا');
  const posted = await p.$$eval('[data-pick]', cs => cs.filter(c => c.checked && !c.disabled).length);
  check('المرسَل ٢٥ فقط', posted, 25);

  console.log('\n⑤ الصفحة الثانية');
  await p.click('.sch-sd__page:not(.is-on):not([disabled]):nth-of-type(3)');
  await p.waitForTimeout(150);
  check('سطر المدى تغيّر', await range(), 'عرض ٢٦–٥٠ من ٦٤ موظفًا');
  const still = await p.$$eval('[data-pick]', cs => cs.filter(c => c.checked && !c.disabled).length);
  check('محدَّدو الصفحة الأولى لم يعودوا يُرسَلون', still, 0);

  console.log('\n⑥ إلغاء التحديد');
  await p.click('.sch-sd__page[data-p="0"]');
  await p.waitForTimeout(120);
  await p.click('[data-clear]');
  await p.waitForTimeout(120);
  check('اختفى الشريط', await p.$eval('#sch-sd-sel', e => e.hidden), true);

  console.log('\n⑦ حالة الشاشة تُحفظ في النموذج');
  await p.click('.sch-sd__tab[data-f="late"]');
  await p.fill('#sch-sd-q', 'محمد');
  await p.waitForTimeout(150);
  const keep = await p.$$eval('#sch-sd-form [data-keep]', es => es.map(e => e.name + '=' + e.value));
  check('التصفية والبحث محفوظان', keep.sort(), ['keep[f]=late', 'keep[q]=محمد'].sort());

  console.log('\n' + '─'.repeat(54));
  if (errs.length) { console.log('أخطاء جافاسكربت: ' + errs.join(' | ')); bad++; }
  console.log(`نجح ${ok} · فشل ${bad}`);
  await b.close();
  process.exit(bad ? 1 : 0);
})();
