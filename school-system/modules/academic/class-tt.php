<?php
/**
 * محرّك الجداول الأسبوعية.
 *
 * ثلاث طبقات لا واحدة:
 *   **إيقاع اليوم** (`bell`) — متى يبدأ اليوم وكم حصة وكم الفسحة، لكل مرحلة.
 *   **الخطة** (`tt_plans` + `tt_quota` + `tt_slots`) — مسودّة تُبنى وتُعتمد.
 *   **المنشور** (`timetable`) — ما يراه المعلم وولي الأمر والطالب وشاشة التغطية.
 *
 * والفصل مقصود: جدول `timetable` تقرؤه ثمانية أسطح بُنيت على مدى عشرة إصدارات،
 * فلا يُمسّ شكله. البناء كلّه يجري في الخطة، والنشر **نسخةٌ ذرّية** منها إليه —
 * فلا ينكسر قارئٌ واحد، ولا يرى المعلم مسودّةً تتغيّر تحت يده.
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

final class SCH_TT
{
    /**
     * إعدادات جاهزة لكل مرحلة.
     *
     * ليست زينة: الروضة تخرج ١٠:١٢ والابتدائي ١:١٥، ومن يفتح الشاشة أول مرة
     * لا يعرف كم حصة للتمهيدي. الضغطة الواحدة تملأ اليوم كاملًا ثم يعدّل.
     */
    public const PRESETS = [
        'nursery'      => ['label' => 'الحضانة',   'periods' => 5,  'len' => 20, 'start' => 450, 'break_after' => 2, 'break_len' => 30, 'note' => 'حصص قصيرة ووقت لعب أطول'],
        'kg'           => ['label' => 'الروضة',    'periods' => 6,  'len' => 22, 'start' => 450, 'break_after' => 3, 'break_len' => 30, 'note' => 'توازن بين الأنشطة واللعب'],
        'prep'         => ['label' => 'التمهيدي',  'periods' => 7,  'len' => 25, 'start' => 435, 'break_after' => 3, 'break_len' => 30, 'note' => 'تمهيد لمهارات الابتدائي'],
        'primary'      => ['label' => 'ابتدائي',   'periods' => 10, 'len' => 32, 'start' => 435, 'break_after' => 4, 'break_len' => 40, 'note' => 'الفسحة بعد الرابعة للجميع'],
        'intermediate' => ['label' => 'متوسط',     'periods' => 8,  'len' => 40, 'start' => 435, 'break_after' => 4, 'break_len' => 30, 'note' => 'حصص أطول وفسحة أقصر'],
        'secondary'    => ['label' => 'ثانوي',     'periods' => 7,  'len' => 45, 'start' => 435, 'break_after' => 3, 'break_len' => 30, 'note' => 'نظام المسارات — ٧ حصص'],
    ];

    /** أيام الأسبوع الستة المحتملة — والقناع يقول أيّها دوام. */
    public const WEEK = [1 => 'الأحد', 2 => 'الاثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء', 5 => 'الخميس', 6 => 'السبت'];

    /** القيود التي يفهمها المولّد. المفتاح ثابت والنصّ يُترجَم. */
    public const RULES = [
        'no_pair'    => 'لا حصتان متتاليتان لنفس المادة',
        'core_early' => 'المواد الأساسية في الحصص الأولى',
        'pe_last'    => 'الأنشطة الحركية آخر النهار',
        'cap'        => 'احترام حد المعلم اليومي',
        'no_gap'     => 'تقليل فراغات المعلم بين الحصص',
    ];

    private const DEFAULT_RULES = ['no_pair', 'core_early', 'pe_last', 'cap'];

    /** أقصى عدد حصص للمعلم في اليوم — سقفٌ يُضبَط من الشاشة. */
    private const MAX_DAILY = 6;

    // ═══════════════════════════ إيقاع اليوم ═══════════════════════════

    /**
     * إيقاع مرحلة — من القاعدة إن ضُبط، وإلا من الإعداد الجاهز.
     *
     * لا يُرجع null أبدًا: شاشةٌ تعرض جدولًا لمرحلة لم تُضبط بعد يجب أن تعرض
     * إيقاعًا معقولًا لا صفحة فارغة.
     */
    public static function bell(string $stage): object
    {
        static $cache = [];

        $stage = $stage !== '' ? $stage : 'primary';
        if (isset($cache[$stage])) {
            return $cache[$stage];
        }

        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('bell') . ' WHERE year_id = %d AND stage = %s',
            SCH_Years::current_id(),
            $stage
        ));

        if (!$row) {
            $p   = self::PRESETS[$stage] ?? self::PRESETS['primary'];
            $row = (object) [
                'id'          => 0,
                'stage'       => $stage,
                'periods'     => $p['periods'],
                'start_min'   => $p['start'],
                'period_len'  => $p['len'],
                'break_after' => $p['break_after'],
                'break_len'   => $p['break_len'],
                'days_mask'   => 31, // الأحد إلى الخميس
            ];
        }

        foreach (['periods', 'start_min', 'period_len', 'break_after', 'break_len', 'days_mask'] as $k) {
            $row->$k = (int) $row->$k;
        }

        return $cache[$stage] = $row;
    }

    public static function save_bell(array $d): bool|WP_Error
    {
        global $wpdb;

        $stage = sanitize_text_field((string) ($d['stage'] ?? ''));
        if (!array_key_exists($stage, SCH_Classes::STAGES) && !array_key_exists($stage, self::PRESETS)) {
            return sch_api_error('bad_stage', __('المرحلة غير معروفة.', 'school-system'), 422);
        }

        $periods = max(1, min(14, (int) ($d['periods'] ?? 8)));
        $fields  = [
            'periods'     => $periods,
            'start_min'   => max(0, min(1439, self::to_min((string) ($d['start'] ?? '07:30')))),
            'period_len'  => max(10, min(120, (int) ($d['period_len'] ?? 45))),
            // الفسحة بعد الحصة الأخيرة لا معنى لها — تصير بعد ما قبلها.
            'break_after' => max(0, min($periods - 1, (int) ($d['break_after'] ?? 3))),
            'break_len'   => max(0, min(120, (int) ($d['break_len'] ?? 30))),
            'days_mask'   => max(1, min(63, (int) ($d['days_mask'] ?? 31))),
            'updated_at'  => sch_now(),
        ];

        $year = SCH_Years::current_id();
        $id   = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('bell') . ' WHERE year_id = %d AND stage = %s',
            $year,
            $stage
        ));

        if ($id > 0) {
            $wpdb->update(sch_table('bell'), $fields, ['id' => $id]);
        } else {
            $wpdb->insert(sch_table('bell'), $fields + [
                'year_id'    => $year,
                'stage'      => $stage,
                'created_at' => sch_now(),
            ]);
        }

        sch_audit('bell.saved', 'bell', $id ?: (int) $wpdb->insert_id, ['stage' => $stage]);
        return true;
    }

    /** بداية الحصة بالدقائق — الفسحة تُزاح إلى ما بعدها. */
    public static function period_start(int $period, string $stage = 'primary'): int
    {
        $b = self::bell($stage);
        $m = $b->start_min + (max(1, $period) - 1) * $b->period_len;

        return $period > $b->break_after ? $m + $b->break_len : $m;
    }

    public static function hhmm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    public static function period_hhmm(int $period, string $stage = 'primary'): string
    {
        return self::hhmm(self::period_start($period, $stage));
    }

    /** أيام الدوام من القناع — مرتّبة. @return array<int,string> */
    public static function days(string $stage): array
    {
        $mask = self::bell($stage)->days_mask;
        $out  = [];

        foreach (self::WEEK as $n => $label) {
            if ($mask & (1 << ($n - 1))) {
                $out[$n] = $label;
            }
        }

        return $out;
    }

    /**
     * شريط اليوم: كل حصة بوقتها، والفسحة بينها كعنصر مستقلّ.
     *
     * @return array<int,array{kind:string,no:int,from:string,to:string}>
     */
    public static function ribbon(string $stage): array
    {
        $b   = self::bell($stage);
        $out = [];

        for ($p = 1; $p <= $b->periods; $p++) {
            $s     = self::period_start($p, $stage);
            $out[] = ['kind' => 'period', 'no' => $p, 'from' => self::hhmm($s), 'to' => self::hhmm($s + $b->period_len)];

            if ($p === $b->break_after && $b->break_len > 0) {
                $s     = $b->start_min + $p * $b->period_len;
                $out[] = ['kind' => 'break', 'no' => 0, 'from' => self::hhmm($s), 'to' => self::hhmm($s + $b->break_len)];
            }
        }

        return $out;
    }

    /** نهاية الدوام وطوله — لرأس الشاشة. @return array{end:string,length:string} */
    public static function day_span(string $stage): array
    {
        $b     = self::bell($stage);
        $end   = $b->start_min + $b->periods * $b->period_len + ($b->break_after > 0 ? $b->break_len : 0);
        $total = $end - $b->start_min;

        return [
            'end'    => self::hhmm($end),
            'length' => sprintf(
                /* translators: 1: ساعات 2: دقائق */
                __('%1$d س %2$d د', 'school-system'),
                intdiv($total, 60),
                $total % 60
            ),
        ];
    }

    private static function to_min(string $hhmm): int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($hhmm), $m)) {
            return 450;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    // ═══════════════════════════ الخطة ═══════════════════════════

    /** @return array<int,object> */
    public static function plans(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('tt_plans') . "
             WHERE year_id = %d AND status <> 'archived'
             ORDER BY effective_from",
            SCH_Years::current_id()
        )) ?: [];
    }

    public static function get_plan(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('tt_plans') . ' WHERE id = %d',
            $id
        ));

        return $row ?: null;
    }

    /**
     * خطة شهر — تُنشأ إن لم توجد.
     *
     * «الشهر» في الواجهة، ومدى التواريخ في القاعدة: المدرسة قد تغيّر الجدول
     * في منتصف الشهر، ولا يعرف تقويمُها شيئًا اسمه «أغسطس كاملًا».
     */
    public static function ensure_plan(string $ym): ?object
    {
        global $wpdb;

        // ترجع null لا خطأً: الشاشة تستدعيها في كل عرض، وشهرٌ غير صحيح أو
        // سنةٌ غير مفتوحة حالةُ «لا خطة» لا عمليةٌ فاشلة.
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return null;
        }

        $year = SCH_Years::current_id();
        if ($year <= 0) {
            return null;
        }

        $from = $ym . '-01';
        $to   = gmdate('Y-m-t', (int) strtotime($from));

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('tt_plans') . '
             WHERE year_id = %d AND effective_from = %s LIMIT 1',
            $year,
            $from
        ));

        if ($row) {
            return $row;
        }

        $wpdb->insert(sch_table('tt_plans'), [
            'year_id'        => $year,
            'name'           => wp_date('F Y', (int) strtotime($from)),
            'effective_from' => $from,
            'effective_to'   => $to,
            'status'         => 'draft',
            'rules'          => implode(',', self::DEFAULT_RULES),
            'max_daily'      => self::MAX_DAILY,
            'created_by'     => get_current_user_id() ?: null,
            'created_at'     => sch_now(),
            'updated_at'     => sch_now(),
        ]);

        $id = (int) $wpdb->insert_id;
        sch_audit('tt.plan_created', 'tt_plan', $id, ['month' => $ym]);

        return self::get_plan($id);
    }

    /**
     * أشهر السنة الدراسية — من تاريخَي بدايتها ونهايتها.
     *
     * لا قائمة مكتوبة تبدأ بأغسطس: السنة قد تبدأ في سبتمبر، ومدرسةٌ تفتح في
     * محرّم لها أشهرٌ أخرى. والمصدر هو سجلّ السنة نفسه.
     *
     * @return array<string,string> 'YYYY-MM' => الاسم
     */
    public static function months(): array
    {
        $year = SCH_Years::current();

        $from = $year && $year->start_date ? (string) $year->start_date : gmdate('Y-08-01');
        $to   = $year && $year->end_date ? (string) $year->end_date : gmdate('Y-06-30', strtotime('+1 year'));

        $cur  = (int) strtotime(substr($from, 0, 7) . '-01');
        $end  = (int) strtotime(substr($to, 0, 7) . '-01');
        $out  = [];
        $stop = 0;

        while ($cur <= $end && $stop++ < 24) {
            $out[gmdate('Y-m', $cur)] = wp_date('F', $cur);
            $cur = (int) strtotime('+1 month', $cur);
        }

        return $out;
    }

    /** الأشهر التي فيها خطة بخانات — النقطة الخضراء في شريط الأشهر. @return array<string,int> */
    public static function months_with_slots(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT p.effective_from, COUNT(s.id) AS n
             FROM ' . sch_table('tt_plans') . ' p
             LEFT JOIN ' . sch_table('tt_slots') . ' s ON s.plan_id = p.id
             WHERE p.year_id = %d
             GROUP BY p.id',
            SCH_Years::current_id()
        )) ?: [];

        $out = [];
        foreach ($rows as $r) {
            if ((int) $r->n > 0) {
                $out[substr((string) $r->effective_from, 0, 7)] = (int) $r->n;
            }
        }

        return $out;
    }

    /** @return string[] */
    public static function rules_of(object $plan): array
    {
        $raw = array_filter(array_map('trim', explode(',', (string) $plan->rules)));

        return array_values(array_intersect($raw, array_keys(self::RULES)));
    }

    public static function set_rules(int $plan_id, array $rules, int $max_daily): bool
    {
        global $wpdb;

        $clean = array_values(array_intersect(array_map('strval', $rules), array_keys(self::RULES)));

        $wpdb->update(sch_table('tt_plans'), [
            'rules'      => implode(',', $clean),
            'max_daily'  => max(1, min(14, $max_daily)),
            'updated_at' => sch_now(),
        ], ['id' => $plan_id]);

        return true;
    }

    // ═══════════════════════════ النصاب والإسناد ═══════════════════════════

    /**
     * نصاب شعبة: كل مادة متاحة لمرحلتها ونصابها ومعلمها.
     *
     * استعلامٌ واحد بضمّ المادة والمعلم — لا استعلامٌ لكل مادة.
     *
     * @return array<int,object>
     */
    public static function quota(int $plan_id, int $class_id): array
    {
        global $wpdb;

        $class = SCH_Classes::get($class_id);
        $stage = $class ? (string) $class->stage : '';

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.id AS subject_id, s.name AS subject_name, s.code,
                    COALESCE(q.weekly, 0) AS weekly,
                    q.teacher_user_id,
                    u.display_name AS teacher_name
             FROM ' . sch_table('subjects') . ' s
             LEFT JOIN ' . sch_table('tt_quota') . ' q
                    ON q.subject_id = s.id AND q.plan_id = %d AND q.class_id = %d
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = q.teacher_user_id
             WHERE s.stage = %s OR s.stage IS NULL OR s.stage = %s
             ORDER BY COALESCE(q.weekly, 0) DESC, s.name',
            $plan_id,
            $class_id,
            $stage,
            ''
        )) ?: [];
    }

    /** صفوف النصاب المُسنَد فعلًا (weekly > 0) — ما يوزّعه المولّد. @return array<int,object> */
    public static function demand(int $plan_id, int $class_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT q.*, s.name AS subject_name, s.code
             FROM ' . sch_table('tt_quota') . ' q
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = q.subject_id
             WHERE q.plan_id = %d AND q.class_id = %d AND q.weekly > 0
             ORDER BY q.weekly DESC, s.name',
            $plan_id,
            $class_id
        )) ?: [];
    }

    public static function set_quota(int $plan_id, int $class_id, int $subject_id, int $weekly): bool|WP_Error
    {
        global $wpdb;

        if (!self::get_plan($plan_id) || !SCH_Classes::get($class_id) || !SCH_Subjects::get($subject_id)) {
            return sch_api_error('not_found', __('الخطة أو الشعبة أو المادة غير موجودة.', 'school-system'), 404);
        }

        $weekly = max(0, min(40, $weekly));
        $now    = sch_now();

        // إدراجٌ أو تحديث في نداء واحد — المفتاح الفريد يحسم أيّهما.
        $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . sch_table('tt_quota') . '
                (plan_id, class_id, subject_id, weekly, created_at, updated_at)
             VALUES (%d, %d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE weekly = VALUES(weekly), updated_at = VALUES(updated_at)',
            $plan_id,
            $class_id,
            $subject_id,
            $weekly,
            $now,
            $now
        ));

        return true;
    }

    public static function set_teacher(int $plan_id, int $class_id, int $subject_id, ?int $teacher): bool|WP_Error
    {
        global $wpdb;

        if ($teacher !== null && $teacher > 0 && !get_userdata($teacher)) {
            return sch_api_error('no_teacher', __('المعلم غير موجود.', 'school-system'), 404);
        }

        $now = sch_now();
        $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . sch_table('tt_quota') . '
                (plan_id, class_id, subject_id, weekly, teacher_user_id, created_at, updated_at)
             VALUES (%d, %d, %d, 0, %d, %s, %s)
             ON DUPLICATE KEY UPDATE teacher_user_id = VALUES(teacher_user_id), updated_at = VALUES(updated_at)',
            $plan_id,
            $class_id,
            $subject_id,
            $teacher ?: null,
            $now,
            $now
        ));

        // الخانات الموضوعة تتبع الإسناد الجديد — وإلا بقي في الجدول اسمُ من
        // لم يعد يُدرّس المادة، ويُرسَل له إشعار حصةٍ ليست له.
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('tt_slots') . '
             SET teacher_user_id = %d
             WHERE plan_id = %d AND class_id = %d AND subject_id = %d',
            $teacher ?: null,
            $plan_id,
            $class_id,
            $subject_id
        ));

        return true;
    }

    /**
     * تعبئة النصاب من القالب: يوزّع سعة الأسبوع على مواد المرحلة.
     *
     * الشاشة الفارغة أول ما يراه الوكيل — والقالب يجعلها قابلة للتشغيل بضغطة.
     */
    public static function seed_quota(int $plan_id, int $class_id): int
    {
        $class = SCH_Classes::get($class_id);
        if (!$class) {
            return 0;
        }

        $subjects = self::quota($plan_id, $class_id);
        if ($subjects === []) {
            return 0;
        }

        $capacity = self::capacity($class_id);
        $n        = count($subjects);
        $base     = intdiv($capacity, $n);
        $rest     = $capacity - $base * $n;

        foreach ($subjects as $i => $s) {
            // الكسر يذهب لأوائل المواد — والقائمة مرتّبة بالأهمّ فالأهمّ.
            self::set_quota($plan_id, $class_id, (int) $s->subject_id, $base + ($i < $rest ? 1 : 0));
        }

        return $capacity;
    }

    /** إسناد معلم لكل مادة بلا معلم — الأقلّ حِملًا أولًا. */
    public static function auto_assign(int $plan_id, int $class_id): int
    {
        $rows = self::quota($plan_id, $class_id);
        $load = self::teacher_load($plan_id);
        $pool = self::teachers();

        if ($pool === []) {
            return 0;
        }

        $done = 0;
        foreach ($rows as $r) {
            if ((int) $r->weekly <= 0 || $r->teacher_user_id) {
                continue;
            }

            // الأقلّ حِملًا يأخذ التالي — فلا يتكدّس النصاب على واحد.
            $best = null;
            $min  = PHP_INT_MAX;
            foreach ($pool as $t) {
                $have = (int) ($load[(int) $t->ID] ?? 0);
                if ($have < $min) {
                    $min  = $have;
                    $best = (int) $t->ID;
                }
            }

            if ($best === null) {
                continue;
            }

            self::set_teacher($plan_id, $class_id, (int) $r->subject_id, $best);
            $load[$best] = $min + (int) $r->weekly;
            $done++;
        }

        return $done;
    }

    /** حِمل كل معلم في الخطة كلّها (مجموع نصابه عبر الشعب). @return array<int,int> */
    public static function teacher_load(int $plan_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT teacher_user_id AS t, SUM(weekly) AS n
             FROM ' . sch_table('tt_quota') . '
             WHERE plan_id = %d AND teacher_user_id IS NOT NULL
             GROUP BY teacher_user_id',
            $plan_id
        )) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->t] = (int) $r->n;
        }

        return $out;
    }

    /** المعلمون المتاحون للإسناد. @return array<int,object> */
    public static function teachers(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        global $wpdb;

        return $cache = $wpdb->get_results(
            'SELECT u.ID, u.display_name
             FROM ' . $wpdb->users . ' u
             INNER JOIN ' . sch_table('employees') . " e ON e.user_id = u.ID
             WHERE e.status = 'active'
             ORDER BY u.display_name"
        ) ?: [];
    }

    /** سعة أسبوع الشعبة: حصص اليوم × أيام الدوام. */
    public static function capacity(int $class_id): int
    {
        $class = SCH_Classes::get($class_id);
        $stage = $class ? (string) $class->stage : 'primary';

        return self::bell($stage)->periods * max(1, count(self::days($stage)));
    }

    // ═══════════════════════════ الخانات ═══════════════════════════

    /**
     * شبكة شعبة: [يوم][حصة] => صف بالمادة والمعلم وعلامة التعارض.
     *
     * التعارض يُحسب في الاستعلام نفسه — لا حلقةٌ تفتح استعلامًا لكل خانة.
     *
     * @return array<int,array<int,object>>
     */
    public static function grid(int $plan_id, int $class_id): array
    {
        global $wpdb;

        $t = sch_table('tt_slots');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, sub.name AS subject_name, u.display_name AS teacher_name,
                    EXISTS (SELECT 1 FROM {$t} x
                             WHERE x.plan_id = s.plan_id AND x.teacher_user_id = s.teacher_user_id
                               AND x.day_of_week = s.day_of_week AND x.period_no = s.period_no
                               AND x.class_id <> s.class_id) AS clash
             FROM {$t} s
             INNER JOIN " . sch_table('subjects') . ' sub ON sub.id = s.subject_id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = s.teacher_user_id
             WHERE s.plan_id = %d AND s.class_id = %d',
            $plan_id,
            $class_id
        )) ?: [];

        $grid = [];
        foreach ($rows as $r) {
            $grid[(int) $r->day_of_week][(int) $r->period_no] = $r;
        }

        return $grid;
    }

    public static function place(int $plan_id, int $class_id, int $day, int $period, int $subject_id): bool|WP_Error
    {
        global $wpdb;

        $class = SCH_Classes::get($class_id);
        if (!$class) {
            return sch_api_error('not_found', __('الشعبة غير موجودة.', 'school-system'), 404);
        }

        // النطاق يُفحص هنا لا في الشاشة: مشرف الابتدائي يملك `sch_build_timetable`
        // كاملةً، ولا شيء كان يمنعه من إعادة كتابة جدول الثانوي بطلبٍ مُلفَّق.
        if (!SCH_Org::may_touch_class($class_id)) {
            return sch_api_error('forbidden', __('هذه الشعبة خارج نطاقك.', 'school-system'), 403);
        }

        $stage = (string) $class->stage;
        $bell  = self::bell($stage);

        if (!array_key_exists($day, self::days($stage)) || $period < 1 || $period > $bell->periods) {
            return sch_api_error('bad_slot', __('اليوم أو رقم الحصة خارج إيقاع المرحلة.', 'school-system'), 422);
        }
        if (!SCH_Subjects::get($subject_id)) {
            return sch_api_error('not_found', __('المادة غير موجودة.', 'school-system'), 404);
        }

        $teacher = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT teacher_user_id FROM ' . sch_table('tt_quota') . '
             WHERE plan_id = %d AND class_id = %d AND subject_id = %d',
            $plan_id,
            $class_id,
            $subject_id
        ));

        $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . sch_table('tt_slots') . '
                (plan_id, class_id, day_of_week, period_no, subject_id, teacher_user_id, locked, created_at)
             VALUES (%d, %d, %d, %d, %d, %d, 1, %s)
             ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id),
                                     teacher_user_id = VALUES(teacher_user_id),
                                     locked = 1',
            $plan_id,
            $class_id,
            $day,
            $period,
            $subject_id,
            $teacher ?: null,
            sch_now()
        ));

        sch_audit('tt.slot_set', 'class', $class_id, ['day' => $day, 'period' => $period, 'subject' => $subject_id]);

        return true;
    }

    public static function clear(int $plan_id, int $class_id, int $day, int $period): bool
    {
        global $wpdb;

        if (!SCH_Org::may_touch_class($class_id)) {
            return false;
        }

        $wpdb->delete(sch_table('tt_slots'), [
            'plan_id'     => $plan_id,
            'class_id'    => $class_id,
            'day_of_week' => $day,
            'period_no'   => $period,
        ]);

        sch_audit('tt.slot_cleared', 'class', $class_id, ['day' => $day, 'period' => $period]);

        return true;
    }

    /** نقل خانة إلى أخرى — والمشغولة تتبادل معها لا تُمحى. */
    public static function move(int $plan_id, int $class_id, int $from_day, int $from_period, int $to_day, int $to_period): bool|WP_Error
    {
        $grid = self::grid($plan_id, $class_id);
        $a    = $grid[$from_day][$from_period] ?? null;

        if (!$a) {
            return sch_api_error('empty', __('الخانة المنقولة فارغة.', 'school-system'), 422);
        }

        $b = $grid[$to_day][$to_period] ?? null;

        self::clear($plan_id, $class_id, $from_day, $from_period);
        self::clear($plan_id, $class_id, $to_day, $to_period);

        $moved = self::place($plan_id, $class_id, $to_day, $to_period, (int) $a->subject_id);
        if (is_wp_error($moved)) {
            self::place($plan_id, $class_id, $from_day, $from_period, (int) $a->subject_id);
            return $moved;
        }

        if ($b) {
            self::place($plan_id, $class_id, $from_day, $from_period, (int) $b->subject_id);
        }

        return true;
    }

    /** تفريغ شعبة — وما ثبّته الوكيل يبقى إن طُلب. */
    public static function wipe(int $plan_id, int $class_id, bool $keep_locked = false): int
    {
        global $wpdb;

        $sql = 'DELETE FROM ' . sch_table('tt_slots') . ' WHERE plan_id = %d AND class_id = %d';
        if ($keep_locked) {
            $sql .= ' AND locked = 0';
        }

        return (int) $wpdb->query($wpdb->prepare($sql, $plan_id, $class_id));
    }

    // ═══════════════════════════ صحّة الجدول ═══════════════════════════

    /**
     * شرائح الصحة الثلاث: تعارضات · نصاب ناقص · حصص ثقيلة متأخرة.
     *
     * ثلاثة أرقام تجيب «هل الجدول جاهز للنشر؟» قبل أن يُفتح — ولذلك تُحسب
     * باستعلامين لا بمسح الشبكة في PHP.
     *
     * @return array{conflicts:int,missing:int,late:int,filled:int,demand:int,cells:array<int,string>}
     */
    public static function health(int $plan_id, int $class_id): array
    {
        global $wpdb;

        $class = SCH_Classes::get($class_id);
        $stage = $class ? (string) $class->stage : 'primary';
        $bell  = self::bell($stage);
        $t     = sch_table('tt_slots');

        $demand = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COALESCE(SUM(weekly), 0) FROM ' . sch_table('tt_quota') . '
             WHERE plan_id = %d AND class_id = %d',
            $plan_id,
            $class_id
        ));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.day_of_week, s.period_no, s.teacher_user_id, sub.name AS subject_name,
                    EXISTS (SELECT 1 FROM {$t} x
                             WHERE x.plan_id = s.plan_id AND x.teacher_user_id = s.teacher_user_id
                               AND x.day_of_week = s.day_of_week AND x.period_no = s.period_no
                               AND x.class_id <> s.class_id) AS clash
             FROM {$t} s
             INNER JOIN " . sch_table('subjects') . ' sub ON sub.id = s.subject_id
             WHERE s.plan_id = %d AND s.class_id = %d',
            $plan_id,
            $class_id
        )) ?: [];

        $conflicts = 0;
        $late      = 0;
        $cells     = [];
        $heavy     = max(1, $bell->periods - 2);

        foreach ($rows as $r) {
            $key = (int) $r->day_of_week . '|' . (int) $r->period_no;

            if ((int) $r->clash === 1) {
                $conflicts++;
                $cells[$key] = 'conflict';
            }
            // الشريحة تسأل: هل وُضعت **مادة أساسية** آخر النهار؟ وعدُّ كل مادة
            // يجعل كل جدول يبدو معتلًّا — فالحصّتان الأخيرتان مملوءتان دائمًا.
            if ((int) $r->period_no > $heavy && self::is_core((string) $r->subject_name)) {
                $late++;
                if (!isset($cells[$key])) {
                    $cells[$key] = 'late';
                }
            }
        }

        $filled = count($rows);

        return [
            'conflicts' => $conflicts,
            'missing'   => max(0, $demand - $filled),
            'late'      => $late,
            'filled'    => $filled,
            'demand'    => $demand,
            'cells'     => $cells,
        ];
    }

    /** صحّة الخطة كلّها — لبوابة النشر. @return array{conflicts:int,unassigned:int,classes:int,slots:int} */
    public static function plan_health(int $plan_id): array
    {
        global $wpdb;

        $t = sch_table('tt_slots');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS slots,
                    COUNT(DISTINCT class_id) AS classes,
                    COALESCE(SUM(teacher_user_id IS NULL), 0) AS unassigned,
                    COALESCE(SUM(EXISTS (SELECT 1 FROM {$t} x
                             WHERE x.plan_id = s.plan_id AND x.teacher_user_id = s.teacher_user_id
                               AND x.day_of_week = s.day_of_week AND x.period_no = s.period_no
                               AND x.class_id <> s.class_id)), 0) AS conflicts
             FROM {$t} s WHERE s.plan_id = %d",
            $plan_id
        ));

        return [
            'slots'      => (int) ($row->slots ?? 0),
            'classes'    => (int) ($row->classes ?? 0),
            'unassigned' => (int) ($row->unassigned ?? 0),
            'conflicts'  => (int) ($row->conflicts ?? 0),
        ];
    }
    // ═══════════════════════════ التوليد ═══════════════════════════

    /**
     * توليد الجدول لمجموعة شعب دفعةً واحدة.
     *
     * **قراران يفصلان هذا المولّد عن نموذجه الأوّليّ، وكلاهما ناتج عن عطل رأيناه:**
     *
     * ① **التوزيع يوميّ لا حصّيّ.** النموذج كان يمرّ حصةً حصة وداخلها الأيام،
     *    فينفد نصاب المواد قبل أن يصل إلى بقيّة الأيام — فيخرج جدولٌ الأربعاء
     *    والخميس فيه ممتلئان والأحد والاثنين فارغان تمامًا. هنا يُبنى **يومٌ
     *    كامل في كل دورة** والنصاب يُقسَّم على الأيام قبل أن تبدأ.
     *
     * ② **التوليد للمدرسة لا للشعبة.** المعلم واحد وشُعبه كثيرة: من يولّد شعبةً
     *    شعبة يجد الشعبة الثلاثين وكل معلميها محجوزون، فيصير جدولها **مستحيلًا
     *    رياضيًّا** لا معطوبًا. الحجز هنا مشتركٌ بين كل الشعب في الدورة الواحدة.
     *
     * والخانات المثبّتة (`locked`) لا تُمسّ — من يعدّل بيده ثم يضغط «توليد من
     * جديد» يجب ألّا يفقد عمله، وإلّا توقّف عن الضغط.
     *
     * @param int[] $class_ids
     * @return array{placed:int,missing:int,classes:int,blocked:array<int,string>}|WP_Error
     */
    public static function generate(int $plan_id, array $class_ids): array|WP_Error
    {
        global $wpdb;

        $plan = self::get_plan($plan_id);
        if (!$plan) {
            return sch_api_error('no_plan', __('الخطة غير موجودة.', 'school-system'), 404);
        }
        if ((string) $plan->status === 'published') {
            return sch_api_error('published', __('الخطة منشورة — أنشئ خطة جديدة للتعديل.', 'school-system'), 409);
        }

        $class_ids = array_values(array_unique(array_filter(array_map('absint', $class_ids))));
        if ($class_ids === []) {
            return sch_api_error('no_class', __('لم تُحدَّد شعبة.', 'school-system'), 422);
        }

        $rules     = self::rules_of($plan);
        $max_daily = max(1, (int) $plan->max_daily);
        $has       = static fn (string $r): bool => in_array($r, $rules, true);

        // حجز المعلمين: يبدأ بما هو مثبّت في الخطة كلّها — بما فيه شعبٌ خارج
        // هذه الدورة، وإلّا صادم التوليدُ جدولًا سابقًا.
        $busy = [];
        $kept = $wpdb->get_results($wpdb->prepare(
            'SELECT class_id, day_of_week, period_no, teacher_user_id
             FROM ' . sch_table('tt_slots') . '
             WHERE plan_id = %d AND (locked = 1 OR class_id NOT IN (' . implode(',', array_fill(0, count($class_ids), '%d')) . '))',
            ...array_merge([$plan_id], $class_ids)
        )) ?: [];

        $locked = [];
        foreach ($kept as $k) {
            if ($k->teacher_user_id) {
                $busy[(int) $k->teacher_user_id . '|' . (int) $k->day_of_week . '|' . (int) $k->period_no] = true;
            }
            $locked[(int) $k->class_id . '|' . (int) $k->day_of_week . '|' . (int) $k->period_no] = true;
        }

        // حِمل المعلم اليوميّ يُحسب عبر الشعب كلّها لا داخل الشعبة الواحدة.
        $load    = [];
        $placed  = 0;
        $missing = 0;
        $blocked = [];
        $rows    = [];
        $now     = sch_now();

        foreach ($class_ids as $class_id) {
            $class = SCH_Classes::get($class_id);
            if (!$class || !SCH_Org::may_touch_class($class_id)) {
                continue;
            }

            $stage  = (string) $class->stage;
            $bell   = self::bell($stage);
            $days   = array_keys(self::days($stage));
            $demand = self::demand($plan_id, $class_id);

            if ($days === [] || $demand === []) {
                $blocked[$class_id] = __('لا نصاب مُسنَد لهذه الشعبة بعد.', 'school-system');
                continue;
            }

            self::wipe($plan_id, $class_id, true);

            // ① النصاب يُقسَّم على الأيام قبل التوزيع — فلا يُستهلك في أوّلها.
            $quota = [];
            foreach ($demand as $d) {
                $quota[(int) $d->subject_id] = [
                    'left'    => (int) $d->weekly,
                    'teacher' => (int) $d->teacher_user_id,
                    'core'    => self::is_core((string) $d->subject_name),
                    'active'  => self::is_active((string) $d->subject_name),
                    'name'    => (string) $d->subject_name,
                ];
            }

            $per_day = [];
            $day_use = [];
            foreach ($days as $day) {
                $day_use[$day] = [];
            }

            $heavy = max(1, $bell->periods - 2);

            for ($p = 1; $p <= $bell->periods; $p++) {
                foreach ($days as $day) {
                    $key = $class_id . '|' . $day . '|' . $p;
                    if (isset($locked[$key])) {
                        continue;
                    }

                    $pick = null;
                    $best = -PHP_INT_MAX;

                    foreach ($quota as $sid => $q) {
                        if ($q['left'] <= 0) {
                            continue;
                        }

                        $t = $q['teacher'];

                        // ② قيدٌ صلب: معلمٌ واحد لا يكون في شعبتين في الحصة نفسها.
                        if ($t > 0 && isset($busy[$t . '|' . $day . '|' . $p])) {
                            continue;
                        }
                        if ($t > 0 && $has('cap') && ($load[$t . '|' . $day] ?? 0) >= $max_daily) {
                            continue;
                        }
                        // لا حصتان متتاليتان لنفس المادة.
                        if ($has('no_pair') && ($per_day[$day][$p - 1] ?? 0) === $sid) {
                            continue;
                        }

                        // الترجيح: ما تبقّى منه أكثر يُوضع أولًا، ثم تفضيلات الموضع.
                        $score = $q['left'] * 10;

                        // المادة لا تتكرّر في اليوم الواحد إلّا عند الضرورة.
                        $score -= ($day_use[$day][$sid] ?? 0) * 25;

                        if ($has('core_early')) {
                            $score += $q['core'] ? ($bell->periods - $p) * 3 : 0;
                        }
                        if ($has('pe_last') && $q['active']) {
                            $score += $p > $heavy ? 20 : -12;
                        }
                        if ($has('no_gap') && $t > 0 && ($load[$t . '|' . $day] ?? 0) > 0) {
                            $score += 4;
                        }

                        if ($score > $best) {
                            $best = $score;
                            $pick = $sid;
                        }
                    }

                    if ($pick === null) {
                        continue;
                    }

                    $t = $quota[$pick]['teacher'];

                    $rows[] = $wpdb->prepare(
                        '(%d, %d, %d, %d, %d, %s, 0, %s)',
                        $plan_id,
                        $class_id,
                        $day,
                        $p,
                        $pick,
                        $t > 0 ? (string) $t : null,
                        $now
                    );

                    $quota[$pick]['left']--;
                    $per_day[$day][$p]       = $pick;
                    $day_use[$day][$pick]    = ($day_use[$day][$pick] ?? 0) + 1;

                    if ($t > 0) {
                        $busy[$t . '|' . $day . '|' . $p] = true;
                        $load[$t . '|' . $day]            = ($load[$t . '|' . $day] ?? 0) + 1;
                    }

                    $placed++;
                }
            }

            $left = 0;
            foreach ($quota as $q) {
                $left += max(0, $q['left']);
            }

            if ($left > 0) {
                $missing += $left;
                // الرسالة بلغة الوكيل: يعرف ما ينقص ولماذا، لا «فشل التوليد».
                $blocked[$class_id] = sprintf(
                    /* translators: %d: عدد الحصص المتبقّية */
                    __('بقيت %d حصة بلا مكان — إمّا أن النصاب أكبر من سعة الأسبوع، أو أن معلميها محجوزون في شعب أخرى.', 'school-system'),
                    $left
                );
            }
        }

        if ($rows !== []) {
            // إدراجٌ واحد لكل الشعب — لا نداءُ قاعدةٍ لكل خانة (١٨٠٠ خانة تعني
            // ١٨٠٠ رحلة ذهاب وإياب، وهي وحدها ما يجعل الشاشة تبدو معلّقة).
            foreach (array_chunk($rows, 400) as $chunk) {
                $wpdb->query(
                    'INSERT INTO ' . sch_table('tt_slots') . '
                        (plan_id, class_id, day_of_week, period_no, subject_id, teacher_user_id, locked, created_at)
                     VALUES ' . implode(',', $chunk) . '
                     ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id), teacher_user_id = VALUES(teacher_user_id)'
                );
            }
        }

        sch_audit('tt.generated', 'tt_plan', $plan_id, ['classes' => count($class_ids), 'placed' => $placed]);

        return [
            'placed'  => $placed,
            'missing' => $missing,
            'classes' => count($class_ids),
            'blocked' => $blocked,
        ];
    }

    /**
     * أساسية أم إثرائية — يُقرأ من اسم المادة.
     *
     * لا عمود في القاعدة يقول ذلك، وإضافةُ عمودٍ تعني شاشةً ثالثة يملؤها أحد.
     * والقائمة تُوسَّع بكلمة، والمادة غير المعروفة تُعامَل إثرائية — وهو
     * الافتراض الأأمن: قيدُ «الأساسية أولًا» لا يُفرَض على ما لا نعرفه.
     */
    private static function is_core(string $name): bool
    {
        foreach (['قرآن', 'عربي', 'العربية', 'رياضيات', 'علوم', 'إسلام', 'اسلام', 'توحيد', 'فقه', 'إنجليز', 'انجليز', 'لغوية', 'عددية'] as $k) {
            if (mb_strpos($name, $k) !== false) {
                return true;
            }
        }

        return false;
    }

    /** مادة حركية — تُفضَّل آخر النهار لا قبل حصة رياضيات. */
    private static function is_active(string $name): bool
    {
        foreach (['بدني', 'رياضة', 'حرك', 'نشاط', 'لعب', 'موسيق'] as $k) {
            if (mb_strpos($name, $k) !== false) {
                return true;
            }
        }

        return false;
    }

    // ═══════════════════════════ النشر ═══════════════════════════

    /**
     * نشر الخطة: نسخُ خاناتها إلى الجدول المنشور — **داخل معاملة واحدة**.
     *
     * ألفا صفٍّ تُحذف وتُكتب؛ انقطاعٌ في المنتصف بلا معاملة يترك المدرسة بنصف
     * جدول: شُعبٌ بجدول جديد وشُعبٌ بلا جدول إطلاقًا، والمعلم يفتح تطبيقه
     * فيرى فراغًا. والفشل يُرجع كل شيء كما كان.
     *
     * @return array{slots:int,classes:int,msg:string}|WP_Error
     */
    public static function publish(int $plan_id): array|WP_Error
    {
        global $wpdb;

        $plan = self::get_plan($plan_id);
        if (!$plan) {
            return sch_api_error('no_plan', __('الخطة غير موجودة.', 'school-system'), 404);
        }

        $health = self::plan_health($plan_id);

        if ($health['slots'] === 0) {
            return sch_api_error('empty', __('لا حصص في الخطة — ولّد الجدول أولًا.', 'school-system'), 422);
        }
        if ($health['conflicts'] > 0) {
            return sch_api_error('conflicts', sprintf(
                /* translators: %d: عدد التعارضات */
                __('لا يُنشر جدول فيه %d تعارض معلم — عالجها أولًا.', 'school-system'),
                $health['conflicts']
            ), 409);
        }
        if ($health['unassigned'] > 0) {
            return sch_api_error('unassigned', sprintf(
                /* translators: %d: عدد الحصص */
                __('%d حصة بلا معلم — لا يعرف النظام من يغطّيها ولا لمن يُرسل إشعارها.', 'school-system'),
                $health['unassigned']
            ), 409);
        }

        $tt   = sch_table('timetable');
        $ts   = sch_table('tt_slots');
        $now  = sch_now();

        $wpdb->query('START TRANSACTION');

        // **القفل هو التحديث نفسه.** ضغطتان على «نشر» في ثانيةٍ واحدة كانتا
        // تمرّان معًا لأن الحارس فحصٌ ثم كتابة: كلتاهما تقرأ 'draft' فتكتب.
        // هنا الشرط داخل الـUPDATE، فالثانية تُصيب صفر صفوف وتُرفض.
        $won = $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('tt_plans') . "
             SET status = 'published', published_by = %d, published_at = %s, updated_at = %s
             WHERE id = %d AND status <> 'published'",
            get_current_user_id() ?: null,
            $now,
            $now,
            $plan_id
        ));

        if ((int) $won !== 1) {
            $wpdb->query('ROLLBACK');
            return sch_api_error('already', __('الخطة منشورة بالفعل — حدّث الصفحة.', 'school-system'), 409);
        }

        $classes = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT class_id FROM {$ts} WHERE plan_id = %d",
            $plan_id
        )) ?: [];

        if ($classes === []) {
            $wpdb->query('ROLLBACK');
            return sch_api_error('empty', __('لا شعب في الخطة.', 'school-system'), 422);
        }

        $in = implode(',', array_fill(0, count($classes), '%d'));

        $ok = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tt} WHERE class_id IN ({$in})",
            ...array_map('intval', $classes)
        ));

        if ($ok === false) {
            $wpdb->query('ROLLBACK');
            return sch_api_error('db_error', sprintf(
                /* translators: %s: رسالة قاعدة البيانات */
                __('تعذّر النشر — %s', 'school-system'),
                (string) $wpdb->last_error
            ), 500);
        }

        $ok = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$tt} (class_id, subject_id, teacher_user_id, day_of_week, period_no, created_at)
             SELECT class_id, subject_id, teacher_user_id, day_of_week, period_no, %s
             FROM {$ts} WHERE plan_id = %d",
            $now,
            $plan_id
        ));

        if ($ok === false) {
            $wpdb->query('ROLLBACK');
            return sch_api_error('db_error', sprintf(
                /* translators: %s: رسالة قاعدة البيانات */
                __('تعذّر النشر — %s', 'school-system'),
                (string) $wpdb->last_error
            ), 500);
        }

        // الخطط السابقة تُؤرشَف: خطتان منشورتان في مدى واحد تعنيان جدولين.
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('tt_plans') . "
             SET status = 'archived', updated_at = %s
             WHERE year_id = %d AND status = 'published' AND id <> %d",
            $now,
            (int) $plan->year_id,
            $plan_id
        ));

        $wpdb->query('COMMIT');

        sch_audit('tt.published', 'tt_plan', $plan_id, [
            'slots'   => $health['slots'],
            'classes' => count($classes),
        ]);

        return [
            'slots'   => $health['slots'],
            'classes' => count($classes),
            'msg'     => sprintf(
                /* translators: 1: عدد الحصص 2: عدد الشعب */
                __('نُشر الجدول: %1$d حصة في %2$d شعبة — ويراه المعلمون وأولياء الأمور الآن.', 'school-system'),
                $health['slots'],
                count($classes)
            ),
        ];
    }

    /**
     * نسخ جدول شعبة إلى شعب أخرى.
     *
     * تُنسَخ **المادة دون المعلم**: نسخ المعلم يعني حجزه في شعبتين في الحصة
     * نفسها — أي أن كل خانة منسوخة تولد تعارضًا. والوكيل يُسند بعدها.
     */
    public static function copy_to(int $plan_id, int $from_class, array $to_classes): int
    {
        global $wpdb;

        $src = $wpdb->get_results($wpdb->prepare(
            'SELECT day_of_week, period_no, subject_id FROM ' . sch_table('tt_slots') . '
             WHERE plan_id = %d AND class_id = %d',
            $plan_id,
            $from_class
        )) ?: [];

        if ($src === []) {
            return 0;
        }

        $done = 0;
        $now  = sch_now();

        foreach (array_unique(array_filter(array_map('absint', $to_classes))) as $cid) {
            if ($cid === $from_class) {
                continue;
            }

            self::wipe($plan_id, $cid, false);

            $rows = [];
            foreach ($src as $r) {
                $teacher = (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT teacher_user_id FROM ' . sch_table('tt_quota') . '
                     WHERE plan_id = %d AND class_id = %d AND subject_id = %d',
                    $plan_id,
                    $cid,
                    (int) $r->subject_id
                ));

                $rows[] = $wpdb->prepare(
                    '(%d, %d, %d, %d, %d, %s, 0, %s)',
                    $plan_id,
                    $cid,
                    (int) $r->day_of_week,
                    (int) $r->period_no,
                    (int) $r->subject_id,
                    $teacher > 0 ? (string) $teacher : null,
                    $now
                );
            }

            if ($rows !== []) {
                $wpdb->query(
                    'INSERT INTO ' . sch_table('tt_slots') . '
                        (plan_id, class_id, day_of_week, period_no, subject_id, teacher_user_id, locked, created_at)
                     VALUES ' . implode(',', $rows) . '
                     ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id), teacher_user_id = VALUES(teacher_user_id)'
                );
                $done++;
            }
        }

        sch_audit('tt.copied', 'tt_plan', $plan_id, ['from' => $from_class, 'to' => $done]);

        return $done;
    }

    // ═══════════════════════════ العدسات ═══════════════════════════

    /** جدول معلم عبر الشعب: [يوم][حصة] => صف. @return array<int,array<int,object>> */
    public static function teacher_grid(int $plan_id, int $teacher_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT s.*, sub.name AS subject_name, c.grade_level, c.section, c.stage
             FROM ' . sch_table('tt_slots') . ' s
             INNER JOIN ' . sch_table('subjects') . ' sub ON sub.id = s.subject_id
             INNER JOIN ' . sch_table('classes') . ' c ON c.id = s.class_id
             WHERE s.plan_id = %d AND s.teacher_user_id = %d',
            $plan_id,
            $teacher_id
        )) ?: [];

        $grid = [];
        foreach ($rows as $r) {
            $grid[(int) $r->day_of_week][(int) $r->period_no] = $r;
        }

        return $grid;
    }

    /**
     * خريطة المدرسة في لحظة: كل شعبة وما فيها في هذا اليوم وهذه الحصة.
     *
     * استعلامٌ واحد لكل الشعب — لا استعلامٌ لكل شعبة، فستّ وثلاثون شعبة
     * تعني ستًّا وثلاثين رحلة إلى القاعدة لعرضٍ واحد.
     *
     * @return array<int,object>
     */
    public static function school_map(int $plan_id, int $day, int $period): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT c.id AS class_id, c.stage, c.grade_level, c.section,
                    s.subject_id, s.teacher_user_id,
                    sub.name AS subject_name, u.display_name AS teacher_name
             FROM ' . sch_table('classes') . ' c
             LEFT JOIN ' . sch_table('tt_slots') . ' s
                    ON s.class_id = c.id AND s.plan_id = %d AND s.day_of_week = %d AND s.period_no = %d
             LEFT JOIN ' . sch_table('subjects') . ' sub ON sub.id = s.subject_id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = s.teacher_user_id
             WHERE c.year_id = %d
             ORDER BY c.stage, c.grade_level, c.section',
            $plan_id,
            $day,
            $period,
            SCH_Years::current_id()
        )) ?: [];
    }
}
