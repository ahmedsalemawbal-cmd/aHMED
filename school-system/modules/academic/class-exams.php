<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الاختبارات — دورةٌ وجدولٌ ودرجات.
 *
 * الشاشة القديمة كانت تُنشئ «اختبارًا» بعنوانٍ حرّ ودرجةٍ عظمى ووزن، وتفتح
 * له كشفًا بخانةٍ واحدة. وهي تعمل، لكنها لا تعرف ما تعرفه المدرسة: أن
 * الاختبارات تأتي **دورات** (نهائي · نصف الفصل · شهري)، وأن لكل دورةٍ
 * جدولًا بقاعةٍ ومراقب، وأن الدرجة **مركّبة** من أعمال سنةٍ واختبارٍ نهائيّ
 * بوزنين، وأنها لا تصل إلى وليّ الأمر قبل أن تعتمدها الإدارة.
 *
 * **ولا جدول قاعدةٍ ثالث.** `exams` و`exam_results` تقرأ منهما سبع جهات —
 * الشهادات وملفّ الطالب والروضة ومركز القيادة وتطبيق وليّ الأمر وREST —
 * وجدولٌ موازٍ يعني حقيقتين. فالنموذج يُسقَط عليهما:
 *
 *   درجاتُ مادةٍ في شعبةٍ في دورة = **صفّان في `exams`**
 *     ① `participation` باسم «أعمال السنة»  · وزنه = وزن أعمال السنة
 *     ② المرصود (`final`/`midterm`/`quiz`)  · وزنه = ١٠٠ − الأوّل
 *
 * ومجموع الوزنين مئة، فمتوسّط `SCH_Assessment::report_card()` المرجّح
 * `(pct×w)/Σw` يُخرج بالضبط «أعمال السنة + النهائي» بلا تعديلٍ في حسابه.
 * والصفّ ② وحده يحمل الموعد والقاعة والمراقب.
 */
final class SCH_Exams
{
    /** الدورات — المفتاح ثابت والنصّ يُترجَم. */
    public const SESSIONS = [
        'final'   => 'الاختبارات النهائية',
        'mid'     => 'اختبارات نصف الفصل',
        'monthly' => 'الاختبارات الشهرية',
    ];

    /**
     * نوع الصفّ المرصود لكل دورة.
     *
     * يبقى داخل `ENUM` العمود القائم — فلا ترحيل للنوع، ولا تنكسر شاشةٌ
     * قديمة تقرأ `EXAM_TYPES`.
     */
    private const GRADED_TYPE = [
        'final'   => 'final',
        'mid'     => 'midterm',
        'monthly' => 'quiz',
    ];

    /** صفّ أعمال السنة — نصف الدرجة الذي لا موعد له. */
    private const YEAR_TYPE = 'participation';

    private const YEAR_TITLE  = 'أعمال السنة';
    private const FINAL_TITLE = 'الاختبار';

    /** القاعات حين لا تُضبَط في الإعدادات. */
    private const DEFAULT_ROOMS = ['قاعة 1', 'قاعة 2', 'قاعة 3', 'المعمل'];

    /** وزن أعمال السنة الافتراضيّ، وحدّاه كما في التصميم. */
    private const YEAR_WEIGHT     = 40;
    private const YEAR_WEIGHT_MIN = 10;
    private const YEAR_WEIGHT_MAX = 60;

    /** حدّ النجاح من مئة. */
    public const PASS_MARK = 50;

    /** أبعد ما يمشيه باحث التواريخ قبل أن يستسلم — قناعٌ بيومٍ واحد يحتاج طولًا. */
    private const DATE_SCAN_LIMIT = 120;

    // ═══════════════════════════ الدورة ونافذتها ═══════════════════════════

    /** القاعات المتاحة — من الإعدادات، وإلا فالافتراض. */
    public static function rooms(): array
    {
        $raw = (string) sch_settings('exam_rooms', '');

        if (trim($raw) === '') {
            return self::DEFAULT_ROOMS;
        }

        $out = [];
        foreach (preg_split('/[\r\n,،]+/', $raw) ?: [] as $one) {
            $one = trim(sanitize_text_field($one));
            if ($one !== '') {
                $out[] = $one;
            }
        }

        return $out !== [] ? $out : self::DEFAULT_ROOMS;
    }

