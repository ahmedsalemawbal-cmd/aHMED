<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * أولياء الأمور.
 * ولي الأمر = مستخدم ووردبريس بدور sch_guardian + صف في sch_guardians.
 * الربط بالأبناء في sch_guardian_student.
 */
final class SCH_Guardians
{
    public const RELATIONS = [
        'father'   => 'الأب',
        'mother'   => 'الأم',
        'brother'  => 'أخ',
        'sister'   => 'أخت',
        'uncle'    => 'عم/خال',
        'guardian' => 'وصي',
    ];

    /** @return array{id:int,user_id:int,login:string,password:string,name:string}|WP_Error */
    public static function create(array $input): array|WP_Error
    {
        global $wpdb;

        $parts = [
            'first_name'  => sanitize_text_field((string) ($input['first_name'] ?? '')),
            'father_name' => sanitize_text_field((string) ($input['father_name'] ?? '')),
            'grand_name'  => sanitize_text_field((string) ($input['grand_name'] ?? '')),
            'family_name' => sanitize_text_field((string) ($input['family_name'] ?? '')),
        ];

        // من يكتب الاسم الكامل في خانة الاسم الأول ثم يملأ الباقي يُنتج تكرارًا:
        // «أحمد سالم محمد عوبل سالم محمد عوبل». نُزيل المكرر بدل أن نعرضه.
        $words = [];
        foreach (array_filter($parts) as $part) {
            foreach (preg_split('/\s+/u', trim($part)) as $word) {
                if ($word !== '' && !in_array($word, $words, true)) {
                    $words[] = $word;
                }
            }
        }

        $name = implode(' ', $words);

        if ($name === '') {
            $name = sanitize_text_field((string) ($input['full_name'] ?? ''));
        }

        $phone = sch_normalize_phone((string) ($input['phone'] ?? ''));
        $email = sanitize_email((string) ($input['email'] ?? ''));

        if ($name === '') {
            return sch_api_error('missing_name', __('اسم ولي الأمر مطلوب.', 'school-system'), 422);
        }
        if ($phone === '') {
            return sch_api_error('missing_phone', __('رقم الجوال مطلوب — هو وسيلة الدخول للتطبيق.', 'school-system'), 422);
        }
        if (self::phone_exists($phone)) {
            return sch_api_error('phone_exists', __('رقم الجوال مسجّل لولي أمر آخر.', 'school-system'), 409);
        }

        $national = sanitize_text_field((string) ($input['national_id'] ?? ''));
        if ($national !== '' && preg_match('/^\d{10}$/', $national) !== 1) {
            return sch_api_error('bad_id', __('رقم الهوية أو الإقامة يجب أن يكون 10 أرقام.', 'school-system'), 422);
        }
        if ($national !== '' && self::national_exists($national)) {
            return sch_api_error('id_exists', __('رقم الهوية مسجّل لولي أمر آخر.', 'school-system'), 409);
        }

        // البريد اختياري — يُولَّد داخلي إن لم يُدخل، لأن ووردبريس يشترطه.
        if ($email === '' || !is_email($email)) {
            $email = $phone . '@guardian.local';
        }
        if (email_exists($email)) {
            return sch_api_error('email_exists', __('البريد مسجّل لمستخدم آخر.', 'school-system'), 409);
        }

        $password = wp_generate_password(10, false);

        $login = 'g' . $phone;

        $user_id = wp_insert_user([
            'user_login'   => $login,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name,
            'role'         => 'sch_guardian',
        ]);

        if (is_wp_error($user_id)) {
            return sch_api_error('user_error', $user_id->get_error_message(), 500);
        }

        $now    = sch_now();
        $first  = SCH_Enrollment::next_parent_no();
        $prefix = substr($first, 0, -4);
        $seq    = (int) substr($first, -4);

        $base = $parts + [
            'user_id'     => $user_id,
            'id_type'     => array_key_exists($input['id_type'] ?? '', SCH_Enrollment::ID_TYPES) ? $input['id_type'] : 'national',
            'city'        => sanitize_text_field((string) ($input['city'] ?? '')) ?: null,
            'national_id' => $national ?: null,
            'phone'       => $phone,
            'alt_phone'   => sch_normalize_phone((string) ($input['alt_phone'] ?? '')) ?: null,
            'workplace'   => sanitize_text_field((string) ($input['workplace'] ?? '')) ?: null,
            'address'     => sanitize_text_field((string) ($input['address'] ?? '')) ?: null,
            'status'      => 'active',
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        // رقم وليّ الأمر فريد — نجرّب أرقامًا متتابعة تحت التزامن، وإن تعذّر
        // نحذف حساب ووردبريس الذي أُنشئ لتوّه فلا يبقى يتيمًا بلا صفّ.
        $guardian_id = 0;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $ok = $wpdb->insert(sch_table('guardians'), $base + [
                'parent_no' => $prefix . str_pad((string) ($seq + $attempt), 4, '0', STR_PAD_LEFT),
            ]);

            if ($ok !== false) {
                $guardian_id = (int) $wpdb->insert_id;
                break;
            }
        }

        if ($guardian_id === 0) {
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            wp_delete_user($user_id);
            return sch_api_error('db_error', __('تعذّر إنشاء وليّ الأمر — أعد المحاولة.', 'school-system'), 500);
        }

        sch_audit('guardian.created', 'guardian', $guardian_id);

        return ['id' => $guardian_id, 'user_id' => (int) $user_id, 'login' => $login, 'password' => $password, 'name' => $name];
    }

