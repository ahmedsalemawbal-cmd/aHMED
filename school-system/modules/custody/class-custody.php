<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * سلسلة العهدة.
 *
 * المبدأ: الطالب في كل لحظة في عهدة جهة معلومة، وكل مسح نقل مسؤولية لا مجرد سجل.
 * وظيفة هذا الصنف ليست تسجيل ما حدث، بل رفض ما لا يجوز أن يحدث.
 *
 * السجل غير قابل للتعديل ولا الحذف — الخطأ يُصحَّح بحدث مضاد، كالقيود المحاسبية.
 */
final class SCH_Custody
{
    public const STATES = [
        'home'       => 'خارج المدرسة',
        'on_bus'     => 'في الباص',
        'at_school'  => 'في المدرسة',
        'left_early' => 'غادر مبكرًا',
    ];

    public const CHECKPOINTS = [
        'bus_board'  => 'صعود الباص',
        'gate_in'    => 'دخول البوابة',
        'gate_out'   => 'خروج البوابة',
        'early_out'  => 'خروج مبكر',
        'bus_alight' => 'النزول والتسليم',
        'manual'     => 'إدخال إداري',
        'correction' => 'تصحيح',
    ];

    /** الانتقالات المسموحة: من => [إلى...] */
    private const ALLOWED = [
        'home'       => ['on_bus', 'at_school'],
        'on_bus'     => ['at_school', 'home'],
        'at_school'  => ['on_bus', 'home', 'left_early'],
        'left_early' => ['home'],
    ];

    /** نقطة التحقق => الحالة التي تنتج عنها */
    private const RESULT = [
        'bus_board'  => 'on_bus',
        'gate_in'    => 'at_school',
        'gate_out'   => 'home',
        'early_out'  => 'left_early',
        'bus_alight' => 'home',
    ];

    // ---------- القراءة ----------

    public static function state_of(int $student_id): string
    {
        $student = SCH_Students::get($student_id);
        return (string) ($student->custody_state ?? 'home');
    }

    public static function state_label(?string $state): string
    {
        return self::STATES[$state ?? ''] ?? self::STATES['home'];
    }

    /** الطالب من ركّاب الباص أم يوصّله أهله؟ يحدد مساره تلقائيًا. */
    public static function rides_bus(int $student_id): bool
    {
        return SCH_Routes::subscription_of($student_id) !== null;
    }

