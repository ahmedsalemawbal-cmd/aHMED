<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الإشعارات الفورية — تصل الجوال والتطبيق مغلق.
 *
 * **بلا فايربيز وبلا مكتبة خارجية.** المعيار المفتوح (Web Push / VAPID) يعمل
 * على أندرويد وسطح المكتب وعلى آيفون ١٦٫٤+ للتطبيق المثبَّت، ويعمل داخل حزمة
 * TWA كما يعمل في المتصفح — فلا نربط منتجنا بخدمة تُحاسِب على نجاحه، ولا
 * ندخل Composer في مشروع قرّر ألّا يدخله.
 *
 * والرسالة **مشفَّرة من طرف إلى طرف** (RFC 8291): خادم الدفع وسيط لا يقرأ
 * «محمد لم يصل المدرسة» — يرى صندوقًا مغلقًا. وهذا شرط لا تحسين: البيانات
 * بيانات أطفال.
 *
 * وكل الاشتراك مربوط بالمستخدم، فمن سُحبت صلاحيته لا يصله شيء.
 */
final class SCH_Push
{
    /** عمر رمز VAPID — أقلّ من الحدّ الأقصى (٢٤ ساعة) بهامش أمان. */
    private const JWT_TTL = 43200;

    /** حجم السجل في تشفير aes128gcm. */
    private const RECORD_SIZE = 4096;

    // ---------- المفاتيح ----------

    /** هل النظام جاهز للإرسال؟ */
    public static function ready(): bool
    {
        return self::private_key() !== '' && self::public_key() !== '';
    }

    public static function public_key(): string
    {
        return (string) sch_settings('push_public', '');
    }

    private static function private_key(): string
    {
        return (string) sch_settings('push_private', '');
    }

    /**
     * توليد زوج مفاتيح VAPID مرة واحدة.
     *
     * المفتاح الخاص لا يغادر الخادم أبدًا، والعام يُسلَّم للمتصفح ليشترك به.
     * وتغييره **يُبطل كل الاشتراكات القائمة** — فلا يُولَّد إلا مرة.
     */
    public static function generate_keys(bool $force = false): true|WP_Error
    {
        if (self::ready() && !$force) {
            return true;
        }

        if (!function_exists('openssl_pkey_new')) {
            return sch_api_error('no_openssl', __('الخادم بلا openssl — لا يمكن توليد المفاتيح.', 'school-system'), 500);
        }

        $res = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($res === false) {
            return sch_api_error('keygen', __('تعذّر توليد المفاتيح.', 'school-system'), 500);
        }

        openssl_pkey_export($res, $pem);
        $detail = openssl_pkey_get_details($res);

        if (!is_array($detail) || !isset($detail['ec']['x'], $detail['ec']['y'])) {
            return sch_api_error('keygen', __('تعذّر قراءة المفاتيح.', 'school-system'), 500);
        }

        // النقطة غير المضغوطة: 0x04 || X(32) || Y(32)
        $public = "\x04" . str_pad((string) $detail['ec']['x'], 32, "\0", STR_PAD_LEFT)
                         . str_pad((string) $detail['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $s = sch_settings();
        $s = is_array($s) ? $s : [];
        $s['push_private'] = (string) $pem;
        $s['push_public']  = self::b64((string) $public);
        update_option('sch_settings', $s);

        sch_audit('push.keys_generated', 'settings', null, []);

        return true;
    }

    // ---------- الاشتراكات ----------

    /** حفظ اشتراك جهاز — أو تحديثه إن تكرّر. */
    public static function subscribe(int $user_id, array $sub): true|WP_Error
    {
        global $wpdb;

        $endpoint = esc_url_raw((string) ($sub['endpoint'] ?? ''));
        $p256dh   = sanitize_text_field((string) ($sub['p256dh'] ?? ''));
        $auth     = sanitize_text_field((string) ($sub['auth'] ?? ''));

        if ($user_id <= 0 || $endpoint === '' || $p256dh === '' || $auth === '') {
            return sch_api_error('bad_sub', __('بيانات الاشتراك ناقصة.', 'school-system'), 422);
        }

        $now  = sch_now();
        $hash = hash('sha256', $endpoint);

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('push_subs') . ' WHERE endpoint_hash = %s',
            $hash
        ));

        $row = [
            'user_id'       => $user_id,
            'endpoint'      => $endpoint,
            'endpoint_hash' => $hash,
            'p256dh'        => $p256dh,
            'auth'          => $auth,
            // اسم عائلة الجهاز فقط لا بصمة المتصفح كاملة — يكفي ليعرف صاحبه
            // أي جهاز اشترك، ولا يصلح للتعقّب.
            'device'        => sanitize_text_field((string) ($sub['device'] ?? '')) ?: null,
            'status'        => 'active',
            'updated_at'    => $now,
        ];

        if ($exists > 0) {
            $wpdb->update(sch_table('push_subs'), $row, ['id' => $exists]);
        } else {
            $wpdb->insert(sch_table('push_subs'), $row + ['created_at' => $now]);
        }

        return true;
    }

