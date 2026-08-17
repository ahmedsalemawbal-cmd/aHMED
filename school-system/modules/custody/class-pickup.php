<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * نداء الانصراف — «أنا عند البوابة، أخرِجوا ابني».
 *
 * المشهد الذي تحلّه: وليّ الأمر يقف بسيارته وقت الانصراف، فيتصل بالمدرسة أو
 * يرسل رسالة أو ينزل ويسأل. والمدرسة تنادي بالمكبّر اسمًا وسط ثلاثمئة طفل.
 * الآن: **ضغطة واحدة** تُظهر اسم الطالب في لوحة الإدارة، فتأخذه المشرفة
 * وتسلّمه في السيارة.
 *
 * وهي ليست بديلًا عن طلب الإجازة — الإجازة غيابٌ يُخطَّط له قبل يوم،
 * والنداء طلبٌ **الآن** ولا يُقبل إلا والطفل في عهدة المدرسة.
 *
 * **وثلاثة قيود مبنيّة لا اختيارية:**
 * 1. لا ينادي إلا **من يحقّ له الاستلام** (`SCH_Custody::pickers()`) —
 *    وإلا صار النداء بابًا خلفيًا يلتفّ على إنفاذ `can_pickup` كلّه.
 * 2. لا نداء إلا والطفل **داخل المدرسة** — نداء لطفل في الباص أو في بيته
 *    يُرسل المشرفة تبحث عمّن ليس هناك.
 * 3. **التسليم ليس إغلاق بطاقة**: إغلاق النداء يمرّ بـ`SCH_Custody::record()`
 *    فيُسجَّل خروجًا حقيقيًا باسم المستلم — ولو أُغلق بلا تسجيل لصار عندنا
 *    طفلٌ ذهب مع أبيه والنظام يقول إنه ما زال في المدرسة.
 */
final class SCH_Pickup
{
    public const STATUSES = [
        'open'      => 'بانتظار المشرفة',
        'onway'     => 'المشرفة في الطريق',
        'done'      => 'سُلّم',
        'cancelled' => 'أُلغي',
    ];

    /** أقصى عمر لنداء مفتوح — ما بعده يُعدّ منسيًّا لا قائمًا. */
    private const STALE_MINUTES = 90;

    /**
     * وليّ الأمر ينادي.
     *
     * @return array{id:int,status:string}|WP_Error
     */
    public static function call(int $student_id, int $caller_id, string $note = ''): array|WP_Error
    {
        global $wpdb;

        $student = SCH_Students::get($student_id);

        if (!$student) {
            return sch_api_error('no_student', __('لم يُعثر على الطالب.', 'school-system'), 404);
        }

        // (١) من يحقّ له الاستلام وحده ينادي.
        $key = self::picker_key_of($student_id, $caller_id);

        if ($key === '') {
            return sch_api_error(
                'not_allowed',
                __('حسابك غير مخوَّل باستلام هذا الطالب. راجع إدارة المدرسة.', 'school-system'),
                403
            );
        }

        // (٢) لا نداء إلا والطفل في المدرسة.
        if ((string) $student->custody_state !== 'at_school') {
            return sch_api_error(
                'not_at_school',
                __('الطالب ليس داخل المدرسة الآن — لا حاجة لاستدعائه.', 'school-system'),
                409
            );
        }

        // نداء قائم؟ يُعاد هو بدل ثانٍ يربك اللوحة.
        $open = self::open_for($student_id);

        if ($open) {
            return ['id' => (int) $open->id, 'status' => (string) $open->status];
        }

        $now = sch_now();

        $wpdb->insert(sch_table('pickup_calls'), [
            'student_id'  => $student_id,
            'caller_id'   => $caller_id,
            'caller_name' => (string) (get_userdata($caller_id)->display_name ?? ''),
            'picker_key'  => $key,
            'note'        => sanitize_text_field($note) ?: null,
            'status'      => 'open',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $id = (int) $wpdb->insert_id;

        sch_audit('pickup.called', 'student', $student_id, ['call' => $id]);
        self::alert_staff($student, $id);

        return ['id' => $id, 'status' => 'open'];
    }

    /** المشرفة تعلّم أنها في الطريق — فيتوقّف الأب عن التساؤل. */
    public static function ack(int $call_id): bool
    {
        global $wpdb;

        $ok = (bool) $wpdb->update(
            sch_table('pickup_calls'),
            ['status' => 'onway', 'ack_by' => get_current_user_id(), 'ack_at' => sch_now(), 'updated_at' => sch_now()],
            ['id' => $call_id, 'status' => 'open']
        );

        if ($ok) {
            $call = self::get($call_id);

            if ($call) {
                SCH_Comms::notify(
                    (int) $call->caller_id,
                    __('المشرفة في الطريق', 'school-system'),
                    __('استلمت المدرسة نداءك، والطالب في طريقه إليك.', 'school-system'),
                    'custody',
                    (int) $call->student_id
                );
            }
        }

        return $ok;
    }

    /**
     * التسليم — **يمرّ بآلة العهدة لا يلتفّ عليها.**
     *
     * لو أُغلق النداء بتحديث حالته فقط لبقي الطالب «داخل المدرسة» في كل شاشة
     * وكل تقرير، ولانقطعت سلسلة العهدة عند أخطر نقطة فيها.
     */
    public static function deliver(int $call_id): true|WP_Error
    {
        global $wpdb;

        $call = self::get($call_id);

        if (!$call || in_array((string) $call->status, ['done', 'cancelled'], true)) {
            return sch_api_error('gone', __('هذا النداء أُغلق من قبل.', 'school-system'), 409);
        }

        // المفتاح اسمه `picker` في عقد `record()` — و`picked_by` اسم العمود
        // في القاعدة. تمريره بالاسم الخطأ يجعل المستلم فارغًا **ويتخطّى فحص
        // `may_pick_up()` بصمت**، فيعود النداء بابًا خلفيًا على إنفاذ `can_pickup`.
        $done = SCH_Custody::record([
            'student_id'    => (int) $call->student_id,
            'checkpoint'    => 'gate_out',
            'picker'        => (string) $call->picker_key,
            'receiver_name' => (string) $call->caller_name,
            'client_uuid'   => 'pickup-' . $call_id,
        ]);

        if (is_wp_error($done)) {
            return $done;
        }

        $wpdb->update(
            sch_table('pickup_calls'),
            ['status' => 'done', 'closed_by' => get_current_user_id(), 'closed_at' => sch_now(), 'updated_at' => sch_now()],
            ['id' => $call_id]
        );

        sch_audit('pickup.delivered', 'student', (int) $call->student_id, ['call' => $call_id]);

        SCH_Comms::notify(
            (int) $call->caller_id,
            __('تم التسليم', 'school-system'),
            __('سُلّم الطالب وخرج من عهدة المدرسة.', 'school-system'),
            'custody',
            (int) $call->student_id
        );

        return true;
    }

    /** إلغاء — من الأب إن غيّر رأيه، أو من الإدارة بسبب. */
    public static function cancel(int $call_id, int $by = 0): bool
    {
        global $wpdb;

        $by = $by ?: get_current_user_id();

        $ok = (bool) $wpdb->update(
            sch_table('pickup_calls'),
            ['status' => 'cancelled', 'closed_by' => $by, 'closed_at' => sch_now(), 'updated_at' => sch_now()],
            ['id' => $call_id]
        );

        if ($ok) {
            sch_audit('pickup.cancelled', 'pickup', $call_id, []);
        }

        return $ok;
    }

    // ---------- قراءة ----------

    public static function get(int $call_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('pickup_calls') . ' WHERE id = %d',
            $call_id
        ));
    }

