<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * رياض الأطفال — نموذج بيانات مستقل تمامًا.
 * لا درجات ولا اختبارات: تقرير يومي (وجبة، نوم، مزاج، نشاط).
 */
final class SCH_KG
{
    public const MOODS = [
        'happy' => 'سعيد',
        'calm'  => 'هادئ',
        'tired' => 'متعب',
        'upset' => 'منزعج',
    ];

    public const MEALS = [
        'all'  => 'أكل كل الوجبة',
        'most' => 'أكل معظمها',
        'some' => 'أكل قليلًا',
        'none' => 'لم يأكل',
    ];

    public static function save_log(array $d): bool|WP_Error
    {
        global $wpdb;

        $student_id = absint($d['student_id'] ?? 0);
        $date       = sch_sanitize_date($d['log_date'] ?? null) ?? current_time('Y-m-d');

        $student = SCH_Students::get($student_id);
        if (!$student) {
            return sch_api_error('student_not_found', __('الطالب غير موجود.', 'school-system'), 404);
        }
        if ($student->stage !== 'kg') {
            return sch_api_error('not_kg', __('التقرير اليومي لطلاب رياض الأطفال فقط.', 'school-system'), 422);
        }

        $data = [
            'mood'        => array_key_exists($d['mood'] ?? '', self::MOODS) ? $d['mood'] : null,
            'meal'        => array_key_exists($d['meal'] ?? '', self::MEALS) ? $d['meal'] : null,
            'nap_minutes' => isset($d['nap_minutes']) ? max(0, min(600, (int) $d['nap_minutes'])) : null,
            'activities'  => sanitize_text_field((string) ($d['activities'] ?? '')) ?: null,
            'note'        => sanitize_textarea_field((string) ($d['note'] ?? '')) ?: null,
            'recorded_by' => get_current_user_id() ?: null,
        ];

        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('kg_logs') . ' WHERE student_id = %d AND log_date = %s',
            $student_id,
            $date
        ));

        if ($existing) {
            $wpdb->update(sch_table('kg_logs'), $data, ['id' => (int) $existing]);
        } else {
            $wpdb->insert(sch_table('kg_logs'), $data + [
                'student_id' => $student_id,
                'log_date'   => $date,
                'created_at' => sch_now(),
            ]);

            foreach (SCH_Guardians::of_student($student_id) as $g) {
                SCH_Comms::notify(
                    (int) $g->user_id,
                    __('التقرير اليومي', 'school-system'),
                    sprintf(
                        /* translators: %s: اسم الطفل */
                        __('جاهز تقرير %s لهذا اليوم.', 'school-system'),
                        $student->full_name
                    ),
                    'kg',
                    $student_id
                );
            }
        }

        return true;
    }

    /** كشف الروضة ليوم: كل أطفال شعبة مع تقاريرهم. */
    public static function sheet(int $class_id, string $date): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.id, s.full_name, k.mood, k.meal, k.nap_minutes, k.activities, k.note
             FROM ' . sch_table('enrollments') . ' e
             INNER JOIN ' . sch_table('students') . ' s ON s.id = e.student_id
             LEFT JOIN ' . sch_table('kg_logs') . ' k ON k.student_id = s.id AND k.log_date = %s
             WHERE e.class_id = %d AND e.status = %s AND s.status = %s AND s.stage = %s
             ORDER BY s.full_name',
            $date,
            $class_id,
            'active',
            'active',
            'kg'
        )) ?: [];
    }

    public static function history(int $student_id, int $days = 14): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('kg_logs') . '
             WHERE student_id = %d AND log_date >= %s
             ORDER BY log_date DESC',
            $student_id,
            gmdate('Y-m-d', time() - $days * DAY_IN_SECONDS)
        )) ?: [];
    }

    /** شعب رياض الأطفال فقط. */
    public static function classes(): array
    {
        return array_values(array_filter(
            SCH_Classes::list(),
            static fn (object $c): bool => $c->stage === 'kg'
        ));
    }
}

/**
 * المؤشرات والإنذار المبكر.
 * لا يوجد جدول: كل شيء يُحسب من البيانات القائمة.
 */
