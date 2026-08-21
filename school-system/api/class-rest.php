<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * مسارات REST للتطبيقات.
 * كل مسار له permission_callback حقيقي — بلا استثناء.
 * كل عملية كتابة من الجوال تحمل client_uuid لمنع التكرار بعد انقطاع الشبكة.
 */
final class SCH_Rest
{
    public static function register_routes(): void
    {
        // ===== مشترك =====
        self::route('/me', 'GET', 'me', static fn (): bool => is_user_logged_in());
        self::route('/me/notifications', 'GET', 'notifications', static fn (): bool => is_user_logged_in());
        self::route('/me/notifications/read', 'POST', 'read_notifications', static fn (): bool => is_user_logged_in());
        self::route('/me/push/subscribe', 'POST', 'push_subscribe', static fn (): bool => is_user_logged_in());
        self::route('/me/push/unsubscribe', 'POST', 'push_unsubscribe', static fn (): bool => is_user_logged_in());

        // ===== ولي الأمر =====
        self::route('/me/children', 'GET', 'my_children', static fn (): bool => current_user_can('sch_view_own_children'));
        self::route('/students/(?P<id>\d+)/today', 'GET', 'student_today', [self::class, 'can_view_student']);
        self::route('/students/(?P<id>\d+)/attendance', 'GET', 'student_attendance', [self::class, 'can_view_student']);
        self::route('/students/(?P<id>\d+)/grades', 'GET', 'student_grades', [self::class, 'can_view_student']);
        self::route('/students/(?P<id>\d+)/invoices', 'GET', 'student_invoices', [self::class, 'can_view_student']);
        self::route('/students/(?P<id>\d+)/trip', 'GET', 'student_trip', [self::class, 'can_view_student']);

        // ===== السائق =====
        self::route('/driver/trip', 'GET', 'driver_trip', static fn (): bool => current_user_can('sch_drive_trip'));
        self::route('/driver/trip/open', 'POST', 'driver_open', static fn (): bool => current_user_can('sch_drive_trip'));
        self::route('/driver/trip/(?P<id>\d+)/manifest', 'GET', 'driver_manifest', [self::class, 'can_drive_trip']);
        self::route('/driver/trip/(?P<id>\d+)/scan', 'POST', 'driver_scan', [self::class, 'can_drive_trip']);
        self::route('/driver/trip/(?P<id>\d+)/position', 'POST', 'driver_position', [self::class, 'can_drive_trip']);
        self::route('/driver/trip/(?P<id>\d+)/close', 'POST', 'driver_close', [self::class, 'can_drive_trip']);

        // ===== الإدارة =====
        self::route('/students', 'GET', 'list_students', static fn (): bool => current_user_can('sch_view_students'));
        self::route('/attendance', 'POST', 'post_attendance', static fn (): bool => current_user_can('sch_manage_attendance'));
        self::route('/students/lookup', 'GET', 'lookup_student', static fn (): bool => current_user_can('sch_view_students'));

        // ===== العهدة =====
        self::route('/custody/scan', 'POST', 'custody_scan', static fn (): bool => current_user_can('sch_scan_gate') || current_user_can('sch_manage_transport'));
        self::route('/custody/board', 'GET', 'custody_board', static fn (): bool => current_user_can('sch_view_custody'));

        /*
         * مسارا الميدان: من يقف عند البوابة أو يقود الحافلة يحتاج شيئين لا
         * ثالث لهما — أن يجد الطالب حين تتعطّل الكاميرا، وأن يعرف من يحقّ له
         * استلامه. وكلاهما كان يمرّ عبر `/students` التي تشترط
         * `sch_view_students` — ولا يملكها الحارس ولا السائق، فيُرَدّان بـ403.
         * وهما مسارا **قراءة ضيّقة**: اسمٌ وشعبةٌ وحالة، لا ملفّ طالب.
         */
        self::route('/gate/search', 'GET', 'gate_search', [self::class, 'can_work_field']);
        self::route('/gate/pickers/(?P<id>\\d+)', 'GET', 'gate_pickers', [self::class, 'can_work_field']);

        // ===== التعلّم =====
        self::route('/content/(?P<id>\\d+)/open', 'POST', 'content_open', static fn (): bool => current_user_can('sch_learn'));
        self::route('/student/pass', 'GET', 'student_pass', static fn (): bool => current_user_can('sch_learn'));

        // ===== التتبع الحي =====
        self::route('/track/(?P<id>\\d+)', 'GET', 'track_child', static fn (): bool => current_user_can('sch_view_own_children'));
    }

