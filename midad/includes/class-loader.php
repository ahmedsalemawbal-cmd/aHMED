<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * محمّل الوحدات — **قائمةٌ صريحة لا مسحٌ لمجلّد**.
 *
 * المسح يجعل ترتيب التحميل رهين ترتيب نظام الملفّات، وصنفٌ يُحمَّل قبل من
 * يرثه أو يستعمل ثابتَه يسقط بخطأٍ لا يدلّ على سببه. والقائمة تقول الترتيب
 * وتُقرأ بالعين.
 */
final class MDD_Loader
{
    /** الترتيب مقصود: الدوالّ ثمّ البوّابة ثمّ من يبني عليها. */
    private const CORE = [
        'includes/functions.php',
        'includes/icons.php',
        'includes/class-db.php',
        'includes/class-tenant.php',
        'includes/class-member.php',
        'includes/class-access.php',
        'includes/class-activator.php',
    ];

    private const MODULES = [];

    private const FRONTEND = [];

    public static function boot(): void
    {
        self::load();

        load_plugin_textdomain('midad', false, dirname(plugin_basename(MDD_FILE)) . '/languages');

        MDD_Activator::maybe_upgrade();

        /*
         * أصحاب `mdd_member` ممنوعون من لوحة ووردبريس.
         *
         * لا لأن فيها سرًّا، بل لأنها **ليست منتجهم**: مشتركٌ يهبط على
         * لوحةٍ إنجليزية فيها «Posts» و«Plugins» يظنّ أنه أخطأ الموقع.
         */
        add_action('admin_init', [self::class, 'keep_out']);
        add_filter('show_admin_bar', [self::class, 'hide_bar']);
    }

    /** تُنادى من أدوات الفحص أيضًا — فتُحمَّل الأصناف بلا ووردبريس كامل. */
    public static function load(): void
    {
        foreach ([...self::CORE, ...self::MODULES, ...self::FRONTEND] as $file) {
            require_once MDD_PATH . $file;
        }
    }

    public static function keep_out(): void
    {
        if (wp_doing_ajax() || current_user_can('mdd_admin')) {
            return;
        }

        if (current_user_can('mdd_access')) {
            wp_safe_redirect(mdd_url());
            exit;
        }
    }

    public static function hide_bar(bool $show): bool
    {
        return current_user_can('mdd_admin') ? $show : false;
    }
}
