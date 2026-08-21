<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الملاحظات والإحالات.
 *
 * القاعدة: من يشاهد لا يقرر. المعلم **يسجّل**، والنظام **يوجّه**،
 * وجهة واحدة **تتكلم** مع ولي الأمر.
 *
 * المعلم لا يراسل ولي الأمر أبدًا — لأن عشرين معلمًا بعشرين أسلوبًا
 * يعني رسائل متناقضة النبرة، ورسالة كُتبت في لحظة انفعال لا تُسحب.
 */
final class SCH_Notes
{
    /** التصنيف => [التسمية، الوجهة، هل يُدفع إشعار] */
    public const CATEGORIES = [
        'positive'      => ['سلوك إيجابي', 'none',       false],
        'participation' => ['مشاركة وتفاعل', 'none',     false],
        'followup'      => ['يحتاج متابعة', 'specialist', true],
        'health'        => ['صحي — للعيادة', 'clinic',   false],
    ];

    public const STATUSES = [
        'open'        => 'جديد',
        'in_progress' => 'قيد المعالجة',
        'closed'      => 'مُغلق',
    ];

    /** مهلة التصعيد بالأيام — ملاحظة متروكة تُرفع للوكيل. */
    private const STALE_DAYS = 3;

    /** عدد المخالفات التي ترفع الحالة للأخصائية تلقائيًا. */
    private const REPEAT_LIMIT = 3;

    // ---------- التسجيل ----------

    /**
     * تسجيل ملاحظة. دور المعلم ينتهي هنا.
     *
     * @return array{id:int,route:string}|WP_Error
     */
    public static function create(array $d): array|WP_Error
    {
        global $wpdb;

        $student_id = absint($d['student_id'] ?? 0);
        $category   = (string) ($d['category'] ?? '');
        $body       = sanitize_text_field((string) ($d['body'] ?? ''));

        $student = SCH_Students::get($student_id);
        if (!$student) {
            return sch_api_error('student_not_found', __('الطالب غير موجود.', 'school-system'), 404);
        }
        if (!isset(self::CATEGORIES[$category])) {
            return sch_api_error('bad_category', __('التصنيف غير معروف.', 'school-system'), 422);
        }
        if (mb_strlen($body) < 3) {
            return sch_api_error('body_required', __('اكتب سطرًا واحدًا محددًا — القالب الجاهز يفقد قيمته من المرة الثالثة.', 'school-system'), 422);
        }

        // `$push` لم يعد يُقرأ هنا: التصنيفان اللذان يدفعان إشعارًا لا يمرّان
        // بوليّ الأمر أصلًا — بل بالأخصائية أو العيادة، ومنهما `contact_parent()`.
        [$label, $route] = self::CATEGORIES[$category];

        $wpdb->insert(sch_table('student_notes'), [
            'student_id'     => $student_id,
            'category'       => $category,
            'route'          => $route,
            'body'           => $body,
            'author_user_id' => get_current_user_id() ?: null,
            'status'         => $route === 'none' ? 'closed' : 'open',
            'opened_at'      => sch_now(),
            'closed_at'      => $route === 'none' ? sch_now() : null,
            'created_at'     => sch_now(),
        ]);

        $note_id = (int) $wpdb->insert_id;
        $name    = SCH_Enrollment::full_name($student);

        /*
         * الإيجابي والمشاركة وحدهما يذهبان لخط ولي الأمر — صامتين.
         *
         * وكانت الحلقة **بلا شرط**، فالتصنيفان الآخران يصلان الأب أيضًا:
         * «يحتاج متابعة» تصله بعنوانها وبنصّ المعلم الخام (`$push = true`)،
         * و«صحي» تصله كذلك. وهذا نقضٌ لثلاث قواعد في `CLAUDE.md` معًا:
         * **المعلم لا يراسل وليّ الأمر أبدًا** · الأخصائية الصوت الوحيد في
         * السلوكي · والعيادة الصوت الوحيد في الصحي. ودليل النيّة موجود في
         * الشفرة نفسها: خيط اليوم في تطبيق الأسرة يُصفّي
         * `category IN ('positive','participation')` عمدًا.
         *
         * والتصنيفان الآخران يبلغان الأب من بابهما: `contact_parent()` بعد
         * أن تقرّر الأخصائية أو العيادة ماذا يُقال.
         */
        if ($route === 'none') {
            foreach (SCH_Guardians::of_student($student_id) as $g) {
                SCH_Comms::notify(
                    (int) $g->user_id,
                    $label,
                    $body,
                    'note_silent',
                    $student_id
                );
            }
        }

        self::check_repeats($student_id, $category);

        sch_audit('note.created', 'student', $student_id, ['category' => $category, 'route' => $route]);

        return ['id' => $note_id, 'route' => $route];
    }

