<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * إنشاء الجداول والأدوار، وترحيل النسخ.
 * كل تغيير في مخطط قاعدة البيانات يستلزم رفع SCH_VERSION.
 */
final class SCH_Activator
{
    public static function activate(): void
    {
        SCH_Loader::load_modules();

        self::create_tables();
        self::register_roles();
        SCH_Accounts::seed();
        update_option('sch_db_version', SCH_VERSION);
        update_option('sch_jwt_secret', self::ensure_secret(), false);
        SCH_Dashboard::add_rewrite();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /** يُنادى عند كل تحميل — يشغّل الترحيل فقط عند اختلاف النسخة. */
    public static function maybe_upgrade(): void
    {
        if (get_option('sch_db_version') !== SCH_VERSION) {
            SCH_Loader::load_modules();
            self::create_tables();
            self::register_roles();
            SCH_Accounts::seed();
            update_option('sch_db_version', SCH_VERSION);

            self::purge_caches();
        }
    }

    /**
     * الترقية تُفرّغ الكاش بنفسها.
     *
     * بلا هذا يرفع المستخدم نسخة جديدة فتخدمه طبقة الكاش القديمة،
     * فيظن أن الإصلاح لم يصل. وقع ذلك فعلًا وأتعب المالك،
     * فصار التفريغ جزءًا من الترقية لا خطوة يدوية تُنسى.
     */
    /**
     * الجداول التي فشل إنشاؤها.
     *
     * جدول لا يُنشأ بصمت يجعل كل عملية عليه تفشل برسالة عامة، فيضيع اليوم
     * في البحث عن سبب في المكان الخطأ. فنُبلّغ عنه في الإعدادات صراحةً.
     */
    public static function missing_tables(): array
    {
        global $wpdb;

        $p       = $wpdb->prefix . 'sch_';
        $missing = [];

        foreach (self::expected_tables() as $name) {
            $full = $p . $name;

            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) !== $full) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    private static function expected_tables(): array
    {
        static $names = null;

        if ($names !== null) {
            return $names;
        }

        $src = (string) file_get_contents(__FILE__);
        preg_match_all('/CREATE TABLE \{\$p\}(\w+) \(/', $src, $m);

        return $names = array_values(array_unique($m[1] ?? []));
    }

    private static function purge_caches(): void
    {
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }

        // إشارات عامة تلتقطها إضافات الكاش الشائعة.
        do_action('litespeed_purge_all');
        do_action('rocket_clean_domain');
        do_action('w3tc_flush_all');

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        sch_audit('system.cache_purged', 'system', null, ['version' => SCH_VERSION]);
    }

    private static function ensure_secret(): string
    {
        $existing = get_option('sch_jwt_secret');
        return is_string($existing) && $existing !== '' ? $existing : wp_generate_password(64, true, true);
    }

    private static function create_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $p       = $wpdb->prefix . 'sch_';
        // ENGINE=InnoDB صراحةً: المعاملات (transactions) والمفاتيح الأجنبية
        // تعتمد عليه، وبدونه كانت الجداول ترث محرّك الخادم الافتراضي (قد يكون
        // MyISAM) فتصير كل START TRANSACTION/ROLLBACK بلا أثر — كتابة جزئية صامتة.
        $charset = 'ENGINE=InnoDB ' . $wpdb->get_charset_collate();

