<?php
/**
 * بيانات تجريبية لنظام المدرسة — تُشغَّل عبر: wp eval-file seed.php
 * الهدف: مدرسة صغيرة واقعية، لأن العطل لا يظهر على جدول فارغ.
 */

SCH_Loader::load_modules();
wp_set_current_user(1); // المدير — يملك كل الصلاحيات

$log = static function (string $m): void { echo $m . "\n"; };
$err = static function (string $where, $r) use ($log): bool {
    if (is_wp_error($r)) { $log("  !! {$where}: " . $r->get_error_code() . ' — ' . $r->get_error_message()); return true; }
    return false;
};

// ---------- السنة الدراسية ----------
$year = SCH_Years::create(['name' => '1447 / 2025-2026', 'start_date' => '2025-08-24', 'end_date' => '2026-06-11']);
if ($err('year', $year)) { $cur = SCH_Years::current(); $year = ['id' => $cur->id ?? 0]; }
$year_id = (int) $year['id'];
SCH_Years::set_current($year_id);
$log("year_id={$year_id}");

// سنة سابقة، لترقية العام معنى
$prev = SCH_Years::create(['name' => '1446 / 2024-2025', 'start_date' => '2024-08-25', 'end_date' => '2025-06-12']);
$log('prev year: ' . (is_wp_error($prev) ? 'skip' : $prev['id']));

// ---------- الشعب ----------
$classes = [];
$plan = [
    ['kg', 'روضة ثانية', 'أ', 22],
    ['primary', 'الأول الابتدائي', 'أ', 28],
    ['primary', 'الأول الابتدائي', 'ب', 28],
    ['primary', 'الرابع الابتدائي', 'أ', 30],
    ['intermediate', 'الأول المتوسط', 'أ', 30],
    ['secondary', 'الأول الثانوي', 'أ', 32],
];
foreach ($plan as [$stage, $grade, $section, $cap]) {
    $c = SCH_Classes::create(['year_id' => $year_id, 'stage' => $stage, 'grade_level' => $grade, 'section' => $section, 'capacity' => $cap]);
    if (!$err("class {$grade}/{$section}", $c)) { $classes[] = (int) $c['id']; }
}
$log('classes: ' . count($classes));

// ---------- الموظفون: دور لكل واجهة ----------
$staff_plan = [
    ['أحمد سالم عوبل',      'principal@example.test',  'sch_principal'],
    ['نورة عبدالله القحطاني','deputy@example.test',     'sch_deputy'],
    ['خالد فهد الزهراني',   'supervisor@example.test', 'sch_supervisor'],
    ['منى صالح الحربي',     'teacher@example.test',    'sch_teacher'],
    ['سعد ناصر العتيبي',    'teacher2@example.test',   'sch_teacher'],
    ['هند محمد الدوسري',    'counselor@example.test',  'sch_counselor'],
    ['فاطمة علي الشهري',    'clinic@example.test',     'sch_staff'],
    ['عبدالرحمن يوسف باني', 'accountant@example.test', 'sch_accountant'],
    ['ماجد سعيد الغامدي',   'transport@example.test',  'sch_transport_supervisor'],
    ['سلطان عايد المطيري',  'driver@example.test',     'sch_driver'],
    ['بدر حمد السبيعي',     'guard@example.test',      'sch_guard'],
    ['ريم طارق العنزي',     'content@example.test',    'sch_content'],
];
$staff = [];
foreach ($staff_plan as $i => [$name, $email, $role]) {
    $s = SCH_Staff::create([
        'full_name' => $name, 'email' => $email, 'role' => $role,
        'password'  => 'Test!12345',
        'phone'     => '05500000' . str_pad((string) ($i + 10), 2, '0', STR_PAD_LEFT),
        'job_title' => SCH_Staff::role_label($role),
        'department'=> 'عام',
        'hired_at'  => '2024-09-01',
    ]);
    if (!$err("staff {$role}", $s)) { $staff[$role][] = $s; }
}
$log('staff: ' . array_sum(array_map('count', $staff)));

// ---------- الطلاب + أولياء الأمور ----------
$first  = ['محمد', 'عبدالله', 'سارة', 'نورة', 'خالد', 'ريم', 'فيصل', 'لمى', 'تركي', 'جنى', 'ياسر', 'دانة', 'راكان', 'مريم', 'سلمان', 'هيا', 'بدر', 'شهد', 'ماجد', 'رغد'];
$father = ['سالم', 'ناصر', 'فهد', 'عبدالعزيز', 'سعد', 'حمد', 'طارق', 'يوسف'];
$grand  = ['محمد', 'علي', 'صالح', 'إبراهيم', 'عمر'];
$family = ['العتيبي', 'القحطاني', 'الحربي', 'الزهراني', 'الشهري', 'الدوسري', 'المطيري', 'الغامدي', 'باني', 'عوبل'];