    /** النداء المفتوح لطالب — يراه أبوه في تطبيقه. */
    public static function open_for(int $student_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('pickup_calls') . "
              WHERE student_id = %d AND status IN ('open','onway')
                AND created_at >= %s
              ORDER BY id DESC LIMIT 1",
            $student_id,
            gmdate('Y-m-d H:i:s', (int) current_time('timestamp', true) - self::STALE_MINUTES * MINUTE_IN_SECONDS)
        ));
    }

    /**
     * لوحة المشرفة: النداءات المفتوحة بأقدمها أولًا.
     *
     * الأقدم أولًا لا الأحدث — الأب الذي ينتظر منذ عشر دقائق أولى بمن وصل الآن.
     */
    public static function board(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT c.*, s.full_name, s.grade_level, s.section, s.photo_file
               FROM ' . sch_table('pickup_calls') . ' c
               INNER JOIN ' . sch_table('students') . ' s ON s.id = c.student_id
              WHERE c.status IN (\'open\',\'onway\') AND c.created_at >= %s
              ORDER BY c.created_at ASC',
            gmdate('Y-m-d H:i:s', (int) current_time('timestamp', true) - self::STALE_MINUTES * MINUTE_IN_SECONDS)
        )) ?: [];
    }

    /** عدد النداءات المنتظرة — شارة في التنقّل. */
    public static function waiting(): int
    {
        return count(self::board());
    }

    // ---------- أدوات ----------

    /** مفتاح المستلم لهذا المستخدم عند هذا الطالب — فارغ إن لم يكن مخوَّلًا. */
    private static function picker_key_of(int $student_id, int $user_id): string
    {
        foreach (SCH_Custody::pickers($student_id) as $p) {
            if ($p['key'] === 'g:' . $user_id) {
                return (string) $p['key'];
            }
        }

        return '';
    }

    /**
     * تنبيه من يستلم النداء.
     *
     * يذهب لمن يملك صلاحية البوابة أو إدارة الحضور — لا لكل الموظفين:
     * إشعارٌ يصل من لا يعنيه يُدرَّب الناس على تجاهله.
     */
    private static function alert_staff(object $student, int $call_id): void
    {
        $title = __('نداء انصراف', 'school-system');
        $body  = sprintf(
            /* translators: 1: اسم الطالب 2: الصف */
            __('وليّ أمر %1$s عند البوابة — %2$s', 'school-system'),
            (string) $student->full_name,
            trim((string) $student->grade_level . ' / ' . (string) $student->section)
        );

        $seen = [];

        foreach (['sch_scan_gate', 'sch_manage_attendance', 'sch_manage_students'] as $cap) {
            foreach (get_users(['capability' => $cap, 'fields' => 'ID', 'number' => 40]) as $uid) {
                $uid = (int) $uid;

                if (isset($seen[$uid])) {
                    continue;
                }

                $seen[$uid] = true;
                SCH_Comms::notify($uid, $title, $body, 'custody', (int) $student->id);
            }
        }
    }
}
