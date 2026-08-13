<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * إذن الدواء.
 *
 * اليوم يضع ولي الأمر الدواء في الحقيبة مع ورقة، فتُنسى أو تُعطى الجرعة مرتين.
 * والقاعدة صارمة: **لا يُعطى الطفل دواء بلا إذن مكتوب من وليّه.**
 *
 * ثلاثة قيود تجعل الوحدة محكمة:
 * 1. لا جرعة بلا إذن سارٍ — انتهت المدة فاختفى من قائمة الممرضة.
 * 2. جرعة واحدة لكل موعد — الضغط الثاني مرفوض بوقت الأول.
 * 3. كل إعطاء باسم من أعطاه ووقته، ولا يُحذف.
 */
final class SCH_Medication
{
    public const STATUSES = [
        'pending'  => 'بانتظار الصحة المدرسية',
        'active'   => 'سارٍ',
        'ended'    => 'منتهٍ',
        'rejected' => 'مرفوض',
    ];

    /** المواعيد المتاحة — مواعيد اليوم الدراسي فقط. */
    public const SLOTS = ['08:00', '10:00', '12:00', '14:00'];

    // ---------- الطلب ----------

    public static function request(array $d, array $files = []): array|WP_Error
    {
        global $wpdb;

        $student_id = absint($d['student_id'] ?? 0);
        $name       = sanitize_text_field((string) ($d['med_name'] ?? ''));
        $dose       = sanitize_text_field((string) ($d['dose'] ?? ''));
        $times      = array_values(array_intersect((array) ($d['times'] ?? []), self::SLOTS));

        if (!SCH_Students::get($student_id)) {
            return sch_api_error('student_not_found', __('الطالب غير موجود.', 'school-system'), 404);
        }
        if ($name === '' || $dose === '') {
            return sch_api_error('missing', __('اسم الدواء والجرعة مطلوبان.', 'school-system'), 422);
        }
        if ($times === []) {
            return sch_api_error('no_times', __('اختر موعدًا واحدًا على الأقل.', 'school-system'), 422);
        }

        $start = sch_sanitize_date($d['start_date'] ?? null) ?? current_time('Y-m-d');
        $days  = max(1, min(60, (int) ($d['days'] ?? 1)));
        $end   = gmdate('Y-m-d', strtotime($start) + ($days - 1) * DAY_IN_SECONDS);

        $stored = null;
        if (!empty($files['prescription']['tmp_name'])) {
            $saved = SCH_Enrollment::store_upload($files['prescription'], $student_id, 'rx');
            if (!is_wp_error($saved)) {
                $stored = $saved['stored'];
            }
        }

        $wpdb->insert(sch_table('medications'), [
            'student_id'  => $student_id,
            'med_name'    => $name,
            'dose'        => $dose,
            'times_csv'   => implode(',', $times),
            'start_date'  => $start,
            'end_date'    => $end,
            'note'        => sanitize_text_field((string) ($d['note'] ?? '')) ?: null,
            'file_stored' => $stored,
            'status'      => 'pending',
            'created_by'  => get_current_user_id() ?: null,
            'created_at'  => sch_now(),
        ]);

        $med_id = (int) $wpdb->insert_id;

        SCH_Alerts::open(
            'med_pending',
            'medium',
            $student_id,
            null,
            __('إذن دواء بانتظار المراجعة', 'school-system'),
            sprintf('%s — %s', SCH_Students::get($student_id)->full_name, $name)
        );

        sch_audit('med.requested', 'student', $student_id, ['med' => $name]);

        return ['id' => $med_id];
    }

    /** اعتماد الصحة المدرسية — هنا تُولَّد جرعات المدة كلها. */
    public static function approve(int $med_id): bool|WP_Error
    {
        global $wpdb;

        $med = self::get($med_id);
        if (!$med) {
            return sch_api_error('not_found', __('الإذن غير موجود.', 'school-system'), 404);
        }

        $wpdb->update(sch_table('medications'), [
            'status'      => 'active',
            'approved_by' => get_current_user_id() ?: null,
            'approved_at' => sch_now(),
        ], ['id' => $med_id]);

        $times = explode(',', (string) $med->times_csv);
        $day   = strtotime((string) $med->start_date);
        $last  = strtotime((string) $med->end_date);

        while ($day <= $last) {
            $date = gmdate('Y-m-d', $day);

            foreach ($times as $time) {
                $wpdb->insert(sch_table('med_doses'), [
                    'med_id'   => $med_id,
                    'due_date' => $date,
                    'due_time' => $time,
                ]);
            }

            $day += DAY_IN_SECONDS;
        }

        foreach (SCH_Guardians::of_student((int) $med->student_id) as $g) {
            SCH_Comms::notify(
                (int) $g->user_id,
                __('اعتُمد إذن الدواء', 'school-system'),
                sprintf('%s — %s', $med->med_name, $med->dose),
                'med',
                (int) $med->student_id
            );
        }

        sch_audit('med.approved', 'student', (int) $med->student_id);

        return true;
    }

