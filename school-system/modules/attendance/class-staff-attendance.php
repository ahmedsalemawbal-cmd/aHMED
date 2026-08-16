<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * حضور الموظفين بالمسح.
 *
 * الموظف يمسح بطاقته عند البوابة كالطالب تمامًا — فيُسجَّل دخوله وخروجه
 * إلكترونيًا، ويُحسب تأخّره من وقت المسح مقارنةً بموعد بدء الدوام.
 * سجل واحد لكل موظف في كل يوم (قيد فريد user_id + att_date).
 *
 * هذا الصنف هو الكاتب الوحيد لجدول staff_attendance — المسح والإدخال اليدوي
 * (عبر SCH_Deputy) كلاهما يمرّ من هنا، فلا مصدرا حقيقة يتباعدان.
 */
final class SCH_StaffAttendance
{
    /** بداية الدوام المتوقّعة للموظف ودقائق التسامح ونهاية الدوام — قابلة للضبط. */
    private const EXPECTED_IN = '07:00';
    private const GRACE_MIN   = 10;
    private const DAY_END     = '13:00';

    public static function expected_in(): string
    {
        $v = (string) sch_settings('attendance_staff_in', '');
        return preg_match('/^\d{2}:\d{2}$/', $v) ? $v : self::EXPECTED_IN;
    }

    public static function grace(): int
    {
        $v = sch_settings('attendance_grace', '');
        return $v !== '' ? max(0, (int) $v) : self::GRACE_MIN;
    }

    public static function day_end(): string
    {
        $v = (string) sch_settings('attendance_day_end', '');
        return preg_match('/^\d{2}:\d{2}$/', $v) ? $v : self::DAY_END;
    }

    // ---------- التعرّف ----------

