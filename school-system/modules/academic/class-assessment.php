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

    /*
     * `assign()` و`of_class()` حُذفتا مع شاشة «المواد الدراسية».
     *
     * كانتا الكاتب والقارئ الوحيدَين خارج دورة الخطة. و`class_subjects`
     * تُبنى الآن في `SCH_TT::publish()` من نصاب الخطة المعتمدة — كاتبٌ واحد،
     * فلا يفترق ما يراه المعلم في «فصولي» عمّا يقوله الجدول.
     */

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

    /*
     * `set_slot()` و`clear_slot()` حُذفتا مع شاشة «المواد الدراسية».
     *
     * كانتا تكتبان في `timetable` مباشرةً — **وهو مُخرَج النشر لا مدخله**:
     * حصةٌ تُوضع من هناك لا خطةَ لها ولا نصاب ولا اعتماد، وأوّل «اعتماد» من
     * شاشة الجداول يمسحها بلا أثر (`publish()` تحذف صفوف الشعبة ثم تكتب من
     * الخطة). فالوكيل يبني نصف جدولٍ من شاشةٍ ويفقده من الأخرى.
     *
     * والكاتب الوحيد في هذا الجدول اليوم هو `SCH_TT::publish()`، والقراءة
     * تبقى هنا (`grid()` · `running_period()` · `school_day()`).
     */

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

    /*
     * `create_exam()` حُذفت مع نافذة «إنشاء اختبار» الحرّة.
     *
     * كانت تُنشئ اختبارًا بعنوانٍ حرّ ودرجةٍ عظمى ووزن — خارج أيّ دورة، فلا
     * موعد له ولا قاعة ولا اعتماد. والاختبارات تأتي دورات، والإنشاء صار في
     * `SCH_Exams::ensure_pair()` مربوطًا بشعبةٍ ومادةٍ ودورة.
     *
     * والصفوف القديمة تبقى: `session = 'legacy'` بعد الترحيل، تُقرأ في ملفّ
     * الطالب والشهادات كما كانت ولا تظهر في الشاشة الجديدة.
     */

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

    /**
     * اختبارات قادمة لشعبة — ما تاريخه اليوم أو بعده **وحُفظ جدوله**.
     *
     * `published_at` هي ما تضعه `SCH_Exams::save_schedule()`. وبدونها كان
     * وليّ الأمر يرى الموعد **لحظة وضعه على اليوم**، فيقرأ جدولًا يتغيّر
     * تحت عينه بينما الوكيل يجرّب مواضع المواد.
     */
    public static function upcoming_exams(int $class_id, int $limit = 10): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT e.*, s.name AS subject_name
             FROM ' . sch_table('exams') . ' e
             INNER JOIN ' . sch_table('subjects') . ' s ON s.id = e.subject_id
             WHERE e.class_id = %d AND e.exam_date IS NOT NULL AND e.exam_date >= %s
               AND e.published_at IS NOT NULL
             ORDER BY e.exam_date
             LIMIT %d',
            $class_id,
            current_time('Y-m-d'),
            $limit
        )) ?: [];
    }

    /*
     * `result_sheet()` و`save_scores()` حُذفتا مع كشف الدرجة الواحدة.
     *
     * الدرجة صارت **مركّبة** — أعمال سنةٍ ونهائيّ بوزنين — ولها حالة
     * (مرصودة · مُرسَلة · معتمدة) وعذرٌ يُقصي من المتوسط. وكشفٌ يكتب رقمًا
     * واحدًا بلا وزنٍ ولا حالة يتخطّى قفل الإرسال وحدّ كل نصف.
     *
     * والبديل `SCH_Exams::roster()` و`save_score()`.
     */

    /**
     * كشف درجات الطالب في كل المواد مع النسبة المرجّحة.
     *
     * **للسنة الجارية افتراضًا.** كان الاستعلام بلا قيدٍ على السنة، فتُطوى
     * نتائج الطالب **من سنواته كلّها** في متوسّطٍ واحد يُعرض في تطبيق الأسرة
     * وفي ملفّه بوصفه «المتوسّط العام» للسنة الحالية — فطالبٌ تحسّن هذا العام
     * يظلّ يُقرأ بمتوسّط سنواته السابقة. والامتحان يحمل `class_id`، والشعبة
     * تحمل `year_id`، فالضمّ يحسمها في الاستعلام نفسه.
     *
     * **والمعتمَد وحده.** هذه الدالّة منفذ كل ما تراه الأسرة — تطبيق وليّ
     * الأمر وملفّ الطالب والروضة وواجهة REST — فالاعتماد يُفحَص هنا مرّةً
     * لا في أربع شاشات تنسى إحداها. والدرجات المرصودة قبل هذه النسخة
     * عُلِّمت معتمدةً في الترحيل، فلا تختفي درجةٌ يراها أهلُها اليوم.
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
               AND e.approved_at IS NOT NULL
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
