<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** الحضور اليومي. سجل واحد لكل طالب في كل يوم. */
final class SCH_Attendance
{
    public const STATUSES = [
        'present' => 'حاضر',
        'absent'  => 'غائب',
        'late'    => 'متأخر',
        'excused' => 'غياب بعذر',
    ];

    public static function mark(int $student_id, string $date, string $status, string $method = 'manual', string $note = ''): bool|WP_Error
    {
        global $wpdb;

        if (!array_key_exists($status, self::STATUSES)) {
            return sch_api_error('bad_status', __('حالة الحضور غير صحيحة.', 'school-system'), 422);
        }
        if (!sch_sanitize_date($date)) {
            return sch_api_error('bad_date', __('التاريخ غير صحيح.', 'school-system'), 422);
        }

        $class = SCH_Students::current_class($student_id);

        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('attendance') . ' WHERE student_id = %d AND att_date = %s',
            $student_id,
            $date
        ));

        $data = [
            'class_id'    => $class ? (int) $class->id : null,
            'status'      => $status,
            'method'      => in_array($method, ['qr', 'manual', 'transport'], true) ? $method : 'manual',
            'note'        => sanitize_text_field($note) ?: null,
            'recorded_by' => get_current_user_id() ?: null,
            'recorded_at' => sch_now(),
        ];

        if ($existing) {
            // تسجيل النقل لا يلغي قرار المعلم اليدوي.
            if ($method === 'transport') {
                return true;
            }
            $wpdb->update(sch_table('attendance'), $data, ['id' => (int) $existing]);
        } else {
            $wpdb->insert(sch_table('attendance'), $data + [
                'student_id' => $student_id,
                'att_date'   => $date,
            ]);
        }

        if ($status === 'absent') {
            self::notify_absence($student_id, $date);
        }

        return true;
    }

    /** كشف حضور شعبة في يوم. */
    public static function sheet(int $class_id, string $date): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.id, s.full_name, a.status, a.method, a.note
             FROM ' . sch_table('enrollments') . ' e
             INNER JOIN ' . sch_table('students') . ' s ON s.id = e.student_id
             LEFT JOIN ' . sch_table('attendance') . ' a ON a.student_id = s.id AND a.att_date = %s
             WHERE e.class_id = %d AND e.status = %s AND s.status = %s
             ORDER BY s.full_name',
            $date,
            $class_id,
            'active',
            'active'
        )) ?: [];
    }

    /** ملخص اليوم على مستوى المدرسة. */
    public static function day_summary(string $date): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . sch_table('attendance') . '
             WHERE att_date = %s GROUP BY status',
            $date
        )) ?: [];

        $out = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($rows as $r) {
            $out[$r->status] = (int) $r->c;
        }

        return $out;
    }

    /** سجل حضور طالب خلال فترة. */
    public static function history(int $student_id, int $days = 30): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('attendance') . '
             WHERE student_id = %d AND att_date >= %s
             ORDER BY att_date DESC',
            $student_id,
            gmdate('Y-m-d', time() - $days * DAY_IN_SECONDS)
        )) ?: [];
    }

    /** نسبة الحضور لطالب — تُستخدم في التقارير ومؤشرات الإنذار. */
    public static function rate(int $student_id, int $days = 60): float
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total, SUM(status IN ('present','late')) AS present
             FROM " . sch_table('attendance') . "
             WHERE student_id = %d AND att_date >= %s",
            $student_id,
            gmdate('Y-m-d', time() - $days * DAY_IN_SECONDS)
        ));

        $total = (int) ($row->total ?? 0);
        return $total === 0 ? 0.0 : round((int) $row->present / $total * 100, 1);
    }

    private static function notify_absence(int $student_id, string $date): void
    {
        $student = SCH_Students::get($student_id);
        if (!$student) {
            return;
        }

        foreach (SCH_Guardians::of_student($student_id) as $g) {
            SCH_Comms::notify(
                (int) $g->user_id,
                __('غياب اليوم', 'school-system'),
                sprintf(
                    /* translators: 1: اسم الطالب 2: التاريخ */
                    __('سُجّل غياب %1$s بتاريخ %2$s.', 'school-system'),
                    $student->full_name,
                    $date
                ),
                'attendance',
                $student_id
            );
        }
    }
}

