<?php
/**
 * بيانات العرض — مطابقة لبيانات التصميم عمدًا.
 *
 * أحمد وأبناؤه الثلاثة، ومسار «الياسمين»، ونفس المبالغ والتواريخ —
 * فتصير المقارنة بين ما بنيتُ وما صُمِّم صورةً بصورة، لا تقريبًا.
 */

declare(strict_types=1);

// ثلاثة أبناء بثلاث حالات مختلفة: فتُرى بطاقة البطل بنغماتها الثلاث
const KIDS = [
    1 => ['first_name' => 'سامي', 'full_name' => 'سامي أحمد العتيبي', 'name_en' => 'Sami Ahmed Alotaibi',
          'grade_level' => 'الرابع', 'section' => 'أ', 'stage' => 'ابتدائي', 'custody_state' => 'at_school',
          'academic_no' => '٤٠٢٧١٥', 'national_id' => '١٠٩٨٧٦٥٤٣٢', 'birth_date' => '2015-04-12'],
    2 => ['first_name' => 'لمى', 'full_name' => 'لمى أحمد العتيبي', 'name_en' => 'Lama Ahmed Alotaibi',
          'grade_level' => 'الثاني', 'section' => 'ب', 'stage' => 'ابتدائي', 'custody_state' => 'on_bus',
          'academic_no' => '٤٠٢٩٣٣', 'national_id' => '١٠٩٨٧٦٥٥١٢', 'birth_date' => '2017-09-03'],
    3 => ['first_name' => 'جود', 'full_name' => 'جود أحمد العتيبي', 'name_en' => 'Jood Ahmed Alotaibi',
          'grade_level' => 'روضة ٢', 'section' => '', 'stage' => 'رياض أطفال', 'custody_state' => 'home',
          'academic_no' => '٤٠٣١٠٨', 'national_id' => '١٠٩٨٧٦٥٩٩٠', 'birth_date' => '2020-01-22'],
];

function demo_student(int $id): ?object {
    if (!isset(KIDS[$id])) { return null; }
    return (object) (KIDS[$id] + [
        'id' => $id, 'photo_file' => '', 'qr_token' => str_repeat((string) $id, 32),
        'badge_token' => str_repeat((string) $id, 32), 'status' => 'active',
        'custody_at' => date('Y-m-d H:i:s'), 'user_id' => 100 + $id, 'notes' => '',
        'father_name' => 'أحمد', 'family_name' => 'العتيبي', 'grand_name' => 'سعد',
        'id_type' => 'national', 'nationality' => 'سعودي', 'gender' => $id === 1 ? 'male' : 'female',
        'enrolled_at' => '2023-08-20', 'student_no' => (string) (1024 + $id),
        'created_at' => '2023-08-20 08:00:00', 'updated_at' => date('Y-m-d H:i:s'),
    ]);
}

final class SCH_Students {
    public static function get(int $id): ?object { return demo_student($id); }
    public static function children_of(int $uid): array {
        return array_values(array_filter(array_map('demo_student', array_keys(KIDS))));
    }
    public static function current_class(int $id): ?object {
        $k = KIDS[$id] ?? null;
        return $k ? (object) ['id' => $id, 'grade_level' => $k['grade_level'], 'section' => $k['section'], 'stage' => $k['stage']] : null;
    }
}

final class SCH_Classes {
    public static function label($c): string {
        return trim(($c->grade_level ?? '') . ($c->section ? ' / ' . $c->section : ''));
    }
}

final class SCH_Enrollment {
    public static function full_name($s): string { return (string) ($s->full_name ?? ''); }
    public static function health(int $id): ?object {
        return $id === 1 ? (object) ['blood_type' => 'O+', 'allergies' => 'الفول السوداني', 'chronic' => '', 'special_needs' => ''] : null;
    }
}

final class SCH_Custody {
    public static function state_label(?string $s): string {
        return ['at_school' => 'في المدرسة', 'on_bus' => 'في الباص', 'home' => 'في المنزل', 'left_early' => 'خرج مبكرًا'][$s] ?? 'غير معروف';
    }

    /* أحمد وأمّ الأبناء دائمان، وخالتهم مفوَّضة اليوم وحده — وجود لا مفوَّض له،
       فتُرى الحالتان: قائمة فيها تفويض وقائمة بلا تفويض. */
    public static function pickers(int $id): array {
        $out = [
            ['key' => 'g:7', 'name' => 'أحمد سعد العتيبي', 'relation' => 'الأب', 'source' => 'guardian', 'until' => null],
            ['key' => 'g:2', 'name' => 'منى عبدالله القحطاني', 'relation' => 'الأم', 'source' => 'guardian', 'until' => null],
        ];
        if ($id === 1) {
            $out[] = ['key' => 'd:7', 'name' => 'نورة سعد المطيري', 'relation' => 'الخالة',
                      'source' => 'delegate', 'until' => date('Y-m-d H:i:s', strtotime('+6 hours'))];
        }
        return $out;
    }

