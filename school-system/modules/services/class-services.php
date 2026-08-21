<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** الصحة المدرسية. */
final class SCH_Clinic
{
    public static function record(array $d): array|WP_Error
    {
        global $wpdb;

        $student_id = absint($d['student_id'] ?? 0);
        $complaint  = sanitize_text_field((string) ($d['complaint'] ?? ''));

        if (!SCH_Students::get($student_id)) {
            return sch_api_error('student_not_found', __('الطالب غير موجود.', 'school-system'), 404);
        }
        if ($complaint === '') {
            return sch_api_error('missing_complaint', __('وصف الحالة مطلوب.', 'school-system'), 422);
        }

        $sent_home = !empty($d['sent_home']);

        $wpdb->insert(sch_table('clinic_visits'), [
            'student_id'  => $student_id,
            'visit_at'    => sch_sanitize_datetime($d['visit_at'] ?? null) ?? sch_now(),
            'complaint'   => $complaint,
            'action_note' => sanitize_text_field((string) ($d['action_note'] ?? '')) ?: null,
            'sent_home'   => $sent_home ? 1 : 0,
            'recorded_by' => get_current_user_id() ?: null,
            'created_at'  => sch_now(),
        ]);

        $student = SCH_Students::get($student_id);

        foreach (SCH_Guardians::of_student($student_id) as $g) {
            SCH_Comms::notify(
                (int) $g->user_id,
                $sent_home ? __('زيارة صحية — طلب استلام الطالب', 'school-system') : __('زيارة صحية', 'school-system'),
                sprintf('%s — %s', $student->full_name ?? '', $complaint),
                'clinic',
                $student_id
            );
        }

        sch_audit('clinic.visit', 'student', $student_id);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function visits(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT v.*, s.full_name FROM ' . sch_table('clinic_visits') . ' v
             INNER JOIN ' . sch_table('students') . ' s ON s.id = v.student_id
             ORDER BY v.visit_at DESC LIMIT %d',
            $limit
        )) ?: [];
    }

    public static function of_student(int $student_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('clinic_visits') . ' WHERE student_id = %d ORDER BY visit_at DESC LIMIT 50',
            $student_id
        )) ?: [];
    }
}

/** المكتبة: الكتب والإعارة. */
final class SCH_Library
{
    public static function add_book(array $d): array|WP_Error
    {
        global $wpdb;

        $title = sanitize_text_field((string) ($d['title'] ?? ''));
        if ($title === '') {
            return sch_api_error('missing_title', __('عنوان الكتاب مطلوب.', 'school-system'), 422);
        }

        $wpdb->insert(sch_table('books'), [
            'title'      => $title,
            'author'     => sanitize_text_field((string) ($d['author'] ?? '')) ?: null,
            'isbn'       => sanitize_text_field((string) ($d['isbn'] ?? '')) ?: null,
            'category'   => sanitize_text_field((string) ($d['category'] ?? '')) ?: null,
            'copies'     => max(1, (int) ($d['copies'] ?? 1)),
            'created_at' => sch_now(),
        ]);

        return ['id' => (int) $wpdb->insert_id];
    }

