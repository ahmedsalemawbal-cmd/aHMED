<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * البوّابة الوحيدة إلى قاعدة البيانات — وحارس عزل المشتركين.
 *
 * **سطرٌ واحد ناسٍ لـ`WHERE tenant_id` يُري مدرسةً بيانات مدرسةٍ أخرى.**
 * وهذا هو الخطأ الذي يقتل منتجات الاشتراك، ولا يُكتشف إلّا بعد وقوعه —
 * حين يكون قد وقع مئات المرّات. فالعزل هنا **بنيويّ لا انضباطيّ**:
 *
 *   • لا `$wpdb` في المشروع كلّه خارج هذا الملفّ. و`audit.py` يرفض غيره.
 *   • كل قراءةٍ وكتابةٍ تمرّ من هنا، والمستأجر يُحقَن **في الطبقة** فلا
 *     تملك الشاشة أن تنساه.
 *   • و`one()` لا تُرجع صفًّا لغير صاحبه **ولو زُوّر المعرّف في الطلب** —
 *     وهي أشيع طرق التسلّل: تبديل `?id=` في الرابط.
 *
 * وثلاثة أصناف من الجداول:
 *
 *   `GLOBAL_TABLES`  مكتبتك أنت — القوالب والباقات والأدوار. يقرؤها الجميع.
 *   `SELF_TABLE`     `tenants` — يُقيَّد بـ`id` لا بـ`tenant_id`.
 *   وما سواهما      يُقيَّد بـ`tenant_id` دائمًا وبلا استثناء.
 */
final class MDD_DB
{
    /** مكتبة المنصّة — عامّة للجميع، ولا تُكتب إلّا من السوبر أدمن. */
    private const GLOBAL_TABLES = ['templates', 'plans', 'roles'];

    /** جدول المستأجر نفسه — معرّفه هو معرّف المستأجر. */
    private const SELF_TABLE = 'tenants';

    /**
     * مستأجرٌ مفروضٌ مؤقّتًا — للكرون والسوبر أدمن وحدهما.
     *
     * الكرون لا مستخدم له، والسوبر أدمن يفتح ملفّ مشتركٍ بعينه. وكلاهما
     * يمرّ من `as_tenant()` **الظاهرة في الشفرة** لا من متغيّرٍ عامّ يُضبَط
     * من حيث لا يُدرى.
     */
    private static ?int $forced = null;

    // ═══════════════════════════ الأسماء ═══════════════════════════

    public static function table(string $name): string
    {
        global $wpdb;

        if (preg_match('/^[a-z_]+$/', $name) !== 1) {
            self::die_hard('اسم جدولٍ غير صحيح: ' . $name);
        }

        return $wpdb->prefix . 'mdd_' . $name;
    }

    public static function is_global(string $table): bool
    {
        return in_array($table, self::GLOBAL_TABLES, true);
    }

    /** عمود القيد لهذا الجدول — أو `null` للجداول العامّة. */
    private static function key_of(string $table): ?string
    {
        if (self::is_global($table)) {
            return null;
        }

        return $table === self::SELF_TABLE ? 'id' : 'tenant_id';
    }

    // ═══════════════════════════ النطاق ═══════════════════════════

    /**
     * المستأجر الحاليّ — ولا قراءة بلا واحد.
     *
     * والسقوط هنا **مقصود**: استعلامٌ بلا مستأجرٍ معروف خطأُ برمجةٍ لا حالةٌ
     * تُعالَج بإرجاع فراغ. وإرجاع الفراغ يُخفي العطل حتى يصير تسريبًا.
     */
    private static function scope(): int
    {
        if (self::$forced !== null) {
            return self::$forced;
        }

        $id = MDD_Tenant::current_id();

        if ($id <= 0) {
            self::die_hard('استعلامٌ بلا مستأجر — راجع MDD_DB::as_tenant().');
        }

        return $id;
    }