    /**
     * إلغاء اشتراك جهاز.
     *
     * `$owner` يقيّد الحذف بصاحبه: الطلب القادم من متصفح يحمل عنوانًا يكتبه
     * صاحبه، ولولا القيد لأسكت أيُّ مستخدم إشعارات غيره بتخمين العنوان.
     * والنداء من داخل الخادم (رد 410) يمرّ بلا قيد لأنه ليس طلب مستخدم.
     */
    public static function unsubscribe(string $endpoint, int $owner = 0): void
    {
        global $wpdb;

        $where = ['endpoint_hash' => hash('sha256', $endpoint)];

        if ($owner > 0) {
            $where['user_id'] = $owner;
        }

        $wpdb->delete(sch_table('push_subs'), $where);
    }

    /** اشتراكات مستخدم — قد يملك أكثر من جهاز. */
    public static function subs_of(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('push_subs') . " WHERE user_id = %d AND status = 'active'",
            $user_id
        )) ?: [];
    }

    /**
     * هل لهذا المستخدم جهاز مشترك؟
     *
     * سؤال الطابور عن كل مستلم، فبثّ لثلاثمئة وليّ أمر يسأله ثلاثمئة مرة —
     * عدّ لا جلب صفوف كاملة لا يُقرأ منها شيء.
     */
    public static function has_sub(int $user_id): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('push_subs') . " WHERE user_id = %d AND status = 'active'",
            $user_id
        )) > 0;
    }

    /** عدد الأجهزة المشتركة — تعرضه شاشة الإعدادات. */
    public static function device_count(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . sch_table('push_subs') . " WHERE status = 'active'");
    }

    // ---------- الإرسال ----------

    /**
     * إرسال إشعار لكل أجهزة مستخدم. يُرجع عدد ما وصل.
     *
     * @param array{title:string,body?:string,url?:string,tag?:string} $msg
     */
    public static function to_user(int $user_id, array $msg): int
    {
        if (!self::ready()) {
            return 0;
        }

        $sent = 0;

        foreach (self::subs_of($user_id) as $sub) {
            if (self::deliver($sub, $msg)) {
                $sent++;
            }
        }

        return $sent;
    }

    /** إرسال لاشتراك واحد. */
    private static function deliver(object $sub, array $msg): bool
    {
        $payload = wp_json_encode([
            'title' => (string) ($msg['title'] ?? ''),
            'body'  => (string) ($msg['body'] ?? ''),
            'url'   => (string) ($msg['url'] ?? home_url('/')),
            'tag'   => (string) ($msg['tag'] ?? 'sch'),
        ], JSON_UNESCAPED_UNICODE);

        $body = self::encrypt((string) $payload, (string) $sub->p256dh, (string) $sub->auth);

        if ($body === null) {
            return false;
        }

        $jwt = self::vapid_token((string) $sub->endpoint);

        if ($jwt === null) {
            return false;
        }

        $res = wp_remote_post((string) $sub->endpoint, [
            'timeout' => 8,
            'headers' => [
                'Authorization'    => 'vapid t=' . $jwt . ', k=' . self::public_key(),
                'Content-Encoding' => 'aes128gcm',
                'Content-Type'     => 'application/octet-stream',
                'TTL'              => '86400',
                'Urgency'          => 'high',
            ],
            'body'    => $body,
        ]);

        if (is_wp_error($res)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($res);

        // 404/410: الجهاز ألغى الاشتراك — يُحذف فلا نطرق بابًا مغلقًا كل يوم
        if ($code === 404 || $code === 410) {
            self::unsubscribe((string) $sub->endpoint);

            return false;
        }

        return $code >= 200 && $code < 300;
    }

    /** إشعار تجريبي لصاحب الشاشة — يثبت أن السلسلة كلها تعمل قبل الاعتماد عليها. */
    public static function test_to_self(): int
    {
        return self::to_user(get_current_user_id(), [
            'title' => sch_settings('school_name', get_bloginfo('name')),
            'body'  => __('هذا إشعار تجريبي — الإشعارات الفورية تعمل.', 'school-system'),
            'url'   => home_url('/'),
            'tag'   => 'sch-test',
        ]);
    }

    // ---------- الطرف الآخر: المتصفح ----------

    /**
     * تهيئة الاشتراك في الصفحة — تُستدعى من قوالب التطبيقات بعد تسجيل عامل الخدمة.
     *
     * تطبع ولا تُرجع: النص جافاسكربت لا محتوى مستخدم، وإرجاعه سيوهم القالب
     * أنه بحاجة تهريب.
     */
    public static function boot(string $sw = ''): void
    {
        if (!self::ready() || !is_user_logged_in()) {
            return;
        }

        $cfg = [
            'key'   => self::public_key(),
            'sub'   => rest_url(SCH_API_NS . '/me/push/subscribe'),
            'unsub' => rest_url(SCH_API_NS . '/me/push/unsubscribe'),
            'nonce' => wp_create_nonce('wp_rest'),
            // شاشة لا تسجّل عامل خدمة بنفسها (الداشبورد) تمرّر عاملها هنا،
            // وإلا انتظر السكربت عاملًا لا يأتي أبدًا فلا يظهر خطأ ولا نتيجة.
            'sw'    => $sw,
        ];

        echo '<script>window.SCH_PUSH=' . wp_json_encode($cfg) . ';</script>' . "\n";
        echo '<script src="' . esc_url(sch_asset('assets/push.js')) . '" defer></script>' . "\n";
    }

    /**
     * مستمعا `push` و`notificationclick` — نصّ واحد تشترك فيه عوامل الخدمة الستة.
     *
     * ولا يُكتب مرتين: عامل خدمة بلا مستمع `push` يستقبل الرسالة ويرميها،
     * فيبدو العطل في الخادم وهو في المتصفح.
     */
    public static function sw_handlers(): void
    {
        $icon = SCH_URL . 'assets/icon-192.png';

        echo "\nconst SCH_ICON = " . wp_json_encode($icon) . ";\n";
        echo <<<'JS'

self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) { d = {}; }
  e.waitUntil(self.registration.showNotification(d.title || 'مدرستي', {
    body: d.body || '',
    icon: SCH_ICON,
    badge: SCH_ICON,
    dir: 'rtl',
    lang: 'ar',
    tag: d.tag || 'sch',
    renotify: true,
    data: { url: d.url || '/' }
  }));
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (ws) {
    for (var i = 0; i < ws.length; i++) {
      if ('focus' in ws[i]) { ws[i].navigate(url); return ws[i].focus(); }
    }
    return clients.openWindow(url);
  }));
});

