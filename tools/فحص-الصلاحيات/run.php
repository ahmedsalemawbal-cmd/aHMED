<?php
/**
 * فحص الصلاحيات — من يرى ماذا، ومن يُمنع ولماذا.
 *
 * `SCH_Perms::may()` هي الحارس الذي يقف أمام كل شاشة في الداشبورد
 * (`class-dashboard.php` تسألها في التنقّل وفي التوجيه). وخطأٌ فيها لا
 * يظهر في أي فحصٍ ساكن: الشفرة سليمة والشاشة موجودة والباب مغلق.
 *
 * والعطل الذي كشفه هذا الفحص: `LOCKED` كانت تمنع **الرؤية والتعديل معًا**،
 * فيردّ `/dashboard/custody` و`/dashboard/audit` بـ403 لكل مستخدم بمن فيهم
 * المدير — وسببا القفل المكتوبان يقولان عكس ذلك: «سجل العهدة لا يُعدَّل»
 * و«سجل النظام للقراءة فقط».
 *
 *   php tools/فحص-الصلاحيات/run.php
 */

declare(strict_types=1);

/* قاعدةٌ فارغة: من لا صلاحيات مخصّصة له يعمل بدوره — وهو الحال المعتاد. */
putenv('EMPTY=1');

require __DIR__ . '/../فحص-الشاشات/محاكي-ووردبريس.php';

$pass = 0;
$fail = 0;