    public static function books(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM ' . sch_table('book_loans') . ' l
                      WHERE l.book_id = b.id AND l.returned_at IS NULL) AS out_count
             FROM ' . sch_table('books') . ' b ORDER BY b.title'
        ) ?: [];
    }

    public static function loan(array $d): bool|WP_Error
    {
        global $wpdb;

        $book_id    = absint($d['book_id'] ?? 0);
        $student_id = absint($d['student_id'] ?? 0);

        $book = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('books') . ' WHERE id = %d', $book_id));
        if (!$book || !SCH_Students::get($student_id)) {
            return sch_api_error('not_found', __('الكتاب أو الطالب غير موجود.', 'school-system'), 404);
        }

        $out = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('book_loans') . ' WHERE book_id = %d AND returned_at IS NULL',
            $book_id
        ));

        if ($out >= (int) $book->copies) {
            return sch_api_error('no_copies', __('لا توجد نسخ متاحة من هذا الكتاب.', 'school-system'), 409);
        }

        $today = current_time('Y-m-d');

        $wpdb->insert(sch_table('book_loans'), [
            'book_id'    => $book_id,
            'student_id' => $student_id,
            'loaned_at'  => $today,
            'due_date'   => sch_sanitize_date($d['due_date'] ?? null) ?? gmdate('Y-m-d', strtotime($today . ' +14 days')),
            'created_at' => sch_now(),
        ]);

        return true;
    }

    public static function give_back(int $loan_id): bool
    {
        global $wpdb;

        $wpdb->update(
            sch_table('book_loans'),
            ['returned_at' => current_time('Y-m-d')],
            ['id' => $loan_id, 'returned_at' => null]
        );

        return true;
    }

    /** إعارات طالب — مفتوحة ومنتهية. */
    public static function loans_of_student(int $student_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT l.*, b.title, b.author FROM ' . sch_table('book_loans') . ' l
             INNER JOIN ' . sch_table('books') . ' b ON b.id = l.book_id
             WHERE l.student_id = %d
             ORDER BY l.returned_at IS NOT NULL, l.due_date DESC
             LIMIT 40',
            $student_id
        )) ?: [];
    }

    public static function open_loans(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT l.*, b.title, s.full_name FROM ' . sch_table('book_loans') . ' l
             INNER JOIN ' . sch_table('books') . ' b ON b.id = l.book_id
             INNER JOIN ' . sch_table('students') . ' s ON s.id = l.student_id
             WHERE l.returned_at IS NULL ORDER BY l.due_date'
        ) ?: [];
    }
}

/** الزوار والأمن والسلامة. */
final class SCH_Security
{
    public const INCIDENT_TYPES = [
        'safety'   => 'سلامة',
        'injury'   => 'إصابة',
        'drill'    => 'تمرين إخلاء',
        'security' => 'أمن',
        'other'    => 'أخرى',
    ];

    public const SEVERITIES = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية'];

    public static function check_in(array $d): array|WP_Error
    {
        global $wpdb;

        $name = sanitize_text_field((string) ($d['full_name'] ?? ''));
        if ($name === '') {
            return sch_api_error('missing_name', __('اسم الزائر مطلوب.', 'school-system'), 422);
        }

        $wpdb->insert(sch_table('visitors'), [
            'full_name'    => $name,
            'national_id'  => sanitize_text_field((string) ($d['national_id'] ?? '')) ?: null,
            'phone'        => sch_normalize_phone((string) ($d['phone'] ?? '')) ?: null,
            'purpose'      => sanitize_text_field((string) ($d['purpose'] ?? '')) ?: null,
            'student_id'   => absint($d['student_id'] ?? 0) ?: null,
            'host_user_id' => get_current_user_id() ?: null,
            'checked_in'   => sch_now(),
            'created_at'   => sch_now(),
        ]);

        sch_audit('visitor.in', 'visitor', (int) $wpdb->insert_id);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function check_out(int $id): bool
    {
        global $wpdb;

        $wpdb->update(
            sch_table('visitors'),
            ['checked_out' => sch_now()],
            ['id' => $id, 'checked_out' => null]
        );

        sch_audit('visitor.out', 'visitor', $id);
        return true;
    }

    public static function visitors(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT v.*, s.full_name AS student_name FROM ' . sch_table('visitors') . ' v
             LEFT JOIN ' . sch_table('students') . ' s ON s.id = v.student_id
             ORDER BY v.checked_in DESC LIMIT %d',
            $limit
        )) ?: [];
    }

    /**
     * من هو بالداخل الآن — بالتصفية في القاعدة لا في PHP.
     *
     * كانت الشاشة تجلب آخر ٥٠ زائرًا ثم تُبقي من لم يخرج منهم. فمن دخل
     * صباحًا ولم يُسجَّل خروجه يسقط من النافذة بعد خمسين زائرًا بعده —
     * **فلا يراه أحد ولا يُخرجه أحد**، ويظلّ عدّاد «بالداخل الآن» يتصاعد
     * أبدًا وهو مبنيّ على استعلامٍ آخر يعدّ الصفوف كلّها. والأقدم أولًا:
     * من طال بقاؤه هو من يُسأل عنه.
     */
    public static function visitors_inside(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT v.*, s.full_name AS student_name FROM ' . sch_table('visitors') . ' v
             LEFT JOIN ' . sch_table('students') . ' s ON s.id = v.student_id
             WHERE v.checked_out IS NULL
             ORDER BY v.checked_in ASC'
        ) ?: [];
    }

    public static function inside_now(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . sch_table('visitors') . ' WHERE checked_out IS NULL');
    }

    public static function report_incident(array $d): array|WP_Error
    {
        global $wpdb;

        $title = sanitize_text_field((string) ($d['title'] ?? ''));
        if ($title === '') {
            return sch_api_error('missing_title', __('عنوان البلاغ مطلوب.', 'school-system'), 422);
        }

        $wpdb->insert(sch_table('incidents'), [
            'incident_type' => array_key_exists($d['incident_type'] ?? '', self::INCIDENT_TYPES) ? $d['incident_type'] : 'other',
            'title'         => $title,
            'description'   => sanitize_textarea_field((string) ($d['description'] ?? '')) ?: null,
            'severity'      => array_key_exists($d['severity'] ?? '', self::SEVERITIES) ? $d['severity'] : 'low',
            'occurred_at'   => sch_sanitize_datetime($d['occurred_at'] ?? null) ?? sch_now(),
            'status'        => 'open',
            'reported_by'   => get_current_user_id() ?: null,
            'created_at'    => sch_now(),
        ]);

        sch_audit('incident.reported', 'incident', (int) $wpdb->insert_id, ['severity' => $d['severity'] ?? 'low']);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function close_incident(int $id): bool
    {
        global $wpdb;
        $wpdb->update(sch_table('incidents'), ['status' => 'closed'], ['id' => $id]);
        sch_audit('incident.closed', 'incident', $id);
        return true;
    }

    public static function incidents(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT i.*, u.display_name FROM ' . sch_table('incidents') . ' i
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = i.reported_by
             ORDER BY i.status, i.occurred_at DESC LIMIT %d',
            $limit
        )) ?: [];
    }
}

