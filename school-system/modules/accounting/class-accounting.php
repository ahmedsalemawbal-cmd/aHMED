<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** دليل الحسابات. شجرة حسابات بأنواعها الخمسة. */
final class SCH_Accounts
{
    public const TYPES = [
        'asset'     => 'أصول',
        'liability' => 'خصوم',
        'equity'    => 'حقوق ملكية',
        'revenue'   => 'إيرادات',
        'expense'   => 'مصروفات',
    ];

    /** الحسابات التي يعتمد عليها الترحيل التلقائي — لا تُحذف. */
    public const SYSTEM = [
        'students_ar' => '1210',
        'cash'        => '1110',
        'bank'        => '1120',
        'tuition_rev' => '4110',
        'transport_rev' => '4120',
        'salaries_exp'  => '5110',
    ];

    /** طبيعة الحساب: مدين أم دائن. */
    public static function is_debit_nature(string $type): bool
    {
        return in_array($type, ['asset', 'expense'], true);
    }

    public static function create(array $d): array|WP_Error
    {
        global $wpdb;

        $code = sanitize_text_field((string) ($d['code'] ?? ''));
        $name = sanitize_text_field((string) ($d['name'] ?? ''));
        $type = (string) ($d['type'] ?? '');

        if ($code === '' || $name === '' || !array_key_exists($type, self::TYPES)) {
            return sch_api_error('invalid_account', __('الرمز والاسم والنوع مطلوبة.', 'school-system'), 422);
        }

        $ok = $wpdb->insert(sch_table('accounts'), [
            'code'       => $code,
            'name'       => $name,
            'type'       => $type,
            'parent_id'  => absint($d['parent_id'] ?? 0) ?: null,
            'is_system'  => 0,
            'status'     => 'active',
            'created_at' => sch_now(),
        ]);

        if ($ok === false) {
            return sch_api_error('duplicate_code', __('رمز الحساب مستخدم.', 'school-system'), 409);
        }

        sch_audit('account.created', 'account', (int) $wpdb->insert_id, ['code' => $code]);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('accounts') . ' WHERE id = %d', $id)) ?: null;
    }

    public static function by_code(string $code): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('accounts') . ' WHERE code = %s', $code)) ?: null;
    }

    public static function id_of(string $key): int
    {
        $code    = self::SYSTEM[$key] ?? '';
        $account = $code !== '' ? self::by_code($code) : null;
        return $account ? (int) $account->id : 0;
    }

    public static function all(?string $type = null): array
    {
        global $wpdb;

        if ($type) {
            return $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . sch_table('accounts') . ' WHERE type = %s ORDER BY code',
                $type
            )) ?: [];
        }

        return $wpdb->get_results('SELECT * FROM ' . sch_table('accounts') . ' ORDER BY code') ?: [];
    }

    /** رصيد حساب من القيود المرحّلة. */
    public static function balance(int $account_id, ?string $upto = null): float
    {
        global $wpdb;

        $account = self::get($account_id);
        if (!$account) {
            return 0.0;
        }

        $upto ??= current_time('Y-m-d');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(l.debit), 0) AS d, COALESCE(SUM(l.credit), 0) AS c
             FROM " . sch_table('journal_lines') . " l
             INNER JOIN " . sch_table('journal_entries') . " e ON e.id = l.entry_id
             WHERE l.account_id = %d AND e.status = 'posted' AND e.entry_date <= %s",
            $account_id,
            $upto
        ));

        $debit  = (float) ($row->d ?? 0);
        $credit = (float) ($row->c ?? 0);

        return self::is_debit_nature((string) $account->type)
            ? round($debit - $credit, 2)
            : round($credit - $debit, 2);
    }

    /** ميزان المراجعة. */
    public static function trial_balance(?string $upto = null): array
    {
        global $wpdb;

        $upto ??= current_time('Y-m-d');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.code, a.name, a.type,
                    COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.debit  ELSE 0 END), 0) AS total_debit,
                    COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.credit ELSE 0 END), 0) AS total_credit
             FROM " . sch_table('accounts') . " a
             LEFT JOIN " . sch_table('journal_lines') . " l ON l.account_id = a.id
             LEFT JOIN " . sch_table('journal_entries') . " e
                    ON e.id = l.entry_id AND e.status = 'posted' AND e.entry_date <= %s
             WHERE a.status = 'active'
             GROUP BY a.id, a.code, a.name, a.type
             HAVING total_debit <> 0 OR total_credit <> 0
             ORDER BY a.code",
            $upto
        )) ?: [];
    }

    /** إنشاء دليل حسابات ابتدائي مناسب لمدرسة. */
    public static function seed(): void
    {
        global $wpdb;

        if ((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . sch_table('accounts')) > 0) {
            return;
        }

        $chart = [
            ['1000', 'الأصول', 'asset', 1],
            ['1100', 'النقدية والبنوك', 'asset', 1],
            ['1110', 'الصندوق', 'asset', 1],
            ['1120', 'البنك', 'asset', 1],
            ['1200', 'الذمم المدينة', 'asset', 1],
            ['1210', 'ذمم الطلاب', 'asset', 1],
            ['1300', 'الأصول الثابتة', 'asset', 0],
            ['2000', 'الخصوم', 'liability', 1],
            ['2100', 'الذمم الدائنة', 'liability', 0],
            ['2200', 'رواتب مستحقة', 'liability', 0],
            ['2300', 'إيرادات مقبوضة مقدمًا', 'liability', 1],
            ['3000', 'حقوق الملكية', 'equity', 1],
            ['3100', 'رأس المال', 'equity', 0],
            ['3200', 'الأرباح المحتجزة', 'equity', 1],
            ['4000', 'الإيرادات', 'revenue', 1],
            ['4110', 'إيرادات الرسوم الدراسية', 'revenue', 1],
            ['4120', 'إيرادات النقل المدرسي', 'revenue', 1],
            ['4130', 'إيرادات أخرى', 'revenue', 0],
            ['5000', 'المصروفات', 'expense', 1],
            ['5110', 'الرواتب والأجور', 'expense', 1],
            ['5120', 'الإيجارات', 'expense', 0],
            ['5130', 'الكهرباء والمياه', 'expense', 0],
            ['5140', 'الصيانة', 'expense', 0],
            ['5150', 'وقود وصيانة الباصات', 'expense', 0],
            ['5160', 'مستلزمات تعليمية', 'expense', 0],
            ['5170', 'مصروفات إدارية', 'expense', 0],
        ];

        foreach ($chart as [$code, $name, $type, $system]) {
            $wpdb->insert(sch_table('accounts'), [
                'code'       => $code,
                'name'       => $name,
                'type'       => $type,
                'is_system'  => $system,
                'status'     => 'active',
                'created_at' => sch_now(),
            ]);
        }
    }
}