    public static function may_pick_up(int $id, string $key): bool { return $key === 'g:7'; }
}

final class SCH_Attendance {
    const STATUSES = ['present' => 'حاضر', 'late' => 'متأخر', 'absent' => 'غائب', 'excused' => 'إجازة معتمدة'];
    public static function rate(int $id): float { return 96.0; }
    public static function summary(int $id): array { return ['present' => 23, 'late' => 2, 'absent' => 3, 'excused' => 1, 'days' => 24, 'rate' => 96]; }
    public static function history(int $id, int $days = 30): array {
        $out = []; $pat = ['present','present','late','present','absent','present','present','excused','present','present','present','late','present','absent','present','present','present','present','present','present','present','present','present','absent'];
        foreach ($pat as $i => $st) { $out[] = (object) ['att_date' => date('Y-m-d', strtotime("-" . (count($pat) - $i) . " days")), 'status' => $st, 'minutes_late' => $st === 'late' ? 12 : 0]; }
        return $out;
    }
}

final class SCH_Assessment {
    public static function report_card(int $id): array {
        return ['subjects' => [
            (object) ['subject_name' => 'رياضيات', 'score' => 96, 'max_score' => 100, 'percent' => 96],
            (object) ['subject_name' => 'علوم', 'score' => 92, 'max_score' => 100, 'percent' => 92],
            (object) ['subject_name' => 'لغة عربية', 'score' => 88, 'max_score' => 100, 'percent' => 88],
            (object) ['subject_name' => 'لغة إنجليزية', 'score' => 84, 'max_score' => 100, 'percent' => 84],
            (object) ['subject_name' => 'دراسات إسلامية', 'score' => 94, 'max_score' => 100, 'percent' => 94],
        ], 'average' => 90.8];
    }
    public static function upcoming_exams(int $cid, int $n = 10): array {
        return [(object) ['title' => 'اختبار الوحدة الثالثة', 'subject_name' => 'رياضيات', 'exam_date' => date('Y-m-d', strtotime('+3 days'))]];
    }
    public static function grade_label(float $p): string { return $p >= 90 ? 'ممتاز' : ($p >= 80 ? 'جيد جدًا' : ($p >= 70 ? 'جيد' : 'مقبول')); }
}

final class SCH_Finance {
    public static function invoices_of_student(int $id): array {
        return [
            (object) ['id' => 3, 'title' => 'القسط الثالث', 'gross' => 1600, 'discount' => 0, 'discount_pct' => 0, 'total' => 1600, 'paid' => 0, 'status' => 'overdue', 'due_date' => date('Y-m-d', strtotime('-14 days')), 'created_at' => date('Y-m-d H:i:s', strtotime('-14 days'))],
            (object) ['id' => 2, 'title' => 'رسوم النقل — الفصل الأول', 'gross' => 900, 'discount' => 0, 'discount_pct' => 0, 'total' => 900, 'paid' => 900, 'status' => 'paid', 'due_date' => '2024-11-03', 'created_at' => '2024-11-03 08:00:00'],
            (object) ['id' => 1, 'title' => 'الزيّ المدرسي', 'gross' => 500, 'discount' => 80, 'discount_pct' => 16, 'total' => 420, 'paid' => 420, 'status' => 'paid', 'due_date' => '2024-09-12', 'created_at' => '2024-09-12 08:00:00'],
        ];
    }
    public static function installments(int $id): array {
        return [
            (object) ['id' => 1, 'seq' => 1, 'amount' => 3400, 'paid' => 3400, 'due_date' => '2024-09-01'],
            (object) ['id' => 2, 'seq' => 2, 'amount' => 3500, 'paid' => 3500, 'due_date' => '2024-11-01'],
            (object) ['id' => 3, 'seq' => 3, 'amount' => 1600, 'paid' => 0,    'due_date' => date('Y-m-d', strtotime('-14 days'))],
            (object) ['id' => 4, 'seq' => 4, 'amount' => 1500, 'paid' => 0,    'due_date' => date('Y-m-d', strtotime('+40 days'))],
        ];
    }
}