final class SCH_Insights
{
    /** لوحة المؤشرات التنفيذية. */
    public static function overview(): array
    {
        global $wpdb;

        $today    = current_time('Y-m-d');
        $students = SCH_Students::list(['status' => 'active', 'per_page' => 1]);
        $classes  = SCH_Classes::list();
        $today_att = SCH_Attendance::day_summary($today);
        $finance  = SCH_Finance::summary();

        $present = $today_att['present'] + $today_att['late'];
        $marked  = array_sum($today_att);

        $riders = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('transport_subs') . ' WHERE status = %s AND year_id = %d',
            'active',
            SCH_Years::current_id()
        ));

        return [
            'students'        => (int) $students['total'],
            'classes'         => count($classes),
            'present_today'   => $present,
            'absent_today'    => $today_att['absent'],
            'attendance_pct'  => $marked > 0 ? round($present / $marked * 100, 1) : 0.0,
            'riders'          => $riders,
            'collection_pct'  => $finance['rate'],
            'outstanding'     => $finance['outstanding'],
            'overdue'         => $finance['overdue'],
            'open_incidents'  => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . sch_table('incidents') . " WHERE status = 'open'"),
            'open_work'       => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . sch_table('maintenance') . " WHERE status <> 'done'"),
            'visitors_inside' => SCH_Security::inside_now(),
        ];
    }

    /**
     * الطلاب المعرّضون للخطر.
     * ثلاثة مؤشرات: الحضور، الأداء، السداد. كل واحد يعطي نقاطًا.
     */
    public static function at_risk(int $limit = 40): array
    {
        global $wpdb;

        $since = gmdate('Y-m-d', time() - 60 * DAY_IN_SECONDS);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.full_name, s.grade_level, s.section,
                    COUNT(a.id) AS marked,
                    SUM(a.status = 'absent') AS absences
             FROM " . sch_table('students') . " s
             LEFT JOIN " . sch_table('attendance') . " a
                    ON a.student_id = s.id AND a.att_date >= %s
             WHERE s.status = 'active'
             GROUP BY s.id, s.full_name, s.grade_level, s.section",
            $since
        )) ?: [];

        $out = [];

        foreach ($rows as $r) {
            $reasons = [];
            $score   = 0;

            $marked   = (int) $r->marked;
            $absences = (int) $r->absences;
            $rate     = $marked > 0 ? round(($marked - $absences) / $marked * 100, 1) : 100.0;

            if ($marked >= 10 && $rate < 85) {
                $score    += $rate < 70 ? 3 : 2;
                $reasons[] = sprintf(
                    /* translators: %s: نسبة الحضور */
                    __('حضور %s%%', 'school-system'),
                    number_format($rate, 1)
                );
            }

            $card    = SCH_Assessment::report_card((int) $r->id);
            $weak    = 0;
            $percents = [];

            foreach ($card as $subject) {
                $percents[] = (float) $subject['percent'];
                if ((float) $subject['percent'] < 60) {
                    $weak++;
                }
            }

            if ($weak > 0) {
                $score    += $weak >= 3 ? 3 : 2;
                $reasons[] = sprintf(
                    /* translators: %d: عدد المواد */
                    _n('%d مادة دون 60%%', '%d مواد دون 60%%', $weak, 'school-system'),
                    $weak
                );
            }

            $overdue = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(i.amount - i.paid), 0)
                 FROM " . sch_table('installments') . " i
                 INNER JOIN " . sch_table('invoices') . " v ON v.id = i.invoice_id
                 WHERE v.student_id = %d AND v.status <> 'void'
                   AND i.status <> 'paid' AND i.due_date < %s",
                (int) $r->id,
                current_time('Y-m-d')
            ));

            if ($overdue > 0) {
                $score    += 1;
                $reasons[] = sprintf(
                    /* translators: %s: المبلغ المتأخر */
                    __('متأخرات %s', 'school-system'),
                    sch_money($overdue)
                );
            }

            if ($score >= 2) {
                $out[] = (object) [
                    'id'          => (int) $r->id,
                    'full_name'   => $r->full_name,
                    'class'       => trim(($r->grade_level ?? '') . ' / ' . ($r->section ?? '')),
                    'score'       => $score,
                    'level'       => $score >= 5 ? 'high' : ($score >= 3 ? 'medium' : 'low'),
                    'attendance'  => $rate,
                    'avg_percent' => $percents !== [] ? round(array_sum($percents) / count($percents), 1) : null,
                    'reasons'     => $reasons,
                ];
            }
        }

        usort($out, static fn (object $a, object $b): int => $b->score <=> $a->score);

        return array_slice($out, 0, $limit);
    }

    /** ترتيب الشعب بنسبة الحضور — يكشف الشعبة التي فيها مشكلة. */
    public static function class_attendance(int $days = 30): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.grade_level, c.section, c.stage,
                    COUNT(a.id) AS marked,
                    SUM(a.status IN ('present','late')) AS present
             FROM " . sch_table('classes') . " c
             LEFT JOIN " . sch_table('attendance') . " a
                    ON a.class_id = c.id AND a.att_date >= %s
             WHERE c.year_id = %d
             GROUP BY c.id, c.grade_level, c.section, c.stage
             HAVING marked > 0
             ORDER BY (present / marked) ASC",
            gmdate('Y-m-d', time() - $days * DAY_IN_SECONDS),
            SCH_Years::current_id()
        )) ?: [];
    }

    /** تحصيل آخر ستة أشهر. */
    public static function collection_trend(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(paid_at, '%%Y-%%m') AS period,
                    COALESCE(SUM(amount), 0) AS total
             FROM " . sch_table('payments') . "
             WHERE paid_at >= %s
             GROUP BY period
             ORDER BY period",
            gmdate('Y-m-01', strtotime('-5 months'))
        )) ?: [];
    }
}
