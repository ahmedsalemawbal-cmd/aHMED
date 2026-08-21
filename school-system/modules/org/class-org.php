<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الهيكل التنظيمي.
 *
 * مبدأ واحد يحكم كل ما هنا:
 * **من يملك القرار يملك الإضافة، ومن يملك التنفيذ يملك التفاصيل.**
 *
 * المدير يوقّع · الوكيل يخطّط · المشرفون ينفّذون يوميًا · المعلمون يدرّسون.
 */
final class SCH_Org
{
    /** ترتيب السلطة. لا أحد يُنشئ من هو في مستواه أو فوقه. */
    public const LEVELS = [
        'administrator'  => 100,
        'sch_principal'  => 80,
        'sch_deputy'     => 60,
        'sch_supervisor' => 40,
        'sch_counselor'  => 30,
        'sch_content'    => 30,
        'sch_transport'  => 30,
        'sch_accountant' => 30,
        'sch_staff'      => 20,
        'sch_teacher'    => 20,
        'sch_guard'      => 10,
        'sch_driver'     => 10,
    ];

    /** نطاقات الإشراف — مبنية على المراحل لأنها لا تتداخل أبدًا. */
    public const SCOPES = [
        'none'   => 'بلا نطاق',
        'early'  => 'ما قبل الابتدائي والابتدائي',
        'middle' => 'متوسط',
        'high'   => 'ثانوي',
        'all'    => 'كل المراحل',
    ];

    /**
     * المرحلة في جدول الشعب => نطاق الإشراف.
     *
     * وكانت مفاتيحها `middle` و`high` — **وهما ليستا من مفردات النظام**:
     * `SCH_Classes::STAGES` تقول `kg · primary · intermediate · secondary`.
     * فالمرحلتان الأخيرتان تسقطان خارج الخريطة، وكان الافتراض `'early'`
     * يفتحهما بدل أن يُغلقهما: **مشرفة الابتدائي تجتاز `may_touch_class()`
     * لكل شعبة في المدرسة بما فيها الثانوي**، ومشرف المتوسط لا يجتازها
     * لشعبةٍ واحدة — وهو نطاقه هو. و`supervisor_of_stage()` تُوجّه إشعارات
     * المرحلتين إلى مشرف الروضة.
     *
     * والمفاتيح الآن مفردات `SCH_Classes::STAGES` حرفًا بحرف.
     */
    private const STAGE_MAP = [
        'nursery'      => 'early',
        'kg'           => 'early',
        'prep'         => 'early',
        'primary'      => 'early',
        'intermediate' => 'middle',
        'secondary'    => 'high',
    ];

    // ---------- سلسلة الإنشاء ----------

    public static function level_of(?WP_User $user = null): int
    {
        $user ??= wp_get_current_user();

        if (!$user instanceof WP_User) {
            return 0;
        }

        $max = 0;
        foreach ($user->roles as $role) {
            $max = max($max, self::LEVELS[$role] ?? 0);
        }

        return $max;
    }

    /**
     * هل يستطيع المستخدم الحالي إنشاء هذا الدور؟
     *
     * القيد ليس شكليًا: بلاه سيصنع موظف لنفسه صلاحيات أعلى بعد شهرين ولن تعرف.
     */
    public static function can_create(string $role): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        $mine   = self::level_of();
        $target = self::LEVELS[$role] ?? 0;

