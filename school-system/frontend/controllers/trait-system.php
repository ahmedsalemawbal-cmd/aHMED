<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الموظفون والصلاحيات والإعدادات والاستيراد والجسر
 *
 * سمة (trait) تُدمَج في `SCH_Dashboard` — فالمعالجات تبقى كما كانت تمامًا:
 * `private static`، تنادَى بـ`self::`، وتُسجَّل في `actions()` المركزي حيث
 * يُفرض النونس ثم الصلاحية قبل أي منها. **نُقلت ولم تُغيَّر.**
 *
 * لإضافة فعل: دالة `do_*` هنا + سطر في `SCH_Dashboard::actions()`. لا مسار ثانٍ.
 */
trait SCH_Ctl_System
{
    private static function do_add_employee(array $d, int $id): array|WP_Error
    {
        $created = SCH_Staff::create($d);

        if (is_wp_error($created)) {
            return $created;
        }

        // الصلاحيات تُحفظ مع الموظف في العملية نفسها — لا شاشة ثانية بعد الإنشاء.
        if (!empty($d['perm']) && !empty($created['user_id'])) {
            $sections = [];

            foreach ((array) $d['perm'] as $slug => $modes) {
                // حالة واحدة لكل قسم: مخفي · يرى · يعدّل
                $mode = sanitize_key((string) ($modes['mode'] ?? 'none'));

                if ($mode === 'none') {
                    continue;
                }

                $sections[sanitize_key((string) $slug)] = [
                    'view' => true,
                    'edit' => $mode === 'edit',
                ];
            }

            SCH_Perms::save(
                (int) $created['user_id'],
                $sections,
                sanitize_key((string) ($d['scope'] ?? 'all')),
                (string) ($d['expires_at'] ?? '')
            );
        }

        return $created;
    }

    private static function do_update_employee(array $d, int $id): array|WP_Error
    {
        $employee_id = absint($d['employee_id'] ?? 0);
        $result      = SCH_Staff::update($employee_id, $d);

        if (is_wp_error($result)) {
            return $result;
        }

        return ['msg' => __('حُفظت التعديلات.', 'school-system'), 'edit' => $employee_id];
    }

    private static function do_suspend_employee(array $d, int $id): bool|WP_Error
    {
        return SCH_Staff::suspend(absint($d['employee_id'] ?? 0));
    }

    private static function do_restore_employee(array $d, int $id): bool|WP_Error
    {
        return SCH_Staff::update(absint($d['employee_id'] ?? 0), ['status' => 'active']);
    }

    private static function do_print_staff_badges(array $d, int $id): array
    {
        $ids = array_map('absint', explode(',', (string) ($d['ids'] ?? '')));
        $n   = SCH_Staff::mark_printed($ids);

        return ['msg' => sprintf(
            /* translators: %d: عدد البطاقات */
            _n('عُلّمت بطاقة واحدة كمطبوعة', 'عُلّمت %d بطاقة كمطبوعة', $n, 'school-system'),
            $n
        )];
    }

    private static function do_save_perms(array $d, int $id): bool|WP_Error
    {
        $sections = [];

        foreach ((array) ($d['perm'] ?? []) as $slug => $modes) {
            $mode = sanitize_key((string) ($modes['mode'] ?? 'none'));

            if ($mode === 'none') {
                continue;
            }

            $sections[sanitize_key((string) $slug)] = [
                'view' => true,
                'edit' => $mode === 'edit',
            ];
        }

        return SCH_Perms::save(
            absint($d['user_id'] ?? 0),
            $sections,
            sanitize_key((string) ($d['scope'] ?? 'all')),
            (string) ($d['expires_at'] ?? '')
        );
    }

    private static function do_copy_perms(array $d, int $id): bool|WP_Error
    {
        return SCH_Perms::copy_from(absint($d['source_id'] ?? 0), absint($d['user_id'] ?? 0));
    }

    /** العودة لصلاحيات الدور: حذف الصفوف وإزالة التوسيع يعيده لسلوكه الافتراضي. */
    private static function do_reset_perms(array $d, int $id): bool
    {
        $user_id = absint($d['user_id'] ?? 0);

        SCH_Perms::reset($user_id);
        sch_audit('perms.reset', 'user', $user_id);

        return true;
    }

    private static function do_save_settings(array $d, int $id): bool
    {
        self::merge_settings([
            'school_name'  => sanitize_text_field((string) ($d['school_name'] ?? '')),
            'school_phone' => sch_normalize_phone((string) ($d['school_phone'] ?? '')),
            'school_email' => sanitize_email((string) ($d['school_email'] ?? '')),
            'address'      => sanitize_text_field((string) ($d['address'] ?? '')),
            'moe_id'       => sanitize_text_field((string) ($d['moe_id'] ?? '')),
        ]);

        sch_audit('settings.updated', 'settings', null, ['section' => 'school']);
        return true;
    }

