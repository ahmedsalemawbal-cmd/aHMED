<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * تطبيقان اثنان على المتاجر — لا خمسة.
 *
 * الشاشات الخمس تبقى كما هي (ولي الأمر · الطالب · المعلم · السائق · البوابة)،
 * لكنها تُغلَّف في **حزمتين** لأن المتجر لا يعرف الأدوار — يعرف تطبيقات:
 *
 * - **الأسرة** (`/a/family`): وليّ الأمر والطالب.
 * - **الموظفون** (`/a/staff`): المعلم والسائق والحارس.
 *
 * كلٌّ منهما PWA مستقلّ باسمه وأيقونته ولونه، ونقطة بدايته بوابة الدخول
 * الموحّدة نفسها — فمن يدخل يُوجَّه لشاشته تلقائيًا بـ`SCH_Portal::home_for()`.
 * ولا نكرّر شاشة واحدة ولا شفرة توجيه.
 *
 * **ولماذا `scope` على الجذر؟** لأن شاشات الحزمة الواحدة تعيش في مسارات
 * متفرّقة (`/app` و`/student`)، والنطاق الأضيق يجعل الانتقال بينها يفتح
 * متصفحًا فوق التطبيق. والحارس الحقيقي هو الصلاحية في الخادم لا نطاق البيان.
 */
final class SCH_Apps
{
    public const BASE = 'a';

    /**
     * الحزمتان: المفتاح => [الاسم، الاسم القصير، الوصف، اللون، الأيقونة، الصلاحيات].
     *
     * الصلاحيات تحدّد من يخصّه هذا التطبيق — فمن فتح الحزمة الخطأ يُقال له
     * أين يذهب بدل أن يُترك أمام شاشة لا تعمل.
     */
    private const PACKS = [
        'family' => [
            'name'  => 'مدرستي — الأسرة',
            'short' => 'مدرستي',
            'desc'  => 'متابعة الأبناء: الباص والحضور والدرجات والرسوم — ولحساب الطالب شاشته.',
            'color' => '#5170FF',
            'icon'  => 'icon',
            'caps'  => ['sch_view_own_children', 'sch_student_app'],
        ],
        'staff' => [
            'name'  => 'مدرستي — الموظفون',
            'short' => 'الموظفون',
            'desc'  => 'المعلم والسائق والحارس: الحضور والرحلات ومسح البوابة.',
            'color' => '#1F5F52',
            'icon'  => 'icon-gate',
            'caps'  => ['sch_manage_attendance', 'sch_drive_trip', 'sch_scan_gate'],
        ],
    ];

    public static function init(): void
    {
        add_action('init', [self::class, 'rewrite']);
        add_action('template_redirect', [self::class, 'route'], 0);
    }

    public static function rewrite(): void
    {
        $b = self::BASE;
        add_rewrite_rule("^{$b}/([a-z]+)/?$", 'index.php?sch_pack=$matches[1]', 'top');
        add_rewrite_rule("^{$b}/([a-z]+)/([a-z.-]+)$", 'index.php?sch_pack=$matches[1]&sch_pack_file=$matches[2]', 'top');
        add_rewrite_tag('%sch_pack%', '([a-z]+)');
        add_rewrite_tag('%sch_pack_file%', '([a-z.-]+)');
    }

    public static function url(string $pack, string $file = ''): string
    {
        return home_url('/' . self::BASE . '/' . $pack . ($file !== '' ? '/' . $file : '/'));
    }

    /** الحزم المعرَّفة — تستعملها شاشة الإعدادات لعرض روابط التثبيت. */
    public static function packs(): array
    {
        return self::PACKS;
    }

    /** هل يخصّ هذا المستخدم هذه الحزمة؟ */
    public static function belongs(string $pack, int $user_id = 0): bool
    {
        $user_id = $user_id ?: get_current_user_id();

        foreach ((array) (self::PACKS[$pack]['caps'] ?? []) as $cap) {
            if (user_can($user_id, $cap)) {
                return true;
            }
        }

        return false;
    }

