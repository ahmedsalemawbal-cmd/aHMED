/* نافذة صفحة المزوّد: حبس التنقّل في نطاقها.
 *
 * فيها يكتب الموظف كلمة مرور بريده، فأي تسرّب إلى نطاق آخر يعني صفحة
 * تصيّد تلبس ثوب التطبيق. الحبس هو الحارس، فيُفحص صراحةً.
 */

'use strict';

const { sameSite, PARTITION } = require('../src/main/provider');

const out = {};
let failures = 0;
const check = (n, c, d) => {
  if (c) out[n] = 'PASS';
  else { out[n] = `FAIL ${JSON.stringify(d === undefined ? '' : d).slice(0, 200)}`; failures++; }
};

const BASE = 'mail.hostinger.com';

check('allows.exactHost', sameSite('https://mail.hostinger.com/settings', BASE));
check('allows.subdomain', sameSite('https://eu.mail.hostinger.com/x', BASE));
check('allows.withPortAndQuery', sameSite('https://mail.hostinger.com:443/a?b=1#c', BASE));
check('allows.caseInsensitive', sameSite('https://MAIL.Hostinger.COM/', BASE));

// الحيلة المعتادة: نطاق يبدأ باسم الموقع وينتهي عند المهاجم.
check('blocks.lookalikeSuffix', !sameSite('https://mail.hostinger.com.evil.test/', BASE));
check('blocks.lookalikePrefix', !sameSite('https://evil-mail.hostinger.com.attacker.test/', BASE));
check('blocks.parentDomain', !sameSite('https://hostinger.com/', BASE));
check('blocks.otherSite', !sameSite('https://example.com/', BASE));
check('blocks.javascriptUrl', !sameSite('javascript:alert(1)', BASE));
check('blocks.dataUrl', !sameSite('data:text/html,<h1>hi', BASE));
check('blocks.fileUrl', !sameSite('file:///etc/passwd', BASE));
check('blocks.garbage', !sameSite('not a url at all', BASE));
check('blocks.empty', !sameSite('', BASE));

// جلسة منفصلة: بيانات المزوّد لا تُخلط ببيانات التطبيق.
check('session.isolated', PARTITION.startsWith('persist:') && PARTITION !== 'persist:', PARTITION);

console.log(JSON.stringify(out, null, 1));
console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
process.exit(failures ? 1 : 0);