    /**
     * التعرّف على الطالب من رمز ممسوح.
     * يقبل الاثنين: البطاقة المطبوعة للصغار، والرمز المتجدد من جوال الكبار.
     *
     * @param string   $token      الرمز كما قرأه القارئ
     * @param int|null $scanned_at وقت المسح — الطابور قد يصل بعد ساعة، والنافذة تُحسب من وقت المسح
     */
    public static function find_by_badge(string $token, ?int $scanned_at = null): ?object
    {
        global $wpdb;

        $token = sanitize_text_field($token);
        if ($token === '') {
            return null;
        }

        // رمز متجدد من جوال الطالب — لقطة الشاشة تنتهي صلاحيتها خلال نصف دقيقة.
        if (SCH_Pass::looks_like($token)) {
            return SCH_Pass::verify($token, $scanned_at);
        }

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('students') . '
             WHERE (badge_token = %s OR qr_token = %s OR academic_no = %s OR student_no = %s)
               AND status = %s
             LIMIT 1',
            $token,
            $token,
            $token,
            $token,
            'active'
        ));

        return $row ?: null;
    }

    // ---------- التسجيل ----------

    /**
     * تسجيل انتقال عهدة.
     *
     * @param array{student_id:int,checkpoint:string,client_uuid:string,receiver_name?:string,
     *              receiver_relation?:string,reason?:string,lat?:mixed,lng?:mixed,occurred_at?:string} $d
     * @return array{event_id:int,state:string,student:object,duplicate:bool}|WP_Error
     */
    public static function record(array $d): array|WP_Error
    {
        global $wpdb;

        $checkpoint = (string) ($d['checkpoint'] ?? '');
        $uuid       = sanitize_text_field((string) ($d['client_uuid'] ?? ''));

        if (!isset(self::RESULT[$checkpoint]) && $checkpoint !== 'manual' && $checkpoint !== 'correction') {
            return sch_api_error('bad_checkpoint', __('نقطة التحقق غير معروفة.', 'school-system'), 422);
        }
        if ($uuid === '') {
            return sch_api_error('missing_uuid', __('client_uuid مطلوب.', 'school-system'), 422);
        }

        $student = self::resolve_student($d);
        if (!$student) {
            $expired = !empty($d['badge_token']) && SCH_Pass::looks_like((string) $d['badge_token']);

            return sch_api_error(
                $expired ? 'pass_expired' : 'student_not_found',
                $expired
                    ? __('انتهت صلاحية الرمز — اطلب من الطالب تحديث شاشته.', 'school-system')
                    : __('لم يُتعرَّف على الطالب.', 'school-system'),
                404
            );
        }

        $student_id = (int) $student->id;

        // نفس العملية أُرسلت من قبل؟ أعِد النتيجة الأولى بلا سجل ثانٍ.
        $dupe = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('custody_events') . ' WHERE client_uuid = %s LIMIT 1',
            $uuid
        ));

        if ($dupe) {
            return [
                'event_id'  => (int) $dupe->id,
                'state'     => (string) $dupe->to_state,
                'student'   => $student,
                'duplicate' => true,
            ];
        }

        $from = (string) ($student->custody_state ?? 'home');
        $to   = self::RESULT[$checkpoint] ?? $from;

        // الخروج نهاية الدوام: راكب الباص يذهب للباص لا للبيت.
        if ($checkpoint === 'gate_out' && self::rides_bus($student_id)) {
            $to = 'on_bus';
        }

        $guard = self::validate($student, $from, $to, $checkpoint, $d);
        if (is_wp_error($guard)) {
            return $guard;
        }

        $occurred = sch_sanitize_datetime($d['occurred_at'] ?? null) ?? sch_now();

        $wpdb->insert(sch_table('custody_events'), [
            'student_id'        => $student_id,
            'from_state'        => $from,
            'to_state'          => $to,
            'checkpoint'        => $checkpoint,
            'actor_user_id'     => get_current_user_id() ?: null,
            'receiver_name'     => sanitize_text_field((string) ($d['receiver_name'] ?? '')) ?: null,
            'receiver_relation' => sanitize_text_field((string) ($d['receiver_relation'] ?? '')) ?: null,
            'reason'            => sanitize_text_field((string) ($d['reason'] ?? '')) ?: null,
            'lat'               => is_numeric($d['lat'] ?? null) ? number_format((float) $d['lat'], 7, '.', '') : null,
            'lng'               => is_numeric($d['lng'] ?? null) ? number_format((float) $d['lng'], 7, '.', '') : null,
            'client_uuid'       => $uuid,
            'occurred_at'       => $occurred,
            'created_at'        => sch_now(),
        ]);

        $event_id = (int) $wpdb->insert_id;

        $wpdb->update(sch_table('students'), [
            'custody_state' => $to,
            'custody_at'    => $occurred,
        ], ['id' => $student_id]);

        self::after_record($student, $from, $to, $checkpoint, $occurred);

        return ['event_id' => $event_id, 'state' => $to, 'student' => $student, 'duplicate' => false];
    }

    /** التحقق قبل قبول الانتقال — هنا يُرفض ما لا يجوز. */
    private static function validate(object $student, string $from, string $to, string $checkpoint, array $d): true|WP_Error
    {
        $student_id = (int) $student->id;
        $name       = SCH_Enrollment::full_name($student);

        if ($checkpoint === 'manual' || $checkpoint === 'correction') {
            return true;
        }

        if (!in_array($to, self::ALLOWED[$from] ?? [], true)) {
            self::log_rejection($student_id, $from, $to, $checkpoint);

            return sch_api_error('bad_transition', sprintf(
                /* translators: 1: اسم الطالب 2: الحالة الحالية */
                __('%1$s حالته الآن «%2$s» — هذه العملية غير ممكنة.', 'school-system'),
                $name,
                self::state_label($from)
            ), 409);
        }

        if ($checkpoint === 'bus_board' && !self::rides_bus($student_id)) {
            self::log_rejection($student_id, $from, $to, $checkpoint);

            return sch_api_error('not_subscribed', sprintf(
                /* translators: %s: اسم الطالب */
                __('%s غير مشترك في النقل المدرسي.', 'school-system'),
                $name
            ), 409);
        }

        // الروضة والابتدائي: لا تسليم بلا تحديد المستلم.
        if ($checkpoint === 'bus_alight'
            && in_array((string) $student->stage, ['kg', 'primary'], true)
            && trim((string) ($d['receiver_name'] ?? '')) === '') {
            return sch_api_error('receiver_required', sprintf(
                /* translators: %s: اسم الطالب */
                __('%s في مرحلة تتطلب تحديد من استلمه.', 'school-system'),
                $name
            ), 422);
        }

        if ($checkpoint === 'early_out' && trim((string) ($d['receiver_name'] ?? '')) === '') {
            return sch_api_error('receiver_required', __('الخروج المبكر يتطلب تحديد المستلم.', 'school-system'), 422);
        }

        return true;
    }

    /** ما يترتب على الانتقال: حضور، إشعارات، إغلاق إنذارات. */
    private static function after_record(object $student, string $from, string $to, string $checkpoint, string $when): void
    {
        $student_id = (int) $student->id;
        $name       = SCH_Enrollment::full_name($student);
        $today      = current_time('Y-m-d');

        // دخول البوابة يؤكد الحضور — لكل الطلاب، راكب الباص وغيره.
        if ($checkpoint === 'gate_in') {
            SCH_Attendance::mark($student_id, $today, 'present', 'qr');
            SCH_Alerts::resolve('bus_no_arrival', $student_id, $today);
            SCH_Alerts::resolve('no_attendance', $student_id, $today);
        }

        if ($checkpoint === 'bus_board') {
            SCH_Alerts::resolve('bus_no_board', $student_id, $today);
        }

        // الاستثناء فقط يُدفع. الباقي صامت في الخط الزمني.
        $push = match ($checkpoint) {
            'bus_alight' => [__('تم تسليم الطالب', 'school-system'), $name],
            'early_out'  => [
                __('خروج مبكر', 'school-system'),
                sprintf(
                    /* translators: 1: اسم الطالب 2: اسم المستلم */
                    __('غادر %1$s المدرسة — استلمه: %2$s', 'school-system'),
                    $name,
                    (string) ($student->receiver_name ?? '')
                ),
            ],
            // الأب واقف بسيارته ينتظر — الإشعار هنا فائدة لا خبر.
            'gate_out'   => $to === 'home'
                ? [__('خرج من البوابة', 'school-system'), $name]
                : null,
            default      => null,
        };

        foreach (SCH_Guardians::of_student($student_id) as $guardian) {
            if ($push !== null) {
                SCH_Comms::notify((int) $guardian->user_id, $push[0], $push[1], 'custody', $student_id);
                continue;
            }

            // صامت: يظهر في الخط الزمني بلا إزعاج.
            SCH_Comms::notify(
                (int) $guardian->user_id,
                self::CHECKPOINTS[$checkpoint] ?? '',
                $name,
                'custody_silent',
                $student_id
            );
        }
    }

    private static function resolve_student(array $d): ?object
    {
        if (!empty($d['badge_token'])) {
            // وقت المسح لا وقت الوصول — بدونه يُرفض كل ما مُسح دون شبكة.
            $when = !empty($d['occurred_at']) ? strtotime((string) $d['occurred_at']) : null;

            return self::find_by_badge((string) $d['badge_token'], $when ?: null);
        }

        $id = absint($d['student_id'] ?? 0);

        return $id > 0 ? SCH_Students::get($id) : null;
    }

    private static function log_rejection(int $student_id, string $from, string $to, string $checkpoint): void
    {
        sch_audit('custody.rejected', 'student', $student_id, [
            'from'       => $from,
            'to'         => $to,
            'checkpoint' => $checkpoint,
        ]);
    }

    // ---------- التقارير ----------

    /** توزيع الطلاب على الحالات — لوحة العهدة. */
    public static function board(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT custody_state, COUNT(*) AS c FROM " . sch_table('students') . "
             WHERE status = 'active' GROUP BY custody_state"
        ) ?: [];

        $out = array_fill_keys(array_keys(self::STATES), 0);
        foreach ($rows as $r) {
            $out[$r->custody_state] = (int) $r->c;
        }

        $out['total']       = array_sum($out);
        $out['unaccounted'] = SCH_Alerts::open_count('critical');

        return $out;
    }

    /** الطلاب في حالة معيّنة الآن. */
    public static function students_in(string $state, int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, full_name, first_name, father_name, grand_name, family_name,
                    academic_no, grade_level, section, custody_at
             FROM " . sch_table('students') . "
             WHERE status = 'active' AND custody_state = %s
             ORDER BY custody_at DESC LIMIT %d",
            $state,
            $limit
        )) ?: [];
    }

    /** سجل عهدة طالب. */
    public static function history(int $student_id, int $limit = 40): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT e.*, u.display_name FROM ' . sch_table('custody_events') . ' e
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = e.actor_user_id
             WHERE e.student_id = %d ORDER BY e.id DESC LIMIT %d',
            $student_id,
            $limit
        )) ?: [];
    }

    /** آخر عمليات اليوم — تظهر في تطبيق البوابة. */
    public static function recent(int $limit = 10): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT e.*, s.full_name FROM ' . sch_table('custody_events') . ' e
             INNER JOIN ' . sch_table('students') . ' s ON s.id = e.student_id
             WHERE e.occurred_at >= %s
             ORDER BY e.id DESC LIMIT %d',
            current_time('Y-m-d') . ' 00:00:00',
            $limit
        )) ?: [];
    }

    /** إعادة الجميع إلى «خارج المدرسة» — تُشغَّل ليلًا استعدادًا ليوم جديد. */
    public static function nightly_reset(): int
    {
        global $wpdb;

        return (int) $wpdb->query(
            "UPDATE " . sch_table('students') . "
             SET custody_state = 'home'
             WHERE custody_state <> 'home' AND status = 'active'"
        );
    }
}
