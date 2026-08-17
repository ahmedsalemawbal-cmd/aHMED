<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * داشبورد مستقل على /dashboard — خارج واجهة ووردبريس تمامًا.
 * مسارات: /dashboard, /dashboard/<section>, /dashboard/<section>/<id>
 */
final class SCH_Dashboard
{
    public const BASE = 'dashboard';

    /** المجالات الأربعة — بطاقات الشاشة الأولى. */
    private const AREAS = [
        'school'    => ['إدارة المدرسة', 'الطلاب وأولياء الأمور والشعب والحضور والمواد والدرجات ورياض الأطفال', 'icon' => 'users'],
        'transport' => ['النقل المدرسي', 'الباصات والمسارات ونقاط التوقف والاشتراكات ورحلات اليوم', 'icon' => 'bus'],
        'erp'       => ['ERP', 'الرسوم والمحاسبة والموارد البشرية والرواتب والأصول والمستودع', 'icon' => 'wallet'],
        'system'    => ['النظام', 'الموظفون والزوار والسلامة والإعدادات وسجل النظام', 'icon' => 'cog'],
    ];

    /** slug => [العنوان، الصلاحية، المجال، المجموعة، الأيقونة] */
    private const SECTIONS = [
        'overview'   => ['نظرة عامة',       'sch_view_students',     'school', '',            'home'],
        'custody'    => ['لوحة العهدة',     'sch_view_custody',      'school', '',            'shield'],
        'alerts'     => ['الإنذارات',       'sch_manage_alerts',     'school', '',            'bell'],
        // السجلات — الأصل قبل المشتق: يُسجَّل، فيُربط بوليّه، فيُوضع في شعبة،
        // فتُطبع بطاقته، فيُكرَّم. والترتيب هنا هو ترتيب العرض.
        'students'   => ['الطلاب',          'sch_view_students',     'school', 'السجلات',     'users'],
        'guardians'  => ['أولياء الأمور',   'sch_manage_guardians',  'school', 'السجلات',     'user'],
        'classes'    => ['الصفوف والشعب',   'sch_manage_classes',    'school', 'السجلات',     'grid'],
        'badges'     => ['بطاقات الطلاب',   'sch_manage_students',   'school', 'السجلات',     'badge'],
        'certificates' => ['الشهادات',      'sch_manage_students',   'school', 'السجلات',     'award'],

        // اليوم الدراسي — ما يجري اليوم، بترتيب حدوثه
        'attendance' => ['الحضور',          'sch_manage_attendance', 'school', 'اليوم الدراسي','check'],
        'attendance-report' => ['تقرير الحضور الشهري', 'sch_manage_attendance', 'school', 'اليوم الدراسي','chart'],
        'timetable'  => ['بناء الجدول',     'sch_build_timetable',   'school', 'اليوم الدراسي','calendar'],
        'subjects'   => ['المواد والجداول', 'sch_manage_subjects',   'school', 'اليوم الدراسي','book'],
        'homework'   => ['الواجبات',        'sch_manage_homework',   'school', 'اليوم الدراسي','pen'],
        'exams'      => ['الاختبارات',      'sch_manage_exams',      'school', 'اليوم الدراسي','clipboard'],
        'content'    => ['المحتوى التعليمي','sch_manage_content',    'school', 'اليوم الدراسي','play'],
        'leaves'     => ['إجازات الطلاب',   'sch_decide_leaves',     'school', 'اليوم الدراسي','leaf'],
        'kg'         => ['رياض الأطفال',    'sch_manage_kg',         'school', 'اليوم الدراسي','sun'],
        // حضور المعلمين شأن موظفين لا سجلات طلاب — نُقل هنا
        'staff-day'  => ['حضور المعلمين',   'sch_supervise_stage',   'school', 'اليوم الدراسي','user-check'],
        'staff-report' => ['تقرير حضور الموظفين', 'sch_supervise_stage', 'school', 'اليوم الدراسي','chart'],

        // الخدمات — ما يُقدَّم للطالب
        'clinic'     => ['الصحة المدرسية',  'sch_manage_services',   'school', 'الخدمات',     'heart'],
        'meds'       => ['أدوية الطلاب',    'sch_manage_meds',       'school', 'الخدمات',     'pill'],
        'inbox'      => ['صندوق المتابعة',  'sch_handle_notes',      'school', 'الخدمات',     'user'],
        'messages'   => ['الرسائل',         'sch_send_messages',     'school', 'الخدمات',     'mail'],
        'library'    => ['المكتبة',         'sch_manage_services',   'school', 'الخدمات',     'book'],

        'nerve'      => ['المتابعة العميقة','sch_view_insights',     'school', '',            'activity'],
        'deputy'     => ['يومي',            'sch_supervise_stage',   'school', '',            'flag'],
        'perms'      => ['الصلاحيات',       'sch_manage_staff',      'system', '',           'lock'],

        'transport'  => ['المسارات والباصات','sch_manage_transport',  'transport', '',        'bus'],

        'finance'    => ['الرسوم والفواتير', 'sch_manage_finance',    'erp', 'المالية',       'invoice'],
        'accounts'   => ['دليل الحسابات',   'sch_manage_accounting', 'erp', 'المحاسبة',      'list'],
        'journal'    => ['القيود اليومية',  'sch_manage_accounting', 'erp', 'المحاسبة',      'book'],
        'ledger'     => ['دفتر الأستاذ',    'sch_manage_accounting', 'erp', 'المحاسبة',      'ledger'],
        'reports'    => ['التقارير المالية','sch_manage_accounting', 'erp', 'المحاسبة',      'chart'],
        'hr'         => ['العقود والإجازات','sch_manage_hr',         'erp', 'الموارد البشرية','badge'],
        'payroll'    => ['الرواتب',         'sch_manage_hr',         'erp', 'الموارد البشرية','wallet'],
        'assets'     => ['الأصول والصيانة', 'sch_manage_assets',     'erp', 'العمليات',      'tool'],
        'inventory'  => ['المستودع',        'sch_manage_assets',     'erp', 'العمليات',      'box'],

        'employees'  => ['الموظفون',        'sch_manage_staff',      'system', '',           'users'],
        'staff-badges' => ['بطاقات الموظفين', 'sch_manage_staff',     'system', '',           'badge'],
        // الأمن والزوّار عمليّات يوميّة — نُقلا من «النظام» لمجموعة ظاهرة في التنقّل.
        'visitors'   => ['الزوار',          'sch_manage_services',   'school', 'الأمن',       'door'],
        'safety'     => ['الأمن والسلامة',  'sch_manage_services',   'school', 'الأمن',       'shield'],
        'import'     => ['استيراد البيانات','sch_manage_students',   'system', '',           'upload'],
        // الإعدادات وأخواتها سياق مستقلّ يستولي على الشريط الجانبي (SETTINGS_NAV).
        'settings'   => ['بيانات المدرسة',  'sch_manage_settings',   'system', '',           'cog'],
        'settings-years' => ['السنوات الدراسية', 'sch_manage_settings', 'system', '',        'calendar'],
        'settings-day'   => ['مواعيد الحضور والتنبيهات', 'sch_manage_settings', 'system', '', 'clock'],
        'rollover'   => ['ترقية العام',     'sch_manage_settings',   'system', '',           'calendar'],
        'audit'      => ['سجل النظام',      'sch_view_audit',        'system', '',           'clock'],
    ];

    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public static function init(): void
    {
        add_action('init', [self::class, 'add_rewrite']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'route'], 0);

