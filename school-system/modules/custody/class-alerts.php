<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الحارس الآلي.
 *
 * لا يمسح شيئًا — يقارن الحالات بالوقت المتوقَّع ويصرخ.
 * وظيفته كشف **الحدث الغائب**: صعد الباص ولم يدخل المدرسة.
 *
 * يعمل كل خمس دقائق. يحتاج Cron حقيقي من لوحة الاستضافة،
 * لأن مؤقت ووردبريس لا يعمل إلا عند زيارة الموقع.
 */
final class SCH_Alerts
{
    public const HOOK = 'sch_guardian_tick';

    /** تصفير العهدة الليلي — يعيد حالة كل طالب إلى «المنزل» استعدادًا ليوم جديد. */
    public const HOOK_NIGHTLY = 'sch_custody_nightly';

    public const SEVERITIES = [
        'medium'   => 'متوسطة',
        'high'     => 'عالية',
        'critical' => 'حرجة',
    ];

    /** الإعدادات الافتراضية — قابلة للتغيير من إعدادات المدرسة. */
    private const DEFAULTS = [
        'bus_no_board_at'   => '06:50',
        'bus_arrival_by'    => '07:45',
        'attendance_by'     => '09:00',
        'dismissal_at'      => '13:00',
        'still_at_school'   => 30,   // دقيقة بعد الانصراف
        'referral_minutes'  => 10,
    ];

