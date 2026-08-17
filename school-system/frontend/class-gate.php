<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * تطبيق البوابة — الحارس.
 *
 * شاشة واحدة. لا قوائم ولا تبويبات.
 * الوضع الافتراضي صحيح 95% من الوقت: صباحًا «دخول» ومساءً «خروج».
 */
final class SCH_Gate
{
    public const BASE = 'gate';

    public const MODES = [
        'gate_in'   => 'دخول',
        'gate_out'  => 'خروج',
        'early_out' => 'خروج مبكر',
    ];

    public static function init(): void
    {
        add_action('init', [self::class, 'add_rewrite']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'route'], 0);
    }

    public static function add_rewrite(): void
    {
        $b = self::BASE;
        add_rewrite_rule("^{$b}/?$", 'index.php?sch_gate=1&sch_gate_section=', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/?$", 'index.php?sch_gate=1&sch_gate_section=$matches[1]', 'top');
    }

    public static function query_vars(array $vars): array
    {
        return [...$vars, 'sch_gate', 'sch_gate_section'];
    }

    public static function url(string $section = ''): string
    {
        return home_url('/' . self::BASE . ($section !== '' ? '/' . $section : '') . '/');
    }

    /** الوضع الافتراضي حسب الساعة — الحارس لا يختار في المعتاد. */
    public static function default_mode(): string
    {
        return (int) current_time('G') < 11 ? 'gate_in' : 'gate_out';
    }

    public static function route(): void
    {
        if (!get_query_var('sch_gate')) {
            return;
        }

        $raw = (string) get_query_var('sch_gate_section');

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

        if ($section === 'logout') {
            wp_logout();
            wp_safe_redirect(self::url());
            exit;
        }

        if (!is_user_logged_in()) {
            self::render_login();
        }

        if (!current_user_can('sch_scan_gate')) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        self::handle_post();

        if ($section === 'visitors') {
            self::render('visitors', []);
        }

        self::render('home', []);
    }

    /** الحارس يستقبل الزائر ويسجّله من جواله — هو من يقف عند الباب لا الإدارة. */
    private static function handle_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action = sanitize_key((string) ($_POST['sch_gate_action'] ?? ''));

        if ($action === 'visitor_in' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_gate_visitor')) {
            SCH_Security::check_in([
                'full_name'   => wp_unslash((string) ($_POST['full_name'] ?? '')),
                'national_id' => (string) ($_POST['national_id'] ?? ''),
                'phone'       => (string) ($_POST['phone'] ?? ''),
                'purpose'     => wp_unslash((string) ($_POST['purpose'] ?? '')),
            ]);

            wp_safe_redirect(add_query_arg('ok', '1', self::url('visitors')));
            exit;
        }

        if ($action === 'visitor_out' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_gate_visitor_out')) {
            SCH_Security::check_out(absint($_POST['visitor_id'] ?? 0));

            wp_safe_redirect(self::url('visitors'));
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

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['sch_gate_login'])) {
            $error = self::attempt_login();
        }

        self::render('login', ['error' => $error]);
    }

    private static function attempt_login(): string
    {
        if (!wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_gate_login')) {
            return __('انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.', 'school-system');
        }

        $input = sanitize_text_field(wp_unslash((string) ($_POST['username'] ?? '')));
        $key   = 'sch_gatelock_' . md5($input . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

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
            sch_audit('gate.login_failed', 'user', null, ['login' => $input]);
            return __('بيانات الدخول غير صحيحة.', 'school-system');
        }

        $employee = SCH_Staff::get_by_user($user->ID);
        if ($employee && $employee->status !== 'active') {
            wp_logout();
            return __('هذا الحساب موقوف. راجع إدارة المدرسة.', 'school-system');
        }

        delete_transient($key);
        wp_set_current_user($user->ID);
        sch_audit('gate.login', 'user', $user->ID);

        wp_safe_redirect(self::url());
        exit;
    }

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
            'name'             => __('بوابة المدرسة', 'school-system'),
            'short_name'       => __('البوابة', 'school-system'),
            'start_url'        => self::url(),
            'scope'            => self::url(),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'lang'             => 'ar',
            'dir'              => 'rtl',
            'background_color' => '#0F1720',
            'theme_color'      => '#0F1720',
            'icons'            => [
                ['src' => SCH_URL . 'assets/icon-gate-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => SCH_URL . 'assets/icon-gate-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    private static function send_service_worker(): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . wp_parse_url(self::url(), PHP_URL_PATH));

        $css   = sch_asset('assets/gate.css');
        $core  = sch_asset('assets/field-core.js');
        $js    = sch_asset('assets/gate.js');

        echo "const CACHE='sch-gate-" . SCH_VERSION . "';\nconst SHELL=['{$css}','{$core}','{$js}'];\n";
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
        $file = SCH_PATH . 'frontend/gate/' . $view . '.php';
        if (!file_exists($file)) {
            $file = SCH_PATH . 'frontend/gate/home.php';
        }

        $sch_view = $view;
        $sch_data = $data;

        require SCH_PATH . 'frontend/gate/layout.php';
        exit;
    }

    /** عدّاد اليوم — يظهر أعلى الشاشة. */
    public static function counters(): array
    {
        $board = SCH_Custody::board();

        return [
            'inside'  => (int) $board['at_school'],
            'outside' => (int) $board['home'] + (int) $board['on_bus'],
            'total'   => (int) $board['total'],
        ];
    }
}