/** الأصول والصيانة والمستودع. */
final class SCH_Assets
{
    public const STATUSES = [
        'in_use'      => 'قيد الاستخدام',
        'storage'     => 'في المستودع',
        'maintenance' => 'تحت الصيانة',
        'disposed'    => 'مستبعد',
    ];

    public const PRIORITIES = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عاجلة'];

    public const WORK_STATUSES = ['open' => 'مفتوح', 'in_progress' => 'قيد التنفيذ', 'done' => 'منجز'];

    public static function create(array $d): array|WP_Error
    {
        global $wpdb;

        $code = sanitize_text_field((string) ($d['asset_code'] ?? ''));
        $name = sanitize_text_field((string) ($d['name'] ?? ''));

        if ($code === '' || $name === '') {
            return sch_api_error('invalid_asset', __('رمز الأصل واسمه مطلوبان.', 'school-system'), 422);
        }

        $ok = $wpdb->insert(sch_table('assets'), [
            'asset_code'    => $code,
            'name'          => $name,
            'category'      => sanitize_text_field((string) ($d['category'] ?? '')) ?: null,
            'location'      => sanitize_text_field((string) ($d['location'] ?? '')) ?: null,
            'purchase_date' => sch_sanitize_date($d['purchase_date'] ?? null),
            'cost'          => round((float) ($d['cost'] ?? 0), 2),
            'status'        => 'in_use',
            'created_at'    => sch_now(),
        ]);

        if ($ok === false) {
            return sch_api_error('duplicate_code', __('رمز الأصل مستخدم.', 'school-system'), 409);
        }

        sch_audit('asset.created', 'asset', (int) $wpdb->insert_id);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function all(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . sch_table('assets') . ' ORDER BY asset_code') ?: [];
    }