/**
 * القيود اليومية بنظام القيد المزدوج.
 * قاعدة صارمة: مجموع المدين = مجموع الدائن، والقيد المرحّل لا يُعدَّل — يُعكَس بقيد مضاد.
 */
final class SCH_Journal
{
    /**
     * إنشاء قيد.
     * $lines = [['account_id'=>1,'debit'=>100,'credit'=>0,'description'=>''], ...]
     */
    public static function create(array $d, array $lines, bool $post = true, bool $in_tx = false): array|WP_Error
    {
        global $wpdb;

        $date = sch_sanitize_date($d['entry_date'] ?? null) ?? current_time('Y-m-d');
        $desc = sanitize_text_field((string) ($d['description'] ?? ''));

        if ($desc === '') {
            return sch_api_error('missing_description', __('وصف القيد مطلوب.', 'school-system'), 422);
        }

        $clean  = [];
        $debit  = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $account_id = absint($line['account_id'] ?? 0);
            $d_amt      = round((float) ($line['debit'] ?? 0), 2);
            $c_amt      = round((float) ($line['credit'] ?? 0), 2);

            if ($account_id === 0 || ($d_amt <= 0 && $c_amt <= 0)) {
                continue;
            }
            if ($d_amt > 0 && $c_amt > 0) {
                return sch_api_error('both_sides', __('السطر الواحد إما مدين أو دائن، لا الاثنين.', 'school-system'), 422);
            }
            if (!SCH_Accounts::get($account_id)) {
                return sch_api_error('bad_account', __('حساب غير موجود في أحد السطور.', 'school-system'), 422);
            }

            $clean[] = [
                'account_id'  => $account_id,
                'debit'       => $d_amt,
                'credit'      => $c_amt,
                'description' => sanitize_text_field((string) ($line['description'] ?? '')) ?: null,
            ];

            $debit  += $d_amt;
            $credit += $c_amt;
        }

        if (count($clean) < 2) {
            return sch_api_error('too_few_lines', __('القيد يحتاج سطرين على الأقل.', 'school-system'), 422);
        }
        if (round($debit, 2) !== round($credit, 2)) {
            return sch_api_error('unbalanced', sprintf(
                /* translators: 1: مجموع المدين 2: مجموع الدائن */
                __('القيد غير متوازن: المدين %1$s والدائن %2$s.', 'school-system'),
                number_format($debit, 2),
                number_format($credit, 2)
            ), 422);
        }

        // قد يُستدعى ضمن معاملة المال نفسها (الفاتورة/الدفعة وقيدها معًا) —
        // عندها لا نبدأ/نُنهي معاملةً خاصةً بنا بل نترك ذلك للمستدعي.
        if (!$in_tx) {
            $wpdb->query('START TRANSACTION');
        }

