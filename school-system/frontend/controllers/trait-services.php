<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * العيادة والأدوية والمكتبة والزوار والسلامة والأصول
 *
 * سمة (trait) تُدمَج في `SCH_Dashboard` — فالمعالجات تبقى كما كانت تمامًا:
 * `private static`، تنادَى بـ`self::`، وتُسجَّل في `actions()` المركزي حيث
 * يُفرض النونس ثم الصلاحية قبل أي منها. **نُقلت ولم تُغيَّر.**
 *
 * لإضافة فعل: دالة `do_*` هنا + سطر في `SCH_Dashboard::actions()`. لا مسار ثانٍ.
 */
trait SCH_Ctl_Services
{
    private static function do_add_clinic(array $d, int $id): array|WP_Error
    {
        return SCH_Clinic::record($d);
    }

    private static function do_give_dose(array $d, int $id): bool|WP_Error
    {
        return SCH_Medication::give(absint($d['dose_id'] ?? 0));
    }

    private static function do_approve_med(array $d, int $id): bool|WP_Error
    {
        return SCH_Medication::approve(absint($d['med_id'] ?? 0));
    }

    private static function do_reject_med(array $d, int $id): bool
    {
        return SCH_Medication::reject(absint($d['med_id'] ?? 0), (string) ($d['reason'] ?? ''));
    }

    private static function do_decide_student_leave(array $d, int $id): bool|WP_Error
    {
        return SCH_StudentLeave::decide(
            absint($d['leave_id'] ?? 0),
            (string) ($d['decision'] ?? ''),
            (string) ($d['decision_note'] ?? '')
        );
    }

    private static function do_add_book(array $d, int $id): array|WP_Error
    {
        return SCH_Library::add_book($d);
    }

    private static function do_loan_book(array $d, int $id): bool|WP_Error
    {
        return SCH_Library::loan($d);
    }

    private static function do_return_book(array $d, int $id): bool
    {
        return SCH_Library::give_back(absint($d['loan_id'] ?? 0));
    }

    private static function do_check_in(array $d, int $id): array|WP_Error
    {
        return SCH_Security::check_in($d);
    }

    private static function do_check_out(array $d, int $id): bool
    {
        return SCH_Security::check_out(absint($d['visitor_id'] ?? 0));
    }

    private static function do_add_incident(array $d, int $id): array|WP_Error
    {
        return SCH_Security::report_incident($d);
    }

    private static function do_close_incident(array $d, int $id): bool
    {
        return SCH_Security::close_incident(absint($d['incident_id'] ?? 0));
    }

    private static function do_add_asset(array $d, int $id): array|WP_Error
    {
        return SCH_Assets::create($d);
    }

    private static function do_add_work(array $d, int $id): array|WP_Error
    {
        return SCH_Assets::report_maintenance($d);
    }

    private static function do_set_work(array $d, int $id): bool|WP_Error
    {
        return SCH_Assets::set_work_status(absint($d['work_id'] ?? 0), (string) ($d['status'] ?? ''));
    }

    private static function do_add_item(array $d, int $id): array|WP_Error
    {
        return SCH_Assets::add_item($d);
    }

    private static function do_move_stock(array $d, int $id): bool|WP_Error
    {
        return SCH_Assets::move_stock($d);
    }

    private static function do_save_kg(array $d, int $id): bool|WP_Error
    {
        return SCH_KG::save_log($d);
    }
}
