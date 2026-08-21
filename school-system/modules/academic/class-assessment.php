<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** المواد الدراسية وإسنادها للشعب والمعلمين. */
final class SCH_Subjects
{
    public static function create(array $d): array|WP_Error
    {
        global $wpdb;

        $name = sanitize_text_field((string) ($d['name'] ?? ''));
        if ($name === '') {
            return sch_api_error('missing_name', __('اسم المادة مطلوب.', 'school-system'), 422);
        }

        $ok = $wpdb->insert(sch_table('subjects'), [
            'name'       => $name,
            'code'       => sanitize_text_field((string) ($d['code'] ?? '')) ?: null,
            'stage'      => array_key_exists($d['stage'] ?? '', SCH_Classes::STAGES) ? $d['stage'] : null,
            'created_at' => sch_now(),
        ]);

        if ($ok === false) {
            return sch_api_error('duplicate_code', __('رمز المادة مستخدم.', 'school-system'), 409);
        }

        sch_audit('subject.created', 'subject', (int) $wpdb->insert_id);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function all(?string $stage = null): array
    {
        global $wpdb;

        if ($stage) {
            return $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . sch_table('subjects') . ' WHERE stage = %s OR stage IS NULL ORDER BY name',
                $stage
            )) ?: [];
        }

        return $wpdb->get_results('SELECT * FROM ' . sch_table('subjects') . ' ORDER BY name') ?: [];
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('subjects') . ' WHERE id = %d', $id)) ?: null;
    }

    /** إسناد مادة لشعبة مع معلمها. */
    public static function assign(array $d): bool|WP_Error
    {
        global $wpdb;

        $class_id   = absint($d['class_id'] ?? 0);
        $subject_id = absint($d['subject_id'] ?? 0);

        if (!SCH_Classes::get($class_id) || !self::get($subject_id)) {
            return sch_api_error('not_found', __('الشعبة أو المادة غير موجودة.', 'school-system'), 404);
        }

        $teacher = absint($d['teacher_user_id'] ?? 0) ?: null;

        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('class_subjects') . ' WHERE class_id = %d AND subject_id = %d',
            $class_id,
            $subject_id
        ));

        if ($existing) {
            $wpdb->update(sch_table('class_subjects'), ['teacher_user_id' => $teacher], ['id' => (int) $existing]);
        } else {
            $wpdb->insert(sch_table('class_subjects'), [
                'class_id'        => $class_id,
                'subject_id'      => $subject_id,
                'teacher_user_id' => $teacher,
                'created_at'      => sch_now(),
            ]);
        }

        return true;
    }

    /** مواد شعبة مع أسماء معلميها. */
    public static function of_class(int $class_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT cs.*, s.name AS subject_name, s.code, u.display_name AS teacher_name
             FROM ' . sch_table('class_subjects') . ' cs
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = cs.subject_id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = cs.teacher_user_id
             WHERE cs.class_id = %d
             ORDER BY s.name',
            $class_id
        )) ?: [];
    }
}

/** الجدول الدراسي الأسبوعي. */
final class SCH_Timetable
{
    public const DAYS    = [1 => 'الأحد', 2 => 'الاثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء', 5 => 'الخميس'];
    public const PERIODS = 8;

    /** إيقاع اليوم: بدايته وطول الحصة والفسحة — رقمٌ واحد لكل شاشة تعرض وقتًا. */
    public const OPEN        = 450; // ٧:٣٠ بالدقائق من منتصف الليل
    public const LEN         = 50;
    public const BREAK_AFTER = 3;
    public const BREAK_LEN   = 30;