        // رقم القيد فريد؛ تحت التزامن قد يحسب قيدان الرقم نفسه (COUNT+1) فنجرّب
        // أرقامًا متتابعة حتى ينجح الإدراج، وإلا تُلصق السطور بقيدٍ خاطئ.
        $entry_id = 0;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $res = $wpdb->insert(sch_table('journal_entries'), [
                'entry_no'    => self::next_number($attempt),
                'entry_date'  => $date,
                'description' => $desc,
                'ref_type'    => sanitize_key((string) ($d['ref_type'] ?? '')) ?: null,
                'ref_id'      => absint($d['ref_id'] ?? 0) ?: null,
                'total'       => round($debit, 2),
                'status'      => $post ? 'posted' : 'draft',
                'created_by'  => get_current_user_id() ?: null,
                'posted_at'   => $post ? sch_now() : null,
                'created_at'  => sch_now(),
            ]);

            if ($res !== false) {
                $entry_id = (int) $wpdb->insert_id;
                break;
            }
        }

        if ($entry_id === 0) {
            if (!$in_tx) {
                $wpdb->query('ROLLBACK');
            }
            return sch_api_error('db_error', __('تعذّر إنشاء القيد — أعد المحاولة.', 'school-system'), 500);
        }

        foreach ($clean as $line) {
            $ins = $wpdb->insert(sch_table('journal_lines'), $line + ['entry_id' => $entry_id]);

            if ($ins === false) {
                $err = $wpdb->last_error;
                if (!$in_tx) {
                    $wpdb->query('ROLLBACK');
                }
                /* translators: %s: رسالة الخطأ من القاعدة */
                return sch_api_error('db_error', sprintf(__('تعذّر حفظ سطور القيد: %s', 'school-system'), $err), 500);
            }
        }

        if (!$in_tx) {
            $wpdb->query('COMMIT');
        }

        sch_audit('journal.created', 'journal', $entry_id, ['total' => $debit, 'posted' => $post]);

        return ['id' => $entry_id];
    }

    public static function post(int $entry_id): bool|WP_Error
    {
        global $wpdb;

        $entry = self::get($entry_id);
        if (!$entry) {
            return sch_api_error('not_found', __('القيد غير موجود.', 'school-system'), 404);
        }
        if ($entry->status === 'posted') {
            return true;
        }

        $wpdb->update(
            sch_table('journal_entries'),
            ['status' => 'posted', 'posted_at' => sch_now()],
            ['id' => $entry_id]
        );

        sch_audit('journal.posted', 'journal', $entry_id);
        return true;
    }

    /** عكس قيد مرحّل بقيد مضاد — القيود المرحّلة لا تُحذف أبدًا. */
    public static function reverse(int $entry_id, bool $in_tx = false): array|WP_Error
    {
        $entry = self::get($entry_id);
        if (!$entry) {
            return sch_api_error('not_found', __('القيد غير موجود.', 'school-system'), 404);
        }
        if ($entry->status !== 'posted') {
            return sch_api_error('not_posted', __('القيد غير مرحّل.', 'school-system'), 409);
        }

        $reversed = [];
        foreach (self::lines($entry_id) as $line) {
            $reversed[] = [
                'account_id'  => (int) $line->account_id,
                'debit'       => (float) $line->credit,
                'credit'      => (float) $line->debit,
                'description' => $line->description,
            ];
        }

        return self::create([
            'entry_date'  => current_time('Y-m-d'),
            'description' => sprintf(
                /* translators: %s: رقم القيد الأصلي */
                __('عكس القيد رقم %s', 'school-system'),
                $entry->entry_no
            ),
            'ref_type'    => 'reversal',
            'ref_id'      => $entry_id,
        ], $reversed, true, $in_tx);
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('journal_entries') . ' WHERE id = %d', $id)) ?: null;
    }

    public static function lines(int $entry_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT l.*, a.code, a.name FROM ' . sch_table('journal_lines') . ' l
             INNER JOIN ' . sch_table('accounts') . ' a ON a.id = l.account_id
             WHERE l.entry_id = %d ORDER BY l.debit DESC, l.id',
            $entry_id
        )) ?: [];
    }

    public static function recent(int $limit = 60): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT e.*, u.display_name FROM ' . sch_table('journal_entries') . ' e
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = e.created_by
             ORDER BY e.entry_date DESC, e.id DESC LIMIT %d',
            $limit
        )) ?: [];
    }

    /** دفتر أستاذ حساب — الحركات مع الرصيد المتحرك. */
    public static function ledger(int $account_id, ?string $from = null, ?string $to = null): array
    {
        global $wpdb;

        $account = SCH_Accounts::get($account_id);
        if (!$account) {
            return [];
        }

        $from ??= gmdate('Y-m-d', time() - 365 * DAY_IN_SECONDS);
        $to   ??= current_time('Y-m-d');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.entry_no, e.entry_date, e.description, l.debit, l.credit
             FROM " . sch_table('journal_lines') . " l
             INNER JOIN " . sch_table('journal_entries') . " e ON e.id = l.entry_id
             WHERE l.account_id = %d AND e.status = 'posted'
               AND e.entry_date BETWEEN %s AND %s
             ORDER BY e.entry_date, e.id",
            $account_id,
            $from,
            $to
        )) ?: [];

        $debit_nature = SCH_Accounts::is_debit_nature((string) $account->type);
        $running      = 0.0;

        foreach ($rows as $row) {
            $delta = $debit_nature
                ? (float) $row->debit - (float) $row->credit
                : (float) $row->credit - (float) $row->debit;

            $running     += $delta;
            $row->balance = round($running, 2);
        }

        return $rows;
    }

    /** قائمة الدخل المبسّطة: الإيرادات ناقص المصروفات. */
    public static function income_statement(string $from, string $to): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.type, a.code, a.name,
                    COALESCE(SUM(l.credit), 0) - COALESCE(SUM(l.debit), 0) AS net
             FROM " . sch_table('accounts') . " a
             INNER JOIN " . sch_table('journal_lines') . " l ON l.account_id = a.id
             INNER JOIN " . sch_table('journal_entries') . " e ON e.id = l.entry_id
             WHERE e.status = 'posted' AND e.entry_date BETWEEN %s AND %s
               AND a.type IN ('revenue','expense')
             GROUP BY a.id, a.type, a.code, a.name
             ORDER BY a.code",
            $from,
            $to
        )) ?: [];

        $revenue  = [];
        $expense  = [];
        $rev_sum  = 0.0;
        $exp_sum  = 0.0;

        foreach ($rows as $r) {
            $net = (float) $r->net;
            if ($r->type === 'revenue') {
                $r->amount = round($net, 2);
                $revenue[] = $r;
                $rev_sum  += $net;
            } else {
                $r->amount = round(-$net, 2); // المصروف بطبيعته مدين
                $expense[] = $r;
                $exp_sum  += -$net;
            }
        }

        return [
            'revenue'  => $revenue,
            'expense'  => $expense,
            'rev_sum'  => round($rev_sum, 2),
            'exp_sum'  => round($exp_sum, 2),
            'net'      => round($rev_sum - $exp_sum, 2),
        ];
    }

    private static function next_number(int $offset = 0): string
    {
        global $wpdb;

        $year = current_time('Y');
        $seq  = 1 + $offset + (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('journal_entries') . ' WHERE entry_no LIKE %s',
            $wpdb->esc_like('JV-' . $year . '-') . '%'
        ));

        return sprintf('JV-%s-%04d', $year, $seq);
    }
}