/** الرسائل والإشعارات داخل النظام. */
final class SCH_Comms
{
    public const AUDIENCES = [
        'all_guardians' => 'كل أولياء الأمور',
        'class'         => 'أولياء أمور شعبة',
        'staff'         => 'الموظفون',
    ];

    /** إشعار فردي — هذا ما يقرؤه التطبيق. */
    public static function notify(int $user_id, string $title, string $body = '', ?string $ref_type = null, ?int $ref_id = null): bool
    {
        global $wpdb;

        if ($user_id <= 0) {
            return false;
        }

        $wpdb->insert(sch_table('notifications'), [
            'user_id'    => $user_id,
            'title'      => sanitize_text_field($title),
            'body'       => sanitize_textarea_field($body) ?: null,
            'ref_type'   => $ref_type,
            'ref_id'     => $ref_id,
            'created_at' => sch_now(),
        ]);

        return true;
    }

    /** رسالة جماعية — تُخزَّن مرة وتُوزَّع إشعارات. */
    public static function broadcast(array $d): array|WP_Error
    {
        global $wpdb;

        $title    = sanitize_text_field((string) ($d['title'] ?? ''));
        $body     = sanitize_textarea_field((string) ($d['body'] ?? ''));
        $audience = (string) ($d['audience'] ?? 'all_guardians');

        if ($title === '' || $body === '') {
            return sch_api_error('missing_content', __('العنوان والنص مطلوبان.', 'school-system'), 422);
        }
        if (!array_key_exists($audience, self::AUDIENCES)) {
            return sch_api_error('bad_audience', __('الفئة المستهدفة غير صحيحة.', 'school-system'), 422);
        }

        $audience_id = absint($d['audience_id'] ?? 0) ?: null;

        $wpdb->insert(sch_table('messages'), [
            'title'       => $title,
            'body'        => $body,
            'audience'    => $audience,
            'audience_id' => $audience_id,
            'created_by'  => get_current_user_id() ?: null,
            'created_at'  => sch_now(),
        ]);

        $message_id = (int) $wpdb->insert_id;
        $recipients = self::resolve_recipients($audience, $audience_id);

        foreach ($recipients as $user_id) {
            self::notify((int) $user_id, $title, $body, 'message', $message_id);
        }

        sch_audit('message.sent', 'message', $message_id, ['audience' => $audience, 'count' => count($recipients)]);

        return ['id' => $message_id, 'msg' => sprintf(
            /* translators: %d: عدد المستقبلين */
            __('أُرسلت الرسالة إلى %d مستخدمًا.', 'school-system'),
            count($recipients)
        )];
    }

    /** @return array<int,int> */
    private static function resolve_recipients(string $audience, ?int $audience_id): array
    {
        global $wpdb;

        return match ($audience) {
            'all_guardians' => array_map('intval', $wpdb->get_col(
                'SELECT user_id FROM ' . sch_table('guardians') . " WHERE status = 'active'"
            ) ?: []),

            'class' => array_map('intval', $wpdb->get_col($wpdb->prepare(
                'SELECT DISTINCT gs.guardian_user_id
                 FROM ' . sch_table('enrollments') . ' e
                 INNER JOIN ' . sch_table('guardian_student') . ' gs ON gs.student_id = e.student_id
                 WHERE e.class_id = %d AND e.status = %s',
                (int) $audience_id,
                'active'
            ) ) ?: []),

            'staff' => array_map('intval', $wpdb->get_col(
                'SELECT user_id FROM ' . sch_table('employees') . " WHERE status = 'active'"
            ) ?: []),

            default => [],
        };
    }

    public static function inbox(int $user_id, int $limit = 50): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('notifications') . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d',
            $user_id,
            $limit
        )) ?: [];
    }

    public static function unread_count(int $user_id): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('notifications') . ' WHERE user_id = %d AND read_at IS NULL',
            $user_id
        ));
    }

    public static function mark_read(int $user_id, int $notification_id = 0): bool
    {
        global $wpdb;

        $where = ['user_id' => $user_id];
        if ($notification_id > 0) {
            $where['id'] = $notification_id;
        }

        $wpdb->update(sch_table('notifications'), ['read_at' => sch_now()], $where);
        return true;
    }

    public static function sent(int $limit = 50): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT m.*, u.display_name FROM ' . sch_table('messages') . ' m
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = m.created_by
             ORDER BY m.id DESC LIMIT %d',
            $limit
        )) ?: [];
    }
}