    /**
     * بداية الحصة بالدقائق من منتصف الليل.
     *
     * كان هذا الحساب مكتوبًا داخل شاشة جدول ولي الأمر وحدها، وشاشة تغطية
     * الحصص تحتاجه أيضًا — ونسختان تعنيان يومًا يبدأ ٧:٣٠ في شاشة و٨:٠٠
     * في أخرى بعد أول تعديل. فصار رقمًا واحدًا هنا.
     */
    public static function period_start(int $period, string $stage = ''): int
    {
        // الإيقاع صار لكل مرحلة (`SCH_TT::bell`): الروضة تخرج ١٠:١٢ والابتدائي
        // ١:١٥، ورقمٌ واحد يكذب عليهما. والثوابت أعلاه تبقى **قيمًا احتياطية**
        // لا مصدرًا — فلا ينكسر نداءٌ قديم بحجّة واحدة.
        if (class_exists('SCH_TT')) {
            return SCH_TT::period_start($period, $stage !== '' ? $stage : 'primary');
        }

        $m = self::OPEN + (max(1, $period) - 1) * self::LEN;

        return $period > self::BREAK_AFTER ? $m + self::BREAK_LEN : $m;
    }

    /** بداية الحصة بصيغة «07:30» — للأرقام الجدولية اللاتينية في الداشبورد. */
    public static function period_hhmm(int $period, string $stage = ''): string
    {
        $m = self::period_start($period, $stage);

        return sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
    }

    /** عدد حصص المرحلة — كان ثابتًا بثمانية، والمرحلة تقول خمسًا أو عشرًا. */
    public static function periods(string $stage = ''): int
    {
        return class_exists('SCH_TT')
            ? SCH_TT::bell($stage !== '' ? $stage : 'primary')->periods
            : self::PERIODS;
    }

    /**
     * يوم الأسبوع الدراسيّ من تقويم اليوم — أو صفرٌ إن كان اليوم عطلة.
     *
     * `date('w')` يبدأ الأحد بصفر، وأيامنا تبدأ الأحد بواحد. **والسبت يوم
     * دوامٍ في مدارس** — `SCH_TT::WEEK` يدعمه منذ بُني المحرّك، وكانت
     * الشاشات تسقطه فيرى معلّم السبت يومًا فارغًا وجدولُه ممتلئ.
     */
    public static function school_day(?string $date = null): int
    {
        $w = $date !== null ? (int) mysql2date('w', $date) : (int) current_time('w');

        return match ($w) {
            0       => 1,  // الأحد
            1, 2, 3, 4 => $w + 1,  // الاثنين … الخميس
            6       => 6,  // السبت
            default => 0,  // الجمعة
        };
    }

    /**
     * أيّ حصةٍ تجري الآن، وأيّها التالية.
     *
     * كان الحساب مكتوبًا باليد في قالب المعلم: `floor((الدقائق - 450) / 50)`
     * ومعه `min(7, …)` — فهو (أ) يفترض جرسًا واحدًا لكل المراحل بينما الروضة
     * تخرج ١٠:١٢ والابتدائي ١:١٥، (ب) يتجاهل الفسحة، (ج) **يجعل حصّتَي
     * الابتدائي التاسعة والعاشرة غير قابلتين للوصول**، (د) ويُسمّي الحصة
     * الجارية «القادمة» — فالسكّة تقول «جارية الآن» والرأس يقول «القادمة»
     * عن الحصة نفسها.
     *
     * @param string $stage مرحلة الشعبة — الجرس يتبعها
     * @return array{now:int,next:int} رقما الحصة الجارية والتالية، وصفرٌ حيث لا شيء
     */
    public static function running_period(string $stage = ''): array
    {
        $minutes = (int) current_time('G') * 60 + (int) current_time('i');
        $count   = self::periods($stage);
        $length  = class_exists('SCH_TT')
            ? SCH_TT::bell($stage !== '' ? $stage : 'primary')->period_len
            : self::LEN;

        $now  = 0;
        $next = 0;

        for ($p = 1; $p <= $count; $p++) {
            $start = self::period_start($p, $stage);
            $end   = $start + $length;

            if ($minutes >= $start && $minutes < $end) {
                $now = $p;
                $next = $p < $count ? $p + 1 : 0;
                break;
            }

            if ($minutes < $start) {
                $next = $p;
                break;
            }
        }

        return ['now' => $now, 'next' => $next];
    }