/**
 * الترحيل التلقائي من العمليات التشغيلية إلى القيود.
 * المحاسب لا يكتب قيدًا يدويًا لكل فاتورة ودفعة.
 */
final class SCH_AutoPost
{
    public static function invoice_issued(int $invoice_id, float $total, bool $in_tx = false): array|WP_Error|null
    {
        if (!self::enabled()) {
            return null;
        }

        $ar  = SCH_Accounts::id_of('students_ar');
        $rev = SCH_Accounts::id_of('tuition_rev');

        if ($ar === 0 || $rev === 0 || $total <= 0) {
            return null;
        }

        return SCH_Journal::create([
            'description' => sprintf(
                /* translators: %d: رقم الفاتورة */
                __('إثبات رسوم — فاتورة رقم %d', 'school-system'),
                $invoice_id
            ),
            'ref_type' => 'invoice',
            'ref_id'   => $invoice_id,
        ], [
            ['account_id' => $ar,  'debit' => $total, 'credit' => 0],
            ['account_id' => $rev, 'debit' => 0,      'credit' => $total],
        ], true, $in_tx);
    }

    public static function payment_received(int $payment_id, int $invoice_id, float $amount, string $method, bool $in_tx = false): array|WP_Error|null
    {
        if (!self::enabled()) {
            return null;
        }

        $cash = SCH_Accounts::id_of($method === 'cash' ? 'cash' : 'bank');
        $ar   = SCH_Accounts::id_of('students_ar');

        if ($cash === 0 || $ar === 0 || $amount <= 0) {
            return null;
        }

        return SCH_Journal::create([
            'description' => sprintf(
                /* translators: %d: رقم الفاتورة */
                __('تحصيل دفعة — فاتورة رقم %d', 'school-system'),
                $invoice_id
            ),
            'ref_type' => 'payment',
            'ref_id'   => $payment_id,
        ], [
            ['account_id' => $cash, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $ar,   'debit' => 0,       'credit' => $amount],
        ], true, $in_tx);
    }

    private static function enabled(): bool
    {
        return (bool) sch_settings('auto_post', true);
    }
}