    /**
     * تنفيذ كتلةٍ باسم مستأجرٍ بعينه.
     *
     * تُعيد النطاق السابق مهما وقع (`finally`) — فاستثناءٌ في الكتلة لا
     * يترك الطلبَ كلَّه يقرأ ببيانات مشتركٍ آخر.
     */
    public static function as_tenant(int $tenant_id, callable $fn): mixed
    {
        $before = self::$forced;
        self::$forced = max(0, $tenant_id);

        try {
            return $fn();
        } finally {
            self::$forced = $before;
        }
    }

    // ═══════════════════════════ القراءة ═══════════════════════════

    /**
     * صفوفٌ من جدول.
     *
     * `$where`: `['status' => 'draft']` أو `['id' => [3, 7, 9]]`.
     * `$opt`:   `order` · `dir` · `limit` · `offset`.
     *
     * @return array<int,object>
     */
    public static function rows(string $table, array $where = [], array $opt = []): array
    {
        global $wpdb;

        [$sql, $args] = self::build($table, $where, $opt);

        return $wpdb->get_results($args === [] ? $sql : $wpdb->prepare($sql, $args)) ?: [];
    }

    /** أوّل صفٍّ يطابق. */
    public static function first(string $table, array $where = [], array $opt = []): ?object
    {
        $rows = self::rows($table, $where, ['limit' => 1] + $opt);

        return $rows[0] ?? null;
    }

    /**
     * صفٌّ بمعرّفه — **ولا يُرجَع لغير صاحبه**.
     *
     * القيد في الاستعلام نفسه لا في فحصٍ بعده: الفحص بعد الجلب يعني أن
     * الصفّ غادر القاعدة فعلًا، ويكفي سطرُ تسجيلٍ أو رسالةُ خطأ ليُسرَّب.
     */
    public static function one(string $table, int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        return self::first($table, ['id' => $id]);
    }

    public static function count(string $table, array $where = []): int
    {
        global $wpdb;

        [$sql, $args] = self::build($table, $where, [], 'COUNT(*)');

        return (int) $wpdb->get_var($args === [] ? $sql : $wpdb->prepare($sql, $args));
    }

    public static function exists(string $table, array $where): bool
    {
        return self::count($table, $where) > 0;
    }

    // ═══════════════════════════ الكتابة ═══════════════════════════

    /** إدراجٌ — والمستأجر و`created_at` يُضافان هنا لا في المنادي. */
    public static function insert(string $table, array $data): int
    {
        global $wpdb;

        $key = self::key_of($table);

        if ($key === 'tenant_id') {
            $data['tenant_id'] = self::scope();
        }

        $data['created_at'] ??= mdd_now();
        $data['updated_at'] ??= $data['created_at'];

        $ok = $wpdb->insert(self::table($table), self::clean($data));

        return $ok === false ? 0 : (int) $wpdb->insert_id;
    }

    /** تحديثٌ بمعرّف — والمستأجر شرطٌ في `WHERE` لا اقتراح. */
    public static function update(string $table, int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $data['updated_at'] ??= mdd_now();
        $where = ['id' => $id];
        $key   = self::key_of($table);

        if ($key === 'tenant_id') {
            $where['tenant_id'] = self::scope();
        } elseif ($key === 'id') {
            $where['id'] = self::scope();

            if ($id !== $where['id']) {
                return false;
            }
        }

        return $wpdb->update(self::table($table), self::clean($data), $where) !== false;
    }

    public static function delete(string $table, int $id): bool
    {
        global $wpdb;

        if ($id <= 0 || !self::one($table, $id)) {
            return false;
        }

        $where = ['id' => $id];

        if (self::key_of($table) === 'tenant_id') {
            $where['tenant_id'] = self::scope();
        }

        return $wpdb->delete(self::table($table), $where) !== false;
    }

    // ═══════════════════════════ المعاملات ═══════════════════════════