$students = [];
$guardians = [];
$n = 0;
foreach ($classes as $ci => $class_id) {
    $per = $ci === 0 ? 5 : 7;
    for ($k = 0; $k < $per; $k++) {
        $n++;
        $fn = $first[$n % count($first)];
        $fa = $father[$n % count($father)];
        $gr = $grand[$n % count($grand)];
        $fm = $family[$n % count($family)];

        $r = SCH_Enrollment::register([
            'first_name' => $fn, 'father_name' => $fa, 'grand_name' => $gr, 'family_name' => $fm,
            'national_id' => (string) (1050000000 + $n * 7919),
            'birth_date' => sprintf('%04d-%02d-%02d', 2012 - intdiv($n, 7), ($n % 12) + 1, ($n % 27) + 1),
            'gender' => $n % 2 ? 'male' : 'female',
            'id_type' => 'national',
            'nationality' => 'سعودي',
            'class_id' => $class_id,
        ], []);
        if ($err("student #{$n}", $r)) { continue; }
        $students[] = (int) $r['id'];

        // ولي أمر لكل طالبين
        if ($n % 2 === 1) {
            $g = SCH_Guardians::create([
                'first_name' => $fa, 'father_name' => $gr, 'grand_name' => 'عبدالله', 'family_name' => $fm,
                'phone' => '0551' . str_pad((string) (100000 + $n * 13), 6, '0', STR_PAD_LEFT),
                'email' => "guardian{$n}@example.test",
                'relation' => 'father',
            ]);
            if (!$err("guardian #{$n}", $g)) {
                $guardians[] = $g;
                SCH_Guardians::link((int) ($g['user_id'] ?? 0), (int) $r['id'], 'father', true);
            }
        } elseif ($guardians !== []) {
            $last = end($guardians);
            SCH_Guardians::link((int) ($last['user_id'] ?? 0), (int) $r['id'], 'father', true);
        }
    }
}
$log('students: ' . count($students) . '  guardians: ' . count($guardians));

// ---------- المواد ----------
foreach ([['رياضيات', 'MATH'], ['لغة عربية', 'ARAB'], ['علوم', 'SCI'], ['إنجليزي', 'ENG'], ['دراسات إسلامية', 'ISL']] as [$sn, $sc]) {
    $r = SCH_Subjects::create(['name' => $sn, 'code' => $sc]);
    $err("subject {$sn}", $r);
}
$log('subjects done');

// ---------- النقل ----------
$bus = SCH_Buses::create(['plate_no' => 'ABC-1234', 'capacity' => 30, 'model' => 'Toyota Coaster 2022', 'driver_id' => (int) ($staff['sch_driver'][0]['user_id'] ?? 0)]);
$err('bus', $bus);
$route = SCH_Routes::create(['name' => 'مسار حي النرجس', 'bus_id' => (int) ($bus['id'] ?? 0), 'direction' => 'both']);
$err('route', $route);
if (!is_wp_error($route)) {
    $rid = (int) $route['id'];
    foreach ([['بداية النرجس', 24.8300, 46.6300], ['دوار الياسمين', 24.8355, 46.6421], ['شارع الملك', 24.8410, 46.6502], ['المدرسة', 24.8480, 46.6600]] as [$nm, $lat, $lng]) {
        $err('stop', SCH_Routes::add_stop(['route_id' => $rid, 'name' => $nm, 'lat' => $lat, 'lng' => $lng, 'pickup_time' => '06:30']));
    }
    $stops = SCH_Routes::stops($rid);
    foreach (array_slice($students, 0, 12) as $sid) {
        $err('sub', SCH_Routes::subscribe(['student_id' => $sid, 'route_id' => $rid, 'stop_id' => (int) ($stops[0]->id ?? 0), 'fee_amount' => 1800]));
    }
}
$log('transport done');

// ---------- المالية ----------
$plan_r = SCH_Finance::create_plan(['name' => 'رسوم ابتدائي 1447', 'stage' => 'primary', 'amount' => 12000, 'year_id' => $year_id]);
$err('fee plan', $plan_r);
$log('finance done');

// ---------- الحضور: شهر واقعي ----------
$att = 0;
for ($d = 1; $d <= 18; $d++) {
    $date = gmdate('Y-m-d', strtotime("-{$d} days"));
    $wd = (int) gmdate('w', strtotime($date));
    if ($wd === 5 || $wd === 6) { continue; }
    foreach ($students as $i => $sid) {
        $roll = ($i * 7 + $d * 3) % 20;
        $st = $roll === 0 ? 'absent' : ($roll === 1 ? 'late' : ($roll === 2 ? 'excused' : 'present'));
        $r = SCH_Attendance::mark($sid, $date, $st, 'manual');
        if (!is_wp_error($r)) { $att++; }
    }
}
$log("attendance rows: {$att}");

$log('--- SEED DONE ---');
