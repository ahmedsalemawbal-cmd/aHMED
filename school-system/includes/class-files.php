<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * خدمة الملفات المحمية.
 *
 * كل ما يُرفع في النظام يُخزَّن خارج مجلد الرفع العام — كتب ورسوم وتقارير ووصفات.
 * وكان كل نوع يحتاج مسارًا خاصًا، فبقيت أنواع بلا مسار: تُرفع ولا تُفتح.
 *
 * هذا الصنف يجمعها في باب واحد: **كل ملف يمر بفحص صلاحية يخصّ نوعه**،
 * ولا يُقرأ من القرص قبل أن يُجاب سؤال «هل يحق لهذا الشخص رؤيته؟».
 */
final class SCH_Files
{
    /** أنواع الملفات وقاعدة الوصول لكل نوع. */
    public const KINDS = [
        'content'  => 'كتاب أو مصدر تعليمي',
        'homework' => 'مرفق واجب',
        'answer'   => 'إجابة طالب',
        'leave'    => 'تقرير إجازة',
        'rx'       => 'وصفة دواء',
    ];

    public static function init(): void
    {
        add_action('init', [self::class, 'add_query_vars']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'maybe_serve'], 1);
    }

    public static function add_query_vars(): void
    {
        // لا قواعد إعادة كتابة — نعمل على معاملات الاستعلام فيعمل من أي مسار.
    }

    public static function query_vars(array $vars): array
    {
        return [...$vars, 'sch_file', 'sch_file_id'];
    }

    public static function url(string $kind, int $id): string
    {
        return add_query_arg([
            'sch_file'    => $kind,
            'sch_file_id' => $id,
        ], home_url('/'));
    }

    public static function maybe_serve(): void
    {
        $kind = isset($_GET['sch_file']) ? sanitize_key(wp_unslash((string) $_GET['sch_file'])) : '';
        $id   = isset($_GET['sch_file_id']) ? absint($_GET['sch_file_id']) : 0;

        if ($kind === '' || $id === 0 || !isset(self::KINDS[$kind])) {
            return;
        }

        self::serve($kind, $id);
    }

    private static function serve(string $kind, int $id): never
    {
        if (!is_user_logged_in()) {
            status_header(403);
            exit;
        }

        $file = match ($kind) {
            'content'  => self::content_file($id),
            'homework' => self::homework_file($id),
            'answer'   => self::answer_file($id),
            'leave'    => self::leave_file($id),
            'rx'       => self::rx_file($id),
            default    => null,
        };

        if ($file === null) {
            status_header(403);
            exit;
        }

        $path = SCH_Enrollment::private_dir() . '/' . $file;

        if (!is_readable($path) || !str_starts_with(realpath($path) ?: '', realpath(SCH_Enrollment::private_dir()) ?: '')) {
            status_header(404);
            exit;
        }

        sch_audit('file.opened', 'file', $id, ['kind' => $kind]);

        $type = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf'        => 'application/pdf',
            'png'        => 'image/png',
            'jpg', 'jpeg'=> 'image/jpeg',
            default      => 'application/octet-stream',
        };

        nocache_headers();
        header('Content-Type: ' . $type);
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        // inline لا attachment: الكتاب يُقرأ في المتصفح والرسم يُرى بلا تنزيل.
        header('Content-Disposition: inline; filename="' . rawurlencode(basename($path)) . '"');

        readfile($path);
        exit;
    }

    // ---------- قواعد الوصول ----------

    /** الكتاب: لطلاب مرحلته المنشورة، ولمن يدير المحتوى. */
    private static function content_file(int $id): ?string
    {
        $item = SCH_Content::get($id);

        if (!$item || !$item->file_stored) {
            return null;
        }

        if (current_user_can('sch_manage_content') || current_user_can('sch_view_students')) {
            return (string) $item->file_stored;
        }

        if ($item->status !== 'published') {
            return null;
        }

        $student = current_user_can('sch_learn') ? SCH_Student::me() : null;

        return $student && (string) $student->stage === (string) $item->stage
            ? (string) $item->file_stored
            : null;
    }

    /** مرفق الواجب: لطلاب الشعبة ولمن يدير الواجبات. */
    private static function homework_file(int $id): ?string
    {
        $hw = SCH_Homework::get($id);

        if (!$hw || !$hw->file_stored) {
            return null;
        }

        if (current_user_can('sch_manage_homework') || current_user_can('sch_view_students')) {
            return (string) $hw->file_stored;
        }

        $student = current_user_can('sch_learn') ? SCH_Student::me() : null;
        $class   = $student ? SCH_Students::current_class((int) $student->id) : null;

        return $class && (int) $class->id === (int) $hw->class_id ? (string) $hw->file_stored : null;
    }

    /** إجابة الطالب: لصاحبها، ولمعلم الشعبة، ولولي أمره. */
    private static function answer_file(int $id): ?string
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT sub.file_stored, sub.student_id FROM ' . sch_table('homework_subs') . ' sub WHERE sub.id = %d',
            $id
        ));

        if (!$row || !$row->file_stored) {
            return null;
        }

        if (current_user_can('sch_manage_homework') || current_user_can('sch_view_students')) {
            return (string) $row->file_stored;
        }

        $me = get_current_user_id();

        if (SCH_Students::guardian_has_child($me, (int) $row->student_id)) {
            return (string) $row->file_stored;
        }

        $student = current_user_can('sch_learn') ? SCH_Student::me() : null;

        return $student && (int) $student->id === (int) $row->student_id ? (string) $row->file_stored : null;
    }

    /** تقرير الإجازة: لولي الأمر ولمن يقرّر. */
    private static function leave_file(int $id): ?string
    {
        $leave = SCH_StudentLeave::get($id);

        if (!$leave || !$leave->file_stored) {
            return null;
        }

        if (current_user_can('sch_decide_leaves') || current_user_can('sch_manage_students')) {
            return (string) $leave->file_stored;
        }

        return SCH_Students::guardian_has_child(get_current_user_id(), (int) $leave->student_id)
            ? (string) $leave->file_stored
            : null;
    }

    /** الوصفة: لولي الأمر وللعيادة — بيانات صحية لا تُفتح لغيرهما. */
    private static function rx_file(int $id): ?string
    {
        $med = SCH_Medication::get($id);

        if (!$med || !$med->file_stored) {
            return null;
        }

        if (current_user_can('sch_manage_meds') || current_user_can('sch_view_health')) {
            return (string) $med->file_stored;
        }

        return SCH_Students::guardian_has_child(get_current_user_id(), (int) $med->student_id)
            ? (string) $med->file_stored
            : null;
    }
}
