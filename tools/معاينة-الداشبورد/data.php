<?php
/**
 * بيانات العرض للداشبورد — مطابقة لبيانات التصميم عمدًا.
 *
 * خمسة طلاب بأسمائهم وأرقامهم كما في إطار «حضور الطلاب»، فتصير المقارنة
 * بين ما بُني وما صُمِّم صورةً بصورة لا تقريبًا.
 */

declare(strict_types=1);

const SCH_ROSTER = [
    ['id' => 1, 'full_name' => 'احمد سالم محمد عوبل',            'academic_no' => '84201042'],
    ['id' => 2, 'full_name' => 'باسل سالم محمد عوبل',            'academic_no' => '84201043'],
    ['id' => 3, 'full_name' => 'سطسط سيس سييس سسطشش',            'academic_no' => '84201051'],
    ['id' => 4, 'full_name' => 'علي سالم محمد عوبل',             'academic_no' => '84201064'],
    ['id' => 5, 'full_name' => 'فهد احمد محمد سالم',             'academic_no' => '84201078'],
];

/** الحالة تُضبط بمتغيّر بيئة، فتُرى الشاشة فارغةً وممتلئة بلا تعديل ملف. */
/** الوضع الفارغ: طالبٌ لا شيء له بعد — لاختبار كل فرعٍ فارغ في الشاشة. */
function sch_empty_mode(): bool { return getenv('EMPTY') === '1'; }

function sch_demo_state(int $i): array
{
    $mode = getenv('DEMO') ?: 'fresh';

    if ($mode === 'fresh') {                       // أول الصباح: لم يُمسح أحد
        return ['status' => null, 'checked_in_at' => null, 'method' => null, 'minutes_late' => 0];
    }

    // منتصف الطابور: مُسح بعضهم، وواحدٌ متأخر
    $t = [
        1 => ['present', '07:12:40'],
        2 => ['present', '07:18:05'],
        3 => ['late',    '07:46:11'],
        4 => [null,      null],
        5 => ['excused', null],
    ][$i] ?? [null, null];

    return [
        'status'        => $t[0],
        'checked_in_at' => $t[1] ? date('Y-m-d') . ' ' . $t[1] : null,
        'method'        => $t[0] === null ? null : ($t[0] === 'excused' ? 'manual' : 'qr'),
        'minutes_late'  => $t[0] === 'late' ? 16 : 0,
    ];
}

final class SCH_Attendance
{
    public const STATUSES = ['present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر', 'excused' => 'غياب بعذر'];

    public static function last_days(int $sid, int $days = 30): array
    {
        $out = []; $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'off' => 0];
        $labels = self::STATUSES + ['off' => 'لا دوام'];
        for ($i = 0; $i < $days; $i++) {
            $k = $i % 7 === 5 ? 'off' : ($i % 9 === 4 ? 'absent' : ($i % 11 === 7 ? 'late' : ($i % 13 === 3 ? 'excused' : 'present')));
            $counts[$k]++;
            $out[] = ['date' => date('Y-m-d', strtotime('-' . ($days - 1 - $i) . ' days')), 'key' => $k, 'label' => $labels[$k]];
        }
        return ['days' => $out, 'counts' => $counts];
    }

    public static function expected_in(): string { return '07:30'; }
    public static function rate(int $student_id, int $days = 60): float { return 92.5; }
    public static function grace(): int { return 10; }

    public static function status_for_arrival(?string $occurred): array
    {
        return $occurred === null ? ['absent', 0] : ['present', 0];
    }

    public static function sheet(int $class_id, string $date): array { return self::day_sheet($date); }

    public static function day_summary(string $date): array
    {
        $out = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
        foreach (self::day_sheet($date) as $r) {
            if ($r->status !== null && isset($out[$r->status])) { $out[$r->status]++; }
        }
        return $out;
    }

    /* نسخة مطابقة لتوقيع الطبقة وشكل مخرجاتها حرفيًّا — بلا `class_label`
       المخترَعة: القالب يبني اسم الشعبة من stage/grade_level/section. */
    public static function day_sheet(string $date): array
    {
        $out = [];
        foreach (SCH_ROSTER as $s) {
            $st = sch_demo_state((int) $s['id']);
            $out[] = (object) ($s + $st + [
                'first_name'  => explode(' ', $s['full_name'])[0],
                'student_no'  => 'S' . $s['academic_no'],
                'grade_level' => 'الرابع',
                'section'     => 'أ',
                'class_id'    => 1,
                'stage'       => 'ابتدائي',
                'note'        => null,
                'photo_file'  => '',
            ]);
        }
        return $out;
    }

    public static function close_day(string $date): int { return 0; }

    public static function mark(int $sid, string $d, string $st, string $m = 'manual', string $n = '', ?string $o = null, int $l = 0): bool
    {
        return true;
    }
}

