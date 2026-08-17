<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** العقود والإجازات. */
final class SCH_HR
{
    public const CONTRACT_TYPES = [
        'full_time' => 'دوام كامل',
        'part_time' => 'دوام جزئي',
        'temporary' => 'مؤقت',
    ];

    public const LEAVE_TYPES = [
        'annual'    => 'سنوية',
        'sick'      => 'مرضية',
        'emergency' => 'اضطرارية',
        'unpaid'    => 'بدون راتب',
    ];

    // ---------- العقود ----------

    public static function create_contract(array $d): array|WP_Error
    {
        global $wpdb;

        $user_id = absint($d['user_id'] ?? 0);
        $start   = sch_sanitize_date($d['start_date'] ?? null);

        if ($user_id === 0 || !get_user_by('id', $user_id)) {
            return sch_api_error('user_not_found', __('الموظف غير موجود.', 'school-system'), 404);
        }
        if (!$start) {
            return sch_api_error('bad_date', __('تاريخ بداية العقد مطلوب.', 'school-system'), 422);
        }

        $end = sch_sanitize_date($d['end_date'] ?? null);
        if ($end !== null && $end <= $start) {
            return sch_api_error('bad_range', __('تاريخ النهاية يجب أن يلي تاريخ البداية.', 'school-system'), 422);
        }

        // عقد نشط واحد لكل موظف — القديم يُنهى تلقائيًا.
        $wpdb->update(
            sch_table('contracts'),
            ['status' => 'terminated', 'updated_at' => sch_now()],
            ['user_id' => $user_id, 'status' => 'active']
        );

        $wpdb->insert(sch_table('contracts'), [
            'user_id'       => $user_id,
            'contract_no'   => sanitize_text_field((string) ($d['contract_no'] ?? '')) ?: null,
            'contract_type' => array_key_exists($d['contract_type'] ?? '', self::CONTRACT_TYPES) ? $d['contract_type'] : 'full_time',
            'start_date'    => $start,
            'end_date'      => $end,
            'basic_salary'  => round((float) ($d['basic_salary'] ?? 0), 2),
            'allowances'    => round((float) ($d['allowances'] ?? 0), 2),
            'status'        => 'active',
            'created_at'    => sch_now(),
            'updated_at'    => sch_now(),
        ]);

        // insert_id يُحتجَز قبل sch_audit(): التدقيق يُدرج صفًا بنفسه
        // فيصير insert_id رقم صف السجل لا رقم السجل المُنشأ.
        $new_id = (int) $wpdb->insert_id;

        sch_audit('contract.created', 'contract', $new_id, ['user' => $user_id]);
        return ['id' => $new_id];
    }

