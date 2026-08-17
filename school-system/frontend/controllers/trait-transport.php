<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الباصات والمسارات والرحلات والاشتراكات
 *
 * سمة (trait) تُدمَج في `SCH_Dashboard` — فالمعالجات تبقى كما كانت تمامًا:
 * `private static`، تنادَى بـ`self::`، وتُسجَّل في `actions()` المركزي حيث
 * يُفرض النونس ثم الصلاحية قبل أي منها. **نُقلت ولم تُغيَّر.**
 *
 * لإضافة فعل: دالة `do_*` هنا + سطر في `SCH_Dashboard::actions()`. لا مسار ثانٍ.
 */
trait SCH_Ctl_Transport
{
    private static function do_add_bus(array $d, int $id): array|WP_Error
    {
        return SCH_Buses::create($d);
    }

    private static function do_add_route(array $d, int $id): array|WP_Error
    {
        return SCH_Routes::create($d);
    }

    private static function do_add_stop(array $d, int $id): array|WP_Error
    {
        return SCH_Routes::add_stop($d + ['route_id' => $id]);
    }

    private static function do_delete_stop(array $d, int $id): bool
    {
        return SCH_Routes::delete_stop(absint($d['stop_id'] ?? 0));
    }

    private static function do_subscribe(array $d, int $id): bool|WP_Error
    {
        return SCH_Routes::subscribe($d);
    }

    private static function do_unsubscribe(array $d, int $id): bool
    {
        return SCH_Routes::unsubscribe(absint($d['student_id'] ?? 0));
    }

    private static function do_open_trip(array $d, int $id): array|WP_Error
    {
        return SCH_Trips::open(absint($d['route_id'] ?? 0), (string) ($d['direction'] ?? 'morning'));
    }

    private static function do_close_trip(array $d, int $id): bool|WP_Error
    {
        return SCH_Trips::close(absint($d['trip_id'] ?? 0));
    }
}
