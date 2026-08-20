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
    public const STATUSES = ['present' => 'حاضر', 'late' => 'متأخر', 'absent' => 'غائب', 'excused' => 'بعذر'];

    public static function expected_in(): string { return '07:30'; }
    public static function grace(): int { return 10; }

    public static function status_for_arrival(?string $occurred): array
    {
        return $occurred === null ? ['absent', 0] : ['present', 0];
    }

    public static function sheet(int $class_id, string $date): array { return self::gate_sheet($date); }

    public static function day_summary(string $date): array
    {
        $out = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
        foreach (self::gate_sheet($date) as $r) {
            if ($r->status !== null && isset($out[$r->status])) { $out[$r->status]++; }
        }
        return $out;
    }

    /** الكشف على مستوى المدرسة — الدالّة التي تحتاجها الشاشة الجديدة. */
    public static function gate_sheet(string $date, ?int $class_id = null): array
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
                'class_label' => 'الرابع / أ',
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

    public static function state_label(?string $s): string
    {
        return ['at_school' => 'في المدرسة', 'on_bus' => 'في الباص', 'home' => 'في المنزل'][$s] ?? 'غير معروف';
    }
}

final class SCH_Classes
{
    public static function list(): array
    {
        return [(object) ['id' => 1, 'grade_level' => 'الرابع', 'section' => 'أ', 'stage' => 'ابتدائي']];
    }
    public static function get(int $id): ?object { return self::list()[0] ?? null; }
    public static function label($c): string
    {
        return trim(($c->grade_level ?? '') . ($c->section ? ' / ' . $c->section : ''));
    }
}

final class SCH_Students
{
    public static function get(int $id): ?object
    {
        foreach (SCH_Attendance::gate_sheet(date('Y-m-d')) as $s) {
            if ((int) $s->id === $id) { return $s; }
        }
        return null;
    }
}
