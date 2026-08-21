<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الصلاحيات لكل موظف.
 *
 * الدور قالب لا قيد: تختاره فتُفتح مفاتيحه، ثم تعدّل ما تشاء لهذا الشخص وحده.
 * لأن المدارس الحقيقية لا تشبه القوالب — محاسب يدير المستودع، ومعلمة تدير
 * المكتبة بعد الحصص، ووكيل لا تريده أن يلمس الرواتب.
 *
 * ثلاث طبقات:
 * 1. **مفتوح** — تتحكم به كما تشاء.
 * 2. **حساس** — يُمنح فورًا، **ويصل إشعار للمدير**. لا بوابة تُغلق بل إيصال لا يُخفى؛
 *    طابور الموافقات يتعطّل ويحجب موظفًا، والمدير في مدرسة واحدة على بعد غرفتين.
 * 3. **مقفل** — لا يُفتح لأحد مهما كان. يظهر رماديًا **ومعه سببه** فيفهم الموظف
 *    لماذا مُنع بدل أن يظن أن فيه عطلًا.
 *
 * وبُعد ثانٍ لا يوجد في أنظمة الشركات: **على مَن؟** معلم يرى الحضور — حضور فصوله
 * أم المدرسة كلها؟ بلا هذا البُعد تفتح كل صلاحية المدرسة بأكملها.
 */
final class SCH_Perms
{
    /**
     * ذاكرة الطلب الواحد — `map` و`custom` و`expired` لكل مستخدم.
     *
     * @var array<string,mixed>
     */
    private static array $memo = [];

    /** نطاق البيانات — البُعد الثاني للصلاحية. */
    public const SCOPES = [
        'all'   => 'كل المدرسة',
        'stage' => 'مرحلته فقط',
        'own'   => 'فصوله وطلابه فقط',
    ];

    /** أقسام لا تُمنح لأحد إطلاقًا، ومعها سببها. */
    public const LOCKED = [
        'custody' => 'سجل العهدة لا يُعدَّل — الخطأ يُصحَّح بحدث مضاد لا بمحو، وسجل يُمحى لا يصلح دليلًا',
        'audit'   => 'سجل النظام للقراءة فقط — تعديله يُبطل قيمته كسجل',
    ];

    /**
     * أقسام لا تُمنح لأدوار بعينها.
     *
     * القيم **أسماء أقسام حقيقية** من سجلّ أقسام الداشبورد. وكانت
     * فيها `referrals` (شاشةٌ حلّ محلّها `inbox`) و`accounting` (اسمٌ لا
     * قسم له؛ الأقسام أربعة: `accounts` و`journal` و`ledger` و`reports`) —
     * واسمٌ لا يطابق قسمًا لا يمنع شيئًا: كان دفتر الأستاذ يُمنَح للحارس
     * والسائق رغم أن القاعدة تقول غير ذلك.
     */
    public const LOCKED_FOR = [
        'sch_guard'  => ['clinic', 'meds', 'inbox', 'nerve', 'payroll',
                         'accounts', 'journal', 'ledger', 'reports', 'finance'],
        'sch_driver' => ['clinic', 'meds', 'inbox', 'nerve', 'payroll',
                         'accounts', 'journal', 'ledger', 'reports', 'finance'],
    ];

    /** سبب المنع حسب الدور. */
    public const LOCKED_FOR_REASON =
        'بيانات صحية أو سلوكية أو مالية لا يراها من يقف عند البوابة أو يقود الحافلة';

    /**
     * أقسام تُمنح فورًا ويصل إشعار للمدير.
     *
     * كانت تحمل ثلاثة أسماء لا أقسام لها — `accounting` و`org` و`invoices` —
     * فكان منح المحاسبة والفواتير يمرّ **بلا إشعار** ولا تمييزٍ في الشاشة،
     * وهو عكس المقصود تمامًا. والفواتير قسمُها `finance`.
     */
    public const SENSITIVE = [
        'payroll', 'employees', 'perms', 'settings', 'rollover',
        'accounts', 'journal', 'ledger', 'reports', 'finance',
    ];

