<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * طبقة بيانات الموظفين.
 * الموظف = مستخدم ووردبريس بدور sch_* + صف في جدول sch_employees للبيانات الوظيفية.
 */
final class SCH_Staff
{
    public const ROLES = [
        'sch_principal'            => 'مدير المدرسة',
        'sch_deputy'               => 'وكيل الشؤون التعليمية',
        'sch_supervisor'           => 'مشرف مرحلة',
        'sch_counselor'            => 'أخصائي اجتماعي',
        'sch_content'              => 'منسق المحتوى التعليمي',
        'sch_teacher'              => 'معلم',
        'sch_accountant'           => 'محاسب',
        'sch_transport_supervisor' => 'مشرف نقل',
        'sch_driver'               => 'سائق',
        'sch_guard'                => 'حارس البوابة',
        'sch_staff'                => 'موظف إداري',
    ];

    /** @return array{id:int,user_id:int,login:string,password:string,name:string}|WP_Error */
    public static function create(array $input): array|WP_Error
    {
        global $wpdb;

        $name  = sanitize_text_field((string) ($input['full_name'] ?? ''));
        $email = sanitize_email((string) ($input['email'] ?? ''));
        $role  = (string) ($input['role'] ?? '');

        if ($name === '') {
            return sch_api_error('missing_name', __('اسم الموظف مطلوب.', 'school-system'), 422);
        }
        if (!is_email($email)) {
            return sch_api_error('invalid_email', __('البريد الإلكتروني غير صحيح.', 'school-system'), 422);
        }
        if (!array_key_exists($role, self::ROLES)) {
            return sch_api_error('invalid_role', __('الدور المحدد غير معروف.', 'school-system'), 422);
        }

        // سلسلة الإنشاء: لا أحد يُنشئ من هو في مستواه أو فوقه.
        // بلا هذا القيد سيصنع موظف لنفسه صلاحيات أعلى بعد شهرين ولن تعرف.
        $allowed = SCH_Org::guard_create($role);
        if (is_wp_error($allowed)) {
            return $allowed;
        }
        if (email_exists($email)) {
            return sch_api_error('email_exists', __('البريد مسجّل لمستخدم آخر.', 'school-system'), 409);
        }

        $password = (string) ($input['password'] ?? '');
        if (strlen($password) < 10) {
            $password = wp_generate_password(14, true, false);
        }

        $login = self::unique_login($email);

        $user_id = wp_insert_user([
            'user_login'   => $login,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name,
            'first_name'   => $name,
            'role'         => $role,
        ]);

        if (is_wp_error($user_id)) {
            return sch_api_error('user_error', $user_id->get_error_message(), 500);
        }

        $now = sch_now();
        $wpdb->insert(sch_table('employees'), [
            'user_id'     => $user_id,
            'employee_no' => sanitize_text_field((string) ($input['employee_no'] ?? '')) ?: null,
            'job_title'   => sanitize_text_field((string) ($input['job_title'] ?? '')) ?: null,
            'stage_scope' => $role === 'sch_supervisor'
                ? (array_key_exists($input['stage_scope'] ?? '', SCH_Org::SCOPES) ? $input['stage_scope'] : 'early')
                : 'none',
            'weekly_quota' => max(1, min(40, (int) ($input['weekly_quota'] ?? 24))),
            'department'  => sanitize_text_field((string) ($input['department'] ?? '')) ?: null,
            'phone'       => sanitize_text_field((string) ($input['phone'] ?? '')) ?: null,
            'national_id' => sanitize_text_field((string) ($input['national_id'] ?? '')) ?: null,
            'hire_date'   => self::sanitize_date($input['hire_date'] ?? null),
            'badge_token' => sch_generate_qr_token(),
            'status'      => 'active',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        sch_audit('employee.created', 'employee', (int) $wpdb->insert_id, ['role' => $role]);

        return ['id' => (int) $wpdb->insert_id, 'user_id' => (int) $user_id, 'login' => $login, 'password' => $password, 'name' => $name];
    }

    public static function update(int $id, array $input): bool|WP_Error
    {
        global $wpdb;

        $row = self::get($id);
        if (!$row) {
            return sch_api_error('not_found', __('الموظف غير موجود.', 'school-system'), 404);
        }

        $fields = [];
        foreach (['employee_no', 'job_title', 'department', 'phone', 'national_id'] as $key) {
            if (isset($input[$key])) {
                $fields[$key] = sanitize_text_field((string) $input[$key]) ?: null;
            }
        }
        if (isset($input['status']) && in_array($input['status'], ['active', 'suspended', 'left'], true)) {
            $fields['status'] = $input['status'];
        }

        if ($fields !== []) {
            $fields['updated_at'] = sch_now();
            $wpdb->update(sch_table('employees'), $fields, ['id' => $id]);
        }

        // تغيير الدور
        if (!empty($input['role']) && array_key_exists($input['role'], self::ROLES)) {
            $user = get_user_by('id', (int) $row->user_id);
            if ($user instanceof WP_User && !in_array('administrator', $user->roles, true)) {
                $user->set_role((string) $input['role']);
            }
        }

        if (!empty($input['full_name'])) {
            wp_update_user([
                'ID'           => (int) $row->user_id,
                'display_name' => sanitize_text_field((string) $input['full_name']),
            ]);
        }

        sch_audit('employee.updated', 'employee', $id, array_keys($fields));
        return true;
    }

    /** إيقاف موظف: تعطيل الوصول بلا حذف سجله. */
    public static function suspend(int $id): bool|WP_Error
    {
        $row = self::get($id);
        if (!$row) {
            return sch_api_error('not_found', __('الموظف غير موجود.', 'school-system'), 404);
        }

        self::update($id, ['status' => 'suspended']);
        SCH_Auth::revoke_all_sessions((int) $row->user_id);
        sch_audit('employee.suspended', 'employee', $id);

        return true;
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('employees') . ' WHERE id = %d',
            $id
        ));
        return $row ?: null;
    }

