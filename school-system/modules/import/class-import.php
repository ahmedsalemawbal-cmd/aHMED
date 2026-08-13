<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * استيراد الطلاب من ملف CSV.
 * قاعدة صارمة: إما ينجح الملف كاملًا أو يُرفض — لا استيراد جزئي.
 */
final class SCH_Import
{
    private const MAX_ROWS = 2000;

    public const COLUMNS = [
        'full_name'      => 'اسم الطالب',
        'national_id'    => 'رقم الهوية',
        'stage'          => 'المرحلة',
        'grade_level'    => 'الصف',
        'section'        => 'الشعبة',
        'guardian_name'  => 'اسم ولي الأمر',
        'guardian_phone' => 'جوال ولي الأمر',
        'relation'       => 'صلة القرابة',
    ];

    /**
     * @return array{ok:bool,imported:int,errors:array<int,string>}
     */
    public static function run(string $file_path, bool $dry_run = true): array
    {
        $rows = self::read($file_path);
        if (is_wp_error($rows)) {
            return ['ok' => false, 'imported' => 0, 'errors' => [$rows->get_error_message()]];
        }

        $errors = self::validate($rows);
        if ($errors !== [] || $dry_run) {
            return ['ok' => $errors === [], 'imported' => 0, 'errors' => $errors];
        }

        return self::commit($rows);
    }

    /**
     * استيراد صفوف جاهزة — للجسر القادم من نور عبر إضافة المتصفح.
     *
     * نفس التحقق ونفس الالتزام المستخدمين مع ملف Excel: **مسار واحد للاستيراد**
     * لا اثنان يتباعدان. والجسر يمر من هنا كما يمر الملف.
     *
     * @param array<int,array<string,string>> $rows
     * @return array{ok:bool,imported:int,errors:array<int,string>}
     */
    public static function run_rows(array $rows, bool $dry_run = true): array
    {
        $clean = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $one = [];

            foreach (array_keys(self::COLUMNS) as $key) {
                $one[$key] = sanitize_text_field((string) ($row[$key] ?? ''));
            }

            // صف بلا اسم ولا هوية لا يُستورد — غالبًا سطر عنوان أو مجموع.
            if ($one['full_name'] === '' && $one['national_id'] === '') {
                continue;
            }

            $clean[] = $one;
        }

        if ($clean === []) {
            return ['ok' => false, 'imported' => 0, 'errors' => [__('لا صفوف صالحة.', 'school-system')]];
        }

        if (count($clean) > 500) {
            return ['ok' => false, 'imported' => 0, 'errors' => [__('حد الدفعة 500 صف.', 'school-system')]];
        }

        $errors = self::validate($clean);

        if ($errors !== [] || $dry_run) {
            return ['ok' => $errors === [], 'imported' => 0, 'errors' => $errors];
        }

