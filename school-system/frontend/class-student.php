<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * تطبيق الطالب — PWA مستقل على /student.
 *
 * التفريق بين المراحل ليس بالألوان بل بمرحلة نمو الطفل:
 *
 * **الابتدائي:** جلسات قصيرة · **بلا لوحة متصدرين إطلاقًا** — المقارنة في هذا العمر
 * تصنع طفلًا يظن نفسه غبيًا. المقارنة مع نفسه أمس.
 *
 * **المتوسط:** هنا وفقط هنا ينفع التنافس، وتحدي الفصل ضد فصل.
 *
 * **الثانوي:** لا ألعاب — التلعيب مع شاب في السابعة عشرة إهانة لذكائه.
 * يريد درجات، ووقتًا موفَّرًا، ومعرفة أين يخسر.
 */
final class SCH_Student
{
    public const BASE = 'student';

    private const SECTIONS = ['', 'library', 'homework', 'play', 'me', 'pass'];

    public static function init(): void
    {
        add_action('init', [self::class, 'add_rewrite']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'route'], 0);
    }

    public static function add_rewrite(): void
    {
        $b = self::BASE;
        add_rewrite_rule("^{$b}/?$", 'index.php?sch_stu=1&sch_stu_section=', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/?$", 'index.php?sch_stu=1&sch_stu_section=$matches[1]', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/(\d+)/?$", 'index.php?sch_stu=1&sch_stu_section=$matches[1]&sch_stu_id=$matches[2]', 'top');
    }

    public static function query_vars(array $vars): array
    {
        return [...$vars, 'sch_stu', 'sch_stu_section', 'sch_stu_id'];
    }

    public static function url(string $section = '', int $id = 0): string
    {
        $path = '/' . self::BASE;
        if ($section !== '') {
            $path .= '/' . $section;
        }
        if ($id > 0) {
            $path .= '/' . $id;
        }

        return home_url($path . '/');
    }

    public static function route(): void
    {
        if (!get_query_var('sch_stu')) {
            return;
        }

        $raw = (string) get_query_var('sch_stu_section');

        if ($raw === 'manifest.webmanifest') {
            self::send_manifest();
        }
        if ($raw === 'sw.js') {
            self::send_service_worker();
        }

        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-Content-Type-Options: nosniff');
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        $section = sanitize_key($raw);
        $id      = absint(get_query_var('sch_stu_id'));

        if ($section === 'logout') {
            // بنونسٍ لا بمجرّد زيارة: الخروج بـGET يُنفَّذ بصورةٍ في صفحةٍ أخرى.
            if (sch_logout_ok()) {
                wp_logout();
            }

            wp_safe_redirect(self::url());
            exit;
        }

        if (!is_user_logged_in()) {
            self::render_login();
        }

        if (!current_user_can('sch_learn')) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $student = self::me();
        if (!$student) {
            self::render('login', ['error' => __('حسابك غير مرتبط بسجل طالب.', 'school-system')]);
        }

        self::handle_post($student);

        if (!in_array($section, self::SECTIONS, true)) {
            $section = '';
        }

        self::render($section === '' ? 'home' : $section, [
            'section' => $section,
            'id'      => $id,
            'student' => $student,
        ]);
    }

    /** سجل الطالب المرتبط بالحساب الحالي. */
    public static function me(): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . sch_table('students') . " WHERE user_id = %d AND status = 'active'",
            get_current_user_id()
        )) ?: null;
    }

    // ---------- الإجراءات ----------

    private static function handle_post(object $student): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action = sanitize_key((string) ($_POST['sch_stu_action'] ?? ''));
        $back   = wp_get_referer() ?: self::url();

        if ($action === 'submit_hw' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_stu_hw')) {
            $result = SCH_Homework::submit(
                absint($_POST['homework_id'] ?? 0),
                (int) $student->id,
                [
                    'answer_text' => wp_unslash((string) ($_POST['answer_text'] ?? '')),
                    'drawing'     => (string) ($_POST['drawing'] ?? ''),
                ],
                $_FILES
            );

            wp_safe_redirect(add_query_arg(
                is_wp_error($result) ? ['err' => $result->get_error_message()] : ['ok' => '1'],
                self::url('homework')
            ));
            exit;
        }
    }

    // ---------- الدخول ----------

    private static function render_login(): never
    {
        // باب واحد للجميع: خمسة أبواب تعني خمسة روابط يحفظها المستخدم،
        // وأبًا يفتح باب الطالب فيُقال له «لا صلاحية» وهو محق في حسابه.
        wp_safe_redirect(SCH_Portal::url());
        exit;
    }


    // ---------- ملفات التطبيق ----------

    private static function send_manifest(): never
    {
        header('Content-Type: application/manifest+json; charset=utf-8');

        echo wp_json_encode([
            'name'             => __('تعلّم — الطالب', 'school-system'),
            'short_name'       => __('تعلّم', 'school-system'),
            'start_url'        => self::url(),
            'scope'            => self::url(),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'lang'             => 'ar',
            'dir'              => 'rtl',
            'background_color' => '#0B1220',
            'theme_color'      => '#0B1220',
            'icons'            => [
                ['src' => SCH_URL . 'assets/icon-student-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => SCH_URL . 'assets/icon-student-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    private static function send_service_worker(): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . wp_parse_url(self::url(), PHP_URL_PATH));

        $css = sch_asset('assets/student.css');
        $js  = sch_asset('assets/student.js');

        echo "const CACHE='sch-stu-" . SCH_VERSION . "';\nconst SHELL=['{$css}','{$js}'];\n";
        echo <<<'JS'

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// الأصول الثابتة فقط — لا صفحة فيها بيانات طالب.
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  if (!/\.(css|js|png|svg|woff2?)$/.test(new URL(req.url).pathname)) return;
  e.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
});
JS;

        // مستمعا الإشعار الفوري — نصّ واحد لكل عوامل الخدمة.
        SCH_Push::sw_handlers();
        exit;
    }

    // ---------- العرض ----------

    private static function render(string $view, array $data): never
    {
        $file = SCH_PATH . 'frontend/student/' . $view . '.php';
        if (!file_exists($file)) {
            $file = SCH_PATH . 'frontend/student/home.php';
        }

        $sch_view = $view;
        $sch_data = $data;

        require SCH_PATH . 'frontend/student/layout.php';
        exit;
    }

    // ---------- تفريق المراحل ----------

    /** الوضع حسب المرحلة: يحدد اللغة والطول والتنافس. */
    public static function mode(object $student): string
    {
        return match ((string) $student->stage) {
            'kg', 'primary' => 'young',
            'middle'        => 'mid',
            default         => 'senior',
        };
    }

    public static function mode_labels(string $mode): array
    {
        return match ($mode) {
            'young' => [
                'greet'   => __('أهلًا بك', 'school-system'),
                'library' => __('اكتشف', 'school-system'),
                'play'    => __('العب وتعلّم', 'school-system'),
                'hw'      => __('مهامي', 'school-system'),
            ],
            'mid' => [
                'greet'   => __('جاهز؟', 'school-system'),
                'library' => __('المكتبة', 'school-system'),
                'play'    => __('التحديات', 'school-system'),
                'hw'      => __('واجباتي', 'school-system'),
            ],
            default => [
                'greet'   => __('يومك', 'school-system'),
                'library' => __('المصادر', 'school-system'),
                'play'    => __('تدريب', 'school-system'),
                'hw'      => __('واجباتي', 'school-system'),
            ],
        };
    }

    /** الابتدائي يُقارَن بنفسه أمس لا بزملائه — قاعدة لا تُخرَق. */
    public static function shows_ranking(string $mode): bool
    {
        return $mode === 'mid';
    }

    // ---------- ما فاتك ----------

    /**
     * الحصص التي غابها الطالب هذا الأسبوع، ومعها محتوى مرتبط.
     * لا منصة في العالم تستطيع هذا — لأنها لا تعرف جدول مدرسته.
     */
    public static function missed(object $student): array
    {
        global $wpdb;

        $class = SCH_Students::current_class((int) $student->id);
        if (!$class || !SCH_Org::timetable_published()) {
            return [];
        }

        $since = gmdate('Y-m-d', strtotime(current_time('Y-m-d')) - 7 * DAY_IN_SECONDS);

        $days = $wpdb->get_col($wpdb->prepare(
            "SELECT att_date FROM " . sch_table('attendance') . "
             WHERE student_id = %d AND att_date >= %s AND status IN ('absent','excused')
             ORDER BY att_date DESC LIMIT 5",
            (int) $student->id,
            $since
        )) ?: [];

        $out = [];

        foreach ($days as $date) {
            $weekday = (int) gmdate('w', strtotime((string) $date));
            $day     = $weekday === 0 ? 1 : ($weekday <= 4 ? $weekday + 1 : 0);

            if ($day === 0) {
                continue;
            }

            $periods = $wpdb->get_results($wpdb->prepare(
                'SELECT t.period_no, t.subject_id, s.name AS subject_name
                 FROM ' . sch_table('timetable') . ' t
                 INNER JOIN ' . sch_table('subjects') . ' s ON s.id = t.subject_id
                 WHERE t.class_id = %d AND t.day_of_week = %d
                 ORDER BY t.period_no',
                (int) $class->id,
                $day
            )) ?: [];

            foreach ($periods as $p) {
                $p->date      = $date;
                $p->resources = SCH_Content::for_lesson((int) $p->subject_id, (string) $student->stage, 2);
                $out[]        = $p;
            }
        }

        return array_slice($out, 0, 12);
    }

    // ---------- التقدّم ----------

    /** أرقام الطالب — بلا مقارنة بالآخرين للابتدائي. */
    public static function progress(int $student_id): array
    {
        global $wpdb;

        $week = gmdate('Y-m-d', strtotime(current_time('Y-m-d')) - 7 * DAY_IN_SECONDS);

        $minutes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(seconds), 0) / 60 FROM " . sch_table('content_opens') . "
             WHERE student_id = %d AND opened_at >= %s",
            $student_id,
            $week . ' 00:00:00'
        ));

        $prev = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(seconds), 0) / 60 FROM " . sch_table('content_opens') . "
             WHERE student_id = %d AND opened_at >= %s AND opened_at < %s",
            $student_id,
            gmdate('Y-m-d', strtotime($week) - 7 * DAY_IN_SECONDS) . ' 00:00:00',
            $week . ' 00:00:00'
        ));

        $done = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('homework_subs') . " WHERE student_id = %d",
            $student_id
        ));

        return [
            'minutes'      => $minutes,
            'prev_minutes' => $prev,
            'homework'     => $done,
            'attendance'   => SCH_Attendance::rate($student_id),
        ];
    }
}