final class SCH_Custody
{
    public const CHECKPOINTS = [
        'bus_board' => 'صعد الباص', 'gate_in' => 'دخل البوابة', 'gate_out' => 'خرج من البوابة',
        'early_out' => 'خروج مبكر', 'bus_alight' => 'نزل من الباص',
        'manual' => 'تحديث يدوي', 'correction' => 'تصحيح',
    ];

    public static function recent(int $limit = 10): array
    {
        if ((getenv('DEMO') ?: 'fresh') === 'fresh') { return []; }

        $rows = [
            ['سطسط سيس سييس سسطشش', 'gate_in', '07:46:11'],
            ['باسل سالم محمد عوبل', 'gate_in', '07:18:05'],
            ['احمد سالم محمد عوبل', 'gate_in', '07:12:40'],
        ];
        $out = [];
        foreach ($rows as $i => [$name, $cp, $t]) {
            $out[] = (object) ['id' => 90 - $i, 'student_id' => $i + 1, 'full_name' => $name,
                               'checkpoint' => $cp, 'to_state' => 'at_school',
                               'occurred_at' => date('Y-m-d') . ' ' . $t];
        }
        return array_slice($out, 0, $limit);
    }

    public static function delegations(int $sid, int $limit = 20): array
    {
        if (sch_empty_mode()) { return []; }
        return [(object) ['id' => 3, 'person_name' => 'محمد أحمد', 'relation' => 'خاله',
                          'status' => 'active', 'valid_until' => date('Y-m-d H:i:s', time() + 7200)]];
    }

    public static function state_label(?string $s): string
    {
        return ['at_school' => 'في المدرسة', 'on_bus' => 'في الباص', 'home' => 'في المنزل'][$s] ?? 'غير معروف';
    }
}

final class SCH_Classes
{
    public const STAGES = ['kg' => 'روضة', 'primary' => 'ابتدائي', 'middle' => 'متوسط', 'high' => 'ثانوي'];

    public static function list(): array
    {
        return [
            (object) ['id' => 1, 'grade_level' => 'الرابع', 'section' => 'أ', 'stage' => 'ابتدائي', 'capacity' => 15, 'enrolled' => 9],
            (object) ['id' => 2, 'grade_level' => 'الرابع', 'section' => 'ب', 'stage' => 'ابتدائي', 'capacity' => 15, 'enrolled' => 14],
            (object) ['id' => 3, 'grade_level' => 'الخامس', 'section' => 'أ', 'stage' => 'ابتدائي', 'capacity' => 15, 'enrolled' => 11],
        ];
    }
    public static function get(int $id): ?object { return self::list()[0] ?? null; }
    public static function label($c): string
    {
        return trim(($c->grade_level ?? '') . ($c->section ? ' / ' . $c->section : ''));
    }
}

final class SCH_Students
{
    /** قائمة الأسماء — الطلاب الخمسة أنفسهم بأعمدة الضمّ كما في الإنتاج. */
    public static function list(array $args = []): array
    {
        $g = ['أحمد سالم عوبل', null, 'منى القحطاني', null, 'خالد المطيري'];
        $p = ['0551234567', null, '0509876543', null, '0533221100'];
        $r = ['حي النرجس', null, 'حي النرجس', null, null];
        $items = [];
        foreach (array_values(SCH_ROSTER) as $i => $row) {
            $items[] = (object) ($row + [
                'stage' => 'ابتدائي', 'grade_level' => 'الأول', 'section' => 'أ',
                'status' => 'active', 'class_id' => 7,
                'cls_grade' => 'الأول', 'cls_section' => 'أ', 'cls_stage' => 'ابتدائي', 'cls_id' => 7,
                'guardian_name' => $g[$i] ?? null, 'guardian_phone' => $p[$i] ?? null,
                'route_name' => $r[$i] ?? null,
                'custody_state' => [0 => 'at_school', 1 => 'home', 2 => 'at_school', 3 => 'at_school', 4 => 'home'][$i] ?? 'home',
                'att_status' => [0 => 'present', 1 => null, 2 => 'late', 3 => 'present', 4 => 'absent'][$i] ?? null,
                'student_no' => 'S' . $row['academic_no'], 'national_id' => '215846649' . $i,
                'nationality' => 'اليمن', 'birth_date' => '2016-04-0' . ($i + 1),
            ]);
        }
        return ['items' => $items, 'total' => count($items)];
    }

