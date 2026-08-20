<?php
/**
 * `$wpdb` يُصنّع صفوفه من **مخطّط الإضافة نفسه** ومن قائمة `SELECT` للاستعلام.
 *
 * لماذا لا يكفي الفراغ: أغلب الشاشات تعرض حالتها الفارغة وتسكت، فلا يُفحَص
 * إلا رُبع شفرتها. وحين نصنع صفوفًا يُفحَص العرض كلّه — الحلقات والتنسيق
 * وقراءة الحقول.
 *
 * ولماذا لا نكتب بياناتٍ باليد: لأن الصفّ المكتوب باليد يحمل ما ظنّه كاتبه
 * لا ما تقوله القاعدة. هنا تُقرأ الأعمدة من `CREATE TABLE` في المُنشِئ، ومن
 * أسماء الأعمدة والألقاب في الاستعلام نفسه. فإن قرأ قالبٌ حقلًا **لا
 * يجلبه استعلامه**، صرخ PHP هنا بـ«خاصّية غير معرّفة» — وهو عطلٌ حقيقيّ
 * يظهر عند العميل بفراغٍ في الشاشة، ولا يراه أي فحصٍ ساكن.
 */

declare(strict_types=1);

final class SCH_SchemaWpdb
{
    public string $prefix     = 'wp_';
    public string $users      = 'wp_users';
    public string $usermeta   = 'wp_usermeta';
    public string $posts      = 'wp_posts';
    public string $last_error = '';
    public int $insert_id     = 7;
    public int $rows_affected = 1;

    /** جدول => [عمود => نوع] كما في `class-activator.php` */
    private array $schema = [];

    /** جدول => عمود => قيم `ENUM` — تُستعمل كما هي لا تُخترَع */
    private array $enums = [];

    /** كم صفًّا تُرجع القوائم — ثلاثة تكفي لكشف الحلقة ولا تُبطئ */
    private int $rows;

    public function __construct(int $rows = 3)
    {
        $this->rows = max(1, $rows);
        $this->read_schema();
    }

    // ── قراءة المخطّط من المُنشِئ ──────────────────────────────────