        // الطلاب — السجل المرجعي الذي تعتمد عليه كل الوحدات.
        $sql[] = "CREATE TABLE {$p}students (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name     VARCHAR(190)    NOT NULL,
            first_name    VARCHAR(60)     DEFAULT NULL,
            father_name   VARCHAR(60)     DEFAULT NULL,
            grand_name    VARCHAR(60)     DEFAULT NULL,
            family_name   VARCHAR(60)     DEFAULT NULL,
            name_en       VARCHAR(190)    DEFAULT NULL,
            academic_no   VARCHAR(20)     DEFAULT NULL,
            student_no    VARCHAR(20)     DEFAULT NULL,
            id_type       ENUM('national','iqama','visitor') NOT NULL DEFAULT 'national',
            nationality   VARCHAR(60)     DEFAULT NULL,
            photo_file    VARCHAR(190)    DEFAULT NULL,
            enrolled_at   DATE            DEFAULT NULL,
            national_id   VARCHAR(20)     DEFAULT NULL,
            birth_date    DATE            DEFAULT NULL,
            gender        ENUM('male','female') DEFAULT NULL,
            stage         VARCHAR(30)     DEFAULT NULL,
            grade_level   VARCHAR(30)     DEFAULT NULL,
            section       VARCHAR(30)     DEFAULT NULL,
            qr_token      CHAR(32)        NOT NULL,
            user_id       BIGINT UNSIGNED DEFAULT NULL,
            badge_token   CHAR(32)        DEFAULT NULL,
            badge_printed_at DATETIME     DEFAULT NULL,
            custody_state ENUM('home','on_bus','at_school','left_early') NOT NULL DEFAULT 'home',
            custody_at    DATETIME        DEFAULT NULL,
            status        ENUM('active','transferred','withdrawn','graduated') NOT NULL DEFAULT 'active',
            notes         TEXT            DEFAULT NULL,
            created_at    DATETIME        NOT NULL,
            updated_at    DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_qr (qr_token),
            UNIQUE KEY uniq_national (national_id),
            UNIQUE KEY uniq_academic (academic_no),
            UNIQUE KEY uniq_badge (badge_token),
            UNIQUE KEY uniq_user (user_id),
            KEY idx_custody (custody_state, custody_at),
            UNIQUE KEY uniq_studentno (student_no),
            KEY idx_status (status),
            KEY idx_grade (grade_level, section),
            KEY idx_family (family_name)
        ) {$charset};";

        // ربط ولي الأمر بالطالب — ولي أمر واحد قد يكون له عدة أبناء والعكس.
        $sql[] = "CREATE TABLE {$p}guardian_student (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            guardian_user_id  BIGINT UNSIGNED NOT NULL,
            student_id        BIGINT UNSIGNED NOT NULL,
            relation          VARCHAR(30)     DEFAULT NULL,
            is_primary        TINYINT(1)      NOT NULL DEFAULT 0,
            can_pickup        TINYINT(1)      NOT NULL DEFAULT 1,
            created_at        DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_link (guardian_user_id, student_id),
            KEY idx_student (student_id)
        ) {$charset};";

        // رموز التجديد — جلسة لكل جهاز، قابلة للإلغاء من الداشبورد.
        $sql[] = "CREATE TABLE {$p}refresh_tokens (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            token_hash  CHAR(64)        NOT NULL,
            device_id   VARCHAR(100)    NOT NULL,
            device_name VARCHAR(190)    DEFAULT NULL,
            expires_at  DATETIME        NOT NULL,
            revoked_at  DATETIME        DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_hash (token_hash),
            KEY idx_user (user_id, revoked_at)
        ) {$charset};";

        // سجل التدقيق — كل عملية حساسة تُسجَّل هنا.
        $sql[] = "CREATE TABLE {$p}audit_log (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED DEFAULT NULL,
            action      VARCHAR(60)     NOT NULL,
            object_type VARCHAR(40)     DEFAULT NULL,
            object_id   BIGINT UNSIGNED DEFAULT NULL,
            meta        LONGTEXT        DEFAULT NULL,
            ip          VARCHAR(45)     DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_object (object_type, object_id),
            KEY idx_created (created_at)
        ) {$charset};";

        // الموظفون — بيانات وظيفية مرتبطة بمستخدم ووردبريس.
        $sql[] = "CREATE TABLE {$p}employees (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            employee_no VARCHAR(40)     DEFAULT NULL,
            job_title   VARCHAR(120)    DEFAULT NULL,
            stage_scope ENUM('none','early','middle','high','all') NOT NULL DEFAULT 'none',
            weekly_quota TINYINT UNSIGNED NOT NULL DEFAULT 24,
            department  VARCHAR(120)    DEFAULT NULL,
            phone       VARCHAR(30)     DEFAULT NULL,
            national_id VARCHAR(20)     DEFAULT NULL,
            hire_date   DATE            DEFAULT NULL,
            status      ENUM('active','suspended','left') NOT NULL DEFAULT 'active',
            created_at  DATETIME        NOT NULL,
            updated_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user (user_id),
            KEY idx_status (status)
        ) {$charset};";

        // السنوات الدراسية — كل سجل أكاديمي مرتبط بسنة.
        $sql[] = "CREATE TABLE {$p}academic_years (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(60)     NOT NULL,
            start_date DATE            NOT NULL,
            end_date   DATE            NOT NULL,
            is_current TINYINT(1)      NOT NULL DEFAULT 0,
            tt_status  ENUM('draft','pending','published') NOT NULL DEFAULT 'draft',
            tt_sent_by BIGINT UNSIGNED DEFAULT NULL,
            tt_sent_at DATETIME        DEFAULT NULL,
            tt_ok_by   BIGINT UNSIGNED DEFAULT NULL,
            tt_ok_at   DATETIME        DEFAULT NULL,
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_name (name),
            KEY idx_current (is_current)
        ) {$charset};";

        // الصفوف والشعب — كيانات حقيقية لا نصوص حرة.
        $sql[] = "CREATE TABLE {$p}classes (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            year_id             BIGINT UNSIGNED NOT NULL,
            stage               VARCHAR(30)     NOT NULL,
            grade_level         VARCHAR(60)     NOT NULL,
            section             VARCHAR(30)     NOT NULL,
            capacity            SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            homeroom_teacher_id BIGINT UNSIGNED DEFAULT NULL,
            created_at          DATETIME        NOT NULL,
            updated_at          DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_class (year_id, grade_level, section),
            KEY idx_year (year_id),
            KEY idx_stage (stage)
        ) {$charset};";

        // التسجيل — الطالب في صف محدد لسنة محددة.
        $sql[] = "CREATE TABLE {$p}enrollments (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            class_id   BIGINT UNSIGNED NOT NULL,
            year_id    BIGINT UNSIGNED NOT NULL,
            status     ENUM('active','transferred','withdrawn') NOT NULL DEFAULT 'active',
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_enrollment (student_id, year_id),
            KEY idx_class (class_id, status)
        ) {$charset};";

        // أولياء الأمور — بيانات إضافية لمستخدم ووردبريس بدور sch_guardian.
        $sql[] = "CREATE TABLE {$p}guardians (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            parent_no   VARCHAR(20)     DEFAULT NULL,
            photo_file  VARCHAR(190)    DEFAULT NULL,
            first_name  VARCHAR(60)     DEFAULT NULL,
            father_name VARCHAR(60)     DEFAULT NULL,
            grand_name  VARCHAR(60)     DEFAULT NULL,
            family_name VARCHAR(60)     DEFAULT NULL,
            id_type     ENUM('national','iqama','visitor') NOT NULL DEFAULT 'national',
            city        VARCHAR(80)     DEFAULT NULL,
            national_id VARCHAR(20)     DEFAULT NULL,
            phone       VARCHAR(30)     DEFAULT NULL,
            alt_phone   VARCHAR(30)     DEFAULT NULL,
            workplace   VARCHAR(150)    DEFAULT NULL,
            address     VARCHAR(255)    DEFAULT NULL,
            status      ENUM('active','suspended') NOT NULL DEFAULT 'active',
            created_at  DATETIME        NOT NULL,
            updated_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user (user_id),
            UNIQUE KEY uniq_national (national_id),
            UNIQUE KEY uniq_parentno (parent_no),
            KEY idx_phone (phone)
        ) {$charset};";

        // ===== النقل المدرسي =====
        $sql[] = "CREATE TABLE {$p}buses (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            plate_no            VARCHAR(20)     NOT NULL,
            model               VARCHAR(80)     DEFAULT NULL,
            capacity            SMALLINT UNSIGNED NOT NULL DEFAULT 20,
            driver_user_id      BIGINT UNSIGNED DEFAULT NULL,
            supervisor_user_id  BIGINT UNSIGNED DEFAULT NULL,
            registration_expiry DATE            DEFAULT NULL,
            status              ENUM('active','maintenance','retired') NOT NULL DEFAULT 'active',
            created_at          DATETIME        NOT NULL,
            updated_at          DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_plate (plate_no),
            KEY idx_driver (driver_user_id),
            KEY idx_status (status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}routes (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(120)    NOT NULL,
            bus_id     BIGINT UNSIGNED DEFAULT NULL,
            direction  ENUM('morning','afternoon','both') NOT NULL DEFAULT 'both',
            status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
            duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_bus (bus_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}route_stops (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            route_id    BIGINT UNSIGNED NOT NULL,
            name        VARCHAR(150)    NOT NULL,
            lat         DECIMAL(10,7)   DEFAULT NULL,
            lng         DECIMAL(10,7)   DEFAULT NULL,
            sequence    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            pickup_time TIME            DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_route (route_id, sequence)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}transport_subs (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            route_id   BIGINT UNSIGNED NOT NULL,
            stop_id    BIGINT UNSIGNED DEFAULT NULL,
            year_id    BIGINT UNSIGNED NOT NULL,
            fee_amount DECIMAL(10,2)   NOT NULL DEFAULT 0,
            status     ENUM('active','suspended','ended') NOT NULL DEFAULT 'active',
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sub (student_id, year_id),
            KEY idx_route (route_id, status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}trips (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            route_id       BIGINT UNSIGNED NOT NULL,
            bus_id         BIGINT UNSIGNED DEFAULT NULL,
            driver_user_id BIGINT UNSIGNED DEFAULT NULL,
            direction      ENUM('morning','afternoon') NOT NULL,
            trip_date      DATE            NOT NULL,
            started_at     DATETIME        DEFAULT NULL,
            ended_at       DATETIME        DEFAULT NULL,
            status         ENUM('planned','running','completed','cancelled') NOT NULL DEFAULT 'planned',
            created_at     DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_trip (route_id, trip_date, direction),
            KEY idx_status (status, trip_date),
            KEY idx_driver (driver_user_id, trip_date)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}trip_events (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            trip_id     BIGINT UNSIGNED NOT NULL,
            student_id  BIGINT UNSIGNED NOT NULL,
            type        ENUM('board','alight','absent','handover') NOT NULL,
            lat         DECIMAL(10,7)   DEFAULT NULL,
            lng         DECIMAL(10,7)   DEFAULT NULL,
            client_uuid CHAR(36)        NOT NULL,
            recorded_by BIGINT UNSIGNED DEFAULT NULL,
            occurred_at DATETIME        NOT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_client (client_uuid),
            KEY idx_trip (trip_id, type),
            KEY idx_student (student_id, occurred_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}vehicle_positions (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            trip_id     BIGINT UNSIGNED NOT NULL,
            lat         DECIMAL(10,7)   NOT NULL,
            lng         DECIMAL(10,7)   NOT NULL,
            speed       SMALLINT UNSIGNED DEFAULT NULL,
            recorded_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_trip (trip_id, recorded_at)
        ) {$charset};";

        // ===== الحضور =====
        $sql[] = "CREATE TABLE {$p}attendance (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id   BIGINT UNSIGNED NOT NULL,
            class_id     BIGINT UNSIGNED DEFAULT NULL,
            att_date     DATE            NOT NULL,
            status       ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
            method       ENUM('qr','manual','transport') NOT NULL DEFAULT 'manual',
            note         VARCHAR(255)    DEFAULT NULL,
            recorded_by  BIGINT UNSIGNED DEFAULT NULL,
            recorded_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_day (student_id, att_date),
            KEY idx_class_date (class_id, att_date),
            KEY idx_status (status, att_date)
        ) {$charset};";

        // ===== الأكاديمي =====
        $sql[] = "CREATE TABLE {$p}subjects (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(120)    NOT NULL,
            code       VARCHAR(30)     DEFAULT NULL,
            stage      VARCHAR(30)     DEFAULT NULL,
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_code (code),
            KEY idx_stage (stage)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}class_subjects (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id        BIGINT UNSIGNED NOT NULL,
            subject_id      BIGINT UNSIGNED NOT NULL,
            teacher_user_id BIGINT UNSIGNED DEFAULT NULL,
            created_at      DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_cs (class_id, subject_id),
            KEY idx_teacher (teacher_user_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}timetable (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id        BIGINT UNSIGNED NOT NULL,
            subject_id      BIGINT UNSIGNED NOT NULL,
            teacher_user_id BIGINT UNSIGNED DEFAULT NULL,
            day_of_week     TINYINT UNSIGNED NOT NULL,
            period_no       TINYINT UNSIGNED NOT NULL,
            created_at      DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slot (class_id, day_of_week, period_no),
            KEY idx_teacher_slot (teacher_user_id, day_of_week, period_no)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}assignments (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id    BIGINT UNSIGNED NOT NULL,
            subject_id  BIGINT UNSIGNED NOT NULL,
            title       VARCHAR(190)    NOT NULL,
            description TEXT            DEFAULT NULL,
            due_date    DATE            DEFAULT NULL,
            max_score   DECIMAL(6,2)    NOT NULL DEFAULT 10,
            created_by  BIGINT UNSIGNED DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_class (class_id, due_date)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}exams (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id   BIGINT UNSIGNED NOT NULL,
            subject_id BIGINT UNSIGNED NOT NULL,
            title      VARCHAR(190)    NOT NULL,
            exam_type  ENUM('quiz','midterm','final','participation') NOT NULL DEFAULT 'quiz',
            exam_date  DATE            DEFAULT NULL,
            max_score  DECIMAL(6,2)    NOT NULL DEFAULT 100,
            weight     DECIMAL(5,2)    NOT NULL DEFAULT 100,
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_class (class_id, exam_date)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}exam_results (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exam_id     BIGINT UNSIGNED NOT NULL,
            student_id  BIGINT UNSIGNED NOT NULL,
            score       DECIMAL(6,2)    DEFAULT NULL,
            note        VARCHAR(255)    DEFAULT NULL,
            recorded_by BIGINT UNSIGNED DEFAULT NULL,
            recorded_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_result (exam_id, student_id),
            KEY idx_student (student_id)
        ) {$charset};";

        // ===== المالية =====
        $sql[] = "CREATE TABLE {$p}fee_plans (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            year_id      BIGINT UNSIGNED NOT NULL,
            name         VARCHAR(150)    NOT NULL,
            stage        VARCHAR(30)     DEFAULT NULL,
            amount       DECIMAL(10,2)   NOT NULL DEFAULT 0,
            installments TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at   DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_year (year_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}invoices (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id  BIGINT UNSIGNED NOT NULL,
            year_id     BIGINT UNSIGNED NOT NULL,
            plan_id     BIGINT UNSIGNED DEFAULT NULL,
            gross       DECIMAL(10,2)   NOT NULL DEFAULT 0,
            discount_pct DECIMAL(5,2)   NOT NULL DEFAULT 0,
            total       DECIMAL(10,2)   NOT NULL DEFAULT 0,
            discount    DECIMAL(10,2)   NOT NULL DEFAULT 0,
            paid        DECIMAL(10,2)   NOT NULL DEFAULT 0,
            pay_mode    ENUM('full','school','bnpl') NOT NULL DEFAULT 'full',
            grant_reason VARCHAR(190)   DEFAULT NULL,
            granted_by  BIGINT UNSIGNED DEFAULT NULL,
            status      ENUM('open','partial','paid','void') NOT NULL DEFAULT 'open',
            due_date    DATE            DEFAULT NULL,
            created_by  BIGINT UNSIGNED DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            updated_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_student (student_id, year_id),
            KEY idx_status (status, due_date)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}installments (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            seq        TINYINT UNSIGNED NOT NULL,
            amount     DECIMAL(10,2)   NOT NULL,
            due_date   DATE            DEFAULT NULL,
            paid       DECIMAL(10,2)   NOT NULL DEFAULT 0,
            status     ENUM('open','partial','paid') NOT NULL DEFAULT 'open',
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_seq (invoice_id, seq),
            KEY idx_due (due_date, status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}payments (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id     BIGINT UNSIGNED NOT NULL,
            installment_id BIGINT UNSIGNED DEFAULT NULL,
            amount         DECIMAL(10,2)   NOT NULL,
            method         ENUM('cash','transfer','pos','online') NOT NULL DEFAULT 'cash',
            reference      VARCHAR(120)    DEFAULT NULL,
            received_by    BIGINT UNSIGNED DEFAULT NULL,
            paid_at        DATETIME        NOT NULL,
            created_at     DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_invoice (invoice_id),
            KEY idx_paid (paid_at)
        ) {$charset};";

        // ===== التواصل =====
        $sql[] = "CREATE TABLE {$p}messages (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title         VARCHAR(190)    NOT NULL,
            body          TEXT            NOT NULL,
            audience      ENUM('all_guardians','class','student','staff') NOT NULL DEFAULT 'all_guardians',
            audience_id   BIGINT UNSIGNED DEFAULT NULL,
            created_by    BIGINT UNSIGNED DEFAULT NULL,
            created_at    DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_created (created_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}notifications (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT UNSIGNED NOT NULL,
            title      VARCHAR(190)    NOT NULL,
            body       TEXT            DEFAULT NULL,
            ref_type   VARCHAR(40)     DEFAULT NULL,
            ref_id     BIGINT UNSIGNED DEFAULT NULL,
            read_at    DATETIME        DEFAULT NULL,
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user (user_id, read_at),
            KEY idx_created (created_at)
        ) {$charset};";

        // ===== المحاسبة =====
        $sql[] = "CREATE TABLE {$p}accounts (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code       VARCHAR(20)     NOT NULL,
            name       VARCHAR(150)    NOT NULL,
            type       ENUM('asset','liability','equity','revenue','expense') NOT NULL,
            parent_id  BIGINT UNSIGNED DEFAULT NULL,
            is_system  TINYINT(1)      NOT NULL DEFAULT 0,
            status     ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_code (code),
            KEY idx_type (type, status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}journal_entries (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_no    VARCHAR(30)     NOT NULL,
            entry_date  DATE            NOT NULL,
            description VARCHAR(255)    NOT NULL,
            ref_type    VARCHAR(30)     DEFAULT NULL,
            ref_id      BIGINT UNSIGNED DEFAULT NULL,
            total       DECIMAL(14,2)   NOT NULL DEFAULT 0,
            status      ENUM('draft','posted') NOT NULL DEFAULT 'draft',
            created_by  BIGINT UNSIGNED DEFAULT NULL,
            posted_at   DATETIME        DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_no (entry_no),
            KEY idx_date (entry_date, status),
            KEY idx_ref (ref_type, ref_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}journal_lines (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id    BIGINT UNSIGNED NOT NULL,
            account_id  BIGINT UNSIGNED NOT NULL,
            debit       DECIMAL(14,2)   NOT NULL DEFAULT 0,
            credit      DECIMAL(14,2)   NOT NULL DEFAULT 0,
            description VARCHAR(255)    DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_entry (entry_id),
            KEY idx_account (account_id)
        ) {$charset};";

        // ===== الموارد البشرية =====
        $sql[] = "CREATE TABLE {$p}contracts (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       BIGINT UNSIGNED NOT NULL,
            contract_no   VARCHAR(40)     DEFAULT NULL,
            contract_type ENUM('full_time','part_time','temporary') NOT NULL DEFAULT 'full_time',
            start_date    DATE            NOT NULL,
            end_date      DATE            DEFAULT NULL,
            basic_salary  DECIMAL(10,2)   NOT NULL DEFAULT 0,
            allowances    DECIMAL(10,2)   NOT NULL DEFAULT 0,
            status        ENUM('active','expired','terminated') NOT NULL DEFAULT 'active',
            created_at    DATETIME        NOT NULL,
            updated_at    DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user (user_id, status),
            KEY idx_end (end_date, status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}leaves (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            leave_type  ENUM('annual','sick','emergency','unpaid') NOT NULL DEFAULT 'annual',
            start_date  DATE            NOT NULL,
            end_date    DATE            NOT NULL,
            days        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            reason      VARCHAR(255)    DEFAULT NULL,
            status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            decided_by  BIGINT UNSIGNED DEFAULT NULL,
            decided_at  DATETIME        DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user (user_id, status),
            KEY idx_dates (start_date, end_date)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}payroll_runs (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            period_year  SMALLINT UNSIGNED NOT NULL,
            period_month TINYINT UNSIGNED  NOT NULL,
            total        DECIMAL(14,2)   NOT NULL DEFAULT 0,
            headcount    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status       ENUM('draft','posted') NOT NULL DEFAULT 'draft',
            created_by   BIGINT UNSIGNED DEFAULT NULL,
            posted_at    DATETIME        DEFAULT NULL,
            created_at   DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_period (period_year, period_month)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}payslips (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_id     BIGINT UNSIGNED NOT NULL,
            user_id    BIGINT UNSIGNED NOT NULL,
            basic      DECIMAL(10,2)   NOT NULL DEFAULT 0,
            allowances DECIMAL(10,2)   NOT NULL DEFAULT 0,
            deductions DECIMAL(10,2)   NOT NULL DEFAULT 0,
            net        DECIMAL(10,2)   NOT NULL DEFAULT 0,
            note       VARCHAR(255)    DEFAULT NULL,
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slip (run_id, user_id),
            KEY idx_user (user_id)
        ) {$charset};";

        // ===== الخدمات المدرسية =====
        $sql[] = "CREATE TABLE {$p}clinic_visits (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id  BIGINT UNSIGNED NOT NULL,
            visit_at    DATETIME        NOT NULL,
            complaint   VARCHAR(255)    NOT NULL,
            action_note VARCHAR(255)    DEFAULT NULL,
            sent_home   TINYINT(1)      NOT NULL DEFAULT 0,
            recorded_by BIGINT UNSIGNED DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_student (student_id, visit_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}books (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title      VARCHAR(190)    NOT NULL,
            author     VARCHAR(150)    DEFAULT NULL,
            isbn       VARCHAR(20)     DEFAULT NULL,
            category   VARCHAR(80)     DEFAULT NULL,
            copies     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_title (title)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}book_loans (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            book_id     BIGINT UNSIGNED NOT NULL,
            student_id  BIGINT UNSIGNED NOT NULL,
            loaned_at   DATE            NOT NULL,
            due_date    DATE            NOT NULL,
            returned_at DATE            DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_book (book_id, returned_at),
            KEY idx_due (due_date, returned_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}visitors (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name    VARCHAR(190)    NOT NULL,
            national_id  VARCHAR(20)     DEFAULT NULL,
            phone        VARCHAR(30)     DEFAULT NULL,
            purpose      VARCHAR(190)    DEFAULT NULL,
            host_user_id BIGINT UNSIGNED DEFAULT NULL,
            student_id   BIGINT UNSIGNED DEFAULT NULL,
            checked_in   DATETIME        NOT NULL,
            checked_out  DATETIME        DEFAULT NULL,
            created_at   DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_in (checked_in, checked_out)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}incidents (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            incident_type ENUM('safety','injury','drill','security','other') NOT NULL DEFAULT 'other',
            title         VARCHAR(190)    NOT NULL,
            description   TEXT            DEFAULT NULL,
            severity      ENUM('low','medium','high') NOT NULL DEFAULT 'low',
            occurred_at   DATETIME        NOT NULL,
            status        ENUM('open','closed') NOT NULL DEFAULT 'open',
            reported_by   BIGINT UNSIGNED DEFAULT NULL,
            created_at    DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status, occurred_at)
        ) {$charset};";

        // ===== الأصول والمستودع =====
        $sql[] = "CREATE TABLE {$p}assets (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            asset_code    VARCHAR(40)     NOT NULL,
            name          VARCHAR(190)    NOT NULL,
            category      VARCHAR(80)     DEFAULT NULL,
            location      VARCHAR(120)    DEFAULT NULL,
            purchase_date DATE            DEFAULT NULL,
            cost          DECIMAL(12,2)   NOT NULL DEFAULT 0,
            status        ENUM('in_use','storage','maintenance','disposed') NOT NULL DEFAULT 'in_use',
            created_at    DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_code (asset_code),
            KEY idx_status (status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}maintenance (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            asset_id    BIGINT UNSIGNED DEFAULT NULL,
            title       VARCHAR(190)    NOT NULL,
            description TEXT            DEFAULT NULL,
            location    VARCHAR(120)    DEFAULT NULL,
            priority    ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
            status      ENUM('open','in_progress','done') NOT NULL DEFAULT 'open',
            reported_by BIGINT UNSIGNED DEFAULT NULL,
            assigned_to BIGINT UNSIGNED DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            closed_at   DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status, priority)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}inventory (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sku        VARCHAR(40)     DEFAULT NULL,
            name       VARCHAR(190)    NOT NULL,
            unit       VARCHAR(30)     DEFAULT NULL,
            qty        DECIMAL(12,2)   NOT NULL DEFAULT 0,
            min_qty    DECIMAL(12,2)   NOT NULL DEFAULT 0,
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sku (sku),
            KEY idx_name (name)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}stock_moves (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_id    BIGINT UNSIGNED NOT NULL,
            move_type  ENUM('in','out','adjust') NOT NULL,
            qty        DECIMAL(12,2)   NOT NULL,
            note       VARCHAR(255)    DEFAULT NULL,
            moved_by   BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_item (item_id, created_at)
        ) {$charset};";

        // ===== رياض الأطفال =====
        $sql[] = "CREATE TABLE {$p}kg_logs (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id   BIGINT UNSIGNED NOT NULL,
            log_date     DATE            NOT NULL,
            mood         ENUM('happy','calm','tired','upset') DEFAULT NULL,
            meal         ENUM('all','most','some','none') DEFAULT NULL,
            nap_minutes  SMALLINT UNSIGNED DEFAULT NULL,
            activities   VARCHAR(255)    DEFAULT NULL,
            note         TEXT            DEFAULT NULL,
            recorded_by  BIGINT UNSIGNED DEFAULT NULL,
            created_at   DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_day (student_id, log_date),
            KEY idx_date (log_date)
        ) {$charset};";

        // مستندات الطالب — تُخزَّن خارج المجلد العام وتُخدَم بمسار محمي.
        $sql[] = "CREATE TABLE {$p}student_docs (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id  BIGINT UNSIGNED NOT NULL,
            doc_type    ENUM('id','birth','transcript','photo','other') NOT NULL DEFAULT 'other',
            file_name   VARCHAR(190)    NOT NULL,
            stored_as   VARCHAR(190)    NOT NULL,
            mime_type   VARCHAR(80)     DEFAULT NULL,
            file_size   INT UNSIGNED    NOT NULL DEFAULT 0,
            uploaded_by BIGINT UNSIGNED DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_stored (stored_as),
            KEY idx_student (student_id, doc_type)
        ) {$charset};";

        // السجل الصحي — جدول منفصل لأن صلاحيته أضيق من بقية بيانات الطالب.
        $sql[] = "CREATE TABLE {$p}student_health (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id    BIGINT UNSIGNED NOT NULL,
            blood_type    VARCHAR(8)      DEFAULT NULL,
            chronic       VARCHAR(255)    DEFAULT NULL,
            allergies     VARCHAR(255)    DEFAULT NULL,
            special_needs VARCHAR(255)    DEFAULT NULL,
            notes         TEXT            DEFAULT NULL,
            updated_by    BIGINT UNSIGNED DEFAULT NULL,
            updated_at    DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_student (student_id)
        ) {$charset};";

        // ===== سلسلة العهدة =====
        // سجل غير قابل للتعديل: الخطأ يُصحَّح بحدث مضاد لا بمسح السجل.
        $sql[] = "CREATE TABLE {$p}custody_events (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id        BIGINT UNSIGNED NOT NULL,
            from_state        VARCHAR(20)     DEFAULT NULL,
            to_state          VARCHAR(20)     NOT NULL,
            checkpoint        ENUM('bus_board','gate_in','gate_out','early_out','bus_alight','manual','correction') NOT NULL,
            actor_user_id     BIGINT UNSIGNED DEFAULT NULL,
            receiver_name     VARCHAR(190)    DEFAULT NULL,
            receiver_relation VARCHAR(60)     DEFAULT NULL,
            reason            VARCHAR(255)    DEFAULT NULL,
            lat               DECIMAL(10,7)   DEFAULT NULL,
            lng               DECIMAL(10,7)   DEFAULT NULL,
            client_uuid       CHAR(36)        NOT NULL,
            occurred_at       DATETIME        NOT NULL,
            created_at        DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_client (client_uuid),
            KEY idx_student (student_id, occurred_at),
            KEY idx_day (occurred_at),
            KEY idx_checkpoint (checkpoint, occurred_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}alerts (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_code    VARCHAR(40)     NOT NULL,
            severity     ENUM('medium','high','critical') NOT NULL DEFAULT 'medium',
            student_id   BIGINT UNSIGNED DEFAULT NULL,
            trip_id      BIGINT UNSIGNED DEFAULT NULL,
            title        VARCHAR(190)    NOT NULL,
            detail       VARCHAR(500)    DEFAULT NULL,
            status       ENUM('open','ack','closed') NOT NULL DEFAULT 'open',
            day_key      DATE            NOT NULL,
            opened_at    DATETIME        NOT NULL,
            ack_by       BIGINT UNSIGNED DEFAULT NULL,
            ack_at       DATETIME        DEFAULT NULL,
            closed_by    BIGINT UNSIGNED DEFAULT NULL,
            closed_at    DATETIME        DEFAULT NULL,
            close_reason VARCHAR(255)    DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_rule_day (rule_code, student_id, day_key),
            KEY idx_status (status, severity, opened_at)
        ) {$charset};";

        // الملاحظات والإحالات — جدول واحد: كلاهما مشاهدة تحتاج مسارًا ودورة حياة.
        $sql[] = "CREATE TABLE {$p}student_notes (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id     BIGINT UNSIGNED NOT NULL,
            category       ENUM('positive','participation','followup','health') NOT NULL DEFAULT 'positive',
            route          ENUM('none','specialist','clinic') NOT NULL DEFAULT 'none',
            body           VARCHAR(500)    DEFAULT NULL,
            author_user_id BIGINT UNSIGNED DEFAULT NULL,
            status         ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
            assigned_to    BIGINT UNSIGNED DEFAULT NULL,
            opened_at      DATETIME        DEFAULT NULL,
            closed_by      BIGINT UNSIGNED DEFAULT NULL,
            closed_at      DATETIME        DEFAULT NULL,
            resolution     VARCHAR(500)    DEFAULT NULL,
            escalated_at   DATETIME        DEFAULT NULL,
            recheck_at     DATETIME        DEFAULT NULL,
            recheck_result ENUM('improved','same','worse') DEFAULT NULL,
            parent_response ENUM('knows','unknown','contact_me') DEFAULT NULL,
            parent_body    VARCHAR(500)    DEFAULT NULL,
            responded_at   DATETIME        DEFAULT NULL,
            created_at     DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_student (student_id, created_at),
            KEY idx_queue (route, status, created_at),
            KEY idx_recheck (recheck_at, recheck_result)
        ) {$charset};";

        // ===== الوكيل =====
        $sql[] = "CREATE TABLE {$p}staff_attendance (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            att_date    DATE            NOT NULL,
            status      ENUM('present','late','absent','leave') NOT NULL DEFAULT 'present',
            checked_at  DATETIME        DEFAULT NULL,
            note        VARCHAR(255)    DEFAULT NULL,
            recorded_by BIGINT UNSIGNED DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_day (user_id, att_date),
            KEY idx_date (att_date, status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}substitutions (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sub_date             DATE            NOT NULL,
            class_id             BIGINT UNSIGNED NOT NULL,
            period_no            TINYINT UNSIGNED NOT NULL,
            subject_id           BIGINT UNSIGNED DEFAULT NULL,
            original_teacher_id  BIGINT UNSIGNED DEFAULT NULL,
            substitute_teacher_id BIGINT UNSIGNED DEFAULT NULL,
            reason               ENUM('leave','absence','task') NOT NULL DEFAULT 'leave',
            status               ENUM('proposed','assigned','done') NOT NULL DEFAULT 'proposed',
            assigned_by          BIGINT UNSIGNED DEFAULT NULL,
            created_at           DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slot (sub_date, class_id, period_no),
            KEY idx_sub (substitute_teacher_id, sub_date),
            KEY idx_status (status, sub_date)
        ) {$charset};";

        // ===== الذاكرة عبر السنوات =====
        $sql[] = "CREATE TABLE {$p}year_summary (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id     BIGINT UNSIGNED NOT NULL,
            year_id        BIGINT UNSIGNED NOT NULL,
            works          VARCHAR(300)    DEFAULT NULL,
            avoid          VARCHAR(300)    DEFAULT NULL,
            notes          VARCHAR(300)    DEFAULT NULL,
            author_user_id BIGINT UNSIGNED DEFAULT NULL,
            approved_by    BIGINT UNSIGNED DEFAULT NULL,
            approved_at    DATETIME        DEFAULT NULL,
            created_at     DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_year (student_id, year_id),
            KEY idx_year (year_id, approved_at)
        ) {$charset};";

        // ===== أدوية الطلاب =====
        // لا يُعطى الطفل دواء بلا إذن مكتوب من وليّه — يحمي الطفل والمدرسة معًا.
        $sql[] = "CREATE TABLE {$p}medications (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id   BIGINT UNSIGNED NOT NULL,
            med_name     VARCHAR(150)    NOT NULL,
            dose         VARCHAR(60)     NOT NULL,
            times_csv    VARCHAR(120)    NOT NULL,
            start_date   DATE            NOT NULL,
            end_date     DATE            NOT NULL,
            note         VARCHAR(255)    DEFAULT NULL,
            file_stored  VARCHAR(190)    DEFAULT NULL,
            status       ENUM('pending','active','ended','rejected') NOT NULL DEFAULT 'pending',
            created_by   BIGINT UNSIGNED DEFAULT NULL,
            approved_by  BIGINT UNSIGNED DEFAULT NULL,
            approved_at  DATETIME        DEFAULT NULL,
            created_at   DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_student (student_id, status),
            KEY idx_window (status, start_date, end_date)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}med_doses (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            med_id     BIGINT UNSIGNED NOT NULL,
            due_date   DATE            NOT NULL,
            due_time   VARCHAR(5)      NOT NULL,
            given_at   DATETIME        DEFAULT NULL,
            given_by   BIGINT UNSIGNED DEFAULT NULL,
            note       VARCHAR(255)    DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dose (med_id, due_date, due_time),
            KEY idx_due (due_date, given_at)
        ) {$charset};";

        // ===== إجازات الطلاب =====
        $sql[] = "CREATE TABLE {$p}student_leaves (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id  BIGINT UNSIGNED NOT NULL,
            reason_code ENUM('sick','appointment','travel','family','social','other') NOT NULL DEFAULT 'sick',
            start_date  DATE            NOT NULL,
            end_date    DATE            NOT NULL,
            days        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            note        VARCHAR(255)    DEFAULT NULL,
            file_stored VARCHAR(190)    DEFAULT NULL,
            status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            requested_by BIGINT UNSIGNED DEFAULT NULL,
            decided_by  BIGINT UNSIGNED DEFAULT NULL,
            decided_at  DATETIME        DEFAULT NULL,
            decision_note VARCHAR(255)  DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_student (student_id, status),
            KEY idx_window (status, start_date, end_date)
        ) {$charset};";

        // ===== المحتوى التعليمي =====
        // الكتب والفيديوهات والألعاب. اللعبة تُخزَّن ككود ويُشغَّل في صندوق معزول.
        // (أُزيل هنا تعريف مكرَّر قديم لأربعة جداول — content · content_opens ·
        //  homework · homework_subs — كان dbDelta يدمجه مع التعريف الصحيح في
        //  اتحاد مكسور فيفشل الإدخال. الكود يستخدم النسخة أدناه: opened_at ·
        //  teacher_id · answer_type · answer_text.)
        $sql[] = "CREATE TABLE {$p}content (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_type ENUM('book','video','game','link') NOT NULL DEFAULT 'book',
            title        VARCHAR(190)    NOT NULL,
            description  VARCHAR(500)    DEFAULT NULL,
            stage        ENUM('kg','primary','middle','high') NOT NULL DEFAULT 'primary',
            grade_level  VARCHAR(40)     DEFAULT NULL,
            subject_id   BIGINT UNSIGNED DEFAULT NULL,
            lesson_ref   VARCHAR(120)    DEFAULT NULL,
            media_url    VARCHAR(500)    DEFAULT NULL,
            file_stored  VARCHAR(190)    DEFAULT NULL,
            game_code    LONGTEXT        DEFAULT NULL,
            status       ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            opens        INT UNSIGNED    NOT NULL DEFAULT 0,
            review_at    DATE            DEFAULT NULL,
            created_by   BIGINT UNSIGNED DEFAULT NULL,
            created_at   DATETIME        NOT NULL,
            updated_at   DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_browse (stage, status, content_type),
            KEY idx_subject (subject_id, status),
            KEY idx_review (review_at, status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}content_opens (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            score      SMALLINT UNSIGNED DEFAULT NULL,
            seconds    INT UNSIGNED    NOT NULL DEFAULT 0,
            opened_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_content (content_id, opened_at),
            KEY idx_student (student_id, opened_at)
        ) {$charset};";

        // ===== الواجبات =====
        // تختفي من «واجباتي» آخر الأسبوع، ولا تُحذف — الغائب يحتاجها والمعلم يحتاج سجلها.
        $sql[] = "CREATE TABLE {$p}homework (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id    BIGINT UNSIGNED NOT NULL,
            subject_id  BIGINT UNSIGNED DEFAULT NULL,
            teacher_id  BIGINT UNSIGNED DEFAULT NULL,
            title       VARCHAR(190)    NOT NULL,
            body        TEXT            DEFAULT NULL,
            answer_type ENUM('text','drawing','file','none') NOT NULL DEFAULT 'text',
            file_stored VARCHAR(190)    DEFAULT NULL,
            content_id  BIGINT UNSIGNED DEFAULT NULL,
            week_key    VARCHAR(10)     NOT NULL,
            due_date    DATE            NOT NULL,
            taught_on   DATE            DEFAULT NULL,
            status      ENUM('open','closed') NOT NULL DEFAULT 'open',
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_class (class_id, week_key),
            KEY idx_due (due_date, status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}homework_subs (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            homework_id  BIGINT UNSIGNED NOT NULL,
            student_id   BIGINT UNSIGNED NOT NULL,
            answer_text  TEXT            DEFAULT NULL,
            file_stored  VARCHAR(190)    DEFAULT NULL,
            submitted_at DATETIME        NOT NULL,
            score        DECIMAL(5,2)    DEFAULT NULL,
            feedback     VARCHAR(500)    DEFAULT NULL,
            graded_by    BIGINT UNSIGNED DEFAULT NULL,
            graded_at    DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sub (homework_id, student_id),
            KEY idx_student (student_id, submitted_at)
        ) {$charset};";

        // ===== الصلاحيات لكل موظف =====
        // الدور قالب لا قيد: من ليس له صف هنا يعمل بصلاحيات دوره كما كان.
        $sql[] = "CREATE TABLE {$p}user_perms (
            id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id  BIGINT UNSIGNED NOT NULL,
            section  VARCHAR(40)     NOT NULL,
            can_view TINYINT(1)      NOT NULL DEFAULT 1,
            can_edit TINYINT(1)      NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_perm (user_id, section)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}user_scope (
            user_id    BIGINT UNSIGNED NOT NULL,
            scope      ENUM('all','stage','own') NOT NULL DEFAULT 'all',
            expires_at DATE            DEFAULT NULL,
            updated_by BIGINT UNSIGNED DEFAULT NULL,
            updated_at DATETIME        DEFAULT NULL,
            PRIMARY KEY (user_id),
            KEY idx_expiry (expires_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}perm_log (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            target_user BIGINT UNSIGNED NOT NULL,
            actor_user  BIGINT UNSIGNED DEFAULT NULL,
            granted     VARCHAR(500)    DEFAULT NULL,
            revoked     VARCHAR(500)    DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_target (target_user, id)
        ) {$charset};";

        // ===== قائمة المتابعة =====
        // نجمة يضعها موظف على طالب ليتابعه، وجرس يُنبّهه عند أي حدث له.
        $sql[] = "CREATE TABLE {$p}watchlist (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            notify     TINYINT(1)      NOT NULL DEFAULT 0,
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_watch (user_id, student_id),
            KEY idx_student (student_id, notify)
        ) {$charset};";

        // ===== علامات سير العمل =====
        // للخطوات اليدوية («تم») ولخيار «لا ينطبق» الذي بدونه لا يصل العدّاد صفرًا.
        $sql[] = "CREATE TABLE {$p}flow_marks (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            flow       VARCHAR(24)     NOT NULL,
            entity_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
            step       VARCHAR(32)     NOT NULL,
            state      ENUM('done','na') NOT NULL DEFAULT 'done',
            user_id    BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_mark (flow, entity_id, step)
        ) {$charset};";

        // ===== الشهادات =====
        // وثيقة تُعلَّق على الجدار وتُصوَّر — فرقمها التسلسلي لا يتكرر داخل السنة.
        $sql[] = "CREATE TABLE {$p}certificates (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            year_id    BIGINT UNSIGNED NOT NULL,
            type       VARCHAR(24)     NOT NULL,
            template   VARCHAR(16)     NOT NULL DEFAULT 'royal',
            title      VARCHAR(120)    NOT NULL,
            reason     VARCHAR(255)    DEFAULT NULL,
            serial     VARCHAR(32)     NOT NULL,
            issued_by  BIGINT UNSIGNED DEFAULT NULL,
            issued_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_serial (serial),
            KEY idx_student (student_id, id),
            KEY idx_year (year_id, type)
        ) {$charset};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        self::add_foreign_keys();
        self::backfill_numbers();
    }

    /** ملء المعرّفات للسجلات التي أُنشئت قبل إضافة الترقيم. */
    private static function backfill_numbers(): void
    {
        global $wpdb;

        if (!class_exists('SCH_Enrollment')) {
            return;
        }

        $guardians = $wpdb->get_col(
            'SELECT id FROM ' . sch_table('guardians') . " WHERE parent_no IS NULL OR parent_no = '' ORDER BY id"
        ) ?: [];

        foreach ($guardians as $id) {
            $wpdb->update(
                sch_table('guardians'),
                ['parent_no' => SCH_Enrollment::next_parent_no()],
                ['id' => (int) $id]
            );
        }

        $badgeless = $wpdb->get_col(
            'SELECT id FROM ' . sch_table('students') . " WHERE badge_token IS NULL OR badge_token = ''"
        ) ?: [];

        foreach ($badgeless as $id) {
            $wpdb->update(sch_table('students'), ['badge_token' => sch_generate_qr_token()], ['id' => (int) $id]);
        }

        $students = $wpdb->get_col(
            'SELECT id FROM ' . sch_table('students') . " WHERE academic_no IS NULL OR academic_no = '' ORDER BY id"
        ) ?: [];

        foreach ($students as $id) {
            $academic = SCH_Enrollment::next_academic_no();
            $wpdb->update(sch_table('students'), [
                'academic_no' => $academic,
                'student_no'  => SCH_Enrollment::student_no($academic),
            ], ['id' => (int) $id]);
        }
    }

    /**
     * المفاتيح الأجنبية.
     * dbDelta لا يدعم قيود FOREIGN KEY، فتُضاف هنا بعد إنشاء الجداول.
     * تُنفَّذ فقط على InnoDB، وتتجاهل الخطأ إن كان القيد موجودًا مسبقًا.
     */
    private static function add_foreign_keys(): void
    {
        global $wpdb;

        $p = $wpdb->prefix . 'sch_';

        $constraints = [
            ['custody_events',  'fk_custody_student', 'student_id',  'students'],
            ['student_docs',    'fk_docs_student',    "student_id",  'students'],
            ['student_health',  'fk_health_student',  'student_id',  'students'],
            ['enrollments',     'fk_enroll_student',  'student_id',  'students'],
            ['enrollments',     'fk_enroll_class',    'class_id',    'classes'],
            ['guardian_student','fk_gs_student',      'student_id',  'students'],
            ['transport_subs',  'fk_sub_student',     'student_id',  'students'],
            ['transport_subs',  'fk_sub_route',       'route_id',    'routes'],
            ['route_stops',     'fk_stop_route',      'route_id',    'routes'],
            ['trip_events',     'fk_event_trip',      'trip_id',     'trips'],
            ['journal_lines',   'fk_line_entry',      'entry_id',    'journal_entries'],
            ['journal_lines',   'fk_line_account',    'account_id',  'accounts'],
            ['installments',    'fk_inst_invoice',    'invoice_id',  'invoices'],
            ['payments',        'fk_pay_invoice',     'invoice_id',  'invoices'],
            ['payslips',        'fk_slip_run',        'run_id',      'payroll_runs'],
            ['book_loans',      'fk_loan_book',       'book_id',     'books'],
        ];

        foreach ($constraints as [$table, $name, $column, $parent]) {
            $full   = $p . $table;
            $engine = $wpdb->get_var($wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $full
            ));

            if ($engine !== 'InnoDB') {
                continue;
            }

            $exists = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s',
                $full,
                $name
            ));

            if ($exists > 0) {
                continue;
            }

            $wpdb->hide_errors();
            $wpdb->query(
                "ALTER TABLE `{$full}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) "
                . "REFERENCES `{$p}{$parent}` (`id`) ON DELETE CASCADE"
            );
            $wpdb->show_errors();
        }
    }

    /**
     * الأدوار الثمانية. الصلاحيات تُفحص في الخادم لا في الواجهة.
     */
    private static function register_roles(): void
    {
        $roles = [
            'sch_teacher' => [
                'label' => __('معلم', 'school-system'),
                'caps'  => ['read', 'sch_view_students', 'sch_manage_attendance', 'sch_manage_grades', 'sch_manage_exams', 'sch_send_messages', 'sch_manage_kg', 'sch_write_notes', 'sch_manage_homework', 'sch_manage_content'],
            ],
            'sch_accountant' => [
                'label' => __('محاسب', 'school-system'),
                'caps'  => ['read', 'sch_view_students', 'sch_manage_finance', 'sch_manage_accounting'],
            ],
            'sch_transport_supervisor' => [
                'label' => __('مشرف نقل', 'school-system'),
                'caps'  => ['read', 'sch_view_students', 'sch_manage_transport', 'sch_send_messages', 'sch_view_custody'],
            ],
            'sch_driver' => [
                'label' => __('سائق', 'school-system'),
                'caps'  => ['read', 'sch_drive_trip'],
            ],
            'sch_guardian' => [
                'label' => __('ولي أمر', 'school-system'),
                'caps'  => ['read', 'sch_view_own_children'],
            ],
            'sch_student' => [
                'label' => __('طالب', 'school-system'),
                'caps'  => ['read', 'sch_view_own_record', 'sch_student_app', 'sch_learn'],
            ],
            'sch_principal' => [
                'label' => __('مدير المدرسة', 'school-system'),
                'caps'  => ['sch_grant_installments', 'read', 'sch_view_students', 'sch_view_custody', 'sch_manage_alerts',
                            'sch_view_insights', 'sch_view_audit', 'sch_approve_timetable',
                            'sch_manage_staff', 'sch_manage_settings'],
            ],
            'sch_supervisor' => [
                'label' => __('مشرف مرحلة', 'school-system'),
                'caps'  => ['read', 'sch_view_students', 'sch_manage_attendance', 'sch_manage_classes',
                            'sch_manage_staff', 'sch_supervise_stage', 'sch_view_custody',
                            'sch_manage_alerts', 'sch_send_messages', 'sch_handle_notes'],
            ],
            'sch_deputy' => [
                'label' => __('وكيل الشؤون التعليمية', 'school-system'),
                'caps'  => ['read', 'sch_view_students', 'sch_manage_classes', 'sch_manage_subjects',
                            'sch_manage_exams', 'sch_manage_attendance', 'sch_view_custody',
                            'sch_manage_alerts', 'sch_send_messages', 'sch_view_insights',
                            'sch_manage_deputy', 'sch_grant_installments', 'sch_handle_notes', 'sch_write_notes', 'sch_decide_leaves',
                            'sch_build_timetable', 'sch_manage_staff'],
            ],
            'sch_content' => [
                'label' => __('منسق المحتوى التعليمي', 'school-system'),
                'caps'  => ['read', 'sch_manage_content'],
            ],
            'sch_counselor' => [
                'label' => __('أخصائي اجتماعي', 'school-system'),
                'caps'  => ['read', 'sch_view_students', 'sch_write_notes', 'sch_handle_notes',
                            'sch_send_messages', 'sch_view_insights'],
            ],
            'sch_guard' => [
                'label' => __('حارس البوابة', 'school-system'),
                'caps'  => ['read', 'sch_scan_gate'],
            ],
            'sch_staff' => [
                'label' => __('موظف إداري', 'school-system'),
                'caps'  => ['read', 'sch_view_students', 'sch_manage_students', 'sch_manage_guardians', 'sch_manage_services', 'sch_view_health', 'sch_manage_docs', 'sch_handle_notes', 'sch_manage_meds'],
            ],
        ];

        foreach ($roles as $slug => $data) {
            remove_role($slug); // إعادة التسجيل تضمن سريان أي صلاحية جديدة
            add_role($slug, $data['label'], array_fill_keys($data['caps'], true));
        }

        // المدير يملك كل صلاحيات النظام.
        $admin = get_role('administrator');
        if ($admin instanceof WP_Role) {
            foreach ([
                'sch_view_students', 'sch_manage_students', 'sch_manage_attendance',
                'sch_manage_grades', 'sch_manage_finance', 'sch_manage_transport',
                'sch_drive_trip', 'sch_manage_settings', 'sch_manage_staff',
                'sch_manage_guardians', 'sch_manage_classes', 'sch_view_audit',
                'sch_manage_subjects', 'sch_manage_exams', 'sch_send_messages', 'sch_manage_accounting',
                'sch_manage_hr', 'sch_manage_services', 'sch_manage_assets', 'sch_manage_kg', 'sch_view_insights',
                'sch_view_health', 'sch_manage_docs', 'sch_scan_gate', 'sch_view_custody', 'sch_manage_alerts',
                'sch_write_notes', 'sch_handle_notes', 'sch_manage_deputy', 'sch_grant_installments', 'sch_manage_meds', 'sch_decide_leaves', 'sch_supervise_stage', 'sch_approve_timetable', 'sch_build_timetable',
                'sch_manage_content', 'sch_manage_homework', 'sch_learn',
                'sch_manage_content', 'sch_manage_homework', 'sch_student_app',
            ] as $cap) {
                $admin->add_cap($cap);
            }
        }
    }
}
