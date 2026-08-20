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
            'SELECT s.id, s.full_name, a.status, a.method, a.note, a.checked_in_at, a.minutes_late
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

    /**
     * كشف اليوم على مستوى المدرسة — كل طالب نشط ومعه حالته اليوم إن رُصدت.
     *
     * `sheet()` تخدم شعبة واحدة وتبقى كما هي: شاشات الشُّعب تعتمد شكل مخرجاتها.
     * وشاشة البوابة تحتاج المدرسة كلها في قائمة واحدة لأن الماسح لا يعرف شعبة
     * القادم إليه — فالتصفية بالشعبة قرار عرض لا قرار استعلام.
     *
     * الضمّ الأيسر على الحضور هو ما يجعل «بانتظار المسح» حالةً قائمة بذاتها:
     * `status` تعود NULL لمن لم يُرصد بعد، وهو تمييز لا يعطيه عدٌّ للمرصودين.
     *
     * والضمّ على التسجيل أيسر أيضًا عمدًا: الطالب النشط الذي لم يُسجَّل في شعبة
     * بعدُ يبقى في الكشف — إسقاطه يعني بطاقةً تُمسح على البوابة ولا تجد صاحبها.
     * والشعبة تُقرأ من تسجيل السنة الحالية، وتسقط على نسخة سجل الطالب المبسّطة
     * إن لم يوجد تسجيل.
     *
     * @return array<int,object> صف لكل طالب: id · full_name · first_name ·
     *                          academic_no · student_no · photo_file · stage ·
     *                          grade_level · section · class_id · status ·
     *                          method · note · checked_in_at · minutes_late
     */
    public static function day_sheet(string $date): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.id, s.full_name, s.first_name, s.academic_no, s.student_no, s.photo_file,
                    COALESCE(c.stage, s.stage)             AS stage,
                    COALESCE(c.grade_level, s.grade_level) AS grade_level,
                    COALESCE(c.section, s.section)         AS section,
                    c.id AS class_id,
                    a.status, a.method, a.note, a.checked_in_at, a.minutes_late
             FROM ' . sch_table('students') . ' s
             LEFT JOIN ' . sch_table('enrollments') . ' e
                    ON e.student_id = s.id AND e.year_id = %d AND e.status = %s
             LEFT JOIN ' . sch_table('classes') . ' c ON c.id = e.class_id
             LEFT JOIN ' . sch_table('attendance') . ' a
                    ON a.student_id = s.id AND a.att_date = %s
             WHERE s.status = %s
             ORDER BY s.full_name',
            SCH_Years::current_id(),
            'active',
            $date,
            'active'
        )) ?: [];
    }

    /**
     * إغلاق كشف اليوم: يرصد غيابًا لكل من لم يُرصد بعدُ، ويُرجع عدد من رُصد.
     *
     * الأمان عند التكرار مبنيّ لا مفحوص: الشرط `a.id IS NULL` يستثني من له سجل
     * أصلًا فلا يُغيَّر قرار قائم (حاضر أو متأخر أو غياب بعذر من إجازة معتمدة)،
     * و`INSERT IGNORE` مع المفتاح الفريد (student_id, att_date) يحسم السباق بين
     * ضغطتين متزامنتين — فالضغطة الثانية تكتب صفرًا وتُرجع صفرًا.
     *
     * وإدراج واحد لكل الغائبين لا نداء `mark()` لكل طالب: مدرسة بأربعمئة طالب
     * كانت ستفتح أربعمئة استعلام قبل أن يعود الزرّ.
     *
     * @return int عدد من رُصد غيابه في هذه الضغطة (0 إن لم يبقَ أحد أو كان التاريخ غير صالح)
     */
    public static function close_day(string $date): int
    {
        global $wpdb;

        // تاريخ لم يأتِ بعد لا غياب فيه — والإغلاق فعل لا رجعة فيه على كشف كامل.
        if (!sch_sanitize_date($date) || $date > current_time('Y-m-d')) {
            return 0;
        }

        // ختم زمني واحد لكل هذه الدفعة — به وحده نميّز من كتبته هذه الضغطة.
        $now = sch_now();

        $written = $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . sch_table('attendance') . '
                    (student_id, class_id, att_date, status, method, recorded_by, recorded_at)
             SELECT s.id, e.class_id, %s, %s, %s, NULLIF(%d, 0), %s
             FROM ' . sch_table('students') . ' s
             LEFT JOIN ' . sch_table('enrollments') . ' e
                    ON e.student_id = s.id AND e.year_id = %d AND e.status = %s
             LEFT JOIN ' . sch_table('attendance') . ' a
                    ON a.student_id = s.id AND a.att_date = %s
             WHERE s.status = %s AND a.id IS NULL',
            $date,
            'absent',
            'manual',
            get_current_user_id(),
            $now,
            SCH_Years::current_id(),
            'active',
            $date,
            'active'
        ));

        $count = max(0, (int) $written);

        if ($count === 0) {
            return 0;
        }

        // الإبلاغ يقع على من كتبته هذه الضغطة وحدها: الختم الزمني يفرزهم عمّن
        // رُصد غيابه يدويًا قبل قليل، فلا يصل أهله إشعار ثانٍ عن الغياب نفسه.
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT student_id FROM ' . sch_table('attendance') . '
             WHERE att_date = %s AND status = %s AND method = %s AND recorded_at = %s',
            $date,
            'absent',
            'manual',
            $now
        )) ?: [];

        foreach ($ids as $id) {
            self::notify_absence((int) $id, $date);
        }

        sch_audit('attendance.closed', 'attendance', null, ['date' => $date, 'count' => $count]);

        return $count;
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

    /**
     * شريط آخر N يومًا لطالب — خليّة لكل يوم تقويميّ بالترتيب (الأقدم أولًا).
     *
     * اليوم الذي لا سجلّ له ليس غيابًا: قد يكون عطلة أو يومًا لم يُرصد بعد.
     * يُعطى مفتاح `off` وحده، ولا يدخل حساب الغياب — وإلّا بدت إجازة نهاية
     * الأسبوع في ملفّ الطفل كأنها تغيّب.
     *
     * @return array{days:array<int,array{date:string,key:string,label:string}>,counts:array<string,int>}
     */
    public static function last_days(int $student_id, int $days = 30): array
    {
        global $wpdb;

        $days  = max(1, min(120, $days));
        $start = gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' -' . ($days - 1) . ' days'));

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT att_date, status FROM ' . sch_table('attendance') . '
             WHERE student_id = %d AND att_date >= %s',
            $student_id,
            $start
        )) ?: [];

        $by_date = [];
        foreach ($rows as $r) {
            $by_date[(string) $r->att_date] = (string) $r->status;
        }

        $labels = self::STATUSES + ['off' => __('لا دوام', 'school-system')];
        $out    = [];
        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'off' => 0];

        for ($i = 0; $i < $days; $i++) {
            $date = gmdate('Y-m-d', strtotime($start . ' +' . $i . ' days'));
            $key  = $by_date[$date] ?? 'off';
            if (!isset($counts[$key])) {
                $key = 'off';
            }
            $counts[$key]++;
            $out[] = ['date' => $date, 'key' => $key, 'label' => (string) $labels[$key]];
        }

        return ['days' => $out, 'counts' => $counts];
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
        'user'          => 'شخص واحد',
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
     *
     * القناتان مستقلّتان: من له جهاز مشترك يصله الإشعار الفوري، ومن له بريد
     * يصله البريد، **ومن له الاثنان يصله الاثنان**. الرسالة التي تخصّ طفلًا
     * لا يُقامَر على وصولها بقناة واحدة.
     */
    private static function queue_delivery(int $notification_id, int $user_id): void
    {
        global $wpdb;

        if ($notification_id <= 0) {
            return;
        }

        $channels = [];
        $user     = get_userdata($user_id);

        if (sch_settings('notify_email', true) && $user && is_email((string) $user->user_email)) {
            $channels[] = 'email';
        }

        if (sch_settings('notify_push', true) && SCH_Push::ready() && SCH_Push::has_sub($user_id)) {
            $channels[] = 'push';
        }

        foreach ($channels as $channel) {
            $wpdb->insert(sch_table('deliveries'), [
                'notification_id' => $notification_id,
                'user_id'         => $user_id,
                'channel'         => $channel,
                'status'          => 'queued',
                'attempts'        => 0,
                'created_at'      => sch_now(),
                'updated_at'      => sch_now(),
            ]);
        }
    }

    /**
     * معالج الطابور — يُشغَّل بالكرون: يرسل دفعة عبر القناة ويعيد المحاولة حتى
     * ثلاثًا ثم يعلّمها فاشلة. قناة SMS تُوصَّل هنا لاحقًا بمفاتيحها.
     */
    public static function process_deliveries(int $limit = 30): int
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.user_id, d.channel, d.attempts, n.title, n.body, n.ref_type, n.ref_id
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

            if ($d->channel === 'push') {
                // الضغطة تفتح شاشة الشخص نفسه — ولي الأمر لا يُرمى على شاشة موظف.
                $ok = SCH_Push::to_user((int) $d->user_id, [
                    'title' => (string) $d->title,
                    'body'  => (string) ($d->body ?: ''),
                    'url'   => SCH_Portal::home_for((int) $d->user_id),
                    'tag'   => 'sch-' . (string) ($d->ref_type ?: 'n'),
                ]) > 0;
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

            // شخص واحد — من زرّ «مراسلة» في ملفّ الطالب. المستخدم يُتحقَّق من
            // وجوده هنا لا في القالب: الرقم يأتي من الرابط.
            'user' => $audience_id && get_userdata($audience_id) ? [(int) $audience_id] : [],

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
