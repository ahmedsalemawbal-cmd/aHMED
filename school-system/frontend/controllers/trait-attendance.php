<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الحضور والملاحظات والإنذارات والعهدة
 *
 * سمة (trait) تُدمَج في `SCH_Dashboard` — فالمعالجات تبقى كما كانت تمامًا:
 * `private static`، تنادَى بـ`self::`، وتُسجَّل في `actions()` المركزي حيث
 * يُفرض النونس ثم الصلاحية قبل أي منها. **نُقلت ولم تُغيَّر.**
 *
 * لإضافة فعل: دالة `do_*` هنا + سطر في `SCH_Dashboard::actions()`. لا مسار ثانٍ.
 */
trait SCH_Ctl_Attendance
{
    private static function do_mark_attendance(array $d, int $id): bool|WP_Error
    {
        $date     = sch_sanitize_date($d['att_date'] ?? null) ?? current_time('Y-m-d');
        $statuses = (array) ($d['status'] ?? []);

        foreach ($statuses as $student_id => $status) {
            $result = SCH_Attendance::mark(absint($student_id), $date, (string) $status, 'manual');
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return true;
    }

    private static function do_mark_staff(array $d, int $id): bool|WP_Error
    {
        return SCH_Deputy::mark_staff(
            absint($d['user_id'] ?? 0),
            (string) ($d['status'] ?? ''),
            (string) ($d['note'] ?? '')
        );
    }

    /** حفظ كشف حضور الموظفين دفعةً واحدة — نفس نمط رصد الطلاب. */
    private static function do_mark_staff_bulk(array $d, int $id): bool|WP_Error
    {
        $statuses = (array) ($d['status'] ?? []);

        foreach ($statuses as $user_id => $status) {
            $status = (string) $status;
            if ($status === '') {
                continue; // «لم يُرصد» يُترك كما هو، لا يُكتب
            }
            $result = SCH_Deputy::mark_staff(absint($user_id), $status);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return true;
    }

    private static function do_assign_sub(array $d, int $id): bool|WP_Error
    {
        return SCH_Deputy::assign(absint($d['sub_id'] ?? 0), absint($d['teacher_id'] ?? 0));
    }

    private static function do_add_note(array $d, int $id): array|WP_Error
    {
        return SCH_Notes::create($d + ['student_id' => $id ?: absint($d['student_id'] ?? 0)]);
    }

    private static function do_take_note(array $d, int $id): bool
    {
        return SCH_Notes::take(absint($d['note_id'] ?? 0));
    }

    private static function do_close_note(array $d, int $id): bool|WP_Error
    {
        return SCH_Notes::close(absint($d['note_id'] ?? 0), (string) ($d['resolution'] ?? ''));
    }

    private static function do_contact_parent(array $d, int $id): bool|WP_Error
    {
        return SCH_Notes::contact_parent(absint($d['note_id'] ?? 0), (string) ($d['message'] ?? ''));
    }

    private static function do_ack_alert(array $d, int $id): bool
    {
        return SCH_Alerts::acknowledge(absint($d['alert_id'] ?? 0));
    }

    private static function do_close_alert(array $d, int $id): bool|WP_Error
    {
        return SCH_Alerts::close(absint($d['alert_id'] ?? 0), (string) ($d['close_reason'] ?? ''));
    }

    /** إدخال إداري — حين يفرغ جوال الحارس أو يُنسى مسح. */
    private static function do_custody_manual(array $d, int $id): array|WP_Error
    {
        return SCH_Custody::record([
            'student_id'  => absint($d['student_id'] ?? 0),
            'checkpoint'  => sanitize_key((string) ($d['checkpoint'] ?? 'manual')),
            'client_uuid' => wp_generate_uuid4(),
            'reason'      => (string) ($d['reason'] ?? __('إدخال إداري', 'school-system')),
        ]);
    }

    private static function do_toggle_watch(array $d, int $id): bool
    {
        SCH_Views::toggle_watch(
            absint($d['student_id'] ?? 0),
            !empty($d['notify'])
        );

        return true;
    }

    private static function do_save_summary(array $d, int $id): bool|WP_Error
    {
        return SCH_Nerve::save_summary(absint($d['student_id'] ?? 0) ?: $id, $d);
    }

    private static function do_approve_summary(array $d, int $id): bool
    {
        return SCH_Nerve::approve_summary(absint($d['student_id'] ?? 0) ?: $id);
    }
}
