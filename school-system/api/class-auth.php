<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * مصادقة التطبيقات: JWT (HS256) بلا مكتبات خارجية.
 * access_token قصير العمر، refresh_token مربوط بالجهاز وقابل للإلغاء.
 */
final class SCH_Auth
{
    private const ACCESS_TTL  = 900;          // 15 دقيقة
    private const REFRESH_TTL = 30 * DAY_IN_SECONDS;

    public static function register_routes(): void
    {
        register_rest_route(SCH_API_NS, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'login'],
            'permission_callback' => '__return_true', // نقطة الدخول الوحيدة المفتوحة
            'args'                => [
                'username'  => ['required' => true],
                'password'  => ['required' => true],
                'device_id' => ['required' => true],
            ],
        ]);

        register_rest_route(SCH_API_NS, '/auth/refresh', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'refresh'],
            'permission_callback' => '__return_true',
            'args'                => ['refresh_token' => ['required' => true]],
        ]);

        register_rest_route(SCH_API_NS, '/auth/logout', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'logout'],
            'permission_callback' => static fn (): bool => is_user_logged_in(),
        ]);
    }

    public static function login(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $username = sanitize_user((string) $req->get_param('username'));

        // شاشة الداشبورد تقفل بعد خمس محاولات، وهذا المسار كان بلا أي حدّ —
        // فصار الطريق الجانبي المفتوح لتجربة كلمات المرور، يتجاوز القفل تمامًا.
        if (self::is_throttled($username)) {
            return sch_api_error(
                'too_many_attempts',
                __('محاولات كثيرة. انتظر ربع ساعة ثم أعد المحاولة.', 'school-system'),
                429
            );
        }

        $user = wp_authenticate($username, (string) $req->get_param('password'));

        if (is_wp_error($user)) {
            self::count_failure($username);

            // رسالة واحدة لكل فشل: «اسم غير موجود» تكشف من هو مسجَّل ومن ليس.
            return sch_api_error('invalid_credentials', __('بيانات الدخول غير صحيحة.', 'school-system'), 401);
        }

        self::clear_failures($username);

        $device_id = sanitize_text_field((string) $req->get_param('device_id'));
        $refresh   = self::issue_refresh_token($user->ID, $device_id, (string) $req->get_param('device_name'));

        sch_audit('auth.login', 'user', $user->ID, ['device' => $device_id]);

        return new WP_REST_Response([
            'access_token'  => self::issue_access_token($user->ID),
            'refresh_token' => $refresh,
            'expires_in'    => self::ACCESS_TTL,
            'user'          => [
                'id'    => $user->ID,
                'name'  => $user->display_name,
                'roles' => array_values($user->roles),
            ],
        ]);
    }

    public static function refresh(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $token = (string) $req->get_param('refresh_token');
        $row   = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('refresh_tokens') . '
             WHERE token_hash = %s AND revoked_at IS NULL AND expires_at > %s LIMIT 1',
            hash('sha256', $token),
            sch_now()
        ));

        if (!$row) {
            return sch_api_error('invalid_refresh_token', __('الجلسة منتهية. سجّل الدخول من جديد.', 'school-system'), 401);
        }

        // صاحب الرمز قد يكون حُذف أو أُوقف بعد إصداره — والرمز يعيش ٣٠ يومًا.
        $user_id = (int) $row->user_id;
        if (!get_userdata($user_id)) {
            $wpdb->update(sch_table('refresh_tokens'), ['revoked_at' => sch_now()], ['id' => (int) $row->id]);

            return sch_api_error('invalid_refresh_token', __('الجلسة منتهية. سجّل الدخول من جديد.', 'school-system'), 401);
        }

        // **تدوير الرمز:** الرمز المستهلك يُلغى ويُصدر بديله. بلا تدوير يبقى
        // رمز مسروق صالحًا شهرًا كاملًا بلا أثر ولا طريقة لكشف الاستخدام المزدوج.
        $fresh = self::issue_refresh_token(
            $user_id,
            (string) $row->device_id,
            (string) ($row->device_name ?? '')
        );

        return new WP_REST_Response([
            'access_token'  => self::issue_access_token($user_id),
            'refresh_token' => $fresh,
            'expires_in'    => self::ACCESS_TTL,
        ]);
    }

    public static function logout(WP_REST_Request $req): WP_REST_Response
    {
        global $wpdb;

        $wpdb->update(
            sch_table('refresh_tokens'),
            ['revoked_at' => sch_now()],
            ['user_id' => get_current_user_id(), 'device_id' => sanitize_text_field((string) $req->get_param('device_id'))]
        );

        return new WP_REST_Response(['ok' => true]);
    }

    /** يربط ترويسة Bearer بمستخدم ووردبريس في كل طلب REST. */
    public static function authenticate_bearer(mixed $user_id): mixed
    {
        if ($user_id) {
            return $user_id; // مصادَق مسبقًا بالكوكيز (الداشبورد)
        }

        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            return $user_id;
        }

        $payload = self::decode(substr($header, 7));
        return $payload['sub'] ?? $user_id;
    }

    /** إلغاء كل جلسات الجوال لمستخدم — يُستدعى عند إيقاف موظف. */
    public static function revoke_all_sessions(int $user_id): void
    {
        global $wpdb;

        $wpdb->update(
            sch_table('refresh_tokens'),
            ['revoked_at' => sch_now()],
            ['user_id' => $user_id, 'revoked_at' => null]
        );

        if (function_exists('wp_destroy_all_sessions')) {
            $manager = WP_Session_Tokens::get_instance($user_id);
            $manager->destroy_all();
        }
    }

    private static function issue_access_token(int $user_id): string
    {
        $now = time();
        return self::encode([
            'sub' => $user_id,
            'iat' => $now,
            'exp' => $now + self::ACCESS_TTL,
            'iss' => home_url(),
        ]);
    }

    private static function issue_refresh_token(int $user_id, string $device_id, string $device_name = ''): string
    {
        global $wpdb;

        $token = bin2hex(random_bytes(32));

        // جهاز واحد = جلسة واحدة: ألغِ السابقة قبل إصدار جديدة.
        $wpdb->update(
            sch_table('refresh_tokens'),
            ['revoked_at' => sch_now()],
            ['user_id' => $user_id, 'device_id' => $device_id, 'revoked_at' => null]
        );

        $wpdb->insert(sch_table('refresh_tokens'), [
            'user_id'     => $user_id,
            'token_hash'  => hash('sha256', $token),
            'device_id'   => $device_id,
            'device_name' => sanitize_text_field($device_name) ?: null,
            'expires_at'  => gmdate('Y-m-d H:i:s', time() + self::REFRESH_TTL),
            'created_at'  => sch_now(),
        ]);

        return $token;
    }

    // ---------- حدّ المحاولات ----------

    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    /** مفتاح العدّاد: الاسم + IP — فلا يُقفل حساب موظف بمحاولات من شبكة أخرى. */
    private static function throttle_key(string $username): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

        return 'sch_api_try_' . md5(strtolower($username) . '|' . $ip);
    }

    private static function is_throttled(string $username): bool
    {
        return (int) get_transient(self::throttle_key($username)) >= self::MAX_ATTEMPTS;
    }

    private static function count_failure(string $username): void
    {
        $key = self::throttle_key($username);

        set_transient($key, (int) get_transient($key) + 1, self::LOCK_MINUTES * MINUTE_IN_SECONDS);
    }

    private static function clear_failures(string $username): void
    {
        delete_transient(self::throttle_key($username));
    }

    // ---------- التوقيع ----------

    /**
     * مفتاح التوقيع — **ويُولَّد إن غاب**.
     *
     * كان يُولَّد في `SCH_Activator::activate()` وحدها. ومن نسخ مجلد الإضافة
     * يدويًا بلا تفعيل (وهذا ما يحدث عند التسليم بملف ZIP)، أو حُذف الخيار من
     * قاعدته، يصير المفتاح نصًّا فارغًا — و`hash_hmac` بمفتاح فارغ نتيجة
     * **يستطيع أي أحد حسابها**، فيُوقّع رمزًا صالحًا لأي مستخدم بما فيه المدير.
     *
     * الآن: يُولَّد عند أول حاجة، ويُرفض التوقيع والتحقق إن كان أقصر من 32 بايتًا.
     */
    private static function secret(): string
    {
        $secret = (string) get_option('sch_jwt_secret', '');

        if (strlen($secret) < 32) {
            $secret = wp_generate_password(64, true, true);
            update_option('sch_jwt_secret', $secret, false);
            sch_audit('auth.secret_generated', 'system');
        }

        return $secret;
    }

    private static function encode(array $payload): string
    {
        $secret = self::secret();
        if (strlen($secret) < 32) {
            return '';
        }

        $header = self::b64(wp_json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body   = self::b64(wp_json_encode($payload));
        $sig    = self::b64(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

        return "{$header}.{$body}.{$sig}";
    }

    private static function decode(string $jwt): ?array
    {
        $secret = self::secret();
        if (strlen($secret) < 32) {
            return null; // بلا مفتاح صالح لا يُقبل أي رمز — الرفض أسلم من القبول.
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $sig] = $parts;

        $expected = self::b64(hash_hmac('sha256', "{$header}.{$body}", $secret, true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $payload = json_decode(self::b64_decode($body), true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64_decode(string $encoded): string
    {
        return (string) base64_decode(strtr($encoded, '-_', '+/'), true);
    }
}