    /**
     * أوّل يومٍ في نافذة الدورة.
     *
     * الشهريّ من منتصف شهره — والشهر يُحلّ إلى سنته من `SCH_TT::months()`،
     * فدورةُ يناير تقع في السنة الميلادية التالية لا في التي بدأت فيها
     * السنة الدراسية. والنهائيّ ونصفُ الفصل من الإعدادات، وإلا فمن مدى
     * السنة نفسها: **لا تاريخَ مكتوبًا في الشفرة**.
     */
    public static function session_start(string $session, ?int $month = null): string
    {
        $months = SCH_TT::months();
        $keys   = array_keys($months);

        if ($session === 'monthly') {
            $want = max(1, min(12, (int) $month));

            foreach ($keys as $ym) {
                if ((int) substr($ym, 5, 2) === $want) {
                    return $ym . '-15';
                }
            }

            return ($keys[0] ?? gmdate('Y-m')) . '-15';
        }

        $set = sch_sanitize_date(sch_settings($session === 'mid' ? 'exam_start_mid' : 'exam_start_final', ''));
        if ($set !== null) {
            return $set;
        }

        // نصف الفصل في ثالث شهرٍ من السنة، والنهائيّ في آخرها.
        $ym = $session === 'mid'
            ? ($keys[2] ?? ($keys[0] ?? gmdate('Y-m')))
            : ($keys[count($keys) - 2] ?? ($keys[0] ?? gmdate('Y-m')));

        return $ym . '-13';
    }

    /**
     * أيام الدورة — يومٌ دراسيّ لكل مادة، متتاليةً من بدايتها.
     *
     * والأيام الدراسية **من قناع المرحلة** (`SCH_TT::days`) لا بتثبيت الجمعة
     * والسبت: مدرسةٌ تداوم السبت كانت تُقفَز عليه فيُدفَع اختبارها أسبوعًا،
     * والقناع يعرفه منذ بُني محرّك الجداول.
     *
     * @return array<int,array{date:string,weekday:string,label:string}>
     */
    public static function days_for(int $class_id, string $session, ?int $month = null): array
    {
        $class = SCH_Classes::get($class_id);
        $stage = $class ? (string) $class->stage : 'primary';
        $open  = SCH_TT::days($stage);
        $need  = count(self::subjects_of($class_id));

        if ($need === 0 || $open === []) {
            return [];
        }

        $out  = [];
        $cur  = (int) strtotime(self::session_start($session, $month) . ' 12:00:00 UTC');
        $step = 0;

        while (count($out) < $need && $step++ < self::DATE_SCAN_LIMIT) {
            $date = gmdate('Y-m-d', $cur);
            $dow  = SCH_Timetable::school_day($date);

            if (isset($open[$dow])) {
                $out[] = [
                    'date'    => $date,
                    'weekday' => (string) $open[$dow],
                    'label'   => (string) mysql2date('j F', $date),
                ];
            }

            $cur += DAY_IN_SECONDS;
        }

        return $out;
    }

    /** «من ١٣ ديسمبر إلى ٢١ ديسمبر» — نافذة الدورة كما تُقرأ. */
    public static function window_label(int $class_id, string $session, ?int $month = null): string
    {
        $days = self::days_for($class_id, $session, $month);

        if ($days === []) {
            return '';
        }

        return sprintf(
            /* translators: 1: أول يوم 2: آخر يوم */
            __('من %1$s إلى %2$s', 'school-system'),
            $days[0]['label'],
            $days[count($days) - 1]['label']
        );
    }

    /** اسم الدورة كما يُطبع في الترويسة. */
    public static function session_label(string $session, ?int $month = null): string
    {
        $base = self::SESSIONS[$session] ?? $session;

        if ($session !== 'monthly') {
            return $base;
        }

        foreach (array_keys(SCH_TT::months()) as $ym) {
            if ((int) substr($ym, 5, 2) === (int) $month) {
                return $base . ' — ' . mysql2date('F', $ym . '-01');
            }
        }

        return $base;
    }

    // ═══════════════════════════ مواد الشعبة ═══════════════════════════

    /**
     * مواد الشعبة ومعلّموها.
     *
     * المصدر `class_subjects` — وهو ما صار يُبنى مع نشر جدول الحصص، فمعلّم
     * المادة معروفٌ بلا إدخالٍ ثانٍ. ومدرسةٌ لم تنشر جدولها بعد تأخذ مواد
     * مرحلتها بلا معلّم، فلا تقف الشاشة عند صفرٍ لا سبيل لتجاوزه.
     *
     * @return array<int,object>
     */
    public static function subjects_of(int $class_id): array
    {
        global $wpdb;

        static $memo = [];

        if (isset($memo[$class_id])) {
            return $memo[$class_id];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT s.id, s.name, cs.teacher_user_id, u.display_name AS teacher_name
             FROM ' . sch_table('class_subjects') . ' cs
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = cs.subject_id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = cs.teacher_user_id
             WHERE cs.class_id = %d
             ORDER BY s.name',
            $class_id
        )) ?: [];

        if ($rows === []) {
            $class = SCH_Classes::get($class_id);

            foreach (SCH_Subjects::all($class ? (string) $class->stage : null) as $s) {
                $rows[] = (object) [
                    'id'              => (int) $s->id,
                    'name'            => (string) $s->name,
                    'teacher_user_id' => null,
                    'teacher_name'    => null,
                ];
            }
        }