    /** موظف نشط من رمز بطاقته العشوائي — أو null إن لم يكن الرمز لموظف. */
    public static function resolve_badge(string $token): ?object
    {
        global $wpdb;

        $token = sanitize_text_field($token);
        if (strlen($token) < 8) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT e.id, e.user_id, e.employee_no, e.job_title, e.badge_token, u.display_name
             FROM ' . sch_table('employees') . ' e
             INNER JOIN ' . $wpdb->users . ' u ON u.ID = e.user_id
             WHERE e.badge_token = %s AND e.status = %s
             LIMIT 1',
            $token,
            'active'
        ));

        if (!$row) {
            return null;
        }

        $user       = get_user_by('id', (int) $row->user_id);
        $row->role  = $user instanceof WP_User ? (string) ($user->roles[0] ?? '') : '';

        return $row;
    }

    // ---------- المسح ----------

    /**
     * توزيع مسح البوابة: صباحًا دخول، مساءً خروج.
     *
     * @return array{ok:bool,kind:string,duplicate:bool,mode:string,status:string,
     *               status_label:string,minutes_late:int,checkpoint_label:string,
     *               checked_at:string,person:array}
     */
    public static function scan(object $staff, string $checkpoint, array $params): array
    {
        $occurred = sch_sanitize_datetime($params['occurred_at'] ?? null) ?: sch_now();
        $out      = in_array($checkpoint, ['gate_out', 'early_out'], true);

        return $out
            ? self::check_out((int) $staff->user_id, $occurred, $staff)
            : self::check_in((int) $staff->user_id, $occurred, $staff);
    }

    /** دخول: يُحسب التأخّر من وقت المسح. المسح المكرر في نفس اليوم لا يعيد ضبط الوقت. */
    public static function check_in(int $user_id, string $occurred, ?object $staff = null): array
    {
        global $wpdb;

        $date     = substr($occurred, 0, 10) ?: current_time('Y-m-d');
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT id, status, checked_at, minutes_late FROM ' . sch_table('staff_attendance')
            . ' WHERE user_id = %d AND att_date = %s',
            $user_id,
            $date
        ));

        // سُجّل دخوله من قبل اليوم؟ أعِد النتيجة الأولى بلا ضبط ثانٍ (idempotency).
        if ($existing && $existing->checked_at && in_array($existing->status, ['present', 'late'], true)) {
            return self::response($user_id, 'in', (string) $existing->status, (int) $existing->minutes_late, $existing->checked_at, true, $staff);
        }

        $arr = self::arrival_status($occurred);

        self::write($user_id, $date, [
            'status'       => $arr['status'],
            'method'       => 'qr',
            'checked_at'   => $occurred,
            'minutes_late' => $arr['minutes'],
            'recorded_by'  => get_current_user_id() ?: null,
        ]);

        return self::response($user_id, 'in', $arr['status'], $arr['minutes'], $occurred, false, $staff);
    }

    /** خروج: يثبّت وقت الانصراف — يُحتسب منه الدوام والخروج المبكر في التقارير. */
    public static function check_out(int $user_id, string $occurred, ?object $staff = null): array
    {
        global $wpdb;

        $date     = substr($occurred, 0, 10) ?: current_time('Y-m-d');
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT id, status, checked_at, checkout_at, minutes_late FROM ' . sch_table('staff_attendance')
            . ' WHERE user_id = %d AND att_date = %s',
            $user_id,
            $date
        ));

        if ($existing) {
            self::write($user_id, $date, ['checkout_at' => $occurred]);
            $status = (string) $existing->status;
            $mins   = (int) $existing->minutes_late;
        } else {
            // خرج بلا تسجيل دخول — كان حاضرًا، نثبّت خروجه فقط.
            self::write($user_id, $date, [
                'status'      => 'present',
                'method'      => 'qr',
                'checkout_at' => $occurred,
                'recorded_by' => get_current_user_id() ?: null,
            ]);
            $status = 'present';
            $mins   = 0;
        }

        return self::response($user_id, 'out', $status, $mins, $occurred, false, $staff);
    }

    /** الإدخال اليدوي (من الوكيل) — يمرّ من هنا فلا كاتبان للجدول. */
    public static function mark_manual(int $user_id, string $status, string $note = ''): bool|WP_Error
    {
        if (!array_key_exists($status, SCH_Deputy::STAFF_STATUSES)) {
            return sch_api_error('bad_status', __('حالة الحضور غير صحيحة.', 'school-system'), 422);
        }

        self::write($user_id, current_time('Y-m-d'), [
            'status'       => $status,
            'method'       => 'manual',
            'checked_at'   => sch_now(),
            'minutes_late' => 0,
            'note'         => sanitize_text_field($note) ?: null,
            'recorded_by'  => get_current_user_id() ?: null,
        ]);

        return true;
    }

    // ---------- التقارير ----------

    /**
     * سجل شهر لموظف، مفهرسًا باليوم (Y-m-d) => صف.
     *
     * @param string $ym صيغة 'Y-m'
     * @return array<string,object>
     */
    public static function month(int $user_id, string $ym): array
    {
        global $wpdb;

        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = current_time('Y-m');
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT att_date, status, method, checked_at, checkout_at, minutes_late, note
             FROM ' . sch_table('staff_attendance') . "
             WHERE user_id = %d AND att_date LIKE %s
             ORDER BY att_date",
            $user_id,
            $wpdb->esc_like($ym) . '-%'
        )) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->att_date] = $r;
        }

        return $out;
    }

    /** ملخّص شهر: عدّاد كل حالة + مجموع دقائق التأخّر. */
    public static function month_totals(int $user_id, string $ym): array
    {
        $totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'late_minutes' => 0];

        foreach (self::month($user_id, $ym) as $r) {
            $s = (string) $r->status;
            if (isset($totals[$s])) {
                $totals[$s]++;
            }
            $totals['late_minutes'] += (int) $r->minutes_late;
        }

        return $totals;
    }

    // ---------- الداخل ----------

    /** حالة الوصول من وقت المسح: حاضر أو متأخر، ودقائق التأخّر عن الموعد. */
    private static function arrival_status(string $occurred): array
    {
        $t = substr($occurred, 11, 5);
        if (!preg_match('/^\d{2}:\d{2}$/', $t)) {
            return ['status' => 'present', 'minutes' => 0];
        }

        [$h, $m]   = array_map('intval', explode(':', $t));
        [$eh, $em] = array_map('intval', explode(':', self::expected_in()));

        $mins     = $h * 60 + $m;
        $expected = $eh * 60 + $em;
        $late     = $mins > ($expected + self::grace());

        return [
            'status'  => $late ? 'late' : 'present',
            'minutes' => $late ? max(0, $mins - $expected) : 0,
        ];
    }

    /** upsert على القيد الفريد (user_id, att_date) — كاتب الجدول الوحيد. */
    private static function write(int $user_id, string $date, array $data): void
    {
        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('staff_attendance') . ' WHERE user_id = %d AND att_date = %s',
            $user_id,
            $date
        ));

        if ($existing) {
            $wpdb->update(sch_table('staff_attendance'), $data, ['id' => (int) $existing]);
        } else {
            $wpdb->insert(sch_table('staff_attendance'), $data + [
                'user_id'    => $user_id,
                'att_date'   => $date,
                'created_at' => sch_now(),
            ]);
        }
    }

    /** استجابة موحّدة لتطبيق البوابة. */
    private static function response(int $user_id, string $mode, string $status, int $minutes, string $when, bool $dup, ?object $staff): array
    {
        $role   = $staff->role ?? '';
        $label  = SCH_Deputy::STAFF_STATUSES[$status] ?? $status;

        // سطر جاهز للعرض على شاشة البوابة — «متأخر · ١٢ د» أو «حاضر ٠٧:٠٢».
        if ($mode === 'out') {
            $flash = sprintf(__('انصرف · %s', 'school-system'), substr($when, 11, 5));
        } elseif ($status === 'late' && $minutes > 0) {
            $flash = sprintf(
                /* translators: 1: الحالة 2: دقائق التأخّر */
                __('%1$s · %2$s د', 'school-system'),
                $label,
                number_format_i18n($minutes)
            );
        } else {
            $flash = sprintf(__('%1$s · %2$s', 'school-system'), $label, substr($when, 11, 5));
        }

        return [
            'ok'               => true,
            'kind'             => 'staff',
            'duplicate'        => $dup,
            'mode'             => $mode,
            'status'           => $status,
            'status_label'     => $label,
            'flash'            => $flash,
            'minutes_late'     => $minutes,
            'checkpoint_label' => $mode === 'out' ? __('خروج', 'school-system') : __('دخول', 'school-system'),
            'checked_at'       => substr($when, 11, 5),
            'person'           => [
                'user_id'    => $user_id,
                'name'       => (string) ($staff->display_name ?? ''),
                'role'       => (string) $role,
                'role_label' => $role !== '' ? SCH_Staff::role_label((string) $role) : '',
                'job_title'  => (string) ($staff->job_title ?? ''),
            ],
        ];
    }
}