    /** وضع حصة في خانة — مع منع تعارض المعلم. */
    public static function set_slot(array $d): bool|WP_Error
    {
        global $wpdb;

        $class_id   = absint($d['class_id'] ?? 0);
        $subject_id = absint($d['subject_id'] ?? 0);
        $day        = (int) ($d['day_of_week'] ?? 0);
        $period     = (int) ($d['period_no'] ?? 0);
        $teacher    = absint($d['teacher_user_id'] ?? 0) ?: null;

        if (!array_key_exists($day, self::DAYS) || $period < 1 || $period > self::PERIODS) {
            return sch_api_error('bad_slot', __('اليوم أو رقم الحصة غير صحيح.', 'school-system'), 422);
        }
        if (!SCH_Classes::get($class_id) || !SCH_Subjects::get($subject_id)) {
            return sch_api_error('not_found', __('الشعبة أو المادة غير موجودة.', 'school-system'), 404);
        }

        // معلم واحد لا يكون في شعبتين في نفس الحصة.
        if ($teacher) {
            $clash = $wpdb->get_row($wpdb->prepare(
                'SELECT t.*, c.grade_level, c.section
                 FROM ' . sch_table('timetable') . ' t
                 INNER JOIN ' . sch_table('classes') . ' c ON c.id = t.class_id
                 WHERE t.teacher_user_id = %d AND t.day_of_week = %d AND t.period_no = %d AND t.class_id <> %d
                 LIMIT 1',
                $teacher,
                $day,
                $period,
                $class_id
            ));

            if ($clash) {
                return sch_api_error('teacher_clash', sprintf(
                    /* translators: 1: الصف 2: الشعبة */
                    __('المعلم مشغول في هذه الحصة مع %1$s / %2$s.', 'school-system'),
                    $clash->grade_level,
                    $clash->section
                ), 409);
            }
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('timetable') . ' WHERE class_id = %d AND day_of_week = %d AND period_no = %d',
            $class_id,
            $day,
            $period
        ));

        $data = ['subject_id' => $subject_id, 'teacher_user_id' => $teacher];

        if ($existing) {
            $wpdb->update(sch_table('timetable'), $data, ['id' => (int) $existing]);
        } else {
            $wpdb->insert(sch_table('timetable'), $data + [
                'class_id'    => $class_id,
                'day_of_week' => $day,
                'period_no'   => $period,
                'created_at'  => sch_now(),
            ]);
        }

        return true;
    }

    public static function clear_slot(int $class_id, int $day, int $period): bool
    {
        global $wpdb;
        $wpdb->delete(sch_table('timetable'), ['class_id' => $class_id, 'day_of_week' => $day, 'period_no' => $period]);
        return true;
    }

    /** جدول شعبة كمصفوفة [يوم][حصة]. */
    public static function grid(int $class_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT t.*, s.name AS subject_name, u.display_name AS teacher_name
             FROM ' . sch_table('timetable') . ' t
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = t.subject_id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = t.teacher_user_id
             WHERE t.class_id = %d',
            $class_id
        )) ?: [];

        $grid = [];
        foreach ($rows as $r) {
            $grid[(int) $r->day_of_week][(int) $r->period_no] = $r;
        }

        return $grid;
    }
}

/** الاختبارات والواجبات والدرجات. */
final class SCH_Assessment
{
    public const EXAM_TYPES = [
        'quiz'          => 'اختبار قصير',
        'midterm'       => 'اختبار فصلي',
        'final'         => 'اختبار نهائي',
        'participation' => 'مشاركة وأعمال',
    ];

