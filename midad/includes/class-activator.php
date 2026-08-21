<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الجداول والأدوار والبذرة.
 *
 * **وكل جدولٍ يحمل `tenant_id` وفهرسًا عليه** إلّا ثلاثة: `roles` و`plans`
 * و`templates` — فهي مكتبة المنصّة لا بيانات مشترك. والفهرس ليس تحسينًا:
 * كل استعلامٍ في مداد يبدأ بـ`WHERE tenant_id = …`، فبلا فهرسٍ يمسح
 * الجدول كلَّه لكل مشتركٍ يفتح شاشة.
 */
final class MDD_Activator
{
    public static function activate(): void
    {
        self::create_tables();
        self::register_roles();
        self::seed();

        update_option('mdd_db_version', MDD_VERSION);
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules(false);
    }

    /** الترقية تُنادى مع كل إقلاع — والمقارنة رخيصة والترحيل لا. */
    public static function maybe_upgrade(): void
    {
        if (get_option('mdd_db_version') === MDD_VERSION) {
            return;
        }

        self::create_tables();
        self::register_roles();
        self::seed();

        update_option('mdd_db_version', MDD_VERSION);
    }

    // ═══════════════════════════ الجداول ═══════════════════════════

    private static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $p       = $wpdb->prefix . 'mdd_';
        $charset = $wpdb->get_charset_collate() . ' ENGINE=InnoDB';
        $sql     = [];

        // ── المشترك والناس ──

