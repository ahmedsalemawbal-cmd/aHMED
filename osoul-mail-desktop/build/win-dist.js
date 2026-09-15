/**
 * بناء مثبّت ويندوز.
 *
 * ويندوز 11 فيه "التحكم الذكي في التطبيقات" يحظر كل ملف تنفيذي غير موقّع،
 * وبلا زر تجاوز — فالمثبّت غير الموقّع لا يُثبَّت أصلًا على تلك الأجهزة.
 *
 * التوقيع هنا اختياري ويُفعَّل بمجرد توفّر بيانات الاعتماد، بطريقتين:
 *
 *   1) شهادة PFX تقليدية — electron-builder يقرأ CSC_LINK و
 *      CSC_KEY_PASSWORD وحده، فلا نمرّر شيئًا.
 *   2) خدمة التوقيع من Microsoft (Trusted Signing) — تحتاج خيارات في
 *      الإعدادات، ولا يجوز تركها ثابتة: electron-builder يفشل إن وجدها
 *      بلا بيانات اعتماد. فنضيفها هنا حين تتوفّر فقط.
 *
 * وبلا أيٍّ منهما يبقى البناء ناجحًا بمثبّت غير موقّع، لأن تعطيل البناء
 * كله لغياب شهادة يمنع حتى الاختبار.
 */

'use strict';

const { spawnSync } = require('child_process');

const AZURE = {
  account: process.env.AZURE_CODE_SIGNING_ACCOUNT,
  profile: process.env.AZURE_CERT_PROFILE,
  endpoint: process.env.AZURE_CODE_SIGNING_ENDPOINT || 'https://neu.codesigning.azure.net/',
  publisher: process.env.AZURE_PUBLISHER_NAME,
};

const args = ['electron-builder', '--win', '--x64'];

if (AZURE.account && AZURE.profile && process.env.AZURE_CLIENT_ID) {
  args.push(
    `--config.win.azureSignOptions.endpoint=${AZURE.endpoint}`,
    `--config.win.azureSignOptions.codeSigningAccountName=${AZURE.account}`,
    `--config.win.azureSignOptions.certificateProfileName=${AZURE.profile}`,
  );
  if (AZURE.publisher) {
    args.push(`--config.win.azureSignOptions.publisherName=${AZURE.publisher}`);
  }
  console.log('التوقيع: خدمة Microsoft Trusted Signing');
} else if (process.env.CSC_LINK) {
  console.log('التوقيع: شهادة PFX');
} else {
  console.log('تنبيه: بلا شهادة توقيع — ويندوز 11 سيحظر هذا المثبّت.');
}

const res = spawnSync('npx', args, { stdio: 'inherit', shell: process.platform === 'win32' });
process.exit(res.status == null ? 1 : res.status);
