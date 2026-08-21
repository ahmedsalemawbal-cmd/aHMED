<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** السنوات الدراسية. كل سجل أكاديمي يُنسب لسنة. */
final class SCH_Years
{
    /** ذاكرة الطلب الواحد للسنة الجارية. */
    private static ?object $memo = null;
    private static bool $loaded  = false;

    public static function create(array $input): array|WP_Error
    {
        global $wpdb;

        $name  = sanitize_text_field((string) ($input['name'] ?? ''));
        $start = sch_sanitize_date($input['start_date'] ?? null);
        $end   = sch_sanitize_date($input['end_date'] ?? null);

        if ($name === '' || !$start || !$end) {
            return sch_api_error('invalid_year', __('اسم السنة وتاريخا البداية والنهاية مطلوبة.', 'school-system'), 422);
        }
        if ($start >= $end) {
            return sch_api_error('invalid_range', __('تاريخ البداية يجب أن يسبق تاريخ النهاية.', 'school-system'), 422);
        }

        $ok = $wpdb->insert(sch_table('academic_years'), [
            'name'       => $name,
            'start_date' => $start,
            'end_date'   => $end,
            'is_current' => 0,
            'created_at' => sch_now(),
        ]);

        if ($ok === false) {
            return sch_api_error('duplicate_year', __('هذه السنة مسجّلة مسبقًا.', 'school-system'), 409);
        }

        $id = (int) $wpdb->insert_id;
        if (!self::current()) {
            self::set_current($id);
        }

        sch_audit('year.created', 'year', $id, ['name' => $name]);
        return ['id' => $id];
    }

    public static function set_current(int $id): bool
    {
        global $wpdb;

        $wpdb->query("UPDATE " . sch_table('academic_years') . " SET is_current = 0");
        $wpdb->update(sch_table('academic_years'), ['is_current' => 1], ['id' => $id]);
        update_option('sch_current_year_id', $id, false);
        self::forget();

        // مزامنة الحقول المبسّطة على سجل الطالب من تسجيله في السنة التي صارت
        // حالية — فالمرحلة/الصف/الشعبة تعكس العام الجاري لا الماضي (بعد الترقية).
        $wpdb->query($wpdb->prepare(
            "UPDATE " . sch_table('students') . " s
             INNER JOIN " . sch_table('enrollments') . " e
                     ON e.student_id = s.id AND e.year_id = %d AND e.status = 'active'
             INNER JOIN " . sch_table('classes') . " c ON c.id = e.class_id
             SET s.stage = c.stage, s.grade_level = c.grade_level, s.section = c.section, s.updated_at = %s",
            $id,
            sch_now()
        ));

        sch_audit('year.activated', 'year', $id);
        return true;
    }

    public static function all(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . sch_table('academic_years') . ' ORDER BY start_date DESC'
        ) ?: [];
    }

    /**
     * السنة الجارية — **مرّةً واحدة في الطلب**.
     *
     * `current_id()` تُنادى من كل طبقةٍ تقريبًا: الشعب والجداول والفواتير
     * والحضور والاشتراكات كلّها مقيّدة بالسنة. وبلا ذاكرةٍ كان كل نداءٍ منها
     * استعلامًا جديدًا بالنصّ نفسه — **عشرات الاستعلامات المتطابقة في رسمة
     * الصفحة الواحدة**، وضِعفها في كل ضغطة زرّ (إرسالٌ ثمّ تحويلٌ ثمّ تحميل).
     *
     * والسنة لا تتبدّل داخل الطلب إلا في `set_current()` — وهي تُفرّغ.
     */
    public static function current(): ?object
    {
        global $wpdb;

        if (self::$loaded) {
            return self::$memo;
        }

        $row = $wpdb->get_row(
            'SELECT * FROM ' . sch_table('academic_years') . ' WHERE is_current = 1 LIMIT 1'
        );

        self::$loaded = true;

        return self::$memo = ($row ?: null);
    }

    /** تُنادى بعد كل كتابةٍ تمسّ السنة الجارية. */
    public static function forget(): void
    {
        self::$loaded = false;
        self::$memo   = null;
    }

    public static function current_id(): int
    {
        $year = self::current();
        return $year ? (int) $year->id : 0;
    }
}

/** الصفوف والشعب. */
final class SCH_Classes
{
    /**
     * ذاكرة الطلب الواحد — معرّف الشعبة => صفّها (أو `null` إن لم توجد).
     *
     * @var array<int,?object>
     */
    private static array $memo = [];