final class SCH_Routes {
    public static function subscription_of(int $id): ?object {
        return $id === 3 ? null : (object) ['route_id' => 1, 'route_name' => 'الياسمين', 'stop_name' => 'حيّ الياسمين — أمام مسجد الفرقان', 'pickup_time' => '06:50', 'dropoff_time' => '13:00'];
    }
}

final class SCH_Timetable {
    const DAYS = [1 => 'الأحد', 2 => 'الاثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء', 5 => 'الخميس'];
    const PERIODS = 7;
    public static function grid(int $cid): array {
        $subs = ['رياضيات' => 'أ. خالد المطيري', 'علوم' => 'أ. هند الزهراني', 'لغة عربية' => 'أ. منى القحطاني', 'إنجليزي' => 'أ. هند الزهراني', 'إسلامية' => 'أ. خالد المطيري', 'اجتماعيات' => 'أ. منى القحطاني', 'رياضة' => 'أ. خالد المطيري'];
        $names = array_keys($subs); $out = [];
        foreach (self::DAYS as $d => $_) {
            for ($p = 1; $p <= self::PERIODS; $p++) {
                $n = $names[($d * 3 + $p) % count($names)];
                $out[$d][$p] = (object) ['subject_name' => $n, 'teacher_name' => $subs[$n]];
            }
        }
        return $out;
    }
}

final class SCH_Clinic {
    public static function of_student(int $id): array {
        return [
            (object) ['visit_at' => date('Y-m-d H:i:s', strtotime('today 12:05')), 'created_at' => date('Y-m-d H:i:s', strtotime('today 12:05')), 'complaint' => 'صداع خفيف', 'action_note' => 'راحة ٢٠ دقيقة وشرب ماء', 'sent_home' => 0],
            (object) ['visit_at' => date('Y-m-d H:i:s', strtotime('-9 days')), 'created_at' => date('Y-m-d H:i:s', strtotime('-9 days')), 'complaint' => 'كدمة في الركبة', 'action_note' => 'تعقيم وضمادة بعد حصة الرياضة', 'sent_home' => 0],
            (object) ['visit_at' => date('Y-m-d H:i:s', strtotime('-22 days')), 'created_at' => date('Y-m-d H:i:s', strtotime('-22 days')), 'complaint' => 'حرارة ٣٨٫٢', 'action_note' => 'اتُّصل بوليّ الأمر فورًا', 'sent_home' => 1],
        ];
    }
}

final class SCH_Medication {
    const STATUSES = ['active' => 'ساري', 'expired' => 'منتهٍ', 'stopped' => 'موقوف'];
    public static function of_student(int $id): array { return []; }
}

final class SCH_StudentLeave {
    const REASONS  = ['sick' => 'مرضي', 'travel' => 'سفر', 'family' => 'ظرف عائلي', 'other' => 'أخرى'];
    const STATUSES = ['pending' => 'بانتظار الردّ', 'approved' => 'معتمَدة', 'rejected' => 'مرفوضة'];
    public static function on_leave_today(int $id) { return null; }
    public static function of_student(int $id): array { return []; }
}

final class SCH_Certificates {
    const TYPES = ['excellence' => 'تفوّق', 'reading' => 'قراءة', 'attendance' => 'حضور', 'thanks' => 'شكر'];
    public static function of_student(int $id): array {
        return [
            (object) ['id' => 1, 'type' => 'reading', 'template' => 'royal', 'title' => 'المركز الأول في مسابقة القراءة', 'issued_at' => date('Y-m-d', strtotime('-1 day')), 'serial' => 'C-1041', 'status' => 'valid'],
            (object) ['id' => 2, 'type' => 'excellence', 'template' => 'classic', 'title' => 'شهادة تفوّق — الفصل الأول', 'issued_at' => date('Y-m-d', strtotime('-40 days')), 'serial' => 'C-0987', 'status' => 'valid'],
        ];
    }
    public static function get(int $id): ?object { $all = self::of_student(1); foreach ($all as $c) { if ((int) $c->id === $id) { return $c; } } return null; }
    public static function svg($c, $w = 600): string {
        return '<svg viewBox="0 0 600 420" width="100%" role="img" aria-label="' . esc_attr($c->title ?? '') . '">'
            . '<rect width="600" height="420" rx="18" fill="#f7edd3"/><circle cx="300" cy="150" r="46" fill="none" stroke="#7e5a0c" stroke-width="3"/>'
            . '<text x="300" y="250" text-anchor="middle" font-family="Cairo" font-size="27" font-weight="700" fill="#191b2e">' . esc_html($c->title ?? '') . '</text>'
            . '<text x="300" y="292" text-anchor="middle" font-family="Cairo" font-size="18" fill="#7e5a0c">سامي أحمد العتيبي</text></svg>';
    }
}