    public static function active_contract(int $user_id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('contracts') . " WHERE user_id = %d AND status = 'active' LIMIT 1",
            $user_id
        )) ?: null;
    }

    public static function contracts(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT c.*, u.display_name FROM ' . sch_table('contracts') . ' c
             INNER JOIN ' . $wpdb->users . ' u ON u.ID = c.user_id
             ORDER BY c.status, u.display_name'
        ) ?: [];
    }

    /** العقود المنتهية خلال 60 يومًا. */
    public static function expiring_contracts(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT c.*, u.display_name FROM ' . sch_table('contracts') . ' c
             INNER JOIN ' . $wpdb->users . ' u ON u.ID = c.user_id
             WHERE c.status = %s AND c.end_date IS NOT NULL AND c.end_date <= %s
             ORDER BY c.end_date',
            'active',
            gmdate('Y-m-d', time() + 60 * DAY_IN_SECONDS)
        )) ?: [];
    }

    // ---------- الإجازات ----------

    public static function request_leave(array $d): array|WP_Error
    {
        global $wpdb;

        $user_id = absint($d['user_id'] ?? 0) ?: get_current_user_id();
        $start   = sch_sanitize_date($d['start_date'] ?? null);
        $end     = sch_sanitize_date($d['end_date'] ?? null);

        if (!$start || !$end) {
            return sch_api_error('bad_dates', __('تاريخا الإجازة مطلوبان.', 'school-system'), 422);
        }
        if ($end < $start) {
            return sch_api_error('bad_range', __('تاريخ النهاية قبل البداية.', 'school-system'), 422);
        }

        $days = (int) ((strtotime($end) - strtotime($start)) / DAY_IN_SECONDS) + 1;

        // منع تداخل إجازتين معتمدتين لنفس الموظف.
        $overlap = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('leaves') . "
             WHERE user_id = %d AND status IN ('pending','approved')
               AND start_date <= %s AND end_date >= %s LIMIT 1",
            $user_id,
            $end,
            $start
        ));

        if ($overlap) {
            return sch_api_error('overlap', __('توجد إجازة متداخلة مع هذه الفترة.', 'school-system'), 409);
        }

        $wpdb->insert(sch_table('leaves'), [
            'user_id'    => $user_id,
            'leave_type' => array_key_exists($d['leave_type'] ?? '', self::LEAVE_TYPES) ? $d['leave_type'] : 'annual',
            'start_date' => $start,
            'end_date'   => $end,
            'days'       => max(1, $days),
            'reason'     => sanitize_text_field((string) ($d['reason'] ?? '')) ?: null,
            'status'     => 'pending',
            'created_at' => sch_now(),
        ]);

        $new_id = (int) $wpdb->insert_id;

        sch_audit('leave.requested', 'leave', $new_id, ['user' => $user_id, 'days' => $days]);
        return ['id' => $new_id];
    }

    public static function decide_leave(int $id, string $decision): bool|WP_Error
    {
        global $wpdb;

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return sch_api_error('bad_decision', __('القرار غير صحيح.', 'school-system'), 422);
        }

        $leave = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('leaves') . ' WHERE id = %d', $id));
        if (!$leave) {
            return sch_api_error('not_found', __('الطلب غير موجود.', 'school-system'), 404);
        }

        $wpdb->update(sch_table('leaves'), [
            'status'     => $decision,
            'decided_by' => get_current_user_id() ?: null,
            'decided_at' => sch_now(),
        ], ['id' => $id]);

        SCH_Comms::notify(
            (int) $leave->user_id,
            $decision === 'approved' ? __('اعتُمدت إجازتك', 'school-system') : __('رُفض طلب إجازتك', 'school-system'),
            sprintf('%s → %s', $leave->start_date, $leave->end_date),
            'leave',
            $id
        );

        sch_audit('leave.' . $decision, 'leave', $id);
        return true;
    }

    public static function leaves(?string $status = null): array
    {
        global $wpdb;

        $sql = 'SELECT l.*, u.display_name FROM ' . sch_table('leaves') . ' l
                INNER JOIN ' . $wpdb->users . ' u ON u.ID = l.user_id';

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                $sql . ' WHERE l.status = %s ORDER BY l.start_date DESC LIMIT 200',
                $status
            )) ?: [];
        }

        return $wpdb->get_results($sql . ' ORDER BY l.id DESC LIMIT 200') ?: [];
    }

    /** الموظفون في إجازة معتمدة اليوم. */
    public static function on_leave_today(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT l.*, u.display_name FROM ' . sch_table('leaves') . ' l
             INNER JOIN ' . $wpdb->users . ' u ON u.ID = l.user_id
             WHERE l.status = %s AND %s BETWEEN l.start_date AND l.end_date',
            'approved',
            current_time('Y-m-d')
        )) ?: [];
    }
}

