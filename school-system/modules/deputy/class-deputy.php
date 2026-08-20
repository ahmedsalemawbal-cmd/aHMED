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
        // الكتابة تمرّ من الكاتب الوحيد لجدول الحضور — فلا مصدرا حقيقة يتباعدان.
        $res = SCH_StaffAttendance::mark_manual($user_id, $status, $note);
        if (is_wp_error($res)) {
            return $res;
        }

        // الغياب يولّد حصصًا شاغرة — نقترح لها بدلاء فورًا.
        if (in_array($status, ['absent', 'leave'], true)) {
            self::propose_for_teacher($user_id, current_time('Y-m-d'), $status === 'leave' ? 'leave' : 'absence');
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

    /**
     * كشف اليوم كاملًا: كل موظف نشط، وحالته، ونصاب يومه، وحال تغطيته.
     *
     * `staff_sheet()` تخدم النداءات القديمة وتبقى كما هي. وهذه تضيف ما لا
     * تُقرأ الشاشة الجديدة بدونه: القسم ورقم البطاقة وطريقة الرصد (يدويّ
     * أم بالبوابة) وعدد حصص اليوم وكم منها بلا بديل — كلّه في استعلامٍ
     * واحد، لأن مدرسةً بمئة موظف كانت ستفتح أربعمئة استعلامٍ لو سُئل عن
     * كلٍّ على حدة.
     *
     * وحساب النقص `cover_open` من **الجدول** لا من جدول الاحتياط: معلمٌ
     * غاب قبل أن تُولَّد مقترحاته (أو غاب في نسخةٍ سابقة للميزة) حصصه
     * شاغرة فعلًا وإن لم يكن له صفٌّ واحد في `substitutions`.
     *
     * @return array<int,object>
     */
    public static function staff_day(string $date): array
    {
        global $wpdb;

        $day = self::day_of_week($date);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID AS user_id, u.display_name, u.user_email,
                    e.job_title, e.department, e.employee_no,
                    a.status, a.method, a.checked_at, a.checkout_at, a.minutes_late, a.note,
                    (SELECT COUNT(*) FROM " . sch_table('leaves') . " l
                      WHERE l.user_id = u.ID AND l.status = 'approved'
                        AND %s BETWEEN l.start_date AND l.end_date) AS on_leave,
                    (SELECT COUNT(*) FROM " . sch_table('timetable') . " t
                      WHERE t.teacher_user_id = u.ID AND t.day_of_week = %d) AS lessons,
                    (SELECT COUNT(*) FROM " . sch_table('substitutions') . " sb
                      WHERE sb.sub_date = %s AND sb.original_teacher_id = u.ID
                        AND sb.substitute_teacher_id IS NOT NULL) AS cover_done,
                    (SELECT v.display_name FROM " . sch_table('substitutions') . " sb2
                       INNER JOIN {$wpdb->users} v ON v.ID = sb2.substitute_teacher_id
                      WHERE sb2.sub_date = %s AND sb2.original_teacher_id = u.ID
                      ORDER BY sb2.period_no LIMIT 1) AS cover_by
             FROM " . sch_table('employees') . " e
             INNER JOIN {$wpdb->users} u ON u.ID = e.user_id
             LEFT JOIN " . sch_table('staff_attendance') . " a
                    ON a.user_id = u.ID AND a.att_date = %s
             WHERE e.status = 'active'
             ORDER BY u.display_name",
            $date,
            $day,
            $date,
            $date,
            $date
        )) ?: [];

        foreach ($rows as $row) {
            // الإجازة المعتمدة حالةٌ تسبق ما في جدول الحضور: من أذِنّا له
            // بالغياب ليس غائبًا ولو رُصد كذلك بإغلاقٍ آليّ.
            $row->state = (int) $row->on_leave > 0
                ? 'leave'
                : ((string) ($row->status ?? '') ?: 'none');

            $row->lessons    = (int) $row->lessons;
            $row->cover_done = (int) $row->cover_done;
            $row->needs      = in_array($row->state, ['absent', 'leave'], true) && $row->lessons > 0;
            $row->cover_open = $row->needs ? max(0, $row->lessons - $row->cover_done) : 0;
            $row->manual     = (string) ($row->method ?? '') === 'manual';
        }

        return $rows;
    }

    /** مجموع الحصص بلا بديل في الكشف — يُحسب من الكشف نفسه فلا يخالف عمودَه. */
    public static function open_cover_count(array $sheet): int
    {
        $n = 0;
        foreach ($sheet as $row) {
            $n += (int) ($row->cover_open ?? 0);
        }

        return $n;
    }

    /**
     * إغلاق كشف الموظفين: يرصد من لم يُرصد بعدُ، ثم يقترح بدلاء لحصصهم.
     *
     * من له إجازة معتمدة يُرصد «إجازة» لا «غياب» — الفرق بينهما هو الفرق
     * بين موظف تغيّب وموظف أذِنّا له، وهو يظهر في راتبه وتقريره.
     *
     * و`a.id IS NULL` مع `INSERT IGNORE` يجعلان التكرار آمنًا بالبناء:
     * لا قرارٌ قائم يُغيَّر، والضغطة الثانية تكتب صفرًا.
     *
     * @return int عدد من رُصد في هذه الضغطة
     */
    public static function close_staff_day(string $date): int
    {
        global $wpdb;

        if (!sch_sanitize_date($date) || $date > current_time('Y-m-d')) {
            return 0;
        }

        // ختم زمني واحد لهذه الدفعة — به وحده نعرف من كتبته هذه الضغطة.
        $now = sch_now();

        $written = $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . sch_table('staff_attendance') . '
                    (user_id, att_date, status, method, recorded_by, created_at)
             SELECT e.user_id, %s,
                    CASE WHEN EXISTS (
                             SELECT 1 FROM ' . sch_table('leaves') . ' l
                             WHERE l.user_id = e.user_id AND l.status = %s
                               AND %s BETWEEN l.start_date AND l.end_date
                         ) THEN %s ELSE %s END,
                    %s, NULLIF(%d, 0), %s
             FROM ' . sch_table('employees') . ' e
             LEFT JOIN ' . sch_table('staff_attendance') . ' a
                    ON a.user_id = e.user_id AND a.att_date = %s
             WHERE e.status = %s AND a.id IS NULL',
            $date,
            'approved',
            $date,
            'leave',
            'absent',
            'manual',
            get_current_user_id(),
            $now,
            $date,
            'active'
        ));

        $count = max(0, (int) $written);

        if ($count === 0) {
            return 0;
        }

        // الحصص الشاغرة تُقترح لمن رُصد الآن وحده — والاقتراح نفسه لا يتكرر.
        //
        // والحالة شرطٌ مع الختم الزمني لا زينة: الختم بدقّة الثانية، ومسحٌ
        // وقع في الثانية نفسها كان سيدخل الدفعة، فتُفتح حصصٌ شاغرة لمعلمٍ
        // **حاضر**. والغياب والإجازة وحدهما يتركان فصلًا بلا معلم.
        $fresh = $wpdb->get_results($wpdb->prepare(
            'SELECT user_id, status FROM ' . sch_table('staff_attendance') . '
             WHERE att_date = %s AND created_at = %s AND method = %s
               AND status IN (%s, %s)',
            $date,
            $now,
            'manual',
            'absent',
            'leave'
        )) ?: [];

        foreach ($fresh as $row) {
            self::propose_for_teacher(
                (int) $row->user_id,
                $date,
                (string) $row->status === 'leave' ? 'leave' : 'absence'
            );
        }

        sch_audit('staff_attendance.closed', 'staff_attendance', null, ['date' => $date, 'count' => $count]);

        return $count;
    }

    // ---------- الاحتياط المقترح ----------

    /** رقم اليوم في الجدول (١ الأحد … ٥ الخميس) — و٠ ليومَي العطلة. */
    public static function day_of_week(string $date): int
    {
        $weekday = (int) gmdate('w', strtotime($date));

        return $weekday === 0 ? 1 : ($weekday <= 4 ? $weekday + 1 : 0);
    }

    /** حصص معلم غائب في يوم — تُستخرَج من الجدول. */
    public static function vacant_slots(int $teacher_id, string $date): array
    {
        global $wpdb;

        $day = self::day_of_week($date);

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
     *
     * الاستثناءات أربعة، والثالث منها كان ناقصًا: من أُسند إليه احتياطٌ في
     * **هذه الحصة بالذات** كان يُرشَّح مرة أخرى، فمعلمان غائبان في الحصة
     * الثالثة يأخذان البديل نفسه — ويقف الوكيل أمام فصلٍ بلا أحد وهو يظن
     * أنه غطّاه. عدد احتياطاته اليوم كان يُحسَب للترتيب لا للمنع.
     *
     * والرابع بنيويّ لا اسميّ: البديل من أسندت إليه المدرسة مادةً أو
     * حصةً. الإدارة تُستثنى لأنها لا تُدرّس، لا لأن اسم قسمها «إدارة» —
     * والاسم نصّ حرّ يُكتب بألف طريقة.
     * والشرط مزدوج عمدًا: «له حصص في الجدول» وحده كان يُسقط **المعلم
     * الذي يومه فارغ** — وهو أفضل بديل ممكن — ويُسقط المعلم الجديد الذي
     * أُسندت إليه مواد ولم يُبنَ جدوله بعد.
     */
    public static function candidates(int $day, int $period, int $subject_id, string $date, int $limit = 3): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID AS user_id, u.display_name, e.department, e.job_title,
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
                     SELECT sb2.substitute_teacher_id FROM " . sch_table('substitutions') . " sb2
                     WHERE sb2.sub_date = %s AND sb2.period_no = %d
                       AND sb2.substitute_teacher_id IS NOT NULL
                   )
               AND u.ID NOT IN (
                     SELECT l.user_id FROM " . sch_table('leaves') . " l
                     WHERE l.status = 'approved' AND %s BETWEEN l.start_date AND l.end_date
                   )
               AND u.ID NOT IN (
                     SELECT a.user_id FROM " . sch_table('staff_attendance') . " a
                     WHERE a.att_date = %s AND a.status IN ('absent','leave')
                   )
               AND (
                     EXISTS (SELECT 1 FROM " . sch_table('class_subjects') . " cx
                             WHERE cx.teacher_user_id = u.ID)
                  OR EXISTS (SELECT 1 FROM " . sch_table('timetable') . " tx
                             WHERE tx.teacher_user_id = u.ID)
                   )
             ORDER BY same_subject DESC, today_subs ASC, load_count ASC, u.display_name
             LIMIT %d",
            $subject_id,
            $date,
            $day,
            $period,
            $date,
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

        $date = current_time('Y-m-d');
        $day  = self::day_of_week($date);

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

    // ---------- لوح تغطية معلم ----------

    /**
     * هل يغطّي نطاق المستخدم هذه الشعبة؟
     *
     * قاعدةٌ واحدة تحكم ما يُعرَض وما يُكتَب معًا: زرٌّ يظهر ثم يُرفض عند
     * الضغط أسوأ من زرٍّ لا يظهر. وهي نفس قاعدة شاشة الحصص الشاغرة —
     * فمن بلا نطاق محدَّد يبقى كما كان يعمل، ولا تُقفل عليه شاشة فجأة.
     */
    private static function may_cover(int $class_id): bool
    {
        $scope = SCH_Org::my_scope();

        return $scope === 'all' || $scope === 'none' || SCH_Org::may_touch_class($class_id);
    }

    /**
     * حصص معلم في يوم، ولكل حصة بديلها إن عُيّن أو مرشحوها إن لم يُعيَّن.
     *
     * المصدر هو **الجدول** والاحتياط ضمٌّ أيسر عليه، لا العكس: الحصة قائمة
     * لأنها مجدوَلة، لا لأن أحدًا أنشأ لها صفَّ اقتراح. فمعلمٌ غاب ولم
     * تُولَّد مقترحاته لسببٍ ما تظهر حصصه هنا كاملةً وتُغطّى.
     *
     * @return array{teacher:?object,rows:array<int,object>,open:int,total:int}
     */
    public static function coverage(int $teacher_id, string $date): array
    {
        global $wpdb;

        $teacher = $wpdb->get_row($wpdb->prepare(
            'SELECT u.ID AS user_id, u.display_name, e.job_title, e.department,
                    a.status, a.note
             FROM ' . sch_table('employees') . ' e
             INNER JOIN ' . $wpdb->users . ' u ON u.ID = e.user_id
             LEFT JOIN ' . sch_table('staff_attendance') . ' a
                    ON a.user_id = u.ID AND a.att_date = %s
             WHERE e.user_id = %d AND e.status = %s',
            $date,
            $teacher_id,
            'active'
        ));

        $day = self::day_of_week($date);

        if (!$teacher || $day === 0) {
            return ['teacher' => $teacher ?: null, 'rows' => [], 'open' => 0, 'total' => 0];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT t.class_id, t.period_no, t.subject_id,
                    c.stage, c.grade_level, c.section,
                    s.name AS subject_name,
                    sb.id AS sub_id, sb.substitute_teacher_id,
                    v.display_name AS substitute_name
             FROM ' . sch_table('timetable') . ' t
             INNER JOIN ' . sch_table('classes') . ' c ON c.id = t.class_id
             LEFT JOIN ' . sch_table('subjects') . ' s ON s.id = t.subject_id
             LEFT JOIN ' . sch_table('substitutions') . ' sb
                    ON sb.sub_date = %s AND sb.class_id = t.class_id AND sb.period_no = t.period_no
             LEFT JOIN ' . $wpdb->users . ' v ON v.ID = sb.substitute_teacher_id
             WHERE t.teacher_user_id = %d AND t.day_of_week = %d
             ORDER BY t.period_no',
            $date,
            $teacher_id,
            $day
        )) ?: [];

        // المشرف يغطّي نطاقه وحده — والوكيل والمدير يغطّيان الكل.
        $rows = array_values(array_filter(
            $rows,
            static fn (object $r): bool => self::may_cover((int) $r->class_id)
        ));

        $open = 0;

        foreach ($rows as $row) {
            $row->period_no  = (int) $row->period_no;
            $row->time       = SCH_Timetable::period_hhmm($row->period_no);
            $row->assigned   = (int) ($row->substitute_teacher_id ?? 0) > 0;
            $row->candidates = $row->assigned
                ? []
                : self::candidates($day, $row->period_no, (int) $row->subject_id, $date, 8);

            $open += $row->assigned ? 0 : 1;
        }

        return ['teacher' => $teacher, 'rows' => $rows, 'open' => $open, 'total' => count($rows)];
    }

    /**
     * تعيين بديل لحصة بعينها — تُعرَّف بالمعلم واليوم والشعبة ورقم الحصة.
     *
     * الحصة تُعرَّف بمكانها في الجدول لا برقم صفٍّ في جدول الاحتياط: الصف
     * قد لا يكون موجودًا بعد، وإنشاؤه هنا يجعل التغطية تعمل على أي معلم
     * غائب مهما كان طريق غيابه.
     */
    public static function cover_assign(int $teacher_id, string $date, int $class_id, int $period_no, int $sub_teacher_id): bool|WP_Error
    {
        global $wpdb;

        $day = self::day_of_week($date);

        if (!sch_sanitize_date($date) || $day === 0) {
            return sch_api_error('bad_date', __('لا حصص في هذا اليوم.', 'school-system'), 422);
        }

        // الحصة يجب أن تكون حصةَ هذا المعلم فعلًا — وإلا كان الطلب مُلفَّقًا.
        $slot = $wpdb->get_row($wpdb->prepare(
            'SELECT class_id, period_no, subject_id FROM ' . sch_table('timetable') . '
             WHERE teacher_user_id = %d AND day_of_week = %d AND class_id = %d AND period_no = %d',
            $teacher_id,
            $day,
            $class_id,
            $period_no
        ));

        if (!$slot) {
            return sch_api_error('not_found', __('هذه ليست حصة هذا المعلم اليوم.', 'school-system'), 404);
        }

        if (!self::may_cover($class_id)) {
            return sch_api_error('forbidden', __('هذه الشعبة خارج نطاقك.', 'school-system'), 403);
        }

        $sub_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('substitutions') . '
             WHERE sub_date = %s AND class_id = %d AND period_no = %d',
            $date,
            $class_id,
            $period_no
        ));

        if ($sub_id === 0) {
            $wpdb->insert(sch_table('substitutions'), [
                'sub_date'            => $date,
                'class_id'            => $class_id,
                'period_no'           => $period_no,
                'subject_id'          => (int) $slot->subject_id,
                'original_teacher_id' => $teacher_id,
                'reason'              => 'absence',
                'status'              => 'proposed',
                'created_at'          => sch_now(),
            ]);
            $sub_id = (int) $wpdb->insert_id;
        }

        if ($sub_id === 0) {
            return sch_api_error('db', __('تعذّر إنشاء سجل الحصة.', 'school-system') . ' ' . $wpdb->last_error, 500);
        }

        // الإسناد والإشعار والتدقيق من مكانٍ واحد — لا مسارَي تعيين يتباعدان.
        return self::assign($sub_id, $sub_teacher_id);
    }

    /** سحب بديل عن حصة — ويصل السحب إليه، فلا يأتي لفصلٍ لم يعد له. */
    public static function unassign(int $sub_id): bool|WP_Error
    {
        global $wpdb;

        $sub = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('substitutions') . ' WHERE id = %d',
            $sub_id
        ));

        if (!$sub) {
            return sch_api_error('not_found', __('الحصة غير موجودة.', 'school-system'), 404);
        }

        if (!self::may_cover((int) $sub->class_id)) {
            return sch_api_error('forbidden', __('هذه الشعبة خارج نطاقك.', 'school-system'), 403);
        }

        $was = (int) ($sub->substitute_teacher_id ?? 0);

        $wpdb->update(sch_table('substitutions'), [
            'substitute_teacher_id' => null,
            'status'                => 'proposed',
            'assigned_by'           => null,
        ], ['id' => $sub_id]);

        if ($was > 0) {
            $class = SCH_Classes::get((int) $sub->class_id);

            SCH_Comms::notify(
                $was,
                __('أُلغيت حصة احتياط', 'school-system'),
                sprintf(
                    /* translators: 1: رقم الحصة 2: الشعبة */
                    __('لم تعد الحصة %1$d — %2$s مسندة إليك اليوم.', 'school-system'),
                    (int) $sub->period_no,
                    $class ? SCH_Classes::label($class) : ''
                ),
                'substitution',
                $sub_id
            );
        }

        sch_audit('substitution.unassigned', 'substitution', $sub_id, ['was' => $was]);

        return true;
    }

    /**
     * تغطية كل حصص معلم بأفضل مرشّح لكلٍّ منها.
     *
     * المرشّحون يُسألون **داخل الحلقة** لا مرة واحدة قبلها: كل تعيين يشغل
     * بديلًا في تلك الحصة، والسؤال الجديد وحده يعرف ذلك.
     *
     * @return int عدد الحصص التي غُطّيت
     */
    public static function auto_assign(int $teacher_id, string $date): int
    {
        $day  = self::day_of_week($date);
        $done = 0;

        if ($day === 0) {
            return 0;
        }

        foreach (self::coverage($teacher_id, $date)['rows'] as $row) {
            if ($row->assigned) {
                continue;
            }

            $pick = self::candidates($day, (int) $row->period_no, (int) $row->subject_id, $date, 1);

            if ($pick === []) {
                continue;
            }

            $res = self::cover_assign(
                $teacher_id,
                $date,
                (int) $row->class_id,
                (int) $row->period_no,
                (int) $pick[0]->user_id
            );

            $done += is_wp_error($res) ? 0 : 1;
        }

        return $done;
    }

    /** تذكير البدلاء بحصص اليوم — إشعار الإسناد وصل وقتها، وهذا تذكيرٌ به. */
    public static function notify_subs(int $teacher_id, string $date): int
    {
        $sent = 0;

        foreach (self::coverage($teacher_id, $date)['rows'] as $row) {
            if (!$row->assigned) {
                continue;
            }

            SCH_Comms::notify(
                (int) $row->substitute_teacher_id,
                __('تذكير بحصة احتياط', 'school-system'),
                sprintf(
                    /* translators: 1: الوقت 2: المادة 3: الصف 4: الشعبة */
                    __('الحصة %1$s — %2$s · %3$s/%4$s اليوم.', 'school-system'),
                    $row->time,
                    (string) ($row->subject_name ?? ''),
                    (string) ($row->grade_level ?? ''),
                    (string) ($row->section ?? '')
                ),
                'substitution',
                (int) ($row->sub_id ?? 0) ?: null
            );

            $sent++;
        }

        return $sent;
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