    public static function create_exam(array $d): array|WP_Error
    {
        global $wpdb;

        $title      = sanitize_text_field((string) ($d['title'] ?? ''));
        $class_id   = absint($d['class_id'] ?? 0);
        $subject_id = absint($d['subject_id'] ?? 0);

        if ($title === '' || !SCH_Classes::get($class_id) || !SCH_Subjects::get($subject_id)) {
            return sch_api_error('invalid_exam', __('العنوان والشعبة والمادة مطلوبة.', 'school-system'), 422);
        }

        $wpdb->insert(sch_table('exams'), [
            'class_id'   => $class_id,
            'subject_id' => $subject_id,
            'title'      => $title,
            'exam_type'  => array_key_exists($d['exam_type'] ?? '', self::EXAM_TYPES) ? $d['exam_type'] : 'quiz',
            'exam_date'  => sch_sanitize_date($d['exam_date'] ?? null),
            'max_score'  => max(1, (float) ($d['max_score'] ?? 100)),
            'weight'     => max(0, min(100, (float) ($d['weight'] ?? 100))),
            'created_at' => sch_now(),
        ]);

        sch_audit('exam.created', 'exam', (int) $wpdb->insert_id);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function get_exam(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT e.*, s.name AS subject_name, c.grade_level, c.section
             FROM ' . sch_table('exams') . ' e
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = e.subject_id
             INNER JOIN ' . sch_table('classes') . ' c ON c.id = e.class_id
             WHERE e.id = %d',
            $id
        )) ?: null;
    }

    public static function exams(?int $class_id = null): array
    {
        global $wpdb;

        $sql = 'SELECT e.*, s.name AS subject_name, c.grade_level, c.section,
                       (SELECT COUNT(*) FROM ' . sch_table('exam_results') . ' r
                         WHERE r.exam_id = e.id AND r.score IS NOT NULL) AS graded
                FROM ' . sch_table('exams') . ' e
                INNER JOIN ' . sch_table('subjects') . ' s ON s.id = e.subject_id
                INNER JOIN ' . sch_table('classes') . ' c ON c.id = e.class_id';

        if ($class_id) {
            return $wpdb->get_results($wpdb->prepare(
                $sql . ' WHERE e.class_id = %d ORDER BY e.exam_date DESC, e.id DESC',
                $class_id
            )) ?: [];
        }

        return $wpdb->get_results($sql . ' ORDER BY e.exam_date DESC, e.id DESC LIMIT 100') ?: [];
    }

    /** اختبارات قادمة لشعبة — ما تاريخه اليوم أو بعده. */
    public static function upcoming_exams(int $class_id, int $limit = 10): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT e.*, s.name AS subject_name
             FROM ' . sch_table('exams') . ' e
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = e.subject_id
             WHERE e.class_id = %d AND e.exam_date IS NOT NULL AND e.exam_date >= %s
             ORDER BY e.exam_date
             LIMIT %d',
            $class_id,
            current_time('Y-m-d'),
            $limit
        )) ?: [];
    }

    /** كشف درجات اختبار — كل طلاب الشعبة مع درجاتهم إن وُجدت. */
    public static function result_sheet(int $exam_id): array
    {
        global $wpdb;

        $exam = self::get_exam($exam_id);
        if (!$exam) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.id, s.full_name, r.score, r.note
             FROM ' . sch_table('enrollments') . ' e
             INNER JOIN ' . sch_table('students') . ' s ON s.id = e.student_id
             LEFT JOIN ' . sch_table('exam_results') . ' r ON r.student_id = s.id AND r.exam_id = %d
             WHERE e.class_id = %d AND e.status = %s AND s.status = %s
             ORDER BY s.full_name',
            $exam_id,
            (int) $exam->class_id,
            'active',
            'active'
        )) ?: [];
    }

    /** حفظ درجات دفعة واحدة. */
    public static function save_scores(int $exam_id, array $scores): bool|WP_Error
    {
        global $wpdb;

        $exam = self::get_exam($exam_id);
        if (!$exam) {
            return sch_api_error('not_found', __('الاختبار غير موجود.', 'school-system'), 404);
        }

        foreach ($scores as $student_id => $raw) {
            $student_id = absint($student_id);
            if ($student_id === 0) {
                continue;
            }

            $score = ($raw === '' || $raw === null) ? null : (float) $raw;
            if ($score !== null && ($score < 0 || $score > (float) $exam->max_score)) {
                return sch_api_error('score_range', sprintf(
                    /* translators: %s: الدرجة العظمى */
                    __('الدرجة يجب أن تكون بين 0 و %s.', 'school-system'),
                    $exam->max_score
                ), 422);
            }

            $existing = $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . sch_table('exam_results') . ' WHERE exam_id = %d AND student_id = %d',
                $exam_id,
                $student_id
            ));

            $data = ['score' => $score, 'recorded_by' => get_current_user_id() ?: null, 'recorded_at' => sch_now()];

            if ($existing) {
                $wpdb->update(sch_table('exam_results'), $data, ['id' => (int) $existing]);
            } else {
                $wpdb->insert(sch_table('exam_results'), $data + ['exam_id' => $exam_id, 'student_id' => $student_id]);
            }
        }

        sch_audit('exam.graded', 'exam', $exam_id, ['count' => count($scores)]);
        return true;
    }

    /**
     * كشف درجات الطالب في كل المواد مع النسبة المرجّحة.
     *
     * **للسنة الجارية افتراضًا.** كان الاستعلام بلا قيدٍ على السنة، فتُطوى
     * نتائج الطالب **من سنواته كلّها** في متوسّطٍ واحد يُعرض في تطبيق الأسرة
     * وفي ملفّه بوصفه «المتوسّط العام» للسنة الحالية — فطالبٌ تحسّن هذا العام
     * يظلّ يُقرأ بمتوسّط سنواته السابقة. والامتحان يحمل `class_id`، والشعبة
     * تحمل `year_id`، فالضمّ يحسمها في الاستعلام نفسه.
     *
     * @param int|null $year_id سنةٌ بعينها، أو `null` للسنة الجارية
     */
    public static function report_card(int $student_id, ?int $year_id = null): array
    {
        global $wpdb;

        $year_id ??= SCH_Years::current_id();

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT e.id, e.title, e.exam_type, e.max_score, e.weight, e.exam_date,
                    s.name AS subject_name, r.score
             FROM ' . sch_table('exam_results') . ' r
             INNER JOIN ' . sch_table('exams') . ' e ON e.id = r.exam_id
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = e.subject_id
             INNER JOIN ' . sch_table('classes') . ' c ON c.id = e.class_id
             WHERE r.student_id = %d AND c.year_id = %d AND r.score IS NOT NULL
             ORDER BY s.name, e.exam_date',
            $student_id,
            $year_id
        )) ?: [];

        $by_subject = [];
        foreach ($rows as $r) {
            $subject = $r->subject_name;
            $by_subject[$subject]['exams'][] = $r;

            $pct = (float) $r->max_score > 0 ? ((float) $r->score / (float) $r->max_score) * 100 : 0;
            $by_subject[$subject]['weighted'] = ($by_subject[$subject]['weighted'] ?? 0) + $pct * ((float) $r->weight / 100);
            $by_subject[$subject]['weights']  = ($by_subject[$subject]['weights'] ?? 0) + ((float) $r->weight / 100);
        }

        foreach ($by_subject as $subject => $data) {
            $by_subject[$subject]['percent'] = $data['weights'] > 0
                ? round($data['weighted'] / $data['weights'], 1)
                : 0.0;
        }

        return $by_subject;
    }

    /** التقدير وفق سلم وزارة التعليم. */
    public static function grade_label(float $percent): string
    {
        return match (true) {
            $percent >= 95 => __('ممتاز مرتفع', 'school-system'),
            $percent >= 90 => __('ممتاز', 'school-system'),
            $percent >= 85 => __('جيد جدًا مرتفع', 'school-system'),
            $percent >= 80 => __('جيد جدًا', 'school-system'),
            $percent >= 75 => __('جيد مرتفع', 'school-system'),
            $percent >= 70 => __('جيد', 'school-system'),
            $percent >= 60 => __('مقبول', 'school-system'),
            default        => __('راسب', 'school-system'),
        };
    }
}
