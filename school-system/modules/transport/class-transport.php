<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** الباصات والسائقون. */
final class SCH_Buses
{
    public static function create(array $d): array|WP_Error
    {
        global $wpdb;

        $plate = sanitize_text_field((string) ($d['plate_no'] ?? ''));
        if ($plate === '') {
            return sch_api_error('missing_plate', __('رقم اللوحة مطلوب.', 'school-system'), 422);
        }

        $ok = $wpdb->insert(sch_table('buses'), [
            'plate_no'            => $plate,
            'model'               => sanitize_text_field((string) ($d['model'] ?? '')) ?: null,
            'capacity'            => max(1, (int) ($d['capacity'] ?? 20)),
            'driver_user_id'      => absint($d['driver_user_id'] ?? 0) ?: null,
            'supervisor_user_id'  => absint($d['supervisor_user_id'] ?? 0) ?: null,
            'registration_expiry' => sch_sanitize_date($d['registration_expiry'] ?? null),
            'status'              => 'active',
            'created_at'          => sch_now(),
            'updated_at'          => sch_now(),
        ]);

        if ($ok === false) {
            return sch_api_error('duplicate_plate', __('رقم اللوحة مسجّل لباص آخر.', 'school-system'), 409);
        }

        sch_audit('bus.created', 'bus', (int) $wpdb->insert_id, ['plate' => $plate]);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('buses') . ' WHERE id = %d', $id)) ?: null;
    }

    public static function all(bool $active_only = false): array
    {
        global $wpdb;
        $sql = 'SELECT * FROM ' . sch_table('buses');
        if ($active_only) {
            $sql .= " WHERE status = 'active'";
        }
        return $wpdb->get_results($sql . ' ORDER BY plate_no') ?: [];
    }

    /** الباصات التي تنتهي استمارتها خلال 30 يومًا. */
    public static function expiring(): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('buses') . '
             WHERE registration_expiry IS NOT NULL
               AND registration_expiry <= %s
               AND status = %s
             ORDER BY registration_expiry',
            gmdate('Y-m-d', time() + 30 * DAY_IN_SECONDS),
            'active'
        )) ?: [];
    }

    public static function set_status(int $id, string $status): bool
    {
        global $wpdb;
        if (!in_array($status, ['active', 'maintenance', 'retired'], true)) {
            return false;
        }
        $wpdb->update(sch_table('buses'), ['status' => $status, 'updated_at' => sch_now()], ['id' => $id]);
        sch_audit('bus.status', 'bus', $id, ['status' => $status]);
        return true;
    }
}