    private static function route(string $path, string $methods, string $callback, callable $permission): void
    {
        register_rest_route(SCH_API_NS, $path, [
            'methods'             => $methods,
            'callback'            => [self::class, $callback],
            'permission_callback' => $permission,
        ]);
    }

    // ---------- مشترك ----------

    public static function me(): WP_REST_Response
    {
        $user = wp_get_current_user();

        return new WP_REST_Response([
            'id'      => $user->ID,
            'name'    => $user->display_name,
            'roles'   => array_values($user->roles),
            'unread'  => SCH_Comms::unread_count($user->ID),
            'school'  => sch_settings('school_name', get_bloginfo('name')),
        ]);
    }

    public static function notifications(): WP_REST_Response
    {
        return new WP_REST_Response(array_map(static fn (object $n): array => [
            'id'         => (int) $n->id,
            'title'      => $n->title,
            'body'       => $n->body,
            'ref_type'   => $n->ref_type,
            'ref_id'     => $n->ref_id ? (int) $n->ref_id : null,
            'read'       => $n->read_at !== null,
            'created_at' => $n->created_at,
        ], SCH_Comms::inbox(get_current_user_id())));
    }

    public static function read_notifications(WP_REST_Request $r): WP_REST_Response
    {
        SCH_Comms::mark_read(get_current_user_id(), absint($r->get_param('id')));
        return new WP_REST_Response(['ok' => true]);
    }

    /** تسجيل جهاز للإشعارات الفورية — الاشتراك يخصّ صاحب الجلسة لا مَن يُرسله. */
    public static function push_subscribe(WP_REST_Request $r): WP_REST_Response|WP_Error
    {
        $done = SCH_Push::subscribe(get_current_user_id(), [
            'endpoint' => (string) $r->get_param('endpoint'),
            'p256dh'   => (string) $r->get_param('p256dh'),
            'auth'     => (string) $r->get_param('auth'),
            'device'   => (string) $r->get_param('device'),
        ]);

        return is_wp_error($done) ? $done : new WP_REST_Response(['ok' => true]);
    }

    public static function push_unsubscribe(WP_REST_Request $r): WP_REST_Response
    {
        SCH_Push::unsubscribe(esc_url_raw((string) $r->get_param('endpoint')), get_current_user_id());

        return new WP_REST_Response(['ok' => true]);
    }

    // ---------- ولي الأمر ----------

    public static function my_children(): WP_REST_Response
    {
        $out = [];

        foreach (SCH_Students::children_of(get_current_user_id()) as $child) {
            $class = SCH_Students::current_class((int) $child->id);

            $out[] = SCH_Students::to_api($child) + [
                'class'          => $class ? SCH_Classes::label($class) : null,
                'attendance_pct' => SCH_Attendance::rate((int) $child->id),
            ];
        }

        return new WP_REST_Response($out);
    }

    /** الشاشة الرئيسية لولي الأمر — نداء واحد يجمع كل ما يحتاجه. */
    public static function student_today(WP_REST_Request $r): WP_REST_Response
    {
        global $wpdb;

        $id    = (int) $r->get_param('id');
        $today = current_time('Y-m-d');

        $attendance = $wpdb->get_row($wpdb->prepare(
            'SELECT status, method FROM ' . sch_table('attendance') . ' WHERE student_id = %d AND att_date = %s',
            $id,
            $today
        ));

        $sub  = SCH_Routes::subscription_of($id);
        $trip = null;

        if ($sub) {
            $active = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . sch_table('trips') . " WHERE route_id = %d AND trip_date = %s AND status = 'running' LIMIT 1",
                (int) $sub->route_id,
                $today
            ));

