<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * تطبيق السائق — PWA على /driver.
 * مبني لاستخدام بيد واحدة والجوال مثبّت في الباص:
 * أزرار كبيرة، قائمة واحدة، وكل عملية تعمل دون إنترنت ثم تُزامَن.
 */
final class SCH_Driver
{
    public const BASE = 'driver';

    public static function init(): void
    {
        add_action('init', [self::class, 'add_rewrite']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'route'], 0);
    }

    public static function add_rewrite(): void
    {
        $b = self::BASE;
        add_rewrite_rule("^{$b}/?$", 'index.php?sch_drv=1&sch_drv_section=', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/?$", 'index.php?sch_drv=1&sch_drv_section=$matches[1]', 'top');
    }

    public static function query_vars(array $vars): array
    {
        return [...$vars, 'sch_drv', 'sch_drv_section'];
    }

    public static function url(string $section = ''): string
    {
        return home_url('/' . self::BASE . ($section !== '' ? '/' . $section : '') . '/');
    }

    public static function route(): void
    {
        if (!get_query_var('sch_drv')) {
            return;
        }

        $raw = (string) get_query_var('sch_drv_section');

        if ($raw === 'manifest.webmanifest') {
            self::send_manifest();
        }
        if ($raw === 'sw.js') {
            self::send_service_worker();
        }

        self::no_cache_headers();

        $section = sanitize_key(str_replace('.js', '', $raw));

        if ($section === 'logout') {
            wp_logout();
            wp_safe_redirect(self::url());
            exit;
        }

        if (!is_user_logged_in()) {
            self::render_login();
        }

        if (!current_user_can('sch_drive_trip')) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        self::handle_post();

        $trip = SCH_Trips::active_for_driver(get_current_user_id());

        if (in_array($section, ['stops', 'issue', 'history', 'bus'], true)) {
            self::render($section, ['trip' => $trip]);
        }

        self::render($trip ? 'trip' : 'home', ['trip' => $trip]);
    }

    private static function handle_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action = sanitize_key((string) ($_POST['sch_drv_action'] ?? ''));

        if ($action === 'report_issue' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_drv_issue')) {
            $bus = self::bus_of(get_current_user_id());

            $result = SCH_Assets::report_maintenance([
                'title'       => (string) ($_POST['title'] ?? ''),
                'description' => (string) ($_POST['description'] ?? ''),
                'location'    => $bus ? sprintf(__('الباص %s', 'school-system'), $bus->plate_no) : __('الباص', 'school-system'),
                'priority'    => (string) ($_POST['priority'] ?? 'medium'),
            ]);

            wp_safe_redirect(add_query_arg(
                is_wp_error($result) ? ['err' => '1'] : ['ok' => '1'],
                self::url('issue')
            ));
            exit;
        }
    }

    /** باص السائق. */
    public static function bus_of(int $user_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('buses') . ' WHERE driver_user_id = %d AND status <> %s LIMIT 1',
            $user_id,
            'retired'
        )) ?: null;
    }

    /** رحلات السائق اليوم. */
    public static function trips_today(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT t.*, r.name AS route_name,
                    (SELECT COUNT(DISTINCT e.student_id) FROM ' . sch_table('trip_events') . " e
                      WHERE e.trip_id = t.id AND e.type = 'board') AS boarded
             FROM " . sch_table('trips') . ' t
             INNER JOIN ' . sch_table('routes') . ' r ON r.id = t.route_id
             WHERE t.driver_user_id = %d AND t.trip_date = %s
             ORDER BY t.id DESC',
            $user_id,
            current_time('Y-m-d')
        )) ?: [];
    }

    // ---------- الدخول ----------

    private static function render_login(): never
    {
        // باب واحد للجميع: خمسة أبواب تعني خمسة روابط يحفظها المستخدم،
        // وأبًا يفتح باب الطالب فيُقال له «لا صلاحية» وهو محق في حسابه.
        wp_safe_redirect(SCH_Portal::url());
        exit;

        $error = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['sch_drv_login'])) {
            $error = self::attempt_login();
        }

        self::render('login', ['error' => $error]);
    }

    private static function attempt_login(): string
    {
        if (!wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_drv_login')) {
            return __('انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.', 'school-system');
        }

        $input = sanitize_text_field(wp_unslash((string) ($_POST['username'] ?? '')));
        $key   = 'sch_drvlock_' . md5($input . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ((int) get_transient($key) >= 5) {
            return __('محاولات كثيرة. أعد المحاولة بعد 15 دقيقة.', 'school-system');
        }

        $user = wp_signon([
            'user_login'    => self::resolve_login($input),
            'user_password' => (string) ($_POST['password'] ?? ''),
            'remember'      => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            set_transient($key, (int) get_transient($key) + 1, 15 * MINUTE_IN_SECONDS);
            sch_audit('driver.login_failed', 'user', null, ['login' => $input]);
            return __('بيانات الدخول غير صحيحة.', 'school-system');
        }

        $employee = SCH_Staff::get_by_user($user->ID);
        if ($employee && $employee->status !== 'active') {
            wp_logout();
            return __('هذا الحساب موقوف. راجع إدارة المدرسة.', 'school-system');
        }

        delete_transient($key);
        wp_set_current_user($user->ID);
        sch_audit('driver.login', 'user', $user->ID);

        wp_safe_redirect(self::url());
        exit;
    }

    /** يقبل رقم الجوال أو اسم المستخدم. */
    private static function resolve_login(string $input): string
    {
        global $wpdb;

        $phone = sch_normalize_phone($input);
        if ($phone === '') {
            return $input;
        }

        $user_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT user_id FROM ' . sch_table('employees') . ' WHERE phone = %s LIMIT 1',
            $phone
        ));

        $user = $user_id > 0 ? get_user_by('id', $user_id) : null;

        return $user instanceof WP_User ? $user->user_login : $input;
    }

    // ---------- ملفات التطبيق ----------

    private static function send_manifest(): never
    {
        header('Content-Type: application/manifest+json; charset=utf-8');

        echo wp_json_encode([
            'name'             => __('باص المدرسة — السائق', 'school-system'),
            'short_name'       => __('الباص', 'school-system'),
            'start_url'        => self::url(),
            'scope'            => self::url(),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'lang'             => 'ar',
            'dir'              => 'rtl',
            'background_color' => '#2A251E',
            'theme_color'      => '#2A251E',
            'icons'            => [
                ['src' => SCH_URL . 'assets/icon-driver-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => SCH_URL . 'assets/icon-driver-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    private static function send_service_worker(): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . wp_parse_url(self::url(), PHP_URL_PATH));

        $css     = sch_asset('assets/driver.css');
        $js      = sch_asset('assets/driver.js');
        $version = 'sch-drv-' . SCH_VERSION;

        echo "const CACHE='{$version}';\nconst SHELL=['{$css}','{$js}'];\n";
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
        $file = SCH_PATH . 'frontend/driver/' . $view . '.php';
        if (!file_exists($file)) {
            $file = SCH_PATH . 'frontend/driver/home.php';
        }

        $sch_view = $view;
        $sch_data = $data;

        require SCH_PATH . 'frontend/driver/layout.php';
        exit;
    }

    private static function no_cache_headers(): void
    {
        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-Content-Type-Options: nosniff');

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
    }

    /** المسار المُسنَد لسائق عبر باصه. */
    public static function route_of(int $user_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT r.*, b.plate_no FROM ' . sch_table('routes') . ' r
             INNER JOIN ' . sch_table('buses') . ' b ON b.id = r.bus_id
             WHERE b.driver_user_id = %d AND r.status = %s AND b.status = %s
             ORDER BY r.id LIMIT 1',
            $user_id,
            'active',
            'active'
        )) ?: null;
    }
}