    public static function today_counts(): array
    {
        return ['total' => 5, 'in' => 3, 'late' => 1, 'absent' => 1, 'nog' => 2];
    }

    public static function now_state(object $r): array
    {
        $att = (string) ($r->att_status ?? '');
        if ($att === 'absent') { return ['key' => 'absent', 'label' => 'غائب']; }
        if ($att === 'late')   { return ['key' => 'late',   'label' => 'متأخر']; }
        if ((string) ($r->custody_state ?? '') === 'at_school') { return ['key' => 'in', 'label' => 'داخل المدرسة']; }
        return ['key' => 'out', 'label' => 'خارج المدرسة'];
    }

    public static function current_class(int $id): ?object
    {
        return sch_empty_mode() ? null : (SCH_Classes::list()[0] ?? null);
    }

    public static function get(int $id): ?object
    {
        foreach (SCH_Attendance::day_sheet(date('Y-m-d')) as $s) {
            if ((int) $s->id === $id) { break; }
            $s = null;
        }

        $name = $s->full_name ?? 'احمد سالم محمد عوبل';

        return (object) [
            'id' => $id, 'full_name' => $name, 'name_en' => 'AHMED SALEM MOHAMMED AWBAL',
            'academic_no' => '1448000' . $id, 'student_no' => 'S1448000' . $id,
            'national_id' => '215846649' . $id, 'nationality' => 'اليمن',
            'birth_date' => '2016-04-09', 'gender' => $id % 2 ? 'male' : 'female',
            'stage' => 'primary', 'grade_level' => 'الرابع', 'section' => 'أ',
            'enrolled_at' => '2026-08-09', 'status' => 'active',
            'qr_token' => 'demo-token', 'user_id' => sch_empty_mode() ? 0 : ($id % 3 ? 41 : 0),
            'photo_file' => null, 'custody_state' => 'at_school',
        ];
    }

    public static function state_now(object $student): array
    {
        $student->att_status = 'late';
        return self::now_state($student);
    }
}

/**
 * موظفو العرض — من بذرة التصميم نفسها، فتُقارَن الشاشة بإطارها صفًّا بصف.
 *
 * الأسماء الستة الأولى ثابتة كما في التصميم (إدارةٌ حاضرة، ومعلمٌ غائب،
 * ومعلمةٌ في إجازة)، ثم يُولَّد الباقي بالقاعدة نفسها: واحدٌ غائب كل ١٩،
 * وواحدٌ في إجازة كل ٢٣.
 */
final class SCH_Deputy
{
    public const STAFF_STATUSES = [
        'present' => 'حاضر', 'late' => 'متأخر', 'absent' => 'غائب', 'leave' => 'في إجازة',
    ];

