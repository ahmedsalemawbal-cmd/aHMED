<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الشهادات.
 *
 * الشهادة ورقة تُعلَّق على جدار البيت ويصوّرها الأب لأهله — فتصميمها ليس ترفًا.
 * ولذلك بُنيت **رسمًا متجهًا (SVG)** لا صورة: تُطبع بأي حجم بلا تشوّه، وملفها
 * خفيف يصل تطبيق ولي الأمر بلا انتظار.
 *
 * **وثلاثة أنواع تُقترح آليًا** — الحضور المثالي والتفوّق والتحسّن. فبدل أن يبحث
 * الوكيل عن المستحقين، تفتح الشاشة فتجدهم. وهذا ما يجعل الميزة تُستخدم فعلًا
 * لا تُنسى بعد أسبوع.
 */
final class SCH_Certificates
{
    /** الأنواع: التسمية · العنوان على الشهادة · القالب المقترح · هل يُقترح آليًا. */
    public const TYPES = [
        'excellence' => ['التفوّق الدراسي', 'شهادة تفوّق',        'royal',   true],
        'attendance' => ['الحضور المثالي',  'لم يغب يومًا',       'modern',  true],
        'progress'   => ['التحسّن الملحوظ', 'شهادة تقدّم',        'modern',  true],
        'behavior'   => ['السلوك المتميّز', 'شهادة سلوك متميّز', 'classic', false],
        'quran'      => ['الحفظ والتلاوة',  'شهادة تميّز',        'classic', false],
        'activity'   => ['المشاركة والأنشطة', 'شهادة مشاركة',    'classic', false],
        'thanks'     => ['شكر وتقدير',      'شهادة شكر وتقدير',  'classic', false],
        'completion' => ['إتمام السنة',     'شهادة إتمام',        'sapphire', false],
    ];

    /** ثلاثة قوالب بصرية — لكل مناسبة ما يليق بها. */
    /** سقف الطباعة في الصفحة الواحدة — القالب المزخرف ~٧٠ كيلوبايت. */
    public const PRINT_BATCH = 50;

    /** تصنيفات المكتبة — سكّة التصفية في نافذة اختيار القالب. */
    public const CATEGORIES = [
        'formal'  => 'رسمية',
        'grad'    => 'تخرّج وإتمام',
        'honor'   => 'تكريم وتفوّق',
        'young'   => 'طفولة ورياض',
        'sport'   => 'رياضية وأنشطة',
        'quran'   => 'قرآنية',
    ];

    /**
     * سجلّ القوالب — لا ثابت من أربعة أسطر.
     *
     * كان كل قالب دالةً بأربعين سطرًا من SVG، فالقالب العشرون يعني ثمانمئة سطر
     * تُكتب يدويًا. الآن: القوالب الأربعة الأولى تحتفظ بدالّتها الخاصة (ميزة
     * معتمدة لا تُمسّ)، وما بعدها **وصف** تبنيه آلة واحدة من لوحة ألوان وتخطيط
     * وزخارف — فالقالب الجديد اثنا عشر سطرًا لا أربعون.
     *
     * المفاتيح: الاسم · التصنيف · وسوم البحث · التخطيط · اللوحة · الزخارف · دالة خاصة
     */
    public const TEMPLATES = [
        'royal' => [
            'name' => 'الملكية', 'cat' => 'formal', 'tags' => 'ذهب داكن فخم رسمي',
            'fn' => 'tpl_royal',
        ],
        'classic' => [
            'name' => 'الكلاسيكية', 'cat' => 'grad', 'tags' => 'إطار ذهبي تقليدي',
            'fn' => 'tpl_classic',
        ],
        'modern' => [
            'name' => 'الحديثة', 'cat' => 'honor', 'tags' => 'نظيفة خط كبير شريط جانبي',
            'fn' => 'tpl_modern',
        ],
        'sapphire' => [
            'name' => 'الياقوتية', 'cat' => 'formal', 'tags' => 'نيلي فضة عميق',
            'fn' => 'tpl_sapphire',
        ],

        // ── مبنيّة بالآلة: لوحة + تخطيط + زخارف ──
        'obsidian' => [
            'name' => 'السبج', 'cat' => 'formal', 'tags' => 'أسود فضة رصين',
            'layout' => 'centered', 'orn' => ['frame2', 'corners', 'seal', 'rope'],
            'pal' => ['bg' => '#0B0D12', 'bg2' => '#14171F', 'ink' => '#FFFFFF',
                      'soft' => '#9AA3B2', 'dim' => '#5C6472', 'gold' => '#C7CCD6'],
        ],
        'pearl' => [
            'name' => 'اللؤلؤية', 'cat' => 'formal', 'tags' => 'أبيض فاتح راقٍ بسيط',
            'layout' => 'minimal', 'orn' => ['rule', 'seal'],
            'pal' => ['bg' => '#FBFAF7', 'bg2' => '#F3F1EA', 'ink' => '#1A1A18',
                      'soft' => '#6B6A62', 'dim' => '#9A968C', 'gold' => '#A98F4C'],
        ],
        'laurel' => [
            'name' => 'الغار', 'cat' => 'grad', 'tags' => 'أخضر تخرّج إكليل',
            'layout' => 'centered', 'orn' => ['frame2', 'rosette', 'seal', 'rope'],
            'pal' => ['bg' => '#08221B', 'bg2' => '#0E3227', 'ink' => '#FFFFFF',
                      'soft' => '#9CC3B4', 'dim' => '#5F8577', 'gold' => '#CBA85A'],
        ],
        'crimson' => [
            'name' => 'القرمزية', 'cat' => 'honor', 'tags' => 'أحمر عنّابي تكريم',
            'layout' => 'band', 'orn' => ['seal', 'corners'],
            'pal' => ['bg' => '#FFFFFF', 'bg2' => '#7A1723', 'ink' => '#1B1013',
                      'soft' => '#6B4A50', 'dim' => '#9B858A', 'gold' => '#B08640'],
        ],
        'emerald' => [
            'name' => 'الزمردية', 'cat' => 'honor', 'tags' => 'أخضر زمرد تفوّق',
            'layout' => 'band', 'orn' => ['seal', 'rule'],
            'pal' => ['bg' => '#FFFFFF', 'bg2' => '#0B5140', 'ink' => '#0E1A16',
                      'soft' => '#4A6A60', 'dim' => '#8FA39B', 'gold' => '#C2A253'],
        ],
        'sunny' => [
            'name' => 'الشمسية', 'cat' => 'young', 'tags' => 'أصفر مرح أطفال روضة',
            'layout' => 'playful', 'orn' => ['confetti', 'seal'],
            'pal' => ['bg' => '#FFFDF4', 'bg2' => '#FFE9A8', 'ink' => '#3A2C06',
                      'soft' => '#8A7326', 'dim' => '#B8A45E', 'gold' => '#E8A317'],
        ],
        'bloom' => [
            'name' => 'الزهرية', 'cat' => 'young', 'tags' => 'وردي بنفسجي لطيف أطفال',
            'layout' => 'playful', 'orn' => ['confetti', 'seal'],
            'pal' => ['bg' => '#FFFAFD', 'bg2' => '#FBD9EC', 'ink' => '#3B1430',
                      'soft' => '#8A5378', 'dim' => '#BC94AE', 'gold' => '#C86FA6'],
        ],
        'track' => [
            'name' => 'المضمار', 'cat' => 'sport', 'tags' => 'رياضة نشاط أزرق حيوي',
            'layout' => 'band', 'orn' => ['corners', 'rule'],
            'pal' => ['bg' => '#FFFFFF', 'bg2' => '#14346E', 'ink' => '#0C1526',
                      'soft' => '#46587A', 'dim' => '#8D99AE', 'gold' => '#2E8BC0'],
        ],
        'mushaf' => [
            'name' => 'المصحفية', 'cat' => 'quran', 'tags' => 'قرآن حفظ تلاوة زخرفة',
            'layout' => 'centered', 'orn' => ['frame2', 'rosette', 'corners', 'seal'],
            'pal' => ['bg' => '#0D2A21', 'bg2' => '#123A2D', 'ink' => '#F6F1E0',
                      'soft' => '#B9C9AE', 'dim' => '#6F8A78', 'gold' => '#D8B75F'],
        ],
        'ivory' => [
            'name' => 'العاجية', 'cat' => 'grad', 'tags' => 'عاجي هادئ إتمام سنة',
            'layout' => 'minimal', 'orn' => ['rule', 'corners'],
            'pal' => ['bg' => '#FCFBF6', 'bg2' => '#EFEADC', 'ink' => '#22201A',
                      'soft' => '#6A6558', 'dim' => '#9E9787', 'gold' => '#9C7F3F'],
        ],
    ];