    /** الحزمة التي يخصّها هذا المستخدم — لتوجيهه إلى تطبيقه الصحيح. */
    public static function pack_of(int $user_id = 0): string
    {
        foreach (array_keys(self::PACKS) as $pack) {
            if (self::belongs($pack, $user_id)) {
                return $pack;
            }
        }

        return '';
    }

    public static function route(): void
    {
        $pack = (string) get_query_var('sch_pack');

        if ($pack === '' || !isset(self::PACKS[$pack])) {
            return;
        }

        $file = (string) get_query_var('sch_pack_file');

        if ($file === 'manifest.webmanifest') {
            self::send_manifest($pack);
        }

        if ($file === 'sw.js') {
            self::send_service_worker($pack);
        }

        // نقطة البدء: من دخل يذهب لشاشته، ومن لم يدخل يرى بوابة الدخول.
        if (!is_user_logged_in()) {
            wp_safe_redirect(add_query_arg('pack', $pack, SCH_Portal::url()));
            exit;
        }

        // فتح الحزمة الخطأ: يُقال له أين يذهب بدل شاشة لا تعمل.
        if (!self::belongs($pack, get_current_user_id()) && !current_user_can('manage_options')) {
            $mine = self::pack_of();

            if ($mine !== '' && $mine !== $pack) {
                wp_safe_redirect(self::url($mine));
                exit;
            }
        }

        wp_safe_redirect(SCH_Portal::home_for());
        exit;
    }

    private static function send_manifest(string $pack): never
    {
        $p = self::PACKS[$pack];

        header('Content-Type: application/manifest+json; charset=utf-8');

        echo wp_json_encode([
            'id'               => '/' . self::BASE . '/' . $pack . '/',
            'name'             => sch_settings('school_name', get_bloginfo('name')) . ' — ' . $p['short'],
            'short_name'       => $p['short'],
            'description'      => $p['desc'],
            'start_url'        => self::url($pack),
            // الجذر: شاشات الحزمة الواحدة في مسارات متفرّقة، والنطاق الأضيق
            // يفتح متصفحًا فوق التطبيق عند الانتقال بينها.
            'scope'            => home_url('/'),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'lang'             => 'ar',
            'dir'              => 'rtl',
            'background_color' => '#FFFFFF',
            'theme_color'      => $p['color'],
            'categories'       => ['education'],
            // الأيقونات من ملفات الحزمة بمقاساتها المعلنة تمامًا — شعار المدرسة
            // المرفوع لا يُستعمل هنا: مقاسه غير معروف، ومقاس مُعلن كاذب يُسقِط
            // التحقق في متجر جوجل.
            'icons'            => [
                [
                    'src'     => SCH_URL . 'assets/' . $p['icon'] . '-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src'     => SCH_URL . 'assets/' . $p['icon'] . '-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    /**
     * عامل خدمة واحد للحزمة: يفتح هيكلها بلا إنترنت،
     * **ولا يخزّن أي صفحة فيها بيانات طالب** (قاعدة المشروع).
     */
    private static function send_service_worker(string $pack): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');

        $cache  = 'sch-' . $pack . '-' . SCH_VERSION;
        $assets = [
            sch_asset('assets/shared-ui.css'),
            sch_asset('assets/parent.css'),
            sch_asset('assets/list-tools.js'),
        ];

        echo "const C = " . wp_json_encode($cache) . ";\n";
        echo "const A = " . wp_json_encode(array_values($assets)) . ";\n";
        echo <<<'JS'
self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(C).then(function (c) { return c.addAll(A); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k !== C; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

/* الأصول الثابتة من الذاكرة، وكل ما عداها من الشبكة:
   لا تُخزَّن صفحة فيها بيانات طالب أبدًا. */
self.addEventListener('fetch', function (e) {
  var r = e.request;
  if (r.method !== 'GET') { return; }
  if (A.indexOf(new URL(r.url).pathname) === -1 && A.indexOf(r.url) === -1) { return; }
  e.respondWith(caches.match(r).then(function (hit) { return hit || fetch(r); }));
});
JS;

        // الإشعار الفوري: يصل والتطبيق مغلق، والضغط عليه يفتح الشاشة المقصودة.
        SCH_Push::sw_handlers();

        exit;
    }
}
