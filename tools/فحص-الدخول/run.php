<?php
/**
 * فحص بوابة الدخول الموحّدة — أين يهبط كلّ دور؟
 *
 * الباب واحد (`/login`)، والوعد أن يأخذ كلًّا إلى تطبيقه. وكسرُ هذا الوعد
 * لا يظهر في أي فحصٍ ساكن: الشفرة سليمة، والصلاحيات سليمة، والوجهة خاطئة.
 *
 * والعطل الذي كشفه هذا الفحص: الحارس يملك `sch_scan_gate` وهي أيضًا صلاحية
 * قسم «نداء الانصراف» في الداشبورد، والمعلم يملك `sch_manage_attendance`
 * وهي صلاحية قسم «الحضور» — فكان سؤال الداشبورد يسبق سؤال التطبيق، ويُرَدّ
 * الاثنان إلى `/dashboard` ولا يبلغان تطبيقيهما أبدًا.
 *
 *   php tools/فحص-الدخول/run.php
 */

declare(strict_types=1);

/*
 * قاعدةٌ فارغة عمدًا: التوجيه شأن **الأدوار** لا الصلاحيات المخصّصة.
 * وقاعدةٌ مصنوعة تجعل `SCH_Perms::is_custom()` صحيحةً لكل مستخدم، فتُغلق
 * كل الأقسام وتفرغ `allowed_areas()` — وهو حال مستخدمٍ عُدّلت صلاحياته
 * يدويًّا، لا حال المستخدم المعتاد الذي يعمل بدوره كما هو.
 */
putenv('EMPTY=1');

require __DIR__ . '/../فحص-الشاشات/محاكي-ووردبريس.php';

/** الأدوار وصلاحياتها كما يسجّلها `SCH_Activator::seed_roles()` حرفيًّا. */
$roles = [];
$src   = (string) file_get_contents(SCH_PATH . 'includes/class-activator.php');
preg_match_all(
    "/'(sch_[a-z_]+)'\s*=>\s*\[\s*'label'\s*=>[^\]]*?'caps'\s*=>\s*\[(.*?)\]/s",
    $src,
    $m,
    PREG_SET_ORDER
);
foreach ($m as $hit) {
    preg_match_all("/'([a-z_]+)'/", $hit[2], $caps);
    $roles[$hit[1]] = $caps[1];
}

/** [الدور، الوجهة المتوقّعة، لماذا] */
$cases = [
    ['sch_guard',    '/gate/',      'الحارس عند البوابة يمسح البطاقات — لا يدير مدرسة'],
    ['sch_driver',   '/driver/',    'السائق في الحافلة'],
    ['sch_teacher',  '/teacher/',   'المعلم في الفصل — وله صلاحية الحضور التي لها قسمٌ في الداشبورد'],
    ['sch_student',  '/student/',   'الطالب'],
    ['sch_guardian', '/app/',       'وليّ الأمر'],
    ['sch_principal',   '/dashboard/', 'المدير مكانه الداشبورد'],
    ['sch_supervisor',  '/dashboard/', 'مشرفة المرحلة تملك صلاحية الحضور كذلك — ولا تُرسَل لتطبيق المعلم'],
    ['sch_deputy',      '/dashboard/', 'الوكيل'],
    ['sch_accountant',  '/dashboard/', 'المحاسب'],
    ['sch_counselor',   '/dashboard/', 'الأخصائي الاجتماعي'],
    ['sch_staff',       '/dashboard/', 'الموظف الإداري'],
    ['sch_content',     '/dashboard/', 'منسّق المحتوى'],
];

$pass = 0;
$fail = 0;

function ok(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✓ {$name}\n"; return; }
    $fail++;
    echo "  ✗ {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

echo "\n══════════════════════════════════════════════\n";
echo "  بوابة الدخول: أين يهبط كلّ دور؟\n";
echo "══════════════════════════════════════════════\n\n";

echo "① أدوارٌ مقروءة من المُنشِئ: " . count($roles) . "\n\n";

echo "② الوجهة لكل دور\n";
foreach ($cases as [$role, $want, $why]) {
    if (!isset($roles[$role])) {
        ok("الدور {$role} معرَّف", false, 'غير موجود في المُنشِئ');
        continue;
    }

    $GLOBALS['SCH_TEST_USERS'] = [9 => ['roles' => [$role], 'caps' => $roles[$role]]];
    $GLOBALS['SCH_CURRENT_USER'] = 9;

    $got = SCH_Portal::home_for(9);
    ok(
        sprintf('%-22s ← %s', $role, $want),
        str_contains($got, $want),
        'ذهب إلى ' . $got . ' · ' . $why
    );
}

echo "\n③ من جمع دورين\n";

// معلّمٌ هو مشرف مرحلة كذلك — الدور الأوسع يغلب
$GLOBALS['SCH_TEST_USERS'] = [9 => [
    'roles' => ['sch_teacher', 'sch_supervisor'],
    'caps'  => [...$roles['sch_teacher'], ...$roles['sch_supervisor']],
]];
$GLOBALS['SCH_CURRENT_USER'] = 9;
$got = SCH_Portal::home_for(9);
ok('معلّم + مشرف مرحلة ← /dashboard/', str_contains($got, '/dashboard/'), 'ذهب إلى ' . $got);

// حارسٌ هو وليّ أمر كذلك — البوابة أخصّ، وكلاهما دورُ تطبيق
$GLOBALS['SCH_TEST_USERS'] = [9 => [
    'roles' => ['sch_guardian', 'sch_guard'],
    'caps'  => [...$roles['sch_guardian'], ...$roles['sch_guard']],
]];
$got = SCH_Portal::home_for(9);
ok('حارس + وليّ أمر ← /gate/', str_contains($got, '/gate/'), 'ذهب إلى ' . $got);

echo "\n④ التوجيه نيابةً عن مستخدمٍ آخر\n";

// النداء من الكرون لبناء رابط إشعار: يجب أن يُعطي وجهة **صاحب الحساب**
// لا وجهة من يشغّل الطلب.
$GLOBALS['SCH_TEST_USERS'] = [
    9  => ['roles' => ['sch_guardian'], 'caps' => $roles['sch_guardian']],
    77 => ['roles' => ['sch_principal'], 'caps' => $roles['sch_principal']],
];
$GLOBALS['SCH_CURRENT_USER'] = 77;
$got = SCH_Portal::home_for(9);
ok('وجهة وليّ الأمر تُبنى صحيحة والمدير هو المُشغِّل', str_contains($got, '/app/'), 'ذهب إلى ' . $got);

echo "\n══════════════════════════════════════════════\n";
printf("  نجح %d · فشل %d\n", $pass, $fail);
echo "══════════════════════════════════════════════\n";

exit($fail === 0 ? 0 : 1);