        add_action('admin_init', [self::class, 'block_wp_admin']);
        add_filter('show_admin_bar', [self::class, 'hide_admin_bar']);
        add_filter('login_redirect', [self::class, 'after_login_redirect'], 10, 3);
    }

    public static function add_rewrite(): void
    {
        $b = self::BASE;
        add_rewrite_rule("^{$b}/?$", 'index.php?sch_dash=1&sch_section=', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/?$", 'index.php?sch_dash=1&sch_section=$matches[1]', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/(\d+)/?$", 'index.php?sch_dash=1&sch_section=$matches[1]&sch_id=$matches[2]', 'top');

        if (get_option('sch_rewrite_version') !== SCH_VERSION) {
            flush_rewrite_rules(false);
            update_option('sch_rewrite_version', SCH_VERSION, false);
        }
    }

    public static function query_vars(array $vars): array
    {
        return [...$vars, 'sch_dash', 'sch_section', 'sch_id'];
    }

    public static function url(string $section = '', int $id = 0): string
    {
        $path = '/' . self::BASE;
        if ($section !== '') {
            $path .= '/' . $section;
        }
        if ($id > 0) {
            $path .= '/' . $id;
        }
        return home_url($path . '/');
    }

    public static function sections(): array
    {
        return self::SECTIONS;
    }

    public static function areas(): array
    {
        return self::AREAS;
    }

    /**
     * البلاطات العلوية — عشر مجموعات بدل عمود جانبي بخمسة وأربعين قسمًا.
     *
     * والاسم القصير لا المسار الكامل: بلاطة تقول «السجلات» وتحتها «إدارة المدرسة»
     * بخط صغير. لو كتبنا المسار كاملًا لقُرئت «إدارة المدرسة» أربع مرات في صف واحد.
     */
    private const GROUP_META = [
        'school|'              => ['الرئيسية',        'home'],
        'school|السجلات'       => ['السجلات',        'badge'],
        'school|اليوم الدراسي' => ['اليوم الدراسي',  'calendar'],
        'school|الخدمات'       => ['الخدمات',        'heart'],
        'school|الأمن'         => ['الأمن والزوّار', 'shield'],
        'transport|'           => ['النقل',          'bus'],
        'erp|المالية'          => ['المالية',        'wallet'],
        'erp|المحاسبة'         => ['المحاسبة',       'chart'],
        'erp|الموارد البشرية'  => ['الموارد البشرية', 'users'],
        'erp|العمليات'         => ['العمليات',       'grid'],
        'system|'              => ['النظام',         'cog'],
    ];

    /**
     * سياق الإعدادات — يستولي على الشريط الجانبي كأنظمة SaaS الكبرى:
     * زر رجوع، عنوان «الإعدادات»، ومجموعات بعناوين صغيرة. أقسامه تُستبعَد من
     * التنقّل الرئيسي وتظهر هنا وحدها، فيبقى التنقّل الرئيسي نظيفًا للعمل اليومي.
     */
    private const SETTINGS_NAV = [
        'المدرسة'           => ['settings', 'settings-years', 'rollover'],
        'اليوم الدراسي'     => ['settings-day'],
        'الفريق والصلاحيات' => ['employees', 'staff-badges', 'perms'],
        'البيانات والنظام'  => ['import', 'audit'],
    ];

    /** هل هذا القسم ضمن سياق الإعدادات؟ */
    public static function is_settings(string $section): bool
    {
        foreach (self::SETTINGS_NAV as $slugs) {
            if (in_array($section, $slugs, true)) {
                return true;
            }
        }
        return false;
    }

    /** تنقّل الإعدادات مجمّعًا — مُصفّى بصلاحيات المستخدم كالتنقّل الرئيسي. */
    public static function settings_nav(): array
    {
        $out = [];
        foreach (self::SETTINGS_NAV as $label => $slugs) {
            $items = [];
            foreach ($slugs as $slug) {
                $meta = self::SECTIONS[$slug] ?? null;
                if ($meta === null) {
                    continue;
                }
                if (!current_user_can((string) $meta[1]) || !SCH_Perms::may($slug, 'view')) {
                    continue;
                }
                $items[$slug] = $meta;
            }
            if ($items !== []) {
                $out[$label] = $items;
            }
        }

        return $out;
    }

    /** أول قسم إعدادات متاح — مقصد زرّ «الإعدادات» المثبّت في التنقّل الرئيسي. */
    public static function settings_home(): string
    {
        foreach (self::settings_nav() as $items) {
            return (string) array_key_first($items);
        }

        return '';
    }

    /** المجموعات التي يملك المستخدم قسمًا واحدًا فيها على الأقل. */
    public static function groups(): array
    {
        $out = [];

        foreach (self::GROUP_META as $key => [$label, $icon]) {
            [$area, $group] = array_pad(explode('|', $key), 2, '');
            $sections = [];

            foreach (self::SECTIONS as $slug => $meta) {
                if ((string) $meta[2] !== $area || (string) ($meta[3] ?? '') !== $group) {
                    continue;
                }
                // أقسام الإعدادات لها سياقها الجانبي المستقل — تُستبعَد من التنقّل الرئيسي.
                if (self::is_settings($slug)) {
                    continue;
                }
                if (!current_user_can((string) $meta[1]) || !SCH_Perms::may($slug, 'view')) {
                    continue;
                }

                $sections[$slug] = $meta;
            }

            if ($sections === []) {
                continue;
            }

            $out[$key] = [
                'label'    => $label,
                'icon'     => $icon,
                'area'     => (string) (self::AREAS[$area][0] ?? ''),
                'sections' => $sections,
            ];
        }

        return $out;
    }

    /** مفتاح مجموعة قسم معيّن — لإبراز البلاطة النشطة. */
    public static function group_of(string $section): string
    {
        $meta = self::SECTIONS[$section] ?? null;

        return $meta ? ((string) $meta[2] . '|' . (string) ($meta[3] ?? '')) : '';
    }

    /** المجال الذي ينتمي له قسم. */
    public static function icon_of(string $section): string
    {
        return self::SECTIONS[$section][4] ?? 'dot';
    }

    /** أقسام مجال مجمّعة حسب المجموعة. */
    public static function grouped_sections(string $area): array
    {
        $groups = [];
        foreach (self::sections_in($area) as $slug => $meta) {
            $groups[$meta[3] ?? ''][$slug] = $meta;
        }
        return $groups;
    }

    public static function area_of(string $section): string
    {
        return self::SECTIONS[$section][2] ?? 'school';
    }

    /** أقسام مجال معيّن التي يملك المستخدم صلاحيتها. */
    public static function sections_in(string $area): array
    {
        $out = [];
        foreach (self::SECTIONS as $slug => $meta) {
            // فحص الدور أولًا، ثم الصلاحيات المخصصة إن وُجدت لهذا الموظف.
            if ($meta[2] === $area && current_user_can($meta[1]) && SCH_Perms::may($slug, 'view')) {
                $out[$slug] = $meta;
            }
        }
        return $out;
    }

    /** المجالات التي يستطيع المستخدم دخولها. */
    public static function allowed_areas(): array
    {
        $out = [];
        foreach (self::AREAS as $key => $meta) {
            $sections = self::sections_in($key);
            if ($sections !== []) {
                $out[$key] = $meta + ['landing' => array_key_first($sections), 'count' => count($sections)];
            }
        }
        return $out;
    }

    /** action => [الصلاحية، اسم المعالج] */
    private static function actions(): array
    {
        return [
            'add_student'      => ['sch_manage_students',  'do_add_student'],
            'register_student' => ['sch_manage_students',  'do_register_student'],
            'upload_doc'       => ['sch_manage_docs',      'do_upload_doc'],
            'delete_doc'       => ['sch_manage_docs',      'do_delete_doc'],
            'save_health'      => ['sch_view_health',      'do_save_health'],
            'bulk'             => ['read',                 'do_bulk'],
            'flow_mark'        => ['read',                 'do_flow_mark'],
            'print_badges'     => ['sch_manage_students',  'do_print_badges'],
            'print_staff_badges' => ['sch_manage_staff',   'do_print_staff_badges'],
            'toggle_watch'     => ['sch_view_students',    'do_toggle_watch'],
            'issue_cert'       => ['sch_manage_students',  'do_issue_cert'],
            'issue_certs_bulk' => ['sch_manage_students',  'do_issue_certs_bulk'],
            'revoke_cert'      => ['sch_manage_students',  'do_revoke_cert'],
            'issue_key'        => ['sch_manage_students',  'do_issue_key'],
            'revoke_key'       => ['sch_manage_students',  'do_revoke_key'],
            'update_employee'  => ['sch_manage_staff',     'do_update_employee'],
            'restore_employee' => ['sch_manage_staff',     'do_restore_employee'],
            'student_login'    => ['sch_manage_students',  'do_student_login'],
            'save_perms'       => ['sch_manage_staff',     'do_save_perms'],
            'copy_perms'       => ['sch_manage_staff',     'do_copy_perms'],
            'reset_perms'      => ['sch_manage_staff',     'do_reset_perms'],
            'give_dose'        => ['sch_manage_meds',      'do_give_dose'],
            'approve_med'      => ['sch_manage_meds',      'do_approve_med'],
            'reject_med'       => ['sch_manage_meds',      'do_reject_med'],
            'decide_leave_s'   => ['sch_decide_leaves',    'do_decide_student_leave'],
            'ack_alert'        => ['sch_manage_alerts',    'do_ack_alert'],
            'close_alert'      => ['sch_manage_alerts',    'do_close_alert'],
            'custody_manual'   => ['sch_view_custody',     'do_custody_manual'],
            'add_note'         => ['sch_write_notes',      'do_add_note'],
            'add_content'      => ['sch_manage_content',   'do_add_content'],
            'content_status'   => ['sch_manage_content',   'do_content_status'],
            'content_renew'    => ['sch_manage_content',   'do_content_renew'],
            'add_homework'     => ['sch_manage_homework',  'do_add_homework'],
            'grade_homework'   => ['sch_manage_homework',  'do_grade_homework'],
            'take_note'        => ['sch_handle_notes',     'do_take_note'],
            'close_note'       => ['sch_handle_notes',     'do_close_note'],
            'contact_parent'   => ['sch_handle_notes',     'do_contact_parent'],
            'mark_staff'       => ['sch_supervise_stage',  'do_mark_staff'],
            'mark_staff_bulk'  => ['sch_supervise_stage',  'do_mark_staff_bulk'],
            'assign_sub'       => ['sch_supervise_stage',  'do_assign_sub'],
            'submit_timetable' => ['sch_build_timetable',  'do_submit_timetable'],
            'publish_timetable'=> ['sch_approve_timetable','do_publish_timetable'],
            'reopen_timetable' => ['sch_build_timetable',  'do_reopen_timetable'],
            'save_summary'     => ['sch_handle_notes',     'do_save_summary'],
            'approve_summary'  => ['sch_manage_deputy',    'do_approve_summary'],
            'update_student'   => ['sch_manage_students',  'do_update_student'],
            'enroll_student'   => ['sch_manage_students',  'do_enroll_student'],
            'add_guardian'     => ['sch_manage_guardians', 'do_add_guardian'],
            'link_guardian'    => ['sch_manage_guardians', 'do_link_guardian'],
            'unlink_guardian'  => ['sch_manage_guardians', 'do_unlink_guardian'],
            'add_child'        => ['sch_manage_guardians', 'do_add_child'],
            'update_guardian'  => ['sch_manage_guardians', 'do_update_guardian'],
            'add_employee'     => ['sch_manage_staff',     'do_add_employee'],
            'suspend_employee' => ['sch_manage_staff',     'do_suspend_employee'],
            'add_class'        => ['sch_manage_classes',   'do_add_class'],
            'delete_class'     => ['sch_manage_classes',   'do_delete_class'],
            'add_year'         => ['sch_manage_settings',  'do_add_year'],
            'set_year'         => ['sch_manage_settings',  'do_set_year'],
            'save_settings'    => ['sch_manage_settings',  'do_save_settings'],
            'save_timing'      => ['sch_manage_settings',  'do_save_timing'],
            'save_alerts'      => ['sch_manage_settings',  'do_save_alerts'],
            'import_students'  => ['sch_manage_students',  'do_import'],

            'mark_attendance'  => ['sch_manage_attendance', 'do_mark_attendance'],
            'add_bus'          => ['sch_manage_transport',  'do_add_bus'],
            'add_route'        => ['sch_manage_transport',  'do_add_route'],
            'add_stop'         => ['sch_manage_transport',  'do_add_stop'],
            'delete_stop'      => ['sch_manage_transport',  'do_delete_stop'],
            'subscribe'        => ['sch_manage_transport',  'do_subscribe'],
            'unsubscribe'      => ['sch_manage_transport',  'do_unsubscribe'],
            'open_trip'        => ['sch_manage_transport',  'do_open_trip'],
            'close_trip'       => ['sch_manage_transport',  'do_close_trip'],
            'add_subject'      => ['sch_manage_subjects',   'do_add_subject'],
            'assign_subject'   => ['sch_manage_subjects',   'do_assign_subject'],
            'set_slot'         => ['sch_manage_subjects',   'do_set_slot'],
            'clear_slot'       => ['sch_manage_subjects',   'do_clear_slot'],
            'add_exam'         => ['sch_manage_exams',      'do_add_exam'],
            'save_scores'      => ['sch_manage_exams',      'do_save_scores'],
            'add_fee_plan'     => ['sch_manage_finance',    'do_add_fee_plan'],
            'issue_invoice'    => ['sch_manage_finance',    'do_issue_invoice'],
            'record_payment'   => ['sch_manage_finance',    'do_record_payment'],
            'void_invoice'     => ['sch_manage_finance',    'do_void_invoice'],
            'refund_payment'   => ['sch_manage_finance',    'do_refund_payment'],
            'run_rollover'     => ['sch_manage_settings',   'do_run_rollover'],
            'send_message'     => ['sch_send_messages',     'do_send_message'],

            'add_account'      => ['sch_manage_accounting', 'do_add_account'],
            'add_journal'      => ['sch_manage_accounting', 'do_add_journal'],
            'post_journal'     => ['sch_manage_accounting', 'do_post_journal'],
            'reverse_journal'  => ['sch_manage_accounting', 'do_reverse_journal'],

            'add_contract'     => ['sch_manage_hr',       'do_add_contract'],
            'request_leave'    => ['sch_manage_hr',       'do_request_leave'],
            'decide_leave'     => ['sch_manage_hr',       'do_decide_leave'],
            'run_payroll'      => ['sch_manage_hr',       'do_run_payroll'],
            'post_payroll'     => ['sch_manage_hr',       'do_post_payroll'],
            'add_clinic'       => ['sch_manage_services', 'do_add_clinic'],
            'add_book'         => ['sch_manage_services', 'do_add_book'],
            'loan_book'        => ['sch_manage_services', 'do_loan_book'],
            'return_book'      => ['sch_manage_services', 'do_return_book'],
            'check_in'         => ['sch_manage_services', 'do_check_in'],
            'check_out'        => ['sch_manage_services', 'do_check_out'],
            'add_incident'     => ['sch_manage_services', 'do_add_incident'],
            'close_incident'   => ['sch_manage_services', 'do_close_incident'],
            'add_asset'        => ['sch_manage_assets',   'do_add_asset'],
            'add_work'         => ['sch_manage_assets',   'do_add_work'],
            'set_work'         => ['sch_manage_assets',   'do_set_work'],
            'add_item'         => ['sch_manage_assets',   'do_add_item'],
            'move_stock'       => ['sch_manage_assets',   'do_move_stock'],
            'save_kg'          => ['sch_manage_kg',       'do_save_kg'],
        ];
    }

    // ---------- التوجيه ----------

    public static function route(): void
    {
        if (!get_query_var('sch_dash')) {
            return;
        }

        self::no_cache_headers();

        $section = sanitize_key((string) get_query_var('sch_section'));
        $id      = absint(get_query_var('sch_id'));

        if ($section === 'logout') {
            self::handle_logout();
        }

        if (!is_user_logged_in()) {
            self::render_login();
        }

        if ($section === 'login') {
            wp_safe_redirect(self::url());
            exit;
        }

        if ($section === '') {
            $areas = self::allowed_areas();

            if ($areas === []) {
                // من لا داشبورد له يُنقل لتطبيقه لا يُرمى على 403.
                // هذا الفرع لا يُبلَغ إلا حين `$areas === []`، فمن له مجال واحد
                // في الداشبورد لا يمرّ هنا أصلًا — ومشرف المرحلة والوكيل منهم.
                // والترتيب مقصود: الأخص أولًا، فالمعلم قد يكون وليّ أمر أيضًا.
                foreach ([
                    ['sch_scan_gate',        [SCH_Gate::class,    'url']],
                    ['sch_drive_trip',       [SCH_Driver::class,  'url']],
                    ['sch_manage_attendance', [SCH_Teacher::class, 'url']],
                    ['sch_student_app',       [SCH_Student::class, 'url']],
                    ['sch_view_own_children', [SCH_App::class,    'url']],
                ] as [$sch_cap, $sch_dest]) {
                    if (current_user_can($sch_cap) && is_callable($sch_dest)) {
                        wp_safe_redirect(call_user_func($sch_dest));
                        exit;
                    }
                }

                status_header(403);
                self::render('403', ['section' => '']);
            }

            // مستخدم له مجال واحد فقط يدخله مباشرة — شاشة اختيار بخيار واحد عائق لا تنظيم.
            if (count($areas) === 1) {
                $only = reset($areas);
                wp_safe_redirect(self::url((string) $only['landing']));
                exit;
            }

            self::render('hub', ['section' => '', 'areas' => $areas]);
        }

        if (!array_key_exists($section, self::SECTIONS)) {
            status_header(404);
            self::render('404', ['section' => $section]);
        }

        if (!current_user_can(self::SECTIONS[$section][1]) || !SCH_Perms::may($section, 'view')) {
            status_header(403);
            self::render('403', ['section' => $section]);
        }

        if ($section === 'import' && isset($_GET['sch_template'])) {
            self::send_template();
        }

        if ($section === 'students' && isset($_GET['sch_file'])) {
            self::send_private_file(absint($_GET['sch_file']), $id);
        }

        if ($section === 'students' && isset($_GET['sch_photo']) && $id > 0) {
            self::send_photo($id);
        }

        // وثيقة A4 مستقلة — بلا شريط جانبي ولا قالب الداشبورد.
        if ($section === 'students' && isset($_GET['sch_doc']) && $id > 0) {
            self::no_cache_headers();
            $sch_data = ['id' => $id];
            require SCH_PATH . 'frontend/views/student-print.php';
            exit;
        }

        if (isset($_GET['sch_export']) && in_array($section, ['students', 'audit', 'attendance-report', 'staff-report'], true)) {
            self::send_export($section);
        }

        self::handle_post($section, $id);

        $view = $section;
        if ($id > 0 && file_exists(SCH_PATH . 'frontend/views/' . $view . '-single.php')) {
            $view .= '-single';
        }

        self::render($view, ['section' => $section, 'id' => $id]);
    }

    private static function handle_post(string $section, int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action  = sanitize_key((string) ($_POST['sch_action'] ?? ''));
        $actions = self::actions();

        if (!isset($actions[$action])) {
            self::back($section, $id, ['err' => 'unknown_action']);
        }
        if (!wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_' . $action)) {
            self::back($section, $id, [
                'err' => 'nonce',
                'msg' => __('انتهت صلاحية النموذج. حدّث الصفحة وأعد المحاولة.', 'school-system'),
            ]);
        }

        [$capability, $method] = $actions[$action];
        if (!current_user_can($capability)) {
            self::back($section, $id, [
                'err' => 'forbidden',
                'msg' => __('لا تملك صلاحية هذه العملية.', 'school-system'),
            ]);
        }

        $result = self::$method(wp_unslash($_POST), $id);

        if (is_wp_error($result)) {
            self::back($section, $id, ['err' => $result->get_error_code(), 'msg' => $result->get_error_message()]);
        }

        $args = ['ok' => $action];
        if (is_array($result)) {
            // بيانات الدخول لا تمر أبدًا في الرابط — تُحفظ لعرض واحد ثم تُمحى.
            if (isset($result['password'])) {
                self::stash_credentials($result);
                $args['cred'] = 1;
            }
            if (isset($result['msg'])) {
                $args['msg'] = $result['msg'];
            }
            if (isset($result['goto_id'])) {
                $id = (int) $result['goto_id'];
            }
            // البقاء في شاشة التعديل بعد الحفظ: العودة للقائمة تُضيّع مكان المستخدم.
            if (isset($result['edit'])) {
                $args['edit'] = (int) $result['edit'];
            }
        }

        self::back($section, $id, $args);
    }

    /** حفظ بيانات دخول الحساب الجديد لعرضها مرة واحدة. */
    private static function stash_credentials(array $result): void
    {
        set_transient('sch_cred_' . get_current_user_id(), [
            'name'     => (string) ($result['name'] ?? ''),
            'login'    => (string) ($result['login'] ?? ''),
            'password' => (string) ($result['password'] ?? ''),
        ], 5 * MINUTE_IN_SECONDS);
    }

    /** قراءة بيانات الدخول ومحوها فورًا — عرض واحد لا أكثر. */
    public static function pull_credentials(): ?array
    {
        $key  = 'sch_cred_' . get_current_user_id();
        $data = get_transient($key);
        delete_transient($key);

        return is_array($data) && $data !== [] ? $data : null;
    }

    /** خدمة مستند طالب بعد التحقق من الصلاحية — لا وصول مباشر للملف أبدًا. */
    private static function send_private_file(int $doc_id, int $student_id): never
    {
        if (!current_user_can('sch_manage_docs')) {
            status_header(403);
            exit;
        }

        $doc = SCH_Enrollment::get_doc($doc_id);
        if (!$doc || ($student_id > 0 && (int) $doc->student_id !== $student_id)) {
            status_header(404);
            exit;
        }

        $path = SCH_Enrollment::private_dir() . '/' . $doc->stored_as;
        if (!is_readable($path)) {
            status_header(404);
            exit;
        }

        sch_audit('doc.viewed', 'student', (int) $doc->student_id, ['type' => $doc->doc_type]);

        nocache_headers();
        header('Content-Type: ' . $doc->mime_type);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="' . rawurlencode((string) $doc->file_name) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** رابط صورة الطالب المحمية. */
    public static function photo_url(int $student_id): string
    {
        return add_query_arg('sch_photo', 1, self::url('students', $student_id));
    }

    private static function send_photo(int $student_id): never
    {
        if (!current_user_can('sch_view_students')) {
            status_header(403);
            exit;
        }

        $student = SCH_Students::get($student_id);
        if (!$student || !$student->photo_file) {
            status_header(404);
            exit;
        }

        $path = SCH_Enrollment::private_dir() . '/' . $student->photo_file;
        if (!is_readable($path)) {
            status_header(404);
            exit;
        }

        header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private static function send_template(): never
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students-template.csv"');
        echo SCH_Import::template();
        exit;
    }

    private static function back(string $section, int $id, array $args): never
    {
        wp_safe_redirect(add_query_arg($args, self::url($section, $id)));
        exit;
    }

    // ---------- المعالجات ----------

    private static function do_add_student(array $d, int $id): array|WP_Error
    {
        $created = SCH_Students::create($d);
        if (is_wp_error($created)) {
            return $created;
        }

        if (!empty($d['class_id'])) {
            SCH_Students::enroll((int) $created['id'], absint($d['class_id']));
        }

        return $created;
    }

    private static function do_register_student(array $d, int $id): array|WP_Error
    {
        return SCH_Enrollment::register($d, $_FILES);
    }

    private static function do_upload_doc(array $d, int $id): array|WP_Error
    {
        $student_id = absint($d['student_id'] ?? 0) ?: $id;
        $type       = sanitize_key((string) ($d['doc_type'] ?? ''));

        if (empty($_FILES['doc']['tmp_name'])) {
            return sch_api_error('no_file', __('اختر ملفًا.', 'school-system'), 422);
        }

        $result = SCH_Enrollment::save_doc($student_id, $type, $_FILES['doc']);

        return is_wp_error($result) ? $result : ['id' => $result, 'goto_id' => $student_id];
    }

    private static function do_delete_doc(array $d, int $id): bool
    {
        return SCH_Enrollment::delete_doc(absint($d['doc_id'] ?? 0));
    }

    private static function do_save_health(array $d, int $id): bool|WP_Error
    {
        return SCH_Enrollment::save_health(absint($d['student_id'] ?? 0) ?: $id, $d);
    }

    private static function do_add_note(array $d, int $id): array|WP_Error
    {
        return SCH_Notes::create($d + ['student_id' => $id ?: absint($d['student_id'] ?? 0)]);
    }

    private static function do_add_content(array $d, int $id): array|WP_Error
    {
        return SCH_Content::create($d, $_FILES);
    }

    private static function do_content_status(array $d, int $id): bool|WP_Error
    {
        return SCH_Content::set_status(absint($d['content_id'] ?? 0), sanitize_key((string) ($d['status'] ?? '')));
    }

    private static function do_content_renew(array $d, int $id): bool
    {
        return SCH_Content::renew(absint($d['content_id'] ?? 0));
    }

    private static function do_add_homework(array $d, int $id): array|WP_Error
    {
        return SCH_Homework::create($d, $_FILES);
    }

    private static function do_grade_homework(array $d, int $id): bool
    {
        return SCH_Homework::grade(
            absint($d['sub_id'] ?? 0),
            (float) ($d['score'] ?? 0),
            (string) ($d['feedback'] ?? '')
        );
    }

    private static function do_take_note(array $d, int $id): bool
    {
        return SCH_Notes::take(absint($d['note_id'] ?? 0));
    }

    private static function do_close_note(array $d, int $id): bool|WP_Error
    {
        return SCH_Notes::close(absint($d['note_id'] ?? 0), (string) ($d['resolution'] ?? ''));
    }

    private static function do_contact_parent(array $d, int $id): bool|WP_Error
    {
        return SCH_Notes::contact_parent(absint($d['note_id'] ?? 0), (string) ($d['message'] ?? ''));
    }

    private static function do_mark_staff(array $d, int $id): bool|WP_Error
    {
        return SCH_Deputy::mark_staff(
            absint($d['user_id'] ?? 0),
            (string) ($d['status'] ?? ''),
            (string) ($d['note'] ?? '')
        );
    }

    /** حفظ كشف حضور الموظفين دفعةً واحدة — نفس نمط رصد الطلاب. */
    private static function do_mark_staff_bulk(array $d, int $id): bool|WP_Error
    {
        $statuses = (array) ($d['status'] ?? []);

        foreach ($statuses as $user_id => $status) {
            $status = (string) $status;
            if ($status === '') {
                continue; // «لم يُرصد» يُترك كما هو، لا يُكتب
            }
            $result = SCH_Deputy::mark_staff(absint($user_id), $status);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return true;
    }

    private static function do_assign_sub(array $d, int $id): bool|WP_Error
    {
        return SCH_Deputy::assign(absint($d['sub_id'] ?? 0), absint($d['teacher_id'] ?? 0));
    }

    private static function do_save_summary(array $d, int $id): bool|WP_Error
    {
        return SCH_Nerve::save_summary(absint($d['student_id'] ?? 0) ?: $id, $d);
    }

    private static function do_approve_summary(array $d, int $id): bool
    {
        return SCH_Nerve::approve_summary(absint($d['student_id'] ?? 0) ?: $id);
    }

    /** فعل جماعي — الصلاحية تُفحَص داخل SCH_Bulk لكل فعل على حدة. */
    private static function do_bulk(array $d, int $id): array|WP_Error
    {
        return SCH_Bulk::run(
            sanitize_key((string) ($d['screen'] ?? '')),
            sanitize_key((string) ($d['bulk_op'] ?? '')),
            array_map('absint', explode(',', (string) ($d['ids'] ?? ''))),
            [
                'text'   => wp_unslash((string) ($d['bulk_text'] ?? '')),
                'select' => (string) ($d['bulk_select'] ?? ''),
            ]
        );
    }

    /** تعليم البطاقات كمطبوعة — الطباعة نفسها تتم في المتصفح. */
    private static function do_print_badges(array $d, int $id): array
    {
        $ids = array_map('absint', explode(',', (string) ($d['ids'] ?? '')));
        $n   = SCH_Students::mark_printed($ids);

        return ['msg' => sprintf(
            /* translators: %d: عدد البطاقات */
            _n('عُلّمت بطاقة واحدة كمطبوعة', 'عُلّمت %d بطاقة كمطبوعة', $n, 'school-system'),
            $n
        )];
    }

    private static function do_print_staff_badges(array $d, int $id): array
    {
        $ids = array_map('absint', explode(',', (string) ($d['ids'] ?? '')));
        $n   = SCH_Staff::mark_printed($ids);

        return ['msg' => sprintf(
            /* translators: %d: عدد البطاقات */
            _n('عُلّمت بطاقة واحدة كمطبوعة', 'عُلّمت %d بطاقة كمطبوعة', $n, 'school-system'),
            $n
        )];
    }

    private static function do_flow_mark(array $d, int $id): bool
    {
        return SCH_Flow::mark(
            sanitize_key((string) ($d['flow'] ?? '')),
            absint($d['entity_id'] ?? 0),
            sanitize_key((string) ($d['step'] ?? '')),
            sanitize_key((string) ($d['state'] ?? 'done'))
        );
    }

    private static function do_toggle_watch(array $d, int $id): bool
    {
        SCH_Views::toggle_watch(
            absint($d['student_id'] ?? 0),
            !empty($d['notify'])
        );

        return true;
    }

    /** إعادة موظف موقوف للعمل — الموظف يُوقَف لا يُحذف، فإعادته بضغطة. */
    /** توليد بيانات دخول الطالب وعرضها — أو إنشاء حسابه إن كان ناقصًا. */
    private static function do_student_login(array $d, int $id): array|WP_Error
    {
        $student_id = absint($d['student_id'] ?? 0);
        $account    = SCH_Enrollment::reset_student_password($student_id);

        if (is_wp_error($account)) {
            return $account;
        }

        self::stash_credentials([
            'name'     => SCH_Enrollment::full_name(SCH_Students::get($student_id)),
            'login'    => $account['login'],
            'password' => $account['password'],
        ]);

        return ['cred' => 1, 'goto_id' => $student_id];
    }

    /** تعديل بيانات موظف — الصلاحيات لها شاشتها المستقلة. */
    private static function do_issue_cert(array $d, int $id): array|WP_Error
    {
        // النطاق يُفحص كما تفحصه الأفعال الجماعية: الدور وحده كان يترك موظفًا
        // مقصورًا على مرحلة يُصدر شهادات لكل المدرسة.
        if (!SCH_Perms::may('certificates', 'edit')) {
            return sch_api_error('forbidden', __('لا تملك صلاحية إصدار الشهادات.', 'school-system'), 403);
        }

        $result = SCH_Certificates::issue($d);

        if (is_wp_error($result)) {
            return $result;
        }

        return ['msg' => sprintf(
            /* translators: %s: الرقم التسلسلي */
            __('صدرت الشهادة برقم %s', 'school-system'),
            $result['serial']
        )];
    }

    /** سحب شهادة صدرت بالخطأ — بسبب مكتوب، والوثيقة تبقى مختومة «ملغاة». */
    private static function do_revoke_cert(array $d, int $id): array|WP_Error
    {
        if (!SCH_Perms::may('certificates', 'edit')) {
            return sch_api_error('forbidden', __('لا تملك صلاحية إلغاء الشهادات.', 'school-system'), 403);
        }

        $result = SCH_Certificates::revoke(
            absint($d['cert_id'] ?? 0),
            (string) ($d['reason'] ?? '')
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return ['msg' => __('أُلغيت الشهادة، ووصل الخبر ولي الأمر.', 'school-system')];
    }

    /** إصدار جماعي — الوكيل يمنح ثلاثين شهادة بضغطة لا بثلاثين. */
    private static function do_issue_certs_bulk(array $d, int $id): array|WP_Error
    {
        if (!SCH_Perms::may('certificates', 'edit')) {
            return sch_api_error('forbidden', __('لا تملك صلاحية إصدار الشهادات.', 'school-system'), 403);
        }

        $ids  = array_values(array_filter(array_map('absint', explode(',', (string) ($d['ids'] ?? '')))));
        $type = sanitize_key((string) ($d['type'] ?? ''));

        if ($ids === []) {
            return sch_api_error('empty', __('لم تحدد طلابًا.', 'school-system'), 422);
        }

        if (count($ids) > 300) {
            return sch_api_error('too_many', __('حدّد ٣٠٠ طالب أو أقل في المرة الواحدة.', 'school-system'), 422);
        }

        $done = 0;
        $dupe = 0;

        foreach ($ids as $student_id) {
            $r = SCH_Certificates::issue([
                'student_id' => $student_id,
                'type'       => $type,
                'template'   => (string) ($d['template'] ?? ''),
                'reason'     => (string) ($d['reason'] ?? ''),
            ]);

            if (!is_wp_error($r)) {
                $done++;
            } elseif ($r->get_error_code() === 'duplicate') {
                $dupe++;
            }
        }

        $msg = sprintf(
            /* translators: %s: العدد */
            _n('صدرت شهادة واحدة', 'صدرت %s شهادات', $done, 'school-system'),
            number_format_i18n($done)
        );

        // المتخطّى يُقال لا يُبتلع: الوكيل الذي حدّد ٣٠ ورأى «صدرت ١٢» بلا سبب
        // يظنّ النظام معطّلًا.
        if ($dupe > 0) {
            $msg .= ' · ' . sprintf(
                /* translators: %s: العدد */
                _n('وتُخطّي طالب نالها هذا العام', 'وتُخطّي %s نالوها هذا العام', $dupe, 'school-system'),
                number_format_i18n($dupe)
            );
        }

        return ['msg' => $msg];
    }

    private static function do_issue_key(array $d, int $id): array|WP_Error
    {
        $result = SCH_Bridge::issue_key((string) ($d['label'] ?? ''));

        if (is_wp_error($result)) {
            return $result;
        }

        // المفتاح لا يمر في الرابط أبدًا — يُحفظ لعرض واحد ثم يُمحى.
        set_transient('sch_bkey_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS);

        return ['msg' => __('صدر المفتاح — انسخه الآن، لن يظهر مرة أخرى.', 'school-system')];
    }

    private static function do_revoke_key(array $d, int $id): array
    {
        SCH_Bridge::revoke_key(sanitize_text_field((string) ($d['hash'] ?? '')));

        return ['msg' => __('أُلغي المفتاح.', 'school-system')];
    }

    /** مفتاح الجسر لعرض واحد. */
    public static function pull_bridge_key(): ?array
    {
        $key = 'sch_bkey_' . get_current_user_id();
        $val = get_transient($key);

        if (!$val) {
            return null;
        }

        delete_transient($key);

        return is_array($val) ? $val : null;
    }

    private static function do_update_employee(array $d, int $id): array|WP_Error
    {
        $employee_id = absint($d['employee_id'] ?? 0);
        $result      = SCH_Staff::update($employee_id, $d);

        if (is_wp_error($result)) {
            return $result;
        }

        return ['msg' => __('حُفظت التعديلات.', 'school-system'), 'edit' => $employee_id];
    }

    private static function do_restore_employee(array $d, int $id): bool|WP_Error
    {
        return SCH_Staff::update(absint($d['employee_id'] ?? 0), ['status' => 'active']);
    }

    private static function do_save_perms(array $d, int $id): bool|WP_Error
    {
        $sections = [];

        foreach ((array) ($d['perm'] ?? []) as $slug => $modes) {
            $mode = sanitize_key((string) ($modes['mode'] ?? 'none'));

            if ($mode === 'none') {
                continue;
            }

            $sections[sanitize_key((string) $slug)] = [
                'view' => true,
                'edit' => $mode === 'edit',
            ];
        }

        return SCH_Perms::save(
            absint($d['user_id'] ?? 0),
            $sections,
            sanitize_key((string) ($d['scope'] ?? 'all')),
            (string) ($d['expires_at'] ?? '')
        );
    }

    private static function do_copy_perms(array $d, int $id): bool|WP_Error
    {
        return SCH_Perms::copy_from(absint($d['source_id'] ?? 0), absint($d['user_id'] ?? 0));
    }

    /** العودة لصلاحيات الدور: حذف الصفوف وإزالة التوسيع يعيده لسلوكه الافتراضي. */
    private static function do_reset_perms(array $d, int $id): bool
    {
        $user_id = absint($d['user_id'] ?? 0);

        SCH_Perms::reset($user_id);
        sch_audit('perms.reset', 'user', $user_id);

        return true;
    }

    private static function do_submit_timetable(array $d, int $id): array|WP_Error
    {
        return SCH_Org::submit_timetable();
    }

    private static function do_publish_timetable(array $d, int $id): bool|WP_Error
    {
        return SCH_Org::publish_timetable();
    }

    private static function do_reopen_timetable(array $d, int $id): bool
    {
        return SCH_Org::reopen_timetable();
    }

    private static function do_give_dose(array $d, int $id): bool|WP_Error
    {
        return SCH_Medication::give(absint($d['dose_id'] ?? 0));
    }

    private static function do_approve_med(array $d, int $id): bool|WP_Error
    {
        return SCH_Medication::approve(absint($d['med_id'] ?? 0));
    }

    private static function do_reject_med(array $d, int $id): bool
    {
        return SCH_Medication::reject(absint($d['med_id'] ?? 0), (string) ($d['reason'] ?? ''));
    }

    private static function do_decide_student_leave(array $d, int $id): bool|WP_Error
    {
        return SCH_StudentLeave::decide(
            absint($d['leave_id'] ?? 0),
            (string) ($d['decision'] ?? ''),
            (string) ($d['decision_note'] ?? '')
        );
    }

    private static function do_ack_alert(array $d, int $id): bool
    {
        return SCH_Alerts::acknowledge(absint($d['alert_id'] ?? 0));
    }

    private static function do_close_alert(array $d, int $id): bool|WP_Error
    {
        return SCH_Alerts::close(absint($d['alert_id'] ?? 0), (string) ($d['close_reason'] ?? ''));
    }

    /** إدخال إداري — حين يفرغ جوال الحارس أو يُنسى مسح. */
    private static function do_custody_manual(array $d, int $id): array|WP_Error
    {
        return SCH_Custody::record([
            'student_id'  => absint($d['student_id'] ?? 0),
            'checkpoint'  => sanitize_key((string) ($d['checkpoint'] ?? 'manual')),
            'client_uuid' => wp_generate_uuid4(),
            'reason'      => (string) ($d['reason'] ?? __('إدخال إداري', 'school-system')),
        ]);
    }

    private static function do_update_student(array $d, int $id): bool|WP_Error
    {
        return SCH_Students::update($id, $d);
    }

    private static function do_enroll_student(array $d, int $id): bool|WP_Error
    {
        return SCH_Students::enroll($id, absint($d['class_id'] ?? 0));
    }

    private static function do_add_guardian(array $d, int $id): array|WP_Error
    {
        $created = SCH_Guardians::create($d);
        if (is_wp_error($created)) {
            return $created;
        }

        $student_id = absint($d['student_id'] ?? 0) ?: $id;
        if ($student_id > 0) {
            SCH_Guardians::link((int) $created['user_id'], $student_id, (string) ($d['relation'] ?? 'father'), true);
        }

        return $created;
    }

    private static function do_link_guardian(array $d, int $id): bool|WP_Error
    {
        $guardian = SCH_Guardians::get(absint($d['guardian_id'] ?? 0));
        if (!$guardian) {
            return sch_api_error('not_found', __('ولي الأمر غير موجود.', 'school-system'), 404);
        }

        return SCH_Guardians::link(
            (int) $guardian->user_id,
            $id ?: absint($d['student_id'] ?? 0),
            (string) ($d['relation'] ?? 'father'),
            !empty($d['is_primary'])
        );
    }

    private static function do_unlink_guardian(array $d, int $id): bool
    {
        return SCH_Guardians::unlink(absint($d['guardian_user_id'] ?? 0), $id ?: absint($d['student_id'] ?? 0));
    }

    /** إضافة ابن لولي أمر بالرقم الأكاديمي. */
    private static function do_add_child(array $d, int $id): bool|WP_Error
    {
        $guardian = SCH_Guardians::get($id ?: absint($d['guardian_id'] ?? 0));
        if (!$guardian) {
            return sch_api_error('not_found', __('ولي الأمر غير موجود.', 'school-system'), 404);
        }

        $student = SCH_Students::get_by_academic((string) ($d['academic_no'] ?? ''));
        if (!$student) {
            return sch_api_error('student_not_found', __('لا يوجد طالب بهذا الرقم.', 'school-system'), 404);
        }

        return SCH_Guardians::link(
            (int) $guardian->user_id,
            (int) $student->id,
            (string) ($d['relation'] ?? 'father'),
            !empty($d['is_primary'])
        );
    }

    private static function do_update_guardian(array $d, int $id): bool|WP_Error
    {
        return SCH_Guardians::update($id ?: absint($d['guardian_id'] ?? 0), $d);
    }

    private static function do_add_employee(array $d, int $id): array|WP_Error
    {
        $created = SCH_Staff::create($d);

        if (is_wp_error($created)) {
            return $created;
        }

        // الصلاحيات تُحفظ مع الموظف في العملية نفسها — لا شاشة ثانية بعد الإنشاء.
        if (!empty($d['perm']) && !empty($created['user_id'])) {
            $sections = [];

            foreach ((array) $d['perm'] as $slug => $modes) {
                // حالة واحدة لكل قسم: مخفي · يرى · يعدّل
                $mode = sanitize_key((string) ($modes['mode'] ?? 'none'));

                if ($mode === 'none') {
                    continue;
                }

                $sections[sanitize_key((string) $slug)] = [
                    'view' => true,
                    'edit' => $mode === 'edit',
                ];
            }

            SCH_Perms::save(
                (int) $created['user_id'],
                $sections,
                sanitize_key((string) ($d['scope'] ?? 'all')),
                (string) ($d['expires_at'] ?? '')
            );
        }

        return $created;
    }

    private static function do_suspend_employee(array $d, int $id): bool|WP_Error
    {
        return SCH_Staff::suspend(absint($d['employee_id'] ?? 0));
    }

    private static function do_add_class(array $d, int $id): array|WP_Error
    {
        return SCH_Classes::create($d);
    }

    private static function do_delete_class(array $d, int $id): bool|WP_Error
    {
        return SCH_Classes::delete(absint($d['class_id'] ?? 0));
    }

    private static function do_add_year(array $d, int $id): array|WP_Error
    {
        return SCH_Years::create($d);
    }

    private static function do_set_year(array $d, int $id): bool
    {
        return SCH_Years::set_current(absint($d['year_id'] ?? 0));
    }

    /** دمج مفاتيح في إعدادات المدرسة بلا مسح البقية — لكل قسم نموذجه. */
    private static function merge_settings(array $patch): void
    {
        $s = sch_settings();
        if (!is_array($s)) {
            $s = [];
        }
        update_option('sch_settings', array_merge($s, $patch));
    }

    private static function do_save_settings(array $d, int $id): bool
    {
        self::merge_settings([
            'school_name'  => sanitize_text_field((string) ($d['school_name'] ?? '')),
            'school_phone' => sch_normalize_phone((string) ($d['school_phone'] ?? '')),
            'school_email' => sanitize_email((string) ($d['school_email'] ?? '')),
            'address'      => sanitize_text_field((string) ($d['address'] ?? '')),
            'moe_id'       => sanitize_text_field((string) ($d['moe_id'] ?? '')),
        ]);

        sch_audit('settings.updated', 'settings', null, ['section' => 'school']);
        return true;
    }

    /** مواعيد اليوم الدراسي — تُغذّي حساب الحضور والتأخّر. */
    private static function do_save_timing(array $d, int $id): bool
    {
        $time = static fn (mixed $v): string => preg_match('/^\d{2}:\d{2}$/', (string) $v) ? (string) $v : '';

        self::merge_settings([
            'attendance_student_in' => $time($d['attendance_student_in'] ?? ''),
            'attendance_staff_in'   => $time($d['attendance_staff_in'] ?? ''),
            'attendance_grace'      => (string) max(0, min(120, absint($d['attendance_grace'] ?? 10))),
            'attendance_day_end'    => $time($d['attendance_day_end'] ?? ''),
        ]);

        sch_audit('settings.updated', 'settings', null, ['section' => 'timing']);
        return true;
    }

    /** حدود الإنذارات — تُغذّي الحارس الآلي (SCH_Alerts). */
    private static function do_save_alerts(array $d, int $id): bool
    {
        $time = static fn (mixed $v): string => preg_match('/^\d{2}:\d{2}$/', (string) $v) ? (string) $v : '';

        self::merge_settings([
            'alert_bus_no_board_at'  => $time($d['alert_bus_no_board_at'] ?? ''),
            'alert_bus_arrival_by'   => $time($d['alert_bus_arrival_by'] ?? ''),
            'alert_attendance_by'    => $time($d['alert_attendance_by'] ?? ''),
            'alert_dismissal_at'     => $time($d['alert_dismissal_at'] ?? ''),
            'alert_still_at_school'  => (string) max(0, min(180, absint($d['alert_still_at_school'] ?? 30))),
            'alert_referral_minutes' => (string) max(1, min(120, absint($d['alert_referral_minutes'] ?? 10))),
        ]);

        sch_audit('settings.updated', 'settings', null, ['section' => 'alerts']);
        return true;
    }

    private static function do_import(array $d, int $id): array|WP_Error
    {
        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file((string) $_FILES['csv']['tmp_name'])) {
            return sch_api_error('no_file', __('اختر ملف CSV أولًا.', 'school-system'), 422);
        }

        if ((int) ($_FILES['csv']['size'] ?? 0) > 5 * MB_IN_BYTES) {
            return sch_api_error('too_large', __('حجم الملف يتجاوز 5 ميجابايت.', 'school-system'), 422);
        }

        $dry    = empty($d['confirm']);
        $result = SCH_Import::run((string) $_FILES['csv']['tmp_name'], $dry);
        $key    = 'sch_import_errors_' . get_current_user_id();

        if (!$result['ok']) {
            set_transient($key, $result['errors'], 10 * MINUTE_IN_SECONDS);
            return sch_api_error('import_failed', __('الملف يحتوي أخطاء. راجع التقرير أدناه.', 'school-system'), 422);
        }

        delete_transient($key);

        if ($dry) {
            return ['msg' => __('الملف سليم. أعد الرفع مع تفعيل «تأكيد الاستيراد» لإتمام العملية.', 'school-system')];
        }

        return ['msg' => sprintf(
            /* translators: %d: عدد الطلاب */
            __('اُستورد %d طالبًا.', 'school-system'),
            $result['imported']
        )];
    }

    // ---------- معالجات الوحدات ----------

    private static function do_mark_attendance(array $d, int $id): bool|WP_Error
    {
        $date     = sch_sanitize_date($d['att_date'] ?? null) ?? current_time('Y-m-d');
        $statuses = (array) ($d['status'] ?? []);

        foreach ($statuses as $student_id => $status) {
            $result = SCH_Attendance::mark(absint($student_id), $date, (string) $status, 'manual');
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return true;
    }

    private static function do_add_bus(array $d, int $id): array|WP_Error
    {
        return SCH_Buses::create($d);
    }

    private static function do_add_route(array $d, int $id): array|WP_Error
    {
        return SCH_Routes::create($d);
    }

    private static function do_add_stop(array $d, int $id): array|WP_Error
    {
        return SCH_Routes::add_stop($d + ['route_id' => $id]);
    }

    private static function do_delete_stop(array $d, int $id): bool
    {
        return SCH_Routes::delete_stop(absint($d['stop_id'] ?? 0));
    }

    private static function do_subscribe(array $d, int $id): bool|WP_Error
    {
        return SCH_Routes::subscribe($d);
    }

    private static function do_unsubscribe(array $d, int $id): bool
    {
        return SCH_Routes::unsubscribe(absint($d['student_id'] ?? 0));
    }

    private static function do_open_trip(array $d, int $id): array|WP_Error
    {
        return SCH_Trips::open(absint($d['route_id'] ?? 0), (string) ($d['direction'] ?? 'morning'));
    }

    private static function do_close_trip(array $d, int $id): bool|WP_Error
    {
        return SCH_Trips::close(absint($d['trip_id'] ?? 0));
    }

    private static function do_add_subject(array $d, int $id): array|WP_Error
    {
        return SCH_Subjects::create($d);
    }

    private static function do_assign_subject(array $d, int $id): bool|WP_Error
    {
        return SCH_Subjects::assign($d);
    }

    private static function do_set_slot(array $d, int $id): bool|WP_Error
    {
        return SCH_Timetable::set_slot($d);
    }

    private static function do_clear_slot(array $d, int $id): bool
    {
        return SCH_Timetable::clear_slot(
            absint($d['class_id'] ?? 0),
            (int) ($d['day_of_week'] ?? 0),
            (int) ($d['period_no'] ?? 0)
        );
    }

    private static function do_add_exam(array $d, int $id): array|WP_Error
    {
        return SCH_Assessment::create_exam($d);
    }

    private static function do_save_scores(array $d, int $id): bool|WP_Error
    {
        return SCH_Assessment::save_scores(absint($d['exam_id'] ?? 0), (array) ($d['score'] ?? []));
    }

    private static function do_add_fee_plan(array $d, int $id): array|WP_Error
    {
        return SCH_Finance::create_plan($d);
    }

    private static function do_issue_invoice(array $d, int $id): array|WP_Error
    {
        return SCH_Finance::issue_invoice($d);
    }

    private static function do_record_payment(array $d, int $id): array|WP_Error
    {
        return SCH_Finance::record_payment($d);
    }

    private static function do_void_invoice(array $d, int $id): bool|WP_Error
    {
        return SCH_Finance::void_invoice(absint($d['invoice_id'] ?? 0), (string) ($d['reason'] ?? ''));
    }

    private static function do_refund_payment(array $d, int $id): bool|WP_Error
    {
        return SCH_Finance::refund_payment(absint($d['payment_id'] ?? 0), (string) ($d['reason'] ?? ''));
    }

    private static function do_run_rollover(array $d, int $id): array|WP_Error
    {
        $map = [];
        foreach ((array) ($d['map'] ?? []) as $k => $v) {
            $map[sanitize_text_field((string) $k)] = sanitize_text_field((string) $v);
        }

        $grad = [];
        foreach ((array) ($d['graduate'] ?? []) as $k => $v) {
            $grad[sanitize_text_field((string) $k)] = 1;
        }

        return SCH_Rollover::run(absint($d['from_year'] ?? 0), absint($d['to_year'] ?? 0), $map, $grad);
    }

    private static function do_send_message(array $d, int $id): array|WP_Error
    {
        return SCH_Comms::broadcast($d);
    }

    private static function do_add_account(array $d, int $id): array|WP_Error
    {
        return SCH_Accounts::create($d);
    }

    private static function do_add_journal(array $d, int $id): array|WP_Error
    {
        $lines = [];

        foreach ((array) ($d['line_account'] ?? []) as $i => $account_id) {
            $lines[] = [
                'account_id'  => absint($account_id),
                'debit'       => (float) (($d['line_debit'][$i] ?? 0) ?: 0),
                'credit'      => (float) (($d['line_credit'][$i] ?? 0) ?: 0),
                'description' => (string) ($d['line_note'][$i] ?? ''),
            ];
        }

        return SCH_Journal::create($d, $lines, empty($d['as_draft']));
    }

    private static function do_post_journal(array $d, int $id): bool|WP_Error
    {
        return SCH_Journal::post(absint($d['entry_id'] ?? 0));
    }

    private static function do_reverse_journal(array $d, int $id): array|WP_Error
    {
        return SCH_Journal::reverse(absint($d['entry_id'] ?? 0));
    }

    private static function do_add_contract(array $d, int $id): array|WP_Error
    {
        return SCH_HR::create_contract($d);
    }

    private static function do_request_leave(array $d, int $id): array|WP_Error
    {
        return SCH_HR::request_leave($d);
    }

    private static function do_decide_leave(array $d, int $id): bool|WP_Error
    {
        return SCH_HR::decide_leave(absint($d['leave_id'] ?? 0), (string) ($d['decision'] ?? ''));
    }

    private static function do_run_payroll(array $d, int $id): array|WP_Error
    {
        return SCH_Payroll::generate((int) ($d['period_year'] ?? 0), (int) ($d['period_month'] ?? 0));
    }

    private static function do_post_payroll(array $d, int $id): bool|WP_Error
    {
        return SCH_Payroll::post(absint($d['run_id'] ?? 0));
    }

    private static function do_add_clinic(array $d, int $id): array|WP_Error
    {
        return SCH_Clinic::record($d);
    }

    private static function do_add_book(array $d, int $id): array|WP_Error
    {
        return SCH_Library::add_book($d);
    }

    private static function do_loan_book(array $d, int $id): bool|WP_Error
    {
        return SCH_Library::loan($d);
    }

    private static function do_return_book(array $d, int $id): bool
    {
        return SCH_Library::give_back(absint($d['loan_id'] ?? 0));
    }

    private static function do_check_in(array $d, int $id): array|WP_Error
    {
        return SCH_Security::check_in($d);
    }

    private static function do_check_out(array $d, int $id): bool
    {
        return SCH_Security::check_out(absint($d['visitor_id'] ?? 0));
    }

    private static function do_add_incident(array $d, int $id): array|WP_Error
    {
        return SCH_Security::report_incident($d);
    }

    private static function do_close_incident(array $d, int $id): bool
    {
        return SCH_Security::close_incident(absint($d['incident_id'] ?? 0));
    }

    private static function do_add_asset(array $d, int $id): array|WP_Error
    {
        return SCH_Assets::create($d);
    }

    private static function do_add_work(array $d, int $id): array|WP_Error
    {
        return SCH_Assets::report_maintenance($d);
    }

    private static function do_set_work(array $d, int $id): bool|WP_Error
    {
        return SCH_Assets::set_work_status(absint($d['work_id'] ?? 0), (string) ($d['status'] ?? ''));
    }

    private static function do_add_item(array $d, int $id): array|WP_Error
    {
        return SCH_Assets::add_item($d);
    }

    private static function do_move_stock(array $d, int $id): bool|WP_Error
    {
        return SCH_Assets::move_stock($d);
    }

    private static function do_save_kg(array $d, int $id): bool|WP_Error
    {
        return SCH_KG::save_log($d);
    }

    // ---------- الدخول ----------

    private static function render_login(): never
    {
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['sch_login'])) {
            $error = self::attempt_login();
        }
        self::render('login', ['error' => $error]);
    }

    private static function attempt_login(): string
    {
        if (!wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_login')) {
            return __('انتهت صلاحية النموذج. حدّث الصفحة وحاول مرة أخرى.', 'school-system');
        }

        $username = sanitize_user(wp_unslash((string) ($_POST['username'] ?? '')));
        $key      = 'sch_lock_' . md5($username . '|' . self::client_ip());
        $attempts = (int) get_transient($key);

        if ($attempts >= self::MAX_ATTEMPTS) {
            return sprintf(
                /* translators: %d: عدد الدقائق */
                __('تم إيقاف المحاولات مؤقتًا. أعد المحاولة بعد %d دقيقة.', 'school-system'),
                self::LOCK_MINUTES
            );
        }

        $user = wp_signon([
            'user_login'    => $username,
            'user_password' => (string) ($_POST['password'] ?? ''),
            'remember'      => !empty($_POST['remember']),
        ], is_ssl());

        if (is_wp_error($user)) {
            set_transient($key, $attempts + 1, self::LOCK_MINUTES * MINUTE_IN_SECONDS);
            sch_audit('auth.failed', 'user', null, ['login' => $username]);
            return __('اسم المستخدم أو كلمة المرور غير صحيحة.', 'school-system');
        }

        $employee = SCH_Staff::get_by_user($user->ID);
        if ($employee && $employee->status !== 'active') {
            wp_logout();
            return __('هذا الحساب موقوف. راجع إدارة المدرسة.', 'school-system');
        }

        delete_transient($key);
        wp_set_current_user($user->ID);
        sch_audit('auth.login', 'user', $user->ID, ['via' => 'dashboard']);

        wp_safe_redirect(self::url());
        exit;
    }

    private static function handle_logout(): never
    {
        if (is_user_logged_in()) {
            sch_audit('auth.logout', 'user', get_current_user_id());
        }
        wp_logout();
        wp_safe_redirect(self::url());
        exit;
    }

    // ---------- العرض ----------

    /** تصدير CSV — نسخة للمدقّق أو الأرشيف. مسار تنزيل مبكّر قبل القالب،
        ويحترم فلاتر الشاشة ونطاق الصلاحية (list يطبّق «على مَن»). */
    private static function send_export(string $section): never
    {
        self::no_cache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sch-' . $section . '-' . current_time('Y-m-d') . '.csv"');

        echo "\xEF\xBB\xBF"; // BOM ليقرأ إكسل العربية صحيحًا
        $out = fopen('php://output', 'w');

        if ($section === 'students') {
            fputcsv($out, [
                __('الرقم الأكاديمي', 'school-system'), __('الاسم', 'school-system'),
                __('المرحلة', 'school-system'), __('الصف', 'school-system'),
                __('الشعبة', 'school-system'), __('الحالة', 'school-system'),
            ]);
            $rows = SCH_Students::list([
                'search'      => isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '',
                'status'      => 'active',
                'stage'       => isset($_GET['stage']) ? sanitize_key(wp_unslash((string) $_GET['stage'])) : '',
                'grade_level' => isset($_GET['g']) ? sanitize_text_field(wp_unslash((string) $_GET['g'])) : '',
                'class_id'    => isset($_GET['class_id']) ? absint($_GET['class_id']) : 0,
                'view'        => isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : '',
                'per_page'    => 5000,
                'with'        => false,
            ])['items'];
            foreach ($rows as $s) {
                fputcsv($out, [
                    (string) ($s->academic_no ?? ''), (string) $s->full_name,
                    (string) ($s->stage ?? ''), (string) ($s->grade_level ?? ''),
                    (string) ($s->section ?? ''), (string) ($s->status ?? ''),
                ]);
            }
        } elseif ($section === 'audit') {
            fputcsv($out, [
                __('الوقت', 'school-system'), __('المستخدم', 'school-system'),
                __('العملية', 'school-system'), __('الهدف', 'school-system'), 'IP',
            ]);
            $rows = SCH_Audit::query([
                'search'      => isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '',
                'object_type' => isset($_GET['obj']) ? sanitize_key((string) $_GET['obj']) : '',
                'from'        => isset($_GET['from']) ? (sch_sanitize_date($_GET['from']) ?? '') : '',
                'to'          => isset($_GET['to']) ? (sch_sanitize_date($_GET['to']) ?? '') : '',
                'per_page'    => 5000,
            ])['items'];
            foreach ($rows as $r) {
                fputcsv($out, [
                    (string) $r->created_at, (string) ($r->display_name ?? ''),
                    (string) $r->action,
                    $r->object_type ? $r->object_type . '#' . $r->object_id : '',
                    (string) ($r->ip ?? ''),
                ]);
            }
        } elseif ($section === 'attendance-report') {
            $class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
            $ym       = isset($_GET['ym']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['ym']) ? (string) $_GET['ym'] : current_time('Y-m');

            fputcsv($out, [
                __('الرقم الأكاديمي', 'school-system'), __('الاسم', 'school-system'),
                __('حاضر', 'school-system'), __('متأخر', 'school-system'),
                __('غائب', 'school-system'), __('بعذر', 'school-system'),
                __('دقائق التأخّر', 'school-system'), __('نسبة الحضور %', 'school-system'),
            ]);

            foreach (($class_id > 0 ? SCH_Attendance::class_month($class_id, $ym) : []) as $r) {
                $total = (int) $r->total;
                $rate  = $total > 0 ? round(((int) $r->present + (int) $r->late) / $total * 100, 1) : 0;
                fputcsv($out, [
                    (string) ($r->academic_no ?? ''), (string) $r->full_name,
                    (int) $r->present, (int) $r->late, (int) $r->absent, (int) $r->excused,
                    (int) $r->late_minutes, $rate,
                ]);
            }
        } elseif ($section === 'staff-report') {
            $ym = isset($_GET['ym']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['ym']) ? (string) $_GET['ym'] : current_time('Y-m');

            fputcsv($out, [
                __('الاسم', 'school-system'), __('الدور', 'school-system'),
                __('حاضر', 'school-system'), __('متأخر', 'school-system'),
                __('غائب', 'school-system'), __('إجازة', 'school-system'),
                __('دقائق التأخّر', 'school-system'),
            ]);

            foreach (SCH_StaffAttendance::staff_roster($ym) as $r) {
                fputcsv($out, [
                    (string) $r->display_name, SCH_Staff::role_label((string) $r->role),
                    (int) $r->present, (int) $r->late, (int) $r->absent, (int) $r->leave_days,
                    (int) $r->late_minutes,
                ]);
            }
        }

        fclose($out);
        sch_audit('export.csv', $section, null);
        exit;
    }

    private static function render(string $view, array $data = []): never
    {
        $file = SCH_PATH . 'frontend/views/' . $view . '.php';
        if (!file_exists($file)) {
            $file = SCH_PATH . 'frontend/views/404.php';
        }

        $sch_view = $view;
        $sch_data = $data;

        require SCH_PATH . 'frontend/views/layout.php';
        exit;
    }

    private static function no_cache_headers(): void
    {
        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
    }

    // ---------- عزل ووردبريس ----------

    public static function block_wp_admin(): void
    {
        if (wp_doing_ajax() || current_user_can('manage_options')) {
            return;
        }
        if (current_user_can('sch_view_own_children')) {
            wp_safe_redirect(SCH_App::url());
            exit;
        }
        if (current_user_can('sch_drive_trip') && !current_user_can('sch_manage_transport')) {
            wp_safe_redirect(SCH_Driver::url());
            exit;
        }
        if (current_user_can('sch_scan_gate') && !current_user_can('sch_view_students')) {
            wp_safe_redirect(SCH_Gate::url());
            exit;
        }
        if (self::is_school_user()) {
            wp_safe_redirect(self::url());
            exit;
        }
    }

    public static function hide_admin_bar(bool $show): bool
    {
        return self::is_school_user() ? false : $show;
    }

    public static function after_login_redirect(string $to, string $requested, mixed $user): string
    {
        if (!$user instanceof WP_User) {
            return $to;
        }
        if (in_array('sch_guardian', $user->roles, true)) {
            return SCH_App::url();
        }
        if (in_array('sch_driver', $user->roles, true)) {
            return SCH_Driver::url();
        }
        if (in_array('sch_guard', $user->roles, true)) {
            return SCH_Gate::url();
        }
        // المعلم: حساب واحد بسطحين — الجوال للميدان والكمبيوتر للدرجات.
        if (in_array('sch_teacher', $user->roles, true) && wp_is_mobile()) {
            return SCH_Teacher::url();
        }
        return self::is_school_user($user) ? self::url() : $to;
    }

    private static function is_school_user(?WP_User $user = null): bool
    {
        $user ??= wp_get_current_user();
        if (!$user instanceof WP_User || !$user->exists()) {
            return false;
        }
        foreach ($user->roles as $role) {
            if (str_starts_with((string) $role, 'sch_')) {
                return true;
            }
        }
        return false;
    }

    private static function client_ip(): string
    {
        return isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : '0.0.0.0';
    }
}