    public static function reject(int $med_id, string $reason = ''): bool
    {
        global $wpdb;

        $med = self::get($med_id);
        $wpdb->update(sch_table('medications'), [
            'status' => 'rejected',
            'note'   => sanitize_text_field($reason) ?: ($med->note ?? null),
        ], ['id' => $med_id]);

        if ($med) {
            foreach (SCH_Guardians::of_student((int) $med->student_id) as $g) {
                SCH_Comms::notify(
                    (int) $g->user_id,
                    __('لم يُعتمد إذن الدواء', 'school-system'),
                    $reason ?: (string) $med->med_name,
                    'med',
                    (int) $med->student_id
                );
            }
        }

        return true;
    }

    // ---------- الجرعات ----------

    /** جرعات اليوم — ما تراه الممرضة. */
    public static function today_doses(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, m.med_name, m.dose, m.student_id, s.full_name,
                    h.allergies
             FROM " . sch_table('med_doses') . " d
             INNER JOIN " . sch_table('medications') . " m ON m.id = d.med_id
             INNER JOIN " . sch_table('students') . " s ON s.id = m.student_id
             LEFT JOIN " . sch_table('student_health') . " h ON h.student_id = m.student_id
             WHERE d.due_date = %s AND m.status = 'active'
             ORDER BY d.given_at IS NOT NULL, d.due_time",
            current_time('Y-m-d')
        )) ?: [];
    }

    /** تسجيل الإعطاء — لا يتكرر، ويُشعر ولي الأمر. */
    public static function give(int $dose_id): bool|WP_Error
    {
        global $wpdb;

        $dose = $wpdb->get_row($wpdb->prepare(
            "SELECT d.*, m.med_name, m.dose, m.student_id, m.status
             FROM " . sch_table('med_doses') . " d
             INNER JOIN " . sch_table('medications') . " m ON m.id = d.med_id
             WHERE d.id = %d",
            $dose_id
        ));

        if (!$dose) {
            return sch_api_error('not_found', __('الجرعة غير موجودة.', 'school-system'), 404);
        }
        if ($dose->status !== 'active') {
            return sch_api_error('not_active', __('الإذن غير سارٍ.', 'school-system'), 409);
        }
        if ($dose->given_at !== null) {
            return sch_api_error('already_given', sprintf(
                /* translators: %s: وقت الإعطاء */
                __('أُعطيت هذه الجرعة الساعة %s.', 'school-system'),
                substr((string) $dose->given_at, 11, 5)
            ), 409);
        }

        $wpdb->update(sch_table('med_doses'), [
            'given_at' => sch_now(),
            'given_by' => get_current_user_id() ?: null,
        ], ['id' => $dose_id]);

        foreach (SCH_Guardians::of_student((int) $dose->student_id) as $g) {
            SCH_Comms::notify(
                (int) $g->user_id,
                __('أُعطي الدواء', 'school-system'),
                sprintf('%s — %s · %s', $dose->med_name, $dose->dose, current_time('H:i')),
                'med',
                (int) $dose->student_id
            );
        }

        sch_audit('med.given', 'student', (int) $dose->student_id, ['med' => $dose->med_name]);

        return true;
    }

    /** جرعة تأخرت عشرين دقيقة — تنبيه ثم تصعيد. */
    public static function check_missed(): int
    {
        global $wpdb;

        $now = strtotime(current_time('mysql'));

        $late = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.due_time, m.student_id, m.med_name, s.full_name
             FROM " . sch_table('med_doses') . " d
             INNER JOIN " . sch_table('medications') . " m ON m.id = d.med_id
             INNER JOIN " . sch_table('students') . " s ON s.id = m.student_id
             WHERE d.due_date = %s AND d.given_at IS NULL AND m.status = 'active'
             LIMIT 30",
            current_time('Y-m-d')
        )) ?: [];

        $count = 0;

        foreach ($late as $d) {
            $due = strtotime(current_time('Y-m-d') . ' ' . $d->due_time);
            if ($now - $due < 20 * MINUTE_IN_SECONDS) {
                continue;
            }

            SCH_Alerts::open(
                'med_missed',
                'high',
                (int) $d->student_id,
                null,
                __('جرعة دواء تأخرت', 'school-system'),
                sprintf('%s — %s · %s', $d->full_name, $d->med_name, $d->due_time)
            );

            $count++;
        }

        return $count;
    }

    // ---------- القراءة ----------

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('medications') . ' WHERE id = %d', $id)) ?: null;
    }

    /** أدوية طالب مع جرعات اليوم — ما يراه ولي الأمر. */
    public static function of_student(int $student_id): array
    {
        global $wpdb;

        $meds = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('medications') . '
             WHERE student_id = %d ORDER BY FIELD(status, %s, %s, %s, %s), id DESC LIMIT 20',
            $student_id,
            'active',
            'pending',
            'ended',
            'rejected'
        )) ?: [];

        foreach ($meds as $med) {
            $med->doses = $med->status === 'active'
                ? $wpdb->get_results($wpdb->prepare(
                    'SELECT * FROM ' . sch_table('med_doses') . '
                     WHERE med_id = %d AND due_date = %s ORDER BY due_time',
                    (int) $med->id,
                    current_time('Y-m-d')
                )) ?: []
                : [];
        }

        return $meds;
    }

    /** أدوية سارية اليوم — تظهر في بطاقة الطوارئ. */
    public static function active_names(int $student_id): array
    {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT CONCAT(med_name, ' ', dose) FROM " . sch_table('medications') . "
             WHERE student_id = %d AND status = 'active' AND %s BETWEEN start_date AND end_date",
            $student_id,
            current_time('Y-m-d')
        )) ?: [];
    }

    public static function pending_count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . sch_table('medications') . " WHERE status = 'pending'");
    }

    /** إنهاء الأذونات المنتهية مدتها. */
    public static function expire_old(): int
    {
        global $wpdb;

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE " . sch_table('medications') . "
             SET status = 'ended' WHERE status = 'active' AND end_date < %s",
            current_time('Y-m-d')
        ));
    }
}