JS;
    }

    // ---------- التشفير (RFC 8291 · aes128gcm) ----------

    /**
     * تشفير الحمولة بمفتاح الجهاز — فخادم الدفع وسيط لا يقرأ محتواها.
     */
    private static function encrypt(string $payload, string $p256dh_b64, string $auth_b64): ?string
    {
        $ua_public = self::b64d($p256dh_b64);
        $auth      = self::b64d($auth_b64);

        if (strlen($ua_public) !== 65 || $auth === '') {
            return null;
        }

        // مفتاح عابر لكل رسالة
        $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

        if ($eph === false) {
            return null;
        }

        $d = openssl_pkey_get_details($eph);

        if (!is_array($d)) {
            return null;
        }

        $as_public = "\x04" . str_pad((string) $d['ec']['x'], 32, "\0", STR_PAD_LEFT)
                            . str_pad((string) $d['ec']['y'], 32, "\0", STR_PAD_LEFT);

        // السرّ المشترك: ECDH مع مفتاح الجهاز العام
        $peer = self::peer_pem($ua_public);

        if ($peer === null) {
            return null;
        }

        $shared = openssl_pkey_derive($peer, $eph, 32);

        if ($shared === false) {
            return null;
        }

        $salt = random_bytes(16);

        // PRK ثم مفتاح المحتوى ورقم الاستعمال — كما نصّ المعيار حرفيًا
        $prk = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $ua_public . $as_public, $auth);
        $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nnc = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\0", $salt);

        // 0x02 فاصل نهاية السجل (لا حشو بعده)
        $tag = '';
        $ct  = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nnc, $tag);

        if ($ct === false) {
            return null;
        }

        // الرأس: salt(16) + recordSize(4) + idLen(1) + المفتاح العام(65)
        return $salt . pack('N', self::RECORD_SIZE) . chr(65) . $as_public . $ct . $tag;
    }

    /** بناء مفتاح عام صالح لـopenssl من نقطة غير مضغوطة. */
    private static function peer_pem(string $point): mixed
    {
        // ترويسة DER ثابتة لمنحنى prime256v1 بمفتاح عام غير مضغوط
        $der = base64_decode('MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', true) . substr($point, 1);
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);

        return $key === false ? null : $key;
    }

    // ---------- رمز VAPID ----------

    /** رمز موقَّع يثبت للخادم أننا نحن — صالح لأصل الخادم وحده. */
    private static function vapid_token(string $endpoint): ?string
    {
        $parts = wp_parse_url($endpoint);

        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $aud   = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        $email = (string) sch_settings('school_email', get_option('admin_email'));

        // بلا تهريب الشرطات: `https:\/\/` صحيح لكنه يخالف كل التطبيقات المرجعية
        // ويكبّر الرمز بلا فائدة.
        $head = self::b64((string) wp_json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
        $body = self::b64((string) wp_json_encode([
            'aud' => $aud,
            'exp' => time() + self::JWT_TTL,
            'sub' => is_email($email) ? 'mailto:' . $email : home_url('/'),
        ], JSON_UNESCAPED_SLASHES));

        $key = openssl_pkey_get_private(self::private_key());

        if ($key === false) {
            return null;
        }

        $der = '';

        if (!openssl_sign($head . '.' . $body, $der, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $raw = self::der_to_raw($der);

        return $raw === null ? null : $head . '.' . $body . '.' . self::b64($raw);
    }

    /**
     * توقيع openssl يأتي بصيغة DER، وES256 يريد r‖s خامَّين بـ٦٤ بايت.
     * هذه الترجمة هي كل الفرق بين رمز مقبول ورمز يُرفض بلا رسالة مفهومة.
     */
    private static function der_to_raw(string $der): ?string
    {
        $off = 0;

        if (($der[$off++] ?? '') !== "\x30") {
            return null;
        }

        // طول التسلسل: قصير أو ممتد ببايت واحد
        $len = ord($der[$off++] ?? "\0");

        if ($len > 0x80) {
            $off += ($len - 0x80);
        }

        $read = static function () use ($der, &$off): ?string {
            if (($der[$off++] ?? '') !== "\x02") {
                return null;
            }

            $n = ord($der[$off++] ?? "\0");
            $v = substr($der, $off, $n);
            $off += $n;

            // إزالة بايت الصفر الذي يمنع تفسير الرقم سالبًا، ثم الحشو إلى ٣٢
            $v = ltrim($v, "\0");

            return str_pad($v, 32, "\0", STR_PAD_LEFT);
        };

        $r = $read();
        $s = $read();

        return ($r === null || $s === null) ? null : $r . $s;
    }

    // ---------- أدوات ----------

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64d(string $s): string
    {
        $s = strtr($s, '-_', '+/');

        return (string) base64_decode(str_pad($s, (int) (ceil(strlen($s) / 4) * 4), '=', STR_PAD_RIGHT), true);
    }
}
