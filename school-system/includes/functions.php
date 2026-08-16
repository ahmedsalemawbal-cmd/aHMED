<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** اسم جدول بالسابقة الصحيحة. */
function sch_table(string $name): string
{
    global $wpdb;
    return $wpdb->prefix . 'sch_' . $name;
}

/** الوقت الحالي بتوقيت الموقع بصيغة MySQL. */
function sch_now(): string
{
    return current_time('mysql');
}

/** رمز QR عشوائي للطالب — لا يحمل أي بيانات شخصية. */
function sch_generate_qr_token(): string
{
    return bin2hex(random_bytes(16));
}

/** تسجيل عملية حساسة في سجل التدقيق. */
function sch_audit(string $action, ?string $object_type = null, ?int $object_id = null, array $meta = []): void
{
    global $wpdb;

    $wpdb->insert(sch_table('audit_log'), [
        'user_id'     => get_current_user_id() ?: null,
        'action'      => $action,
        'object_type' => $object_type,
        'object_id'   => $object_id,
        'meta'        => $meta ? wp_json_encode($meta) : null,
        'ip'          => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null,
        'created_at'  => sch_now(),
    ]);
}

/** استجابة خطأ موحّدة للـAPI. */
function sch_api_error(string $code, string $message, int $status = 400): WP_Error
{
    return new WP_Error('sch_' . $code, $message, ['status' => $status]);
}

/** توحيد صيغة رقم الجوال السعودي إلى 05xxxxxxxx. */
function sch_normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '966')) {
        $digits = '0' . substr($digits, 3);
    }
    if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
        $digits = '0' . $digits;
    }
    return preg_match('/^05\d{8}$/', $digits) === 1 ? $digits : '';
}

/** تحقق من صيغة تاريخ Y-m-d. */
function sch_sanitize_date(mixed $value): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value ? $value : null;
}

/** إعدادات المدرسة (مصفوفة واحدة في options). */
function sch_settings(?string $key = null, mixed $default = ''): mixed
{
    $settings = get_option('sch_settings', []);
    if (!is_array($settings)) {
        $settings = [];
    }
    if ($key === null) {
        return $settings;
    }
    return $settings[$key] ?? $default;
}

/** تحقق من صيغة تاريخ ووقت Y-m-d H:i:s. */
function sch_sanitize_datetime(mixed $value): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'] as $format) {
        $d = DateTime::createFromFormat($format, $value);
        if ($d) {
            return $d->format('Y-m-d H:i:s');
        }
    }
    return null;
}

/** تنسيق مبلغ بالريال. */
function sch_money(float|string|null $amount): string
{
    return number_format((float) $amount, 2) . ' ' . __('ر.س', 'school-system');
}

/**
 * رابط أصل موسوم بتاريخ تعديله.
 *
 * وسم الأصول برقم الإضافة عطل حقيقي وقع عندنا: نُصلح ملف تصميم ونرفعه،
 * ورقم الإضافة لم يتغيّر — فيبقى المتصفح وطبقة الكاش على النسخة القديمة،
 * فيظهر HTML الجديد بتصميم قديم وكأن الإصلاح لم يُطبَّق.
 *
 * تاريخ التعديل يتغيّر مع كل رفع بالضرورة، فيستحيل تكرار العطل.
 */
function sch_asset(string $relative): string
{
    static $stamps = [];

    $relative = ltrim($relative, '/');

    if (!isset($stamps[$relative])) {
        $path = SCH_PATH . $relative;
        $stamps[$relative] = is_readable($path) ? (string) filemtime($path) : SCH_VERSION;
    }

    return add_query_arg('v', $stamps[$relative], SCH_URL . $relative);
}

/**
 * المبلغ رقمًا وعملةً في وسمين منفصلين.
 *
 * الرقم اللاتيني داخل نص عربي يخلط المتصفح اتجاهه فيقفز جزء منه للطرف الآخر.
 * وفصلهما مع `unicode-bidi: isolate` يمنع ذلك تمامًا.
 */
function sch_money_html(float|string|null $amount): string
{
    return '<span class="scha-num">' . esc_html(number_format((float) $amount, 2)) . '</span>'
        . ' <span class="scha-cur">' . esc_html__('ر.س', 'school-system') . '</span>';
}

/**
 * بنود «ما ينتظرك اليوم» — تُبنى مرة واحدة لكل طلب (memoized)، ويشترك فيها
 * مركز القيادة (نظرة عامة) وجرس الشريط العلوي. كل بند مربوط بصلاحيته
 * ووجهته، فلا يُعرض إلا لمن يملك التصرّف فيه.
 *
 * @return array<int,array{level:string,n:int,title:string,hint:string,url:string}>
 */
function sch_pending_items(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    if (!class_exists('SCH_Deputy') || !class_exists('SCH_Dashboard')) {
        return $cache = [];
    }

    $b = SCH_Deputy::today_board();

    $raw = [
        ['r', (int) ($b['vacant'] ?? 0),      __('حصص بلا معلم', 'school-system'),               __('اعتمد الاحتياط المقترح', 'school-system'), SCH_Dashboard::url('deputy'),                              'sch_supervise_stage'],
        ['r', (int) ($b['critical'] ?? 0),    __('إنذار حرج مفتوح', 'school-system'),            __('يحتاج تدخلًا فوريًا', 'school-system'),      SCH_Dashboard::url('alerts'),                              'sch_manage_alerts'],
        ['a', (int) ($b['clinic'] ?? 0),      __('إحالة صحية مفتوحة', 'school-system'),          __('صندوق الصحة المدرسية', 'school-system'),     add_query_arg('t', 'clinic', SCH_Dashboard::url('inbox')), 'sch_handle_notes'],
        ['a', (int) ($b['leaves'] ?? 0),      __('طلب إجازة بانتظار القرار', 'school-system'),   __('اعتمد أو ارفض بسبب', 'school-system'),       SCH_Dashboard::url('leaves'),                              'sch_decide_leaves'],
        ['t', (int) ($b['meds'] ?? 0),        __('إذن دواء بانتظار العيادة', 'school-system'),   __('راجعه واعتمده', 'school-system'),            SCH_Dashboard::url('meds'),                                'sch_manage_meds'],
        ['t', (int) ($b['stale_notes'] ?? 0), __('ملاحظة بانتظار الأخصائية', 'school-system'),   __('صندوق المتابعة', 'school-system'),           SCH_Dashboard::url('inbox'),                               'sch_handle_notes'],
    ];

    $out = [];
    foreach ($raw as $i) {
        if (current_user_can((string) $i[5])) {
            $out[] = [
                'level' => (string) $i[0],
                'n'     => (int) $i[1],
                'title' => (string) $i[2],
                'hint'  => (string) $i[3],
                'url'   => (string) $i[4],
            ];
        }
    }

    return $cache = $out;
}
