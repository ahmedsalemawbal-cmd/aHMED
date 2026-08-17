<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * هوية المدرسة المرئية — الشعار وأيقونة التبويب.
 *
 * **ولماذا هذه عامة وملفات الطالب محمية؟** لأن القاعدة تحمي ما يجب ألّا يُرى:
 * وثيقة الطالب وصورته وسجلّه الصحي. والشعار **يُراد أن يُرى** — يظهر في صفحة
 * الدخول قبل أي جلسة، وعلى الشهادات المطبوعة، وأيقونة التبويب يطلبها المتصفح
 * أحيانًا بلا كوكيز أصلًا. فحبسها خلف فحص صلاحية يكسرها ولا يحمي شيئًا.
 *
 * والقيود هنا على **النوع** لا على الوصول:
 * - النوع يُفحص من محتوى الملف لا من امتداده.
 * - **SVG مرفوض صراحةً** — يحمل جافاسكربت، ورفعه إلى مجلد عام ثغرة XSS مباشرة.
 * - الاسم عشوائي، والقديم يُحذف عند الاستبدال فلا يتراكم.
 */
final class SCH_Brand
{
    /** أقصى حجم — الشعار صورة واجهة لا صورة عالية الدقة. */
    private const MAX = 2097152; // 2MB

    /** الأنواع المقبولة => الامتداد. لا SVG. */
    private const ALLOWED = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/x-icon'    => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    private const SLOTS = ['logo', 'favicon'];

    public static function dir(): string
    {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit($uploads['basedir']) . 'sch-brand';

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return $dir;
    }

    public static function base_url(): string
    {
        $uploads = wp_upload_dir();

        return trailingslashit($uploads['baseurl']) . 'sch-brand';
    }

    /** رابط الشعار — أو '' إن لم يُرفع. */
    public static function logo(): string
    {
        return self::url_of('logo');
    }

    /** رابط أيقونة التبويب — أو '' إن لم تُرفع. */
    public static function favicon(): string
    {
        return self::url_of('favicon');
    }

    private static function url_of(string $slot): string
    {
        $file = (string) sch_settings('brand_' . $slot, '');

        if ($file === '') {
            return '';
        }

        // الملف قد يُحذف من القرص خارج النظام — لا نُخرج رابطًا مكسورًا
        if (!is_readable(self::dir() . '/' . $file)) {
            return '';
        }

        return self::base_url() . '/' . $file;
    }

    /**
     * حفظ ملف في خانة الشعار أو الأيقونة.
     *
     * @param array<string,mixed> $file عنصر من $_FILES
     */
    public static function save(string $slot, array $file): true|WP_Error
    {
        if (!in_array($slot, self::SLOTS, true)) {
            return sch_api_error('bad_slot', __('خانة غير معروفة.', 'school-system'), 422);
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return sch_api_error('no_file', __('لم يُرفع ملف.', 'school-system'), 422);
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX) {
            return sch_api_error('bad_size', __('حجم الصورة يتجاوز ٢ ميجابايت.', 'school-system'), 422);
        }

        // النوع من محتوى الملف نفسه — الامتداد يمكن تزويره.
        $check = wp_check_filetype_and_ext((string) $file['tmp_name'], (string) ($file['name'] ?? ''));
        $mime  = (string) ($check['type'] ?: '');

        if (!isset(self::ALLOWED[$mime])) {
            return sch_api_error('bad_type', __('الصور المقبولة: PNG أو JPG أو WEBP (وICO للأيقونة).', 'school-system'), 422);
        }

        // فحص ثانٍ: أبعاد حقيقية — ملف نصّي بامتداد صورة لا يجتازه
        $dims = @getimagesize((string) $file['tmp_name']);

        if ($mime !== 'image/x-icon' && $mime !== 'image/vnd.microsoft.icon'
            && (!is_array($dims) || (int) ($dims[0] ?? 0) < 1)) {
            return sch_api_error('bad_image', __('الملف ليس صورة صالحة.', 'school-system'), 422);
        }

        $name = $slot . '-' . wp_generate_password(10, false) . '.' . self::ALLOWED[$mime];
        $path = self::dir() . '/' . $name;

        if (!move_uploaded_file((string) $file['tmp_name'], $path)) {
            return sch_api_error('move_failed', __('تعذّر حفظ الصورة.', 'school-system'), 500);
        }

        @chmod($path, 0644);

        self::forget_file($slot);
        self::put('brand_' . $slot, $name);

        sch_audit('brand.updated', 'settings', null, ['slot' => $slot]);

        return true;
    }

    /** إزالة الشعار أو الأيقونة والعودة للافتراضي. */
    public static function remove(string $slot): true|WP_Error
    {
        if (!in_array($slot, self::SLOTS, true)) {
            return sch_api_error('bad_slot', __('خانة غير معروفة.', 'school-system'), 422);
        }

        self::forget_file($slot);
        self::put('brand_' . $slot, '');

        sch_audit('brand.removed', 'settings', null, ['slot' => $slot]);

        return true;
    }

    /** كتابة مفتاح واحد في خيار الإعدادات بلا مسّ بقيّته. */
    private static function put(string $key, string $value): void
    {
        $s = sch_settings();

        if (!is_array($s)) {
            $s = [];
        }

        $s[$key] = $value;
        update_option('sch_settings', $s);
    }

    /** حذف الملف القديم من القرص — الاستبدال لا يترك أثرًا يتراكم. */
    private static function forget_file(string $slot): void
    {
        $old = (string) sch_settings('brand_' . $slot, '');

        if ($old === '') {
            return;
        }

        $path = self::dir() . '/' . basename($old);

        // لا نخرج من مجلد الهوية مهما كانت القيمة المخزَّنة
        $real = realpath($path);
        $base = realpath(self::dir());

        if ($real && $base && str_starts_with($real, $base) && is_file($real)) {
            @unlink($real);
        }
    }
}
