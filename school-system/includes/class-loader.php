<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * نقطة تحميل واحدة. كل وحدة جديدة تُضاف هنا ولا شيء غير ذلك.
 */
final class SCH_Loader
{
    /**
     * تحميل ملفات الوحدات.
     * يُستدعى من init() ومن المفعّل — لأن التفعيل والترقية يحتاجان الأصناف
     * قبل أن تُسجَّل الخطافات. require_once يجعل النداء المتكرر آمنًا.
     */
    public static function load_modules(): void
    {
        // طبقة البيانات
        require_once SCH_PATH . 'modules/students/class-students.php';
        require_once SCH_PATH . 'modules/staff/class-staff.php';
        require_once SCH_PATH . 'modules/academic/class-academic.php';
        require_once SCH_PATH . 'modules/guardians/class-guardians.php';
        require_once SCH_PATH . 'modules/import/class-import.php';
        require_once SCH_PATH . 'modules/attendance/class-attendance.php';
        require_once SCH_PATH . 'modules/attendance/class-staff-attendance.php';
        require_once SCH_PATH . 'modules/academic/class-assessment.php';
        require_once SCH_PATH . 'modules/transport/class-transport.php';
        require_once SCH_PATH . 'modules/finance/class-finance.php';
        require_once SCH_PATH . 'modules/accounting/class-accounting.php';
        require_once SCH_PATH . 'modules/hr/class-hr.php';
        require_once SCH_PATH . 'modules/services/class-services.php';
        require_once SCH_PATH . 'modules/kg/class-kg.php';
        require_once SCH_PATH . 'modules/enrollment/class-enrollment.php';
        require_once SCH_PATH . 'modules/custody/class-custody.php';
        require_once SCH_PATH . 'modules/custody/class-alerts.php';
        require_once SCH_PATH . 'modules/custody/class-pass.php';
        require_once SCH_PATH . 'modules/notes/class-notes.php';
        require_once SCH_PATH . 'modules/deputy/class-deputy.php';
        require_once SCH_PATH . 'modules/nerve/class-nerve.php';
        require_once SCH_PATH . 'modules/clinic/class-medication.php';
        require_once SCH_PATH . 'modules/org/class-org.php';
        require_once SCH_PATH . 'modules/audit/class-audit.php';
        require_once SCH_PATH . 'modules/staff/class-perms.php';
        require_once SCH_PATH . 'modules/staff/class-bulk.php';
        require_once SCH_PATH . 'modules/staff/class-flow.php';
        require_once SCH_PATH . 'modules/academic/class-certificates.php';
        require_once SCH_PATH . 'modules/academic/class-rollover.php';
        require_once SCH_PATH . 'modules/learning/class-content.php';
        require_once SCH_PATH . 'modules/learning/class-homework.php';
        require_once SCH_PATH . 'modules/comms/class-push.php';
        require_once SCH_PATH . 'includes/class-files.php';
        require_once SCH_PATH . 'includes/class-brand.php';

        // الـAPI والداشبورد
        require_once SCH_PATH . 'api/class-auth.php';
        require_once SCH_PATH . 'api/class-rest.php';
        require_once SCH_PATH . 'frontend/class-dashboard.php';
        require_once SCH_PATH . 'frontend/class-app.php';
        require_once SCH_PATH . 'frontend/class-portal.php';
        require_once SCH_PATH . 'frontend/class-apps.php';
        require_once SCH_PATH . 'frontend/class-modal.php';
        require_once SCH_PATH . 'frontend/class-table.php';
        require_once SCH_PATH . 'api/class-bridge.php';
        require_once SCH_PATH . 'frontend/class-driver.php';
        require_once SCH_PATH . 'frontend/class-gate.php';
        require_once SCH_PATH . 'frontend/class-teacher.php';
        require_once SCH_PATH . 'frontend/class-student.php';
    }

    public static function init(): void
    {
        self::load_modules();

        add_action('rest_api_init', ['SCH_Auth', 'register_routes']);
        add_action('rest_api_init', ['SCH_Rest', 'register_routes']);
        add_filter('determine_current_user', ['SCH_Auth', 'authenticate_bearer'], 20);

        // الداشبورد المستقل على /dashboard
        SCH_Dashboard::init();
        SCH_App::init();
        SCH_Portal::init();
        SCH_Apps::init();
        SCH_Bridge::init();
        SCH_Driver::init();
        SCH_Gate::init();
        SCH_Teacher::init();
        SCH_Student::init();
        SCH_Files::init();
        SCH_Alerts::init();

        // شاشات ووردبريس (للمدير فقط)
        if (is_admin()) {
            require_once SCH_PATH . 'admin/class-admin.php';
            SCH_Admin::init();
        }
    }
}