/** مسير الرواتب. */
final class SCH_Payroll
{
    public const MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
        7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    /** توليد مسير الشهر من العقود النشطة. */
    public static function generate(int $year, int $month): array|WP_Error
    {
        global $wpdb;

        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
            return sch_api_error('bad_period', __('الشهر أو السنة غير صحيحة.', 'school-system'), 422);
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('payroll_runs') . ' WHERE period_year = %d AND period_month = %d',
            $year,
            $month
        ));

        if ($existing && $existing->status === 'posted') {
            return sch_api_error('already_posted', __('مسير هذا الشهر مرحّل ولا يُعاد توليده.', 'school-system'), 409);
        }

        $contracts = $wpdb->get_results($wpdb->prepare(
            'SELECT c.*, u.display_name FROM ' . sch_table('contracts') . ' c
             INNER JOIN ' . $wpdb->users . ' u ON u.ID = c.user_id
             WHERE c.status = %s',
            'active'
        )) ?: [];

        if ($contracts === []) {
            return sch_api_error('no_contracts', __('لا توجد عقود نشطة.', 'school-system'), 422);
        }

        $wpdb->query('START TRANSACTION');

        if ($existing) {
            $run_id = (int) $existing->id;
            $wpdb->delete(sch_table('payslips'), ['run_id' => $run_id]);
        } else {
            $wpdb->insert(sch_table('payroll_runs'), [
                'period_year'  => $year,
                'period_month' => $month,
                'status'       => 'draft',
                'created_by'   => get_current_user_id() ?: null,
                'created_at'   => sch_now(),
            ]);
            $run_id = (int) $wpdb->insert_id;
        }

        $total = 0.0;

        foreach ($contracts as $c) {
            $basic      = (float) $c->basic_salary;
            $allowances = (float) $c->allowances;
            $deductions = self::unpaid_deduction((int) $c->user_id, $year, $month, $basic);
            $net        = round($basic + $allowances - $deductions, 2);

            $wpdb->insert(sch_table('payslips'), [
                'run_id'     => $run_id,
                'user_id'    => (int) $c->user_id,
                'basic'      => $basic,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'net'        => $net,
                'created_at' => sch_now(),
            ]);

            $total += $net;
        }

        $wpdb->update(sch_table('payroll_runs'), [
            'total'     => round($total, 2),
            'headcount' => count($contracts),
        ], ['id' => $run_id]);

        $wpdb->query('COMMIT');
        sch_audit('payroll.generated', 'payroll', $run_id, ['total' => $total]);

        return ['id' => $run_id];
    }

    /** ترحيل المسير محاسبيًا: مصروف رواتب مقابل النقدية. */
    public static function post(int $run_id): bool|WP_Error
    {
        global $wpdb;

        $run = self::get_run($run_id);
        if (!$run) {
            return sch_api_error('not_found', __('المسير غير موجود.', 'school-system'), 404);
        }
        if ($run->status === 'posted') {
            return true;
        }

        $wpdb->update(
            sch_table('payroll_runs'),
            ['status' => 'posted', 'posted_at' => sch_now()],
            ['id' => $run_id]
        );

        $expense = SCH_Accounts::id_of('salaries_exp');
        $bank    = SCH_Accounts::id_of('bank');

        if ($expense > 0 && $bank > 0 && (float) $run->total > 0) {
            SCH_Journal::create([
                'description' => sprintf(
                    /* translators: 1: الشهر 2: السنة */
                    __('مسير رواتب %1$s %2$d', 'school-system'),
                    self::MONTHS[(int) $run->period_month] ?? '',
                    (int) $run->period_year
                ),
                'ref_type' => 'payroll',
                'ref_id'   => $run_id,
            ], [
                ['account_id' => $expense, 'debit' => (float) $run->total, 'credit' => 0],
                ['account_id' => $bank,    'debit' => 0,                  'credit' => (float) $run->total],
            ]);
        }

        sch_audit('payroll.posted', 'payroll', $run_id);
        return true;
    }

    public static function get_run(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('payroll_runs') . ' WHERE id = %d', $id)) ?: null;
    }

    public static function runs(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . sch_table('payroll_runs') . ' ORDER BY period_year DESC, period_month DESC LIMIT 36'
        ) ?: [];
    }

    public static function payslips(int $run_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT p.*, u.display_name FROM ' . sch_table('payslips') . ' p
             INNER JOIN ' . $wpdb->users . ' u ON u.ID = p.user_id
             WHERE p.run_id = %d ORDER BY u.display_name',
            $run_id
        )) ?: [];
    }

    /** خصم أيام الإجازة بدون راتب من الشهر. */
    private static function unpaid_deduction(int $user_id, int $year, int $month, float $basic): float
    {
        global $wpdb;

        $first = sprintf('%04d-%02d-01', $year, $month);
        $last  = gmdate('Y-m-t', strtotime($first));

        $days = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COALESCE(SUM(days), 0) FROM ' . sch_table('leaves') . "
             WHERE user_id = %d AND status = 'approved' AND leave_type = 'unpaid'
               AND start_date <= %s AND end_date >= %s",
            $user_id,
            $last,
            $first
        ));

        return $days > 0 ? round($basic / 30 * min($days, 30), 2) : 0.0;
    }
}
