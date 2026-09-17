/**
 * توقيع محلي (ad-hoc) لحزمة macOS بعد تجميعها.
 *
 * لا نملك شهادة Apple Developer، لكن غياب التوقيع كليًا ليس خيارًا: أجهزة
 * Apple Silicon ترفض تشغيل أي ثنائي arm64 بلا توقيع فتقتل العملية فورًا،
 * فلا ينفع معها لا "كليك يمين ← Open" ولا مسح علامة الحجر. التوقيع المحلي
 * يجعل النظام يقبل تشغيل البرنامج، ويبقى تحذير "مطوّر غير موثوق" لمرة واحدة
 * فقط لأن التطبيق غير موثّق (notarized).
 *
 * الترتيب هو كل شيء: توقيع حزمة قبل ما بداخلها يفشل بـ "code object is not
 * signed at all". لذلك نمشي في الشجرة ونُخرج كل عنصر بعد محتوياته، فيأتي
 * chrome_crashpad_handler قبل Electron Framework، وتأتي الأطر والمساعدات
 * كلها قبل الحزمة الأم.
 */

'use strict';

const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');

/** حزم macOS التي تُوقَّع ككيان واحد بعد توقيع ما بداخلها. */
const BUNDLE_EXT = /\.(framework|app|bundle|xpc|appex)$/;

/**
 * هل هذا الملف هو الملف التنفيذي الرئيسي لحزمة؟
 *
 * codesign يحوّل مسار الملف الرئيسي إلى الحزمة الحاوية له، فتوقيعه منفردًا
 * يعني توقيع الحزمة قبل أوانها ويفشل بـ "code object is not signed at all".
 * هذه الملفات تُوقَّع ضمن حزمها لا قبلها.
 */
function isBundleMainExecutable(file) {
  // ‎…/Name.app/Contents/MacOS/<تنفيذي>
  if (/\.app\/Contents\/MacOS\/[^/]+$/.test(file)) return true;
  // ‎…/Name.framework/Versions/<إصدار>/Name
  const m = /([^/]+)\.framework\/Versions\/[^/]+\/([^/]+)$/.exec(file);
  return !!m && m[1] === m[2];
}

/** هل الملف ثنائي Mach-O (تنفيذي أو مكتبة)؟ نقرأ الرقم السحري بدل التخمين. */
function isMachO(file) {
  let fd;
  try {
    fd = fs.openSync(file, 'r');
    const buf = Buffer.alloc(4);
    if (fs.readSync(fd, buf, 0, 4, 0) < 4) return false;
    const magic = buf.readUInt32BE(0);
    return (
      magic === 0xfeedface || magic === 0xfeedfacf || // 32/64-bit
      magic === 0xcefaedfe || magic === 0xcffaedfe || // معكوس البايتات
      magic === 0xcafebabe || magic === 0xcafebabf    // universal (fat)
    );
  } catch (_) {
    return false;
  } finally {
    if (fd !== undefined) fs.closeSync(fd);
  }
}

/**
 * كل ما يحتاج توقيعًا داخل الحزمة، مرتّبًا من الداخل إلى الخارج.
 * الروابط الرمزية تُتجاهل: أطر macOS مليئة بـ Versions/Current، وتوقيعها
 * يوقّع الهدف مرتين.
 */
function collectTargets(root) {
  const targets = [];

  const walk = (dir) => {
    let entries;
    try {
      entries = fs.readdirSync(dir, { withFileTypes: true });
    } catch (_) {
      return;
    }
    for (const entry of entries) {
      const full = path.join(dir, entry.name);
      if (entry.isSymbolicLink()) continue;

      if (entry.isDirectory()) {
        walk(full);
        if (BUNDLE_EXT.test(entry.name)) targets.push(full);
      } else if (entry.isFile() && isMachO(full) && !isBundleMainExecutable(full)) {
        targets.push(full);
      }
    }
  };

  walk(root);
  return targets;
}

function sign(target) {
  execFileSync('codesign', [
    '--force',
    '--sign', '-',        // هوية محلية (ad-hoc)
    '--timestamp=none',   // الختم الزمني يحتاج شهادة حقيقية
    target,
  ], { stdio: 'inherit' });
}