    public static function staff_day(string $date): array
    {
        $male   = ['محمد', 'أحمد', 'خالد', 'فهد', 'سعود', 'عبدالله', 'ناصر', 'ماجد', 'سلطان', 'بدر', 'طلال', 'يوسف', 'عمر', 'راشد', 'زياد', 'صالح'];
        $female = ['نورة', 'سارة', 'هند', 'أمل', 'ريم', 'منى', 'لطيفة', 'عبير', 'دلال', 'مها', 'وفاء', 'أروى', 'بشرى', 'شذى'];
        $last   = ['عوبل', 'الشامي', 'القحطاني', 'الحربي', 'العتيبي', 'الزهراني', 'الدوسري', 'الغامدي', 'السبيعي', 'المطيري', 'الشهري', 'البقمي'];
        $subj   = ['رياضيات', 'لغة عربية', 'علوم', 'لغة إنجليزية', 'دراسات إسلامية', 'اجتماعيات', 'حاسب آلي', 'تربية فنية', 'تربية بدنية', 'مهارات رقمية'];

        $fixed = [
            ['Fahd Al-Qahtani', 'مدير المدرسة', 'إدارة', 398, 'in'],
            ['اميره الشامي', 'وكيلة الشؤون التعليمية', 'إدارة', 407, 'in'],
            ['جميله احمد', 'وكيلة شؤون الطالبات', 'إدارة', 415, 'in'],
            ['دواد احمد عوبل', 'وكيل شؤون الطلاب', 'إدارة', 426, 'in'],
            ['محمد عوبل', 'معلم رياضيات', 'رياضيات', 0, 'absent'],
            ['وداد عوبل', 'معلمة لغة عربية', 'لغة عربية', 0, 'leave'],
        ];

        $mode = getenv('DEMO') ?: 'fresh';
        $rows = [];

        foreach ($fixed as $i => $f) {
            $rows[] = self::row($i + 1, $f[0], $f[1], $f[2], $f[3], $f[4], $date, $mode, $i);
        }

        for ($i = 6; $i < 64; $i++) {
            $fem  = $i % 3 === 0;
            $s    = $subj[$i % count($subj)];
            $name = ($fem ? $female[$i % count($female)] : $male[$i % count($male)]) . ' ' . $last[($i * 5) % count($last)];
            $stat = $i % 19 === 7 ? 'absent' : ($i % 23 === 11 ? 'leave' : 'in');

            $rows[] = self::row(
                $i + 1,
                $name,
                ($fem ? 'معلمة ' : 'معلم ') . $s,
                $s,
                390 + (($i * 7) % 46),
                $stat,
                $date,
                $mode,
                $i
            );
        }

        usort($rows, static fn ($a, $b) => strcmp($a->display_name, $b->display_name));

        return $rows;
    }

    private static function row(int $id, string $name, string $job, string $dept, int $min, string $kind, string $date, string $mode, int $i): object
    {
        // «fresh» أول الصباح: لم يُمسح أحد بعد. و«mid» منتصف الطابور.
        $scanned = $kind === 'in' && $mode !== 'fresh';
        $state   = $kind === 'in' ? ($scanned ? ($min > 430 ? 'late' : 'present') : 'none') : $kind;
        $lessons = $dept === 'إدارة' ? 0 : 2 + ($i % 3);
        $done    = $state === 'absent' && $i % 2 === 0 ? 1 : 0;

        return (object) [
            'user_id'      => $id,
            'display_name' => $name,
            'job_title'    => $job,
            'department'   => $dept,
            'employee_no'  => '84201' . str_pad((string) $id, 2, '0', STR_PAD_LEFT),
            'status'       => $state === 'none' ? null : $state,
            'method'       => $state === 'none' ? null : ($kind === 'in' ? 'qr' : 'manual'),
            'checked_at'   => $scanned ? $date . ' ' . sprintf('%02d:%02d:00', intdiv($min, 60), $min % 60) : null,
            'checkout_at'  => null,
            'minutes_late' => $state === 'late' ? $min - 430 : 0,
            'note'         => null,
            'on_leave'     => $kind === 'leave' ? 1 : 0,
            'state'        => $state,
            'lessons'      => $lessons,
            'cover_done'   => $done,
            'cover_by'     => $done > 0 ? 'سارة القحطاني' : null,
            'needs'        => in_array($state, ['absent', 'leave'], true) && $lessons > 0,
            'cover_open'   => in_array($state, ['absent', 'leave'], true) ? max(0, $lessons - $done) : 0,
            'manual'       => $state !== 'none' && $kind !== 'in',
        ];
    }

    public static function open_cover_count(array $sheet): int
    {
        $n = 0;
        foreach ($sheet as $r) { $n += (int) $r->cover_open; }
        return $n;
    }