final class SCH_KG {
    const MOODS = ['happy' => 'سعيد', 'calm' => 'هادئ', 'tired' => 'متعب', 'upset' => 'منزعج'];
    const MEALS = ['all' => 'أكل كل الوجبة', 'most' => 'أكل معظمها', 'some' => 'أكل قليلًا', 'none' => 'لم يأكل'];
    public static function history(int $id, int $n = 14): array {
        return [(object) ['log_date' => date('Y-m-d'), 'mood' => 'happy', 'meal' => 'most', 'nap_minutes' => 45,
                'activities' => 'رسمت شجرة بألوان الخريف وشاركت في ركن الموسيقى مع المجموعة.', 'note' => 'انتهت الحفّاضات — يُرجى إرسال عبوة']];
    }
}

final class SCH_Library {
    public static function loans_of_student(int $id): array { return []; }
}

final class SCH_Guardians {
    public static function by_user(int $uid): ?object {
        return (object) ['id' => 1, 'user_id' => $uid, 'photo_file' => '', 'first_name' => 'أحمد', 'father_name' => 'سعد',
            'family_name' => 'العتيبي', 'phone' => '٠٥٥١٢٣٤٥٦٧', 'national_id' => '١٠٢٣٤٥٦٧٨٩', 'parent_no' => 'P-1041',
            'city' => 'الرياض', 'workplace' => '', 'address' => '', 'alt_phone' => '', 'status' => 'active'];
    }
}

final class SCH_Pickup {
    const STATUSES = ['open' => 'بانتظار المشرفة', 'onway' => 'المشرفة في الطريق', 'done' => 'سُلّم', 'cancelled' => 'أُلغي'];
    public static function open_for(int $id): ?object {
        return getenv('DEMO_CALL') ? (object) ['id' => 1, 'student_id' => $id, 'status' => getenv('DEMO_CALL'),
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes')), 'caller_name' => 'أحمد سعد العتيبي'] : null;
    }
}

final class SCH_Comms {
    public static function unread_count(int $uid): int { return 3; }
    public static function inbox(int $uid, int $n = 40): array {
        $m = [
            ['custody', 'دخل بوابة المدرسة', 'تمّ تسجيل دخول العهدة عند البوابة الرئيسية', '-2 hours', 1, 'سامي'],
            ['clinic', 'زيارة العيادة', 'صداع خفيف — أُعطي ماءً وراحة، ولم يُرسل للمنزل', '-4 hours', 1, 'سامي'],
            ['transport', 'صعد باص العودة', 'مسار «الياسمين» — الوصول المتوقّع ١٣:٠٠', '-5 hours', 0, 'لمى'],
            ['certificate', 'شهادة جديدة', 'المركز الأول في مسابقة القراءة', '-1 day', 0, 'سامي'],
            ['attendance', 'رُصدت درجة رياضيات', '٩٦ من ١٠٠ — أ. خالد المطيري', '-1 day', 0, 'سامي'],
            ['invoice', 'تذكير بالقسط الثالث', '١٦٠٠ ر.س — تجاوز موعد الاستحقاق', '-2 days', 0, 'سامي'],
            ['message', 'تعميم إداري', 'انصراف مبكر يوم الخميس الساعة ١١:٠٠', '-3 days', 0, ''],
        ];
        $out = [];
        foreach ($m as $i => [$t, $ti, $b, $ago, $unread, $kid]) {
            $out[] = (object) ['id' => $i + 1, 'title' => $ti, 'body' => $b, 'ref_type' => $t, 'ref_id' => 1,
                'read_at' => $unread ? null : date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s', strtotime($ago)), 'kid' => $kid];
        }
        return $out;
    }
}

final class SCH_QR {
    public static function svg(string $token, int $scale = 4, int $margin = 1): string {
        // شبكة زائفة بحجم رمز حقيقي — الغرض قياس المساحة والحوافّ لا المسح
        $n = 25; $px = $scale; $sz = ($n + $margin * 2) * $px;
        $s = '<svg width="' . $sz . '" height="' . $sz . '" viewBox="0 0 ' . $sz . ' ' . $sz . '" role="img" aria-label="رمز"><rect width="' . $sz . '" height="' . $sz . '" fill="#fff"/>';
        $seed = crc32($token);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $corner = ($x < 7 && $y < 7) || ($x >= $n - 7 && $y < 7) || ($x < 7 && $y >= $n - 7);
                $on = $corner ? (($x % 6 === 0 || $y % 6 === 0) || ($x > 1 && $x < 5 && $y > 1 && $y < 5)) : ((($seed >> (($x * 7 + $y * 3) % 24)) & 1) === 1);
                if ($on) { $s .= '<rect x="' . (($x + $margin) * $px) . '" y="' . (($y + $margin) * $px) . '" width="' . $px . '" height="' . $px . '" fill="#0b0c14"/>'; }
            }
        }
        return $s . '</svg>';
    }
}