/**
 * النسخ المؤقتة لكل معمارية في بناء universal.
 *
 * electron-builder يبني x64 و arm64 في مجلدين ينتهيان بـ "-temp" ثم يدمجهما.
 * والدمج يشترط تطابق بايتات كل ملف غير ثنائي بين النسختين، بينما التوقيع
 * ينتج _CodeSignature/CodeResources مختلفًا لكل منهما — فيفشل بـ
 * "Expected all non-binary files to have identical SHAs". لذلك نوقّع النسخة
 * المدموجة وحدها؛ و electron-builder يستدعي الخطاف عليها أيضًا بعد الدمج.
 */
function isIntermediateArchBuild(appOutDir) {
  return /-temp$/.test(appOutDir);
}

/* ---------------------------------------------------------- تقليم اللغات
 *
 * Electron يشحن خمسًا وخمسين ترجمة لواجهة Chromium بأربعين ميجابايت،
 * والتطبيق بلغتين. الفرق يدفعه كل موظف مرةً في التنزيل ومرةً على القرص.
 *
 * خيار electronLanguages في electron-builder يعمل على ماك ولينكس ويُتجاهل
 * على ويندوز، فنقلّم هنا بأنفسنا للمنصّتين معًا.
 *
 * en-US يبقى دائمًا: هو ما يرجع إليه Chromium حين لا يجد لغة النظام،
 * والتطبيق يُثبّت لغة العرض صراحةً في main.js فلا يطلب ملفًا محذوفًا.
 */
const KEEP_PAK = new Set(['ar.pak', 'en-US.pak']);
const KEEP_LPROJ = new Set(['ar.lproj', 'en.lproj', 'en_GB.lproj', 'en-US.lproj']);

function localeDirs(appOutDir, platform) {
  if (platform === 'darwin') {
    const name = fs.readdirSync(appOutDir).find((f) => f.endsWith('.app'));
    if (!name) return [];
    return [path.join(appOutDir, name, 'Contents', 'Frameworks',
      'Electron Framework.framework', 'Versions', 'A', 'Resources')];
  }
  return [path.join(appOutDir, 'locales')];
}

function sizeOf(target) {
  const stat = fs.statSync(target);
  if (!stat.isDirectory()) return stat.size;
  return fs.readdirSync(target).reduce((sum, f) => sum + sizeOf(path.join(target, f)), 0);
}

function pruneLocales(appOutDir, platform) {
  let removed = 0;
  let freed = 0;
  for (const dir of localeDirs(appOutDir, platform)) {
    if (!fs.existsSync(dir)) continue;
    for (const entry of fs.readdirSync(dir)) {
      const drop = (entry.endsWith('.pak') && !KEEP_PAK.has(entry))
        || (entry.endsWith('.lproj') && !KEEP_LPROJ.has(entry));
      if (!drop) continue;
      const full = path.join(dir, entry);
      freed += sizeOf(full);
      fs.rmSync(full, { recursive: true, force: true });
      removed++;
    }
  }
  if (removed) {
    console.log(`  • حُذفت ${removed} ترجمة غير مستعملة (${(freed / 1048576).toFixed(1)} م.ب)`);
  }
  return { removed, freed };
}

exports.default = async function afterPack(context) {
  if (!isIntermediateArchBuild(context.appOutDir)) {
    pruneLocales(context.appOutDir, context.electronPlatformName);
  }

  if (context.electronPlatformName !== 'darwin') return;
  if (isIntermediateArchBuild(context.appOutDir)) {
    console.log(`  • تخطّي التوقيع للنسخة المؤقتة ${context.appOutDir}`);
    return;
  }

  const name = context.packager.appInfo.productFilename;
  const appPath = path.join(context.appOutDir, `${name}.app`);
  if (!fs.existsSync(appPath)) {
    throw new Error(`afterPack: لم أجد الحزمة المتوقعة ${appPath}`);
  }

  const targets = collectTargets(appPath);
  for (const target of targets) sign(target);
  sign(appPath);

  // نتحقق فعليًا بدل الاكتفاء بعدم ظهور خطأ.
  execFileSync('codesign', ['--verify', '--strict', '--deep', appPath], { stdio: 'inherit' });
  console.log(`  • ad-hoc signed ${targets.length + 1} objects in ${appPath}`);
};

// يُستخدم في الاختبار للتحقق من ترتيب التوقيع دون الحاجة إلى macOS.
exports.collectTargets = collectTargets;
exports.isBundleMainExecutable = isBundleMainExecutable;
exports.isIntermediateArchBuild = isIntermediateArchBuild;
exports.pruneLocales = pruneLocales;
exports.KEEP_PAK = KEEP_PAK;