    public static function init(): void
    {
        add_action(self::HOOK, [self::class, 'run']);
        // طابور تسليم الإشعارات يُعالَج على نفس النبضة (كل خمس دقائق).
        add_action(self::HOOK, ['SCH_Comms', 'process_deliveries']);

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 300, 'sch_five_minutes', self::HOOK);
        }

        // تصفير العهدة الليلي — مرة كل ليلة نحو الثانية فجرًا بتوقيت الموقع.
        // بدونه تبقى حالة الطالب «في المدرسة/في الباص» فيُرفض مسح الصباح
        // ويُطلَق إنذار «بقي في الباص» كاذبًا كل يوم.
        add_action(self::HOOK_NIGHTLY, ['SCH_Custody', 'nightly_reset']);

        if (!wp_next_scheduled(self::HOOK_NIGHTLY)) {
            $local_2am = strtotime('tomorrow 02:00:00', current_time('timestamp'));
            $gmt_ts    = $local_2am - (int) (get_option('gmt_offset') * HOUR_IN_SECONDS);
            wp_schedule_event($gmt_ts, 'daily', self::HOOK_NIGHTLY);
        }

        add_filter('cron_schedules', [self::class, 'add_interval']);
    }

    public static function add_interval(array $schedules): array
    {
        $schedules['sch_five_minutes'] = [
            'interval' => 300,
            'display'  => __('كل خمس دقائق', 'school-system'),
        ];

        return $schedules;
    }

    private static function setting(string $key): string|int
    {
        $value = sch_settings('alert_' . $key, '');
        return $value !== '' ? $value : self::DEFAULTS[$key];
    }

    // ---------- الدورة ----------

    public static function run(): void
    {
        // يوم دراسي فقط. والسبت يوم دوامٍ في مدارس — `SCH_Timetable::school_day()`
        // هي المرجع الواحد، فلا تُسكَت إنذارات يومٍ فيه أطفال.
        if (SCH_Timetable::school_day() === 0) {
            return;
        }

        $now = current_time('H:i');

        // إشعار الغياب بعد مهلة التصحيح لا في لحظة الرصد.
        SCH_Attendance::sweep_absence_notices();

        self::rule_no_board($now);
        self::rule_no_arrival($now);
        self::rule_no_attendance($now);
        self::rule_staff_no_show($now);
        self::rule_still_at_school($now);
        self::rule_stale_referral();

        if (class_exists('SCH_Medication')) {
            SCH_Medication::check_missed();
        }

        // الباص يقترب من نقطة أحدهم — إشعار واحد لكل رحلة، فلا يفقد قيمته.
        foreach (SCH_Trips::today() as $running) {
            if ($running->status === 'running') {
                SCH_Trips::notify_approaching((int) $running->id);
            }
        }

        // التصعيد ومراجعة الحلقة — مرة كل ساعة تكفي، لا كل خمس دقائق.
        $hourly = (int) get_option('sch_alerts_hourly', 0);
        if ($hourly < strtotime(current_time('mysql')) - 3600) {
            if (class_exists('SCH_Notes')) {
                SCH_Notes::escalate_stale();
            }
            if (class_exists('SCH_Nerve')) {
                SCH_Nerve::run_rechecks();
            }
            if (class_exists('SCH_Medication')) {
                SCH_Medication::expire_old();
            }
            if (class_exists('SCH_Org')) {
                self::escalate_unowned();
            }
            update_option('sch_alerts_hourly', strtotime(current_time('mysql')), false);
        }

        update_option('sch_alerts_last_run', current_time('mysql'), false);
    }

    /** 1) مشترك نقل ولم يُسجَّل صعوده — إشعار لولي الأمر. */
    private static function rule_no_board(string $now): void
    {
        global $wpdb;

        if ($now < (string) self::setting('bus_no_board_at') || $now > '07:30') {
            return;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.full_name, s.first_name, s.family_name
             FROM " . sch_table('transport_subs') . " sub
             INNER JOIN " . sch_table('students') . " s ON s.id = sub.student_id
             WHERE sub.status = 'active' AND sub.year_id = %d
               AND s.status = 'active' AND s.custody_state = 'home'
             LIMIT 200",
            SCH_Years::current_id()
        )) ?: [];

        // من له إجازة معتمدة اليوم لا يُزعَج أحد بسببه.
        $excused = class_exists('SCH_StudentLeave') ? SCH_StudentLeave::excused_today() : [];

        foreach ($rows as $s) {
            if (in_array((int) $s->id, $excused, true)) {
                continue;
            }
            $created = self::open('bus_no_board', 'medium', (int) $s->id, null,
                __('لم يُسجَّل صعوده للباص', 'school-system'),
                (string) $s->full_name);

            if ($created) {
                foreach (SCH_Guardians::of_student((int) $s->id) as $g) {
                    SCH_Comms::notify(
                        (int) $g->user_id,
                        __('لم يصعد الباص', 'school-system'),
                        sprintf(
                            /* translators: %s: اسم الطالب */
                            __('لم يُسجَّل صعود %s للباص. هل هو معك اليوم؟', 'school-system'),
                            $s->full_name
                        ),
                        'custody',
                        (int) $s->id
                    );
                }
            }
        }
    }

    /**
     * 2) في الباص ولم يدخل المدرسة — القاعدة الأهم في النظام كله.
     * هي التي تكتشف طفلًا نائمًا في مقعد خلفي قبل أن يسأل عنه أحد.
     */
    private static function rule_no_arrival(string $now): void
    {
        global $wpdb;

        if ($now < (string) self::setting('bus_arrival_by') || $now > '11:00') {
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT id, full_name, custody_at FROM " . sch_table('students') . "
             WHERE status = 'active' AND custody_state = 'on_bus'
             LIMIT 200"
        ) ?: [];

        foreach ($rows as $s) {
            self::open('bus_no_arrival', 'critical', (int) $s->id, null,
                __('صعد الباص ولم يدخل المدرسة', 'school-system'),
                sprintf(
                    /* translators: 1: اسم الطالب 2: وقت الصعود */
                    __('%1$s — آخر رصد: %2$s. تحقّق من الباص فورًا.', 'school-system'),
                    $s->full_name,
                    (string) $s->custody_at
                ));
        }
    }

    /** 3) لم يُرصد حضوره ولا غيابه — للإدارة لا لولي الأمر. */
    private static function rule_no_attendance(string $now): void
    {
        global $wpdb;

        if ($now < (string) self::setting('attendance_by') || $now > '11:30') {
            return;
        }

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('students') . " s
             LEFT JOIN " . sch_table('attendance') . " a
                    ON a.student_id = s.id AND a.att_date = %s
             LEFT JOIN " . sch_table('student_leaves') . " l
                    ON l.student_id = s.id AND l.status = 'approved'
                   AND %s BETWEEN l.start_date AND l.end_date
             WHERE s.status = 'active' AND a.id IS NULL AND l.id IS NULL",
            current_time('Y-m-d'),
            current_time('Y-m-d')
        ));

        if ($count > 0) {
            self::open('no_attendance', 'medium', null, null,
                __('طلاب بلا رصد حضور', 'school-system'),
                sprintf(
                    /* translators: %d: عدد الطلاب */
                    _n('%d طالب لم يُرصد حضوره ولا غيابه.', '%d طلاب لم يُرصد حضورهم ولا غيابهم.', $count, 'school-system'),
                    $count
                ));
        }
    }

    /**
     * 3.ب) موظفون لم يُسجَّل حضورهم بعد بدء الدوام + مهلة — للوكيل لا لأحد آخر.
     * إنذار مجمَّع بعدد لا قائمة أفراد، والوكيل يفتح كشف الحضور فيرى مَن.
     */
    private static function rule_staff_no_show(string $now): void
    {
        global $wpdb;

        if (!class_exists('SCH_StaffAttendance')) {
            return;
        }

        // مهلة ٤٥ دقيقة بعد بدء دوام الموظف قبل الإنذار — تأخّر ربع ساعة ليس غيابًا.
        $cutoff = gmdate('H:i', strtotime((string) SCH_StaffAttendance::expected_in()) + 45 * 60);
        if ($now < $cutoff || $now > '11:00') {
            return;
        }

        $today = current_time('Y-m-d');

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM " . sch_table('employees') . " e
             INNER JOIN {$wpdb->users} u ON u.ID = e.user_id
             LEFT JOIN " . sch_table('staff_attendance') . " a
                    ON a.user_id = u.ID AND a.att_date = %s
             LEFT JOIN " . sch_table('leaves') . " l
                    ON l.user_id = u.ID AND l.status = 'approved'
                   AND %s BETWEEN l.start_date AND l.end_date
             WHERE e.status = 'active' AND a.id IS NULL AND l.id IS NULL",
            $today,
            $today
        ));

        if ($count > 0) {
            self::open('staff_no_show', 'medium', null, null,
                __('موظفون بلا رصد حضور', 'school-system'),
                sprintf(
                    /* translators: %d: عدد الموظفين */
                    _n('%d موظف لم يُسجَّل حضوره بعد.', '%d موظفين لم يُسجَّل حضورهم بعد.', $count, 'school-system'),
                    $count
                ));
        }
    }

    /** 4) ما زال في المدرسة بعد الانصراف. */
    private static function rule_still_at_school(string $now): void
    {
        $cutoff = gmdate('H:i', strtotime((string) self::setting('dismissal_at')) + ((int) self::setting('still_at_school')) * 60);

        if ($now < $cutoff || $now > '18:00') {
            return;
        }

        foreach (SCH_Custody::students_in('at_school', 200) as $s) {
            self::open('still_at_school', 'high', (int) $s->id, null,
                __('ما زال في المدرسة بعد الانصراف', 'school-system'),
                (string) $s->full_name);
        }
    }

    /** 5) إحالة للعيادة لم تُفتح خلال المهلة — تعمل بعد بناء وحدة الملاحظات. */
    private static function rule_stale_referral(): void
    {
        global $wpdb;

        $table = sch_table('student_notes');

        // الجدول يُبنى في مرحلة لاحقة — نتجاوز بهدوء حتى ذلك الحين.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return;
        }

        $minutes = (int) self::setting('referral_minutes');
        $cutoff  = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - $minutes * 60);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT n.student_id, s.full_name
             FROM {$table} n
             INNER JOIN " . sch_table('students') . " s ON s.id = n.student_id
             WHERE n.route = 'clinic' AND n.status = 'open' AND n.created_at <= %s
             LIMIT 50",
            $cutoff
        )) ?: [];

        foreach ($rows as $r) {
            self::open(
                'stale_referral',
                'high',
                (int) $r->student_id,
                null,
                __('إحالة صحية لم تُفتح', 'school-system'),
                (string) $r->full_name
            );
        }
    }

    // ---------- إدارة الإنذارات ----------

    /** فتح إنذار — لا يتكرر لنفس الطالب في نفس اليوم. */
    /**
     * التصعيد المتدرج: المشرف أولًا ← الوكيل بعد ساعة ← المدير بعد يوم.
     * الإنذار الذي يبقى مفتوحًا بلا صاحب لا يُغلقه أحد.
     */
    public static function escalate_unowned(): int
    {
        global $wpdb;

        $now      = strtotime(current_time('mysql'));
        $deputy   = SCH_Org::deputy_user_id();
        $principal = SCH_Org::principal_user_id();
        $moved    = 0;

        $open = $wpdb->get_results(
            "SELECT id, title, opened_at, severity FROM " . sch_table('alerts') . "
             WHERE status = 'open' LIMIT 60"
        ) ?: [];

        foreach ($open as $a) {
            $age = $now - strtotime((string) $a->opened_at);

            // كل طبقة تُشعَر مرة واحدة لكل إنذار — بلا هذا يُنبَّه المدير كل ساعة
            // إلى الأبد فيصمت عن الجرس (إشعار يتكرر يفقد قيمته).
            if ($age >= DAY_IN_SECONDS && $principal) {
                $key = 'sch_esc_pri_' . (int) $a->id;
                if (!get_transient($key)) {
                    SCH_Comms::notify($principal, __('إنذار مفتوح منذ يوم', 'school-system'), (string) $a->title, 'alert', (int) $a->id);
                    set_transient($key, 1, 30 * DAY_IN_SECONDS);
                    $moved++;
                }
                continue;
            }

            if ($age >= HOUR_IN_SECONDS && $deputy) {
                $key = 'sch_esc_dep_' . (int) $a->id;
                if (!get_transient($key)) {
                    SCH_Comms::notify($deputy, __('إنذار لم يُستلم', 'school-system'), (string) $a->title, 'alert', (int) $a->id);
                    set_transient($key, 1, 30 * DAY_IN_SECONDS);
                    $moved++;
                }
            }
        }

        return $moved;
    }

    public static function open(string $rule, string $severity, ?int $student_id, ?int $trip_id, string $title, string $detail = ''): bool
    {
        global $wpdb;

        // الإنذارات بلا طالب (مستوى المدرسة) لا يحميها القيد الفريد لأن MySQL
        // يسمح بتكرار NULL — فنفحص وجود إنذار مفتوح لنفس القاعدة اليوم يدويًا،
        // وإلا تكرّر «طلاب بلا رصد حضور» كل خمس دقائق طوال الصباح.
        if ($student_id === null) {
            $dupe = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . sch_table('alerts') . "
                 WHERE rule_code = %s AND day_key = %s AND status = 'open' AND student_id IS NULL",
                $rule,
                current_time('Y-m-d')
            ));

            if ($dupe > 0) {
                return false;
            }
        }

        $ok = $wpdb->insert(sch_table('alerts'), [
            'rule_code'  => $rule,
            'severity'   => array_key_exists($severity, self::SEVERITIES) ? $severity : 'medium',
            'student_id' => $student_id,
            'trip_id'    => $trip_id,
            'title'      => $title,
            'detail'     => $detail ?: null,
            'status'     => 'open',
            'day_key'    => current_time('Y-m-d'),
            'opened_at'  => sch_now(),
        ]);

        // القيد الفريد يمنع التكرار — الفشل هنا متوقع ولا يُعتبر خطأ.
        if ($ok === false) {
            return false;
        }

        sch_audit('alert.opened', 'student', $student_id, ['rule' => $rule, 'severity' => $severity]);

        return true;
    }

    /** إغلاق تلقائي حين يزول السبب. */
    public static function resolve(string $rule, int $student_id, string $day): void
    {
        global $wpdb;

        $wpdb->update(sch_table('alerts'), [
            'status'       => 'closed',
            'closed_at'    => sch_now(),
            'close_reason' => __('زال السبب تلقائيًا', 'school-system'),
        ], [
            'rule_code'  => $rule,
            'student_id' => $student_id,
            'day_key'    => $day,
            'status'     => 'open',
        ]);
    }

    public static function acknowledge(int $id): bool
    {
        global $wpdb;

        $wpdb->update(sch_table('alerts'), [
            'status' => 'ack',
            'ack_by' => get_current_user_id() ?: null,
            'ack_at' => sch_now(),
        ], ['id' => $id, 'status' => 'open']);

        sch_audit('alert.ack', 'alert', $id);

        return true;
    }

    /** الإغلاق يتطلب سببًا مكتوبًا — الإنذار الحرج لا يُغلق تلقائيًا أبدًا. */
    public static function close(int $id, string $reason): bool|WP_Error
    {
        global $wpdb;

        $reason = sanitize_text_field($reason);
        if (strlen($reason) < 3) {
            return sch_api_error('reason_required', __('اكتب سبب الإغلاق.', 'school-system'), 422);
        }

        $wpdb->update(sch_table('alerts'), [
            'status'       => 'closed',
            'closed_by'    => get_current_user_id() ?: null,
            'closed_at'    => sch_now(),
            'close_reason' => $reason,
        ], ['id' => $id]);

        sch_audit('alert.closed', 'alert', $id, ['reason' => $reason]);

        return true;
    }

    /** @return array<int,object> */
    public static function open_alerts(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, s.full_name FROM " . sch_table('alerts') . " a
             LEFT JOIN " . sch_table('students') . " s ON s.id = a.student_id
             WHERE a.status <> 'closed'
             ORDER BY FIELD(a.severity, 'critical', 'high', 'medium'), a.opened_at
             LIMIT %d",
            $limit
        )) ?: [];
    }

    public static function open_count(?string $severity = null): int
    {
        global $wpdb;

        if ($severity) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . sch_table('alerts') . "
                 WHERE status <> 'closed' AND severity = %s",
                $severity
            ));
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . sch_table('alerts') . " WHERE status <> 'closed'"
        );
    }

    /** هل يعمل المؤقت فعلًا؟ يُعرض للمدير. */
    public static function health(): array
    {
        $last = (string) get_option('sch_alerts_last_run', '');

        return [
            'last_run' => $last,
            'stale'    => $last === '' || strtotime($last) < strtotime(current_time('mysql')) - 1800,
            'real_cron'=> defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        ];
    }
}
