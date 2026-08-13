<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * جهاز المدرسة العصبي.
 *
 * الفرق بين أرشيف وجهاز عصبي: الأرشيف يسجّل وينتظر أن يقرأه أحد،
 * والجهاز العصبي يلاحظ، ويوجّه، ويُلزم، **ويتحقق أن العلاج نفع**.
 *
 * كل ما هنا عدّ ومقارنة وطرح تواريخ. لا ذكاء اصطناعي ولا خدمة خارجية —
 * ما يبدو ذكاءً هو حساب بسيط أُجري في الوقت الصحيح ووصل للشخص الصحيح.
 */
final class SCH_Nerve
{
    /** أيام بلا أي تفاعل تجعل الطالب "غير مرئي". */
    private const INVISIBLE_DAYS = 21;

    /** الحد الأدنى لمتوسط تفاعلات الشعبة حتى تكون المقارنة ذات معنى. */
    private const PEER_MIN = 3;

    // ---------- الطالب غير المرئي ----------

    /**
     * الطلاب الذين لم يُذكروا منذ ثلاثة أسابيع.
     *
     * في كل فصل طفل لا يشاغب فيُعاقَب ولا يتفوق فيُكرَّم — ينهي عامه ولم يلتفت إليه أحد.
     * كل الأنظمة ترصد المتعثر والمشاغب. هذه ترصد **المنسي**.
     *
     * قائمة انتباه لا قائمة اتهام: لا تُعرض للمعلم ولا لولي الأمر إطلاقًا.
     */
    public static function invisible_students(int $limit = 30): array
    {
        global $wpdb;

        $since = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - self::INVISIBLE_DAYS * DAY_IN_SECONDS);
        $day   = substr($since, 0, 10);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.full_name, s.first_name, s.father_name, s.grand_name, s.family_name,
                    s.grade_level, s.section,
                    (SELECT COUNT(*) FROM " . sch_table('student_notes') . " n
                      WHERE n.student_id = s.id AND n.created_at >= %s)          AS notes_c,
                    (SELECT COUNT(*) FROM " . sch_table('clinic_visits') . " v
                      WHERE v.student_id = s.id AND v.visit_at >= %s)            AS clinic_c,
                    (SELECT COUNT(*) FROM " . sch_table('exam_results') . " r
                      WHERE r.student_id = s.id AND r.recorded_at >= %s
                        AND r.score IS NOT NULL)                                 AS grades_c,
                    (SELECT COUNT(*) FROM " . sch_table('attendance') . " a
                      WHERE a.student_id = s.id AND a.att_date >= %s
                        AND a.status <> 'present')                               AS flags_c
             FROM " . sch_table('students') . " s
             WHERE s.status = 'active'",
            $since,
            $since,
            $since,
            $day
        )) ?: [];

        // متوسط تفاعلات كل شعبة — المقارنة بالأقران لا برقم مطلق.
        $peer = [];
        foreach ($rows as $r) {
            $key = (string) $r->grade_level . '|' . (string) $r->section;
            $peer[$key][] = (int) $r->notes_c + (int) $r->clinic_c + (int) $r->grades_c + (int) $r->flags_c;
        }

        $out = [];

        foreach ($rows as $r) {
            $total = (int) $r->notes_c + (int) $r->clinic_c + (int) $r->grades_c + (int) $r->flags_c;
            if ($total > 0) {
                continue;
            }

            $key  = (string) $r->grade_level . '|' . (string) $r->section;
            $vals = $peer[$key] ?? [];
            $avg  = $vals !== [] ? array_sum($vals) / count($vals) : 0;

            // لو الشعبة كلها بلا تفاعلات فالمشكلة في الرصد لا في الطالب.
            if ($avg < self::PEER_MIN) {
                continue;
            }

            $r->peer_average = round($avg, 1);
            $out[] = $r;
        }

        usort($out, static fn (object $a, object $b): int => $b->peer_average <=> $a->peer_average);

        return array_slice($out, 0, $limit);
    }

    // ---------- إغلاق الحلقة ----------

    /**
     * مراجعة الملاحظات المغلقة بعد أسبوعين: هل نفع ما فُعل؟
     *
     * كل نظام يقول لك إن الطالب في خطر. ولا نظام يسأل بعد أسبوعين: هل تحسّن؟
     * التدخلات في المدارس تُطلق وتُنسى — وهذه أكبر فجوة في برمجيات التعليم.
     */
    public static function run_rechecks(): int
    {
        global $wpdb;

        $due = $wpdb->get_results($wpdb->prepare(
            "SELECT n.id, n.student_id, n.closed_at, n.resolution, s.full_name
             FROM " . sch_table('student_notes') . " n
             INNER JOIN " . sch_table('students') . " s ON s.id = n.student_id
             WHERE n.recheck_at IS NOT NULL AND n.recheck_result IS NULL
               AND n.recheck_at <= %s
             LIMIT 40",
            current_time('mysql')
        )) ?: [];

        foreach ($due as $note) {
            $verdict = self::compare_before_after((int) $note->student_id, (string) $note->closed_at);

            $wpdb->update(
                sch_table('student_notes'),
                ['recheck_result' => $verdict['result']],
                ['id' => (int) $note->id]
            );

            if ($verdict['result'] === 'improved') {
                continue;
            }

            // لم يتحسّن ← يُعاد فتحه بدرجة أعلى ويُرفع للوكيل مع الأرقام.
            SCH_Alerts::open(
                'no_improvement',
                'high',
                (int) $note->student_id,
                null,
                __('تدخّل لم يُثمر', 'school-system'),
                sprintf(
                    /* translators: 1: اسم الطالب 2: مقارنة الأرقام */
                    __('%1$s — بعد أسبوعين من المعالجة: %2$s', 'school-system'),
                    $note->full_name,
                    $verdict['summary']
                )
            );
        }

        return count($due);
    }

    /** مقارنة ثلاثة مؤشرات قبل التدخل وبعده. */
    public static function compare_before_after(int $student_id, string $pivot): array
    {
        global $wpdb;

        $t     = strtotime($pivot) ?: time();
        $start = gmdate('Y-m-d', $t - 14 * DAY_IN_SECONDS);
        $mid   = gmdate('Y-m-d', $t);
        $end   = gmdate('Y-m-d', $t + 14 * DAY_IN_SECONDS);

        $rate = static function (string $from, string $to) use ($wpdb, $student_id): ?float {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) AS marked, SUM(status IN ('present','late')) AS ok
                 FROM " . sch_table('attendance') . "
                 WHERE student_id = %d AND att_date BETWEEN %s AND %s",
                $student_id,
                $from,
                $to
            ));

            return ((int) ($row->marked ?? 0)) > 0
                ? round((int) $row->ok / (int) $row->marked * 100, 1)
                : null;
        };

        $tone = static function (string $from, string $to) use ($wpdb, $student_id): ?float {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) AS total, SUM(category IN ('positive','participation')) AS good
                 FROM " . sch_table('student_notes') . "
                 WHERE student_id = %d AND created_at BETWEEN %s AND %s",
                $student_id,
                $from . ' 00:00:00',
                $to . ' 23:59:59'
            ));

            return ((int) ($row->total ?? 0)) > 0
                ? round((int) $row->good / (int) $row->total * 100, 1)
                : null;
        };

        $before_att = $rate($start, $mid);
        $after_att  = $rate($mid, $end);
        $before_tone = $tone($start, $mid);
        $after_tone  = $tone($mid, $end);

        $score = 0;
        $parts = [];

        if ($before_att !== null && $after_att !== null) {
            $score += $after_att <=> $before_att;
            $parts[] = sprintf(
                /* translators: 1: نسبة قبل 2: نسبة بعد */
                __('الحضور %1$s%% ← %2$s%%', 'school-system'),
                number_format($before_att, 1),
                number_format($after_att, 1)
            );
        }

        if ($before_tone !== null && $after_tone !== null) {
            $score += $after_tone <=> $before_tone;
            $parts[] = sprintf(
                /* translators: 1: نسبة قبل 2: نسبة بعد */
                __('الملاحظات الإيجابية %1$s%% ← %2$s%%', 'school-system'),
                number_format($before_tone, 1),
                number_format($after_tone, 1)
            );
        }

        return [
            'result'  => $score > 0 ? 'improved' : ($score < 0 ? 'worse' : 'same'),
            'summary' => $parts !== [] ? implode(' · ', $parts) : __('لا بيانات كافية للمقارنة', 'school-system'),
        ];
    }

    // ---------- زمن الاستجابة ----------

    /**
     * من لحظة ظهور الإشارة إلى لحظة أول تصرف.
     *
     * يُعرض للمدير **كوسيط لا كقائمة أفراد** — الهدف كشف الاختناق لا محاسبة شخص.
     * لحظة تحوّله لأداة لوم، سيغلق الناس البنود بلا عمل ليحسّنوا رقمهم.
     */
    public static function response_times(int $days = 60): array
    {
        global $wpdb;

        $since = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - $days * DAY_IN_SECONDS);

        $notes = $wpdb->get_col($wpdb->prepare(
            "SELECT TIMESTAMPDIFF(MINUTE, created_at, closed_at)
             FROM " . sch_table('student_notes') . "
             WHERE route = 'specialist' AND closed_at IS NOT NULL AND created_at >= %s",
            $since
        )) ?: [];

        $clinic = $wpdb->get_col($wpdb->prepare(
            "SELECT TIMESTAMPDIFF(MINUTE, created_at, closed_at)
             FROM " . sch_table('student_notes') . "
             WHERE route = 'clinic' AND closed_at IS NOT NULL AND created_at >= %s",
            $since
        )) ?: [];

        $alerts = $wpdb->get_col($wpdb->prepare(
            "SELECT TIMESTAMPDIFF(MINUTE, opened_at, COALESCE(ack_at, closed_at))
             FROM " . sch_table('alerts') . "
             WHERE (ack_at IS NOT NULL OR closed_at IS NOT NULL) AND opened_at >= %s",
            $since
        )) ?: [];

        return [
            'specialist' => self::median($notes),
            'clinic'     => self::median($clinic),
            'alerts'     => self::median($alerts),
        ];
    }

    private static function median(array $values): ?float
    {
        $nums = array_values(array_filter(
            array_map('floatval', $values),
            static fn (float $v): bool => $v >= 0
        ));

        if ($nums === []) {
            return null;
        }

        sort($nums);
        $mid = intdiv(count($nums), 2);

        return count($nums) % 2 === 1
            ? $nums[$mid]
            : round(($nums[$mid - 1] + $nums[$mid]) / 2, 1);
    }

    /** تحويل الدقائق لصيغة بشرية. */
    public static function humanize(?float $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        if ($minutes < 60) {
            return sprintf(
                /* translators: %d: عدد الدقائق */
                _n('%d دقيقة', '%d دقيقة', (int) $minutes, 'school-system'),
                (int) $minutes
            );
        }
        if ($minutes < 1440) {
            return sprintf(
                /* translators: %s: عدد الساعات */
                __('%s ساعة', 'school-system'),
                number_format($minutes / 60, 1)
            );
        }

        return sprintf(
            /* translators: %s: عدد الأيام */
            __('%s يوم', 'school-system'),
            number_format($minutes / 1440, 1)
        );
    }

    // ---------- مقاييس المدرسة ----------

    /** الأرقام التي تُقال للمدرسة — كلها محسوبة لا مقدَّرة. */
    public static function school_metrics(): array
    {
        global $wpdb;

        $year  = SCH_Years::current();
        $since = $year->start_date ?? gmdate('Y-01-01');

        $detected = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('student_notes') . "
             WHERE route <> 'none' AND created_at >= %s",
            $since . ' 00:00:00'
        ));

        $handled = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('student_notes') . "
             WHERE route <> 'none' AND status = 'closed' AND created_at >= %s",
            $since . ' 00:00:00'
        ));

        $improved = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('student_notes') . "
             WHERE recheck_result = 'improved' AND created_at >= %s",
            $since . ' 00:00:00'
        ));

        $times = self::response_times(120);

        return [
            'detected'  => $detected,
            'handled'   => $handled,
            'improved'  => $improved,
            'response'  => $times['specialist'],
            'invisible' => count(self::invisible_students(100)),
        ];
    }

    // ---------- الذاكرة عبر السنوات ----------

    /**
     * المدارس تفقد ذاكرتها كل صيف: معلمة قضت عامًا تتعلم أطفالها،
     * ثم تستلم معلمة جديدة وتبدأ من الصفر — والطفل يدفع ثمن الأشهر الثلاثة.
     */
    public static function summary(int $student_id, ?int $year_id = null): ?object
    {
        global $wpdb;

        $year_id ??= SCH_Years::current_id();

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('year_summary') . ' WHERE student_id = %d AND year_id = %d',
            $student_id,
            $year_id
        ));

        return $row ?: null;
    }

    /** آخر ملخص معتمد من سنة سابقة — ما تفتحه المعلمة الجديدة أول يوم. */
    public static function previous_summary(int $student_id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT ys.*, y.name AS year_name
             FROM ' . sch_table('year_summary') . ' ys
             INNER JOIN ' . sch_table('academic_years') . ' y ON y.id = ys.year_id
             WHERE ys.student_id = %d AND ys.approved_at IS NOT NULL AND ys.year_id <> %d
             ORDER BY ys.year_id DESC LIMIT 1',
            $student_id,
            SCH_Years::current_id()
        ));

        return $row ?: null;
    }

    public static function save_summary(int $student_id, array $d): bool|WP_Error
    {
        global $wpdb;

        $year_id = SCH_Years::current_id();
        if ($year_id === 0) {
            return sch_api_error('no_year', __('لا توجد سنة دراسية نشطة.', 'school-system'), 422);
        }

        $data = [
            'works'          => sanitize_text_field((string) ($d['works'] ?? '')) ?: null,
            'avoid'          => sanitize_text_field((string) ($d['avoid'] ?? '')) ?: null,
            'notes'          => sanitize_text_field((string) ($d['notes'] ?? '')) ?: null,
            'author_user_id' => get_current_user_id() ?: null,
        ];

        if ($data['works'] === null && $data['avoid'] === null && $data['notes'] === null) {
            return sch_api_error('empty', __('اكتب سطرًا واحدًا على الأقل.', 'school-system'), 422);
        }

        $existing = self::summary($student_id, $year_id);

        if ($existing) {
            $wpdb->update(sch_table('year_summary'), $data, ['id' => (int) $existing->id]);
        } else {
            $wpdb->insert(sch_table('year_summary'), $data + [
                'student_id' => $student_id,
                'year_id'    => $year_id,
                'created_at' => sch_now(),
            ]);
        }

        sch_audit('summary.saved', 'student', $student_id);

        return true;
    }

    public static function approve_summary(int $student_id): bool
    {
        global $wpdb;

        $wpdb->update(sch_table('year_summary'), [
            'approved_by' => get_current_user_id() ?: null,
            'approved_at' => sch_now(),
        ], ['student_id' => $student_id, 'year_id' => SCH_Years::current_id()]);

        return true;
    }

    /** ما يساعد على كتابة الملخص: أرقام العام وملاحظاته. */
    public static function summary_context(int $student_id): array
    {
        return [
            'attendance' => SCH_Attendance::rate($student_id),
            'notes'      => SCH_Notes::of_student($student_id, 12),
            'report'     => SCH_Assessment::report_card($student_id),
        ];
    }
}