    /** مواعيد اليوم الدراسي — تُغذّي حساب الحضور والتأخّر. */
    private static function do_save_timing(array $d, int $id): bool
    {
        $time = static fn (mixed $v): string => preg_match('/^\d{2}:\d{2}$/', (string) $v) ? (string) $v : '';

        self::merge_settings([
            'attendance_student_in' => $time($d['attendance_student_in'] ?? ''),
            'attendance_staff_in'   => $time($d['attendance_staff_in'] ?? ''),
            'attendance_grace'      => (string) max(0, min(120, absint($d['attendance_grace'] ?? 10))),
            'attendance_day_end'    => $time($d['attendance_day_end'] ?? ''),
        ]);

        sch_audit('settings.updated', 'settings', null, ['section' => 'timing']);
        return true;
    }

    /** حدود الإنذارات — تُغذّي الحارس الآلي (SCH_Alerts). */
    private static function do_save_alerts(array $d, int $id): bool
    {
        $time = static fn (mixed $v): string => preg_match('/^\d{2}:\d{2}$/', (string) $v) ? (string) $v : '';

        self::merge_settings([
            'alert_bus_no_board_at'  => $time($d['alert_bus_no_board_at'] ?? ''),
            'alert_bus_arrival_by'   => $time($d['alert_bus_arrival_by'] ?? ''),
            'alert_attendance_by'    => $time($d['alert_attendance_by'] ?? ''),
            'alert_dismissal_at'     => $time($d['alert_dismissal_at'] ?? ''),
            'alert_still_at_school'  => (string) max(0, min(180, absint($d['alert_still_at_school'] ?? 30))),
            'alert_referral_minutes' => (string) max(1, min(120, absint($d['alert_referral_minutes'] ?? 10))),
        ]);

        sch_audit('settings.updated', 'settings', null, ['section' => 'alerts']);
        return true;
    }

    /** دمج مفاتيح في إعدادات المدرسة بلا مسح البقية — لكل قسم نموذجه. */
    private static function merge_settings(array $patch): void
    {
        $s = sch_settings();
        if (!is_array($s)) {
            $s = [];
        }
        update_option('sch_settings', array_merge($s, $patch));
    }

    private static function do_import(array $d, int $id): array|WP_Error
    {
        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file((string) $_FILES['csv']['tmp_name'])) {
            return sch_api_error('no_file', __('اختر ملف CSV أولًا.', 'school-system'), 422);
        }

        if ((int) ($_FILES['csv']['size'] ?? 0) > 5 * MB_IN_BYTES) {
            return sch_api_error('too_large', __('حجم الملف يتجاوز 5 ميجابايت.', 'school-system'), 422);
        }

        $dry    = empty($d['confirm']);
        $result = SCH_Import::run((string) $_FILES['csv']['tmp_name'], $dry);
        $key    = 'sch_import_errors_' . get_current_user_id();

        if (!$result['ok']) {
            set_transient($key, $result['errors'], 10 * MINUTE_IN_SECONDS);
            return sch_api_error('import_failed', __('الملف يحتوي أخطاء. راجع التقرير أدناه.', 'school-system'), 422);
        }

        delete_transient($key);

        if ($dry) {
            return ['msg' => __('الملف سليم. أعد الرفع مع تفعيل «تأكيد الاستيراد» لإتمام العملية.', 'school-system')];
        }

        return ['msg' => sprintf(
            /* translators: %d: عدد الطلاب */
            __('اُستورد %d طالبًا.', 'school-system'),
            $result['imported']
        )];
    }

    private static function do_issue_key(array $d, int $id): array|WP_Error
    {
        $result = SCH_Bridge::issue_key((string) ($d['label'] ?? ''));

        if (is_wp_error($result)) {
            return $result;
        }

        // المفتاح لا يمر في الرابط أبدًا — يُحفظ لعرض واحد ثم يُمحى.
        set_transient('sch_bkey_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS);

        return ['msg' => __('صدر المفتاح — انسخه الآن، لن يظهر مرة أخرى.', 'school-system')];
    }

    private static function do_revoke_key(array $d, int $id): array
    {
        SCH_Bridge::revoke_key(sanitize_text_field((string) ($d['hash'] ?? '')));

        return ['msg' => __('أُلغي المفتاح.', 'school-system')];
    }

    private static function do_send_message(array $d, int $id): array|WP_Error
    {
        return SCH_Comms::broadcast($d);
    }

    /** فعل جماعي — الصلاحية تُفحَص داخل SCH_Bulk لكل فعل على حدة. */
    private static function do_bulk(array $d, int $id): array|WP_Error
    {
        return SCH_Bulk::run(
            sanitize_key((string) ($d['screen'] ?? '')),
            sanitize_key((string) ($d['bulk_op'] ?? '')),
            array_map('absint', explode(',', (string) ($d['ids'] ?? ''))),
            [
                'text'   => wp_unslash((string) ($d['bulk_text'] ?? '')),
                'select' => (string) ($d['bulk_select'] ?? ''),
            ]
        );
    }

    private static function do_flow_mark(array $d, int $id): bool
    {
        return SCH_Flow::mark(
            sanitize_key((string) ($d['flow'] ?? '')),
            absint($d['entity_id'] ?? 0),
            sanitize_key((string) ($d['step'] ?? '')),
            sanitize_key((string) ($d['state'] ?? 'done'))
        );
    }
}
