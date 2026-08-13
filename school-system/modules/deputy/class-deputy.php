<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الوكيل.
 *
 * المبدأ الذي يميّز هذه الوحدة: النظام **يقترح الحل** لا يعرض المشكلة.
 * هو يملك الجدول والإجازات والنصاب — فليس عذرًا أن يترك الوكيل يبحث.
 */
final class SCH_Deputy
{
    public const STAFF_STATUSES = [
        'present' => 'حاضر',
        'late'    => 'متأخر',
        'absent'  => 'غائب',
        'leave'   => 'في إجازة',
    ];

    public const REASONS = ['leave' => 'إجازة', 'absence' => 'غياب', 'task' => 'مهمة رسمية'];

    // ---------- حضور المعلمين ----------

    public static function mark_staff(int $user_id, string $status, string $note = ''): bool|WP_Error
    {
        global $wpdb;

        if (!array_key_exists($status, self::STAFF_STATUSES)) {
            return sch_api_error('bad_status', __('حالة الحضور غير صحيحة.', 'school-system'), 422);
        }

        $today = current_time('Y-m-d');

        $data = [
            'status'      => $status,
            'checked_at'  => sch_now(),
            'note'        => sanitize_text_field($note) ?: null,
            'recorded_by' => get_current_user_id() ?: null,
        ];

        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('staff_attendance') . ' WHERE user_id = %d AND att_date = %s',
            $user_id,
            $today
        ));

        if ($existing) {
            $wpdb->update(sch_table('staff_attendance'), $data, ['id' => (int) $existing]);
        } else {
            $wpdb->insert(sch_table('staff_attendance'), $data + [
                'user_id'    => $user_id,
                'att_date'   => $today,
                'created_at' => sch_now(),
            ]);
        }

        // الغياب يولّد حصصًا شاغرة — نقترح لها بدلاء فورًا.
        if (in_array($status, ['absent', 'leave'], true)) {
            self::propose_for_teacher($user_id, $today, $status === 'leave' ? 'leave' : 'absence');
        }

        return true;
    }

    /** كشف حضور الموظفين اليوم، مع من هو في إجازة معتمدة. */
    public static function staff_sheet(): array
    {
        global $wpdb;

        $today = current_time('Y-m-d');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID AS user_id, u.display_name, e.job_title,
                    a.status, a.checked_at, a.note,
                    (SELECT COUNT(*) FROM " . sch_table('leaves') . " l
                      WHERE l.user_id = u.ID AND l.status = 'approved'
                        AND %s BETWEEN l.start_date AND l.end_date) AS on_leave
             FROM " . sch_table('employees') . " e
             INNER JOIN {$wpdb->users} u ON u.ID = e.user_id
             LEFT JOIN " . sch_table('staff_attendance') . " a
                    ON a.user_id = u.ID AND a.att_date = %s
             WHERE e.status = 'active'
             ORDER BY u.display_name",
            $today,
            $today
        )) ?: [];
    }

    // ---------- الاحتياط المقترح ----------

    /** حصص معلم غائب في يوم — تُستخرَج من الجدول. */
    public static function vacant_slots(int $teacher_id, string $date): array
    {
        global $wpdb;

        $weekday = (int) gmdate('w', strtotime($date));
        $day     = $weekday === 0 ? 1 : ($weekday <= 4 ? $weekday + 1 : 0);

        if ($day === 0) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            'SELECT t.*, c.grade_level, c.section, s.name AS subject_name
             FROM ' . sch_table('timetable') . ' t
             INNER JOIN ' . sch_table('classes') . ' c ON c.id = t.class_id
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = t.subject_id
             WHERE t.teacher_user_id = %d AND t.day_of_week = %d
             ORDER BY t.period_no',
            $teacher_id,
            $day
        )) ?: [];
    }

    /**
     * ترشيح البدلاء لحصة.
     * الترتيب: يدرّس نفس المادة ← متفرغ في تلك الحصة ← الأقل نصابًا ← لم يأخذ احتياطًا اليوم.
     */
    public static function candidates(int $day, int $period, int $subject_id, string $date, int $limit = 3): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID AS user_id, u.display_name,
                    (SELECT COUNT(*) FROM " . sch_table('class_subjects') . " cs
                      WHERE cs.teacher_user_id = u.ID AND cs.subject_id = %d) AS same_subject,
                    (SELECT COUNT(*) FROM " . sch_table('timetable') . " tt
                      WHERE tt.teacher_user_id = u.ID) AS load_count,
                    (SELECT COUNT(*) FROM " . sch_table('substitutions') . " sb
                      WHERE sb.substitute_teacher_id = u.ID AND sb.sub_date = %s) AS today_subs
             FROM {$wpdb->users} u
             INNER JOIN " . sch_table('employees') . " e ON e.user_id = u.ID AND e.status = 'active'
             WHERE u.ID NOT IN (
                     SELECT t.teacher_user_id FROM " . sch_table('timetable') . " t
                     WHERE t.day_of_week = %d AND t.period_no = %d AND t.teacher_user_id IS NOT NULL
                   )
               AND u.ID NOT IN (
                     SELECT l.user_id FROM " . sch_table('leaves') . " l
                     WHERE l.status = 'approved' AND %s BETWEEN l.start_date AND l.end_date
                   )
               AND u.ID NOT IN (
                     SELECT a.user_id FROM " . sch_table('staff_attendance') . " a
                     WHERE a.att_date = %s AND a.status IN ('absent','leave')
                   )
             ORDER BY same_subject DESC, today_subs ASC, load_count ASC, u.display_name
             LIMIT %d",
            $subject_id,
            $date,
            $day,
            $period,
            $date,
            $date,
            $limit
        )) ?: [];
    }

    /** إنشاء مقترحات لكل حصص معلم غائب. */
    public static function propose_for_teacher(int $teacher_id, string $date, string $reason = 'leave'): int
    {
        global $wpdb;

        $created  = 0;
        $by_scope = [];

        foreach (self::vacant_slots($teacher_id, $date) as $slot) {
            $class = SCH_Classes::get((int) $slot->class_id);
            $scope = SCH_Org::scope_of_stage($class->stage ?? null);
            $exists = $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . sch_table('substitutions') . '
                 WHERE sub_date = %s AND class_id = %d AND period_no = %d',
                $date,
                (int) $slot->class_id,
                (int) $slot->period_no
            ));

            if ($exists) {
                continue;
            }

            $wpdb->insert(sch_table('substitutions'), [
                'sub_date'            => $date,
                'class_id'            => (int) $slot->class_id,
                'period_no'           => (int) $slot->period_no,
                'subject_id'          => (int) $slot->subject_id,
                'original_teacher_id' => $teacher_id,
                'reason'              => array_key_exists($reason, self::REASONS) ? $reason : 'leave',
                'status'              => 'proposed',
                'created_at'          => sch_now(),
            ]);

            $created++;
            $by_scope[$scope] = ($by_scope[$scope] ?? 0) + 1;
        }

        // الحصة تتبع الشعبة لا المعلم: معلم يدرّس مرحلتين يولّد بندين عند مشرفين.
        if ($created > 0) {
            foreach ($by_scope as $scope => $count) {
                $supervisor = SCH_Org::supervisor_of_stage(
                    $scope === 'early' ? 'primary' : $scope
                );

                SCH_Alerts::open(
                    'vacant_periods',
                    'high',
                    null,
                    null,
                    __('حصص بلا معلم', 'school-system'),
                    sprintf(
                        /* translators: 1: عدد الحصص 2: النطاق */
                        __('%1$d حصة في %2$s تحتاج بديلًا.', 'school-system'),
                        $count,
                        SCH_Org::SCOPES[$scope] ?? ''
                    )
                );

                if ($supervisor) {
                    SCH_Comms::notify(
                        $supervisor,
                        __('حصص تحتاج بديلًا', 'school-system'),
                        sprintf(
                            /* translators: %d: عدد الحصص */
                            _n('حصة واحدة في نطاقك.', '%d حصص في نطاقك.', $count, 'school-system'),
                            $count
                        ),
                        'substitution',
                        null
                    );
                }
            }
        }

        return $created;
    }

    /** الحصص الشاغرة اليوم مع مرشحيها. */
    public static function today_vacancies(): array
    {
        global $wpdb;

        $date    = current_time('Y-m-d');
        $weekday = (int) current_time('w');
        $day     = $weekday === 0 ? 1 : ($weekday <= 4 ? $weekday + 1 : 0);

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT sb.*, c.grade_level, c.section, s.name AS subject_name,
                    u.display_name AS original_name, v.display_name AS substitute_name
             FROM ' . sch_table('substitutions') . ' sb
             INNER JOIN ' . sch_table('classes') . ' c ON c.id = sb.class_id
             LEFT JOIN ' . sch_table('subjects') . ' s ON s.id = sb.subject_id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = sb.original_teacher_id
             LEFT JOIN ' . $wpdb->users . ' v ON v.ID = sb.substitute_teacher_id
             WHERE sb.sub_date = %s
             ORDER BY sb.status, sb.period_no',
            $date
        )) ?: [];

        // المشرف يرى نطاقه وحده؛ الوكيل والمدير يريان الكل.
        $scope = SCH_Org::my_scope();

        if ($scope !== 'all' && $scope !== 'none') {
            $rows = array_values(array_filter(
                $rows,
                static fn (object $r): bool => SCH_Org::may_touch_class((int) $r->class_id)
            ));
        }

        foreach ($rows as $row) {
            $row->candidates = $row->status === 'proposed' && $day > 0
                ? self::candidates($day, (int) $row->period_no, (int) $row->subject_id, $date)
                : [];
        }

        return $rows;
    }

    /** اعتماد البديل — ضغطة واحدة تحدّث الجدول وتُشعر المعلم. */
    public static function assign(int $sub_id, int $teacher_id): bool|WP_Error
    {
        global $wpdb;

        $sub = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('substitutions') . ' WHERE id = %d',
            $sub_id
        ));

        if (!$sub) {
            return sch_api_error('not_found', __('الحصة غير موجودة.', 'school-system'), 404);
        }
        if ($teacher_id === 0 || !get_user_by('id', $teacher_id)) {
            return sch_api_error('bad_teacher', __('اختر معلمًا.', 'school-system'), 422);
        }

        $wpdb->update(sch_table('substitutions'), [
            'substitute_teacher_id' => $teacher_id,
            'status'                => 'assigned',
            'assigned_by'           => get_current_user_id() ?: null,
        ], ['id' => $sub_id]);

        $class = SCH_Classes::get((int) $sub->class_id);

        SCH_Comms::notify(
            $teacher_id,
            __('حصة احتياط', 'school-system'),
            sprintf(
                /* translators: 1: الحصة 2: الشعبة */
                __('أُسندت إليك الحصة %1$d — %2$s اليوم.', 'school-system'),
                (int) $sub->period_no,
                $class ? SCH_Classes::label($class) : ''
            ),
            'substitution',
            $sub_id
        );

        sch_audit('substitution.assigned', 'substitution', $sub_id, ['teacher' => $teacher_id]);

        return true;
    }

    // ---------- شاشة يوم الوكيل ----------

    /** قائمة مشاكل اليوم لا قائمة أقسام. */
    public static function today_board(): array
    {
        global $wpdb;

        $date = current_time('Y-m-d');

        $vacant = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('substitutions') . "
             WHERE sub_date = %s AND status = 'proposed'",
            $date
        ));

        $unchecked = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('employees') . " e
             LEFT JOIN " . sch_table('staff_attendance') . " a
                    ON a.user_id = e.user_id AND a.att_date = %s
             WHERE e.status = 'active' AND a.id IS NULL",
            $date
        ));

        return [
            'vacant'      => $vacant,
            'unchecked'   => $unchecked,
            'stale_notes' => SCH_Notes::open_count('specialist'),
            'clinic'      => SCH_Notes::open_count('clinic'),
            'leaves'      => class_exists('SCH_StudentLeave') ? SCH_StudentLeave::pending_count() : 0,
            'meds'        => class_exists('SCH_Medication') ? SCH_Medication::pending_count() : 0,
            'alerts'      => SCH_Alerts::open_count(),
            'critical'    => SCH_Alerts::open_count('critical'),
        ];
    }
}
