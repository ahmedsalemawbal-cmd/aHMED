<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * النسخ الاحتياطي والاستعادة.
 *
 * لم يكن في النظام كلّه نسخةٌ احتياطية واحدة: كشفُ أربعمئة طالب، وحضورُ فصلٍ
 * دراسيّ، وسجلُّ عهدةٍ لا يُعدَّل ولا يُحذف، وفواتيرُ أهاليهم — كلّه على قاعدةٍ
 * واحدة بلا نسخة. وأوّل خطأ إداريّ أو ترقيةٍ فاشلة يمحو سنةً من العمل.
 *
 * **ثلاث قواعد تحكم هذا الملفّ:**
 *
 * 1. **النسخة تشمل الملفّات لا الجداول وحدها.** صورةُ الطفل ووصفةُ دوائه
 *    وتقريرُ إجازته في `uploads/sch-private/`، وقاعدةٌ بلا ملفّاتها تُستعاد
 *    ناقصةً بصمت — صفوفٌ تشير إلى ملفّات لا وجود لها.
 * 2. **الاستعادة معاملةٌ واحدة.** تُكتب الجداول كلّها أو لا يُكتب شيء. واستعادةٌ
 *    تتوقّف في منتصفها أسوأ من ألّا تبدأ: قاعدةٌ نصفُها من أمس ونصفُها من اليوم.
 * 3. **النسخة تُؤخذ قبل اللحظتين اللتين يضيع فيهما العمل** — الترقية وترحيل
 *    السنة — تلقائيًّا وبلا سؤال، لأن من يحتاجها لا يتذكّرها.
 *
 * والملفّ يُخزَّن في المجلّد المحميّ نفسه (`sch-private/backups/`) فلا يُنزَّل
 * من عنوانٍ مباشر، ويُخدَم عبر `SCH_Files` بفحص صلاحيةٍ يخصّه.
 */
final class SCH_Backup
{
    /** صيغة الملفّ — رقمٌ يتغيّر إن تغيّر الشكل، فلا تُستعاد نسخةٌ لا تُفهم. */
    private const FORMAT = 2;

    /** ما يُبقى من النسخ التلقائية — الأقدم يُحذف. */
    private const KEEP = 8;

    /** حدّ الصفوف في دفعة الإدراج الواحدة — أكبر منه يتجاوز `max_allowed_packet`. */
    private const CHUNK = 200;

    // ═══════════════════════ المسارات ═══════════════════════

    /** مجلّد النسخ — داخل المحميّ، فلا يُنزَّل بعنوانٍ مباشر. */
    public static function dir(): string
    {
        $dir = SCH_Enrollment::private_dir() . '/backups';

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return $dir;
    }

    // ═══════════════════════ الأخذ ═══════════════════════

    /**
     * نسخةٌ كاملة.
     *
     * @param string $why سببُ النسخة — يظهر في القائمة: «يدويّة» أو «قبل الترقية»
     * @return array{file:string,size:int,tables:int,rows:int,files:int}|WP_Error
     */
    public static function create(string $why = 'manual'): array|WP_Error
    {
        global $wpdb;

        if (!class_exists('ZipArchive')) {
            return sch_api_error('no_zip', __('الخادم لا يدعم ZipArchive — راجع مزوّد الاستضافة.', 'school-system'), 500);
        }

        $stamp = current_time('Y-m-d_His');
        $path  = self::dir() . '/sch-' . $stamp . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return sch_api_error('zip_failed', __('تعذّر إنشاء ملفّ النسخة — تحقّق من مساحة القرص وصلاحيات المجلّد.', 'school-system'), 500);
        }

        $prefix = $wpdb->prefix . 'sch_';
        $tables = 0;
        $rows   = 0;

        foreach (SCH_Activator::table_names() as $name) {
            $full = $prefix . $name;

            if ((string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) !== $full) {
                continue;
            }

            $sql = self::dump_table($full, $rows);
            $zip->addFromString('tables/' . $name . '.sql', $sql);
            $tables++;
        }

        // الملفّات المحميّة — بلاها تُستعاد صفوفٌ تشير إلى صورٍ لا وجود لها.
        $files = self::add_private_files($zip);