    public static function begin(): void
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
    }

    public static function commit(): void
    {
        global $wpdb;
        $wpdb->query('COMMIT');
    }

    public static function rollback(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }

    public static function last_error(): string
    {
        global $wpdb;
        return (string) $wpdb->last_error;
    }

    // ═══════════════════════════ ما يعبر المشتركين ═══════════════════════════

    /**
     * قراءةٌ عبر كل المشتركين — **للسوبر أدمن وحده**.
     *
     * وهي المنفذ الوحيد الذي يتجاوز العزل، ولذلك: حارسٌ صريح، وسقوطٌ لا
     * إرجاعُ فراغ، واسمٌ يُعثَر عليه بالبحث في سطرٍ واحد.
     *
     * @return array<int,object>
     */
    public static function across(string $table, array $where = [], array $opt = []): array
    {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            self::die_hard('قراءةٌ عبر المشتركين بلا صلاحية.');
        }

        [$sql, $args] = self::build($table, $where, $opt, '*', false);

        return $wpdb->get_results($args === [] ? $sql : $wpdb->prepare($sql, $args)) ?: [];
    }

    /**
     * عضويّة مستخدم — تُقرأ **قبل** أن يُعرَف مستأجره.
     *
     * وهي الاستثناء الثاني والأخير، وسببه بنيويّ: `MDD_Tenant::current_id()`
     * تُبنى من هذا الصفّ، فلا يمكن أن تسبقه. ولا تقبل إلّا معرّف مستخدم.
     */
    public static function membership_of(int $user_id): ?object
    {
        global $wpdb;

        if ($user_id <= 0) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table('members') . " WHERE user_id = %d AND status = 'active' LIMIT 1",
            $user_id
        )) ?: null;
    }

    // ═══════════════════════════ الداخل ═══════════════════════════

    /**
     * بناء الاستعلام — والأسماء تُفحَص، والقيم تُمرَّر وسائطَ لا تُلصَق.
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function build(
        string $table,
        array $where,
        array $opt,
        string $columns = '*',
        bool $scoped = true
    ): array {
        $key = self::key_of($table);

        if ($scoped && $key !== null) {
            $where[$key] = self::scope();
        }

        $parts = [];
        $args  = [];

        foreach ($where as $col => $value) {
            self::check_column((string) $col);

            if (is_array($value)) {
                if ($value === []) {
                    // شرطٌ لا يطابق شيئًا — أصدق من حذفه فيتّسع الاستعلام.
                    $parts[] = '1 = 0';
                    continue;
                }

                $parts[] = "`{$col}` IN (" . implode(',', array_fill(0, count($value), '%s')) . ')';
                array_push($args, ...array_map('strval', $value));
                continue;
            }

            if ($value === null) {
                $parts[] = "`{$col}` IS NULL";
                continue;
            }

            $parts[] = "`{$col}` = %s";
            $args[]  = (string) $value;
        }

        $sql = "SELECT {$columns} FROM " . self::table($table);

        if ($parts !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $parts);
        }

        if (isset($opt['order'])) {
            self::check_column((string) $opt['order']);
            $dir  = strtoupper((string) ($opt['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `{$opt['order']}` {$dir}";
        }

        if (isset($opt['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int) $opt['limit']);

            if (isset($opt['offset'])) {
                $sql .= ' OFFSET ' . max(0, (int) $opt['offset']);
            }
        }

        return [$sql, $args];
    }

    private static function check_column(string $col): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $col) !== 1) {
            self::die_hard('اسم عمودٍ غير صحيح: ' . $col);
        }
    }

    /** لا مفاتيح غريبة تصل `$wpdb->insert` — فتُدرَج بلا قصد. */
    private static function clean(array $data): array
    {
        $out = [];

        foreach ($data as $col => $value) {
            self::check_column((string) $col);
            $out[$col] = $value;
        }

        return $out;
    }

    /**
     * خطأُ برمجةٍ يوقف الطلب.
     *
     * ولا يُطبع تفصيلُه للزائر: الرسالة تقول «خللٌ في النظام» وتُسجَّل
     * كاملةً في سجلّ الخادم. وإفشاء أسماء الجداول للمهاجم هديّة.
     */
    private static function die_hard(string $why): never
    {
        error_log('[midad] ' . $why);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_die(esc_html('مداد: ' . $why));
        }

        wp_die(esc_html__('حدث خلل في النظام. حدّث الصفحة أو تواصل معنا.', 'midad'));
    }
}