        return $mine > 0 && $target > 0 && $target < $mine;
    }

    /** الأدوار التي يستطيع المستخدم الحالي إنشاءها — تُعرض في نموذج الإضافة. */
    public static function creatable_roles(): array
    {
        $labels = SCH_Staff::ROLES ?? [];
        $out    = [];

        foreach ($labels as $role => $label) {
            if (self::can_create($role)) {
                $out[$role] = $label;
            }
        }

        return $out;
    }

    public static function guard_create(string $role): true|WP_Error
    {
        if (self::can_create($role)) {
            return true;
        }

        return sch_api_error('forbidden_role', sprintf(
            /* translators: %s: اسم الدور */
            __('لا تملك صلاحية إنشاء «%s» — لا يُنشأ من هو في مستواك أو فوقه.', 'school-system'),
            SCH_Staff::ROLES[$role] ?? $role
        ), 403);
    }

    // ---------- النطاقات ----------

    /**
     * نطاق الإشراف لمرحلة — والمجهول **يُغلَق لا يُفتَح**.
     *
     * كان الافتراض `'early'`: مرحلةٌ لا تعرفها الخريطة تُعامَل معاملة
     * الابتدائي، فتُفتح لمشرفه. والافتراض الآمن في الصلاحيات هو المنع.
     */
    public static function scope_of_stage(?string $stage): string
    {
        return self::STAGE_MAP[$stage ?? ''] ?? 'none';
    }

    /** نطاق المستخدم الحالي: مشرف مرحلة، أم كل المدرسة؟ */
    public static function my_scope(): string
    {
        // النطاق المخصص يعلو على الدور: مربية صف تُفتح لها الشاشات وترى طلابها وحدهم.
        if (class_exists('SCH_Perms')) {
            $custom = SCH_Perms::scope();

            if ($custom === 'own') {
                return 'own';
            }
        }

        if (current_user_can('sch_manage_deputy') || current_user_can('manage_options')) {
            return 'all';
        }

        $employee = SCH_Staff::get_by_user(get_current_user_id());

        return (string) ($employee->stage_scope ?? 'none');
    }

    /** المراحل التي يغطيها نطاق. */
    public static function stages_in(string $scope): array
    {
        if ($scope === 'all') {
            return array_keys(self::STAGE_MAP);
        }

        return array_keys(array_filter(
            self::STAGE_MAP,
            static fn (string $s): bool => $s === $scope
        ));
    }

    /** مشرف مرحلة معيّنة — إليه تُوجَّه الحصص والإنذارات. */
    public static function supervisor_of_stage(?string $stage): ?int
    {
        global $wpdb;

        $scope = self::scope_of_stage($stage);

        $user_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT e.user_id FROM " . sch_table('employees') . " e
             WHERE e.status = 'active' AND e.stage_scope IN (%s, 'all')
             ORDER BY FIELD(e.stage_scope, %s, 'all') LIMIT 1",
            $scope,
            $scope
        ));

        // لا مشرف للنطاق؟ الوكيل يتولّاه — لا تبقى مرحلة بلا صاحب.
        return $user_id > 0 ? $user_id : self::deputy_user_id();
    }

    /** مشرف شعبة — الحصة تتبع الشعبة لا المعلم. */
    public static function supervisor_of_class(int $class_id): ?int
    {
        $class = SCH_Classes::get($class_id);

        return self::supervisor_of_stage($class->stage ?? null);
    }

    public static function deputy_user_id(): ?int
    {
        $users = get_users(['role' => 'sch_deputy', 'number' => 1, 'fields' => 'ID']);

        return $users !== [] ? (int) $users[0] : null;
    }

    public static function principal_user_id(): ?int
    {
        $users = get_users(['role' => 'sch_principal', 'number' => 1, 'fields' => 'ID']);

        return $users !== [] ? (int) $users[0] : null;
    }

    /** المشرفون الثلاثة مع نطاقاتهم — تُعرض في شاشة الوكيل. */
    public static function supervisors(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT e.user_id, e.stage_scope, e.job_title, u.display_name, e.phone
             FROM " . sch_table('employees') . " e
             INNER JOIN {$wpdb->users} u ON u.ID = e.user_id
             WHERE e.status = 'active' AND e.stage_scope <> 'none'
             ORDER BY FIELD(e.stage_scope, 'early', 'middle', 'high', 'all')"
        ) ?: [];
    }

    /** هل نطاق المستخدم يشمل هذه الشعبة؟ */
    public static function may_touch_class(int $class_id): bool
    {
        $scope = self::my_scope();

        if ($scope === 'all') {
            return true;
        }

        $class = SCH_Classes::get($class_id);

        if ($class === null) {
            return false;
        }

        $class_scope = self::scope_of_stage($class->stage);

        // مرحلةٌ مجهولة لا تُطابق أحدًا — ولا من نطاقه `none` أيضًا.
        return $class_scope !== 'none' && $class_scope === $scope;
    }

    // ---------- مراحل الجدول ----------

    public const TT_STATES = [
        'draft'     => 'مسودة',
        'pending'   => 'بانتظار اعتماد المدير',
        'published' => 'منشور',
    ];

    /**
     * حالة جدول السنة.
     *
     * وكُتّاب `tt_status` كانوا ثلاثة أفعالٍ من تدفّق v10.6 **لا نموذج لأيّها
     * في المشروع كلّه** — حُذفت، وصار `SCH_TT::publish()` يرفع الراية داخل
     * معاملته: النشر والراية حقيقةٌ واحدة، وفصلهما هو ما جعل الدالّة كاذبةً
     * أبدًا ومعها «ما فاتك» وسكّة «اليوم».
     */
    public static function timetable_state(): object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT tt_status, tt_sent_by, tt_sent_at, tt_ok_by, tt_ok_at
             FROM ' . sch_table('academic_years') . ' WHERE id = %d',
            SCH_Years::current_id()
        ));

        return $row ?: (object) ['tt_status' => 'draft', 'tt_sent_at' => null, 'tt_ok_at' => null];
    }

    public static function timetable_published(): bool
    {
        return self::timetable_state()->tt_status === 'published';
    }




    /** ما يمنع النشر: تعارض معلم، أو حصة بلا معلم. */
    public static function timetable_problems(): array
    {
        global $wpdb;

        $year_id = SCH_Years::current_id();

        $conflicts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (
                 SELECT t.teacher_user_id, t.day_of_week, t.period_no
                 FROM " . sch_table('timetable') . " t
                 INNER JOIN " . sch_table('classes') . " c ON c.id = t.class_id
                 WHERE c.year_id = %d AND t.teacher_user_id IS NOT NULL
                 GROUP BY t.teacher_user_id, t.day_of_week, t.period_no
                 HAVING COUNT(*) > 1
             ) x",
            $year_id
        ));

        $unassigned = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('timetable') . " t
             INNER JOIN " . sch_table('classes') . " c ON c.id = t.class_id
             WHERE c.year_id = %d AND t.teacher_user_id IS NULL",
            $year_id
        ));

        $slots = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('timetable') . " t
             INNER JOIN " . sch_table('classes') . " c ON c.id = t.class_id
             WHERE c.year_id = %d",
            $year_id
        ));

        return ['conflicts' => $conflicts, 'unassigned' => $unassigned, 'slots' => $slots];
    }

    // ---------- النصاب ----------

    /** نصاب كل معلم مقابل حدّه — يظهر أثناء البناء لا بعد شهرين من الشكاوى. */
    public static function teacher_load(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID AS user_id, u.display_name, e.weekly_quota, e.stage_scope,
                    (SELECT COUNT(*) FROM " . sch_table('timetable') . " t
                      INNER JOIN " . sch_table('classes') . " c ON c.id = t.class_id
                      WHERE t.teacher_user_id = u.ID AND c.year_id = %d) AS periods
             FROM " . sch_table('employees') . " e
             INNER JOIN {$wpdb->users} u ON u.ID = e.user_id
             WHERE e.status = 'active'
             HAVING periods > 0 OR e.stage_scope = 'none'
             ORDER BY periods DESC",
            SCH_Years::current_id()
        )) ?: [];
    }

    // ---------- جدول المعلم ----------

    /** حصص المعلم اليوم — ما يفتحه في تطبيقه وهو واقف في الممر. */
    public static function today_periods(int $teacher_id): array
    {
        global $wpdb;

        if (!self::timetable_published()) {
            return [];
        }

        // السبت يوم دوامٍ في مدارس، و`SCH_TT::WEEK` يدعمه منذ بُني المحرّك —
        // وكانت هذه الصيغة تُسقطه فيرى معلّم السبت يومًا فارغًا وجدولُه ممتلئ.
        $day = SCH_Timetable::school_day();

        if ($day === 0) {
            return [];
        }

        $date = current_time('Y-m-d');

        // الاحتياط يعلو على الجدول: من أُسند اليوم يظهر في مكانه.
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.period_no, c.stage, c.grade_level, c.section, s.name AS subject_name, 0 AS is_sub
             FROM " . sch_table('timetable') . " t
             INNER JOIN " . sch_table('classes') . " c ON c.id = t.class_id
             INNER JOIN " . sch_table('subjects') . " s ON s.id = t.subject_id
             WHERE t.teacher_user_id = %d AND t.day_of_week = %d
               AND NOT EXISTS (
                     SELECT 1 FROM " . sch_table('substitutions') . " sb
                     WHERE sb.sub_date = %s AND sb.class_id = t.class_id
                       AND sb.period_no = t.period_no AND sb.status = 'assigned'
                   )

             UNION ALL

             SELECT sb.period_no, c2.stage, c2.grade_level, c2.section, s2.name AS subject_name, 1 AS is_sub
             FROM " . sch_table('substitutions') . " sb
             INNER JOIN " . sch_table('classes') . " c2 ON c2.id = sb.class_id
             LEFT JOIN " . sch_table('subjects') . " s2 ON s2.id = sb.subject_id
             WHERE sb.substitute_teacher_id = %d AND sb.sub_date = %s AND sb.status = 'assigned'

             ORDER BY period_no",
            $teacher_id,
            $day,
            $date,
            $teacher_id,
            $date
        )) ?: [];
    }
}