    private function read_schema(): void
    {
        $src = (string) file_get_contents(SCH_PATH . 'includes/class-activator.php');

        // CREATE TABLE {$p}students ( ... ) أو … sch_table('x')
        preg_match_all(
            '/CREATE TABLE\s+(?:\{\$\w+\}|[\'"]?\s*\.\s*\$?\w*\s*\.\s*[\'"]?)?([a-z_]+)\s*\((.*?)\n\s*\)\s*(?:\{?\$?charset|ENGINE|;|\'")/is',
            $src,
            $m,
            PREG_SET_ORDER
        );

        foreach ($m as $t) {
            $table = preg_replace('/^wp_sch_|^sch_/', '', trim($t[1]));
            $cols  = [];
            $enums = [];
            foreach (explode("\n", $t[2]) as $line) {
                $line = trim($line);
                if ($line === '' || preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN)\b/i', $line)) {
                    continue;
                }
                if (preg_match('/^`?([a-z_][a-z0-9_]*)`?\s+([A-Za-z]+)/', $line, $c)) {
                    $cols[$c[1]] = strtoupper($c[2]);

                    /*
                     * قيم `ENUM` تُحفظ لتُستعمل كما هي.
                     * القيمة المخترَعة تُنتج بلاغًا كاذبًا: عمودٌ قيمُه
                     * `present|late|absent|leave` لو مُلئ بـ«active» صرخ
                     * العدّاد في الشاشة، والعطل في الأداة لا في النظام.
                     */
                    if (strtoupper($c[2]) === 'ENUM' && preg_match('/ENUM\s*\((.*?)\)/i', $line, $e)) {
                        preg_match_all("/'([^']*)'/", $e[1], $vals);
                        if ($vals[1] !== []) {
                            $enums[$c[1]] = $vals[1];
                        }
                    }
                }
            }
            if ($cols !== []) {
                $this->schema[$table] = $cols;
                if ($enums !== []) { $this->enums[$table] = $enums; }
            }
        }
    }

    // ── واجهة $wpdb ───────────────────────────────────────────────

    public function prepare($q, ...$a)
    {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $i = 0;
        return preg_replace_callback('/%[dfs]/', static function (array $m) use (&$i, $a): string {
            $v = $a[$i++] ?? null;
            return match ($m[0]) {
                '%d'    => (string) (int) $v,
                '%f'    => (string) (float) $v,
                default => $v === null ? 'NULL' : "'" . str_replace("'", "''", (string) $v) . "'",
            };
        }, (string) $q);
    }

    public function get_results($q = null, $output = OBJECT)
    {
        $q = (string) $q;
        if (!preg_match('/^\s*(\(?\s*SELECT|WITH)/i', $q)) { return []; }

        $rows = [];
        for ($i = 1; $i <= $this->rows; $i++) {
            $rows[] = $this->row_for($q, $i);
        }

        if ($output === ARRAY_A) {
            return array_map(static fn (object $r): array => (array) $r, $rows);
        }
        return $rows;
    }

    public function get_row($q = null, $output = OBJECT, $y = 0)
    {
        $q = (string) $q;
        if (!preg_match('/^\s*(\(?\s*SELECT|WITH)/i', $q)) { return null; }
        $r = $this->row_for($q, 1);
        return $output === ARRAY_A ? (array) $r : $r;
    }

    public function get_col($q = null, $x = 0)
    {
        $rows = $this->get_results($q);
        $out  = [];
        foreach ($rows as $r) {
            $vals = array_values((array) $r);
            $out[] = $vals[$x] ?? 1;
        }
        return $out;
    }

    public function get_var($q = null, $x = 0, $y = 0)
    {
        $q = (string) $q;
        // العدّ والمجاميع أرقام، وغيرها أوّل عمودٍ في صفٍّ مصنوع
        if (preg_match('/\bSELECT\s+(COUNT|SUM|MAX|MIN|AVG)\s*\(/i', $q)) {
            return preg_match('/\bMAX\s*\(/i', $q) ? (string) $this->rows : (string) $this->rows;
        }
        $r = $this->get_row($q);
        if ($r === null) { return null; }
        $vals = array_values((array) $r);
        return $vals[$x] ?? null;
    }

    public function query($q) { return 1; }
    public function insert($t, $d, $f = null) { $this->insert_id++; return 1; }
    public function replace($t, $d, $f = null) { return 1; }
    public function update($t, $d, $w, $f = null, $wf = null) { return 1; }
    public function delete($t, $w, $f = null) { return 1; }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    public function esc_like($t) { return addcslashes((string) $t, '_%\\'); }
    public function _escape($d) { return $d; }
    public function suppress_errors($s = true) { return false; }
    public function flush() {}

    // ── صناعة الصفّ ───────────────────────────────────────────────

    private function row_for(string $q, int $n): object
    {
        $cols  = $this->columns_of($q);
        $alias = $this->alias_map($q);
        $row   = new stdClass();

        foreach ($cols as $c => $qualifier) {
            $row->$c = $this->value_for($c, $n, $q, $alias[$qualifier] ?? '');
        }

        // `id` موجودٌ دائمًا: أغلب القوالب تبني به روابطها
        if (!isset($row->id)) { $row->id = $n; }

        return $row;
    }

    /**
     * أعمدة الصفّ = ما يطلبه الاستعلام نفسه.
     *
     * وهذا هو بيت القصيد: `SELECT a, b AS c` يُنتج صفًّا فيه `a` و`c` فقط.
     * فإن قرأ القالبُ `d` صرخ PHP — وذاك حقلٌ نسيَه كاتب الاستعلام.
     *
     * @return string[]
     */
    private function columns_of(string $q): array
    {
        $q = preg_replace('/\s+/', ' ', $q) ?? $q;

        $list = $this->select_list($q);
        if ($list === null) {
            return $this->schema_columns($q);
        }

        $out = [];

        // `*` أو `t.*` تعني أعمدة الجدول كلّها، ولكلٍّ جدولُه
        if (preg_match_all('/(?:^|[\s,])(?:([a-z_][a-z0-9_]*)\.)?\*/i', $list, $stars, PREG_SET_ORDER)) {
            foreach ($stars as $st) {
                foreach ($this->schema_columns($q, $st[1] ?? '') as $col => $qual) {
                    $out[$col] = $qual;
                }
            }
        }

        foreach ($this->split_top($list) as $item) {
            $item = trim($item);
            if ($item === '' || preg_match('/(^|[\s.])\*$/', $item)) { continue; }

            // اللقب يبقى مربوطًا بمصدره: `sa.status AS state` قيمتُه من
            // `staff_attendance` لا من أي جدولٍ آخر فيه عمودٌ اسمه `status`.
            $qual = preg_match('/(?:^|[\s(,])([a-z_][a-z0-9_]*)\.[a-z_]/i', $item, $qm) ? $qm[1] : '';

            if (preg_match('/\bAS\s+`?([a-z_][a-z0-9_]*)`?\s*$/i', $item, $a)) {
                $out[$a[1]] = $qual;
                continue;
            }
            if (preg_match('/^`?(?:([a-z_][a-z0-9_]*)`?\.)?`?([a-z_][a-z0-9_]*)`?$/i', $item, $a)) {
                $out[$a[2]] = $a[1] ?? '';
                continue;
            }
            $out[trim($item, '`')] = $qual;
        }

        return array_filter(
            $out,
            static fn ($c): bool => (bool) preg_match('/^[a-z_][a-z0-9_]*$/i', (string) $c),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * أعمدة الجداول المذكورة في الاستعلام — مع لقب كلٍّ منها.
     *
     * @return array<string,string> عمود => لقب الجدول
     */
    private function schema_columns(string $q, string $only_alias = ''): array
    {
        $alias = $this->alias_map($q);
        $out   = [];

        // اللقب يُترجَم إلى جدوله مباشرةً — لا بالبحث العكسيّ: الاستعلام
        // قد يصل الجدولَ بنفسه بلقبين (`s` و`x`)، والبحث العكسيّ يُرجع
        // أوّلهما فيضيع عمودُ الآخر.
        if ($only_alias !== '') {
            $table = $alias[$only_alias] ?? '';
            if ($table === '__users') {
                foreach (['ID', 'display_name', 'user_login', 'user_email', 'user_registered'] as $col) {
                    $out[$col] = $only_alias;
                }
                return $out;
            }
            foreach (array_keys($this->schema[$table] ?? []) as $col) {
                $out[$col] = $only_alias;
            }
            return $out;
        }

        foreach ($alias as $a => $table) {
            if ($table === '__users') {
                foreach (['ID', 'display_name', 'user_login', 'user_email', 'user_registered'] as $col) {
                    $out[$col] ??= $a;
                }
                continue;
            }
            foreach (array_keys($this->schema[$table] ?? []) as $col) {
                $out[$col] ??= $a;
            }
        }

        return $out;
    }

    /**
     * لقب => جدول، من `FROM`/`JOIN`.
     *
     * بلا هذا لا يُعرَف أيّ `status` هو: `employees.status` قيمُه
     * `active|suspended|left`، و`staff_attendance.status` قيمُه
     * `present|late|absent|leave`. والاستعلام يصل بينهما.
     *
     * @return array<string,string>
     */
    private function alias_map(string $q): array
    {
        $map = [];
        preg_match_all(
            '/\b(?:FROM|JOIN)\s+`?((?:wp_)?(?:sch_)?[a-z_][a-z0-9_]*)`?(?:\s+(?:AS\s+)?`?([a-z_][a-z0-9_]*)`?)?/i',
            $q,
            $m,
            PREG_SET_ORDER
        );

        foreach ($m as $hit) {
            $table = preg_replace('/^wp_sch_|^sch_/', '', $hit[1]);
            $a     = $hit[2] ?? '';
            if ($a !== '' && preg_match('/^(ON|WHERE|INNER|LEFT|RIGHT|GROUP|ORDER|SET|USING|AND|OR)$/i', $a)) { $a = ''; }
            if ($hit[1] === 'wp_users') { $table = '__users'; }
            $map[$a] = $table;
        }

        return $map;
    }

    /**
     * قائمة `SELECT` الخارجية وحدها.
     *
     * الاستعلام الواقعي فيه استعلامٌ فرعيّ داخل القائمة نفسها
     * (`(SELECT COUNT(*) …) AS enrolled`)، وقراءةُ ما بين أول `SELECT` وأول
     * `FROM` تقف عند `FROM` الفرعيّ فتضيع الألقاب — وهي أهمّ ما نريده.
     * فيُقرأ العمق: `FROM` المقصود هو الذي عمقه صفر.
     */
    private function select_list(string $q): ?string
    {
        $start = null;
        $depth = 0;
        $len   = strlen($q);

        for ($i = 0; $i < $len; $i++) {
            $ch = $q[$i];
            if ($ch === '(') { $depth++; continue; }
            if ($ch === ')') { $depth--; continue; }
            if ($depth !== 0) { continue; }

            if ($start === null && stripos(substr($q, $i, 7), 'SELECT ') === 0
                && ($i === 0 || !ctype_alnum($q[$i - 1]))) {
                $start = $i + 7;
                $i    += 6;
                continue;
            }
            if ($start !== null && stripos(substr($q, $i, 5), 'FROM ') === 0
                && !ctype_alnum($q[$i - 1])) {
                return substr($q, $start, $i - $start);
            }
        }

        // `SELECT (…) AS a, (…) AS b` بلا `FROM` — استعلامٌ كلّه استعلاماتٌ
        // فرعية. قائمته تمتدّ إلى نهاية النصّ، وإهمالها يُخفي ألقابها.
        if ($start !== null) {
            return substr($q, $start);
        }

        return null;
    }

    /** @return string[] */
    private function split_top(string $list): array
    {
        $out = ['']; $d = 0;
        foreach (str_split($list) as $ch) {
            if ($ch === '(') { $d++; }
            if ($ch === ')') { $d--; }
            if ($ch === ',' && $d === 0) { $out[] = ''; continue; }
            $out[count($out) - 1] .= $ch;
        }
        return $out;
    }

    private function value_for(string $col, int $n, string $q = '', string $table = ''): mixed
    {
        $c = strtolower($col);

        /*
         * القيمة المحصورة تُؤخذ من مخطّط **الجدول المذكور في الاستعلام**.
         * أعمدةٌ كثيرة تتشارك الاسم `status`، ولكلٍّ قيمُه: خطّة الجدول
         * `draft` وحضور الموظف `present`. أخذُ أوّل ما يُصادَف كان يملأ
         * كشف الحضور بـ«draft» فيصرخ عدّادُه — عطلٌ في الأداة لا في النظام.
         */
        if ($table !== '' && isset($this->enums[$table][$c])) {
            return $this->enums[$table][$c][0];
        }
        foreach ($this->enums as $t => $cols) {
            if (isset($cols[$c]) && preg_match('/\b(?:wp_)?sch_' . preg_quote($t, '/') . '\b/i', $q)) {
                return $cols[$c][0];
            }
        }

        return match (true) {
            $c === 'id' || str_ends_with($c, '_id')          => $n,
            $c === 'ID'                                       => $n,
            str_starts_with($c, 'is_') || $c === 'locked'
                || $c === 'active' || str_ends_with($c, '_flag') => 0,
            str_ends_with($c, '_at') || $c === 'created_at'
                || $c === 'updated_at'                        => date('Y-m-d H:i:s'),
            str_ends_with($c, '_date') || $c === 'att_date'
                || $c === 'day'                               => date('Y-m-d'),
            str_ends_with($c, '_time') || $c === 'start_time'
                || $c === 'end_time'                          => '07:30:00',
            str_contains($c, 'amount') || str_contains($c, 'total')
                || str_contains($c, 'paid') || str_contains($c, 'balance')
                || str_contains($c, 'price') || str_contains($c, 'debit')
                || str_contains($c, 'credit') || str_contains($c, 'salary') => '100.00',
            str_contains($c, 'count') || str_starts_with($c, 'n_')
                || $c === 'n' || $c === 'c' || str_contains($c, 'weekly')
                || str_contains($c, 'minutes') || str_contains($c, 'periods')
                || str_contains($c, 'capacity') || str_contains($c, 'enrolled') => $n,
            str_contains($c, 'email')                         => 'u' . $n . '@example.test',
            str_contains($c, 'phone') || str_contains($c, 'mobile') => '05000000' . $n,
            str_contains($c, 'name') || str_contains($c, 'title')
                || str_contains($c, 'label')                  => 'اسم ' . $n,
            str_contains($c, 'status') || str_contains($c, 'state') => 'active',
            default                                            => 'قيمة ' . $n,
        };
    }
}
