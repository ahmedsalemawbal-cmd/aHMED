/* جذور الثقة وتصنيف أخطاء الشهادات.
 *
 * العطل الذي أوقف الموظفين كان شهادة يعترضها برنامج حماية: الاتصال يُرفض
 * وتظهر رسالة "حاول مرة أخرى" التي لا تدل على شيء. هذا الفحص يحرس الأمرين
 * معًا — أن قائمة الثقة تُبنى سليمة، وأن الخطأ يُسمّى باسمه.
 */

'use strict';

const tls = require('tls');
const ca = require('../src/main/ca');
const { classify } = require('../src/main/session');

const out = {};
let failures = 0;
const check = (n, c, d) => {
  if (c) out[n] = 'PASS';
  else { out[n] = `FAIL ${JSON.stringify(d === undefined ? '' : d).slice(0, 300)}`; failures++; }
};

/* --- قائمة الثقة --- */
const bundle = ca.bundle();
check('bundle.isArray', Array.isArray(bundle) && bundle.length > 0, bundle.length);
check('bundle.keepsNodeRoots',
  (tls.rootCertificates || []).every((r) => bundle.includes(r)),
  { node: (tls.rootCertificates || []).length, bundle: bundle.length });
check('bundle.everyEntryIsPem',
  bundle.every((p) => /^-----BEGIN CERTIFICATE-----[\s\S]+-----END CERTIFICATE-----$/.test(p.trim())));
check('bundle.noDuplicates', new Set(bundle).size === bundle.length,
  bundle.length - new Set(bundle).size);
check('bundle.cached', ca.bundle() === bundle);
check('bundle.countsSystemRoots', typeof ca.systemCount() === 'number', ca.systemCount());

/* --- تصنيف الأخطاء --- */
const code = (err) => classify(err).osoul.code;

check('cert.selfSignedInChain',
  code({ message: 'self signed certificate in certificate chain', code: 'SELF_SIGNED_CERT_IN_CHAIN' }) === 'CERT');
check('cert.unableToVerify',
  code({ message: 'unable to verify the first certificate', code: 'UNABLE_TO_VERIFY_LEAF_SIGNATURE' }) === 'CERT');
check('cert.expired',
  code({ message: 'certificate has expired', code: 'CERT_HAS_EXPIRED' }) === 'CERT');
check('cert.wrongHostname',
  code({ message: "Hostname/IP does not match certificate's altnames" }) === 'CERT');

// اعتراض TLS يأتي أحيانًا ملفوفًا بـ ESOCKET؛ يجب ألا يُقرأ كانقطاع شبكة.
check('cert.beatsSocketWrapper',
  code({ message: 'self signed certificate in certificate chain', code: 'ESOCKET' }) === 'CERT');

check('auth.stillAuth',
  code({ message: 'NO [AUTHENTICATIONFAILED] Invalid credentials' }) === 'AUTH');
check('network.stillNetwork',
  code({ message: 'getaddrinfo ENOTFOUND imap.example.invalid', code: 'ENOTFOUND' }) === 'NETWORK');
check('network.refusedStillNetwork',
  code({ message: 'connect ECONNREFUSED 127.0.0.1:993', code: 'ECONNREFUSED' }) === 'NETWORK');
check('unknown.staysUnknown', code({ message: 'something else entirely' }) === 'UNKNOWN');

console.log(JSON.stringify(out, null, 1));
console.log(`\nجذور: ${bundle.length} (من النظام: ${ca.systemCount()})`);
console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
process.exit(failures ? 1 : 0);
