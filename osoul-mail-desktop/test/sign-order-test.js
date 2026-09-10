/* اختبار ترتيب التوقيع المحلي لحزمة macOS — يعمل على أي نظام بلا codesign.
 *
 * الخطأ الذي يمسكه: توقيع حزمة قبل ما بداخلها. codesign يرفضه بـ
 * "code object is not signed at all"، ولا يظهر إلا على جهاز ماك أثناء
 * البناء — فنتحقق من الترتيب هنا على شجرة مُصطنعة تحاكي حزمة Electron.
 */

'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const { collectTargets, isBundleMainExecutable } = require('../build/after-pack.js');

const out = {};
let failures = 0;
const check = (n, c, d) => {
  if (c) out[n] = 'PASS';
  else { out[n] = `FAIL ${JSON.stringify(d === undefined ? '' : d)}`; failures++; }
};

/** ملف بترويسة Mach-O حقيقية (universal) حتى يلتقطه الكاشف. */
function machO(file) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  const buf = Buffer.alloc(16);
  buf.writeUInt32BE(0xcafebabe, 0);
  fs.writeFileSync(file, buf);
}

function plainFile(file, text) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, text || 'not a binary');
}

/* شجرة تحاكي حزمة Electron المجمّعة، بما فيها الحالة التي كسرت البناء:
   مساعد ومكتبات داخل Electron Framework. */
const root = fs.mkdtempSync(path.join(os.tmpdir(), 'osoul-sign-'));
const app = path.join(root, 'Osoul Mail.app');
const FW = path.join(app, 'Contents/Frameworks');

machO(path.join(app, 'Contents/MacOS/Osoul Mail'));
plainFile(path.join(app, 'Contents/Info.plist'), '<plist/>');
plainFile(path.join(app, 'Contents/Resources/icon.icns'), 'icns');

machO(path.join(FW, 'Electron Framework.framework/Versions/A/Electron Framework'));
machO(path.join(FW, 'Electron Framework.framework/Versions/A/Helpers/chrome_crashpad_handler'));
machO(path.join(FW, 'Electron Framework.framework/Versions/A/Libraries/libEGL.dylib'));
machO(path.join(FW, 'Osoul Mail Helper (GPU).app/Contents/MacOS/Osoul Mail Helper (GPU)'));
machO(path.join(FW, 'Squirrel.framework/Versions/A/Squirrel'));
machO(path.join(FW, 'Squirrel.framework/Versions/A/Resources/ShipIt'));

// رابط رمزي كالذي في أطر macOS الحقيقية — يجب تجاهله لا توقيعه مرتين.
fs.symlinkSync('A', path.join(FW, 'Electron Framework.framework/Versions/Current'));

const targets = collectTargets(app).map((p) => p.slice(app.length + 1));
const at = (p) => targets.indexOf(p);

/* --- 1) كل حزمة بعد كل ما بداخلها --- */
let misordered = [];
for (const [i, p] of targets.entries()) {
  if (!/\.(framework|app|bundle)$/.test(p)) continue;
  const inner = targets.filter((q, j) => q.startsWith(`${p}/`) && j > i);
  if (inner.length) misordered.push(`${p} قبل ${inner.join(', ')}`);
}
check('bundleAfterContents', misordered.length === 0, misordered);

/* --- 2) الحالة التي كسرت البناء فعلًا --- */
check('crashpadBeforeFramework',
  at('Contents/Frameworks/Electron Framework.framework/Versions/A/Helpers/chrome_crashpad_handler')
  < at('Contents/Frameworks/Electron Framework.framework'));

check('dylibBeforeFramework',
  at('Contents/Frameworks/Electron Framework.framework/Versions/A/Libraries/libEGL.dylib')
  < at('Contents/Frameworks/Electron Framework.framework'));

check('shipItBeforeSquirrel',
  at('Contents/Frameworks/Squirrel.framework/Versions/A/Resources/ShipIt')
  < at('Contents/Frameworks/Squirrel.framework'));

/* --- 3) ما يجب أن يُوقَّع وما لا يجب --- */
/* الملفات التنفيذية الرئيسية للحزم تُوقَّع ضمن حزمها لا منفردة: توقيعها
   منفردة يجعل codesign يوقّع الحزمة قبل محتوياتها فيفشل البناء. */
check('frameworkMainBinaryExcluded',
  at('Contents/Frameworks/Electron Framework.framework/Versions/A/Electron Framework') === -1,
  targets.filter((p) => p.endsWith('/Electron Framework')));

check('helperAppMainBinaryExcluded',
  at('Contents/Frameworks/Osoul Mail Helper (GPU).app/Contents/MacOS/Osoul Mail Helper (GPU)') === -1,
  targets.filter((p) => p.includes('.app/Contents/MacOS/')));

check('outerAppMainBinaryExcluded', at('Contents/MacOS/Osoul Mail') === -1);

check('squirrelMainBinaryExcluded',
  at('Contents/Frameworks/Squirrel.framework/Versions/A/Squirrel') === -1);

/* لكن ما ليس ملفًا رئيسيًا يجب أن يُوقَّع منفردًا */
check('crashpadIncluded',
  at('Contents/Frameworks/Electron Framework.framework/Versions/A/Helpers/chrome_crashpad_handler') > -1);
check('dylibIncluded',
  at('Contents/Frameworks/Electron Framework.framework/Versions/A/Libraries/libEGL.dylib') > -1);
check('shipItIncluded',
  at('Contents/Frameworks/Squirrel.framework/Versions/A/Resources/ShipIt') > -1);
check('everyBundleIncluded',
  ['Contents/Frameworks/Electron Framework.framework',
   'Contents/Frameworks/Osoul Mail Helper (GPU).app',
   'Contents/Frameworks/Squirrel.framework'].every((b) => at(b) > -1));

/* الدالة نفسها */
check('detectsFrameworkMain', isBundleMainExecutable('/x/Electron Framework.framework/Versions/A/Electron Framework'));
check('detectsAppMain', isBundleMainExecutable('/x/Helper (GPU).app/Contents/MacOS/Helper (GPU)'));
check('doesNotFlagHelperTool', !isBundleMainExecutable('/x/E.framework/Versions/A/Helpers/chrome_crashpad_handler'));
check('doesNotFlagResource', !isBundleMainExecutable('/x/Squirrel.framework/Versions/A/Resources/ShipIt'));
check('doesNotFlagDylib', !isBundleMainExecutable('/x/E.framework/Versions/A/Libraries/libEGL.dylib'));
check('plistExcluded', !targets.some((p) => p.endsWith('.plist')), targets.filter((p) => p.endsWith('.plist')));
check('resourcesExcluded', !targets.some((p) => p.endsWith('.icns')));
check('symlinkExcluded', !targets.some((p) => p.endsWith('Versions/Current')),
  targets.filter((p) => p.includes('Current')));
check('noDuplicates', new Set(targets).size === targets.length);

/* --- 4) الحزمة الأم ليست ضمن القائمة (تُوقَّع أخيرًا على حدة) --- */
check('rootNotInTargets', !targets.includes(''), targets[0]);

fs.rmSync(root, { recursive: true, force: true });

console.log(JSON.stringify(out, null, 1));
console.log(`\nعدد العناصر: ${targets.length}`);
console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
process.exit(failures ? 1 : 0);
