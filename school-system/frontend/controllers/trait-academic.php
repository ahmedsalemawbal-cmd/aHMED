<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الشعب والسنوات والمواد والاختبارات والجدول والمحتوى
 *
 * سمة (trait) تُدمَج في `SCH_Dashboard` — فالمعالجات تبقى كما كانت تمامًا:
 * `private static`، تنادَى بـ`self::`، وتُسجَّل في `actions()` المركزي حيث
 * يُفرض النونس ثم الصلاحية قبل أي منها. **نُقلت ولم تُغيَّر.**
 *
 * لإضافة فعل: دالة `do_*` هنا + سطر في `SCH_Dashboard::actions()`. لا مسار ثانٍ.
 */
trait SCH_Ctl_Academic
{
    private static function do_add_class(array $d, int $id): array|WP_Error
    {
        return SCH_Classes::create($d);
    }

    private static function do_delete_class(array $d, int $id): bool|WP_Error
    {
        return SCH_Classes::delete(absint($d['class_id'] ?? 0));
    }

    private static function do_add_year(array $d, int $id): array|WP_Error
    {
        return SCH_Years::create($d);
    }

    private static function do_set_year(array $d, int $id): bool
    {
        return SCH_Years::set_current(absint($d['year_id'] ?? 0));
    }

    private static function do_add_subject(array $d, int $id): array|WP_Error
    {
        return SCH_Subjects::create($d);
    }

    private static function do_assign_subject(array $d, int $id): bool|WP_Error
    {
        return SCH_Subjects::assign($d);
    }

    private static function do_set_slot(array $d, int $id): bool|WP_Error
    {
        return SCH_Timetable::set_slot($d);
    }

    private static function do_clear_slot(array $d, int $id): bool
    {
        return SCH_Timetable::clear_slot(
            absint($d['class_id'] ?? 0),
            (int) ($d['day_of_week'] ?? 0),
            (int) ($d['period_no'] ?? 0)
        );
    }

    private static function do_submit_timetable(array $d, int $id): array|WP_Error
    {
        return SCH_Org::submit_timetable();
    }

    private static function do_publish_timetable(array $d, int $id): bool|WP_Error
    {
        return SCH_Org::publish_timetable();
    }

    private static function do_reopen_timetable(array $d, int $id): bool
    {
        return SCH_Org::reopen_timetable();
    }

    private static function do_add_exam(array $d, int $id): array|WP_Error
    {
        return SCH_Assessment::create_exam($d);
    }

    private static function do_save_scores(array $d, int $id): bool|WP_Error
    {
        return SCH_Assessment::save_scores(absint($d['exam_id'] ?? 0), (array) ($d['score'] ?? []));
    }

    private static function do_add_content(array $d, int $id): array|WP_Error
    {
        return SCH_Content::create($d, $_FILES);
    }

    private static function do_content_status(array $d, int $id): bool|WP_Error
    {
        return SCH_Content::set_status(absint($d['content_id'] ?? 0), sanitize_key((string) ($d['status'] ?? '')));
    }

    private static function do_content_renew(array $d, int $id): bool
    {
        return SCH_Content::renew(absint($d['content_id'] ?? 0));
    }

    private static function do_add_homework(array $d, int $id): array|WP_Error
    {
        return SCH_Homework::create($d, $_FILES);
    }

    private static function do_grade_homework(array $d, int $id): bool
    {
        return SCH_Homework::grade(
            absint($d['sub_id'] ?? 0),
            (float) ($d['score'] ?? 0),
            (string) ($d['feedback'] ?? '')
        );
    }

    private static function do_run_rollover(array $d, int $id): array|WP_Error
    {
        $map = [];
        foreach ((array) ($d['map'] ?? []) as $k => $v) {
            $map[sanitize_text_field((string) $k)] = sanitize_text_field((string) $v);
        }

        $grad = [];
        foreach ((array) ($d['graduate'] ?? []) as $k => $v) {
            $grad[sanitize_text_field((string) $k)] = 1;
        }

        return SCH_Rollover::run(absint($d['from_year'] ?? 0), absint($d['to_year'] ?? 0), $map, $grad);
    }
}
