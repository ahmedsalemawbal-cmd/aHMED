<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * ترقية العام الدراسي.
 *
 * أثقل عملية إدارية في السنة: فتح عام جديد وإعادة تسجيل كل طالب. تُنفَّذ هنا
 * بخريطة صفوف يحدّدها المدير (الصف free-text فلا يُخمَّن الترقّي) داخل معاملة
 * واحدة، وهي **idempotent**: من سُجِّل في سنة الهدف لا يُكرَّر.
 *
 * لا تلمس بيانات سنة المصدر — تُنشئ في سنة الهدف فقط. ومزامنة الحقول المبسّطة
 * (المرحلة/الصف/الشعبة على سجل الطالب) تتم عند **تفعيل** السنة لا هنا، فلا
 * تُمثَّل السنة الجديدة قبل أن تصير الحالية.
 */
final class SCH_Rollover
{
    /**
     * ملخص صفوف سنة المصدر وأعداد طلابها — للمعاينة قبل التنفيذ.
     * @return array<int,object>
     */
    public static function plan(int $from_year): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.id AS class_id, c.stage, c.grade_level, c.section,
                    COUNT(e.id) AS students
             FROM " . sch_table('classes') . " c
             LEFT JOIN " . sch_table('enrollments') . " e
                    ON e.class_id = c.id AND e.year_id = %d AND e.status = 'active'
             WHERE c.year_id = %d
             GROUP BY c.id, c.stage, c.grade_level, c.section
             ORDER BY c.stage, c.grade_level, c.section",
            $from_year,
            $from_year
        )) ?: [];
    }

    /**
     * تنفيذ الترقية.
     *
     * @param array<string,string> $grade_map  مفتاح «stage|grade» ← اسم الصف في سنة الهدف
     * @param array<string,mixed>  $graduate   مفتاح «stage|grade» موجودٌ ⇐ يتخرّج ولا يُرقَّى
     */
    public static function run(int $from_year, int $to_year, array $grade_map, array $graduate): array|WP_Error
    {
        // واللحظة الثانية: ترحيل السنة ينقل كل طالبٍ ويُخرّج دفعةً كاملة.
        if (class_exists('SCH_Backup')) {
            SCH_Backup::auto('before_rollover');
        }

        global $wpdb;

        if ($from_year <= 0 || $to_year <= 0 || $from_year === $to_year) {
            return sch_api_error('bad_years', __('اختر سنة مصدر وسنة هدف مختلفتين.', 'school-system'), 422);
        }

        $classes = self::plan($from_year);
        if ($classes === []) {
            return sch_api_error('empty', __('لا صفوف في سنة المصدر.', 'school-system'), 422);
        }

        // من سُجِّل في سنة الهدف مسبقًا لا يُكرَّر (إعادة التشغيل آمنة).
        $already = array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT student_id FROM ' . sch_table('enrollments') . ' WHERE year_id = %d',
            $to_year
        )) ?: []);

        $wpdb->query('START TRANSACTION');

        $promoted = 0;
        $graduated = 0;
        $created_classes = 0;
        $target_cache = [];

        foreach ($classes as $c) {
            $key  = $c->stage . '|' . $c->grade_level;
            $sids = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT student_id FROM " . sch_table('enrollments') . "
                 WHERE class_id = %d AND year_id = %d AND status = 'active'",
                (int) $c->class_id,
                $from_year
            )) ?: []);

            if (!empty($graduate[$key])) {
                $graduated += count($sids);
                continue;
            }

            $target_grade = (isset($grade_map[$key]) && trim((string) $grade_map[$key]) !== '')
                ? sanitize_text_field((string) $grade_map[$key])
                : (string) $c->grade_level;

            // اعثر على شعبة الهدف (نفس المرحلة والشعبة، الصف المُرقّى) أو أنشئها.
            $tkey = $c->stage . '|' . $target_grade . '|' . $c->section;
            if (isset($target_cache[$tkey])) {
                $target_class_id = $target_cache[$tkey];
            } else {
                $target_class_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM " . sch_table('classes') . "
                     WHERE year_id = %d AND stage = %s AND grade_level = %s AND section = %s LIMIT 1",
                    $to_year,
                    $c->stage,
                    $target_grade,
                    $c->section
                ));

                if ($target_class_id === 0) {
                    $ins = $wpdb->insert(sch_table('classes'), [
                        'year_id'             => $to_year,
                        'stage'               => $c->stage,
                        'grade_level'         => $target_grade,
                        'section'             => $c->section,
                        'capacity'            => 30,
                        'homeroom_teacher_id' => null,
                        'created_at'          => sch_now(),
                        'updated_at'          => sch_now(),
                    ]);
                    if ($ins === false) {
                        $wpdb->query('ROLLBACK');
                        return sch_api_error('db_error', __('تعذّر إنشاء شعبة الهدف.', 'school-system'), 500);
                    }
                    $target_class_id = (int) $wpdb->insert_id;
                    $created_classes++;
                }

                $target_cache[$tkey] = $target_class_id;
            }

            foreach ($sids as $sid) {
                if (in_array($sid, $already, true)) {
                    continue;
                }
                $ins = $wpdb->insert(sch_table('enrollments'), [
                    'student_id' => $sid,
                    'class_id'   => $target_class_id,
                    'year_id'    => $to_year,
                    'status'     => 'active',
                    'created_at' => sch_now(),
                ]);
                if ($ins === false) {
                    $wpdb->query('ROLLBACK');
                    return sch_api_error('db_error', __('تعذّر ترقية أحد الطلاب.', 'school-system'), 500);
                }
                $already[] = $sid;
                $promoted++;
            }
        }

        $wpdb->query('COMMIT');

        sch_audit('year.rollover', 'year', $to_year, [
            'from'      => $from_year,
            'promoted'  => $promoted,
            'graduated' => $graduated,
            'classes'   => $created_classes,
        ]);

        return ['promoted' => $promoted, 'graduated' => $graduated, 'classes' => $created_classes];
    }
}