        return $memo[$class_id] = $rows;
    }

    // ═══════════════════════════ الجدول ═══════════════════════════

    /**
     * خانات الدورة لشعبة — الصفّ المرصود وحده، مفهرسًا بالمادة.
     *
     * @return array<int,object>
     */
    public static function schedule(int $class_id, string $session, ?int $month = null): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT e.*, s.name AS subject_name, u.display_name AS proctor_name
             FROM ' . sch_table('exams') . ' e
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = e.subject_id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = e.proctor_user_id
             WHERE e.class_id = %d AND e.session = %s
               AND ' . self::month_sql(self::month_of($session, $month), 'e') . '
               AND e.exam_type <> %s',
            $class_id,
            $session,
            self::YEAR_TYPE
        )) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->subject_id] = $r;
        }

        return $out;
    }

    /**
     * التعارضات — القاعة أو المراقب محجوزان في التاريخ نفسه **في شعبةٍ أخرى**.
     *
     * والقيد على «شعبةٍ أخرى» مقصود: قاعةٌ واحدة لشعبةٍ واحدة في يومين
     * مختلفين ليست تعارضًا، ومعلّمٌ يراقب شعبته مرّتين في يومين كذلك.
     *
     * @return array{room:int,proctor:int,missing:int,cells:array<int,string>}
     */
    public static function conflicts(int $class_id, string $session, ?int $month = null): array
    {
        global $wpdb;

        $placed = self::schedule($class_id, $session, $month);
        $t      = sch_table('exams');

        $room    = 0;
        $proctor = 0;
        $cells   = [];

        foreach ($placed as $sid => $e) {
            if ((string) $e->exam_date === '') {
                continue;
            }

            $clash_room = $e->room !== null && $e->room !== ''
                ? (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t}
                      WHERE class_id <> %d AND exam_date = %s AND room = %s AND exam_type <> %s",
                    $class_id,
                    $e->exam_date,
                    $e->room,
                    self::YEAR_TYPE
                ))
                : 0;

            $clash_proctor = (int) $e->proctor_user_id > 0
                ? (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t}
                      WHERE class_id <> %d AND exam_date = %s AND proctor_user_id = %d AND exam_type <> %s",
                    $class_id,
                    $e->exam_date,
                    (int) $e->proctor_user_id,
                    self::YEAR_TYPE
                ))
                : 0;

            if ($clash_room > 0) {
                $room++;
            }
            if ($clash_proctor > 0) {
                $proctor++;
            }
            if ($clash_room > 0 || $clash_proctor > 0) {
                $cells[(int) $sid] = ($clash_room > 0 ? 'room' : '') . ($clash_proctor > 0 ? 'proctor' : '');
            }
        }

        return [
            'room'    => $room,
            'proctor' => $proctor,
            'missing' => max(0, count(self::subjects_of($class_id)) - count($placed)),
            'cells'   => $cells,
        ];
    }

    /**
     * وضعُ مادةٍ في يوم.
     *
     * ويومٌ واحد لمادةٍ واحدة: الوضع على يومٍ مشغول يُزيح ساكنه بدل أن يُكدّس
     * اختبارين على طالبٍ واحد في صباحٍ واحد.
     */
    public static function place(int $class_id, int $subject_id, string $session, ?int $month, string $date): bool|WP_Error
    {
        global $wpdb;

        $guard = self::guard($class_id, $session);
        if (is_wp_error($guard)) {
            return $guard;
        }

        if (!SCH_Subjects::get($subject_id)) {
            return sch_api_error('not_found', __('المادة غير موجودة.', 'school-system'), 404);
        }

        $day = sch_sanitize_date($date);
        if ($day === null) {
            return sch_api_error('bad_date', __('التاريخ غير صحيح.', 'school-system'), 422);
        }

        $valid = false;
        foreach (self::days_for($class_id, $session, $month) as $d) {
            if ($d['date'] === $day) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            return sch_api_error('outside_window', __('هذا اليوم خارج نافذة الدورة.', 'school-system'), 422);
        }

        $mo   = self::month_of($session, $month);
        $subs = self::subjects_of($class_id);
        $mine = null;

        foreach ($subs as $s) {
            if ((int) $s->id === $subject_id) {
                $mine = $s;
                break;
            }
        }

        $rooms = self::rooms();
        $index = 0;
        foreach ($subs as $i => $s) {
            if ((int) $s->id === $subject_id) {
                $index = (int) $i;
                break;
            }
        }

        $wpdb->query('START TRANSACTION');

        // ساكن اليوم يُرفع أوّلًا — بلا هذا يقع اختباران في صباحٍ واحد.
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('exams') . '
                SET exam_date = NULL, updated_at = %s
              WHERE class_id = %d AND session = %s AND ' . self::month_sql($mo) . '
                AND exam_type <> %s AND exam_date = %s AND subject_id <> %d',
            sch_now(),
            $class_id,
            $session,
            self::YEAR_TYPE,
            $day,
            $subject_id
        ));

        $pair = self::ensure_pair($class_id, $subject_id, $session, $month);

        $wpdb->update(
            sch_table('exams'),
            [
                'exam_date'       => $day,
                'room'            => $rooms[$index % count($rooms)],
                'proctor_user_id' => $mine && $mine->teacher_user_id ? (int) $mine->teacher_user_id : null,
                'updated_at'      => sch_now(),
            ],
            ['id' => $pair['graded']]
        );

        $wpdb->query('COMMIT');

        sch_audit('exam.placed', 'exam', $pair['graded'], ['date' => $day, 'session' => $session]);

        return true;
    }

    /** رفعُ مادةٍ من يومها — ودرجاتها لا تُمَسّ. */
    public static function clear(int $class_id, int $subject_id, string $session, ?int $month = null): bool|WP_Error
    {
        global $wpdb;

        $guard = self::guard($class_id, $session);
        if (is_wp_error($guard)) {
            return $guard;
        }

        $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('exams') . '
                SET exam_date = NULL, room = NULL, updated_at = %s
              WHERE class_id = %d AND subject_id = %d AND session = %s
                AND ' . self::month_sql(self::month_of($session, $month)) . ' AND exam_type <> %s',
            sch_now(),
            $class_id,
            $subject_id,
            $session,
            self::YEAR_TYPE
        ));

        return true;
    }

    /** القاعة أو المراقب — حقلٌ واحد يُبدَّل. */
    public static function set_slot_field(
        int $class_id,
        int $subject_id,
        string $session,
        ?int $month,
        string $field,
        string $value
    ): bool|WP_Error {
        global $wpdb;

        $guard = self::guard($class_id, $session);
        if (is_wp_error($guard)) {
            return $guard;
        }

        /*
         * القيمة تُبنى في الجملة لا تُمرَّر وسيطًا.
         *
         * `prepare` تكتب `null` نصًّا فارغًا: `proctor_user_id = ''` على عمود
         * `BIGINT` تُقبَل صفرًا في وضعٍ متساهل **وتُرفَض في الصارم** — و«بلا
         * مراقب» حالةٌ مشروعة تقع كل يوم. فالقيمة إمّا `NULL` صريحة، وإمّا
         * وسيطٌ من النوع الصحيح.
         */
        if ($field === 'room') {
            if ($value !== '' && !in_array($value, self::rooms(), true)) {
                return sch_api_error('bad_room', __('القاعة غير معروفة.', 'school-system'), 422);
            }

            $col = 'room';
            $set = $value !== '' ? $wpdb->prepare('%s', $value) : 'NULL';
        } elseif ($field === 'proctor') {
            $uid = absint($value);

            if ($uid > 0 && !get_userdata($uid)) {
                return sch_api_error('no_teacher', __('المراقب غير موجود.', 'school-system'), 404);
            }

            $col = 'proctor_user_id';
            $set = $uid > 0 ? (string) $uid : 'NULL';
        } else {
            return sch_api_error('bad_field', __('حقل غير معروف.', 'school-system'), 422);
        }

        $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('exams') . "
                SET `{$col}` = {$set}, updated_at = %s
              WHERE class_id = %d AND subject_id = %d AND session = %s
                AND " . self::month_sql(self::month_of($session, $month)) . ' AND exam_type = %s',
            sch_now(),
            $class_id,
            $subject_id,
            $session,
            self::GRADED_TYPE[$session] ?? 'quiz'
        ));

        return true;
    }

    /**
     * حفظ الجدول — وهو ما يجعله يظهر لوليّ الأمر والطالب.
     *
     * وقبله لا يرى أحدٌ نصفَ جدولٍ يُبنى: `upcoming_exams()` تشترط
     * `published_at`. والحفظ ثانيةً **يُحدّث ولا يُكرّر** — الصفوف هي هي،
     * والتحديث يمسّ `published_at` وحده.
     */
    public static function save_schedule(int $class_id, string $session, ?int $month = null): array|WP_Error
    {
        global $wpdb;

        $guard = self::guard($class_id, $session);
        if (is_wp_error($guard)) {
            return $guard;
        }

        $placed = array_filter(
            self::schedule($class_id, $session, $month),
            static fn (object $e): bool => (string) $e->exam_date !== ''
        );

        if ($placed === []) {
            return sch_api_error('empty', __('لا مادة لها موعد بعد — ضع المواد على الأيام أولًا.', 'school-system'), 422);
        }

        $existed = false;
        foreach ($placed as $e) {
            if ($e->published_at !== null) {
                $existed = true;
                break;
            }
        }

        $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('exams') . '
                SET published_at = %s, updated_at = %s
              WHERE class_id = %d AND session = %s
                AND ' . self::month_sql(self::month_of($session, $month)) . '
                AND exam_type <> %s AND exam_date IS NOT NULL',
            sch_now(),
            sch_now(),
            $class_id,
            $session,
            self::YEAR_TYPE
        ));

        sch_audit('exam.schedule_saved', 'class', $class_id, [
            'session' => $session,
            'month'   => $month,
            'count'   => count($placed),
        ]);

        return ['count' => count($placed), 'updated' => $existed];
    }

    /**
     * «الجداول المُنشأة» — **مشتقّةٌ بالضمّ لا مخزّنةٌ في جدولٍ ثالث**.
     *
     * سجلٌّ منفصل لما حُفظ يفترق عن الواقع عند أوّل حذفٍ لشعبة أو تعديلٍ
     * لموعد. والضمّ يقول ما في القاعدة الآن، لا ما كان يومَ الحفظ.
     *
     * @return array<int,object>
     */
    public static function saved_list(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT e.class_id, e.session, e.session_month,
                    COUNT(*) AS n, MAX(e.published_at) AS at,
                    c.stage, c.grade_level, c.section
             FROM ' . sch_table('exams') . ' e
             INNER JOIN ' . sch_table('classes') . ' c ON c.id = e.class_id
             WHERE e.published_at IS NOT NULL AND e.session <> %s
               AND e.exam_type <> %s AND c.year_id = %d
             GROUP BY e.class_id, e.session, e.session_month
             ORDER BY at DESC
             LIMIT 40',
            'legacy',
            self::YEAR_TYPE,
            SCH_Years::current_id()
        )) ?: [];

        foreach ($rows as $r) {
            $health = self::conflicts((int) $r->class_id, (string) $r->session, $r->session_month === null ? null : (int) $r->session_month);

            $r->conflicts = $health['room'] + $health['proctor'];
            $r->label     = $r->grade_level . ' / ' . $r->section . ' — '
                          . self::session_label((string) $r->session, $r->session_month === null ? null : (int) $r->session_month);
        }

        return $rows;
    }

    // ═══════════════════════════ الدرجات ═══════════════════════════

    /**
     * وزنا المادة — أعمال السنة والنهائيّ، ومجموعهما مئةٌ دائمًا.
     *
     * @return array{year:int,final:int}
     */
    public static function weights(int $class_id, int $subject_id, string $session, ?int $month = null): array
    {
        global $wpdb;

        $year = $wpdb->get_var($wpdb->prepare(
            'SELECT max_score FROM ' . sch_table('exams') . '
              WHERE class_id = %d AND subject_id = %d AND session = %s
                AND ' . self::month_sql(self::month_of($session, $month)) . ' AND exam_type = %s',
            $class_id,
            $subject_id,
            $session,
            self::YEAR_TYPE
        ));

        $y = $year === null ? self::YEAR_WEIGHT : (int) round((float) $year);
        $y = max(self::YEAR_WEIGHT_MIN, min(self::YEAR_WEIGHT_MAX, $y));

        return ['year' => $y, 'final' => 100 - $y];
    }

    /** تبديل وزن أعمال السنة — والنهائيّ يتبعه، فلا يخرج المجموع عن مئة. */
    public static function set_weight(int $class_id, int $subject_id, string $session, ?int $month, int $year_weight): bool|WP_Error
    {
        global $wpdb;

        $guard = self::guard_grades($class_id, $subject_id, $session);
        if (is_wp_error($guard)) {
            return $guard;
        }

        $y = max(self::YEAR_WEIGHT_MIN, min(self::YEAR_WEIGHT_MAX, $year_weight));
        $pair = self::ensure_pair($class_id, $subject_id, $session, $month);

        $wpdb->update(sch_table('exams'), ['max_score' => $y, 'weight' => $y, 'updated_at' => sch_now()], ['id' => $pair['year']]);
        $wpdb->update(sch_table('exams'), ['max_score' => 100 - $y, 'weight' => 100 - $y, 'updated_at' => sch_now()], ['id' => $pair['graded']]);

        return true;
    }

    /**
     * كشف الشعبة — كل طالب ودرجتاه وحالته.
     *
     * @return array<int,object>
     */
    public static function roster(int $class_id, int $subject_id, string $session, ?int $month = null): array
    {
        global $wpdb;

        $pair = self::pair_ids($class_id, $subject_id, $session, $month);

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.id, s.full_name, s.academic_no,
                    ry.score AS year_score, rf.score AS final_score,
                    GREATEST(COALESCE(ry.excused, 0), COALESCE(rf.excused, 0)) AS excused
             FROM ' . sch_table('enrollments') . ' e
             INNER JOIN ' . sch_table('students') . ' s ON s.id = e.student_id
             LEFT JOIN ' . sch_table('exam_results') . ' ry ON ry.student_id = s.id AND ry.exam_id = %d
             LEFT JOIN ' . sch_table('exam_results') . ' rf ON rf.student_id = s.id AND rf.exam_id = %d
             WHERE e.class_id = %d AND e.status = %s AND s.status = %s
             ORDER BY s.full_name',
            $pair['year'],
            $pair['graded'],
            $class_id,
            'active',
            'active'
        )) ?: [];
    }

    /**
     * درجةٌ واحدة — «أعمال السنة» أو «النهائي».
     *
     * والحدّ العلويّ وزنُ نصفها لا مئة: خانةٌ تقبل ٩٠ في نصفٍ وزنُه ٤٠
     * تُخرج مجموعًا فوق المئة، ولا يُكتشف إلا عند مراجعة الشهادات.
     */
    public static function save_score(
        int $class_id,
        int $subject_id,
        string $session,
        ?int $month,
        int $student_id,
        string $part,
        string $raw
    ): bool|WP_Error {
        global $wpdb;

        $guard = self::guard_grades($class_id, $subject_id, $session);
        if (is_wp_error($guard)) {
            return $guard;
        }

        if (!in_array($part, ['year', 'graded'], true)) {
            return sch_api_error('bad_part', __('نصف الدرجة غير معروف.', 'school-system'), 422);
        }

        if (self::locked($class_id, $subject_id, $session, $month)) {
            return sch_api_error('locked', __('الدرجات مُرسَلة للاعتماد — لا تُعدَّل حتى تُعاد.', 'school-system'), 409);
        }

        $w    = self::weights($class_id, $subject_id, $session, $month);
        $max  = $part === 'year' ? $w['year'] : $w['final'];
        $pair = self::ensure_pair($class_id, $subject_id, $session, $month);

        $score = trim($raw) === '' ? null : (float) $raw;

        if ($score !== null && ($score < 0 || $score > $max)) {
            return sch_api_error('score_range', sprintf(
                /* translators: %d: الدرجة العظمى لهذا النصف */
                __('الدرجة يجب أن تكون بين 0 و %d.', 'school-system'),
                $max
            ), 422);
        }

        self::upsert_result($pair[$part], $student_id, ['score' => $score]);

        return true;
    }

    /** الغياب بعذر — يُقصي الطالب من المتوسط ويضعه في كشف الدور البديل. */
    public static function toggle_excused(int $class_id, int $subject_id, string $session, ?int $month, int $student_id): bool|WP_Error
    {
        $guard = self::guard_grades($class_id, $subject_id, $session);
        if (is_wp_error($guard)) {
            return $guard;
        }

        if (self::locked($class_id, $subject_id, $session, $month)) {
            return sch_api_error('locked', __('الدرجات مُرسَلة للاعتماد — لا تُعدَّل حتى تُعاد.', 'school-system'), 409);
        }

        $pair = self::ensure_pair($class_id, $subject_id, $session, $month);
        $now  = self::is_excused($pair['graded'], $student_id);

        foreach (['year', 'graded'] as $part) {
            self::upsert_result($pair[$part], $student_id, ['excused' => $now ? 0 : 1]);
        }

        return true;
    }

    /**
     * إحصاءات الكشف — والمعذور خارج الحساب.
     *
     * @return array{entered:int,total:int,avg:int,fails:int}
     */
    public static function stats(array $roster, array $weights): array
    {
        $sum     = 0;
        $entered = 0;
        $fails   = 0;

        foreach ($roster as $r) {
            if ((int) $r->excused === 1) {
                continue;
            }
            if ($r->year_score === null || $r->final_score === null) {
                continue;
            }

            $total = self::total_of($r, $weights);
            $entered++;
            $sum += $total;

            if ($total < self::PASS_MARK) {
                $fails++;
            }
        }

        return [
            'entered' => $entered,
            'total'   => count($roster),
            'avg'     => $entered > 0 ? (int) round($sum / $entered) : 0,
            'fails'   => $fails,
        ];
    }

    /** مجموع الطالب — كلّ نصفٍ مقصوصٌ على وزنه. */
    public static function total_of(object $row, array $weights): int
    {
        return (int) round(
            min((float) $weights['year'], (float) $row->year_score)
            + min((float) $weights['final'], (float) $row->final_score)
        );
    }

    // ═══════════════════════════ الإرسال والاعتماد ═══════════════════════════

    /** حالة درجات المادة: `open` · `submitted` · `approved`. */
    public static function grade_state(int $class_id, int $subject_id, string $session, ?int $month = null): string
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT MIN(submitted_at) AS sub, MIN(approved_at) AS app
             FROM ' . sch_table('exams') . '
             WHERE class_id = %d AND subject_id = %d AND session = %s
               AND ' . self::month_sql(self::month_of($session, $month)),
            $class_id,
            $subject_id,
            $session
        ));

        if (!$row) {
            return 'open';
        }
        if ($row->app !== null) {
            return 'approved';
        }

        return $row->sub !== null ? 'submitted' : 'open';
    }

    private static function locked(int $class_id, int $subject_id, string $session, ?int $month): bool
    {
        return self::grade_state($class_id, $subject_id, $session, $month) !== 'open';
    }

    /**
     * إرسال الدرجات للاعتماد.
     *
     * **والقفل هو التحديث نفسه** (`WHERE submitted_at IS NULL`) كما في
     * `SCH_TT::publish()`: ضغطتان في ثانيةٍ واحدة لا تُرسلان مرّتين.
     */
    public static function submit(int $class_id, int $subject_id, string $session, ?int $month = null): array|WP_Error
    {
        global $wpdb;

        $guard = self::guard_grades($class_id, $subject_id, $session);
        if (is_wp_error($guard)) {
            return $guard;
        }

        self::ensure_pair($class_id, $subject_id, $session, $month);

        $won = $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('exams') . '
                SET submitted_at = %s, submitted_by = %d, updated_at = %s
              WHERE class_id = %d AND subject_id = %d AND session = %s
                AND ' . self::month_sql(self::month_of($session, $month)) . '
                AND submitted_at IS NULL',
            sch_now(),
            get_current_user_id() ?: 0,
            sch_now(),
            $class_id,
            $subject_id,
            $session
        ));

        if ((int) $won < 1) {
            return sch_api_error('already', __('الدرجات مُرسَلة بالفعل — حدّث الصفحة.', 'school-system'), 409);
        }

        sch_audit('exam.grades_submitted', 'class', $class_id, ['subject' => $subject_id, 'session' => $session]);

        return ['msg' => __('أُرسلت الدرجات للاعتماد — ولا يراها وليّ الأمر حتى تُعتمد.', 'school-system')];
    }

    /** اعتماد الإدارة — وهو **وحده** ما يُنزل الدرجة إلى وليّ الأمر. */
    public static function approve(int $class_id, int $subject_id, string $session, ?int $month = null): array|WP_Error
    {
        global $wpdb;

        if (self::grade_state($class_id, $subject_id, $session, $month) !== 'submitted') {
            return sch_api_error('not_submitted', __('لا درجات بانتظار الاعتماد هنا.', 'school-system'), 409);
        }

        $won = $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('exams') . '
                SET approved_at = %s, approved_by = %d, updated_at = %s
              WHERE class_id = %d AND subject_id = %d AND session = %s
                AND ' . self::month_sql(self::month_of($session, $month)) . '
                AND submitted_at IS NOT NULL AND approved_at IS NULL',
            sch_now(),
            get_current_user_id() ?: 0,
            sch_now(),
            $class_id,
            $subject_id,
            $session
        ));

        if ((int) $won < 1) {
            return sch_api_error('already', __('اعتُمدت للتوّ — حدّث الصفحة.', 'school-system'), 409);
        }

        sch_audit('exam.grades_approved', 'class', $class_id, ['subject' => $subject_id, 'session' => $session]);

        return ['msg' => __('اعتُمدت النتائج — ونُشرت لأولياء الأمور فورًا.', 'school-system')];
    }

    // ═══════════════════════════ الداخل ═══════════════════════════

    /** رقم الشهر كما يُخزَّن — `null` لغير الشهريّ. */
    private static function month_of(string $session, ?int $month): ?int
    {
        return $session === 'monthly' ? max(1, min(12, (int) $month)) : null;
    }

    /**
     * شرط الشهر — يُبنى بيدٍ لا بـ`prepare`.
     *
     * `$wpdb->prepare()` **يحوّل `null` إلى نصٍّ فارغ**، و`session_month = ''`
     * على عمودٍ عدديّ يُقرأ صفرًا لا NULL — فشرطٌ يبدو سليمًا لا يطابق صفًّا
     * واحدًا في الدورات غير الشهرية، وهي الأغلب. وكذلك مصفوفة `WHERE` في
     * `$wpdb->update()`: قيمةٌ `null` فيها تُكتب `= NULL` ولا تطابق أبدًا.
     *
     * والقيمة هنا عددٌ مقصوصٌ بين ١ و١٢ قبل أن تصل، فلا حقنة.
     */
    private static function month_sql(?int $month, string $alias = ''): string
    {
        $col = ($alias !== '' ? $alias . '.' : '') . 'session_month';

        return $month === null ? "{$col} IS NULL" : $col . ' = ' . (int) $month;
    }

    public static function valid_session(string $session): bool
    {
        return array_key_exists($session, self::SESSIONS);
    }

    /** حارس الجدول: الشعبة والدورة والنطاق. */
    private static function guard(int $class_id, string $session): true|WP_Error
    {
        if (!SCH_Classes::get($class_id)) {
            return sch_api_error('not_found', __('الشعبة غير موجودة.', 'school-system'), 404);
        }
        if (!self::valid_session($session)) {
            return sch_api_error('bad_session', __('الدورة غير معروفة.', 'school-system'), 422);
        }

        // النطاق يُفحص في الطبقة لا في الشاشة: مشرف الابتدائي يملك القدرة
        // كاملةً، ولا شيء يمنعه من إعادة كتابة جدول الثانوي بطلبٍ مُلفَّق.
        if (!SCH_Org::may_touch_class($class_id)) {
            return sch_api_error('forbidden', __('هذه الشعبة خارج نطاقك.', 'school-system'), 403);
        }

        return true;
    }

    /**
     * حارس الدرجات — ومعه قيدٌ لا يعرفه حارس الجدول.
     *
     * **معلّم المادة يرصد مادته وحدها.** ومن يملك `sch_manage_exams` (الوكيل
     * والمديرة) يرصد كل مادة. والفحص هنا لا في الشاشة: إخفاء الحقل يمنع
     * الضغط ولا يمنع الطلب.
     */
    private static function guard_grades(int $class_id, int $subject_id, string $session): true|WP_Error
    {
        global $wpdb;

        $base = self::guard($class_id, $session);
        if (is_wp_error($base)) {
            return $base;
        }

        if (current_user_can('sch_manage_exams')) {
            return true;
        }

        $mine = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('class_subjects') . '
              WHERE class_id = %d AND subject_id = %d AND teacher_user_id = %d',
            $class_id,
            $subject_id,
            get_current_user_id()
        ));

        return $mine > 0
            ? true
            : sch_api_error('forbidden', __('هذه المادة ليست مادتك في هذه الشعبة.', 'school-system'), 403);
    }

    /**
     * صفّا المادة — يُنشآن إن لم يكونا.
     *
     * @return array{year:int,graded:int}
     */
    private static function ensure_pair(int $class_id, int $subject_id, string $session, ?int $month): array
    {
        global $wpdb;

        $ids = self::pair_ids($class_id, $subject_id, $session, $month);

        if ($ids['year'] > 0 && $ids['graded'] > 0) {
            return $ids;
        }

        $subject = SCH_Subjects::get($subject_id);
        $name    = $subject ? (string) $subject->name : '';
        $mo      = self::month_of($session, $month);
        $now     = sch_now();
        $w       = self::YEAR_WEIGHT;

        foreach ([
            ['key' => 'year',   'type' => self::YEAR_TYPE,                       'title' => self::YEAR_TITLE,  'max' => $w],
            ['key' => 'graded', 'type' => self::GRADED_TYPE[$session] ?? 'quiz', 'title' => self::FINAL_TITLE, 'max' => 100 - $w],
        ] as $row) {
            if ($ids[$row['key']] > 0) {
                continue;
            }

            $wpdb->insert(sch_table('exams'), [
                'class_id'      => $class_id,
                'subject_id'    => $subject_id,
                'title'         => $row['title'] . ($name !== '' ? ' — ' . $name : ''),
                'exam_type'     => $row['type'],
                'session'       => $session,
                'session_month' => $mo,
                'max_score'     => $row['max'],
                'weight'        => $row['max'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            $ids[$row['key']] = (int) $wpdb->insert_id;
        }

        return $ids;
    }

    /** @return array{year:int,graded:int} */
    private static function pair_ids(int $class_id, int $subject_id, string $session, ?int $month): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, exam_type FROM ' . sch_table('exams') . '
              WHERE class_id = %d AND subject_id = %d AND session = %s
                AND ' . self::month_sql(self::month_of($session, $month)),
            $class_id,
            $subject_id,
            $session
        )) ?: [];

        $out = ['year' => 0, 'graded' => 0];

        foreach ($rows as $r) {
            $key = (string) $r->exam_type === self::YEAR_TYPE ? 'year' : 'graded';

            if ($out[$key] === 0) {
                $out[$key] = (int) $r->id;
            }
        }

        return $out;
    }

    private static function is_excused(int $exam_id, int $student_id): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT excused FROM ' . sch_table('exam_results') . ' WHERE exam_id = %d AND student_id = %d',
            $exam_id,
            $student_id
        )) === 1;
    }

    private static function upsert_result(int $exam_id, int $student_id, array $data): void
    {
        global $wpdb;

        if ($exam_id <= 0 || $student_id <= 0) {
            return;
        }

        $existing = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('exam_results') . ' WHERE exam_id = %d AND student_id = %d',
            $exam_id,
            $student_id
        ));

        $data += ['recorded_by' => get_current_user_id() ?: null, 'recorded_at' => sch_now()];

        if ($existing > 0) {
            $wpdb->update(sch_table('exam_results'), $data, ['id' => $existing]);
            return;
        }

        $wpdb->insert(sch_table('exam_results'), $data + ['exam_id' => $exam_id, 'student_id' => $student_id]);
    }
}