    public static function update(int $id, array $input): bool|WP_Error
    {
        global $wpdb;

        $row = self::get($id);
        if (!$row) {
            return sch_api_error('not_found', __('ولي الأمر غير موجود.', 'school-system'), 404);
        }

        $fields = [];
        foreach (['national_id', 'workplace', 'address'] as $key) {
            if (isset($input[$key])) {
                $fields[$key] = sanitize_text_field((string) $input[$key]) ?: null;
            }
        }
        foreach (['phone', 'alt_phone'] as $key) {
            if (isset($input[$key])) {
                $fields[$key] = sch_normalize_phone((string) $input[$key]) ?: null;
            }
        }
        if (isset($input['status']) && in_array($input['status'], ['active', 'suspended'], true)) {
            $fields['status'] = $input['status'];
        }

        if ($fields !== []) {
            $fields['updated_at'] = sch_now();
            $wpdb->update(sch_table('guardians'), $fields, ['id' => $id]);
        }

        if (!empty($input['full_name'])) {
            wp_update_user([
                'ID'           => (int) $row->user_id,
                'display_name' => sanitize_text_field((string) $input['full_name']),
            ]);
        }

        sch_audit('guardian.updated', 'guardian', $id);
        return true;
    }

    /** ربط ولي أمر بطالب. */
    public static function link(int $guardian_user_id, int $student_id, string $relation = 'father', bool $is_primary = false): bool|WP_Error
    {
        global $wpdb;

        if (!SCH_Students::get($student_id)) {
            return sch_api_error('student_not_found', __('الطالب غير موجود.', 'school-system'), 404);
        }

        $exists = (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT 1 FROM ' . sch_table('guardian_student') . ' WHERE guardian_user_id = %d AND student_id = %d',
            $guardian_user_id,
            $student_id
        ));

        if ($exists) {
            return sch_api_error('already_linked', __('ولي الأمر مرتبط بهذا الطالب مسبقًا.', 'school-system'), 409);
        }

        if ($is_primary) {
            $wpdb->update(sch_table('guardian_student'), ['is_primary' => 0], ['student_id' => $student_id]);
        }

        $wpdb->insert(sch_table('guardian_student'), [
            'guardian_user_id' => $guardian_user_id,
            'student_id'       => $student_id,
            'relation'         => array_key_exists($relation, self::RELATIONS) ? $relation : 'guardian',
            'is_primary'       => $is_primary ? 1 : 0,
            // الافتراض «يستلم» يبقى كما هو فلا ينكسر شيء قائم — والإدارة تضيّقه
            // لمن لا يحقّ له. التوسيع الافتراضي أأمن من إقفال مدرسة على نفسها.
            'can_pickup'       => 1,
            'created_at'       => sch_now(),
        ]);