        $sql[] = "CREATE TABLE {$p}tenants (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type                 VARCHAR(20)     NOT NULL DEFAULT 'teacher',
            name                 VARCHAR(190)    NOT NULL,
            phone                VARCHAR(20)     DEFAULT NULL,
            email                VARCHAR(190)    DEFAULT NULL,
            status               VARCHAR(20)     NOT NULL DEFAULT 'trial',
            trial_ends_at        DATETIME        DEFAULT NULL,
            subscription_ends_at DATETIME        DEFAULT NULL,
            created_at           DATETIME        NOT NULL,
            updated_at           DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_type (type),
            KEY idx_trial (trial_ends_at),
            KEY idx_subscription (subscription_ends_at)
        ) {$charset};";

        // مستخدمٌ واحد لمشتركٍ واحد: `uniq_user` تمنع أن يظهر شخصٌ في
        // مدرستين — فتصير قراءة نطاقه رهينة ترتيب الصفوف.
        $sql[] = "CREATE TABLE {$p}members (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id  BIGINT UNSIGNED NOT NULL,
            user_id    BIGINT UNSIGNED NOT NULL,
            role       VARCHAR(40)     NOT NULL DEFAULT 'teacher',
            is_owner   TINYINT(1)      NOT NULL DEFAULT 0,
            status     VARCHAR(20)     NOT NULL DEFAULT 'active',
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user (user_id),
            KEY idx_tenant (tenant_id, status),
            KEY idx_role (tenant_id, role)
        ) {$charset};";

        // ── مكتبة المنصّة: بلا مستأجر ──

        $sql[] = "CREATE TABLE {$p}roles (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug       VARCHAR(40)     NOT NULL,
            label      VARCHAR(120)    NOT NULL,
            sort       SMALLINT UNSIGNED NOT NULL DEFAULT 50,
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slug (slug),
            KEY idx_sort (sort)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}plans (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug       VARCHAR(40)     NOT NULL,
            type       VARCHAR(20)     NOT NULL DEFAULT 'teacher',
            name       VARCHAR(150)    NOT NULL,
            price      DECIMAL(10,2)   NOT NULL DEFAULT 0,
            months     SMALLINT UNSIGNED NOT NULL DEFAULT 12,
            features   TEXT            DEFAULT NULL,
            is_default TINYINT(1)      NOT NULL DEFAULT 0,
            status     VARCHAR(20)     NOT NULL DEFAULT 'active',
            sort       SMALLINT UNSIGNED NOT NULL DEFAULT 50,
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slug (slug),
            KEY idx_type (type, status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}templates (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug       VARCHAR(80)     NOT NULL,
            role       VARCHAR(40)     NOT NULL DEFAULT 'general',
            title      VARCHAR(190)    NOT NULL,
            summary    VARCHAR(255)    DEFAULT NULL,
            fields     LONGTEXT        DEFAULT NULL,
            body       LONGTEXT        DEFAULT NULL,
            version    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            status     VARCHAR(20)     NOT NULL DEFAULT 'draft',
            sort       SMALLINT UNSIGNED NOT NULL DEFAULT 50,
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slug (slug),
            KEY idx_role (role, status, sort)
        ) {$charset};";

        // ── المال ──

        $sql[] = "CREATE TABLE {$p}subscriptions (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id  BIGINT UNSIGNED NOT NULL,
            plan_id    BIGINT UNSIGNED NOT NULL,
            status     VARCHAR(20)     NOT NULL DEFAULT 'pending',
            starts_at  DATETIME        DEFAULT NULL,
            ends_at    DATETIME        DEFAULT NULL,
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_tenant (tenant_id, status),
            KEY idx_ends (ends_at)
        ) {$charset};";

        // الضريبة عمودان من اليوم بنسبة صفر: إضافتهما بعد مئة فاتورة تعني
        // ترحيلًا لفواتيرَ صدرت، ووثيقةٌ مالية لا تُعدَّل بأثرٍ رجعيّ.
        $sql[] = "CREATE TABLE {$p}invoices (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id   BIGINT UNSIGNED NOT NULL,
            number      VARCHAR(40)     NOT NULL,
            plan_id     BIGINT UNSIGNED DEFAULT NULL,
            subtotal    DECIMAL(10,2)   NOT NULL DEFAULT 0,
            vat_rate    DECIMAL(5,2)    NOT NULL DEFAULT 0,
            vat_amount  DECIMAL(10,2)   NOT NULL DEFAULT 0,
            total       DECIMAL(10,2)   NOT NULL DEFAULT 0,
            status      VARCHAR(20)     NOT NULL DEFAULT 'unpaid',
            paid_at     DATETIME        DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            updated_at  DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_number (number),
            KEY idx_tenant (tenant_id, status)
        ) {$charset};";

        // `client_uuid` فريدٌ: ضغطتان على «ادفع» أو إعادةُ إرسال من ميسر
        // لا تُنشئان دفعتين — وهي قاعدة أُصول نفسها في المسح والفواتير.
        $sql[] = "CREATE TABLE {$p}payments (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id   BIGINT UNSIGNED NOT NULL,
            invoice_id  BIGINT UNSIGNED DEFAULT NULL,
            client_uuid VARCHAR(64)     NOT NULL,
            gateway     VARCHAR(20)     NOT NULL DEFAULT 'moyasar',
            gateway_id  VARCHAR(120)    DEFAULT NULL,
            amount      DECIMAL(10,2)   NOT NULL DEFAULT 0,
            status      VARCHAR(20)     NOT NULL DEFAULT 'initiated',
            raw         LONGTEXT        DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            updated_at  DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_client (client_uuid),
            KEY idx_gateway (gateway_id),
            KEY idx_tenant (tenant_id, status)
        ) {$charset};";

        // ── القوالب المملوءة ──

        $sql[] = "CREATE TABLE {$p}documents (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id   BIGINT UNSIGNED NOT NULL,
            template_id BIGINT UNSIGNED NOT NULL,
            member_id   BIGINT UNSIGNED DEFAULT NULL,
            title       VARCHAR(190)    NOT NULL,
            values_json LONGTEXT        DEFAULT NULL,
            status      VARCHAR(20)     NOT NULL DEFAULT 'draft',
            created_at  DATETIME        NOT NULL,
            updated_at  DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_tenant (tenant_id, updated_at),
            KEY idx_template (tenant_id, template_id)
        ) {$charset};";

        // ── نور: جداولُ بأعمدتها كما قُرئت ──

        $sql[] = "CREATE TABLE {$p}sheets (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id   BIGINT UNSIGNED NOT NULL,
            title       VARCHAR(190)    NOT NULL,
            source      VARCHAR(40)     NOT NULL DEFAULT 'noor',
            columns_json LONGTEXT       DEFAULT NULL,
            rows_count  INT UNSIGNED    NOT NULL DEFAULT 0,
            created_by  BIGINT UNSIGNED DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            updated_at  DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_tenant (tenant_id, created_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}sheet_rows (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id  BIGINT UNSIGNED NOT NULL,
            sheet_id   BIGINT UNSIGNED NOT NULL,
            idx        INT UNSIGNED    NOT NULL DEFAULT 0,
            cells      LONGTEXT        DEFAULT NULL,
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_sheet (tenant_id, sheet_id, idx)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$p}bridge_keys (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id  BIGINT UNSIGNED NOT NULL,
            user_id    BIGINT UNSIGNED NOT NULL,
            key_hash   CHAR(64)        NOT NULL,
            label      VARCHAR(80)     DEFAULT NULL,
            expires_at DATETIME        NOT NULL,
            used_at    DATETIME        DEFAULT NULL,
            uses       INT UNSIGNED    NOT NULL DEFAULT 0,
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_hash (key_hash),
            KEY idx_tenant (tenant_id)
        ) {$charset};";

        // ── النظام ──

        $sql[] = "CREATE TABLE {$p}audit_log (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id   BIGINT UNSIGNED NOT NULL,
            user_id     BIGINT UNSIGNED DEFAULT NULL,
            action      VARCHAR(60)     NOT NULL,
            object_type VARCHAR(40)     DEFAULT NULL,
            object_id   BIGINT UNSIGNED DEFAULT NULL,
            meta        TEXT            DEFAULT NULL,
            ip          VARCHAR(45)     DEFAULT NULL,
            created_at  DATETIME        NOT NULL,
            updated_at  DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_tenant (tenant_id, created_at),
            KEY idx_action (action)
        ) {$charset};";

        // حصّة التحسين تُعَدّ شهريًّا لكل مشترك — والمفتاح الفريد يجعل
        // الزيادة `INSERT … ON DUPLICATE KEY UPDATE` بلا سباق.
        $sql[] = "CREATE TABLE {$p}ai_usage (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id  BIGINT UNSIGNED NOT NULL,
            ym         CHAR(7)         NOT NULL,
            calls      INT UNSIGNED    NOT NULL DEFAULT 0,
            tokens     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME        NOT NULL,
            updated_at DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_month (tenant_id, ym)
        ) {$charset};";

        foreach ($sql as $one) {
            dbDelta($one);
        }
    }

    // ═══════════════════════════ الأدوار ═══════════════════════════

    /**
     * دورٌ واحد لكل مشترك في ووردبريس: `mdd_member`.
     *
     * والتمييز بين مدير المدرسة والوكيل **ليس صلاحيةً في ووردبريس** بل
     * حقلٌ في `members` — لأنه يحدّد ما يراه من القوالب لا ما يقدر عليه في
     * النظام. وخلطُ الاثنين يُنشئ ستّة أدوار ووردبريس بلا سبب.
     */
    private static function register_roles(): void
    {
        remove_role('mdd_member');

        add_role('mdd_member', __('مشترك مداد', 'midad'), [
            'read'       => true,
            'mdd_access' => true,
        ]);

        $admin = get_role('administrator');

        if ($admin) {
            $admin->add_cap('mdd_access');
            $admin->add_cap('mdd_admin');
        }
    }

    // ═══════════════════════════ البذرة ═══════════════════════════

    /** تُكتب مرّةً ولا تُكتب فوق تعديلٍ لاحق من السوبر أدمن. */
    private static function seed(): void
    {
        global $wpdb;

        $roles = $wpdb->prefix . 'mdd_roles';
        $plans = $wpdb->prefix . 'mdd_plans';
        $now   = mdd_now();

        foreach (MDD_Member::SEED_ROLES as $slug => [$label, $sort]) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO `{$roles}` (slug, label, sort, created_at) VALUES (%s, %s, %d, %s)",
                $slug,
                $label,
                $sort,
                $now
            ));
        }

        // باقتان افتراضيّتان — الأسعار تُضبَط من السوبر أدمن، والبذرة تجعل
        // التجربة تعمل من اللحظة الأولى بدل شاشةٍ فارغة يوم التثبيت.
        $defaults = [
            [
                'slug' => 'school-year', 'type' => 'school', 'name' => 'باقة المدرسة — سنوية',
                'price' => 0, 'features' => ['seats' => 10, 'noor' => true, 'ai' => 300, 'categories' => []],
            ],
            [
                'slug' => 'teacher-year', 'type' => 'teacher', 'name' => 'باقة المعلّم — سنوية',
                'price' => 0, 'features' => ['seats' => 1, 'noor' => true, 'ai' => 100, 'categories' => ['teacher', 'general']],
            ],
        ];

        foreach ($defaults as $one) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO `{$plans}`
                    (slug, type, name, price, months, features, is_default, status, sort, created_at)
                 VALUES (%s, %s, %s, %f, 12, %s, 1, 'active', 10, %s)",
                $one['slug'],
                $one['type'],
                $one['name'],
                $one['price'],
                (string) wp_json_encode($one['features']),
                $now
            ));
        }
    }

    /** أسماء الجداول — تُقرأ من `CREATE TABLE` نفسها لا من قائمةٍ ثانية تُنسى. */
    public static function table_names(): array
    {
        static $memo = null;

        if ($memo !== null) {
            return $memo;
        }

        $src = (string) file_get_contents(__FILE__);
        preg_match_all('/CREATE TABLE \{\$p\}(\w+)/', $src, $m);

        return $memo = $m[1] ?? [];
    }
}
