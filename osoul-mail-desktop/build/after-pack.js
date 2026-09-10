/**
 * توقيع محلي (ad-hoc) لحزمة macOS بعد تجميعها.
 *
 * لا نملك شهادة Apple Developer، لكن غياب التوقيع كليًا ليس خيارًا: أجهزة
 * Apple Silicon ترفض تشغيل أي ثنائي arm64 بلا توقيع فتقتل العملية فورًا،
 * فلا ينفع معها لا "كليك يمين ← Open" ولا مسح علامة الحجر. التوقيع المحلي
 * (codesign --sign -) يجعل النظام يقبل تشغيل البرنامج، ويبقى تحذير "مطوّر
 * غير موثوق" لمرة واحدة فقط لأن التطبيق غير موثّق (notarized).
 *
 * نوقّع الأطر والمساعدات من الداخل إلى الخارج: توقيع الحزمة الأم يفشل أو
 * يصبح غير صالح إن وُقِّعت قبل ما تحتويه.
 */

'use strict';

const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');

function sign(target) {
  execFileSync('codesign', [
    '--force',
    '--sign', '-',            // هوية محلية (ad-hoc)
    '--timestamp=none',       // الختم الزمني يحتاج شهادة حقيقية
    target,
  ], { stdio: 'inherit' });
}

/** كل ما يجب توقيعه داخل الحزمة، من الأعمق إلى الأسطح. */
function innerTargets(appPath) {
  const out = [];
  const frameworks = path.join(appPath, 'Contents', 'Frameworks');
  if (!fs.existsSync(frameworks)) return out;

  for (const entry of fs.readdirSync(frameworks)) {
    const full = path.join(frameworks, entry);
    if (entry.endsWith('.app')) {
      // تطبيقات مساعدة (GPU / Renderer / Plugin)
      const bin = path.join(full, 'Contents', 'MacOS');
      if (fs.existsSync(bin)) {
        for (const f of fs.readdirSync(bin)) out.push(path.join(bin, f));
      }
      out.push(full);
    } else if (entry.endsWith('.framework') || entry.endsWith('.dylib')) {
      out.push(full);
    }
  }
  return out;
}

exports.default = async function afterPack(context) {
  if (context.electronPlatformName !== 'darwin') return;

  const name = context.packager.appInfo.productFilename;
  const appPath = path.join(context.appOutDir, `${name}.app`);
  if (!fs.existsSync(appPath)) {
    throw new Error(`afterPack: لم أجد الحزمة المتوقعة ${appPath}`);
  }

  for (const target of innerTargets(appPath)) sign(target);
  sign(appPath);

  // نتحقق فعليًا بدل الاكتفاء بعدم ظهور خطأ.
  execFileSync('codesign', ['--verify', '--strict', appPath], { stdio: 'inherit' });
  console.log(`  • ad-hoc signed  ${appPath}`);
};