    public static function get_by_user(int $user_id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('employees') . ' WHERE user_id = %d',
            $user_id
        ));
        return $row ?: null;
    }

    /**
     * @return array{items:array<int,object>,total:int}
     */
    public static function list(array $args = []): array
    {
        global $wpdb;

        $emp   = sch_table('employees');
        $users = $wpdb->users;

        $where  = ['1=1'];
        $params = [];

        if (!empty($args['search'])) {
            $where[]  = '(u.display_name LIKE %s OR u.user_email LIKE %s OR e.employee_no LIKE %s)';
            $like     = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $params   = [...$params, $like, $like, $like];
        }
        if (!empty($args['status'])) {
            $where[]  = 'e.status = %s';
            $params[] = (string) $args['status'];
        }

        $clause   = implode(' AND ', $where);
        $per_page = min(100, max(1, (int) ($args['per_page'] ?? 25)));
        $offset   = (max(1, (int) ($args['page'] ?? 1)) - 1) * $per_page;

        $count_sql = "SELECT COUNT(*) FROM {$emp} e INNER JOIN {$users} u ON u.ID = e.user_id WHERE {$clause}";
        $total     = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, ...$params) : $count_sql);

        $list_sql = "SELECT e.*, u.display_name, u.user_email
                     FROM {$emp} e
                     INNER JOIN {$users} u ON u.ID = e.user_id
                     WHERE {$clause}
                     ORDER BY u.display_name ASC
                     LIMIT %d OFFSET %d";

        $items = $wpdb->get_results($wpdb->prepare($list_sql, ...[...$params, $per_page, $offset]));

        // الدور يُقرأ من ووردبريس لا من جدولنا — مصدر واحد للحقيقة.
        foreach ($items ?: [] as $item) {
            $user        = get_user_by('id', (int) $item->user_id);
            $item->role  = $user instanceof WP_User ? (string) ($user->roles[0] ?? '') : '';
        }

        return ['items' => $items ?: [], 'total' => $total];
    }

    /**
     * قائمة الموظفين لطباعة البطاقات — الموظفون النشطون فقط، بأسمائهم وأدوارهم،
     * مرتّبين بالدور ثم الاسم فتُطبع كل مجموعة معًا. بلا ترقيم صفحات: بطاقات لا سجل.
     *
     * @return array<int,object>
     */
    public static function badges(array $args = []): array
    {
        global $wpdb;

        $emp   = sch_table('employees');
        $users = $wpdb->users;

        $items = $wpdb->get_results(
            "SELECT e.id, e.user_id, e.employee_no, e.job_title, e.department,
                    e.badge_token, e.badge_printed_at, u.display_name
             FROM {$emp} e
             INNER JOIN {$users} u ON u.ID = e.user_id
             WHERE e.status = 'active'
             ORDER BY u.display_name ASC"
        ) ?: [];

        // الدور من ووردبريس لا من جدولنا — مصدر واحد للحقيقة.
        foreach ($items as $item) {
            $user       = get_user_by('id', (int) $item->user_id);
            $item->role = $user instanceof WP_User ? (string) ($user->roles[0] ?? '') : '';
        }

        // تصفية اختيارية بالدور
        $role = (string) ($args['role'] ?? '');
        if ($role !== '') {
            $items = array_values(array_filter($items, static fn (object $e): bool => $e->role === $role));
        }

        return $items;
    }

    /** تعليم بطاقات الموظفين كمطبوعة — كبطاقات الطلاب: نطبع ثم نُعلّم. */
    public static function mark_printed(array $ids): int
    {
        global $wpdb;

        $ids = array_values(array_filter(array_map('absint', $ids)));
        if ($ids === []) {
            return 0;
        }

        $in = implode(',', array_fill(0, count($ids), '%d'));

        return (int) $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('employees') . " SET badge_printed_at = %s WHERE id IN ({$in})",
            array_merge([sch_now()], $ids)
        ));
    }

    public static function role_label(string $role): string
    {
        if ($role === 'administrator') {
            return __('مدير النظام', 'school-system');
        }
        return self::ROLES[$role] ?? $role;
    }

    private static function unique_login(string $email): string
    {
        $base  = sanitize_user(current(explode('@', $email)), true) ?: 'staff';
        $login = $base;
        $i     = 1;
        while (username_exists($login)) {
            $login = $base . ++$i;
        }
        return $login;
    }

    private static function sanitize_date(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value ? $value : null;
    }
}