        sch_audit('guardian.linked', 'student', $student_id, ['guardian' => $guardian_user_id]);
        return true;
    }

    /**
     * منح حقّ استلام الطفل لوليّ أمر أو منعه.
     *
     * البُعد الذي كان مخزَّنًا في القاعدة منذ اليوم الأول ولا تقرأه شاشة ولا
     * يفحصه حارس. وكل تغيير يُسجَّل: من منح حقّ أخذ طفل لمن ومتى.
     */
    public static function set_pickup(int $student_id, int $guardian_user_id, bool $on): bool|WP_Error
    {
        global $wpdb;

        if ($student_id < 1 || $guardian_user_id < 1) {
            return sch_api_error('bad_args', __('بيانات ناقصة.', 'school-system'), 422);
        }

        $done = $wpdb->update(
            sch_table('guardian_student'),
            ['can_pickup' => $on ? 1 : 0],
            ['student_id' => $student_id, 'guardian_user_id' => $guardian_user_id]
        );

        if ($done === false) {
            return sch_api_error('db_error', __('تعذّر تحديث حقّ الاستلام.', 'school-system'), 500);
        }

        sch_audit('pickup.right_changed', 'student', $student_id, [
            'guardian' => $guardian_user_id,
            'can'      => $on ? 1 : 0,
        ]);

        return true;
    }

    public static function unlink(int $guardian_user_id, int $student_id): bool
    {
        global $wpdb;

        $wpdb->delete(sch_table('guardian_student'), [
            'guardian_user_id' => $guardian_user_id,
            'student_id'       => $student_id,
        ]);

        sch_audit('guardian.unlinked', 'student', $student_id, ['guardian' => $guardian_user_id]);
        return true;
    }

    /** أولياء أمور طالب معيّن. */
    public static function of_student(int $student_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT g.*, gs.relation, gs.is_primary, gs.can_pickup, u.display_name
             FROM ' . sch_table('guardian_student') . ' gs
             INNER JOIN ' . $wpdb->users . ' u ON u.ID = gs.guardian_user_id
             LEFT JOIN ' . sch_table('guardians') . ' g ON g.user_id = gs.guardian_user_id
             WHERE gs.student_id = %d
             ORDER BY gs.is_primary DESC',
            $student_id
        )) ?: [];
    }

    /** جوال ولي الأمر الأساسي لطالب. */
    public static function primary_phone(int $student_id): ?string
    {
        global $wpdb;

        $phone = $wpdb->get_var($wpdb->prepare(
            'SELECT g.phone FROM ' . sch_table('guardian_student') . ' gs
             INNER JOIN ' . sch_table('guardians') . ' g ON g.user_id = gs.guardian_user_id
             WHERE gs.student_id = %d AND g.phone IS NOT NULL
             ORDER BY gs.is_primary DESC LIMIT 1',
            $student_id
        ));

        return $phone ? (string) $phone : null;
    }

    /** أبناء ولي أمر مع شعبهم. */
    public static function children_of_guardian(int $guardian_user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.*, gs.relation, gs.is_primary, gs.can_pickup
             FROM ' . sch_table('guardian_student') . ' gs
             INNER JOIN ' . sch_table('students') . ' s ON s.id = gs.student_id
             WHERE gs.guardian_user_id = %d
             ORDER BY s.full_name',
            $guardian_user_id
        )) ?: [];
    }

    public static function by_user(int $user_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('guardians') . ' WHERE user_id = %d',
            $user_id
        )) ?: null;
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('guardians') . ' WHERE id = %d', $id));
        return $row ?: null;
    }

    /** @return array{items:array<int,object>,total:int} */
    public static function list(array $args = []): array
    {
        global $wpdb;

        $g     = sch_table('guardians');
        $users = $wpdb->users;

        $where  = ['1=1'];
        $params = [];

        if (!empty($args['search'])) {
            $where[] = '(u.display_name LIKE %s OR g.phone LIKE %s OR g.national_id LIKE %s OR g.parent_no LIKE %s)';
            $like    = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $params  = [...$params, $like, $like, $like, $like];
        }

        $clause   = implode(' AND ', $where);
        $per_page = min(100, max(1, (int) ($args['per_page'] ?? 50)));
        $offset   = (max(1, (int) ($args['page'] ?? 1)) - 1) * $per_page;

        $count_sql = "SELECT COUNT(*) FROM {$g} g INNER JOIN {$users} u ON u.ID = g.user_id WHERE {$clause}";
        $total     = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, ...$params) : $count_sql);

        $list_sql = "SELECT g.*, u.display_name, u.user_email,
                            (SELECT COUNT(*) FROM " . sch_table('guardian_student') . " gs
                              WHERE gs.guardian_user_id = g.user_id) AS children_count
                     FROM {$g} g
                     INNER JOIN {$users} u ON u.ID = g.user_id
                     WHERE {$clause}
                     ORDER BY u.display_name ASC
                     LIMIT %d OFFSET %d";

        $items = $wpdb->get_results($wpdb->prepare($list_sql, ...[...$params, $per_page, $offset]));

        return ['items' => $items ?: [], 'total' => $total];
    }

    public static function relation_label(?string $relation): string
    {
        return self::RELATIONS[$relation ?? ''] ?? __('ولي أمر', 'school-system');
    }

    private static function national_exists(string $national_id): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT 1 FROM ' . sch_table('guardians') . ' WHERE national_id = %s LIMIT 1',
            $national_id
        ));
    }

    private static function phone_exists(string $phone): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT 1 FROM ' . sch_table('guardians') . ' WHERE phone = %s LIMIT 1',
            $phone
        ));
    }
}