    public static function report_maintenance(array $d): array|WP_Error
    {
        global $wpdb;

        $title = sanitize_text_field((string) ($d['title'] ?? ''));
        if ($title === '') {
            return sch_api_error('missing_title', __('عنوان البلاغ مطلوب.', 'school-system'), 422);
        }

        $wpdb->insert(sch_table('maintenance'), [
            'asset_id'    => absint($d['asset_id'] ?? 0) ?: null,
            'title'       => $title,
            'description' => sanitize_textarea_field((string) ($d['description'] ?? '')) ?: null,
            'location'    => sanitize_text_field((string) ($d['location'] ?? '')) ?: null,
            'priority'    => array_key_exists($d['priority'] ?? '', self::PRIORITIES) ? $d['priority'] : 'medium',
            'status'      => 'open',
            'reported_by' => get_current_user_id() ?: null,
            'created_at'  => sch_now(),
        ]);

        sch_audit('maintenance.reported', 'maintenance', (int) $wpdb->insert_id);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function set_work_status(int $id, string $status): bool|WP_Error
    {
        global $wpdb;

        if (!array_key_exists($status, self::WORK_STATUSES)) {
            return sch_api_error('bad_status', __('الحالة غير صحيحة.', 'school-system'), 422);
        }

        $wpdb->update(sch_table('maintenance'), [
            'status'    => $status,
            'closed_at' => $status === 'done' ? sch_now() : null,
        ], ['id' => $id]);

        return true;
    }

    public static function work_orders(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT m.*, a.name AS asset_name, u.display_name FROM ' . sch_table('maintenance') . ' m
             LEFT JOIN ' . sch_table('assets') . ' a ON a.id = m.asset_id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = m.reported_by
             ORDER BY FIELD(m.status, "open", "in_progress", "done"),
                      FIELD(m.priority, "high", "medium", "low"), m.id DESC
             LIMIT 150'
        ) ?: [];
    }

    // ---------- المستودع ----------

    public static function add_item(array $d): array|WP_Error
    {
        global $wpdb;

        $name = sanitize_text_field((string) ($d['name'] ?? ''));
        if ($name === '') {
            return sch_api_error('missing_name', __('اسم الصنف مطلوب.', 'school-system'), 422);
        }

        $ok = $wpdb->insert(sch_table('inventory'), [
            'sku'        => sanitize_text_field((string) ($d['sku'] ?? '')) ?: null,
            'name'       => $name,
            'unit'       => sanitize_text_field((string) ($d['unit'] ?? '')) ?: null,
            'qty'        => 0,
            'min_qty'    => round((float) ($d['min_qty'] ?? 0), 2),
            'created_at' => sch_now(),
            'updated_at' => sch_now(),
        ]);

        if ($ok === false) {
            return sch_api_error('duplicate_sku', __('رمز الصنف مستخدم.', 'school-system'), 409);
        }

        return ['id' => (int) $wpdb->insert_id];
    }

    /** حركة مخزون. الرصيد لا يُدخل يدويًا — يُحسب من الحركات. */
    public static function move_stock(array $d): bool|WP_Error
    {
        global $wpdb;

        $item_id = absint($d['item_id'] ?? 0);
        $type    = (string) ($d['move_type'] ?? '');
        $qty     = round((float) ($d['qty'] ?? 0), 2);

        $item = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('inventory') . ' WHERE id = %d', $item_id));
        if (!$item) {
            return sch_api_error('not_found', __('الصنف غير موجود.', 'school-system'), 404);
        }
        if (!in_array($type, ['in', 'out', 'adjust'], true) || $qty <= 0) {
            return sch_api_error('bad_move', __('نوع الحركة أو الكمية غير صحيحة.', 'school-system'), 422);
        }

        $current = (float) $item->qty;
        $new_qty = match ($type) {
            'in'     => $current + $qty,
            'out'    => $current - $qty,
            'adjust' => $qty,
        };

        if ($new_qty < 0) {
            return sch_api_error('insufficient', sprintf(
                /* translators: %s: الرصيد المتاح */
                __('الرصيد المتاح %s فقط.', 'school-system'),
                number_format($current, 2)
            ), 409);
        }

        $wpdb->insert(sch_table('stock_moves'), [
            'item_id'    => $item_id,
            'move_type'  => $type,
            'qty'        => $qty,
            'note'       => sanitize_text_field((string) ($d['note'] ?? '')) ?: null,
            'moved_by'   => get_current_user_id() ?: null,
            'created_at' => sch_now(),
        ]);

        $wpdb->update(sch_table('inventory'), [
            'qty'        => $new_qty,
            'updated_at' => sch_now(),
        ], ['id' => $item_id]);

        sch_audit('stock.' . $type, 'inventory', $item_id, ['qty' => $qty]);
        return true;
    }

    public static function items(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . sch_table('inventory') . ' ORDER BY name') ?: [];
    }

    /** الأصناف تحت الحد الأدنى. */
    public static function low_stock(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT * FROM ' . sch_table('inventory') . ' WHERE min_qty > 0 AND qty <= min_qty ORDER BY name'
        ) ?: [];
    }
}