final class SCH_Brand {
    public static function favicon(): string { return ''; }
}

final class SCH_Push {
    public static function ready(): bool { return true; }
    public static function boot(string $sw = ''): string { return ''; }
    public static function public_key(): string { return 'demo'; }
}

final class SCH_App {
    public static function url(string $slug = '', int $id = 0): string {
        return '/dashboard/app/' . $slug . ($id ? '/' . $id : '');
    }
    public static function avatar_url(): string { return ''; }
    public static function photo_url(int $id): string { return ''; }

    public static function today(int $id): array {
        $st = KIDS[$id]['custody_state'] ?? 'home';
        $trip = $st === 'on_bus'
            ? ['direction' => 'afternoon', 'state' => 'onboard', 'route_name' => 'الياسمين', 'eta' => 6, 'stops_away' => 2]
            : null;
        return ['attendance' => (object) ['status' => 'present', 'method' => 'gate'],
                'trip' => $trip, 'balance' => 3100.0];
    }

    public static function day_thread(int $id): array {
        $st = KIDS[$id]['custody_state'] ?? 'home';
        if ($st === 'home') {
            return [['time' => '', 'kind' => 'check', 'title' => 'لم يبدأ يومه بعد',
                     'detail' => 'تظهر أول محطّة فور صعوده الباص أو دخوله البوابة', 'state' => 'now']];
        }
        $t = [
            ['time' => '٦:٤٠',  'kind' => 'home',  'title' => 'خرج من البيت',   'detail' => '', 'state' => 'done'],
            ['time' => '٦:٥٢',  'kind' => 'bus',   'title' => 'صعد الباص — مسار «الياسمين»', 'detail' => '', 'state' => 'done'],
            ['time' => '٧:١٤',  'kind' => 'route', 'title' => 'دخل بوابة المدرسة', 'detail' => '', 'state' => 'done'],
            ['time' => '٧:٣٠',  'kind' => 'user-check', 'title' => 'حاضر في الفصل', 'detail' => '', 'state' => 'done'],
        ];
        if ($st === 'at_school') {
            $t[] = ['time' => '١٢:٠٥', 'kind' => 'heart', 'title' => 'زيارة العيادة — صداع خفيف', 'detail' => '', 'state' => 'now'];
            $t[] = ['time' => '١:١٠', 'kind' => 'logout', 'title' => 'الانصراف', 'detail' => 'يخرج من البوابة', 'state' => 'next'];
        } else {
            $t[] = ['time' => '١٢:٢٥', 'kind' => 'bus', 'title' => 'صعد باص العودة', 'detail' => '', 'state' => 'now'];
            $t[] = ['time' => '١٣:٠٠', 'kind' => 'home', 'title' => 'يصل نقطة الالتقاء', 'detail' => 'حيّ الياسمين', 'state' => 'next'];
        }
        return $t;
    }

    public static function day_label(?string $d = null): string {
        $days = ['Sunday' => 'الأحد', 'Monday' => 'الاثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء',
                 'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت'];
        return $days[date('l', $d ? strtotime($d) : time())] ?? '';
    }

    public static function when_label(string $ts): string {
        $diff = time() - strtotime($ts);
        if ($diff < 3600) { return 'قبل ' . max(1, (int) ($diff / 60)) . ' دقيقة'; }
        if ($diff < 86400) { return 'قبل ' . (int) ($diff / 3600) . ' ساعات'; }
        return date('j M', strtotime($ts));
    }

    public static function trip_state_label(string $s): string {
        return ['onboard' => 'في الباص الآن', 'boarded' => 'صعد الباص', 'alighted' => 'نزل من الباص'][$s] ?? '';
    }

    public static function alert_look(string $type): array {
        $icon = ['certificate' => 'award', 'attendance' => 'user-check', 'leave' => 'check', 'med' => 'pill',
                 'clinic' => 'heart', 'custody' => 'route', 'transport' => 'bus', 'invoice' => 'invoice',
                 'finance' => 'wallet', 'payment' => 'wallet', 'timetable' => 'calendar', 'note' => 'pen',
                 'message' => 'mail'][$type] ?? 'bell';
        return [$icon, $type];
    }

    public static function render(string $view, array $data = []): void {}
}
