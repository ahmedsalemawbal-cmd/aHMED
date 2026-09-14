/* دليل الشركة: الأسماء والأقسام وتعديلات السياسة.
 *
 * هذه القائمة هي ما يبحث فيه الموظف حين يكتب اسم زميل بدل عنوانه. خطأ
 * فيها يعني رسالة تذهب إلى الشخص الخطأ، فتستحق فحصًا صريحًا.
 */

'use strict';

const dir = require('../src/main/directory');

const out = {};
let failures = 0;
const check = (n, c, d) => {
  if (c) out[n] = 'PASS';
  else { out[n] = `FAIL ${JSON.stringify(d === undefined ? '' : d).slice(0, 300)}`; failures++; }
};

const list = dir.build({});
const by = (email) => list.find((p) => p.email === email);

/* --- القائمة المدمجة --- */
check('list.notEmpty', list.length >= 33, list.length);
check('list.everyEmailValid', list.every((p) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(p.email)),
  list.filter((p) => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(p.email)).map((p) => p.email));
check('list.allLowercase', list.every((p) => p.email === p.email.toLowerCase()));
check('list.noDuplicateEmails', new Set(list.map((p) => p.email)).size === list.length);
check('list.sameDomain', list.every((p) => p.email.endsWith('@osoulalbinaa.com')),
  list.filter((p) => !p.email.endsWith('@osoulalbinaa.com')).map((p) => p.email));

// الكشف كتب production@osoulalbinaa بلا نطاق أعلى؛ عنوان كهذا لا يصل أبدًا.
check('list.fixesTruncatedDomain', !!by('production@osoulalbinaa.com'));
// وMain@ بحرف كبير: الخوادم تعامل الجزء المحلي بحساسية حالة أحيانًا.
check('list.normalisesCase', !!by('main@osoulalbinaa.com'));

/* --- الأسماء --- */
check('name.titleCased', by('ceo.es@osoulalbinaa.com').name === 'Aveena Na',
  by('ceo.es@osoulalbinaa.com').name);
check('name.everyoneNamed', list.every((p) => p.name && p.name !== p.email),
  list.filter((p) => !p.name || p.name === p.email).map((p) => p.email));
check('name.arabicForEveryone', list.every((p) => p.nameAr),
  list.filter((p) => !p.nameAr).map((p) => p.email));
check('name.arabicIsArabic', list.every((p) => /[؀-ۿ]/.test(p.nameAr)),
  list.filter((p) => !/[؀-ۿ]/.test(p.nameAr)).map((p) => p.email));

/* --- الأقسام --- */
check('dept.ceo', by('ceo@osoulalbinaa.com').dept.en === 'CEO');
check('dept.salesGrouped',
  ['sales1', 'sales2', 'sales3', 'sales4', 'sales5', 'sales.co']
    .every((k) => by(`${k}@osoulalbinaa.com`).dept.en === 'Sales'));
check('dept.hrGrouped',
  ['hr.dep', 'hr1', 'sep.hr'].every((k) => by(`${k}@osoulalbinaa.com`).dept.en === 'Human Resources'));
// قسم مخترع أسوأ من قسم غائب: الغامض يبقى فارغًا لا مخمَّنًا.
check('dept.unknownLeftBlank', by('dwsd@osoulalbinaa.com').dept === null);
check('dept.bothLanguages', list.filter((p) => p.dept).every((p) => p.dept.ar && p.dept.en));

/* --- تعديلات السياسة --- */
const patched = dir.build({
  directory: [
    { email: 'dwsd@osoulalbinaa.com', dept: { ar: 'المستودعات', en: 'Warehouse' } },
    { email: 'CEO@osoulalbinaa.com', name: 'A. Ghalib' },
    { email: 'new.hire@osoulalbinaa.com', name: 'New Hire', nameAr: 'موظف جديد' },
    { email: 'not-an-email', name: 'ignored' },
  ],
});
const p = (e) => patched.find((x) => x.email === e);
check('policy.addsDepartment', p('dwsd@osoulalbinaa.com').dept.en === 'Warehouse');
check('policy.rewritesName', p('ceo@osoulalbinaa.com').name === 'A. Ghalib');
check('policy.keepsArabicWhenOnlyNameGiven', p('ceo@osoulalbinaa.com').nameAr === 'عبدالرحمن غالب',
  p('ceo@osoulalbinaa.com').nameAr);
check('policy.addsNewPerson', !!p('new.hire@osoulalbinaa.com'));
check('policy.ignoresJunk', !patched.some((x) => x.name === 'ignored'));
check('policy.noGrowthBeyondEdits', patched.length === list.length + 1, patched.length);

const replaced = dir.build({ directoryReplace: true, directory: [{ email: 'only@osoulalbinaa.com', name: 'Only' }] });
check('policy.canReplaceEntirely', replaced.length === 1 && replaced[0].email === 'only@osoulalbinaa.com',
  replaced.length);

console.log(JSON.stringify(out, null, 1));
console.log(`\nموظفون: ${list.length} · بأقسام: ${list.filter((x) => x.dept).length}`);
console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
process.exit(failures ? 1 : 0);