    public static function coverage(int $teacher_id, string $date): array
    {
        $teacher = null;
        foreach (self::staff_day($date) as $r) {
            if ((int) $r->user_id === $teacher_id) { $teacher = $r; break; }
        }
        if (!$teacher) { return ['teacher' => null, 'rows' => [], 'open' => 0, 'total' => 0]; }

        $grades = ['الأول/أ', 'الثاني/ب', 'الثالث/أ', 'الرابع/ب'];
        $rows   = [];
        $open   = 0;

        for ($k = 0; $k < (int) $teacher->lessons; $k++) {
            $on = $k < (int) $teacher->cover_done;
            $open += $on ? 0 : 1;

            $rows[] = (object) [
                'class_id'              => 10 + $k,
                'period_no'             => $k + 1,
                'subject_id'            => 3,
                'grade_level'           => explode('/', $grades[$k % 4])[0],
                'section'               => explode('/', $grades[$k % 4])[1],
                'subject_name'          => (string) $teacher->department,
                'sub_id'                => 500 + $k,
                'substitute_teacher_id' => $on ? 9 : null,
                'substitute_name'       => $on ? 'سارة القحطاني' : null,
                'time'                  => SCH_Timetable::period_hhmm($k + 1),
                'assigned'              => $on,
                'candidates'            => $on ? [] : [
                    (object) ['user_id' => 21, 'display_name' => 'نورة الحربي',  'department' => $teacher->department, 'job_title' => 'معلمة', 'same_subject' => 1],
                    (object) ['user_id' => 22, 'display_name' => 'خالد العتيبي', 'department' => 'علوم',              'job_title' => 'معلم',  'same_subject' => 0],
                    (object) ['user_id' => 23, 'display_name' => 'أمل الزهراني', 'department' => 'اجتماعيات',         'job_title' => 'معلمة', 'same_subject' => 0],
                ],
            ];
        }

        return ['teacher' => $teacher, 'rows' => $rows, 'open' => $open, 'total' => count($rows)];
    }
}

/**
 * الشهادات: **الصنف الحقيقي** لا نسخة — القوالب والـSVG هي ما يُصيَّر في
 * الإنتاج بالضبط. ولا يُستبدَل منه إلا ما يقرأ القاعدة.
 */
final class SCH_FakeWpdb
{
    public $users = 'wp_users';
    public $prefix = 'wp_';
    public $last_error = '';
    public $insert_id = 0;
    public function prepare($q, ...$a) { return $q; }
    public function get_results($q, $o = null) { return []; }
    public function get_row($q, $o = null) { return null; }
    public function get_var($q) { return 0; }
    public function get_col($q) { return []; }
    public function query($q) { return 0; }
    public function insert($t, $d, $f = null) { return 1; }
    public function update($t, $d, $w, $df = null, $wf = null) { return 1; }
    public function esc_like($s) { return $s; }
}

$GLOBALS['wpdb'] = new SCH_FakeWpdb();

function get_transient($k) { return false; }
function set_transient($k, $v, $t = 0) { return true; }
function delete_transient($k) { return true; }
function sanitize_textarea_field($t) { return trim(strip_tags((string) $t)); }
function sch_api_error($c, $m, $s = 400) { return null; }
function sch_audit(...$a) {}
function is_wp_error($x) { return false; }

final class SCH_Years { public static function current_id(): int { return 1; } }
final class SCH_Guardians
{
    public const RELATIONS = ['father' => 'الأب', 'mother' => 'الأم', 'brother' => 'أخ',
                              'sister' => 'أخت', 'uncle' => 'عم/خال', 'guardian' => 'وصي'];

    /** أوّل طالب له وليّ أمر، والثاني بلا — فتُرى الحالتان في لقطة واحدة. */
    public static function of_student(int $id): array
    {
        if (sch_empty_mode()) { return []; }
        return $id % 2 === 1
            ? [
                (object) ['id' => 4, 'user_id' => 9, 'display_name' => 'احمد سالم محمد عوبل سالم',
                          'phone' => '0556847029', 'relation' => 'father', 'is_primary' => 1, 'can_pickup' => 1],
                (object) ['id' => 5, 'user_id' => 10, 'display_name' => 'نورة عبدالله الحربي',
                          'phone' => '0509876543', 'relation' => 'mother', 'is_primary' => 0, 'can_pickup' => 0],
              ]
            : [];
    }

    public static function list(array $args = []): array
    {
        return ['items' => [
            (object) ['id' => 4, 'display_name' => 'احمد محمد سالم القحطاني', 'phone' => '0551234567'],
            (object) ['id' => 5, 'display_name' => 'نورة عبدالله الحربي', 'phone' => '0509876543'],
        ], 'total' => 2];
    }