    // ---------- القراءة ----------

    /**
     * هل لهذا المستخدم صلاحيات مخصصة؟
     * من ليس له صف واحد يعمل بصلاحيات دوره كما كان — فلا ينكسر شيء قائم.
     */
    public static function is_custom(int $user_id): bool
    {
        global $wpdb;

        $key = 'custom|' . $user_id;

        if (isset(self::$memo[$key])) {
            return (bool) self::$memo[$key];
        }

        return self::$memo[$key] = (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT 1 FROM ' . sch_table('user_perms') . ' WHERE user_id = %d LIMIT 1',
            $user_id
        ));
    }

    /** خريطة صلاحيات مستخدم: section => ['view' => bool, 'edit' => bool] */
    public static function map(int $user_id): array
    {
        global $wpdb;

        $key = 'map|' . $user_id;

        if (isset(self::$memo[$key])) {
            return (array) self::$memo[$key];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT section, can_view, can_edit FROM ' . sch_table('user_perms') . ' WHERE user_id = %d',
            $user_id
        )) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->section] = [
                'view' => (int) $r->can_view === 1,
                'edit' => (int) $r->can_edit === 1,
            ];
        }

        return self::$memo[$key] = $out;
    }

    /**
     * هل يملك المستخدم هذا القسم؟
     *
     * القفلان نوعان ولا يُخلطان:
     *
     * **قفل الدور** (`LOCKED_FOR`) يمنع الرؤية والتعديل معًا — من يقف عند
     * البوابة لا يرى ملفًّا صحّيًّا أصلًا، ومنعُ التعديل وحده لا يحميه.
     *
     * **قفل «للقراءة فقط»** (`LOCKED`) يمنع التعديل ويُبقي الرؤية. وكان
     * يمنع الاثنين، فيردّ `/dashboard/custody` و`/dashboard/audit` بـ403
     * **لكل مستخدم بمن فيهم المدير** — والسببان المكتوبان في `LOCKED`
     * يقولان عكس ذلك حرفًا: «سجل العهدة لا يُعدَّل» و«سجل النظام للقراءة
     * فقط». شاشتان كاملتان كانتا خارج الخدمة.
     */
    /*
     * **تُنادى خمسين مرّة في كل رسمة صفحة** — مرّةً لكل قسمٍ في الشريط
     * الجانبي (`SCH_Dashboard::groups()`)، ثم مرّةً أخرى لأقسام الإعدادات.
     * وكانت `is_custom()` و`expired()` تفتحان استعلامًا في كل نداء بلا
     * ذاكرة: **مئةٌ وستّة وسبعون استعلامًا لرسم الشريط الجانبي وحده**،
     * قبل أن تُقرأ بيانةٌ واحدة من بيانات الشاشة.
     *
     * وكل ضغطة زرٍّ في الداشبورد رسمتان (إرسالٌ ثم تحويلٌ ثم تحميل) — أي
     * ثلاثمئة واثنان وخمسون. وهذا وحده يفسّر «التحميل يدور ويدور».
     *
     * والصلاحيات لا تتغيّر داخل الطلب الواحد، و`save()` تُفرّغ الذاكرة
     * بنفسها. و`map()` كانت محفوظةً هكذا منذ بُنيت — فالنقص كان في أختيها.
     */
    public static function may(string $section, string $mode = 'view', ?int $user_id = null): bool
    {
        $user_id ??= get_current_user_id();

        if ($user_id === 0) {
            return false;
        }

        if (self::locked_for_role($section, $user_id)) {
            return false;
        }

        if ($mode === 'edit' && isset(self::LOCKED[$section])) {
            return false;
        }

        if (self::expired($user_id)) {
            return false;
        }

        // بلا صلاحيات مخصصة: يعمل بدوره كما كان قبل هذه الوحدة.
        if (!self::is_custom($user_id)) {
            return true;
        }

        $map = self::map($user_id);

        if (!isset($map[$section])) {
            return false;
        }

        return $mode === 'edit' ? $map[$section]['edit'] : $map[$section]['view'];
    }

    /**
     * هل هذا القسم مقفل عن المنح؟
     *
     * تُستعمل في شاشة الصلاحيات وفي الحفظ: المقفل لا يُمنَح لأحد، سواء أكان
     * قفلَ دورٍ أم قفل «للقراءة فقط» — فمنحُ «تعديل» في قسمٍ لا يُعدَّل بلا
     * معنى. أما الرؤية فتُقرّرها `may()` وحدها.
     */
    public static function is_locked(string $section, ?int $user_id = null): bool
    {
        return isset(self::LOCKED[$section]) || self::locked_for_role($section, $user_id);
    }

    /** قفلٌ سببه دور المستخدم — يمنع الرؤية والتعديل معًا. */
    public static function locked_for_role(string $section, ?int $user_id = null): bool
    {
        $user = get_userdata($user_id ?? get_current_user_id());

        if (!$user) {
            return false;
        }

        foreach ((array) $user->roles as $role) {
            if (in_array($section, self::LOCKED_FOR[$role] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /** سبب القفل — يُعرض في الشاشة فيفهم الموظف لماذا مُنع. */
    public static function lock_reason(string $section, string $role = ''): string
    {
        if (isset(self::LOCKED[$section])) {
            return self::LOCKED[$section];
        }

        if ($role !== '' && in_array($section, self::LOCKED_FOR[$role] ?? [], true)) {
            return self::LOCKED_FOR_REASON;
        }

        return '';
    }

    // ---------- النطاق ----------

    public static function scope(?int $user_id = null): string
    {
        global $wpdb;

        $user_id ??= get_current_user_id();

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT scope, expires_at FROM ' . sch_table('user_scope') . ' WHERE user_id = %d',
            $user_id
        ));

        if (!$row) {
            return 'all';
        }

        return array_key_exists((string) $row->scope, self::SCOPES) ? (string) $row->scope : 'all';
    }

    /**
     * فصول المستخدم — للنطاق «فصوله وطلابه»: ما هو مربّيه + ما يُدرّس فيه.
     * @return int[]
     */
    public static function class_ids_for(?int $user_id = null): array
    {
        global $wpdb;

        $user_id ??= get_current_user_id();
        if ($user_id === 0) {
            return [];
        }

        // والجدول المنشور طرفٌ ثالث: `class_subjects` تُبنى الآن مع النشر،
        // لكن مدرسةً نشرت جدولها قبل هذه النسخة لا صفوف لها فيه — ونطاقٌ
        // فارغ يعني معلّمًا لا يرى طالبًا واحدًا. و«فصولي» في تطبيق المعلم
        // تقرأ الثلاثة منذ v9، فيتّفق البابان.
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . sch_table('classes') . ' WHERE homeroom_teacher_id = %d
             UNION
             SELECT class_id FROM ' . sch_table('class_subjects') . ' WHERE teacher_user_id = %d
             UNION
             SELECT class_id FROM ' . sch_table('timetable') . ' WHERE teacher_user_id = %d',
            $user_id,
            $user_id,
            $user_id
        ));

        return array_values(array_unique(array_map('intval', $ids ?: [])));
    }

    /**
     * مراحل المستخدم — للنطاق «مرحلته فقط»: مراحل طلاب فصوله.
     * @return string[]
     */
    public static function stages_for(?int $user_id = null): array
    {
        global $wpdb;

        $ids = self::class_ids_for($user_id);
        if ($ids === []) {
            return [];
        }

        $in   = implode(',', array_map('intval', $ids));
        $rows = $wpdb->get_col(
            'SELECT DISTINCT s.stage FROM ' . sch_table('students') . ' s
             INNER JOIN ' . sch_table('enrollments') . " e ON e.student_id = s.id AND e.status = 'active'
             WHERE e.class_id IN ($in) AND s.stage IS NOT NULL AND s.stage <> ''"
        );

        return array_values(array_filter(array_map('strval', $rows ?: [])));
    }

    /** انتهت مدة صلاحياته؟ معلم بديل لأسبوع، ووكيل ينوب أثناء سفر المدير. */
    public static function expired(?int $user_id = null): bool
    {
        global $wpdb;

        $user_id ??= get_current_user_id();
        $key      = 'expired|' . $user_id;

        if (isset(self::$memo[$key])) {
            return (bool) self::$memo[$key];
        }

        $until = $wpdb->get_var($wpdb->prepare(
            'SELECT expires_at FROM ' . sch_table('user_scope') . ' WHERE user_id = %d',
            $user_id
        ));

        return self::$memo[$key] = $until !== null && (string) $until !== '' && (string) $until < current_time('Y-m-d');
    }

    // ---------- الكتابة ----------

    /**
     * إسقاط ما حُفظ في ذاكرة الطلب لمستخدم.
     *
     * ثلاث ذاكرات ساكنة (`map` · `is_custom` · `expired`) تُملأ عند أوّل
     * قراءة. وتُفرَّغ عند الكتابة وحدها: القراءة لا تُبطلها، والطلب ينتهي
     * فتذهب معه.
     */
    public static function forget(int $user_id): void
    {
        foreach (['map', 'custom', 'expired'] as $kind) {
            unset(self::$memo[$kind . '|' . $user_id]);
        }
    }

    /**
     * حفظ صلاحيات موظف.
     *
     * @param array $sections  section => ['view' => bool, 'edit' => bool]
     */
    public static function save(int $user_id, array $sections, string $scope = 'all', string $expires = ''): true|WP_Error
    {
        global $wpdb;

        if ($user_id === get_current_user_id()) {
            return sch_api_error('self_edit', __('لا تعدّل صلاحيات حسابك — اطلب ذلك ممن هو أعلى منك.', 'school-system'), 403);
        }

        $target = get_userdata($user_id);
        if (!$target) {
            return sch_api_error('no_user', __('الموظف غير موجود.', 'school-system'), 404);
        }

        // لا تمنح من هو في مستواك أو فوقك — نفس قاعدة سلسلة الإنشاء.
        if (!current_user_can('manage_options') && SCH_Org::level_of($target) >= SCH_Org::level_of()) {
            return sch_api_error('forbidden', __('لا تملك صلاحية تعديل صلاحيات هذا الموظف.', 'school-system'), 403);
        }

        $before  = self::map($user_id);
        $added   = [];
        $granted = [];

        // ما بعد هذا السطر يقرأ حالةً جديدة — والذاكرة المحفوظة تسبقها.
        self::forget($user_id);

        $wpdb->delete(sch_table('user_perms'), ['user_id' => $user_id]);

        foreach ($sections as $slug => $modes) {
            $slug = sanitize_key((string) $slug);

            if (!isset(SCH_Dashboard::sections()[$slug]) || self::is_locked($slug, $user_id)) {
                continue;
            }

            $view = !empty($modes['view']) || !empty($modes['edit']);
            $edit = !empty($modes['edit']);

            if (!$view) {
                continue;
            }

            $wpdb->insert(sch_table('user_perms'), [
                'user_id'  => $user_id,
                'section'  => $slug,
                'can_view' => 1,
                'can_edit' => $edit ? 1 : 0,
            ]);

            $granted[] = $slug;

            if (in_array($slug, self::SENSITIVE, true) && empty($before[$slug]['view'])) {
                $added[] = SCH_Dashboard::sections()[$slug][0];
            }
        }

        // توسيع لا مجرد تضييق: القسم الممنوح يحتاج قدرة دوره الأساسية كي يعبر فحص
        // `current_user_can` في الموجّه والشريط الجانبي. بلا هذا يُمنَح القسم في القاعدة
        // ثم يُحجب لأن دور الموظف لا يحمل القدرة — فتبدو الصلاحيات «ناقصة» عمّا مُنح.
        self::sync_caps($target, $granted);

        $wpdb->replace(sch_table('user_scope'), [
            'user_id'    => $user_id,
            'scope'      => array_key_exists($scope, self::SCOPES) ? $scope : 'all',
            'expires_at' => sch_sanitize_date($expires) ?: null,
            'updated_by' => get_current_user_id() ?: null,
            'updated_at' => sch_now(),
        ]);

        self::log($user_id, $before, $sections);

        // الإيصال: صلاحية حساسة تُمنح فورًا، والمدير يعرف بها فورًا.
        if ($added !== []) {
            $principal = SCH_Org::principal_user_id();

            if ($principal && $principal !== get_current_user_id()) {
                SCH_Comms::notify(
                    $principal,
                    __('مُنحت صلاحية حساسة', 'school-system'),
                    sprintf(
                        /* translators: 1: من منح 2: لمن 3: الأقسام */
                        __('%1$s منح %2$s: %3$s', 'school-system'),
                        wp_get_current_user()->display_name,
                        $target->display_name,
                        implode(' · ', $added)
                    ),
                    'perm',
                    $user_id
                );
            }
        }

        return true;
    }

    /** نسخ صلاحيات موظف إلى آخر — عيّنت محاسبًا ثانيًا؟ ثانيتان. */
    public static function copy_from(int $source_id, int $target_id): true|WP_Error
    {
        $map = self::map($source_id);

        if ($map === []) {
            return sch_api_error('empty', __('الموظف المصدر يعمل بصلاحيات دوره، فلا شيء يُنسخ.', 'school-system'), 422);
        }

        return self::save($target_id, $map, self::scope($source_id));
    }

    /**
     * مزامنة قدرات ووردبريس للمستخدم مع أقسامه الممنوحة.
     *
     * القسم الممنوح يكسب قدرة دوره الأساسية على مستوى المستخدم (توسيع يعبر
     * `current_user_can`)، وما لم يُمنح تُزال إضافته على مستوى المستخدم فيعود
     * لأصل دوره — بلا مسّ قدرات الدور نفسه (طبقة فوق لا بديلًا عنه).
     *
     * @param string[] $granted_slugs
     */
    private static function sync_caps(WP_User $user, array $granted_slugs): void
    {
        $sections = SCH_Dashboard::sections();

        $need = [];
        foreach ($granted_slugs as $slug) {
            $cap = (string) ($sections[$slug][1] ?? '');
            if ($cap !== '') {
                $need[$cap] = true;
            }
        }

        $managed = [];
        foreach ($sections as $meta) {
            $cap = (string) ($meta[1] ?? '');
            if ($cap !== '') {
                $managed[$cap] = true;
            }
        }

        foreach (array_keys($managed) as $cap) {
            if (isset($need[$cap])) {
                $user->add_cap($cap, true);   // توسيع: القدرة تُمنح على مستوى المستخدم
            } else {
                $user->remove_cap($cap);      // إزالة توسيع سابق — يعود لأصل الدور
            }
        }
    }

    /** العودة لصلاحيات الدور: حذف الصفوف وإزالة أي توسيع مُنح على مستوى المستخدم. */
    public static function reset(int $user_id): void
    {
        global $wpdb;

        $wpdb->delete(sch_table('user_perms'), ['user_id' => $user_id]);
        $wpdb->delete(sch_table('user_scope'), ['user_id' => $user_id]);

        $user = get_userdata($user_id);
        if ($user instanceof WP_User) {
            self::sync_caps($user, []);
        }
    }

    /**
     * إعادة مزامنة قدرات كل من له صلاحيات مخصصة — تُستدعى مرة عند الترقية،
     * فتسري الأقسام الممنوحة قبل هذا الإصلاح بلا حاجة لإعادة حفظها يدويًا.
     */
    public static function resync_all(): void
    {
        global $wpdb;

        if (!class_exists('SCH_Dashboard')) {
            return;
        }

        $ids = $wpdb->get_col('SELECT DISTINCT user_id FROM ' . sch_table('user_perms'));

        foreach ($ids ?: [] as $uid) {
            $user = get_userdata((int) $uid);
            if ($user instanceof WP_User) {
                self::sync_caps($user, array_keys(self::map((int) $uid)));
            }
        }
    }

    /** قالب الدور: ما يفتحه الدور افتراضيًا — نقطة بداية لا قيد. */
    public static function template(string $role): array
    {
        $out = [];

        foreach (SCH_Dashboard::sections() as $slug => $meta) {
            $cap = (string) $meta[1];

            if ($cap !== '' && !self::role_has_cap($role, $cap)) {
                continue;
            }

            if (isset(self::LOCKED[$slug]) || in_array($slug, self::LOCKED_FOR[$role] ?? [], true)) {
                continue;
            }

            $out[$slug] = ['view' => true, 'edit' => true];
        }

        return $out;
    }

    private static function role_has_cap(string $role, string $cap): bool
    {
        $obj = get_role($role);

        return $obj !== null && $obj->has_cap($cap);
    }

    // ---------- السجل ----------

    private static function log(int $target, array $before, array $after): void
    {
        global $wpdb;

        $granted = [];
        $revoked = [];

        foreach (SCH_Dashboard::sections() as $slug => $meta) {
            $had = !empty($before[$slug]['view']);
            $has = !empty($after[$slug]['view']) || !empty($after[$slug]['edit']);

            if (!$had && $has) {
                $granted[] = (string) $meta[0];
            }
            if ($had && !$has) {
                $revoked[] = (string) $meta[0];
            }
        }

        if ($granted === [] && $revoked === []) {
            return;
        }

        $wpdb->insert(sch_table('perm_log'), [
            'target_user' => $target,
            'actor_user'  => get_current_user_id() ?: null,
            'granted'     => implode(' · ', $granted) ?: null,
            'revoked'     => implode(' · ', $revoked) ?: null,
            'created_at'  => sch_now(),
        ]);

        sch_audit('perms.changed', 'user', $target, [
            'granted' => count($granted),
            'revoked' => count($revoked),
        ]);
    }

    public static function history(int $target = 0, int $limit = 40): array
    {
        global $wpdb;

        $sql = 'SELECT l.*, t.display_name AS target_name, a.display_name AS actor_name
                FROM ' . sch_table('perm_log') . " l
                LEFT JOIN {$wpdb->users} t ON t.ID = l.target_user
                LEFT JOIN {$wpdb->users} a ON a.ID = l.actor_user ";

        return ($target > 0
            ? $wpdb->get_results($wpdb->prepare($sql . 'WHERE l.target_user = %d ORDER BY l.id DESC LIMIT %d', $target, $limit))
            : $wpdb->get_results($wpdb->prepare($sql . 'ORDER BY l.id DESC LIMIT %d', $limit))) ?: [];
    }

    // ---------- العرض ----------

    /** الأقسام مجمّعة كما تظهر في الشاشة. */
    public static function grouped(): array
    {
        $out = [];

        foreach (SCH_Dashboard::sections() as $slug => $meta) {
            $card  = (string) ($meta[2] ?? 'school');
            $group = (string) ($meta[3] ?? '');
            $key   = $card . '|' . $group;

            $out[$key][$slug] = $meta;
        }

        return $out;
    }

    /** ما سيراه هذا الموظف فعلًا — تراجعه بنظرة قبل الحفظ. */
    public static function preview(int $user_id): array
    {
        $out = [];

        foreach (SCH_Dashboard::sections() as $slug => $meta) {
            if (self::may($slug, 'view', $user_id)) {
                $out[] = [
                    'title' => (string) $meta[0],
                    'edit'  => self::may($slug, 'edit', $user_id),
                ];
            }
        }

        return $out;
    }
}