function ok(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ {$name}\n"; return; }
    $fail++;
    echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

/** يضع مستخدمًا بدورٍ وصلاحياتٍ ثم يسأل `may()` عن أقسام بعينها. */
function as_user(string $who, array $roles, array $caps, array $expect): void
{
    $GLOBALS['SCH_TEST_USERS']   = [1 => ['roles' => $roles, 'caps' => $caps]];
    $GLOBALS['SCH_CURRENT_USER'] = 1;

    echo "\n── {$who}\n";
    foreach ($expect as $section => [$want_view, $want_edit]) {
        $view = SCH_Perms::may($section, 'view');
        $edit = SCH_Perms::may($section, 'edit');
        ok(
            sprintf('%-12s رؤية=%s تعديل=%s', $section, $want_view ? 'نعم' : 'لا', $want_edit ? 'نعم' : 'لا'),
            $view === $want_view && $edit === $want_edit,
            sprintf('الواقع رؤية=%s تعديل=%s', var_export($view, true), var_export($edit, true))
        );
    }
}

/** الأدوار وصلاحياتها من المُنشِئ نفسه — فلا تتباعد قائمتان. */
$roles = [];
$src   = (string) file_get_contents(SCH_PATH . 'includes/class-activator.php');
preg_match_all("/'(sch_[a-z_]+)'\s*=>\s*\[\s*'label'\s*=>[^\]]*?'caps'\s*=>\s*\[(.*?)\]/s", $src, $m, PREG_SET_ORDER);
foreach ($m as $hit) {
    preg_match_all("/'([a-z_]+)'/", $hit[2], $caps);
    $roles[$hit[1]] = $caps[1];
}

echo "\n══════════════════════════════════════════════\n";
echo "  الصلاحيات: من يرى ماذا\n";
echo "══════════════════════════════════════════════\n";

echo "\n① الأقسام «للقراءة فقط» تُرى ولا تُعدَّل\n";
as_user('المدير', ['sch_principal'], $roles['sch_principal'], [
    'custody'  => [true, false],   // سجل العهدة: يُقرأ ولا يُعدَّل
    'audit'    => [true, false],   // سجل النظام: كذلك
    'students' => [true, true],
]);

echo "\n② قفل الدور يمنع الرؤية والتعديل معًا\n";
as_user('الحارس', ['sch_guard'], $roles['sch_guard'], [
    'clinic' => [false, false],
    'meds'   => [false, false],
    'nerve'  => [false, false],
    'ledger' => [false, false],    // كان اسمه `accounting` في القفل — ولا قسم بهذا الاسم
    'finance' => [false, false],
    'pickup' => [true,  true],     // عملُه هو
]);
as_user('السائق', ['sch_driver'], $roles['sch_driver'], [
    'clinic'  => [false, false],
    'ledger'  => [false, false],
    'payroll' => [false, false],
]);

echo "\n③ كل قسمٍ في `LOCKED` و`LOCKED_FOR` اسمٌ حقيقيّ\n";
$sections = array_keys(SCH_Dashboard::sections());
foreach (array_keys(SCH_Perms::LOCKED) as $slug) {
    ok("LOCKED: {$slug} قسمٌ موجود", in_array($slug, $sections, true));
}
foreach (SCH_Perms::LOCKED_FOR as $role => $slugs) {
    foreach ($slugs as $slug) {
        ok("LOCKED_FOR[{$role}]: {$slug} قسمٌ موجود", in_array($slug, $sections, true));
    }
}

echo "\n④ كل قسمٍ في `SENSITIVE` اسمٌ حقيقيّ — وإلا لم يصل إشعار المدير\n";
foreach (SCH_Perms::SENSITIVE as $slug) {
    ok("SENSITIVE: {$slug} قسمٌ موجود", in_array($slug, $sections, true));
}

echo "\n⑤ المقفل لا يُمنَح مهما طُلب\n";
$GLOBALS['SCH_TEST_USERS']   = [1 => ['roles' => ['sch_principal'], 'caps' => $roles['sch_principal']]];
$GLOBALS['SCH_CURRENT_USER'] = 1;
ok('custody مقفل عن المنح', SCH_Perms::is_locked('custody'));
ok('audit مقفل عن المنح', SCH_Perms::is_locked('audit'));
ok('students ليس مقفلًا', !SCH_Perms::is_locked('students'));

echo "\n⑥ نطاق الإشراف يتبع مفردات المراحل — والمجهول يُغلَق\n";

/*
 * `SCH_Org::STAGE_MAP` كانت مفاتيحها `middle` و`high`، ومفردات النظام
 * (`SCH_Classes::STAGES`) هي `kg · primary · intermediate · secondary`.
 * فالمرحلتان الأخيرتان تسقطان خارج الخريطة وكان الافتراض `'early'`
 * **يفتحهما** بدل أن يُغلقهما:
 *
 *   • مشرفة الابتدائي تجتاز `may_touch_class()` لكل شعبة في المدرسة
 *     بما فيها الثانوي.
 *   • ومشرف المتوسط لا يجتازها لشعبةٍ واحدة — وهو نطاقه هو.
 */
ok('الروضة ← early',        SCH_Org::scope_of_stage('kg') === 'early',           SCH_Org::scope_of_stage('kg'));
ok('الابتدائي ← early',     SCH_Org::scope_of_stage('primary') === 'early',      SCH_Org::scope_of_stage('primary'));
ok('المتوسط ← middle',      SCH_Org::scope_of_stage('intermediate') === 'middle', SCH_Org::scope_of_stage('intermediate'));
ok('الثانوي ← high',        SCH_Org::scope_of_stage('secondary') === 'high',     SCH_Org::scope_of_stage('secondary'));
ok('الثانوي ليس early',     SCH_Org::scope_of_stage('secondary') !== 'early',    SCH_Org::scope_of_stage('secondary'));
ok('المتوسط ليس early',     SCH_Org::scope_of_stage('intermediate') !== 'early', SCH_Org::scope_of_stage('intermediate'));
ok('المرحلة المجهولة تُغلَق', SCH_Org::scope_of_stage('زُهاء') === 'none',        SCH_Org::scope_of_stage('زُهاء'));
ok('الفراغ يُغلَق',          SCH_Org::scope_of_stage('') === 'none',              SCH_Org::scope_of_stage(''));
ok('null يُغلَق',            SCH_Org::scope_of_stage(null) === 'none',            SCH_Org::scope_of_stage(null));

// كل مفردة في STAGES لها نطاقٌ معلوم — وإلا فمرحلةٌ بلا مشرف
foreach (SCH_Classes::STAGES as $slug => $label) {
    ok("STAGES: «{$slug}» له نطاق إشراف", SCH_Org::scope_of_stage($slug) !== 'none', SCH_Org::scope_of_stage($slug));
}

echo "\n══════════════════════════════════════════════\n";
printf("  نجح %d · فشل %d\n", $pass, $fail);
echo "══════════════════════════════════════════════\n";

exit($fail === 0 ? 0 : 1);