    public static function relation_label(?string $r): string { return self::RELATIONS[$r] ?? '—'; }

    public static function get(int $id): ?object
    {
        return (object) ['id' => $id, 'user_id' => 9, 'display_name' => 'احمد سالم محمد عوبل',
                         'phone' => '0556847029', 'email' => '', 'national_id' => '2158466491',
                         'job' => 'موظف', 'workplace' => 'شركة', 'address' => 'الرياض',
                         'status' => 'active', 'created_at' => '2026-08-09 11:35:00'];
    }
}
final class SCH_Comms
{
    public const AUDIENCES = ['all_guardians' => 'كل أولياء الأمور', 'class' => 'أولياء أمور شعبة',
                              'staff' => 'الموظفون', 'user' => 'شخص واحد'];

    public static function notify(...$a) {}

    public static function sent(int $limit = 50): array
    {
        return [
            (object) ['title' => 'اجتماع أولياء الأمور', 'audience' => 'all_guardians',
                      'display_name' => 'اميره الشامي', 'created_at' => '2026-08-18 12:04:00'],
            (object) ['title' => 'تنبيه: تأخّر الحافلة', 'audience' => 'class',
                      'display_name' => 'النظام', 'created_at' => '2026-08-17 07:22:00'],
        ];
    }
}
final class SCH_Enrollment {
    public const ID_TYPES = ['national' => 'هوية وطنية', 'iqama' => 'إقامة', 'passport' => 'جواز'];
    public const RELATIONS = ['father' => 'الأب', 'mother' => 'الأم', 'guardian' => 'وليّ أمر'];
    public const DOC_TYPES = ['id' => 'الهوية أو الإقامة', 'birth' => 'شهادة الميلاد', 'transcript' => 'آخر شهادة دراسية', 'photo' => 'صورة شخصية'];
    public const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    public const GENDERS = ['male' => 'ذكر', 'female' => 'أنثى'];

    public static function timeline(int $id, int $limit = 6): array
    {
        if (sch_empty_mode()) { return []; }
        return array_slice([
            ['title' => 'دخول البوابة', 'when' => '2026-08-20 07:11:04', 'kind' => 'ok', 'tag' => ''],
            ['title' => 'صعد إلى الباص', 'when' => '2026-08-20 06:31:04', 'kind' => 'ok', 'tag' => ''],
            ['title' => 'الحضور: متأخر', 'when' => '2026-08-19 07:52:11', 'kind' => 'warn', 'tag' => 'تأخّر 22 دقيقة'],
            ['title' => 'الحضور: غياب بعذر', 'when' => '2026-08-13 09:44:52', 'kind' => 'info', 'tag' => 'عذر طبي'],
            ['title' => 'سداد دفعة — 8,100.00 ر.س', 'when' => '2026-08-12 08:02:10', 'kind' => 'ok', 'tag' => ''],
            ['title' => 'تسجيل في المدرسة', 'when' => '2026-08-09 10:02:00', 'kind' => 'base', 'tag' => ''],
        ], 0, $limit);
    }

    public static function docs(int $id): array
    {
        if (sch_empty_mode()) { return []; }
        return [
            (object) ['id' => 11, 'doc_type' => 'id', 'file_name' => 'id.jpg', 'file_size' => 1048576, 'created_at' => '2026-08-09 11:35:00'],
            (object) ['id' => 12, 'doc_type' => 'birth', 'file_name' => 'birth.pdf', 'file_size' => 942080, 'created_at' => '2026-08-09 11:36:00'],
            (object) ['id' => 13, 'doc_type' => 'photo', 'file_name' => 'photo.png', 'file_size' => 512000, 'created_at' => '2026-08-09 11:38:00'],
        ];
    }

    public static function health(int $id): ?object
    {
        if (sch_empty_mode()) { return null; }
        return (object) ['blood_type' => 'O+', 'chronic' => '', 'allergies' => 'حساسية من الفول السوداني',
                         'special_needs' => '', 'notes' => ''];
    }

    public static function full_name($o): string
    {
        return (string) ($o->name_snapshot ?? $o->full_name ?? 'محمد عبدالله الأحمد');
    }
}

require SCH_PATH . 'modules/academic/class-certificates.php';