    /**
     * مراحل المدرسة — **مفردةٌ واحدة يقرأها النظام كلّه**.
     *
     * كانت أربعًا، وشاشة الجداول تعرض ستّ بطاقاتٍ للإيقاع (`SCH_TT::PRESETS`)
     * فيها «الحضانة» و«التمهيدي». ومرحلةٌ خارج هذه القائمة **لا تُنشأ لها
     * شعبة أصلًا** (`create()` ترفضها) — فالبطاقتان لا تُضيئان أبدًا مهما
     * ضُغطتا، والوكيل يظنّ الشاشة معطّلة.
     *
     * والقائمتان الآن واحدة، و`audit.py` يمنع افتراقهما ثانيةً.
     */
    public const STAGES = [
        'nursery'      => 'الحضانة',
        'kg'           => 'الروضة',
        'prep'         => 'التمهيدي',
        'primary'      => 'ابتدائي',
        'intermediate' => 'متوسط',
        'secondary'    => 'ثانوي',
    ];

    public static function create(array $input): array|WP_Error
    {
        global $wpdb;

        $year_id = (int) ($input['year_id'] ?? SCH_Years::current_id());
        $stage   = (string) ($input['stage'] ?? '');
        $grade   = sanitize_text_field((string) ($input['grade_level'] ?? ''));
        $section = sanitize_text_field((string) ($input['section'] ?? ''));

        if ($year_id <= 0) {
            return sch_api_error('no_year', __('أنشئ سنة دراسية أولًا من الإعدادات.', 'school-system'), 422);
        }
        if (!array_key_exists($stage, self::STAGES) || $grade === '' || $section === '') {
            return sch_api_error('invalid_class', __('المرحلة والصف والشعبة مطلوبة.', 'school-system'), 422);
        }

        $ok = $wpdb->insert(sch_table('classes'), [
            'year_id'             => $year_id,
            'stage'               => $stage,
            'grade_level'         => $grade,
            'section'             => $section,
            'capacity'            => max(1, (int) ($input['capacity'] ?? 30)),
            'homeroom_teacher_id' => absint($input['homeroom_teacher_id'] ?? 0) ?: null,
            'created_at'          => sch_now(),
            'updated_at'          => sch_now(),
        ]);

        if ($ok === false) {
            return sch_api_error('duplicate_class', __('هذه الشعبة موجودة في هذه السنة.', 'school-system'), 409);
        }

        sch_audit('class.created', 'class', (int) $wpdb->insert_id);
        return ['id' => (int) $wpdb->insert_id];
    }

    /**
     * شعبةٌ بمعرّفها — **مرّةً واحدة لكلّ معرّف في الطلب**.
     *
     * شاشة الجداول وحدها تسألها ستّ مرّات عن الشعبة نفسها في العرض الواحد
     * (`capacity()` · `days()` · `health()` · العرض)، والمولّد يسألها لكل
     * شعبةٍ يمرّ بها. والصفّ لا يتغيّر داخل الطلب إلا بحذفه.
     */
    public static function get(int $id): ?object
    {
        global $wpdb;

        if (array_key_exists($id, self::$memo)) {
            return self::$memo[$id];
        }

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('classes') . ' WHERE id = %d', $id));

        return self::$memo[$id] = ($row ?: null);
    }

    /** الصفوف مع عدد الطلاب المسجّلين في كل شعبة. */
    public static function list(?int $year_id = null): array
    {
        global $wpdb;

        $year_id ??= SCH_Years::current_id();
        if ($year_id <= 0) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM ' . sch_table('enrollments') . ' e
                      WHERE e.class_id = c.id AND e.status = %s) AS enrolled
             FROM ' . sch_table('classes') . ' c
             WHERE c.year_id = %d
             ORDER BY c.stage, c.grade_level, c.section',
            'active',
            $year_id
        )) ?: [];
    }

    public static function label(object $class): string
    {
        return sprintf(
            '%s — %s / %s',
            self::STAGES[$class->stage] ?? $class->stage,
            $class->grade_level,
            $class->section
        );
    }

    public static function has_room(int $class_id): bool
    {
        global $wpdb;

        $class = self::get($class_id);
        if (!$class) {
            return false;
        }

        $count = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('enrollments') . ' WHERE class_id = %d AND status = %s',
            $class_id,
            'active'
        ));

        return $count < (int) $class->capacity;
    }

    public static function delete(int $id): bool|WP_Error
    {
        global $wpdb;

        $count = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('enrollments') . ' WHERE class_id = %d AND status = %s',
            $id,
            'active'
        ));

        if ($count > 0) {
            return sch_api_error('class_not_empty', __('لا يمكن حذف شعبة فيها طلاب. انقلهم أولًا.', 'school-system'), 409);
        }

        $wpdb->delete(sch_table('classes'), ['id' => $id]);
        unset(self::$memo[$id]);
        sch_audit('class.deleted', 'class', $id);

        return true;
    }
}
