<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * تطبيق المعلم — PWA على /teacher.
 *
 * ليس تطبيق مسح بل قائمة. صور الطلاب، ضغطة، أربعة تصنيفات، سطر واحد.
 * **دور المعلم عشر ثوانٍ ثم يقف**: لا يقرر من يُبلَّغ، ولا يصيغ رسالة،
 * ولا يتحمل مسؤولية النبرة. هو عين لا صوت.
 *
 * أما الدرجات والاختبارات والواجبات فمكانها الداشبورد على الكمبيوتر —
 * رصد 150 درجة من جوال عذاب يجعل المعلم يؤجّل الرصد لآخر الفصل.
 */
final class SCH_Teacher
{
    public const BASE = 'teacher';

    public static function init(): void
    {
        add_action('init', [self::class, 'add_rewrite']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'route'], 0);
    }

    public static function add_rewrite(): void
    {
        $b = self::BASE;
        add_rewrite_rule("^{$b}/?$", 'index.php?sch_tch=1&sch_tch_section=', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/?$", 'index.php?sch_tch=1&sch_tch_section=$matches[1]', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/(\d+)/?$", 'index.php?sch_tch=1&sch_tch_section=$matches[1]&sch_tch_id=$matches[2]', 'top');
    }

    public static function query_vars(array $vars): array
    {
        return [...$vars, 'sch_tch', 'sch_tch_section', 'sch_tch_id'];
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
        if (!get_query_var('sch_tch')) {
            return;
        }

        $raw = (string) get_query_var('sch_tch_section');

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

        $section = sanitize_key(str_replace('.js', '', $raw));
        $id      = absint(get_query_var('sch_tch_id'));

        if ($section === 'logout') {
            wp_logout();
            wp_safe_redirect(self::url());
            exit;
        }

        if (!is_user_logged_in()) {
            self::render_login();
        }

        if (!current_user_can('sch_write_notes')) {
            wp_safe_redirect(SCH_Dashboard::url());
            exit;
        }

        self::handle_post();

        if (!in_array($section, ['', 'klass', 'student'], true)) {
            $section = '';
        }

        self::render($section === '' ? 'home' : $section, ['section' => $section, 'id' => $id]);
    }

    private static function handle_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action = sanitize_key((string) ($_POST['sch_tch_action'] ?? ''));
        $back   = wp_get_referer() ?: self::url();

        if ($action === 'note' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_tch_note')) {
            $result = SCH_Notes::create([
                'student_id' => absint($_POST['student_id'] ?? 0),
                'category'   => sanitize_key((string) ($_POST['category'] ?? '')),
                'body'       => wp_unslash((string) ($_POST['body'] ?? '')),
            ]);

            wp_safe_redirect(add_query_arg(
                is_wp_error($result) ? ['err' => $result->get_error_message()] : ['ok' => '1'],
                $back
            ));
            exit;
        }

        if ($action === 'attend' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_tch_attend')) {
            SCH_Attendance::mark(
                absint($_POST['student_id'] ?? 0),
                current_time('Y-m-d'),
                sanitize_key((string) ($_POST['status'] ?? 'present')),
                'manual'
            );

            wp_safe_redirect($back);
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

        $error = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['sch_tch_login'])) {
            $error = self::attempt_login();
        }

        self::render('login', ['error' => $error]);
    }

    private static function attempt_login(): string
    {
        if (!wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_tch_login')) {
            return __('انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.', 'school-system');
        }

        $input = sanitize_text_field(wp_unslash((string) ($_POST['username'] ?? '')));
        $key   = 'sch_tchlock_' . md5($input . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ((int) get_transient($key) >= 5) {
            return __('محاولات كثيرة. أعد المحاولة بعد 15 دقيقة.', 'school-system');
        }

        $user = wp_signon([
            'user_login'    => $input,
            'user_password' => (string) ($_POST['password'] ?? ''),
            'remember'      => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            set_transient($key, (int) get_transient($key) + 1, 15 * MINUTE_IN_SECONDS);
            return __('بيانات الدخول غير صحيحة.', 'school-system');
        }

        delete_transient($key);
        wp_set_current_user($user->ID);
        wp_safe_redirect(self::url());
        exit;
    }

    // ---------- ملفات التطبيق ----------

    private static function send_manifest(): never
    {
        header('Content-Type: application/manifest+json; charset=utf-8');

        echo wp_json_encode([
            'name'             => __('فصلي — المعلم', 'school-system'),
            'short_name'       => __('فصلي', 'school-system'),
            'start_url'        => self::url(),
            'scope'            => self::url(),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'lang'             => 'ar',
            'dir'              => 'rtl',
            'background_color' => '#F8FAFC',
            'theme_color'      => '#0F1720',
            'icons'            => [
                ['src' => SCH_URL . 'assets/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => SCH_URL . 'assets/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    private static function send_service_worker(): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . wp_parse_url(self::url(), PHP_URL_PATH));

        $css = sch_asset('assets/app.css');

        echo "const CACHE='sch-tch-" . SCH_VERSION . "';\nconst SHELL=['{$css}'];\n";
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

// لا نخزّن أي صفحة فيها بيانات طالب — الأصول الثابتة فقط.
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  if (!/\.(css|js|png|svg|woff2?)$/.test(new URL(req.url).pathname)) return;
  e.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
});
JS;
        exit;
    }

    // ---------- العرض ----------

    private static function render(string $view, array $data): never
    {
        $file = SCH_PATH . 'frontend/teacher/' . $view . '.php';
        if (!file_exists($file)) {
            $file = SCH_PATH . 'frontend/teacher/home.php';
        }

        $sch_view = $view;
        $sch_data = $data;

        require SCH_PATH . 'frontend/teacher/layout.php';
        exit;
    }

    // ---------- البيانات ----------

    /** شعب المعلم من الجدول ومن إسناد المواد. */
    public static function my_classes(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT c.* FROM ' . sch_table('classes') . ' c
             WHERE c.year_id = %d AND (
                   c.homeroom_teacher_id = %d
                OR c.id IN (SELECT cs.class_id FROM ' . sch_table('class_subjects') . ' cs WHERE cs.teacher_user_id = %d)
                OR c.id IN (SELECT t.class_id FROM ' . sch_table('timetable') . ' t WHERE t.teacher_user_id = %d)
             )
             ORDER BY c.grade_level, c.section',
            SCH_Years::current_id(),
            $user_id,
            $user_id,
            $user_id
        )) ?: [];
    }

    /** طلاب شعبة مع حالتهم اليوم — الأساس لشاشة الفصل. */
    public static function roster(int $class_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.id, s.full_name, s.first_name, s.father_name, s.grand_name, s.family_name,
                    s.photo_file, s.custody_state, a.status AS att_status
             FROM ' . sch_table('enrollments') . ' e
             INNER JOIN ' . sch_table('students') . ' s ON s.id = e.student_id
             LEFT JOIN ' . sch_table('attendance') . ' a
                    ON a.student_id = s.id AND a.att_date = %s
             WHERE e.class_id = %d AND e.status = %s AND s.status = %s
             ORDER BY s.full_name',
            current_time('Y-m-d'),
            $class_id,
            'active',
            'active'
        )) ?: [];
    }

    /** من لم يُرصد دخوله — شبكة الأمان الساعة 8:30. */
    public static function unmarked(array $roster): array
    {
        return array_values(array_filter(
            $roster,
            static fn (object $s): bool => $s->att_status === null
        ));
    }

    public static function photo_url(object $student): string
    {
        return $student->photo_file
            ? add_query_arg('sch_photo', '1', SCH_Dashboard::url('students', (int) $student->id))
            : '';
    }
}