/** نافذة مشتركة — نسخة من `SCH_Modal` بالوسوم نفسها. */
final class SCH_Modal
{
    public static function button(string $id, string $label, string $icon = 'plus'): void
    {
        echo '<button type="button" class="sch-btn sch-add" data-modal-open="' . esc_attr($id) . '">'
           . sch_icon($icon, 16) . esc_html($label) . '</button>';
    }

    public static function head(string $title, string $count = '', string $btn_id = '', string $btn_label = '', string $icon = 'plus', string $tools = ''): void
    {
        echo '<div class="sch-head"><div class="sch-head__t"><h1 class="sch-title">' . esc_html($title) . '</h1>'
           . ($count !== '' ? '<p class="sch-sub">' . esc_html($count) . '</p>' : '') . '</div>';
        if ($tools !== '' || $btn_id !== '') {
            echo '<div class="sch-head__acts">' . $tools;
            if ($btn_id !== '') { self::button($btn_id, $btn_label, $icon); }
            echo '</div>';
        }
        echo '</div>';
    }

    public static function open(string $id, string $title, string $sub = ''): void
    {
        echo '<div class="sch-modal" id="' . esc_attr($id) . '" hidden><div class="sch-modal__veil"></div>'
           . '<div class="sch-modal__box"><div class="sch-modal__head"><div><strong>' . esc_html($title) . '</strong>'
           . ($sub !== '' ? '<span>' . esc_html($sub) . '</span>' : '') . '</div>'
           . '<button type="button" class="sch-modal__x" data-modal-close>✕</button></div><div class="sch-modal__body">';
    }

    public static function close(): void { echo '</div></div></div>'; }
}

/* ── ما تحتاجه شاشة الطلاب من بقيّة الوحدات ── */
final class SCH_Routes
{
    public static function all(): array { return []; }

    public static function subscription_of(int $id): ?object
    {
        if (sch_empty_mode()) { return null; }
        return (object) ['route_name' => 'حي النرجس', 'stop_name' => null, 'fee_amount' => 7000.0];
    }
}
final class SCH_Views {
    public static function menu(string $screen, string $view = ''): string { return ''; }
}
final class SCH_Table {
    public static function page(): int { return 1; }
    public static function order_args(): array { return []; }
    public static function th(string $label, string $key): string
    {
        return '<th><button type="button" class="sch-th__sort">' . esc_html($label) . '</button></th>';
    }
    public static function pager(int $total, int $per): string
    {
        return '<div class="sch-pager"><span class="sch-sub">عرض ١–' . number_format_i18n(min($total, $per))
             . ' من ' . number_format_i18n($total) . ' طالبًا</span></div>';
    }
}
final class SCH_Bulk {
    public static function pick_all(): void { echo '<input type="checkbox" data-pick-all aria-label="تحديد الكل">'; }
    public static function pick(int $id): void
    {
        echo '<input type="checkbox" data-pick value="' . esc_attr((string) $id) . '" aria-label="تحديد">';
    }
    public static function bar(string $screen): void {}
}
final class SCH_Finance
{
    public static function of_student(int $id): array { return [(object) ['balance' => 28800.0]]; }

    public static function invoices_of_student(int $id): array
    {
        if (sch_empty_mode()) { return []; }
        return [
            (object) ['id' => 21, 'plan_name' => 'الرسوم الدراسية', 'total' => 38000.0, 'paid' => 16200.0, 'due_date' => '2026-09-01'],
        ];
    }

    public static function student_summary(int $id): array
    {
        if (sch_empty_mode()) {
            return ['total' => 0.0, 'paid' => 0.0, 'remaining' => 0.0, 'pct' => 0, 'payments' => 0, 'next_due' => null];
        }
        return ['total' => 45000.0, 'paid' => 16200.0, 'remaining' => 28800.0,
                'pct' => 36, 'payments' => 2, 'next_due' => '2026-09-01'];
    }
}