/** المسارات ونقاط التوقف واشتراكات النقل. */
final class SCH_Routes
{
    public static function create(array $d): array|WP_Error
    {
        global $wpdb;

        $name = sanitize_text_field((string) ($d['name'] ?? ''));
        if ($name === '') {
            return sch_api_error('missing_name', __('اسم المسار مطلوب.', 'school-system'), 422);
        }

        $direction = in_array($d['direction'] ?? '', ['morning', 'afternoon', 'both'], true) ? $d['direction'] : 'both';

        $wpdb->insert(sch_table('routes'), [
            'name'       => $name,
            'bus_id'     => absint($d['bus_id'] ?? 0) ?: null,
            'direction'  => $direction,
            'status'     => 'active',
            'created_at' => sch_now(),
            'updated_at' => sch_now(),
        ]);

        sch_audit('route.created', 'route', (int) $wpdb->insert_id);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('routes') . ' WHERE id = %d', $id)) ?: null;
    }

    /** المسارات مع الباص وعدد المشتركين. */
    public static function all(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT r.*, b.plate_no,
                    (SELECT COUNT(*) FROM ' . sch_table('transport_subs') . ' s
                      WHERE s.route_id = r.id AND s.status = %s) AS riders,
                    (SELECT COUNT(*) FROM ' . sch_table('route_stops') . ' st
                      WHERE st.route_id = r.id) AS stops
             FROM ' . sch_table('routes') . ' r
             LEFT JOIN ' . sch_table('buses') . ' b ON b.id = r.bus_id
             ORDER BY r.name',
            'active'
        )) ?: [];
    }

    public static function add_stop(array $d): array|WP_Error
    {
        global $wpdb;

        $route_id = absint($d['route_id'] ?? 0);
        $name     = sanitize_text_field((string) ($d['name'] ?? ''));

        if (!self::get($route_id) || $name === '') {
            return sch_api_error('invalid_stop', __('المسار واسم نقطة التوقف مطلوبان.', 'school-system'), 422);
        }

        $next = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COALESCE(MAX(sequence), 0) + 1 FROM ' . sch_table('route_stops') . ' WHERE route_id = %d',
            $route_id
        ));

        $wpdb->insert(sch_table('route_stops'), [
            'route_id'    => $route_id,
            'name'        => $name,
            'lat'         => self::coord($d['lat'] ?? null),
            'lng'         => self::coord($d['lng'] ?? null),
            'sequence'    => $next,
            'pickup_time' => self::time($d['pickup_time'] ?? null),
            'created_at'  => sch_now(),
        ]);

        return ['id' => (int) $wpdb->insert_id];
    }

    public static function stops(int $route_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('route_stops') . ' WHERE route_id = %d ORDER BY sequence',
            $route_id
        )) ?: [];
    }

    public static function delete_stop(int $id): bool
    {
        global $wpdb;
        $wpdb->delete(sch_table('route_stops'), ['id' => $id]);
        return true;
    }

    /** اشتراك طالب في النقل — اشتراك واحد لكل طالب في السنة. */
    public static function subscribe(array $d): bool|WP_Error
    {
        global $wpdb;

        $student_id = absint($d['student_id'] ?? 0);
        $route_id   = absint($d['route_id'] ?? 0);
        $year_id    = SCH_Years::current_id();

        if ($year_id <= 0) {
            return sch_api_error('no_year', __('لا توجد سنة دراسية نشطة.', 'school-system'), 422);
        }
        if (!SCH_Students::get($student_id) || !self::get($route_id)) {
            return sch_api_error('not_found', __('الطالب أو المسار غير موجود.', 'school-system'), 404);
        }

        // التحقق من سعة الباص قبل الاشتراك.
        $route = self::get($route_id);
        if ($route->bus_id) {
            $bus     = SCH_Buses::get((int) $route->bus_id);
            $current = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . sch_table('transport_subs') . ' WHERE route_id = %d AND status = %s',
                $route_id,
                'active'
            ));
            if ($bus && $current >= (int) $bus->capacity) {
                return sch_api_error('bus_full', __('الباص مكتمل العدد على هذا المسار.', 'school-system'), 409);
            }
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('transport_subs') . ' WHERE student_id = %d AND year_id = %d',
            $student_id,
            $year_id
        ));

        $data = [
            'route_id'   => $route_id,
            'stop_id'    => absint($d['stop_id'] ?? 0) ?: null,
            'fee_amount' => round((float) ($d['fee_amount'] ?? 0), 2),
            'status'     => 'active',
            'updated_at' => sch_now(),
        ];

        if ($existing) {
            $wpdb->update(sch_table('transport_subs'), $data, ['id' => (int) $existing]);
        } else {
            $wpdb->insert(sch_table('transport_subs'), $data + [
                'student_id' => $student_id,
                'year_id'    => $year_id,
                'created_at' => sch_now(),
            ]);
        }

        sch_audit('transport.subscribed', 'student', $student_id, ['route' => $route_id]);
        return true;
    }

    public static function unsubscribe(int $student_id): bool
    {
        global $wpdb;

        $wpdb->update(
            sch_table('transport_subs'),
            ['status' => 'ended', 'updated_at' => sch_now()],
            ['student_id' => $student_id, 'year_id' => SCH_Years::current_id()]
        );

        sch_audit('transport.unsubscribed', 'student', $student_id);
        return true;
    }

    /** الطلاب المشتركون في مسار، بترتيب نقاط التوقف. */
    public static function riders(int $route_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.id, s.full_name, s.grade_level, s.section, s.qr_token,
                    st.name AS stop_name, st.sequence
             FROM ' . sch_table('transport_subs') . ' sub
             INNER JOIN ' . sch_table('students') . ' s ON s.id = sub.student_id
             LEFT JOIN ' . sch_table('route_stops') . ' st ON st.id = sub.stop_id
             WHERE sub.route_id = %d AND sub.status = %s AND s.status = %s
             ORDER BY st.sequence, s.full_name',
            $route_id,
            'active',
            'active'
        )) ?: [];
    }

    public static function subscription_of(int $student_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT sub.*, r.name AS route_name,
                    st.name AS stop_name, st.sequence AS stop_sequence,
                    st.lat AS stop_lat, st.lng AS stop_lng
             FROM ' . sch_table('transport_subs') . ' sub
             INNER JOIN ' . sch_table('routes') . ' r ON r.id = sub.route_id
             LEFT JOIN ' . sch_table('route_stops') . ' st ON st.id = sub.stop_id
             WHERE sub.student_id = %d AND sub.year_id = %d AND sub.status = %s
             LIMIT 1',
            $student_id,
            SCH_Years::current_id(),
            'active'
        )) ?: null;
    }

    private static function coord(mixed $v): ?string
    {
        if (!is_numeric($v)) {
            return null;
        }
        $f = (float) $v;
        return ($f >= -180 && $f <= 180) ? number_format($f, 7, '.', '') : null;
    }

    private static function time(mixed $v): ?string
    {
        return (is_string($v) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v) === 1) ? $v : null;
    }
}

