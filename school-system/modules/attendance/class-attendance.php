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

    /** بداية اليوم الدراسي المتوقّعة للطالب + دقائق التسامح — قابلة للضبط من الإعدادات. */
    private const EXPECTED_IN = '07:30';
    private const GRACE_MIN   = 10;

    public static function expected_in(): string
    {
        $v = (string) sch_settings('attendance_student_in', '');
        return preg_match('/^\d{2}:\d{2}$/', $v) ? $v : self::EXPECTED_IN;
    }

    public static function grace(): int
    {
        $v = sch_settings('attendance_grace', '');
        return $v !== '' ? max(0, (int) $v) : self::GRACE_MIN;
    }

    /**
     * حالة الوصول من وقت المسح: حاضر أو متأخر، ودقائق التأخّر عن الموعد.
     * المقارنة بالوقت المحلّي (كلاهما من current_time) فلا تتأثّر بالمنطقة الزمنية.
     *
     * @return array{status:string,minutes:int}
     */
    public static function status_for_arrival(?string $occurred): array
    {
        $occurred = $occurred ?: sch_now();
        $t        = substr($occurred, 11, 5);            // 'HH:MM'
        if (!preg_match('/^\d{2}:\d{2}$/', $t)) {
            return ['status' => 'present', 'minutes' => 0];
        }

        [$h, $m]   = array_map('intval', explode(':', $t));
        [$eh, $em] = array_map('intval', explode(':', self::expected_in()));

        $mins     = $h * 60 + $m;
        $expected = $eh * 60 + $em;
        $late     = $mins > ($expected + self::grace());

        return [
            'status'  => $late ? 'late' : 'present',
            'minutes' => $late ? max(0, $mins - $expected) : 0,
        ];
    }

    public static function mark(int $student_id, string $date, string $status, string $method = 'manual', string $note = '', ?string $occurred_at = null, int $minutes_late = 0): bool|WP_Error
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
            'class_id'      => $class ? (int) $class->id : null,
            'status'        => $status,
            'method'        => in_array($method, ['qr', 'manual', 'transport'], true) ? $method : 'manual',
            'checked_in_at' => sch_sanitize_datetime($occurred_at) ?: null,
            'minutes_late'  => max(0, $minutes_late),
            'note'          => sanitize_text_field($note) ?: null,
            'recorded_by'   => get_current_user_id() ?: null,
            'recorded_at'   => sch_now(),
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

    /**
     * سجل شهر لطالب، مفهرسًا باليوم (Y-m-d) => صف — لخريطة التقويم.
     *
     * @param string $ym صيغة 'Y-m'
     * @return array<string,object>
     */
    public static function month(int $student_id, string $ym): array
    {
        global $wpdb;

        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = current_time('Y-m');
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT att_date, status, method, checked_in_at, minutes_late, note
             FROM ' . sch_table('attendance') . "
             WHERE student_id = %d AND att_date LIKE %s
             ORDER BY att_date",
            $student_id,
            $wpdb->esc_like($ym) . '-%'
        )) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->att_date] = $r;
        }

        return $out;
    }

    /** ملخّص شهر لطالب: عدّاد كل حالة + دقائق التأخّر + نسبة الحضور. */
    public static function month_totals(int $student_id, string $ym): array
    {
        $t = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'late_minutes' => 0, 'total' => 0, 'rate' => 0.0];

        foreach (self::month($student_id, $ym) as $r) {
            $s = (string) $r->status;
            if (isset($t[$s])) {
                $t[$s]++;
            }
            $t['late_minutes'] += (int) $r->minutes_late;
            $t['total']++;
        }

        $t['rate'] = $t['total'] > 0 ? round(($t['present'] + $t['late']) / $t['total'] * 100, 1) : 0.0;

        return $t;
    }

    /**
     * تقرير شهر لشعبة كاملة: صف لكل طالب بعدّاداته ونسبته.
     * استعلام واحد مجمَّع — لا N+1 لثلاثين طالبًا.
     *
     * @return array<int,object>
     */
    public static function class_month(int $class_id, string $ym): array
    {
        global $wpdb;

        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = current_time('Y-m');
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.full_name, s.academic_no,
                    SUM(a.status = 'present') AS present,
                    SUM(a.status = 'late')    AS late,
                    SUM(a.status = 'absent')  AS absent,
                    SUM(a.status = 'excused') AS excused,
                    COALESCE(SUM(a.minutes_late), 0) AS late_minutes,
                    COUNT(a.id) AS total
             FROM " . sch_table('enrollments') . " e
             INNER JOIN " . sch_table('students') . " s ON s.id = e.student_id
             LEFT JOIN " . sch_table('attendance') . " a
                    ON a.student_id = s.id AND a.att_date LIKE %s
             WHERE e.class_id = %d AND e.status = %s AND s.status = %s
             GROUP BY s.id, s.full_name, s.academic_no
             ORDER BY s.full_name",
            $wpdb->esc_like($ym) . '-%',
            $class_id,
            'active',
            'active'
        )) ?: [];
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

        self::queue_delivery((int) $wpdb->insert_id, $user_id);

        return true;
    }

    /**
     * يضع الإشعار في طابور التسليم عبر القنوات المفعّلة — يُرسَل لاحقًا بالكرون
     * لا في الطلب، فبثّ لمئات الأولياء لا يرسل مئات الرسائل تباعًا فيُعلّق الطلب.
     */
    private static function queue_delivery(int $notification_id, int $user_id): void
    {
        global $wpdb;

        if ($notification_id <= 0 || !sch_settings('notify_email', true)) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user || !is_email((string) $user->user_email)) {
            return;
        }

        $wpdb->insert(sch_table('deliveries'), [
            'notification_id' => $notification_id,
            'user_id'         => $user_id,
            'channel'         => 'email',
            'status'          => 'queued',
            'attempts'        => 0,
            'created_at'      => sch_now(),
            'updated_at'      => sch_now(),
        ]);
    }

    /**
     * معالج الطابور — يُشغَّل بالكرون: يرسل دفعة عبر القناة ويعيد المحاولة حتى
     * ثلاثًا ثم يعلّمها فاشلة. قنوات Push/SMS تُوصَّل هنا لاحقًا بمفاتيحها.
     */
    public static function process_deliveries(int $limit = 30): int
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.user_id, d.channel, d.attempts, n.title, n.body
             FROM " . sch_table('deliveries') . " d
             INNER JOIN " . sch_table('notifications') . " n ON n.id = d.notification_id
             WHERE d.status = 'queued' AND d.attempts < 3
             ORDER BY d.id ASC LIMIT %d",
            max(1, $limit)
        )) ?: [];

        $sent = 0;

        foreach ($rows as $d) {
            $user = get_userdata((int) $d->user_id);
            $ok   = false;

            if ($d->channel === 'email' && $user && is_email((string) $user->user_email)) {
                $ok = (bool) wp_mail(
                    (string) $user->user_email,
                    (string) $d->title,
                    (string) ($d->body ?: $d->title)
                );
            }

            $attempts = (int) $d->attempts + 1;

            $wpdb->update(sch_table('deliveries'), [
                'status'     => $ok ? 'sent' : ($attempts >= 3 ? 'failed' : 'queued'),
                'attempts'   => $attempts,
                'updated_at' => sch_now(),
            ], ['id' => (int) $d->id]);

            if ($ok) {
                $sent++;
            }
        }

        return $sent;
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