final class SCH_Flow
{
    public static function progress(string $flow, int $id = 0): array
    {
        $defs = [
            ['reg', 'تسجيل الطالب', 1], ['class', 'تحديد الصف والشعبة', 1],
            ['guardian', 'ربط ولي الأمر', 1], ['fees', 'الرسوم الدراسية', 0],
            ['bus', 'النقل المدرسي', 1], ['app', 'تفعيل التطبيق', 1],
            ['card', 'طباعة البطاقة', 1],
        ];
        $steps = []; $done = 0; $next = null;
        foreach ($defs as [$k, $label, $ok]) {
            $state = $ok ? 'done' : ($next === null ? 'current' : 'todo');
            if ($ok) { $done++; } elseif ($next === null) { $next = $k; }
            $steps[$k] = ['label' => $label, 'icon' => 'user', 'why' => 'تُولَّد الفاتورة وأقساطها',
                          'required' => true, 'state' => $state, 'manual' => false,
                          'url' => SCH_Dashboard::url('finance')];
        }
        return ['steps' => $steps, 'done' => $done, 'total' => count($steps), 'next' => $next];
    }
}

final class SCH_Audit
{
    public static function query(array $args = []): array
    {
        if (sch_empty_mode()) { return ['items' => [], 'total' => 0]; }
        return ['items' => [
            (object) ['action' => 'doc.uploaded', 'display_name' => 'اميره الشامي', 'created_at' => '2026-08-20 07:11:00', 'object_type' => 'student', 'object_id' => 1, 'ip' => '10.0.0.4'],
            (object) ['action' => 'guardian.linked', 'display_name' => 'دواد احمد عوبل', 'created_at' => '2026-08-12 08:02:00', 'object_type' => 'student', 'object_id' => 1, 'ip' => '10.0.0.4'],
            (object) ['action' => 'pickup.right_changed', 'display_name' => 'اميره الشامي', 'created_at' => '2026-08-11 09:20:00', 'object_type' => 'student', 'object_id' => 1, 'ip' => '10.0.0.4'],
            (object) ['action' => 'student.registered', 'display_name' => 'اميره الشامي', 'created_at' => '2026-08-09 11:35:00', 'object_type' => 'student', 'object_id' => 1, 'ip' => '10.0.0.4'],
        ], 'total' => 4];
    }

    public static function object_types(): array { return ['student', 'invoice', 'message']; }

    public static function label(string $a): string
    {
        return ['doc.uploaded' => 'رفع مستند', 'guardian.linked' => 'ربط وليّ أمر',
                'pickup.right_changed' => 'تغيير حقّ الاستلام',
                'student.registered' => 'إنشاء ملف الطالب'][$a] ?? $a;
    }
}

final class SCH_Nerve
{
    public static function summary(int $id): ?object
    {
        if (sch_empty_mode()) { return null; }
        return (object) ['works' => 'يستجيب للمدح الفردي أمام مجموعة صغيرة', 'avoid' => '', 'notes' => ''];
    }

    public static function previous_summary(int $id): ?object
    {
        if (sch_empty_mode()) { return null; }
        return (object) ['year_name' => '1447', 'works' => 'الجلوس في الصف الأول', 'avoid' => '', 'notes' => ''];
    }
}

final class SCH_Assessment
{
    public static function report_card(int $id): array
    {
        if (sch_empty_mode()) { return []; }
        return ['الرياضيات' => ['percent' => 88.0], 'العلوم' => ['percent' => 76.5], 'اللغة العربية' => ['percent' => 91.0]];
    }
}

final class SCH_QR
{
    /** رمز تجريبي بالشكل نفسه — المولّد الحقيقي يحتاج قاعدة. */
    public static function svg(string $data, int $scale = 4, int $quiet = 2): string
    {
        $cells = '';
        for ($y = 0; $y < 21; $y++) {
            for ($x = 0; $x < 21; $x++) {
                if ((($x * 7 + $y * 13 + strlen($data)) % 3) === 0) {
                    $cells .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1"/>';
                }
            }
        }
        return '<svg viewBox="0 0 21 21" width="100%" height="100%" shape-rendering="crispEdges"><g fill="#0E1420">' . $cells . '</g></svg>';
    }
}

function size_format($bytes, $decimals = 0)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $bytes = (float) $bytes;
    while ($bytes >= 1024 && $i < 3) { $bytes /= 1024; $i++; }
    return number_format_i18n($bytes, $decimals) . ' ' . $units[$i];
}
function sch_money($n) { return number_format_i18n((float) $n, 2) . ' ر.س'; }
function current_user_can($c) { return true; }
function remove_query_arg($k, $u = null) { return '/dashboard/students/'; }
function esc_js($t) { return esc_attr($t); }