/** الرحلات وأحداث الصعود والنزول. */
final class SCH_Trips
{
    /** فتح رحلة اليوم لمسار واتجاه — أو إرجاع القائمة إن كانت موجودة. */
    public static function open(int $route_id, string $direction): array|WP_Error
    {
        global $wpdb;

        $route = SCH_Routes::get($route_id);
        if (!$route) {
            return sch_api_error('route_not_found', __('المسار غير موجود.', 'school-system'), 404);
        }
        if (!in_array($direction, ['morning', 'afternoon'], true)) {
            return sch_api_error('bad_direction', __('اتجاه الرحلة غير صحيح.', 'school-system'), 422);
        }

        $today    = current_time('Y-m-d');
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('trips') . ' WHERE route_id = %d AND trip_date = %s AND direction = %s',
            $route_id,
            $today,
            $direction
        ));

        if ($existing) {
            return ['id' => (int) $existing->id, 'existing' => true];
        }

        $bus = $route->bus_id ? SCH_Buses::get((int) $route->bus_id) : null;

        $wpdb->insert(sch_table('trips'), [
            'route_id'       => $route_id,
            'bus_id'         => $route->bus_id,
            'driver_user_id' => $bus->driver_user_id ?? (get_current_user_id() ?: null),
            'direction'      => $direction,
            'trip_date'      => $today,
            'started_at'     => sch_now(),
            'status'         => 'running',
            'created_at'     => sch_now(),
        ]);

        $id = (int) $wpdb->insert_id;
        sch_audit('trip.opened', 'trip', $id, ['route' => $route_id, 'direction' => $direction]);

        return ['id' => $id, 'existing' => false];
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('trips') . ' WHERE id = %d', $id)) ?: null;
    }

    /**
     * تسجيل حدث صعود/نزول.
     * client_uuid يمنع التكرار عند إعادة الإرسال بعد انقطاع الشبكة.
     */
    public static function record_event(array $d): array|WP_Error
    {
        global $wpdb;

        $trip_id    = absint($d['trip_id'] ?? 0);
        $type       = (string) ($d['type'] ?? '');
        $uuid       = sanitize_text_field((string) ($d['client_uuid'] ?? ''));
        $student_id = absint($d['student_id'] ?? 0);

        if ($student_id === 0 && !empty($d['qr_token'])) {
            $student    = SCH_Students::get_by_qr(sanitize_text_field((string) $d['qr_token']));
            $student_id = $student ? (int) $student->id : 0;
        }

        if (!in_array($type, ['board', 'alight', 'absent', 'handover'], true)) {
            return sch_api_error('bad_type', __('نوع الحدث غير صحيح.', 'school-system'), 422);
        }
        if ($uuid === '') {
            return sch_api_error('missing_uuid', __('client_uuid مطلوب.', 'school-system'), 422);
        }

        $trip = self::get($trip_id);
        if (!$trip || $trip->status !== 'running') {
            return sch_api_error('trip_not_running', __('الرحلة غير مفتوحة.', 'school-system'), 409);
        }
        if ($student_id === 0) {
            return sch_api_error('student_not_found', __('لم يُتعرَّف على الطالب.', 'school-system'), 404);
        }

        // نفس العملية أُرسلت من قبل؟ أعِد النتيجة نفسها بلا تكرار.
        $dupe = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('trip_events') . ' WHERE client_uuid = %s LIMIT 1',
            $uuid
        ));
        if ($dupe) {
            return ['id' => (int) $dupe->id, 'duplicate' => true, 'student_id' => (int) $dupe->student_id];
        }

        $wpdb->insert(sch_table('trip_events'), [
            'trip_id'     => $trip_id,
            'student_id'  => $student_id,
            'type'        => $type,
            'lat'         => is_numeric($d['lat'] ?? null) ? number_format((float) $d['lat'], 7, '.', '') : null,
            'lng'         => is_numeric($d['lng'] ?? null) ? number_format((float) $d['lng'], 7, '.', '') : null,
            'client_uuid' => $uuid,
            'recorded_by' => get_current_user_id() ?: null,
            'occurred_at' => sch_sanitize_datetime($d['occurred_at'] ?? null) ?? sch_now(),
            'created_at'  => sch_now(),
        ]);

        // النزول خارج الترتيب يُسجَّل ولا يُمنع — أحيانًا يلتقي ولي الأمر بالباص في الطريق.
        if ($type === 'alight' && self::out_of_order($trip_id, $student_id)) {
            sch_audit('trip.out_of_order', 'student', $student_id, ['trip' => $trip_id]);
        }

        // نقل العهدة: الصعود والنزول انتقالان لا مجرد سجلين.
        if ($type === 'board' || $type === 'alight') {
            SCH_Custody::record([
                'student_id'    => $student_id,
                'checkpoint'    => $type === 'board' ? 'bus_board' : 'bus_alight',
                'client_uuid'   => $uuid . '-c',
                'receiver_name' => sanitize_text_field((string) ($d['receiver_name'] ?? '')),
                'lat'           => $d['lat'] ?? null,
                'lng'           => $d['lng'] ?? null,
                'occurred_at'   => $d['occurred_at'] ?? null,
            ]);
        }

        // صعود الصباح يُسجَّل حضورًا مبدئيًا.
        if ($type === 'board' && $trip->direction === 'morning') {
            SCH_Attendance::mark($student_id, current_time('Y-m-d'), 'present', 'transport');
        }

        // العهدة تتولى إشعار الصعود والنزول — نُشعر هنا للحالات الأخرى فقط.
        if ($type === 'absent' || $type === 'handover') {
            self::notify_guardians($student_id, $type);
        }

        return ['id' => (int) $wpdb->insert_id, 'duplicate' => false, 'student_id' => $student_id];
    }

    public static function close(int $trip_id): bool|WP_Error
    {
        global $wpdb;

        $trip = self::get($trip_id);
        if (!$trip) {
            return sch_api_error('not_found', __('الرحلة غير موجودة.', 'school-system'), 404);
        }

        // رحلة العودة لا تُغلق قبل نزول كل من صعد.
        if ($trip->direction === 'afternoon') {
            $pending = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM (
                    SELECT student_id,
                           SUM(type = 'board')  AS boarded,
                           SUM(type = 'alight') AS alighted
                    FROM " . sch_table('trip_events') . "
                    WHERE trip_id = %d
                    GROUP BY student_id
                    HAVING boarded > alighted
                 ) AS pending",
                $trip_id
            ));

            if ($pending > 0) {
                return sch_api_error('students_onboard', sprintf(
                    /* translators: %d: عدد الطلاب */
                    __('لا يمكن إنهاء الرحلة: %d طالبًا لم يُسجَّل نزولهم.', 'school-system'),
                    $pending
                ), 409);
            }
        }

        $wpdb->update(
            sch_table('trips'),
            ['status' => 'completed', 'ended_at' => sch_now()],
            ['id' => $trip_id]
        );

        sch_audit('trip.closed', 'trip', $trip_id);
        return true;
    }

    /** حالة الرحلة: من صعد ومن لم يصعد. */
    /**
     * كشف الرحلة بترتيب النزول الصحيح.
     *
     * **أول من يصعد صباحًا هو آخر من ينزل مساءً** — وهذه نتيجة هندسية لا اختيار:
     * الذهاب يبدأ من أبعد نقطة عن المدرسة، والعودة تعكس الطريق فتبدأ بالأقرب.
     * فالترتيب تصاعديّ ذهابًا وتنازليّ عودةً، ويصير الكشف صحيحًا في الاتجاهين تلقائيًا.
     */
    public static function manifest(int $trip_id): array
    {
        global $wpdb;

        $trip = self::get($trip_id);
        if (!$trip) {
            return [];
        }

        $order = $trip->direction === 'afternoon' ? 'DESC' : 'ASC';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.full_name, s.stage,
                    st.id AS stop_id, st.name AS stop_name, st.sequence AS stop_seq,
                    (SELECT g.phone
                       FROM " . sch_table('guardian_student') . " gs
                       INNER JOIN " . sch_table('guardians') . " g ON g.user_id = gs.guardian_user_id
                      WHERE gs.student_id = s.id AND g.phone IS NOT NULL
                      ORDER BY gs.is_primary DESC LIMIT 1) AS guardian_phone,
                    MAX(CASE WHEN e.type = 'board'  THEN e.occurred_at END) AS boarded_at,
                    MAX(CASE WHEN e.type = 'alight' THEN e.occurred_at END) AS alighted_at
             FROM " . sch_table('transport_subs') . " sub
             INNER JOIN " . sch_table('students') . " s ON s.id = sub.student_id
             LEFT JOIN " . sch_table('route_stops') . " st ON st.id = sub.stop_id
             LEFT JOIN " . sch_table('trip_events') . " e ON e.student_id = s.id AND e.trip_id = %d
             WHERE sub.route_id = %d AND sub.status = 'active'
             GROUP BY s.id, s.full_name, s.stage, st.id, st.name, st.sequence
             ORDER BY st.sequence {$order}, s.full_name",
            $trip_id,
            (int) $trip->route_id
        )) ?: [];
    }

    /**
     * النقطة القادمة في الرحلة — أول نقطة بقي فيها أحد لم يُنجَز.
     * ذهابًا: من لم يصعد. عودةً: من لم ينزل.
     */
    public static function next_stop(int $trip_id): ?object
    {
        $trip = self::get($trip_id);
        if (!$trip) {
            return null;
        }

        $morning = $trip->direction === 'morning';

        foreach (self::manifest($trip_id) as $row) {
            $pending = $morning ? $row->boarded_at === null : ($row->boarded_at !== null && $row->alighted_at === null);

            if ($pending && $row->stop_id) {
                return (object) [
                    'id'       => (int) $row->stop_id,
                    'name'     => (string) $row->stop_name,
                    'sequence' => (int) $row->stop_seq,
                ];
            }
        }

        return null;
    }

    /**
     * هل هذا النزول خارج الترتيب؟
     * تحذير لا منع — أحيانًا يلتقي ولي الأمر بالباص في الطريق، وهذا حقه.
     */
    public static function out_of_order(int $trip_id, int $student_id): bool
    {
        $next = self::next_stop($trip_id);
        if (!$next) {
            return false;
        }

        $sub = SCH_Routes::subscription_of($student_id);
        if (!$sub || !isset($sub->stop_sequence)) {
            return false;
        }

        $trip = self::get($trip_id);

        return $trip->direction === 'afternoon'
            ? (int) $sub->stop_sequence < $next->sequence
            : (int) $sub->stop_sequence > $next->sequence;
    }

    public static function today(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT t.*, r.name AS route_name, b.plate_no
             FROM ' . sch_table('trips') . ' t
             INNER JOIN ' . sch_table('routes') . ' r ON r.id = t.route_id
             LEFT JOIN ' . sch_table('buses') . ' b ON b.id = t.bus_id
             WHERE t.trip_date = %s
             ORDER BY t.direction, r.name',
            current_time('Y-m-d')
        )) ?: [];
    }

    /** الرحلة الجارية للسائق الحالي. */
    public static function active_for_driver(int $user_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('trips') . '
             WHERE driver_user_id = %d AND status = %s AND trip_date = %s
             ORDER BY id DESC LIMIT 1',
            $user_id,
            'running',
            current_time('Y-m-d')
        )) ?: null;
    }

    public static function push_position(int $trip_id, float $lat, float $lng, ?int $speed = null): bool
    {
        global $wpdb;

        $wpdb->insert(sch_table('vehicle_positions'), [
            'trip_id'     => $trip_id,
            'lat'         => number_format($lat, 7, '.', ''),
            'lng'         => number_format($lng, 7, '.', ''),
            'speed'       => $speed,
            'recorded_at' => sch_now(),
        ]);

        return true;
    }

    /**
     * تقدّم الرحلة لنقطة طالب معيّن: كم نقطة بقيت وكم دقيقة تقريبًا.
     *
     * الأب لا يريد أن يحدّق في نقطة تتحرك — يريد أن يعرف متى ينزل لاستقباله.
     */
    public static function progress_to(int $trip_id, int $student_id): ?array
    {
        global $wpdb;

        $trip = self::get($trip_id);
        if (!$trip || $trip->status !== 'running') {
            return null;
        }

        $sub = SCH_Routes::subscription_of($student_id);
        if (!$sub || !$sub->stop_id) {
            return null;
        }

        $stops = SCH_Routes::stops((int) $trip->route_id);
        if ($stops === []) {
            return null;
        }

        // العودة تعكس ترتيب الذهاب.
        if ($trip->direction === 'afternoon') {
            $stops = array_reverse($stops);
        }

        $mine  = 0;
        $done  = 0;
        $index = 0;

        foreach ($stops as $i => $stop) {
            if ((int) $stop->id === (int) $sub->stop_id) {
                $mine = $i;
            }
        }

        // النقاط المنجَزة: التي لم يبقَ فيها أحد معلّق.
        $next = self::next_stop($trip_id);

        foreach ($stops as $i => $stop) {
            if ($next && (int) $stop->id === $next->id) {
                $index = $i;
                break;
            }
            $index = $i + 1;
        }

        $done      = $index;
        $remaining = max(0, $mine - $index);

        return [
            'stops_total'     => count($stops),
            'stops_done'      => $done,
            'stops_remaining' => $remaining,
            'my_index'        => $mine,
            'minutes'         => max(1, (int) ceil($remaining * self::minutes_per_stop((int) $trip->route_id))),
            'arrived'         => $remaining === 0,
        ];
    }

    /** متوسط الدقائق بين نقطتين — من زمن الرحلة المعتاد أو تقدير معقول. */
    private static function minutes_per_stop(int $route_id): float
    {
        $stops = max(1, count(SCH_Routes::stops($route_id)));
        $route = SCH_Routes::get($route_id);
        $total = (int) ($route->duration_min ?? 0);

        return $total > 0 ? $total / $stops : 3.5;
    }

    /**
     * إشعار الاقتراب — مرة واحدة لكل رحلة.
     * إشعار يصل مرتين يفقد قيمته، وإشعار لا يصل يجعل الأب يحدّق في الشاشة.
     */
    public static function notify_approaching(int $trip_id): int
    {
        global $wpdb;

        $trip = self::get($trip_id);
        if (!$trip || $trip->status !== 'running' || $trip->direction !== 'afternoon') {
            return 0;
        }

        $sent = 0;

        foreach (self::manifest($trip_id) as $row) {
            if ($row->boarded_at === null || $row->alighted_at !== null) {
                continue;
            }

            $progress = self::progress_to($trip_id, (int) $row->id);
            if (!$progress || $progress['stops_remaining'] > 1) {
                continue;
            }

            $key = 'sch_near_' . $trip_id . '_' . (int) $row->id;
            if (get_transient($key)) {
                continue;
            }

            set_transient($key, 1, 3 * HOUR_IN_SECONDS);

            foreach (SCH_Guardians::of_student((int) $row->id) as $g) {
                SCH_Comms::notify(
                    (int) $g->user_id,
                    __('الباص يقترب', 'school-system'),
                    sprintf(
                        /* translators: %s: اسم الطالب */
                        __('على بعد نقطة واحدة منكم — استعدّ لاستقبال %s.', 'school-system'),
                        $row->full_name
                    ),
                    'transport',
                    (int) $row->id
                );
            }

            $sent++;
        }

        return $sent;
    }

    /** رحلة ابن جارية الآن — لا يرى الأب غيرها. */
    public static function active_for_student(int $student_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, r.name AS route_name, b.plate_no AS plate, u.display_name AS driver_name
             FROM " . sch_table('transport_subs') . " sub
             INNER JOIN " . sch_table('trips') . " t
                     ON t.route_id = sub.route_id AND t.status = 'running'
             INNER JOIN " . sch_table('routes') . " r ON r.id = t.route_id
             LEFT JOIN " . sch_table('buses') . " b ON b.id = t.bus_id
             LEFT JOIN {$wpdb->users} u ON u.ID = t.driver_user_id
             WHERE sub.student_id = %d AND sub.status = 'active'
             ORDER BY t.started_at DESC LIMIT 1",
            $student_id
        )) ?: null;
    }

    public static function is_onboard(int $trip_id, int $student_id): bool
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT MAX(CASE WHEN type = 'board' THEN 1 ELSE 0 END) AS b,
                    MAX(CASE WHEN type = 'alight' THEN 1 ELSE 0 END) AS a
             FROM " . sch_table('trip_events') . "
             WHERE trip_id = %d AND student_id = %d",
            $trip_id,
            $student_id
        ));

        return (int) ($row->b ?? 0) === 1 && (int) ($row->a ?? 0) === 0;
    }

    public static function last_position(int $trip_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('vehicle_positions') . ' WHERE trip_id = %d ORDER BY id DESC LIMIT 1',
            $trip_id
        )) ?: null;
    }

    private static function notify_guardians(int $student_id, string $type): void
    {
        $student = SCH_Students::get($student_id);
        if (!$student) {
            return;
        }

        $titles = [
            'board'    => __('صعد الطالب إلى الباص', 'school-system'),
            'alight'   => __('نزل الطالب من الباص', 'school-system'),
            'absent'   => __('لم يحضر الطالب للباص', 'school-system'),
            'handover' => __('تم تسليم الطالب', 'school-system'),
        ];

        foreach (SCH_Guardians::of_student($student_id) as $guardian) {
            SCH_Comms::notify(
                (int) $guardian->user_id,
                $titles[$type] ?? __('تحديث النقل', 'school-system'),
                $student->full_name,
                'transport',
                $student_id
            );
        }
    }
}
