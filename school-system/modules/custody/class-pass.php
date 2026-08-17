<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * تصريح الطالب المتجدد.
 *
 * الباركود الثابت على شاشة الجوال **يُصوَّر ويُرسَل**: طالب يرسل لقطة لزميله
 * فيمسحها عند البوابة، فيُسجَّل دخول الأول وهو في البيت — وتسقط سلسلة العهدة من أساسها.
 *
 * الحل: رمز يُشتق من مفتاح الطالب والوقت، ويتغيّر كل 30 ثانية.
 * لقطة الشاشة تصير عديمة القيمة بعد نصف دقيقة.
 *
 * **ولا يخرج المفتاح من الخادم أبدًا** — لا للجوال ولا لجهاز البوابة.
 * وجهاز البوابة يعمل بلا إنترنت لأن المسح يُخزَّن بوقته ثم يُتحقَّق منه عند المزامنة
 * مقابل نافذة **وقت المسح** لا وقت الوصول.
 */
final class SCH_Pass
{
    /** طول النافذة بالثواني. */
    public const WINDOW = 30;

    /** نوافذ التسامح قبل وبعد — تغطي فروق ساعة الجهاز وبطء المسح. */
    private const SKEW = 2;

    /** بادئة تميّز الرمز المتجدد عن الباركود المطبوع. */
    public const PREFIX = 'SR';

    /** طول المُعرِّف الظاهر — كافٍ للبحث ولا يكشف الرقم الأكاديمي. */
    private const REF_LEN = 10;

    // ---------- التوليد ----------

    /** مفتاح الطالب — يُشتق من رمز بطاقته وملح التثبيت، فلا يُخزَّن مرتين. */
    private static function secret(object $student): string
    {
        return hash_hmac(
            'sha256',
            'sch-pass|' . (string) $student->id . '|' . (string) $student->badge_token,
            wp_salt('auth')
        );
    }

    private static function window_at(int $timestamp): int
    {
        return (int) floor($timestamp / self::WINDOW);
    }

    private static function code_for(object $student, int $window): string
    {
        $mac = hash_hmac('sha256', (string) $window, self::secret($student), true);

        // اقتطاع ديناميكي كالمعتاد في رموز الوقت — ستة أرقام تكفي لنافذة 30 ثانية.
        $offset = ord($mac[strlen($mac) - 1]) & 0x0F;
        $chunk  = (
            ((ord($mac[$offset]) & 0x7F) << 24) |
            (ord($mac[$offset + 1]) << 16) |
            (ord($mac[$offset + 2]) << 8) |
            ord($mac[$offset + 3])
        ) % 1000000;

        return str_pad((string) $chunk, 6, '0', STR_PAD_LEFT);
    }

    public static function ref_of(object $student): string
    {
        return substr((string) $student->badge_token, 0, self::REF_LEN);
    }

    /** الرمز الحالي ومدة بقائه — ما يعرضه تطبيق الطالب. */
    public static function current(object $student): array
    {
        $now    = time();
        $window = self::window_at($now);

        return [
            'payload'    => sprintf('%s.%s.%s', self::PREFIX, self::ref_of($student), self::code_for($student, $window)),
            'expires_in' => self::WINDOW - ($now % self::WINDOW),
            'window'     => self::WINDOW,
        ];
    }

    // ---------- التحقق ----------

    public static function looks_like(string $token): bool
    {
        return str_starts_with($token, self::PREFIX . '.');
    }

    /**
     * التحقق من رمز ممسوح.
     *
     * @param string   $token       الرمز كما قرأه القارئ
     * @param int|null $scanned_at  وقت المسح — لا وقت الوصول، فالطابور قد يتأخر ساعة
     */
    public static function verify(string $token, ?int $scanned_at = null): ?object
    {
        global $wpdb;

        if (!self::looks_like($token)) {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [, $ref, $code] = $parts;

        if (!preg_match('/^[a-f0-9]{' . self::REF_LEN . '}$/i', $ref) || !preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $student = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . sch_table('students') . "
             WHERE badge_token LIKE %s AND status = 'active' LIMIT 1",
            $wpdb->esc_like($ref) . '%'
        ));

        if (!$student) {
            return null;
        }

        $at     = $scanned_at ?? time();
        $center = self::window_at($at);

        for ($i = -self::SKEW; $i <= self::SKEW; $i++) {
            // hash_equals يمنع تسريب المعلومة عبر زمن المقارنة.
            if (hash_equals(self::code_for($student, $center + $i), $code)) {
                return $student;
            }
        }

        sch_audit('pass.rejected', 'student', (int) $student->id, ['ref' => $ref]);

        return null;
    }

    /** صورة الرمز — تُبنى في الخادم فلا يغادر المفتاح ولا يحتاج التطبيق مكتبة. */
    public static function svg(object $student, int $module = 6): string
    {
        $pass = self::current($student);

        return SCH_QR::svg($pass['payload'], $module, 2);
    }
}
