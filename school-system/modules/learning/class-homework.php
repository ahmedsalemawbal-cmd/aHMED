<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الواجبات.
 *
 * أسبوعية: تظهر في «واجباتي» خلال أسبوعها ثم تنزل إلى «السابقة».
 * **ولا تُحذف** — الطالب الذي غاب يحتاجها، والمعلم يحتاج سجل من سلّم.
 *
 * وميزة لا يملكها أحد غيرك: الواجب يعرف **من كان غائبًا يوم شُرح الدرس**.
 * فرق كبير بين طالب كسِل وطالب لم يحضر الشرح أصلًا.
 */
final class SCH_Homework
{
    public const ANSWER_TYPES = [
        'text'    => 'إجابة مكتوبة',
        'drawing' => 'رسم',
        'file'    => 'ملف مرفق',
        'none'    => 'بلا تسليم',
    ];

    /** مفتاح الأسبوع: 2026-W32 — يثبّت العرض الأسبوعي بلا حسابات تاريخ. */
    public static function week_key(?string $date = null): string
    {
        $ts = strtotime($date ?? current_time('Y-m-d')) ?: time();

        return gmdate('o-\WW', $ts);
    }

    // ---------- الإنشاء ----------

    public static function create(array $d, array $files = []): array|WP_Error
    {
        global $wpdb;

        $class_id = absint($d['class_id'] ?? 0);
        $title    = sanitize_text_field((string) ($d['title'] ?? ''));

        if (!SCH_Classes::get($class_id)) {
            return sch_api_error('bad_class', __('اختر الشعبة.', 'school-system'), 422);
        }
        if ($title === '') {
            return sch_api_error('missing_title', __('عنوان الواجب مطلوب.', 'school-system'), 422);
        }

        $due = sch_sanitize_date($d['due_date'] ?? null)
            ?? gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' +7 days'));

        $stored = null;
        if (!empty($files['attachment']['tmp_name'])) {
            $saved = SCH_Enrollment::store_upload($files['attachment'], $class_id, 'hw');
            if (!is_wp_error($saved)) {
                $stored = $saved['stored'];
            }
        }

        $wpdb->insert(sch_table('homework'), [
            'class_id'    => $class_id,
            'subject_id'  => absint($d['subject_id'] ?? 0) ?: null,
            'teacher_id'  => get_current_user_id() ?: null,
            'title'       => $title,
            'body'        => sanitize_textarea_field((string) ($d['body'] ?? '')) ?: null,
            'answer_type' => array_key_exists($d['answer_type'] ?? '', self::ANSWER_TYPES) ? $d['answer_type'] : 'text',
            'file_stored' => $stored,
            'content_id'  => absint($d['content_id'] ?? 0) ?: null,
            'week_key'    => self::week_key(),
            'due_date'    => $due,
            // يوم الشرح: نعرف منه من كان غائبًا.
            'taught_on'   => sch_sanitize_date($d['taught_on'] ?? null) ?? current_time('Y-m-d'),
            'status'      => 'open',
            'created_at'  => sch_now(),
        ]);

        $hw_id = (int) $wpdb->insert_id;

        // إشعار صامت لأولياء أمور الشعبة — يظهر في الخط الزمني بلا إزعاج.
        foreach (SCH_Students::in_class($class_id) as $student) {
            foreach (SCH_Guardians::of_student((int) $student->id) as $g) {
                SCH_Comms::notify(
                    (int) $g->user_id,
                    __('واجب جديد', 'school-system'),
                    $title,
                    'homework_silent',
                    (int) $student->id
                );
            }
        }

        sch_audit('homework.created', 'class', $class_id, ['title' => $title]);

        return ['id' => $hw_id];
    }

    // ---------- القراءة ----------

    public static function get(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT h.*, s.name AS subject_name, c.grade_level, c.section
             FROM ' . sch_table('homework') . ' h
             LEFT JOIN ' . sch_table('subjects') . ' s ON s.id = h.subject_id
             INNER JOIN ' . sch_table('classes') . ' c ON c.id = h.class_id
             WHERE h.id = %d',
            $id
        )) ?: null;
    }

    /** واجبات شعبة — الأسبوع الحالي أو ما سبقه. */
    public static function for_class(int $class_id, bool $current_week = true): array
    {
        global $wpdb;

        $week = self::week_key();

        return $wpdb->get_results($wpdb->prepare(
            'SELECT h.*, s.name AS subject_name
             FROM ' . sch_table('homework') . ' h
             LEFT JOIN ' . sch_table('subjects') . ' s ON s.id = h.subject_id
             WHERE h.class_id = %d AND h.week_key ' . ($current_week ? '=' : '<') . ' %s
             ORDER BY h.due_date DESC, h.id DESC LIMIT 40',
            $class_id,
            $week
        )) ?: [];
    }

    /** واجبات الطالب مع حالة تسليمه. */
    public static function for_student(int $student_id, bool $current_week = true): array
    {
        global $wpdb;

        $class = SCH_Students::current_class($student_id);
        if (!$class) {
            return [];
        }

        $week = self::week_key();

        return $wpdb->get_results($wpdb->prepare(
            'SELECT h.*, s.name AS subject_name,
                    sub.id AS sub_id, sub.submitted_at, sub.score, sub.feedback
             FROM ' . sch_table('homework') . ' h
             LEFT JOIN ' . sch_table('subjects') . ' s ON s.id = h.subject_id
             LEFT JOIN ' . sch_table('homework_subs') . ' sub
                    ON sub.homework_id = h.id AND sub.student_id = %d
             WHERE h.class_id = %d AND h.week_key ' . ($current_week ? '=' : '<') . ' %s
             ORDER BY h.due_date, h.id LIMIT 40',
            $student_id,
            (int) $class->id,
            $week
        )) ?: [];
    }

    // ---------- التسليم ----------

    public static function submit(int $homework_id, int $student_id, array $d, array $files = []): bool|WP_Error
    {
        global $wpdb;

        $hw = self::get($homework_id);
        if (!$hw) {
            return sch_api_error('not_found', __('الواجب غير موجود.', 'school-system'), 404);
        }

        $class = SCH_Students::current_class($student_id);
        if (!$class || (int) $class->id !== (int) $hw->class_id) {
            return sch_api_error('forbidden', __('هذا الواجب ليس لشعبتك.', 'school-system'), 403);
        }

        $text   = sanitize_textarea_field((string) ($d['answer_text'] ?? ''));
        $stored = null;

        // الرسم يصل كصورة مرمّزة من لوح التطبيق.
        if (!empty($d['drawing'])) {
            $stored = self::store_drawing((string) $d['drawing'], $student_id);
        } elseif (!empty($files['answer_file']['tmp_name'])) {
            $saved = SCH_Enrollment::store_upload($files['answer_file'], $student_id, 'hwans');
            if (!is_wp_error($saved)) {
                $stored = $saved['stored'];
            }
        }

        if ($text === '' && $stored === null && $hw->answer_type !== 'none') {
            return sch_api_error('empty_answer', __('اكتب إجابتك أو أرفق رسمك.', 'school-system'), 422);
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('homework_subs') . ' WHERE homework_id = %d AND student_id = %d',
            $homework_id,
            $student_id
        ));

        $data = [
            'answer_text'  => $text ?: null,
            'file_stored'  => $stored,
            'submitted_at' => sch_now(),
        ];

        if ($existing) {
            $wpdb->update(sch_table('homework_subs'), $data, ['id' => (int) $existing]);
        } else {
            $wpdb->insert(sch_table('homework_subs'), $data + [
                'homework_id' => $homework_id,
                'student_id'  => $student_id,
            ]);
        }

        return true;
    }

    /** حفظ الرسم — في المجلد المحمي كبقية ملفات الطلاب. */
    private static function store_drawing(string $data_url, int $student_id): ?string
    {
        if (!str_starts_with($data_url, 'data:image/png;base64,')) {
            return null;
        }

        $raw = base64_decode(substr($data_url, 22), true);

        // حد معقول للوح رسم — يمنع رفع ملفات ضخمة عبر هذا الباب.
        if ($raw === false || strlen($raw) > 3145728) {
            return null;
        }

        $name = sprintf('%d-draw-%s.png', $student_id, wp_generate_password(12, false));
        $path = SCH_Enrollment::private_dir() . '/' . $name;

        return file_put_contents($path, $raw) !== false ? $name : null;
    }

    /**
     * كشف التسليم — ومعه من كان غائبًا يوم الشرح.
     * فرق كبير بين طالب كسِل وطالب لم يحضر الشرح أصلًا.
     */
    public static function submissions(int $homework_id): array
    {
        global $wpdb;

        $hw = self::get($homework_id);
        if (!$hw) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.full_name,
                    sub.id AS sub_id, sub.submitted_at, sub.answer_text, sub.file_stored,
                    sub.score, sub.feedback,
                    (SELECT a.status FROM " . sch_table('attendance') . " a
                      WHERE a.student_id = s.id AND a.att_date = %s) AS taught_day_status
             FROM " . sch_table('enrollments') . " e
             INNER JOIN " . sch_table('students') . " s ON s.id = e.student_id
             LEFT JOIN " . sch_table('homework_subs') . " sub
                    ON sub.homework_id = %d AND sub.student_id = s.id
             WHERE e.class_id = %d AND e.status = 'active' AND s.status = 'active'
             ORDER BY sub.submitted_at IS NULL, s.full_name",
            (string) ($hw->taught_on ?: current_time('Y-m-d')),
            $homework_id,
            (int) $hw->class_id
        )) ?: [];
    }

    public static function grade(int $sub_id, float $score, string $feedback = ''): bool
    {
        global $wpdb;

        $wpdb->update(sch_table('homework_subs'), [
            'score'     => round($score, 2),
            'feedback'  => sanitize_text_field($feedback) ?: null,
            'graded_by' => get_current_user_id() ?: null,
            'graded_at' => sch_now(),
        ], ['id' => $sub_id]);

        return true;
    }

    /** ملخص للمعلم: كم سلّم، وكم من غير المسلّمين كان غائبًا يوم الشرح. */
    public static function summary(int $homework_id): array
    {
        $rows    = self::submissions($homework_id);
        $total   = count($rows);
        $done    = 0;
        $absent  = 0;

        foreach ($rows as $r) {
            if ($r->sub_id !== null) {
                $done++;
                continue;
            }
            if (in_array((string) $r->taught_day_status, ['absent', 'excused'], true)) {
                $absent++;
            }
        }

        return [
            'total'        => $total,
            'submitted'    => $done,
            'missing'      => $total - $done,
            'was_absent'   => $absent,
        ];
    }
}