        $zip->addFromString('manifest.json', (string) wp_json_encode([
            'format'      => self::FORMAT,
            'version'     => SCH_VERSION,
            'db_prefix'   => $wpdb->prefix,
            'site'        => home_url('/'),
            'school'      => sch_settings('school_name', ''),
            'created_at'  => current_time('mysql'),
            'created_by'  => get_current_user_id() ?: null,
            'why'         => $why,
            'tables'      => $tables,
            'rows'        => $rows,
            'files'       => $files,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $zip->close();

        // الأقدم يُحذف — وإلّا امتلأ القرص بنسخٍ لا يفتحها أحد.
        self::prune();

        sch_audit('backup.created', 'system', null, [
            'file'   => basename($path),
            'why'    => $why,
            'tables' => $tables,
            'rows'   => $rows,
        ]);

        return [
            'file'   => basename($path),
            'size'   => (int) filesize($path),
            'tables' => $tables,
            'rows'   => $rows,
            'files'  => $files,
        ];
    }

    /**
     * جدولٌ واحد إلى SQL.
     *
     * `SHOW CREATE TABLE` للبنية، ثم الصفوف دفعاتٍ من مئتين. والقيم تمرّ
     * بـ`$wpdb->prepare()` — فاسمُ طالبٍ فيه علامة اقتباس لا يكسر الملفّ.
     */
    private static function dump_table(string $table, int &$rows): string
    {
        global $wpdb;

        $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        $out    = "DROP TABLE IF EXISTS `{$table}`;\n" . ($create[1] ?? '') . ";\n";

        $offset = 0;

        while (true) {
            $batch = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", self::CHUNK, $offset),
                ARRAY_A
            );

            if (!$batch) {
                break;
            }

            $cols = '`' . implode('`,`', array_keys($batch[0])) . '`';
            $vals = [];

            foreach ($batch as $row) {
                $cells = [];

                foreach ($row as $v) {
                    $cells[] = $v === null ? 'NULL' : $wpdb->prepare('%s', (string) $v);
                }

                $vals[] = '(' . implode(',', $cells) . ')';
            }

            $out  .= "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $vals) . ";\n";
            $rows += count($batch);

            if (count($batch) < self::CHUNK) {
                break;
            }

            $offset += self::CHUNK;
        }

        return $out;
    }

    /** ملفّات المجلّد المحميّ — عدا النسخ نفسها، فلا تُنسَخ النسخة داخل نفسها. */
    private static function add_private_files(ZipArchive $zip): int
    {
        $base  = SCH_Enrollment::private_dir();
        $count = 0;

        foreach (glob($base . '/*') ?: [] as $item) {
            if (!is_file($item)) {
                continue;
            }

            $name = basename($item);

            if ($name === '.htaccess' || $name === 'index.php') {
                continue;
            }

            $zip->addFile($item, 'files/' . $name);
            $count++;
        }

        return $count;
    }

    // ═══════════════════════ القائمة ═══════════════════════

    /**
     * النسخ الموجودة، الأحدث أوّلًا.
     *
     * @return array<int,array{file:string,size:int,at:string,why:string,rows:int}>
     */
    public static function all(): array
    {
        $out = [];

        foreach (glob(self::dir() . '/sch-*.zip') ?: [] as $path) {
            $meta = self::manifest($path);

            $out[] = [
                'file' => basename($path),
                'size' => (int) filesize($path),
                'at'   => (string) ($meta['created_at'] ?? gmdate('Y-m-d H:i:s', (int) filemtime($path))),
                'why'  => (string) ($meta['why'] ?? ''),
                'rows' => (int) ($meta['rows'] ?? 0),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        return $out;
    }

    /** بطاقة النسخة — تُقرأ بلا فكّ الأرشيف كلّه. */
    public static function manifest(string $path): array
    {
        if (!is_readable($path) || !class_exists('ZipArchive')) {
            return [];
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return [];
        }

        $raw = $zip->getFromName('manifest.json');
        $zip->close();

        $data = json_decode((string) $raw, true);

        return is_array($data) ? $data : [];
    }

    /** آخر نسخة — لشاشة الجاهزية. */
    public static function latest(): ?array
    {
        $all = self::all();

        return $all[0] ?? null;
    }

    /** مسارٌ آمن من اسمٍ جاء من المستخدم — لا خروج من المجلّد. */
    public static function path_of(string $file): ?string
    {
        // اسمُ النسخة شكلٌ واحد لا غير — وهو أضيق من أي تنقيةٍ عامّة.
        if (preg_match('/^sch-\d{4}-\d{2}-\d{2}_\d{6}\.zip$/', $file) !== 1) {
            return null;
        }

        $path = self::dir() . '/' . $file;

        return is_readable($path) ? $path : null;
    }

    // ═══════════════════════ الاستعادة ═══════════════════════

    /**
     * ما ستستبدله الاستعادة — يُعرض قبل التنفيذ لا بعده.
     *
     * من يستعيد نسخةً يستحقّ أن يرى ما سيخسره: كم طالبًا الآن وكم في النسخة،
     * وكم يومًا مضى عليها. والفرقُ بالأرقام أصدق من تحذيرٍ عامّ.
     *
     * @return array{ok:bool,msg:string,at:string,age_days:int,now:array<string,int>,then:array<string,int>}
     */
    public static function preview(string $file): array|WP_Error
    {
        global $wpdb;

        $path = self::path_of($file);

        if ($path === null) {
            return sch_api_error('not_found', __('النسخة غير موجودة.', 'school-system'), 404);
        }

        $meta = self::manifest($path);

        if ((int) ($meta['format'] ?? 0) !== self::FORMAT) {
            return sch_api_error('bad_format', __('هذه النسخة بصيغةٍ لا يفهمها هذا الإصدار.', 'school-system'), 422);
        }

        /*
         * سابقة الجداول تختلف بين تركيبٍ وآخر (`wp_` أو `wpxy_`). ونسخةٌ من
         * موقعٍ سابقتُه أخرى تُستعاد بجداولَ لا يقرؤها النظام — فتبدو القاعدة
         * فارغةً والبيانات موجودة.
         */
        if ((string) ($meta['db_prefix'] ?? '') !== $wpdb->prefix) {
            return sch_api_error('bad_prefix', sprintf(
                /* translators: 1: سابقة النسخة 2: سابقة هذا الموقع */
                __('النسخة من تركيبٍ سابقةُ جداوله «%1$s» وهذا الموقع «%2$s» — لا تُستعاد هنا.', 'school-system'),
                (string) ($meta['db_prefix'] ?? '؟'),
                $wpdb->prefix
            ), 422);
        }

        $counts = static function (): array {
            global $wpdb;

            $out = [];

            foreach (['students' => 'الطلاب', 'enrollments' => 'التسجيلات', 'attendance' => 'الحضور',
                      'custody_events' => 'أحداث العهدة', 'invoices' => 'الفواتير'] as $t => $label) {
                $full = $wpdb->prefix . 'sch_' . $t;

                $out[$label] = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) === $full
                    ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$full}`")
                    : 0;
            }

            return $out;
        };

        $at  = (string) ($meta['created_at'] ?? '');
        $age = $at !== '' ? (int) floor((strtotime(current_time('mysql')) - strtotime($at)) / DAY_IN_SECONDS) : 0;

        return [
            'ok'       => true,
            'msg'      => '',
            'at'       => $at,
            'age_days' => max(0, $age),
            'now'      => $counts(),
            'then'     => self::counts_in($path),
        ];
    }

    /** عدد الصفوف داخل النسخة — يُحصى من جُمل الإدراج بلا تنفيذها. */
    private static function counts_in(string $path): array
    {
        $zip = new ZipArchive();
        $out = [];

        if ($zip->open($path) !== true) {
            return $out;
        }

        foreach (['students' => 'الطلاب', 'enrollments' => 'التسجيلات', 'attendance' => 'الحضور',
                  'custody_events' => 'أحداث العهدة', 'invoices' => 'الفواتير'] as $t => $label) {
            $sql        = (string) $zip->getFromName('tables/' . $t . '.sql');
            $out[$label] = substr_count($sql, "),\n(") + substr_count($sql, ') VALUES');
        }

        $zip->close();

        return $out;
    }

    /**
     * الاستعادة.
     *
     * كلّها أو لا شيء: معاملةٌ واحدة تلفّ الجداول جميعًا، فاستعادةٌ تتوقّف في
     * منتصفها لا تترك قاعدةً نصفُها من أمس ونصفُها من اليوم. والملفّات تُنسَخ
     * بعد نجاح الجداول لا قبله.
     */
    public static function restore(string $file): array|WP_Error
    {
        global $wpdb;

        $check = self::preview($file);

        if (is_wp_error($check)) {
            return $check;
        }

        $path = (string) self::path_of($file);

        // شبكة أمان: نسخةٌ من الحاضر قبل محوه — فمن استعاد بالخطأ يعود.
        $safety = self::create('before_restore');

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return sch_api_error('zip_failed', __('تعذّر فتح ملفّ النسخة.', 'school-system'), 500);
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
        $wpdb->query('START TRANSACTION');

        $tables = 0;
        $failed = '';

        foreach (SCH_Activator::table_names() as $name) {
            $sql = $zip->getFromName('tables/' . $name . '.sql');

            if ($sql === false) {
                continue;
            }

            foreach (self::split_statements((string) $sql) as $stmt) {
                if ($wpdb->query($stmt) === false) {
                    $failed = $wpdb->last_error;
                    break 2;
                }
            }

            $tables++;
        }

        if ($failed !== '') {
            $wpdb->query('ROLLBACK');
            $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
            $zip->close();

            return sch_api_error('restore_failed', sprintf(
                /* translators: %s: رسالة قاعدة البيانات */
                __('فشلت الاستعادة ولم يتغيّر شيء: %s', 'school-system'),
                $failed
            ), 500);
        }

        $wpdb->query('COMMIT');
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        // الملفّات بعد الجداول: صفٌّ بلا صورته يُصلَح، وصورةٌ بلا صفّها تُهمَل.
        $files = self::restore_private_files($zip);
        $zip->close();

        sch_audit('backup.restored', 'system', null, [
            'file'   => $file,
            'tables' => $tables,
            'safety' => is_wp_error($safety) ? '' : $safety['file'],
        ]);

        // الكاش يحمل صفحاتٍ من قاعدةٍ لم تعد موجودة.
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        return [
            'tables' => $tables,
            'files'  => $files,
            'safety' => is_wp_error($safety) ? '' : $safety['file'],
        ];
    }

    /** ملفّات النسخة إلى المجلّد المحميّ — لا تُمحى الموجودات، تُستكمَل. */
    private static function restore_private_files(ZipArchive $zip): int
    {
        $base  = SCH_Enrollment::private_dir();
        $count = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (!str_starts_with($name, 'files/')) {
                continue;
            }

            $leaf = basename($name);

            // اسمٌ فيه مسارٌ صاعد لا يخرج من المجلّد — `basename` وحدها تكفي،
            // والفحص هنا صريحٌ لأن الأرشيف مصدرٌ خارجيّ لا يُؤتمن.
            if ($leaf === '' || $leaf === '.' || $leaf === '..') {
                continue;
            }

            $data = $zip->getFromIndex($i);

            if ($data !== false && file_put_contents($base . '/' . $leaf, $data) !== false) {
                @chmod($base . '/' . $leaf, 0600);
                $count++;
            }
        }

        return $count;
    }

    /**
     * تقسيم ملفّ SQL إلى جُمل.
     *
     * الفاصلة المنقوطة داخل نصٍّ ليست نهاية جملة — واسمُ مدرسةٍ فيه «؛» أو
     * ملاحظةُ معلّمٍ فيها فاصلة منقوطة تكسر التقسيم الساذج. فيُمشى على الحروف
     * مع تتبّع علامات الاقتباس والهروب.
     *
     * @return array<int,string>
     */
    private static function split_statements(string $sql): array
    {
        $out   = [];
        $buf   = '';
        $quote = '';
        $len   = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];

            if ($quote !== '') {
                $buf .= $c;

                if ($c === '\\' && $i + 1 < $len) {
                    $buf .= $sql[++$i];
                    continue;
                }

                if ($c === $quote) {
                    $quote = '';
                }

                continue;
            }

            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $buf  .= $c;
                continue;
            }

            if ($c === ';') {
                $stmt = trim($buf);

                if ($stmt !== '') {
                    $out[] = $stmt;
                }

                $buf = '';
                continue;
            }

            $buf .= $c;
        }

        $stmt = trim($buf);

        if ($stmt !== '') {
            $out[] = $stmt;
        }

        return $out;
    }

    // ═══════════════════════ الحذف والتقليم ═══════════════════════

    public static function delete(string $file): bool
    {
        $path = self::path_of($file);

        if ($path === null) {
            return false;
        }

        sch_audit('backup.deleted', 'system', null, ['file' => $file]);

        return @unlink($path);
    }

    /** الأقدم يُحذف — وإلّا امتلأ القرص بنسخٍ لا يفتحها أحد. */
    private static function prune(): void
    {
        $all = self::all();

        foreach (array_slice($all, self::KEEP) as $old) {
            @unlink(self::dir() . '/' . $old['file']);
        }
    }

    // ═══════════════════════ اللحظتان الحرجتان ═══════════════════════

    /**
     * نسخةٌ تلقائية قبل ما لا رجعة فيه.
     *
     * الترقية وترحيل السنة هما اللحظتان اللتان يضيع فيهما العمل — ومن يحتاج
     * النسخة فيهما لا يتذكّرها. فتُؤخذ بلا سؤال، وفشلُها لا يوقف العملية:
     * تعطيلُ الترقية لأن القرص ممتلئ أسوأ من الترقية بلا نسخة.
     */
    public static function auto(string $why): void
    {
        if (!class_exists('ZipArchive')) {
            return;
        }

        $done = self::create($why);

        if (is_wp_error($done)) {
            sch_audit('backup.auto_failed', 'system', null, [
                'why'   => $why,
                'error' => $done->get_error_code(),
            ]);
        }
    }
}
