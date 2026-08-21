<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الجسر — استقبال البيانات من إضافة المتصفح.
 *
 * **المبدأ:** الإضافة تقرأ ما يراه الموظف في نور بعينه، وترسله لنظامه.
 * لا تكتب في نور شيئًا، ولا تعرف كلمة مروره، ولا تعمل في الخلفية —
 * تعمل بضغطته وهو أمام الصفحة.
 *
 * **ولماذا مفتاح منفصل لا كلمة مرور الحساب:** المفتاح يُلغى بضغطة إن ضاع
 * الجهاز، ولا يفتح لوحة الإدارة، ويعمل على الاستيراد وحده. وكلمة المرور
 * تفتح كل شيء ولا تُلغى إلا بتغييرها.
 *
 * **والإضافة ليست الطريق الوحيد أبدًا** — الاستيراد من ملف Excel يبقى قائمًا،
 * فإن تغيّر نور وتعطّلت القراءة، يُكمل المدير عمله بالملف.
 */
final class SCH_Bridge
{
    private const OPTION = 'sch_bridge_keys';

    /** المفتاح صالح ٩٠ يومًا — طويل بما يكفي لموسم التسجيل، قصير بما يكفي ليُراجَع. */
    private const TTL_DAYS = 90;

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route(SCH_API_NS, '/bridge/ping', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'ping'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(SCH_API_NS, '/bridge/import', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'import'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ---------- المفاتيح ----------

    /** @return array<string, array{user:int,label:string,created:string,expires:string,used:?string,count:int}> */
    private static function keys(): array
    {
        $keys = get_option(self::OPTION, []);

        return is_array($keys) ? $keys : [];
    }

    /**
     * مفتاح جديد — يُعرض مرة واحدة ثم يُخزَّن مجزّأً.
     *
     * التجزئة تعني أن تسريب قاعدة البيانات لا يُعطي مفاتيح عاملة.
     */
    public static function issue_key(string $label = ''): array|WP_Error
    {
        if (!current_user_can('sch_manage_students')) {
            return sch_api_error('forbidden', __('لا تملك صلاحية إصدار مفتاح.', 'school-system'), 403);
        }

        $plain = 'sch_' . wp_generate_password(40, false);
        $keys  = self::keys();

        // حد معقول: من يحتاج أكثر من عشرة أجهزة يُراجع طريقته لا يزيد المفاتيح.
        if (count($keys) >= 10) {
            return sch_api_error('too_many', __('لديك عشرة مفاتيح — ألغِ واحدًا قبل إصدار جديد.', 'school-system'), 422);
        }

        $keys[hash('sha256', $plain)] = [
            'user'    => get_current_user_id(),
            'label'   => sanitize_text_field($label) ?: __('جهاز بلا اسم', 'school-system'),
            'created' => sch_now(),
            'expires' => gmdate('Y-m-d H:i:s', time() + self::TTL_DAYS * DAY_IN_SECONDS),
            'used'    => null,
            'count'   => 0,
        ];

        update_option(self::OPTION, $keys, false);
        sch_audit('bridge.key_issued', 'user', get_current_user_id());

        return ['key' => $plain, 'expires' => $keys[hash('sha256', $plain)]['expires']];
    }

    public static function revoke_key(string $hash): bool
    {
        if (!current_user_can('sch_manage_students')) {
            return false;
        }

        $keys = self::keys();

        if (!isset($keys[$hash])) {
            return false;
        }

        unset($keys[$hash]);
        update_option(self::OPTION, $keys, false);
        sch_audit('bridge.key_revoked', 'user', get_current_user_id());

        return true;
    }

    /** @return array<string, array<string, mixed>> */
    public static function list_keys(): array
    {
        return self::keys();
    }

    // ---------- تنزيل الإضافة ----------

    /**
     * حزمة الإضافة — **تُبنى في الطلب من الملفّات المثبَّتة**.
     *
     * لا ملفٌّ جاهز يُرسَل بالبريد ويُنسى مع أوّل تحديث: الحزمة تخرج دائمًا
     * مطابقةً للنسخة العاملة على الخادم، فلا تتباعد إضافةُ الوكيل عن
     * نقطتَي `ping` و`import` اللتين تتحدّثان معها.
     *
     * والشاشة التي تُعطي المفتاح هي التي تُعطي الإضافة — والمفتاح وحده بلا
     * إضافةٍ ورقةٌ لا تُلصق في شيء.
     */
    public static function download(): never
    {
        if (!current_user_can('sch_manage_students')) {
            status_header(403);
            exit;
        }

        $dir = SCH_PATH . 'bridge';

        if (!class_exists('ZipArchive') || !is_dir($dir)) {
            status_header(500);
            echo esc_html__('تعذّر تجهيز الحزمة — الخادم لا يدعم ZipArchive.', 'school-system');
            exit;
        }

        $tmp = wp_tempnam('sch-bridge');
        $zip = new ZipArchive();

        if ($tmp === '' || $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            status_header(500);
            echo esc_html__('تعذّر تجهيز الحزمة.', 'school-system');
            exit;
        }

        $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        $n    = 0;

        foreach ($walk as $file) {
            if (!$file->isFile()) {
                continue;
            }

            // المسار داخل الحزمة نسبيّ — فتُفَكّ في مجلّدٍ واحد يُحمَّل كما هو.
            $zip->addFile($file->getPathname(), 'osool-noor-bridge/' . str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1)));
            $n++;
        }

        $zip->close();

        if ($n === 0 || !is_readable($tmp)) {
            @unlink($tmp);
            status_header(500);
            echo esc_html__('الحزمة فارغة — راجع تثبيت الإضافة.', 'school-system');
            exit;
        }

        sch_audit('bridge.downloaded', 'user', get_current_user_id(), ['files' => $n]);

        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Length: ' . (string) filesize($tmp));
        header('Content-Disposition: attachment; filename="osool-noor-bridge.zip"');
        header('X-Content-Type-Options: nosniff');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    // ---------- التحقق ----------

    private static function verify(WP_REST_Request $req): int|WP_Error
    {
        $sent = trim((string) ($req->get_header('X-SCH-Key') ?: $req->get_param('key')));

        if ($sent === '') {
            return sch_api_error('no_key', __('مفتاح الربط مفقود.', 'school-system'), 401);
        }

        $hash = hash('sha256', $sent);
        $keys = self::keys();

        // hash_equals على المجزّأ: المقارنة ثابتة الزمن فلا تُسرّب طول المطابقة.
        $found = null;

        foreach ($keys as $stored => $meta) {
            if (hash_equals($stored, $hash)) {
                $found = $stored;
                break;
            }
        }

        if ($found === null) {
            return sch_api_error('bad_key', __('مفتاح الربط غير صحيح.', 'school-system'), 401);
        }

        if ($keys[$found]['expires'] < sch_now()) {
            return sch_api_error('expired', __('انتهت صلاحية المفتاح — أصدر مفتاحًا جديدًا.', 'school-system'), 401);
        }

        $user = (int) $keys[$found]['user'];

        // الصلاحية تُفحص لحظة الاستخدام لا لحظة الإصدار — فالموظف الموقوف لا يستورد.
        if (!user_can($user, 'sch_manage_students')) {
            return sch_api_error('forbidden', __('صاحب المفتاح لم يعد يملك صلاحية الاستيراد.', 'school-system'), 403);
        }

        $keys[$found]['used'] = sch_now();
        $keys[$found]['count'] = (int) $keys[$found]['count'] + 1;
        update_option(self::OPTION, $keys, false);

        return $user;
    }

    // ---------- النقاط ----------

    /** تتحقق الإضافة من المفتاح قبل أن ترسل شيئًا. */
    public static function ping(WP_REST_Request $req): WP_REST_Response
    {
        $user = self::verify($req);

        if (is_wp_error($user)) {
            return new WP_REST_Response([
                'ok'    => false,
                'error' => $user->get_error_message(),
            ], 401);
        }

        return new WP_REST_Response([
            'ok'     => true,
            'school' => sch_settings('school_name', get_bloginfo('name')),
            'user'   => get_userdata($user)->display_name ?? '',
            'fields' => SCH_Import::COLUMNS,
        ], 200);
    }

    public static function import(WP_REST_Request $req): WP_REST_Response
    {
        $user = self::verify($req);

        if (is_wp_error($user)) {
            return new WP_REST_Response(['ok' => false, 'errors' => [$user->get_error_message()]], 401);
        }

        wp_set_current_user($user);

        $rows = $req->get_param('rows');
        $dry  = (bool) $req->get_param('dry_run');

        if (!is_array($rows)) {
            return new WP_REST_Response([
                'ok'     => false,
                'errors' => [__('لم تصل صفوف.', 'school-system')],
            ], 422);
        }

        $result = SCH_Import::run_rows($rows, $dry);

        sch_audit($dry ? 'bridge.preview' : 'bridge.import', 'user', $user, [
            'rows'     => count($rows),
            'imported' => $result['imported'],
        ]);

        return new WP_REST_Response($result, $result['ok'] ? 200 : 422);
    }
}
