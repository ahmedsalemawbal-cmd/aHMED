<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** وقت الموقع بصيغة MySQL — لا `date()` فتُكتب بتوقيت الخادم. */
function mdd_now(): string
{
    return (string) current_time('mysql');
}

/**
 * رابط أصلٍ موسومٌ ببصمة الملفّ.
 *
 * الترقية تُفرّغ الكاش، لكن المتصفّح لا يعرف — والبصمة هي ما يجعله يطلب
 * النسخة الجديدة بدل أن يخدم القديمة من ذاكرته.
 */
function mdd_asset(string $relative): string
{
    static $stamps = [];

    $relative = ltrim($relative, '/');

    if (!isset($stamps[$relative])) {
        $path = MDD_PATH . $relative;
        $stamps[$relative] = is_readable($path) ? (string) filemtime($path) : MDD_VERSION;
    }

    return add_query_arg('v', $stamps[$relative], MDD_URL . $relative);
}

/** إعدادات المنصّة — للسوبر أدمن، لا للمشترك. */
function mdd_settings(?string $key = null, mixed $default = ''): mixed
{
    $all = get_option('mdd_settings', []);

    if (!is_array($all)) {
        $all = [];
    }

    return $key === null ? $all : ($all[$key] ?? $default);
}

/**
 * سطرٌ في سجلّ التدقيق.
 *
 * ويُكتب بالمستأجر الحاليّ تلقائيًّا: سجلٌّ لا يقول «لمن» لا يُفيد في
 * منصّةٍ فيها ألف مشترك.
 */
function mdd_audit(string $action, string $object_type = '', ?int $object_id = null, array $meta = []): void
{
    if (MDD_Tenant::current_id() <= 0) {
        return;
    }

    MDD_DB::insert('audit_log', [
        'user_id'     => get_current_user_id() ?: null,
        'action'      => sanitize_key($action),
        'object_type' => sanitize_key($object_type),
        'object_id'   => $object_id,
        'meta'        => $meta === [] ? null : (string) wp_json_encode($meta),
        'ip'          => mdd_client_ip(),
    ]);
}

/** أوّل عنوانٍ في السلسلة — وما بعده يزوّره المرسِل. */
function mdd_client_ip(): string
{
    $raw = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $one = trim(explode(',', $raw)[0]);

    return filter_var($one, FILTER_VALIDATE_IP) ? $one : '';
}

/** خطأٌ موحَّد الشكل — كما في أُصول، فالمعالِجات تقرؤه بلا اتّفاقٍ ثانٍ. */
function mdd_error(string $code, string $message, int $status = 400): WP_Error
{
    return new WP_Error('mdd_' . $code, $message, ['status' => $status]);
}

/** تاريخٌ صحيح أو `null` — لا سلسلةٌ فارغة تُكتب في عمود `DATE`. */
function mdd_date(mixed $value): ?string
{
    $value = trim((string) $value);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
}

/**
 * جوّالٌ سعوديّ موحَّد الشكل — أو فراغ.
 *
 * ورقم الجوّال اسمُ الدخول في مداد كما في أُصول، فتوحيده شرطُ ألّا يُنشئ
 * الشخصُ حسابين بكتابة `+966` مرّةً و`05` مرّة.
 */
function mdd_phone(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';

    if ($d === '') {
        return '';
    }

    if (str_starts_with($d, '966')) {
        $d = '0' . substr($d, 3);
    }

    if (strlen($d) === 9 && str_starts_with($d, '5')) {
        $d = '0' . $d;
    }

    return preg_match('/^05\d{8}$/', $d) === 1 ? $d : '';
}

/** رابطٌ داخل مداد: `/midad/…`. */
function mdd_url(string $path = ''): string
{
    $path = trim($path, '/');

    return home_url('/' . MDD_BASE . ($path !== '' ? '/' . $path : '') . '/');
}
