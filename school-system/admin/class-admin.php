<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * شاشات الداشبورد. لا استعلامات SQL هنا — فقط نداء طبقة البيانات وعرض.
 */
final class SCH_Admin
{
    public const SLUG = 'school-system';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_post_sch_save_student', [self::class, 'handle_save_student']);
    }

    public static function menu(): void
    {
        add_menu_page(
            __('نظام المدرسة', 'school-system'),
            __('نظام المدرسة', 'school-system'),
            'sch_view_students',
            self::SLUG,
            [self::class, 'render_students'],
            'dashicons-welcome-learn-more',
            3
        );

        add_submenu_page(
            self::SLUG,
            __('الطلاب', 'school-system'),
            __('الطلاب', 'school-system'),
            'sch_view_students',
            self::SLUG,
            [self::class, 'render_students']
        );
    }

    public static function assets(string $hook): void
    {
        if (!str_contains($hook, self::SLUG)) {
            return;
        }

        wp_enqueue_style(
            'sch-admin',
            SCH_URL . 'assets/admin.css',
            [],
            SCH_VERSION
        );

        // الخط مستضاف محليًا (assets/fonts.css) — عائلة واحدة للنظام كله،
        // ولا نداء لخادم خطوط خارجي. الوسم بـSCH_VERSION كبقية الأصول.
        wp_enqueue_style(
            'sch-fonts',
            SCH_URL . 'assets/fonts.css',
            [],
            SCH_VERSION
        );
    }

    public static function render_students(): void
    {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $page   = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        $result = SCH_Students::list([
            'search'   => $search,
            'status'   => 'active',
            'page'     => $page,
            'per_page' => 20,
        ]);

        require SCH_PATH . 'admin/views/students.php';
    }

    public static function handle_save_student(): void
    {
        if (!current_user_can('sch_manage_students')) {
            wp_die(esc_html__('لا تملك صلاحية إضافة طالب.', 'school-system'), '', ['response' => 403]);
        }

        check_admin_referer('sch_save_student');

        $created = SCH_Students::create([
            'full_name'   => wp_unslash($_POST['full_name'] ?? ''),
            'national_id' => wp_unslash($_POST['national_id'] ?? ''),
            'stage'       => wp_unslash($_POST['stage'] ?? ''),
            'grade_level' => wp_unslash($_POST['grade_level'] ?? ''),
            'section'     => wp_unslash($_POST['section'] ?? ''),
        ]);

        $redirect = add_query_arg(
            is_wp_error($created)
                ? ['page' => self::SLUG, 'sch_error' => $created->get_error_code()]
                : ['page' => self::SLUG, 'sch_saved' => 1],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }
}