            if ($active) {
                $pos   = SCH_Trips::last_position((int) $active->id);
                $event = $wpdb->get_row($wpdb->prepare(
                    'SELECT type, occurred_at FROM ' . sch_table('trip_events') . '
                     WHERE trip_id = %d AND student_id = %d ORDER BY id DESC LIMIT 1',
                    (int) $active->id,
                    $id
                ));

                $trip = [
                    'id'        => (int) $active->id,
                    'direction' => $active->direction,
                    'status'    => $event->type ?? null,
                    'since'     => $event->occurred_at ?? null,
                    'position'  => $pos ? ['lat' => (float) $pos->lat, 'lng' => (float) $pos->lng, 'at' => $pos->recorded_at] : null,
                ];
            }
        }

        /*
         * المستحقّ **للسنة الجارية**.
         *
         * كان الاستعلام بلا `year_id`، وشاشة الرسوم تحسبه بها — فيرى الأب
         * رقمين مختلفين في شاشتين متلاصقتين، والرابط بينهما يقول إن أحدهما
         * كاذب. وفاتورةٌ مفتوحة من سنةٍ ماضية تنفخ رقم «اليوم» بلا سبب ظاهر.
         */
        $due = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total - paid), 0) FROM " . sch_table('invoices') . "
             WHERE student_id = %d AND year_id = %d AND status IN ('open','partial')",
            $id,
            SCH_Years::current_id()
        ));

        return new WP_REST_Response([
            'date'       => $today,
            'attendance' => $attendance ? ['status' => $attendance->status, 'method' => $attendance->method] : null,
            'transport'  => $sub ? ['route' => $sub->route_name, 'stop' => $sub->stop_name] : null,
            'trip'       => $trip,
            'balance'    => round($due, 2),
        ]);
    }

    public static function student_attendance(WP_REST_Request $r): WP_REST_Response
    {
        $id = (int) $r->get_param('id');

        return new WP_REST_Response([
            'rate'    => SCH_Attendance::rate($id),
            'records' => array_map(static fn (object $a): array => [
                'date'   => $a->att_date,
                'status' => $a->status,
                'method' => $a->method,
            ], SCH_Attendance::history($id, absint($r->get_param('days')) ?: 30)),
        ]);
    }

    public static function student_grades(WP_REST_Request $r): WP_REST_Response
    {
        $out = [];

        foreach (SCH_Assessment::report_card((int) $r->get_param('id')) as $subject => $data) {
            $out[] = [
                'subject' => $subject,
                'percent' => $data['percent'],
                'grade'   => SCH_Assessment::grade_label((float) $data['percent']),
                'exams'   => array_map(static fn (object $e): array => [
                    'title' => $e->title,
                    'score' => (float) $e->score,
                    'max'   => (float) $e->max_score,
                    'date'  => $e->exam_date,
                ], $data['exams']),
            ];
        }

        return new WP_REST_Response($out);
    }

    public static function student_invoices(WP_REST_Request $r): WP_REST_Response
    {
        return new WP_REST_Response(array_map(static fn (object $i): array => [
            'id'        => (int) $i->id,
            'total'     => (float) $i->total,
            'paid'      => (float) $i->paid,
            'remaining' => round((float) $i->total - (float) $i->paid, 2),
            'status'    => $i->status,
            'due_date'  => $i->due_date,
        ], SCH_Finance::of_student((int) $r->get_param('id'))));
    }

    public static function student_trip(WP_REST_Request $r): WP_REST_Response
    {
        $sub = SCH_Routes::subscription_of((int) $r->get_param('id'));

        return new WP_REST_Response($sub ? [
            'route' => $sub->route_name,
            'stop'  => $sub->stop_name,
            'fee'   => (float) $sub->fee_amount,
        ] : null);
    }

    // ---------- السائق ----------

    public static function driver_trip(): WP_REST_Response
    {
        $trip = SCH_Trips::active_for_driver(get_current_user_id());

        if (!$trip) {
            return new WP_REST_Response(null);
        }

        $route = SCH_Routes::get((int) $trip->route_id);

        return new WP_REST_Response([
            'id'        => (int) $trip->id,
            'route'     => $route->name ?? '',
            'direction' => $trip->direction,
            'started'   => $trip->started_at,
            'stops'     => array_map(static fn (object $s): array => [
                'id'   => (int) $s->id,
                'name' => $s->name,
                'seq'  => (int) $s->sequence,
                'lat'  => $s->lat !== null ? (float) $s->lat : null,
                'lng'  => $s->lng !== null ? (float) $s->lng : null,
            ], SCH_Routes::stops((int) $trip->route_id)),
        ]);
    }

    /** السائق يفتح رحلة مساره بنفسه — لا ينتظر الإدارة كل صباح. */
    public static function driver_open(WP_REST_Request $r): WP_REST_Response|WP_Error
    {
        $body      = (array) $r->get_json_params();
        $direction = (string) ($body['direction'] ?? 'morning');
        $route     = SCH_Driver::route_of(get_current_user_id());

        if (!$route) {
            return sch_api_error('no_route', __('لا يوجد مسار مُسنَد لك.', 'school-system'), 404);
        }

        $result = SCH_Trips::open((int) $route->id, $direction);

        return is_wp_error($result) ? $result : new WP_REST_Response($result, 201);
    }

    public static function driver_manifest(WP_REST_Request $r): WP_REST_Response
    {
        $trip_id = (int) $r->get_param('id');   // كان غير معرَّف — فيفشل كل نداء بـ500
        $excused = SCH_StudentLeave::excused_today();
        $next    = SCH_Trips::next_stop($trip_id);

        return new WP_REST_Response(array_map(static fn (object $m): array => [
            'excused'     => in_array((int) $m->id, $excused, true),
            'stop_id'     => (int) ($m->stop_id ?? 0),
            'stop_seq'    => (int) ($m->stop_seq ?? 0),
            'is_next'     => $next && (int) ($m->stop_id ?? 0) === $next->id,
            'student_id'  => (int) $m->id,
            'name'        => $m->full_name,
            'stop'        => $m->stop_name,
            'phone'          => $m->guardian_phone ? (string) $m->guardian_phone : null,
            'needs_receiver' => in_array((string) ($m->stage ?? ''), ['kg', 'primary'], true),
            'boarded_at'  => $m->boarded_at,
            'alighted_at' => $m->alighted_at,
        ], SCH_Trips::manifest($trip_id)));
    }

    /** مسح الطالب — يقبل qr_token أو student_id، ويمنع التكرار بـclient_uuid. */
    public static function driver_scan(WP_REST_Request $r): WP_REST_Response|WP_Error
    {
        $body   = (array) $r->get_json_params();
        $result = SCH_Trips::record_event($body + ['trip_id' => (int) $r->get_param('id')]);

        if (is_wp_error($result)) {
            return $result;
        }

        $student = SCH_Students::get((int) $result['student_id']);

        return new WP_REST_Response([
            'ok'        => true,
            'duplicate' => $result['duplicate'],
            'student'   => $student ? ['id' => (int) $student->id, 'name' => $student->full_name] : null,
        ], $result['duplicate'] ? 200 : 201);
    }

    public static function driver_position(WP_REST_Request $r): WP_REST_Response|WP_Error
    {
        $body = (array) $r->get_json_params();

        if (!is_numeric($body['lat'] ?? null) || !is_numeric($body['lng'] ?? null)) {
            return sch_api_error('bad_coords', __('الإحداثيات غير صحيحة.', 'school-system'), 422);
        }

        SCH_Trips::push_position(
            (int) $r->get_param('id'),
            (float) $body['lat'],
            (float) $body['lng'],
            isset($body['speed']) ? (int) $body['speed'] : null
        );

        return new WP_REST_Response(['ok' => true]);
    }

    public static function driver_close(WP_REST_Request $r): WP_REST_Response|WP_Error
    {
        $result = SCH_Trips::close((int) $r->get_param('id'));
        return is_wp_error($result) ? $result : new WP_REST_Response(['ok' => true]);
    }

    // ---------- الإدارة ----------

    public static function list_students(WP_REST_Request $r): WP_REST_Response
    {
        $result = SCH_Students::list([
            'search'   => (string) $r->get_param('search'),
            'status'   => (string) ($r->get_param('status') ?: 'active'),
            'class_id' => absint($r->get_param('class_id')),
            'page'     => absint($r->get_param('page')) ?: 1,
            'per_page' => absint($r->get_param('per_page')) ?: 20,
        ]);

        return new WP_REST_Response([
            'items' => array_map([SCH_Students::class, 'to_api'], $result['items']),
            'total' => $result['total'],
        ]);
    }

    /** بحث سريع بالرقم الأكاديمي — يستخدمه ربط الأبناء في ملف ولي الأمر. */
    public static function lookup_student(WP_REST_Request $r): WP_REST_Response
    {
        $student = SCH_Students::get_by_academic((string) $r->get_param('no'));

        if (!$student) {
            return new WP_REST_Response(['found' => false]);
        }

        $class    = SCH_Students::current_class((int) $student->id);
        $guardian = absint($r->get_param('guardian_user_id'));

        return new WP_REST_Response([
            'found'       => true,
            'id'          => (int) $student->id,
            'name'        => SCH_Enrollment::full_name($student),
            'academic_no' => $student->academic_no,
            'student_no'  => $student->student_no,
            'class'       => $class ? SCH_Classes::label($class) : null,
            'birth_date'  => $student->birth_date,
            'linked'      => $guardian > 0 && SCH_Students::guardian_has_child($guardian, (int) $student->id),
        ]);
    }

    /** مسح البوابة — يمر بآلة الحالات التي ترفض ما لا يجوز. */
    public static function custody_scan(WP_REST_Request $r): WP_REST_Response|WP_Error
    {
        $params = (array) $r->get_json_params();

        // مسح موحّد: بطاقة الموظف تسجّل حضوره لا عهدته — نفس المسح، نفس القارئ.
        $token = (string) ($params['badge_token'] ?? '');
        if ($token !== '') {
            $staff = SCH_StaffAttendance::resolve_badge($token);
            if ($staff) {
                $res = SCH_StaffAttendance::scan($staff, (string) ($params['checkpoint'] ?? ''), $params);
                return new WP_REST_Response($res, $res['duplicate'] ? 200 : 201);
            }
        }

        $result = SCH_Custody::record($params);

        if (is_wp_error($result)) {
            return $result;
        }

        $student = $result['student'];
        $class   = SCH_Students::current_class((int) $student->id);

        // العدّاد يعود مع كل مسح: «في المدرسة ١٤٢ من ٣٠٠» كان مجمَّدًا منذ
        // فتح الصفحة، والحارس يعمل على الشاشة نفسها من السابعة إلى الثانية.
        $counters = SCH_Gate::counters();

        return new WP_REST_Response([
            'ok'               => true,
            'duplicate'        => $result['duplicate'],
            'state'            => $result['state'],
            'state_label'      => SCH_Custody::state_label($result['state']),
            'checkpoint_label' => SCH_Custody::CHECKPOINTS[(string) $r->get_param('checkpoint')] ?? '',
            // مُنسَّقة من الخادم: `number_format_i18n` تُخرج أرقامًا عربية،
            // فلو نسّقتها الواجهة قفز العدّاد من «١٤٢» إلى «142» بعد أوّل مسح.
            'counts'           => sprintf(
                /* translators: 1: عدد من في المدرسة 2: الإجمالي */
                __('في المدرسة %1$s من %2$s', 'school-system'),
                number_format_i18n($counters['inside']),
                number_format_i18n($counters['total'])
            ),
            'student'          => [
                'id'          => (int) $student->id,
                'name'        => SCH_Enrollment::full_name($student),
                'academic_no' => $student->academic_no,
                'klass'       => $class ? SCH_Classes::label($class) : null,
            ],
        ], $result['duplicate'] ? 200 : 201);
    }

    /**
     * تتبع باص ابن — يُطلب كل 15 ثانية.
     *
     * ثلاثة قيود: لا يرى الأب إلا باص ابنه · وأثناء رحلة جارية فقط ·
     * وتختفي البيانات فور إغلاقها. موقع الباص = موقع ثلاثين طفلًا.
     */
    public static function track_child(WP_REST_Request $r): WP_REST_Response|WP_Error
    {
        $student_id = absint($r->get_param('id'));

        if (!SCH_Students::guardian_has_child(get_current_user_id(), $student_id)) {
            return sch_api_error('forbidden', __('لا تملك صلاحية هذا الطالب.', 'school-system'), 403);
        }

        $trip = SCH_Trips::active_for_student($student_id);

        if (!$trip) {
            return new WP_REST_Response(['live' => false]);
        }

        $sub      = SCH_Routes::subscription_of($student_id);
        $position = SCH_Trips::last_position((int) $trip->id);
        $progress = SCH_Trips::progress_to((int) $trip->id, $student_id);
        $stops    = SCH_Routes::stops((int) $trip->route_id);

        if ($trip->direction === 'afternoon') {
            $stops = array_reverse($stops);
        }

        $done = (int) ($progress['stops_done'] ?? 0);

        return new WP_REST_Response([
            'live'      => true,
            'direction' => $trip->direction,
            'route'     => $trip->route_name ?? '',
            'bus'       => $trip->plate ?? '',
            'driver'    => $trip->driver_name ?? '',
            'position'  => $position ? [
                'lat' => (float) $position->lat,
                'lng' => (float) $position->lng,
                'at'  => $position->recorded_at,
            ] : null,
            'stops' => array_values(array_map(static fn (object $s, int $i): array => [
                'id'   => (int) $s->id,
                'name' => (string) $s->name,
                'lat'  => $s->lat !== null ? (float) $s->lat : null,
                'lng'  => $s->lng !== null ? (float) $s->lng : null,
                'done' => $i < $done,
                'mine' => (int) $s->id === (int) ($sub->stop_id ?? 0),
            ], $stops, array_keys($stops))),
            'progress' => $progress,
            'child'    => [
                'name'    => SCH_Enrollment::full_name(SCH_Students::get($student_id)),
                'onboard' => SCH_Trips::is_onboard((int) $trip->id, $student_id),
            ],
        ]);
    }

    /** تصريح الطالب الحالي — يُطلب كل نصف دقيقة والمفتاح لا يغادر الخادم. */
    public static function student_pass(): WP_REST_Response|WP_Error
    {
        $student = SCH_Student::me();

        if (!$student) {
            return sch_api_error('no_student', __('لا يوجد سجل طالب.', 'school-system'), 403);
        }

        $pass = SCH_Pass::current($student);

        return new WP_REST_Response([
            'svg'        => SCH_Pass::svg($student),
            'expires_in' => $pass['expires_in'],
            'window'     => $pass['window'],
        ]);
    }

    /** تسجيل فتح محتوى ونتيجة لعبة — يغذّي عدّاد المنسق وتقدّم الطالب. */
    public static function content_open(WP_REST_Request $r): WP_REST_Response|WP_Error
    {
        $student = SCH_Student::me();

        if (!$student) {
            return sch_api_error('no_student', __('لا يوجد سجل طالب.', 'school-system'), 403);
        }

        $body  = (array) $r->get_json_params();
        $score = isset($body['score']) ? absint($body['score']) : null;

        SCH_Content::record_open(
            absint($r->get_param('id')),
            (int) $student->id,
            $score,
            absint($body['seconds'] ?? 0)
        );

        return new WP_REST_Response(['ok' => true], 201);
    }

    public static function custody_board(): WP_REST_Response
    {
        return new WP_REST_Response(SCH_Custody::board());
    }

    public static function post_attendance(WP_REST_Request $r): WP_REST_Response|WP_Error
    {
        $body   = (array) $r->get_json_params();
        $result = SCH_Attendance::mark(
            absint($body['student_id'] ?? 0),
            (string) ($body['date'] ?? current_time('Y-m-d')),
            (string) ($body['status'] ?? 'present'),
            'manual',
            (string) ($body['note'] ?? '')
        );

        return is_wp_error($result) ? $result : new WP_REST_Response(['ok' => true]);
    }

    // ---------- الصلاحيات ----------

    public static function can_view_student(WP_REST_Request $r): bool
    {
        if (current_user_can('sch_view_students')) {
            return true;
        }

        return current_user_can('sch_view_own_children')
            && SCH_Students::guardian_has_child(get_current_user_id(), (int) $r->get_param('id'));
    }

    /** من يعمل في الميدان: الحارس والمشرف والسائق — ومن يدير الطلاب أصلًا. */
    public static function can_work_field(): bool
    {
        return current_user_can('sch_scan_gate')
            || current_user_can('sch_drive_trip')
            || current_user_can('sch_manage_transport')
            || current_user_can('sch_view_students');
    }

    /**
     * بحث البوابة — البديل الوحيد حين تتعطّل الكاميرا أو تتلف البطاقة.
     *
     * يُرجع ما يكفي للتعرّف على الطفل عند الباب وحده: اسمٌ وشعبةٌ وحالة عهدة
     * ورقمٌ أكاديمي. ولا يُرجع ملفًّا: لا هاتف ولا عنوان ولا صحّة ولا مال.
     */
    public static function gate_search(WP_REST_Request $r): WP_REST_Response
    {
        $q     = trim((string) $r->get_param('q'));
        $token = trim((string) $r->get_param('token'));

        // البطاقة المقروءة تُحلّ إلى طالبها: الخروج المبكر يحتاج المعرّف
        // ليجلب قائمة المخوَّلين، والمسح وحده يعطي رمزًا لا معرّفًا.
        if ($token !== '') {
            $one = SCH_Custody::find_by_badge($token);
            $rows = $one ? [$one] : [];
        } elseif (mb_strlen($q) >= 2) {
            $rows = SCH_Students::list(['search' => $q, 'status' => 'active', 'per_page' => 8])['items'];
        } else {
            return new WP_REST_Response(['items' => []]);
        }

        $items = [];
        foreach ($rows as $s) {
            $class = SCH_Students::current_class((int) $s->id);

            $items[] = [
                'id'          => (int) $s->id,
                'name'        => SCH_Enrollment::full_name($s),
                'academic_no' => (string) ($s->academic_no ?? ''),
                'class'       => $class ? SCH_Classes::label($class) : null,
                'stage'       => (string) ($s->stage ?? ''),
                'state'       => (string) ($s->custody_state ?? 'home'),
                'state_label' => SCH_Custody::state_label((string) ($s->custody_state ?? 'home')),
            ];
        }

        return new WP_REST_Response(['items' => $items]);
    }

    /**
     * من يحقّ له استلام هذا الطالب — أولياء أمره المخوَّلون وتفويضاته السارية.
     *
     * بلا هذا المسار لا واجهة لاختيار المستلم، وبلا اختيارٍ لا يخرج طفل روضة
     * ولا ابتدائي خروجًا مبكرًا ولا ينزل من الباص — لأن `SCH_Custody` تشترطه.
     */
    public static function gate_pickers(WP_REST_Request $r): WP_REST_Response
    {
        $student_id = (int) $r->get_param('id');
        $student    = SCH_Students::get($student_id);

        if (!$student) {
            return new WP_REST_Response(['items' => [], 'required' => false]);
        }

        $items = [];
        foreach (SCH_Custody::pickers($student_id) as $p) {
            $items[] = [
                'key'      => (string) $p['key'],
                'name'     => (string) $p['name'],
                'relation' => (string) $p['relation'],
                'until'    => $p['until'],
            ];
        }

        return new WP_REST_Response([
            'items' => $items,
            // الصغار لا ينزلون من الباص بلا تأكيد — والواجهة تعرف ذلك منها
            'young' => in_array((string) $student->stage, ['kg', 'primary'], true),
        ]);
    }

    /** السائق لا يصل إلا لرحلته هو. */
    public static function can_drive_trip(WP_REST_Request $r): bool
    {
        if (current_user_can('sch_manage_transport')) {
            return true;
        }
        if (!current_user_can('sch_drive_trip')) {
            return false;
        }

        $trip = SCH_Trips::get((int) $r->get_param('id'));

        return $trip !== null && (int) $trip->driver_user_id === get_current_user_id();
    }
}
