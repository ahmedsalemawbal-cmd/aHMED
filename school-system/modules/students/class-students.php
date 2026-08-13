<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * طبقة بيانات الطلاب.
 * كل استعلام SQL يخص الطلاب موجود هنا وحده — لا في الشاشات ولا في الـAPI.
 */
final class SCH_Students
{
    /** @return array{id:int}|WP_Error */
    public static function create(array $input): array|WP_Error
    {
        global $wpdb;

        $name = sanitize_text_field($input['full_name'] ?? '');
        if ($name === '') {
            return sch_api_error('missing_name', __('اسم الطالب مطلوب.', 'school-system'), 422);
        }

        $national = isset($input['national_id']) ? sanitize_text_field((string) $input['national_id']) : null;
        if ($national !== null && $national !== '' && self::national_id_exists($national)) {
            return sch_api_error('duplicate_national_id', __('رقم الهوية مسجّل لطالب آخر.', 'school-system'), 409);
        }

        $now = sch_now();
        $ok  = $wpdb->insert(sch_table('students'), [
            'full_name'   => $name,
            'national_id' => $national ?: null,
            'birth_date'  => self::sanitize_date($input['birth_date'] ?? null),
            'gender'      => in_array($input['gender'] ?? '', ['male', 'female'], true) ? $input['gender'] : null,
            'stage'       => sanitize_text_field((string) ($input['stage'] ?? '')) ?: null,
            'grade_level' => sanitize_text_field((string) ($input['grade_level'] ?? '')) ?: null,
            'section'     => sanitize_text_field((string) ($input['section'] ?? '')) ?: null,
            'qr_token'    => sch_generate_qr_token(),
            'status'      => 'active',
            'notes'       => isset($input['notes']) ? sanitize_textarea_field((string) $input['notes']) : null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        if ($ok === false) {
            // رسالة عامة تُضيّع ساعات: نُعيد سبب القاعدة نفسه، فمن يقرأه يعرف
            // إن كان الجدول ناقصًا أو العمود مفقودًا بدل أن يخمّن.
            $reason = trim((string) $wpdb->last_error);

            return sch_api_error(
                'db_error',
                $reason !== ''
                    ? sprintf(
                        /* translators: %s: رسالة قاعدة البيانات */
                        __('تعذّر حفظ الطالب — %s', 'school-system'),
                        $reason
                    )
                    : __('تعذّر حفظ الطالب.', 'school-system'),
                500
            );
        }

        $id = (int) $wpdb->insert_id;
        sch_audit('student.created', 'student', $id, ['name' => $name]);

        return ['id' => $id];
    }

    public static function update(int $id, array $input): bool|WP_Error
    {
        global $wpdb;

        $fields = [];
        foreach (['full_name', 'stage', 'grade_level', 'section'] as $key) {
            if (isset($input[$key])) {
                $fields[$key] = sanitize_text_field((string) $input[$key]);
            }
        }
        if (isset($input['status']) && in_array($input['status'], ['active', 'transferred', 'withdrawn', 'graduated'], true)) {
            $fields['status'] = $input['status'];
        }
        if ($fields === []) {
            return true;
        }

        $fields['updated_at'] = sch_now();
        $done = $wpdb->update(sch_table('students'), $fields, ['id' => $id]);

        if ($done === false) {
            return sch_api_error('db_error', __('تعذّر تحديث الطالب.', 'school-system'), 500);
        }

        sch_audit('student.updated', 'student', $id, array_keys($fields));
        return true;
    }

    /** تعليم بطاقات كمطبوعة — فلا تُطبع مرتين ولا تُنسى واحدة. */
    public static function mark_printed(array $ids): int
    {
        global $wpdb;

        $ids = array_values(array_filter(array_map('absint', $ids)));

        if ($ids === []) {
            return 0;
        }

        $in = implode(',', array_fill(0, count($ids), '%d'));

        return (int) $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('students') . " SET badge_printed_at = %s WHERE id IN ({$in})",
            array_merge([sch_now()], $ids)
        ));
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('students') . ' WHERE id = %d',
            $id
        ));
        return $row ?: null;
    }

    /** البحث بالرقم الأكاديمي أو رقم الطالب — كلاهما مقبول. */
    public static function get_by_academic(string $number): ?object
    {
        global $wpdb;

        $number = trim($number);
        if ($number === '') {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('students') . '
             WHERE academic_no = %s OR student_no = %s OR national_id = %s
             LIMIT 1',
            $number,
            $number,
            $number
        ));

        return $row ?: null;
    }

    public static function get_by_qr(string $token): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('students') . ' WHERE qr_token = %s AND status = %s',
            $token,
            'active'
        ));
        return $row ?: null;
    }

    /**
     * @param array{search?:string,status?:string,grade_level?:string,page?:int,per_page?:int} $args
     * @return array{items:array<int,object>,total:int}
     */
    public static function list(array $args = []): array
    {
        global $wpdb;

        // الشروط تُبنى ببادئة `s.` من أولها: بعد الضمّ صارت أسماء الأعمدة
        // موجودة في جدولين، والبادئة لاحقًا بتعبير نمطي هشّة وتخطئ.
        $where  = ['1=1'];
        $params = [];

        if (!empty($args['search'])) {
            $where[]  = '(s.full_name LIKE %s OR s.national_id LIKE %s)';
            $like     = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($args['status'])) {
            $where[]  = 's.status = %s';
            $params[] = (string) $args['status'];
        }

        // العروض المحفوظة: شريحة واحدة تُغني عن خمسة حقول تصفية.
        switch ((string) ($args['view'] ?? '')) {
            case 'unmarked':
                $where[]  = 's.id NOT IN (SELECT student_id FROM ' . sch_table('attendance') . ' WHERE att_date = %s)';
                $params[] = current_time('Y-m-d');
                break;

            case 'overdue':
                $where[] = "s.id IN (SELECT student_id FROM " . sch_table('invoices') . "
                                   WHERE (total - paid) > 0 AND status <> 'paid')";
                break;

            case 'transport':
                $where[] = "s.id IN (SELECT student_id FROM " . sch_table('transport_subs') . "
                                   WHERE status = 'active')";
                break;

            case 'watched':
                $where[]  = 's.id IN (SELECT student_id FROM ' . sch_table('watchlist') . ' WHERE user_id = %d)';
                $params[] = get_current_user_id();
                break;
        }
        if (!empty($args['stage'])) {
            $where[]  = 's.stage = %s';
            $params[] = (string) $args['stage'];
        }
        if (!empty($args['section'])) {
            $where[]  = 's.section = %s';
            $params[] = (string) $args['section'];
        }
        if (!empty($args['grade_level'])) {
            $where[]  = 's.grade_level = %s';
            $params[] = (string) $args['grade_level'];
        }
        if (!empty($args['class_id'])) {
            $where[]  = 's.id IN (SELECT student_id FROM ' . sch_table('enrollments')
                        . " WHERE class_id = %d AND status = 'active')";
            $params[] = (int) $args['class_id'];
        }

        $clause = implode(' AND ', $where);
        $per_page = min(100, max(1, (int) ($args['per_page'] ?? 20)));
        $page     = max(1, (int) ($args['page'] ?? 1));
        $offset   = ($page - 1) * $per_page;

        $table = sch_table('students');

        $count_sql = "SELECT COUNT(*) FROM {$table} s WHERE {$clause}";
        $total     = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, ...$params) : $count_sql);

        // ═══ الشعبة وولي الأمر والنقل تُضمّ هنا لا تُستدعى لكل صف ═══
        // القائمة كانت تفتح 301 استعلامًا لمئة طالب: واحدًا لها وثلاثة لكل صف.
        // والضمّ يجعلها واحدًا. `with` تُطفأ لمن لا يحتاجها (مثل القوائم المنسدلة).
        if (empty($args['with']) && ($args['with'] ?? null) !== null) {
            $list_sql = "SELECT s.* FROM {$table} s WHERE {$clause} ORDER BY s.full_name ASC LIMIT %d OFFSET %d";
        } else {
            $en = sch_table('enrollments');
            $cl = sch_table('classes');
            $gs = sch_table('guardian_student');
            $tb = sch_table('transport_subs');
            $rt = sch_table('routes');

            $list_sql = "SELECT s.*,
                                c.grade_level AS cls_grade, c.section AS cls_section, c.stage AS cls_stage,
                                c.id AS cls_id,
                                (SELECT u.display_name
                                   FROM {$gs} g
                                   INNER JOIN {$wpdb->users} u ON u.ID = g.guardian_user_id
                                  WHERE g.student_id = s.id
                                  ORDER BY g.is_primary DESC, g.id ASC LIMIT 1) AS guardian_name,
                                (SELECT r.name
                                   FROM {$tb} t
                                   INNER JOIN {$rt} r ON r.id = t.route_id
                                  WHERE t.student_id = s.id AND t.status = 'active'
                                  LIMIT 1) AS route_name
                           FROM {$table} s
                           LEFT JOIN {$en} e ON e.student_id = s.id AND e.status = 'active'
                           LEFT JOIN {$cl} c ON c.id = e.class_id
                          WHERE {$clause}
                          ORDER BY s.full_name ASC LIMIT %d OFFSET %d";
        }

        $items = $wpdb->get_results($wpdb->prepare($list_sql, ...[...$params, $per_page, $offset]));

        return ['items' => $items ?: [], 'total' => $total];
    }

    /** أبناء ولي أمر معيّن. */
    public static function children_of(int $guardian_user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.* FROM ' . sch_table('students') . ' s
             INNER JOIN ' . sch_table('guardian_student') . ' g ON g.student_id = s.id
             WHERE g.guardian_user_id = %d AND s.status = %s
             ORDER BY s.full_name ASC',
            $guardian_user_id,
            'active'
        )) ?: [];
    }

    /** طلاب شعبة نشطون. */
    public static function in_class(int $class_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.* FROM ' . sch_table('enrollments') . ' e
             INNER JOIN ' . sch_table('students') . ' s ON s.id = e.student_id
             WHERE e.class_id = %d AND e.status = %s AND s.status = %s
             ORDER BY s.full_name',
            $class_id,
            'active',
            'active'
        )) ?: [];
    }

    public static function guardian_has_child(int $guardian_user_id, int $student_id): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT 1 FROM ' . sch_table('guardian_student') . '
             WHERE guardian_user_id = %d AND student_id = %d LIMIT 1',
            $guardian_user_id,
            $student_id
        ));
    }

    /** الشكل المُرسل للتطبيقات — لا يُرجع أعمدة القاعدة كما هي. */
    public static function to_api(object $row): array
    {
        return [
            'id'          => (int) $row->id,
            'full_name'   => $row->full_name,
            'stage'       => $row->stage,
            'grade_level' => $row->grade_level,
            'section'     => $row->section,
            'status'      => $row->status,
        ];
    }

    /** تسجيل الطالب في شعبة للسنة الحالية. طالب واحد = تسجيل واحد لكل سنة. */
    public static function enroll(int $student_id, int $class_id): bool|WP_Error
    {
        global $wpdb;

        $class = SCH_Classes::get($class_id);
        if (!$class) {
            return sch_api_error('class_not_found', __('الشعبة غير موجودة.', 'school-system'), 404);
        }
        if (!SCH_Classes::has_room($class_id)) {
            return sch_api_error('class_full', __('الشعبة مكتملة العدد.', 'school-system'), 409);
        }

        $existing = self::current_enrollment($student_id);

        if ($existing) {
            $wpdb->update(
                sch_table('enrollments'),
                ['class_id' => $class_id, 'status' => 'active'],
                ['id' => (int) $existing->id]
            );
        } else {
            $wpdb->insert(sch_table('enrollments'), [
                'student_id' => $student_id,
                'class_id'   => $class_id,
                'year_id'    => (int) $class->year_id,
                'status'     => 'active',
                'created_at' => sch_now(),
            ]);
        }

        // نسخة مبسّطة على سجل الطالب لتسريع القوائم.
        $wpdb->update(sch_table('students'), [
            'stage'       => $class->stage,
            'grade_level' => $class->grade_level,
            'section'     => $class->section,
            'updated_at'  => sch_now(),
        ], ['id' => $student_id]);

        sch_audit('student.enrolled', 'student', $student_id, ['class' => $class_id]);
        return true;
    }

    public static function current_enrollment(int $student_id): ?object
    {
        global $wpdb;

        $year_id = SCH_Years::current_id();
        if ($year_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('enrollments') . ' WHERE student_id = %d AND year_id = %d LIMIT 1',
            $student_id,
            $year_id
        ));

        return $row ?: null;
    }

    /** الشعبة الحالية للطالب، أو null إن لم يُسجَّل بعد. */
    public static function current_class(int $student_id): ?object
    {
        $enrollment = self::current_enrollment($student_id);
        return $enrollment ? SCH_Classes::get((int) $enrollment->class_id) : null;
    }

    private static function national_id_exists(string $national_id): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT 1 FROM ' . sch_table('students') . ' WHERE national_id = %s LIMIT 1',
            $national_id
        ));
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