        return self::commit($clean);
    }

    /** @return array<int,array<string,string>>|WP_Error */
    private static function read(string $path): array|WP_Error
    {
        if (!is_readable($path)) {
            return sch_api_error('unreadable', __('تعذّر قراءة الملف.', 'school-system'), 400);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return sch_api_error('unreadable', __('تعذّر فتح الملف.', 'school-system'), 400);
        }

        // تجاهل BOM حتى تُقرأ العربية من Excel بشكل صحيح.
        $first = fgets($handle);
        if ($first !== false && str_starts_with($first, "\xEF\xBB\xBF")) {
            $first = substr($first, 3);
        }
        rewind($handle);
        if ($first !== false) {
            fseek($handle, str_starts_with((string) fgets($handle), "\xEF\xBB\xBF") ? 3 : 0);
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            return sch_api_error('empty_file', __('الملف فارغ.', 'school-system'), 400);
        }

        $map = [];
        foreach ($header as $i => $title) {
            $title = trim((string) $title);
            foreach (self::COLUMNS as $key => $label) {
                if ($title === $label || $title === $key) {
                    $map[$i] = $key;
                }
            }
        }

        if (!in_array('full_name', $map, true)) {
            fclose($handle);
            return sch_api_error('missing_column', __('العمود «اسم الطالب» غير موجود في الملف.', 'school-system'), 422);
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line, static fn ($v): bool => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($map as $i => $key) {
                $row[$key] = trim((string) ($line[$i] ?? ''));
            }
            $rows[] = $row;

            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                return sch_api_error('too_many', sprintf(
                    /* translators: %d: الحد الأقصى للصفوف */
                    __('الملف يتجاوز الحد الأقصى (%d صف). قسّمه إلى ملفات أصغر.', 'school-system'),
                    self::MAX_ROWS
                ), 422);
            }
        }

        fclose($handle);
        return $rows;
    }

    /** @return array<int,string> */
    private static function validate(array $rows): array
    {
        global $wpdb;

        $errors = [];
        $seen   = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // +1 للترويسة، +1 لأن العد يبدأ من 1

            if (($row['full_name'] ?? '') === '') {
                $errors[] = sprintf(__('السطر %d: اسم الطالب فارغ.', 'school-system'), $line);
            }

            $nid = $row['national_id'] ?? '';
            if ($nid !== '') {
                if (isset($seen[$nid])) {
                    $errors[] = sprintf(__('السطر %d: رقم الهوية مكرر داخل الملف.', 'school-system'), $line);
                }
                $seen[$nid] = true;

                $exists = $wpdb->get_var($wpdb->prepare(
                    'SELECT 1 FROM ' . sch_table('students') . ' WHERE national_id = %s LIMIT 1',
                    $nid
                ));
                if ($exists) {
                    $errors[] = sprintf(__('السطر %d: رقم الهوية مسجّل مسبقًا في النظام.', 'school-system'), $line);
                }
            }

            $stage = self::normalize_stage($row['stage'] ?? '');
            if ($stage === null && ($row['stage'] ?? '') !== '') {
                $errors[] = sprintf(__('السطر %d: المرحلة غير معروفة (رياض أطفال / ابتدائي / متوسط / ثانوي).', 'school-system'), $line);
            }

            $phone = $row['guardian_phone'] ?? '';
            if ($phone !== '' && sch_normalize_phone($phone) === '') {
                $errors[] = sprintf(__('السطر %d: رقم جوال ولي الأمر غير صحيح.', 'school-system'), $line);
            }
        }

        return array_slice($errors, 0, 50);
    }

    /** @return array{ok:bool,imported:int,errors:array<int,string>} */
    private static function commit(array $rows): array
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
        $imported = 0;

        foreach ($rows as $row) {
            $created = SCH_Students::create([
                'full_name'   => $row['full_name'],
                'national_id' => $row['national_id'] ?? '',
                'stage'       => self::normalize_stage($row['stage'] ?? '') ?? '',
                'grade_level' => $row['grade_level'] ?? '',
                'section'     => $row['section'] ?? '',
            ]);

            if (is_wp_error($created)) {
                $wpdb->query('ROLLBACK');
                return ['ok' => false, 'imported' => 0, 'errors' => [$created->get_error_message()]];
            }

            $imported++;
            self::attach_guardian($row, (int) $created['id']);
        }

        $wpdb->query('COMMIT');
        sch_audit('import.students', null, null, ['count' => $imported]);

        return ['ok' => true, 'imported' => $imported, 'errors' => []];
    }

    private static function attach_guardian(array $row, int $student_id): void
    {
        global $wpdb;

        $phone = sch_normalize_phone($row['guardian_phone'] ?? '');
        $name  = $row['guardian_name'] ?? '';

        if ($phone === '' || $name === '') {
            return;
        }

        // ولي أمر موجود؟ اربطه. غير موجود؟ أنشئه.
        $user_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT user_id FROM ' . sch_table('guardians') . ' WHERE phone = %s LIMIT 1',
            $phone
        ));

        if ($user_id === 0) {
            $created = SCH_Guardians::create(['full_name' => $name, 'phone' => $phone]);
            if (is_wp_error($created)) {
                return;
            }
            $user_id = (int) $created['user_id'];
        }

        SCH_Guardians::link($user_id, $student_id, self::normalize_relation($row['relation'] ?? ''), true);
    }

    private static function normalize_stage(string $raw): ?string
    {
        $map = [
            'رياض أطفال' => 'kg', 'روضة' => 'kg', 'kg' => 'kg',
            'ابتدائي' => 'primary', 'primary' => 'primary',
            'متوسط' => 'intermediate', 'intermediate' => 'intermediate',
            'ثانوي' => 'secondary', 'secondary' => 'secondary',
        ];
        return $map[trim($raw)] ?? null;
    }

    private static function normalize_relation(string $raw): string
    {
        $map = ['الأب' => 'father', 'أب' => 'father', 'الأم' => 'mother', 'أم' => 'mother',
                'أخ' => 'brother', 'أخت' => 'sister', 'عم' => 'uncle', 'خال' => 'uncle'];
        return $map[trim($raw)] ?? 'guardian';
    }

    /** قالب CSV جاهز للتحميل. */
    public static function template(): string
    {
        $out = "\xEF\xBB\xBF" . implode(',', self::COLUMNS) . "\n";
        $out .= "محمد أحمد العتيبي,1012345678,ابتدائي,الرابع,أ,أحمد العتيبي,0512345678,الأب\n";
        return $out;
    }
}