    /** تكرار "يحتاج متابعة" ثلاث مرات يرفعه للأخصائية تلقائيًا. */
    private static function check_repeats(int $student_id, string $category): void
    {
        global $wpdb;

        if ($category !== 'followup') {
            return;
        }

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('student_notes') . "
             WHERE student_id = %d AND category = 'followup' AND created_at >= %s",
            $student_id,
            gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 30 * DAY_IN_SECONDS)
        ));

        if ($count >= self::REPEAT_LIMIT) {
            $student = SCH_Students::get($student_id);

            SCH_Alerts::open(
                'repeat_followup',
                'medium',
                $student_id,
                null,
                __('تكرار ملاحظات المتابعة', 'school-system'),
                sprintf(
                    /* translators: 1: اسم الطالب 2: عدد الملاحظات */
                    __('%1$s — %2$d ملاحظات متابعة خلال شهر.', 'school-system'),
                    SCH_Enrollment::full_name($student),
                    $count
                )
            );
        }
    }

    // ---------- المعالجة ----------

    public static function get(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT n.*, s.full_name, s.first_name, s.father_name, s.grand_name, s.family_name,
                    s.stage, s.grade_level, s.section, u.display_name AS author_name
             FROM ' . sch_table('student_notes') . ' n
             INNER JOIN ' . sch_table('students') . ' s ON s.id = n.student_id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = n.author_user_id
             WHERE n.id = %d',
            $id
        ));

        return $row ?: null;
    }

    /** صندوق الأخصائية أو الصحة المدرسية — الأقدم أولًا. */
    public static function queue(string $route, string $status = ''): array
    {
        global $wpdb;

        $sql = 'SELECT n.*, s.full_name, s.stage, s.grade_level, s.section, u.display_name AS author_name
                FROM ' . sch_table('student_notes') . ' n
                INNER JOIN ' . sch_table('students') . ' s ON s.id = n.student_id
                LEFT JOIN ' . $wpdb->users . ' u ON u.ID = n.author_user_id
                WHERE n.route = %s AND n.status <> %s
                ORDER BY n.created_at ASC LIMIT 100';

        return $wpdb->get_results($wpdb->prepare($sql, $route, $status ?: 'closed')) ?: [];
    }

    public static function take(int $id): bool
    {
        global $wpdb;

        $wpdb->update(sch_table('student_notes'), [
            'status'      => 'in_progress',
            'assigned_to' => get_current_user_id() ?: null,
        ], ['id' => $id, 'status' => 'open']);

        sch_audit('note.taken', 'note', $id);

        return true;
    }

    /**
     * إغلاق الملاحظة — ويضع النظام موعد مراجعة تلقائيًا بعد أسبوعين.
     * التدخلات في المدارس تُطلق وتُنسى؛ هذا ما يمنع ذلك.
     */
    public static function close(int $id, string $resolution): bool|WP_Error
    {
        global $wpdb;

        $resolution = sanitize_text_field($resolution);
        if (mb_strlen($resolution) < 3) {
            return sch_api_error('resolution_required', __('اكتب ما فعلته — بلا ذلك لا نعرف لاحقًا ما نفع.', 'school-system'), 422);
        }

        $note = self::get($id);
        if (!$note) {
            return sch_api_error('not_found', __('الملاحظة غير موجودة.', 'school-system'), 404);
        }

        $wpdb->update(sch_table('student_notes'), [
            'status'     => 'closed',
            'closed_by'  => get_current_user_id() ?: null,
            'closed_at'  => sch_now(),
            'resolution' => $resolution,
            // إغلاق الحلقة: نعود بعد أسبوعين ونقيس.
            'recheck_at' => $note->category === 'followup'
                ? gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) + 14 * DAY_IN_SECONDS)
                : null,
        ], ['id' => $id]);

        sch_audit('note.closed', 'note', $id);

        return true;
    }

    /** تواصل الأخصائية مع ولي الأمر — هي الصوت الوحيد في السلوكي. */
    public static function contact_parent(int $id, string $message): bool|WP_Error
    {
        $note = self::get($id);
        if (!$note) {
            return sch_api_error('not_found', __('الملاحظة غير موجودة.', 'school-system'), 404);
        }

        $message = sanitize_textarea_field($message);
        if (mb_strlen($message) < 5) {
            return sch_api_error('message_required', __('اكتب نص الرسالة.', 'school-system'), 422);
        }

        foreach (SCH_Guardians::of_student((int) $note->student_id) as $g) {
            SCH_Comms::notify(
                (int) $g->user_id,
                __('رسالة من المدرسة', 'school-system'),
                $message,
                'note',
                (int) $note->student_id
            );
        }

        self::take($id);
        sch_audit('note.contacted', 'note', $id);

        return true;
    }

    /** رد ولي الأمر — ثلاثة أزرار وسطر اختياري. */
    /**
     * ردود وليّ الأمر على مجموعة ملاحظات — استعلامٌ واحد لقائمة الرسائل.
     *
     * الشاشة تعرض زرَّي الرد لما لم يُردّ عليه بعد، وسؤالُ كل صفٍّ على حدة
     * يعني أربعين استعلامًا لصفحةٍ واحدة.
     *
     * @param array<int,int> $note_ids
     * @return array<int,string> note_id => الرد
     */
    public static function responses_of(array $note_ids): array
    {
        global $wpdb;

        $ids = array_values(array_unique(array_filter(array_map('intval', $note_ids))));

        if ($ids === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, parent_response FROM ' . sch_table('student_notes') . "
              WHERE id IN ({$in}) AND parent_response IS NOT NULL",
            ...$ids
        )) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = (string) $r->parent_response;
        }

        return $out;
    }

    public static function parent_reply(int $note_id, string $response, string $body = ''): bool|WP_Error
    {
        global $wpdb;

        if (!in_array($response, ['knows', 'unknown', 'contact_me'], true)) {
            return sch_api_error('bad_response', __('الرد غير معروف.', 'school-system'), 422);
        }

        $note = self::get($note_id);
        if (!$note) {
            return sch_api_error('not_found', __('الملاحظة غير موجودة.', 'school-system'), 404);
        }

        // ولي الأمر لا يرد إلا على ملاحظة تخص ابنه.
        if (!SCH_Students::guardian_has_child(get_current_user_id(), (int) $note->student_id)) {
            return sch_api_error('forbidden', __('لا تملك صلاحية هذه الملاحظة.', 'school-system'), 403);
        }

        $wpdb->update(sch_table('student_notes'), [
            'parent_response' => $response,
            'parent_body'     => sanitize_text_field($body) ?: null,
            'responded_at'    => sch_now(),
            // "أرجو التواصل" يعيد فتح البند بأولوية.
            'status'          => $response === 'contact_me' ? 'open' : $note->status,
        ], ['id' => $note_id]);

        if ($response !== 'unknown') {
            SCH_Alerts::open(
                'parent_replied',
                $response === 'contact_me' ? 'high' : 'medium',
                (int) $note->student_id,
                null,
                $response === 'contact_me'
                    ? __('ولي أمر يطلب التواصل', 'school-system')
                    : __('ولي أمر يعرف السبب', 'school-system'),
                trim((string) $note->full_name . ' — ' . $body)
            );
        }

        return true;
    }

    /** ملاحظات طالب — تظهر في ملفه وفي تطبيق ولي الأمر. */
    public static function of_student(int $student_id, int $limit = 30): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT n.*, u.display_name AS author_name
             FROM ' . sch_table('student_notes') . ' n
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = n.author_user_id
             WHERE n.student_id = %d ORDER BY n.id DESC LIMIT %d',
            $student_id,
            $limit
        )) ?: [];
    }

    public static function open_count(string $route): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('student_notes') . "
             WHERE route = %s AND status <> 'closed'",
            $route
        ));
    }

    // ---------- التصعيد ----------

    /**
     * ملاحظة متروكة بلا إجراء ترتفع للوكيل.
     * بلا هذا القيد تُكتب الملاحظات وتُنسى، فيكتشف المعلم أنها تذهب لبئر
     * فيتوقف عن الكتابة — وينهار النظام كله.
     */
    public static function escalate_stale(): int
    {
        global $wpdb;

        $cutoff = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - self::STALE_DAYS * DAY_IN_SECONDS);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT n.id, n.student_id, s.full_name
             FROM " . sch_table('student_notes') . " n
             INNER JOIN " . sch_table('students') . " s ON s.id = n.student_id
             WHERE n.route = 'specialist' AND n.status <> 'closed'
               AND n.escalated_at IS NULL AND n.created_at <= %s
             LIMIT 50",
            $cutoff
        )) ?: [];

        foreach ($rows as $r) {
            SCH_Alerts::open(
                'stale_note',
                'medium',
                (int) $r->student_id,
                null,
                __('ملاحظة متروكة بلا إجراء', 'school-system'),
                sprintf(
                    /* translators: 1: اسم الطالب 2: عدد الأيام */
                    __('%1$s — مضى %2$d أيام بلا معالجة.', 'school-system'),
                    $r->full_name,
                    self::STALE_DAYS
                )
            );

            $wpdb->update(sch_table('student_notes'), ['escalated_at' => sch_now()], ['id' => (int) $r->id]);
        }

        return count($rows);
    }
}