/**
 * إجازة الطالب.
 *
 * قيمتها ليست في النموذج بل في ثلاثة تكاملات:
 * يصمت إنذار الصباح · الباص لا ينتظر · الحضور يُرصد «بعذر» فلا يخصم من النسبة.
 */
final class SCH_StudentLeave
{
    public const REASONS = [
        'sick'        => 'مرض',
        'appointment' => 'موعد طبي',
        'travel'      => 'سفر مع الأسرة',
        'family'      => 'ظرف عائلي',
        'social'      => 'مناسبة اجتماعية',
        'other'       => 'سبب آخر',
    ];

    public const STATUSES = [
        'pending'  => 'بانتظار الاعتماد',
        'approved' => 'معتمدة',
        'rejected' => 'مرفوضة',
    ];

    /** مهلة تقديم العذر بأثر رجعي — الأب المشغول يتذكر متأخرًا. */
    private const BACKDATE_DAYS = 3;

    public static function request(array $d, array $files = []): array|WP_Error
    {
        global $wpdb;

        $student_id = absint($d['student_id'] ?? 0);
        $reason     = (string) ($d['reason_code'] ?? '');

        if (!SCH_Students::get($student_id)) {
            return sch_api_error('student_not_found', __('الطالب غير موجود.', 'school-system'), 404);
        }
        if (!isset(self::REASONS[$reason])) {
            return sch_api_error('bad_reason', __('اختر سبب الإجازة.', 'school-system'), 422);
        }

        $start = sch_sanitize_date($d['start_date'] ?? null);
        $end   = sch_sanitize_date($d['end_date'] ?? null) ?? $start;

        if (!$start || !$end || $end < $start) {
            return sch_api_error('bad_dates', __('تواريخ الإجازة غير صحيحة.', 'school-system'), 422);
        }

        $earliest = gmdate('Y-m-d', strtotime(current_time('Y-m-d')) - self::BACKDATE_DAYS * DAY_IN_SECONDS);
        if ($start < $earliest) {
            return sch_api_error('too_old', sprintf(
                /* translators: %d: عدد الأيام */
                __('يمكن تقديم العذر خلال %d أيام من الغياب.', 'school-system'),
                self::BACKDATE_DAYS
            ), 422);
        }

        $overlap = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . sch_table('student_leaves') . "
             WHERE student_id = %d AND status IN ('pending','approved')
               AND start_date <= %s AND end_date >= %s LIMIT 1",
            $student_id,
            $end,
            $start
        ));

        if ($overlap) {
            return sch_api_error('overlap', __('يوجد طلب يغطي هذه الفترة.', 'school-system'), 409);
        }

        $stored = null;
        if (!empty($files['report']['tmp_name'])) {
            $saved = SCH_Enrollment::store_upload($files['report'], $student_id, 'leave');
            if (!is_wp_error($saved)) {
                $stored = $saved['stored'];
            }
        }

        $days = (int) ((strtotime($end) - strtotime($start)) / DAY_IN_SECONDS) + 1;

        $wpdb->insert(sch_table('student_leaves'), [
            'student_id'   => $student_id,
            'reason_code'  => $reason,
            'start_date'   => $start,
            'end_date'     => $end,
            'days'         => max(1, $days),
            'note'         => sanitize_text_field((string) ($d['note'] ?? '')) ?: null,
            'file_stored'  => $stored,
            'status'       => 'pending',
            'requested_by' => get_current_user_id() ?: null,
            'created_at'   => sch_now(),
        ]);

        sch_audit('leave.student_requested', 'student', $student_id, ['reason' => $reason]);

        return ['id' => (int) $wpdb->insert_id];
    }

    public static function decide(int $id, string $decision, string $note = ''): bool|WP_Error
    {
        global $wpdb;

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return sch_api_error('bad_decision', __('القرار غير صحيح.', 'school-system'), 422);
        }

        $leave = self::get($id);
        if (!$leave) {
            return sch_api_error('not_found', __('الطلب غير موجود.', 'school-system'), 404);
        }
        if ($decision === 'rejected' && trim($note) === '') {
            return sch_api_error('note_required', __('اكتب سبب الرفض — الرفض الصامت يدفع ولي الأمر للاتصال.', 'school-system'), 422);
        }

        $wpdb->update(sch_table('student_leaves'), [
            'status'        => $decision,
            'decided_by'    => get_current_user_id() ?: null,
            'decided_at'    => sch_now(),
            'decision_note' => sanitize_text_field($note) ?: null,
        ], ['id' => $id]);

        // الاعتماد يرصد الغياب بعذر لأيام الإجازة كلها.
        if ($decision === 'approved') {
            $day  = strtotime((string) $leave->start_date);
            $last = strtotime((string) $leave->end_date);

            while ($day <= $last) {
                SCH_Attendance::mark((int) $leave->student_id, gmdate('Y-m-d', $day), 'excused', 'manual');
                $day += DAY_IN_SECONDS;
            }
        }

        foreach (SCH_Guardians::of_student((int) $leave->student_id) as $g) {
            SCH_Comms::notify(
                (int) $g->user_id,
                $decision === 'approved'
                    ? __('اعتُمدت الإجازة', 'school-system')
                    : __('لم تُعتمد الإجازة', 'school-system'),
                $decision === 'approved'
                    ? sprintf('%s → %s', $leave->start_date, $leave->end_date)
                    : $note,
                'leave',
                (int) $leave->student_id
            );
        }

        sch_audit('leave.student_' . $decision, 'student', (int) $leave->student_id);

        return true;
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('student_leaves') . ' WHERE id = %d', $id)) ?: null;
    }

    public static function of_student(int $student_id, int $limit = 15): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('student_leaves') . '
             WHERE student_id = %d ORDER BY id DESC LIMIT %d',
            $student_id,
            $limit
        )) ?: [];
    }

    public static function pending(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT l.*, s.full_name, s.grade_level, s.section
             FROM " . sch_table('student_leaves') . " l
             INNER JOIN " . sch_table('students') . " s ON s.id = l.student_id
             WHERE l.status = 'pending'
             ORDER BY l.start_date, l.id LIMIT 100"
        ) ?: [];
    }

    public static function pending_count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . sch_table('student_leaves') . " WHERE status = 'pending'");
    }

    /**
     * هل الطالب في إجازة معتمدة اليوم؟
     * يُستخدم لإسكات إنذار الصباح، ولإعفاء الباص من انتظاره.
     */
    public static function on_leave_today(int $student_id): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM " . sch_table('student_leaves') . "
             WHERE student_id = %d AND status = 'approved'
               AND %s BETWEEN start_date AND end_date LIMIT 1",
            $student_id,
            current_time('Y-m-d')
        ));
    }

    /** كل من هم في إجازة اليوم — استعلام واحد بدل استعلام لكل طالب. */
    public static function excused_today(): array
    {
        global $wpdb;

        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT student_id FROM " . sch_table('student_leaves') . "
             WHERE status = 'approved' AND %s BETWEEN start_date AND end_date",
            current_time('Y-m-d')
        )) ?: []);
    }
}
