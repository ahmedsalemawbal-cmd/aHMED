<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * التقليم الدوريّ — ما ينمو بلا حدّ.
 *
 * لم يكن في النظام حذفٌ لصفٍّ واحد من الإشعارات ولا من طابور التسليم. وهذا
 * لا يُرى بأربعة طلاب؛ ويُرى بأربعمئة:
 *
 *   ٤٠٠ طالب × وليّا أمر × ٣ إشعارات يوميًّا × ١٨٠ يومًا
 *   ≈ **٤٣٠ ألف صفّ إشعار في السنة**، ومعها ضِعفها في طابور التسليم.
 *
 * وثلاثة آثار، أشدّها الثالث:
 *
 * 1. الجدول ينتفخ ونسخُه الاحتياطية معه.
 * 2. **`SCH_Backup::dump_table()` تكتب كل صفّ في الأرشيف** — فنسخة مدرسةٍ
 *    بعد سنتين تصير مئات الميجابايت، وقد تتجاوز `max_execution_time` فتفشل
 *    **في اللحظة التي يُحتاج فيها إليها**.
 * 3. وسجلّ التدقيق يُقرأ للبحث عن حادثة، فيغرق في مليون سطرٍ روتينيّ.
 *
 * **وما لا يُقلَّم أبدًا:** `custody_events` (سجلٌّ لا يُعدَّل ولا يُحذف —
 * قاعدة المشروع) · `attendance` · `invoices` و`payments` (وثائق مالية) ·
 * `student_notes` (لها دورتها بـ`recheck_at`). فالتقليم للضجيج لا للسجلّ.
 */
final class SCH_Retention
{
    /**
     * كم يبقى كلٌّ — بالأيام.
     *
     * والإشعار المقروء يُقلَّم أسرع من غير المقروء: الأوّل أدّى غرضه،
     * والثاني ما زال ينتظر صاحبه.
     */
    private const KEEP = [
        'notifications_read'   => 120,
        'notifications_unread' => 365,
        'deliveries_done'      => 60,
        'audit'                => 730,
        'positions'            => 30,
    ];

    /** حدّ الحذف في الدفعة الواحدة — أكبر منه يُقفل الجدول ويُبطئ الموقع. */
    private const BATCH = 2000;

    /**
     * كنسةٌ ليلية.
     *
     * تُنادى من المهمّة الليلية بعد `nightly_reset`. وكل جدولٍ يُحذف منه
     * دفعةٌ واحدة في الليلة: أبطأ من حذف كل شيء دفعةً، **لكنه لا يُقفل
     * جدولًا يقرأ منه تطبيقُ وليّ أمرٍ في اللحظة نفسها**. والتراكم يذوب في
     * ليالٍ قليلة ثم يستقرّ.
     *
     * @return array<string,int> ما حُذف من كل جدول
     */
    public static function sweep(): array
    {
        global $wpdb;

        $now  = strtotime(current_time('mysql'));
        $out  = [];
        $ago  = static fn (int $days): string => gmdate('Y-m-d H:i:s', $now - $days * DAY_IN_SECONDS);

        // ① طابور التسليم — ما أُرسل أو يئس. والمعلَّق يبقى حتى يُحسم.
        $out['deliveries'] = (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . sch_table('deliveries') . "
              WHERE status IN ('sent', 'failed') AND updated_at < %s
              LIMIT %d",
            $ago(self::KEEP['deliveries_done']),
            self::BATCH
        ));

        /*
         * ② الإشعارات — والمقروء أوّلًا.
         *
         * ويُحذف الإشعار بعد صفوف تسليمه لا قبلها: العكس يترك صفوفًا يتيمة
         * تشير إلى إشعارٍ لا وجود له، ويُعمى تتبّع «لماذا لم تصل الرسالة؟».
         */
        $out['notifications_read'] = (int) $wpdb->query($wpdb->prepare(
            'DELETE n FROM ' . sch_table('notifications') . ' n
              WHERE n.read_at IS NOT NULL AND n.created_at < %s
                AND NOT EXISTS (SELECT 1 FROM ' . sch_table('deliveries') . ' d WHERE d.notification_id = n.id)
              LIMIT %d',
            $ago(self::KEEP['notifications_read']),
            self::BATCH
        ));

        $out['notifications_old'] = (int) $wpdb->query($wpdb->prepare(
            'DELETE n FROM ' . sch_table('notifications') . ' n
              WHERE n.created_at < %s
                AND NOT EXISTS (SELECT 1 FROM ' . sch_table('deliveries') . ' d WHERE d.notification_id = n.id)
              LIMIT %d',
            $ago(self::KEEP['notifications_unread']),
            self::BATCH
        ));

        // ③ مواقع المركبات — عيّناتٌ للأرشيف لا سجلّ يُراجَع.
        $out['positions'] = (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . sch_table('vehicle_positions') . '
              WHERE recorded_at < %s LIMIT %d',
            $ago(self::KEEP['positions']),
            self::BATCH
        ));

        /*
         * ④ سجلّ التدقيق — سنتان.
         *
         * ولا يُقلَّم منه ما يخصّ المال ولا العهدة ولا الصلاحيات: هذه هي
         * الأسطر التي يُفتَح السجلّ من أجلها بعد سنتين، والباقي ضجيجٌ يُخفيها.
         */
        $out['audit'] = (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . sch_table('audit_log') . "
              WHERE created_at < %s
                AND action NOT LIKE 'invoice.%%'
                AND action NOT LIKE 'payment.%%'
                AND action NOT LIKE 'custody.%%'
                AND action NOT LIKE 'perm.%%'
                AND action NOT LIKE 'backup.%%'
              LIMIT %d",
            $ago(self::KEEP['audit']),
            self::BATCH
        ));

        $total = array_sum($out);

        if ($total > 0) {
            sch_audit('retention.swept', 'system', null, $out);
        }

        return $out;
    }
}