    /** اسم القالب — السجلّ يحمل مصفوفة، والشاشات تريد نصًّا. */
    public static function template_name(string $key): string
    {
        return (string) (self::TEMPLATES[$key]['name'] ?? $key);
    }

    /**
     * كم مرة استُعمل كل قالب في هذه المدرسة — مفتاح => عدد.
     *
     * «الأكثر استخدامًا» يُشتق من تاريخ المدرسة نفسها لا من قائمة نكتبها،
     * فالترتيب يعكس ذوقها هي. والنتيجة مخزَّنة دقيقة: تُقرأ في كل فتح للمكتبة.
     */
    public static function template_usage(): array
    {
        $cached = get_transient('sch_tpl_usage');

        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            'SELECT template, COUNT(*) AS n FROM ' . sch_table('certificates') . "
             WHERE status = 'valid' GROUP BY template"
        ) ?: [];

        $out = [];

        foreach ($rows as $r) {
            $out[(string) $r->template] = (int) $r->n;
        }

        set_transient('sch_tpl_usage', $out, 60);

        return $out;
    }

    // ---------- الإصدار ----------

    public static function issue(array $d): array|WP_Error
    {
        global $wpdb;

        $student_id = absint($d['student_id'] ?? 0);
        $type       = sanitize_key((string) ($d['type'] ?? ''));

        if (!isset(self::TYPES[$type])) {
            return sch_api_error('bad_type', __('نوع الشهادة غير معروف.', 'school-system'), 422);
        }

        $student = SCH_Students::get($student_id);

        if (!$student) {
            return sch_api_error('no_student', __('الطالب غير موجود.', 'school-system'), 404);
        }

        $template = sanitize_key((string) ($d['template'] ?? ''));

        if (!isset(self::TEMPLATES[$template])) {
            $template = self::TYPES[$type][2];
        }

        $year_id = SCH_Years::current_id();

        // حارس التكرار: طالب واحد لا ينال النوع نفسه مرتين في السنة.
        // القيد الحقيقي في القاعدة (uniq_cert_valid)، وهذا يسبقه برسالة تُفهَم
        // بدل خطأ قاعدة بيانات خام.
        $dupe = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('certificates') . '
             WHERE student_id = %d AND year_id = %d AND type = %s AND status = %s LIMIT 1',
            $student_id,
            $year_id,
            $type,
            'valid'
        ));

        if ($dupe > 0) {
            return sch_api_error('duplicate', sprintf(
                /* translators: 1: اسم الطالب 2: نوع الشهادة */
                __('نال %1$s «%2$s» هذا العام بالفعل.', 'school-system'),
                SCH_Enrollment::full_name($student),
                self::TYPES[$type][0]
            ), 409);
        }

        $serial = self::next_serial($year_id);

        $ok = $wpdb->insert(sch_table('certificates'), [
            'student_id' => $student_id,
            'year_id'    => $year_id,
            'type'       => $type,
            'template'   => $template,
            'title'      => sanitize_text_field((string) ($d['title'] ?? self::TYPES[$type][1])),
            'reason'     => sanitize_textarea_field((string) ($d['reason'] ?? '')),
            'serial'     => $serial,
            'issued_by'  => get_current_user_id() ?: null,
            'issued_at'  => sch_now(),
            'status'     => 'valid',
            // لقطة لحظة الإصدار: الوثيقة كانت تقرأ صف الطالب حيًّا، فإعادة طباعة
            // شهادة ١٤٤٧ بعد سنتين تطبع صفّه الجديد على وثيقة قديمة.
            'grade_snapshot'   => (string) ($student->grade_level ?? ''),
            'section_snapshot' => (string) ($student->section ?? ''),
            'name_snapshot'    => SCH_Enrollment::full_name($student),
        ]);

        if ($ok === false) {
            $why = trim((string) $wpdb->last_error);

            return sch_api_error('db_error', $why !== ''
                ? sprintf(
                    /* translators: %s: رسالة قاعدة البيانات */
                    __('تعذّر إصدار الشهادة — %s', 'school-system'),
                    $why
                )
                : __('تعذّر إصدار الشهادة.', 'school-system'), 500);
        }

        $id = (int) $wpdb->insert_id;

        // ولي الأمر يعرف فورًا — الشهادة خبر سارّ لا يُؤجَّل.
        foreach (SCH_Guardians::of_student($student_id) as $g) {
            SCH_Comms::notify(
                (int) $g->user_id,
                __('شهادة جديدة', 'school-system'),
                sprintf(
                    /* translators: 1: اسم الطالب 2: نوع الشهادة */
                    __('حصل %1$s على %2$s', 'school-system'),
                    SCH_Enrollment::full_name($student),
                    self::TYPES[$type][0]
                ),
                'certificate',
                $id
            );
        }

        sch_audit('certificate.issued', 'student', $student_id, ['type' => $type, 'serial' => $serial]);

        return ['id' => $id, 'serial' => $serial];
    }

    /**
     * إلغاء شهادة صدرت بالخطأ — بسبب مكتوب، ولا حذف.
     *
     * الحذف يزوّر الدفتر والإلغاء يوثّقه: الوثيقة تبقى برقمها التسلسلي وتُختَم
     * «ملغاة»، ويصل ولي الأمر خبرُ الإلغاء كما وصله خبر الإصدار. وقبل هذا لم
     * تكن في الوحدة كلها وسيلة سحب — الخطأ يخرج من المدرسة ولا يعود.
     */
    public static function revoke(int $id, string $reason): true|WP_Error
    {
        global $wpdb;

        $reason = trim(sanitize_textarea_field($reason));

        if ($reason === '') {
            return sch_api_error('no_reason', __('اكتب سبب الإلغاء — الإلغاء الصامت لا يُراجَع.', 'school-system'), 422);
        }

        $cert = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('certificates') . ' WHERE id = %d',
            $id
        ));

        if (!$cert) {
            return sch_api_error('not_found', __('الشهادة غير موجودة.', 'school-system'), 404);
        }

        if ((string) $cert->status === 'revoked') {
            return sch_api_error('already', __('الشهادة ملغاة أصلًا.', 'school-system'), 409);
        }

        $ok = $wpdb->update(
            sch_table('certificates'),
            [
                'status'        => 'revoked',
                'revoke_reason' => $reason,
                'revoked_by'    => get_current_user_id() ?: null,
                'revoked_at'    => sch_now(),
            ],
            ['id' => $id]
        );

        if ($ok === false) {
            return sch_api_error('db_error', __('تعذّر إلغاء الشهادة.', 'school-system'), 500);
        }

        // من عَلِم بالإصدار يَعلم بالإلغاء — وإلا بقيت في تطبيقه شهادة مسحوبة.
        foreach (SCH_Guardians::of_student((int) $cert->student_id) as $g) {
            SCH_Comms::notify(
                (int) $g->user_id,
                __('إلغاء شهادة', 'school-system'),
                sprintf(
                    /* translators: %s: عنوان الشهادة */
                    __('أُلغيت «%s». راجع إدارة المدرسة.', 'school-system'),
                    (string) $cert->title
                ),
                'certificate',
                $id
            );
        }

        sch_audit('certificate.revoked', 'student', (int) $cert->student_id, [
            'serial' => (string) $cert->serial,
            'reason' => $reason,
        ]);

        return true;
    }

    /** رقم تسلسلي لا يتكرر داخل السنة — الشهادة وثيقة تُراجَع. */
    private static function next_serial(int $year_id): string
    {
        global $wpdb;

        $n = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('certificates') . ' WHERE year_id = %d',
            $year_id
        ));

        $year = SCH_Years::current();

        return sprintf('SN-%s-%06d', $year->name ?? gmdate('Y'), $n + 1);
    }

    // ---------- القراءة ----------

    public static function of_student(int $student_id): array
    {
        global $wpdb;

        // الملغاة لا تظهر لولي الأمر ولا في ملف الطالب — تبقى في الدفتر للمراجعة
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('certificates') . "
             WHERE student_id = %d AND status = 'valid' ORDER BY id DESC",
            $student_id
        )) ?: [];
    }

    public static function get(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT c.*, s.full_name, s.first_name, s.father_name, s.grand_name, s.family_name,
                    s.academic_no, s.grade_level, s.section
             FROM ' . sch_table('certificates') . ' c
             INNER JOIN ' . sch_table('students') . ' s ON s.id = c.student_id
             WHERE c.id = %d',
            $id
        )) ?: null;
    }

    /**
     * بحث بالمرشّحات — الطالب أو الشعبة أو المرحلة أو النوع أو المدى.
     *
     * ولها استعمالان: عرض القائمة، وطباعة دفعة كاملة في ورقة لكل شهادة.
     */
    public static function search(array $args = []): array
    {
        global $wpdb;

        $where  = ['1=1'];
        $params = [];

        if (!empty($args['student_id'])) {
            $where[]  = 'c.student_id = %d';
            $params[] = (int) $args['student_id'];
        }

        if (!empty($args['class_id'])) {
            $where[]  = 'e.class_id = %d';
            $params[] = (int) $args['class_id'];
        }

        if (!empty($args['stage'])) {
            $where[]  = 's.stage = %s';
            $params[] = (string) $args['stage'];
        }

        if (!empty($args['grade_level'])) {
            $where[]  = 's.grade_level = %s';
            $params[] = (string) $args['grade_level'];
        }

        if (!empty($args['type'])) {
            $where[]  = 'c.type = %s';
            $params[] = (string) $args['type'];
        }

        if (!empty($args['from'])) {
            $where[]  = 'c.issued_at >= %s';
            $params[] = (string) $args['from'] . ' 00:00:00';
        }

        if (!empty($args['to'])) {
            $where[]  = 'c.issued_at <= %s';
            $params[] = (string) $args['to'] . ' 23:59:59';
        }

        $limit  = min(300, max(1, (int) ($args['limit'] ?? 60)));
        $offset = max(0, (int) ($args['offset'] ?? 0));

        $sql = 'SELECT c.*, s.full_name, s.first_name, s.father_name, s.grand_name, s.family_name,
                       s.academic_no, s.grade_level, s.section
                FROM ' . sch_table('certificates') . ' c
                INNER JOIN ' . sch_table('students') . ' s ON s.id = c.student_id
                LEFT JOIN ' . sch_table('enrollments') . " e
                       ON e.student_id = s.id AND e.status = 'active' AND e.year_id = c.year_id
                WHERE " . implode(' AND ', $where) . ' ORDER BY c.id DESC LIMIT ' . $limit
              . ($offset > 0 ? ' OFFSET ' . $offset : '');

        return $wpdb->get_results($params === [] ? $sql : $wpdb->prepare($sql, $params)) ?: [];
    }

    public static function recent(int $limit = 60): array
    {
        return self::search(['limit' => $limit]);
    }

    // ---------- الاقتراح الآلي ----------

    /**
     * من يستحقها الآن — النظام يبحث بدل الوكيل.
     *
     * @return array<string, array<int, object>>
     */
    public static function suggestions(): array
    {
        global $wpdb;

        $year = SCH_Years::current_id();

        // لا نقترح من نال الشهادة نفسها هذا العام.
        $taken = static function (string $type) use ($wpdb, $year): string {
            return $wpdb->prepare(
                ' AND s.id NOT IN (SELECT student_id FROM ' . sch_table('certificates') . '
                                   WHERE type = %s AND year_id = %d)',
                $type,
                $year
            );
        };

        $out = [];

        // ١) لم يغب يومًا خلال آخر ٦٠ يومًا دراسيًا.
        $out['attendance'] = $wpdb->get_results(
            "SELECT s.id, s.full_name, s.grade_level, s.section, s.academic_no
             FROM " . sch_table('students') . " s
             WHERE s.status = 'active'
               AND EXISTS (SELECT 1 FROM " . sch_table('attendance') . " a
                           WHERE a.student_id = s.id)
               AND NOT EXISTS (SELECT 1 FROM " . sch_table('attendance') . " a
                               WHERE a.student_id = s.id AND a.status IN ('absent','late'))"
            . $taken('attendance') . ' ORDER BY s.full_name LIMIT 60'
        ) ?: [];

        // ٢) معدّل ٩٥٪ فأعلى.
        $out['excellence'] = $wpdb->get_results(
            "SELECT s.id, s.full_name, s.grade_level, s.section, s.academic_no,
                    ROUND(AVG(r.score / NULLIF(e.max_score, 0)) * 100, 1) AS pct
             FROM " . sch_table('students') . " s
             INNER JOIN " . sch_table('exam_results') . " r ON r.student_id = s.id
             INNER JOIN " . sch_table('exams') . " e ON e.id = r.exam_id
             WHERE s.status = 'active' AND r.score IS NOT NULL"
            . $taken('excellence') .
            " GROUP BY s.id HAVING pct >= 95 ORDER BY pct DESC LIMIT 60"
        ) ?: [];

        return $out;
    }

    // ---------- الرسم ----------

    /**
     * الشهادة رسمًا متجهًا.
     *
     * تُطبع بأي حجم بلا تشوّه، وملفها خفيف. والنقش الحلزوني مرسوم بمعادلة
     * رياضية لا صورة — فلا يُقلَّد بالنسخ.
     */
    public static function svg(object $cert, bool $print = false): string
    {
        $w = 1123;
        $h = 794;

        $school = sch_settings('school_name', get_bloginfo('name'));

        // اللقطة المجمَّدة أولًا ثم الحيّ للشهادات السابقة لهذه الميزة: الوثيقة
        // تحمل صاحبها وصفَّه كما كانا لحظة الإصدار، فلا يتبدّل متنها بعد سنتين.
        $name  = (string) ($cert->name_snapshot ?? '') !== ''
            ? (string) $cert->name_snapshot
            : SCH_Enrollment::full_name($cert);

        $grade = (string) ($cert->grade_snapshot ?? '') !== ''
            ? (string) $cert->grade_snapshot
            : (string) ($cert->grade_level ?? '');

        $sect  = (string) ($cert->section_snapshot ?? '') !== ''
            ? (string) $cert->section_snapshot
            : (string) ($cert->section ?? '');

        $klass  = trim($grade . ' / ' . $sect, ' /');
        $reason = (string) ($cert->reason ?: '');
        $title  = (string) ($cert->title ?: (self::TYPES[$cert->type][1] ?? ''));
        $serial = (string) $cert->serial;
        $date   = wp_date('j F Y', strtotime((string) $cert->issued_at));

        // القوالب الأربعة الأولى لها دوالّها الخاصة (ميزة معتمدة لا تُمسّ)،
        // وما بعدها يبنيه المحرّك من وصفه في السجلّ.
        $key = (string) $cert->template;
        $tpl = self::TEMPLATES[$key] ?? self::TEMPLATES['royal'];

        if (isset($tpl['fn']) && method_exists(self::class, (string) $tpl['fn'])) {
            $fn   = (string) $tpl['fn'];
            $body = self::$fn($w, $h, $school, $name, $klass, $title, $reason, $serial, $date);
        } else {
            $body = self::tpl_auto($tpl, $w, $h, $school, $name, $klass, $title, $reason, $serial, $date);
        }

        // الملغاة تُختَم على وجهها: نسخة مطبوعة سابقًا قد تدور، والوثيقة نفسها
        // يجب أن تقول إنها لم تعد سارية.
        if ((string) ($cert->status ?? 'valid') === 'revoked') {
            $body .= '<g transform="rotate(-24 ' . ($w / 2) . ' ' . ($h / 2) . ')" opacity=".38">'
                . '<rect x="' . (($w / 2) - 300) . '" y="' . (($h / 2) - 62) . '" width="600" height="124" '
                . 'fill="none" stroke="#B3261E" stroke-width="7" rx="12"/>'
                . '<text x="' . ($w / 2) . '" y="' . (($h / 2) + 26) . '" text-anchor="middle" '
                . 'fill="#B3261E" font-size="72" font-weight="700">'
                . esc_html__('ملغاة', 'school-system') . '</text></g>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" '
            . ($print ? 'width="100%" height="100%" preserveAspectRatio="xMidYMid meet"' : 'class="sch-cert__svg"')
            . ' role="img" aria-label="' . esc_attr($title . ' — ' . $name) . '">' . $body . '</svg>';
    }

    /**
     * الآلة: تبني قالبًا كاملًا من وصفه — لوحة ألوان وتخطيط وزخارف.
     *
     * أربعة تخطيطات تغطّي ما تحتاجه مدرسة: `centered` رسمي متمركز ·
     * `band` بشريط جانبي ملوّن · `minimal` بمساحة بيضاء وخطوط رفيعة ·
     * `playful` للصغار. والزخارف تُركَّب من المساعدات القائمة نفسها.
     */
    private static function tpl_auto(array $tpl, int $w, int $h, string $school, string $name,
                                     string $klass, string $title, string $reason,
                                     string $serial, string $date): string
    {
        $p   = $tpl['pal'];
        $orn = (array) ($tpl['orn'] ?? []);
        $lay = (string) ($tpl['layout'] ?? 'centered');
        $c   = $w / 2;
        $has = static fn (string $k): bool => in_array($k, $orn, true);

        [$l1, $l2] = self::two_lines($reason ?: 'تقديرًا لجهده والتزامه خلال العام الدراسي');

        $dark = $lay !== 'band' && $lay !== 'minimal';
        $svg  = '';

        // ── الأرضية ──
        if ($lay === 'band') {
            // شريط جانبي ملوّن يترك المتن كاملًا للنص (يبدأ من اليمين — RTL)
            $bw   = 168;
            $svg .= '<rect width="' . $w . '" height="' . $h . '" fill="' . $p['bg'] . '"/>'
                 . '<rect x="' . ($w - $bw) . '" y="0" width="' . $bw . '" height="' . $h . '" fill="' . $p['bg2'] . '"/>'
                 . '<rect x="' . ($w - $bw - 5) . '" y="0" width="5" height="' . $h . '" fill="' . $p['gold'] . '"/>';
        } elseif ($lay === 'playful') {
            $svg .= '<rect width="' . $w . '" height="' . $h . '" fill="' . $p['bg'] . '"/>'
                 . '<rect x="26" y="26" width="' . ($w - 52) . '" height="' . ($h - 52) . '" rx="34" fill="' . $p['bg2'] . '" opacity=".55"/>'
                 . '<rect x="26" y="26" width="' . ($w - 52) . '" height="' . ($h - 52) . '" rx="34" fill="none" stroke="' . $p['gold'] . '" stroke-width="3"/>';
        } else {
            $svg .= '<rect width="' . $w . '" height="' . $h . '" fill="' . $p['bg'] . '"/>';
            if ($lay === 'centered') {
                $svg .= '<rect width="' . $w . '" height="' . $h . '" fill="' . $p['bg2'] . '" opacity=".55"/>';
            }
        }

        // ── الزخارف ──
        if ($has('rosette')) {
            $svg .= self::rosette($c, 400, 250, 63, 145, $p['gold'], .3, .13)
                 .  self::rosette($c, 400, 170, 47, 96, $p['gold'], .26, .09);
        }

        if ($has('confetti')) {
            // نقاط ثابتة لا عشوائية — الوثيقة نفسها تُطبع مرتين فتتطابق
            $dots = [[92, 120], [1030, 150], [140, 660], [980, 690], [70, 380],
                     [1050, 420], [230, 92], [890, 706], [320, 700], [800, 96]];
            foreach ($dots as $i => [$dx, $dy]) {
                $svg .= '<circle cx="' . $dx . '" cy="' . $dy . '" r="' . (6 + ($i % 3) * 3)
                     . '" fill="' . ($i % 2 ? $p['gold'] : $p['soft']) . '" opacity=".38"/>';
            }
        }

        if ($has('frame2')) {
            $svg .= '<rect x="24" y="24" width="' . ($w - 48) . '" height="' . ($h - 48) . '" fill="none" stroke="' . $p['gold'] . '" stroke-width="3"/>'
                 .  '<rect x="35" y="35" width="' . ($w - 70) . '" height="' . ($h - 70) . '" fill="none" stroke="' . $p['gold'] . '" stroke-width=".7" opacity=".5"/>';
        }

        if ($has('corners')) {
            foreach ([[56, 56], [$w - 56, 56], [56, $h - 56], [$w - 56, $h - 56]] as [$sx, $sy]) {
                if ($lay === 'band' && $sx > $w - 200) { continue; }
                $svg .= self::star($sx, $sy, 15, $p['gold'], 1.1, .8);
            }
        }

        // ── النص ──
        $tx    = ($lay === 'band') ? (($w - 168) / 2) : $c;
        $inkc  = $p['ink'];
        $softc = $p['soft'];

        $svg .= '<text x="' . $tx . '" y="112" text-anchor="middle" fill="' . $softc . '" font-size="17">' . esc_html($school) . '</text>';

        if ($has('rope')) {
            $svg .= self::rope($tx - 95, $tx + 95, 132, $p['gold']);
        }

        if ($has('rule')) {
            $svg .= '<line x1="' . ($tx - 120) . '" y1="132" x2="' . ($tx + 120) . '" y2="132" stroke="' . $p['gold'] . '" stroke-width="1.4"/>';
        }

        $svg .= '<text x="' . $tx . '" y="206" text-anchor="middle" fill="' . $p['gold'] . '" font-size="52" font-weight="bold">' . esc_html($title) . '</text>'
             .  '<text x="' . $tx . '" y="270" text-anchor="middle" fill="' . $softc . '" font-size="17">تشهد إدارة المدرسة بأن الطالب</text>'
             .  '<text x="' . $tx . '" y="350" text-anchor="middle" fill="' . $inkc . '" font-size="50" font-weight="bold">' . esc_html($name) . '</text>';

        if ($has('rope')) {
            $svg .= self::rope($tx - 230, $tx + 230, 374, $p['gold']);
        } else {
            $svg .= '<line x1="' . ($tx - 200) . '" y1="374" x2="' . ($tx + 200) . '" y2="374" stroke="' . $p['dim'] . '" stroke-width="1"/>';
        }

        $svg .= '<text x="' . $tx . '" y="420" text-anchor="middle" fill="' . $softc . '" font-size="18">' . esc_html($l1) . '</text>';

        if ($l2 !== '') {
            $svg .= '<text x="' . $tx . '" y="452" text-anchor="middle" fill="' . $softc . '" font-size="18">' . esc_html($l2) . '</text>';
        }

        $svg .= '<text x="' . $tx . '" y="' . ($l2 !== '' ? 494 : 462) . '" text-anchor="middle" fill="' . $p['dim'] . '" font-size="15">' . esc_html($klass) . '</text>';

        if ($has('seal')) {
            $svg .= self::seal($tx, 596, 40, $p['gold'], $dark ? $p['bg'] : $p['bg2']);
        }

        // ── التوقيعات والرقم ──
        $sy   = 700;
        $lft  = ($lay === 'band') ? 150 : 175;
        $rgt  = ($lay === 'band') ? ($w - 168 - 150) : ($w - 175);

        $svg .= '<line x1="' . $lft . '" y1="' . $sy . '" x2="' . ($lft + 200) . '" y2="' . $sy . '" stroke="' . $p['dim'] . '" stroke-width="1"/>'
             .  '<text x="' . ($lft + 100) . '" y="' . ($sy + 22) . '" text-anchor="middle" fill="' . $softc . '" font-size="13">مدير المدرسة</text>'
             .  '<line x1="' . ($rgt - 200) . '" y1="' . $sy . '" x2="' . $rgt . '" y2="' . $sy . '" stroke="' . $p['dim'] . '" stroke-width="1"/>'
             .  '<text x="' . ($rgt - 100) . '" y="' . ($sy + 22) . '" text-anchor="middle" fill="' . $softc . '" font-size="13">المشرف التربوي</text>'
             .  '<text x="' . $tx . '" y="752" text-anchor="middle" fill="' . $p['dim'] . '" font-size="11">' . esc_html($serial) . '</text>'
             .  '<text x="' . $tx . '" y="772" text-anchor="middle" fill="' . $p['dim'] . '" font-size="11">' . esc_html($date) . '</text>';

        return $svg;
    }

    /**
     * معاينة القالب ببيانات نموذجية — القالب الحقيقي لا مربّع تدرّج لوني.
     *
     * أول رؤية للوثيقة كانت تأتي **بعد** الإصدار؛ وهذه تجعلها قبله.
     */
    public static function preview_svg(string $template): string
    {
        $key = isset(self::TEMPLATES[$template]) ? $template : array_key_first(self::TEMPLATES);

        $sample = (object) [
            'template'      => $key,
            'title'         => 'شهادة تفوّق',
            'reason'        => 'لتفوّقه ومثابرته خلال الفصل الدراسي',
            'serial'        => '1447-0001',
            'issued_at'     => sch_now(),
            'status'        => 'valid',
            'name_snapshot' => 'محمد عبدالله الأحمد',
            'grade_snapshot'   => 'الصف الأول',
            'section_snapshot' => 'أ',
            'type'          => 'excellence',
        ];

        return self::svg($sample);
    }

    /** وردة سبيروغراف — نقش الأوراق المالية، بمعادلة لا بصورة. */
    private static function rosette(float $cx, float $cy, float $R, float $r, float $d,
                                    string $color, float $sw = .45, float $op = .3): string
    {
        $g     = self::gcd((int) $R, (int) $r);
        $turns = max(1, (int) ($r / max(1, $g)));
        $steps = 2600;
        $pts   = [];

        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps * 2 * M_PI * $turns;
            $x = ($R - $r) * cos($t) + $d * cos(($R - $r) / $r * $t);
            $y = ($R - $r) * sin($t) - $d * sin(($R - $r) / $r * $t);
            $pts[] = round($cx + $x, 1) . ',' . round($cy + $y, 1);
        }

        return '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $color
            . '" stroke-width="' . $sw . '" opacity="' . $op . '"/>';
    }

    private static function gcd(int $a, int $b): int
    {
        return $b === 0 ? max(1, $a) : self::gcd($b, $a % $b);
    }

    /** نجمة ثمانية — الوحدة الأساسية في الزخرفة الإسلامية. */
    private static function star(float $cx, float $cy, float $r, string $color, float $sw = 1, float $op = .35): string
    {
        $mk = static function (int $off) use ($cx, $cy, $r): string {
            $p = [];
            for ($a = $off; $a < 360 + $off; $a += 90) {
                $p[] = round($cx + $r * cos(deg2rad($a)), 1) . ',' . round($cy + $r * sin(deg2rad($a)), 1);
            }

            return implode(' ', $p);
        };

        return '<polygon points="' . $mk(0) . '" fill="none" stroke="' . $color . '" stroke-width="' . $sw . '" opacity="' . $op . '"/>'
             . '<polygon points="' . $mk(45) . '" fill="none" stroke="' . $color . '" stroke-width="' . $sw . '" opacity="' . $op . '"/>';
    }

    /** ختم مسنّن — العنصر الذي يجعل الورقة تبدو صادرة من جهة. */
    private static function seal(float $cx, float $cy, float $r, string $ring, string $core): string
    {
        $teeth = '';

        for ($a = 0; $a < 360; $a += 5) {
            $x1 = $cx + ($r + 2) * cos(deg2rad($a));
            $y1 = $cy + ($r + 2) * sin(deg2rad($a));
            $x2 = $cx + ($r + 9) * cos(deg2rad($a));
            $y2 = $cy + ($r + 9) * sin(deg2rad($a));
            $teeth .= '<line x1="' . round($x1, 1) . '" y1="' . round($y1, 1) . '" x2="' . round($x2, 1)
                . '" y2="' . round($y2, 1) . '" stroke="' . $ring . '" stroke-width="1.5"/>';
        }

        return '<g>' . $teeth
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $core . '"/>'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($r - 6) . '" fill="none" stroke="' . $ring . '" stroke-width="1.1" opacity=".85"/>'
            . self::star($cx, $cy, $r - 15, $ring, 1.3, .95)
            . self::star($cx, $cy, $r - 22, $ring, .8, .6)
            . '</g>';
    }

    /** خيط محبّب — يفصل بلا ثقل الخط المستقيم. */
    private static function rope(float $x1, float $x2, float $y, string $color): string
    {
        $mid = ($x1 + $x2) / 2;

        return '<line x1="' . $x1 . '" y1="' . $y . '" x2="' . $x2 . '" y2="' . $y . '" stroke="' . $color . '" stroke-width=".9"/>'
            . '<circle cx="' . $mid . '" cy="' . $y . '" r="3.2" fill="' . $color . '"/>'
            . '<circle cx="' . ($mid - 14) . '" cy="' . $y . '" r="1.6" fill="' . $color . '"/>'
            . '<circle cx="' . ($mid + 14) . '" cy="' . $y . '" r="1.6" fill="' . $color . '"/>';
    }

    /** سطران على الأكثر — الشهادة لا تحتمل فقرة. */
    private static function two_lines(string $text, int $per = 52): array
    {
        $text = trim($text);

        if ($text === '') {
            return ['', ''];
        }

        if (mb_strlen($text) <= $per) {
            return [$text, ''];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $a     = '';
        $b     = '';

        foreach ($words as $word) {
            if (mb_strlen($a . ' ' . $word) <= $per) {
                $a = trim($a . ' ' . $word);
            } else {
                $b = trim($b . ' ' . $word);
            }
        }

        return [$a, mb_substr($b, 0, $per)];
    }

    private static function tpl_royal(int $w, int $h, string $school, string $name, string $klass,
                                      string $title, string $reason, string $serial, string $date): string
    {
        [$l1, $l2] = self::two_lines($reason ?: 'لتفوّقه ومثابرته خلال الفصل الدراسي');
        $c = $w / 2;

        return '<defs>
  <linearGradient id="cg" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0%" stop-color="#8C6A22"/><stop offset="30%" stop-color="#EFD89A"/>
    <stop offset="52%" stop-color="#C9A44C"/><stop offset="78%" stop-color="#F0DCA4"/>
    <stop offset="100%" stop-color="#7A5A1C"/>
  </linearGradient>
  <linearGradient id="cd" x1="0" y1="0" x2="0.3" y2="1">
    <stop offset="0%" stop-color="#111A44"/><stop offset="60%" stop-color="#0A0F30"/><stop offset="100%" stop-color="#070B24"/>
  </linearGradient>
  <radialGradient id="ch" cx="50%" cy="42%" r="55%">
    <stop offset="0%" stop-color="#243596" stop-opacity=".5"/><stop offset="100%" stop-color="#070B24" stop-opacity="0"/>
  </radialGradient>
</defs>
<rect width="' . $w . '" height="' . $h . '" fill="url(#cd)"/>
<rect width="' . $w . '" height="' . $h . '" fill="url(#ch)"/>'
. self::rosette($c, 400, 250, 63, 145, '#C9A44C', .35, .16)
. self::rosette($c, 400, 170, 47, 96, '#C9A44C', .3, .1)
. '<rect x="24" y="24" width="' . ($w - 48) . '" height="' . ($h - 48) . '" fill="none" stroke="url(#cg)" stroke-width="3"/>
<rect x="35" y="35" width="' . ($w - 70) . '" height="' . ($h - 70) . '" fill="none" stroke="#C9A44C" stroke-width=".7" opacity=".55"/>'
. self::star(56, 56, 16, '#C9A44C', 1.2, .9) . self::star($w - 56, 56, 16, '#C9A44C', 1.2, .9)
. self::star(56, $h - 56, 16, '#C9A44C', 1.2, .9) . self::star($w - 56, $h - 56, 16, '#C9A44C', 1.2, .9)
. '<text x="' . $c . '" y="108" text-anchor="middle" fill="#9BB5AE" font-size="17">' . esc_html($school) . '</text>'
. self::rope($c - 95, $c + 95, 126, '#C9A44C')
. '<text x="' . $c . '" y="200" text-anchor="middle" fill="url(#cg)" font-size="54" font-weight="bold">' . esc_html($title) . '</text>
<text x="' . $c . '" y="266" text-anchor="middle" fill="#8FA69F" font-size="17">تشهد إدارة المدرسة بأن الطالب</text>
<text x="' . $c . '" y="348" text-anchor="middle" fill="#FFFFFF" font-size="50" font-weight="bold">' . esc_html($name) . '</text>'
. self::rope($c - 240, $c + 240, 372, '#C9A44C')
. '<text x="' . $c . '" y="418" text-anchor="middle" fill="#A9BEB8" font-size="18">' . esc_html($l1) . '</text>'
. ($l2 !== '' ? '<text x="' . $c . '" y="450" text-anchor="middle" fill="#A9BEB8" font-size="18">' . esc_html($l2) . '</text>' : '')
. '<text x="' . $c . '" y="' . ($l2 !== '' ? 492 : 460) . '" text-anchor="middle" fill="#7E938D" font-size="15">' . esc_html($klass) . '</text>'
. self::seal($c, 596, 42, '#C9A44C', '#111A44')
. '<line x1="175" y1="700" x2="375" y2="700" stroke="#5E7570" stroke-width="1"/>
<text x="275" y="722" text-anchor="middle" fill="#8FA69F" font-size="13">مدير المدرسة</text>
<line x1="' . ($w - 375) . '" y1="700" x2="' . ($w - 175) . '" y2="700" stroke="#5E7570" stroke-width="1"/>
<text x="' . ($w - 275) . '" y="722" text-anchor="middle" fill="#8FA69F" font-size="13">المشرف التربوي</text>
<text x="' . $c . '" y="752" text-anchor="middle" fill="#4F6660" font-size="11" letter-spacing="2">' . esc_html($serial) . '</text>';
    }

    private static function tpl_classic(int $w, int $h, string $school, string $name, string $klass,
                                        string $title, string $reason, string $serial, string $date): string
    {
        [$l1, $l2] = self::two_lines($reason ?: 'لتميّزه ومشاركته الفاعلة في الأنشطة المدرسية');
        $c = $w / 2;

        return '<defs>
  <linearGradient id="cb" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0%" stop-color="#9A7B33"/><stop offset="28%" stop-color="#E5CD8C"/>
    <stop offset="55%" stop-color="#C0A055"/><stop offset="100%" stop-color="#8A6B26"/>
  </linearGradient>
</defs>
<rect width="' . $w . '" height="' . $h . '" fill="#FDFBF5"/>'
. '<g opacity=".09">' . self::rosette($c, $h / 2, 300, 71, 168, '#7A5A1C', .45, 1) . '</g>'
. '<rect x="28" y="28" width="' . ($w - 56) . '" height="' . ($h - 56) . '" fill="none" stroke="url(#cb)" stroke-width="8"/>
<rect x="44" y="44" width="' . ($w - 88) . '" height="' . ($h - 88) . '" fill="none" stroke="#B99A4E" stroke-width="1.2"/>
<rect x="53" y="53" width="' . ($w - 106) . '" height="' . ($h - 106) . '" fill="none" stroke="#B99A4E" stroke-width=".6" opacity=".55"/>'
. self::star(80, 80, 19, '#B99A4E', 1.3, .85) . self::star($w - 80, 80, 19, '#B99A4E', 1.3, .85)
. self::star(80, $h - 80, 19, '#B99A4E', 1.3, .85) . self::star($w - 80, $h - 80, 19, '#B99A4E', 1.3, .85)
. '<text x="' . $c . '" y="124" text-anchor="middle" fill="#7A6432" font-size="17">بسم الله الرحمن الرحيم</text>
<text x="' . $c . '" y="188" text-anchor="middle" fill="#123F38" font-size="21">' . esc_html($school) . '</text>'
. self::rope($c - 125, $c + 125, 208, '#B99A4E')
. '<text x="' . $c . '" y="278" text-anchor="middle" fill="#0E332D" font-size="48" font-weight="bold">' . esc_html($title) . '</text>
<text x="' . $c . '" y="342" text-anchor="middle" fill="#5A6B66" font-size="17">تتقدّم إدارة المدرسة بخالص الشكر والتقدير للطالب</text>
<text x="' . $c . '" y="420" text-anchor="middle" fill="#0E332D" font-size="48" font-weight="bold">' . esc_html($name) . '</text>'
. self::rope($c - 255, $c + 255, 444, '#B99A4E')
. '<text x="' . $c . '" y="492" text-anchor="middle" fill="#5A6B66" font-size="17">' . esc_html($l1) . '</text>'
. ($l2 !== '' ? '<text x="' . $c . '" y="524" text-anchor="middle" fill="#5A6B66" font-size="17">' . esc_html($l2) . '</text>' : '')
. '<text x="' . $c . '" y="' . ($l2 !== '' ? 566 : 534) . '" text-anchor="middle" fill="#8A7440" font-size="16">سائلين الله له دوام التوفيق والسداد</text>'
. self::seal($c, 638, 38, '#B99A4E', '#123F38')
. '<line x1="150" y1="706" x2="340" y2="706" stroke="#C3B489" stroke-width="1"/>
<text x="245" y="728" text-anchor="middle" fill="#7A6432" font-size="13">مدير المدرسة</text>
<line x1="' . ($w - 340) . '" y1="706" x2="' . ($w - 150) . '" y2="706" stroke="#C3B489" stroke-width="1"/>
<text x="' . ($w - 245) . '" y="728" text-anchor="middle" fill="#7A6432" font-size="13">رائد النشاط</text>
<text x="' . $c . '" y="750" text-anchor="middle" fill="#A79761" font-size="11" letter-spacing="2">' . esc_html($serial) . '</text>';
    }

    private static function tpl_modern(int $w, int $h, string $school, string $name, string $klass,
                                       string $title, string $reason, string $serial, string $date): string
    {
        [$l1, $l2] = self::two_lines($reason ?: 'تُمنح تقديرًا لالتزامه وحضوره كامل أيام الفصل', 54);
        $c = $w / 2;

        return '<defs>
  <linearGradient id="cm" x1="1" y1="0" x2="0" y2="1">
    <stop offset="0%" stop-color="#1B287A"/><stop offset="100%" stop-color="#7B92FF"/>
  </linearGradient>
</defs>
<rect width="' . $w . '" height="' . $h . '" fill="#FBFAF7"/>

<!-- شريط جانبي لا علوي: يترك المتن كاملًا للنص ويوازن الصفحة -->
<path d="M' . ($w - 132) . ' 0 H' . $w . ' V' . $h . ' H' . ($w - 92) . ' Z" fill="url(#cm)"/>'
. '<g opacity=".06">' . self::rosette($c - 40, 430, 250, 63, 145, '#5170FF', .5, 1) . '</g>'
. '<text transform="translate(' . ($w - 52) . ' ' . ($h / 2) . ') rotate(90)" text-anchor="middle" fill="#D5DFFF" font-size="17" font-weight="bold">'
. esc_html($school) . '</text>

<rect x="70" y="70" width="' . ($w - 240) . '" height="' . ($h - 140) . '" fill="none" stroke="#E3DFD6" stroke-width="1.5"/>

<text x="' . ($c - 44) . '" y="176" text-anchor="middle" fill="#5170FF" font-size="15">' . esc_html($klass) . '</text>
<text x="' . ($c - 44) . '" y="266" text-anchor="middle" fill="#111C19" font-size="52" font-weight="bold">' . esc_html($title) . '</text>'
. self::rope($c - 44 - 130, $c - 44 + 130, 300, '#7B92FF')
. '<text x="' . ($c - 44) . '" y="360" text-anchor="middle" fill="#5A6B66" font-size="17">تُمنح هذه الشهادة للطالب</text>
<text x="' . ($c - 44) . '" y="440" text-anchor="middle" fill="#1B287A" font-size="46" font-weight="bold">' . esc_html($name) . '</text>
<line x1="' . ($c - 44 - 230) . '" y1="466" x2="' . ($c - 44 + 230) . '" y2="466" stroke="#E3DFD6" stroke-width="1.5"/>
<text x="' . ($c - 44) . '" y="512" text-anchor="middle" fill="#5A6B66" font-size="17">' . esc_html($l1) . '</text>'
. ($l2 !== '' ? '<text x="' . ($c - 44) . '" y="542" text-anchor="middle" fill="#5A6B66" font-size="17">' . esc_html($l2) . '</text>' : '')
. self::seal($c - 44, 622, 34, '#7B92FF', '#1B287A')
. '<line x1="150" y1="700" x2="330" y2="700" stroke="#C9C3B6" stroke-width="1"/>
<text x="240" y="722" text-anchor="middle" fill="#8B9793" font-size="12">مدير المدرسة</text>
<text x="' . ($w - 200) . '" y="704" text-anchor="middle" fill="#8B9793" font-size="12">' . esc_html($date) . '</text>
<text x="' . ($w - 200) . '" y="724" text-anchor="middle" fill="#B0B9B5" font-size="11" letter-spacing="1">' . esc_html($serial) . '</text>';
    }

    /**
     * الياقوتية — أزرق عميق وفضة.
     *
     * لون لا تجده في شهادات المدارس: يليق بالتخرّج وإتمام السنة، ويُميّز
     * شهادتك عن كل ما رآه الأب من قبل.
     */
    private static function tpl_sapphire(int $w, int $h, string $school, string $name, string $klass,
                                         string $title, string $reason, string $serial, string $date): string
    {
        [$l1, $l2] = self::two_lines($reason ?: 'لإتمامه العام الدراسي بنجاح وتميّز');
        $c = $w / 2;

        return '<defs>
  <linearGradient id="sv" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0%" stop-color="#6E7B92"/><stop offset="28%" stop-color="#E6ECF5"/>
    <stop offset="52%" stop-color="#A9B6CC"/><stop offset="78%" stop-color="#EEF3FA"/>
    <stop offset="100%" stop-color="#5D6A80"/>
  </linearGradient>
  <linearGradient id="sd" x1="0" y1="0" x2="0.3" y2="1">
    <stop offset="0%" stop-color="#16233F"/><stop offset="55%" stop-color="#0D1730"/><stop offset="100%" stop-color="#070E1F"/>
  </linearGradient>
  <radialGradient id="sh" cx="50%" cy="40%" r="58%">
    <stop offset="0%" stop-color="#283C6B" stop-opacity=".55"/><stop offset="100%" stop-color="#070E1F" stop-opacity="0"/>
  </radialGradient>
</defs>
<rect width="' . $w . '" height="' . $h . '" fill="url(#sd)"/>
<rect width="' . $w . '" height="' . $h . '" fill="url(#sh)"/>'
. self::rosette($c, 392, 262, 59, 152, '#8FA0C0', .35, .2)
. self::rosette($c, 392, 178, 41, 98, '#8FA0C0', .3, .12)
. '<rect x="24" y="24" width="' . ($w - 48) . '" height="' . ($h - 48) . '" fill="none" stroke="url(#sv)" stroke-width="3"/>
<rect x="35" y="35" width="' . ($w - 70) . '" height="' . ($h - 70) . '" fill="none" stroke="#A9B6CC" stroke-width=".7" opacity=".5"/>'
. self::star(56, 56, 16, '#C6D2E6', 1.2, .9) . self::star($w - 56, 56, 16, '#C6D2E6', 1.2, .9)
. self::star(56, $h - 56, 16, '#C6D2E6', 1.2, .9) . self::star($w - 56, $h - 56, 16, '#C6D2E6', 1.2, .9)
. '<text x="' . $c . '" y="108" text-anchor="middle" fill="#9FADC6" font-size="17">' . esc_html($school) . '</text>'
. self::rope($c - 95, $c + 95, 126, '#A9B6CC')
. '<text x="' . $c . '" y="200" text-anchor="middle" fill="url(#sv)" font-size="54" font-weight="bold">' . esc_html($title) . '</text>
<text x="' . $c . '" y="266" text-anchor="middle" fill="#93A2BC" font-size="17">تشهد إدارة المدرسة بأن الطالب</text>
<text x="' . $c . '" y="348" text-anchor="middle" fill="#FFFFFF" font-size="50" font-weight="bold">' . esc_html($name) . '</text>'
. self::rope($c - 240, $c + 240, 372, '#A9B6CC')
. '<text x="' . $c . '" y="418" text-anchor="middle" fill="#AFBCD4" font-size="18">' . esc_html($l1) . '</text>'
. ($l2 !== '' ? '<text x="' . $c . '" y="450" text-anchor="middle" fill="#AFBCD4" font-size="18">' . esc_html($l2) . '</text>' : '')
. '<text x="' . $c . '" y="' . ($l2 !== '' ? 492 : 460) . '" text-anchor="middle" fill="#7F8DA8" font-size="15">' . esc_html($klass) . '</text>'
. self::seal($c, 596, 42, '#A9B6CC', '#16233F')
. '<line x1="175" y1="700" x2="375" y2="700" stroke="#5A6884" stroke-width="1"/>
<text x="275" y="722" text-anchor="middle" fill="#93A2BC" font-size="13">مدير المدرسة</text>
<line x1="' . ($w - 375) . '" y1="700" x2="' . ($w - 175) . '" y2="700" stroke="#5A6884" stroke-width="1"/>
<text x="' . ($w - 275) . '" y="722" text-anchor="middle" fill="#93A2BC" font-size="13">المشرف التربوي</text>
<text x="' . $c . '" y="752" text-anchor="middle" fill="#54627C" font-size="11" letter-spacing="2">' . esc_html($serial) . '</text>';
    }
}