<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * المالية: خطط الرسوم، الفواتير، الأقساط، الدفعات.
 * قاعدة: المبالغ لا تُحسب في الواجهة إطلاقًا — كلها هنا.
 */
final class SCH_Finance
{
    public const METHODS = [
        'cash'     => 'نقدًا',
        'transfer' => 'تحويل بنكي',
        'pos'      => 'شبكة',
        'online'   => 'دفع إلكتروني',
    ];

    // ---------- خطط الرسوم ----------

    public static function create_plan(array $d): array|WP_Error
    {
        global $wpdb;

        $name    = sanitize_text_field((string) ($d['name'] ?? ''));
        $amount  = round((float) ($d['amount'] ?? 0), 2);
        $year_id = SCH_Years::current_id();

        if ($year_id <= 0) {
            return sch_api_error('no_year', __('لا توجد سنة دراسية نشطة.', 'school-system'), 422);
        }
        if ($name === '' || $amount <= 0) {
            return sch_api_error('invalid_plan', __('اسم الخطة ومبلغ أكبر من صفر مطلوبان.', 'school-system'), 422);
        }

        $wpdb->insert(sch_table('fee_plans'), [
            'year_id'      => $year_id,
            'name'         => $name,
            'stage'        => array_key_exists($d['stage'] ?? '', SCH_Classes::STAGES) ? $d['stage'] : null,
            'amount'       => $amount,
            'installments' => max(1, min(12, (int) ($d['installments'] ?? 1))),
            'created_at'   => sch_now(),
        ]);

        sch_audit('fee_plan.created', 'fee_plan', (int) $wpdb->insert_id);
        return ['id' => (int) $wpdb->insert_id];
    }

    public static function plans(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('fee_plans') . ' WHERE year_id = %d ORDER BY name',
            SCH_Years::current_id()
        )) ?: [];
    }

    public static function get_plan(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('fee_plans') . ' WHERE id = %d', $id)) ?: null;
    }

    // ---------- الفواتير ----------

    /** إصدار فاتورة لطالب من خطة، مع توليد الأقساط. */
    public static function issue_invoice(array $d): array|WP_Error
    {
        global $wpdb;

        $student_id = absint($d['student_id'] ?? 0);
        $plan       = self::get_plan(absint($d['plan_id'] ?? 0));
        $year_id    = SCH_Years::current_id();

        if (!SCH_Students::get($student_id)) {
            return sch_api_error('student_not_found', __('الطالب غير موجود.', 'school-system'), 404);
        }
        if (!$plan) {
            return sch_api_error('plan_not_found', __('خطة الرسوم غير موجودة.', 'school-system'), 404);
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . sch_table('invoices') . "
             WHERE student_id = %d AND year_id = %d AND plan_id = %d AND status <> 'void'",
            $student_id,
            $year_id,
            (int) $plan->id
        ));

        if ($existing) {
            return sch_api_error('duplicate_invoice', __('للطالب فاتورة قائمة بنفس الخطة لهذه السنة.', 'school-system'), 409);
        }

        // ═══ الخصم نسبة لا مبلغًا، ويُطبَّق قبل التقسيم ═══
        // فالأقساط تظهر على المبلغ النهائي، والأب يرى أنه استفاد.
        $gross = round((float) $plan->amount, 2);
        $pct   = max(0.0, min(100.0, round((float) ($d['discount_pct'] ?? 0), 2)));
        $cut   = round($gross * $pct / 100, 2);
        $total = round($gross - $cut, 2);

        // ═══ نمط الدفع ═══
        // full  — دفعة واحدة كاملة (الأصل)
        // bnpl  — تابي أو تمارا: المدرسة تقبض كاملًا والشركة تُحصّل من الأب
        // school— تقسيط على المدرسة: **امتياز يُمنَح** لمن يستحق، والمخاطرة على المدرسة
        $mode = in_array($d['pay_mode'] ?? 'full', ['full', 'school', 'bnpl'], true)
            ? (string) $d['pay_mode']
            : 'full';

        $reason = sanitize_text_field((string) ($d['grant_reason'] ?? ''));

        if ($mode === 'school') {
            if (!current_user_can('sch_grant_installments')) {
                return sch_api_error(
                    'no_grant',
                    __('تقسيط المدرسة يمنحه المدير أو الوكيل — لا يُفتح من المحاسبة.', 'school-system'),
                    403
                );
            }

            if (trim($reason) === '') {
                // السبب يذكّرك بعد سنة لماذا وافقت، ويمنع أن يصير الاستثناء قاعدة.
                return sch_api_error('no_reason', __('اكتب سبب منح التقسيط.', 'school-system'), 422);
            }
        }

        // تقسيط المدرسة وحده يُقسَّم؛ والدفعة الكاملة وتابي وتمارا قسط واحد.
        $count = $mode === 'school'
            ? max(2, min(4, (int) ($d['installments'] ?? 2)))
            : 1;

        $wpdb->query('START TRANSACTION');

        // فحص كل إدخال والتراجع عند أوّل فشل: بلا هذا كان COMMIT يُثبّت فاتورة
        // ناقصة (أقساط لا تجمع للإجمالي، أو فاتورة بلا أقساط) والمعاملة بلا أثر.
        $ok = $wpdb->insert(sch_table('invoices'), [
            'student_id'   => $student_id,
            'year_id'      => $year_id,
            'plan_id'      => (int) $plan->id,
            'gross'        => $gross,
            'discount_pct' => $pct,
            'discount'     => $cut,
            'total'        => $total,
            'paid'         => 0,
            'status'       => 'open',
            'pay_mode'     => $mode,
            'grant_reason' => $mode === 'school' ? $reason : null,
            'granted_by'   => $mode === 'school' ? (get_current_user_id() ?: null) : null,
            'due_date'     => sch_sanitize_date($d['due_date'] ?? null),
            'created_by'   => get_current_user_id() ?: null,
            'created_at'   => sch_now(),
            'updated_at'   => sch_now(),
        ]);

        if ($ok === false) {
            $wpdb->query('ROLLBACK');
            return sch_api_error('db_error', __('تعذّر إصدار الفاتورة.', 'school-system'), 500);
        }

        $invoice_id = (int) $wpdb->insert_id;
        $first      = sch_sanitize_date($d['due_date'] ?? null) ?? current_time('Y-m-d');
        $each       = round($total / $count, 2);

        // تواريخ مخصصة من الشاشة تعلو على التوليد التلقائي.
        $custom = array_values(array_filter(array_map(
            static fn ($x): ?string => sch_sanitize_date($x),
            (array) ($d['due_dates'] ?? [])
        )));

        for ($i = 1; $i <= $count; $i++) {
            // **الكسر يُضاف للدفعة الأولى** لا للأخيرة: يدفع الأكثر أولًا فيقلّ المتبقي.
            $amount = ($i === 1) ? round($total - $each * ($count - 1), 2) : $each;

            // الأولى عند التسجيل، ثم كل شهرين — والسنة تسعة أشهر فأربع دفعات تنتهي في الثامن.
            $due = $custom[$i - 1] ?? gmdate('Y-m-d', strtotime($first . ' +' . (($i - 1) * 2) . ' month'));

            $ins = $wpdb->insert(sch_table('installments'), [
                'invoice_id' => $invoice_id,
                'seq'        => $i,
                'amount'     => $amount,
                'due_date'   => $due,
                'paid'       => 0,
                'status'     => 'open',
                'created_at' => sch_now(),
            ]);

            if ($ins === false) {
                $wpdb->query('ROLLBACK');
                return sch_api_error('db_error', __('تعذّر إنشاء الأقساط.', 'school-system'), 500);
            }
        }

        $wpdb->query('COMMIT');
        sch_audit('invoice.issued', 'invoice', $invoice_id, ['student' => $student_id, 'total' => $total]);
        SCH_AutoPost::invoice_issued($invoice_id, $total);

        return ['id' => $invoice_id];
    }

    public static function get_invoice(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT i.*, s.full_name, p.name AS plan_name
             FROM ' . sch_table('invoices') . ' i
             INNER JOIN ' . sch_table('students') . ' s ON s.id = i.student_id
             LEFT JOIN ' . sch_table('fee_plans') . ' p ON p.id = i.plan_id
             WHERE i.id = %d',
            $id
        )) ?: null;
    }

    /** @return array{items:array<int,object>,total:int} */
    public static function invoices(array $args = []): array
    {
        global $wpdb;

        $where  = ['i.year_id = %d'];
        $params = [SCH_Years::current_id()];

        if (!empty($args['status'])) {
            $where[]  = 'i.status = %s';
            $params[] = (string) $args['status'];
        }
        if (!empty($args['search'])) {
            $where[]  = 's.full_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like((string) $args['search']) . '%';
        }
        if (!empty($args['student_id'])) {
            $where[]  = 'i.student_id = %d';
            $params[] = (int) $args['student_id'];
        }

        $clause   = implode(' AND ', $where);
        $per_page = min(200, max(1, (int) ($args['per_page'] ?? 50)));
        $offset   = (max(1, (int) ($args['page'] ?? 1)) - 1) * $per_page;

        $inv = sch_table('invoices');
        $stu = sch_table('students');

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$inv} i INNER JOIN {$stu} s ON s.id = i.student_id WHERE {$clause}",
            ...$params
        ));

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, s.full_name FROM {$inv} i
             INNER JOIN {$stu} s ON s.id = i.student_id
             WHERE {$clause}
             ORDER BY i.due_date IS NULL, i.due_date, i.id DESC
             LIMIT %d OFFSET %d",
            ...[...$params, $per_page, $offset]
        ));

        return ['items' => $items ?: [], 'total' => $total];
    }

    /** فواتير طالب في السنة الحالية. */
    public static function invoices_of_student(int $student_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, p.name AS plan_name
             FROM " . sch_table('invoices') . " i
             LEFT JOIN " . sch_table('fee_plans') . " p ON p.id = i.plan_id
             WHERE i.student_id = %d AND i.year_id = %d AND i.status <> 'void'
             ORDER BY i.id DESC",
            $student_id,
            SCH_Years::current_id()
        )) ?: [];
    }

    public static function installments(int $invoice_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('installments') . ' WHERE invoice_id = %d ORDER BY seq',
            $invoice_id
        )) ?: [];
    }

    // ---------- الدفعات ----------

    /** تسجيل دفعة وتوزيعها على الأقساط بالترتيب. */
    public static function record_payment(array $d): array|WP_Error
    {
        global $wpdb;

        $invoice = self::get_invoice(absint($d['invoice_id'] ?? 0));
        $amount  = round((float) ($d['amount'] ?? 0), 2);

        if (!$invoice) {
            return sch_api_error('not_found', __('الفاتورة غير موجودة.', 'school-system'), 404);
        }
        if ($invoice->status === 'void') {
            return sch_api_error('void_invoice', __('الفاتورة ملغاة.', 'school-system'), 409);
        }
        if ($amount <= 0) {
            return sch_api_error('bad_amount', __('المبلغ يجب أن يكون أكبر من صفر.', 'school-system'), 422);
        }

        $remaining = round((float) $invoice->total - (float) $invoice->paid, 2);
        if ($amount > $remaining) {
            return sch_api_error('overpay', sprintf(
                /* translators: %s: المبلغ المتبقي */
                __('المبلغ يتجاوز المتبقي (%s ر.س).', 'school-system'),
                number_format($remaining, 2)
            ), 422);
        }

        $method = array_key_exists($d['method'] ?? '', self::METHODS) ? $d['method'] : 'cash';

        $wpdb->query('START TRANSACTION');

        // قفل الفاتورة داخل المعاملة ثم إعادة الحساب من القيمة الطازجة —
        // دفعتان متزامنتان تتسلسلان على القفل فلا تُبنيان على قراءة قديمة
        // فتزدوج الدفعة أو يتجاوز المجموع الإجمالي.
        $locked = $wpdb->get_row($wpdb->prepare(
            "SELECT id, student_id, total, paid, status
             FROM " . sch_table('invoices') . " WHERE id = %d FOR UPDATE",
            (int) $invoice->id
        ));

        if (!$locked) {
            $wpdb->query('ROLLBACK');
            return sch_api_error('not_found', __('الفاتورة غير موجودة.', 'school-system'), 404);
        }
        if ($locked->status === 'void') {
            $wpdb->query('ROLLBACK');
            return sch_api_error('void_invoice', __('الفاتورة ملغاة.', 'school-system'), 409);
        }

        $remaining = round((float) $locked->total - (float) $locked->paid, 2);
        if ($amount > $remaining) {
            $wpdb->query('ROLLBACK');
            return sch_api_error('overpay', sprintf(
                /* translators: %s: المبلغ المتبقي */
                __('المبلغ يتجاوز المتبقي (%s ر.س).', 'school-system'),
                number_format($remaining, 2)
            ), 422);
        }

        $ok = $wpdb->insert(sch_table('payments'), [
            'invoice_id'  => (int) $locked->id,
            'amount'      => $amount,
            'method'      => $method,
            'reference'   => sanitize_text_field((string) ($d['reference'] ?? '')) ?: null,
            'received_by' => get_current_user_id() ?: null,
            'paid_at'     => sch_sanitize_datetime($d['paid_at'] ?? null) ?? sch_now(),
            'created_at'  => sch_now(),
        ]);

        if ($ok === false) {
            $err = $wpdb->last_error;
            $wpdb->query('ROLLBACK');
            /* translators: %s: رسالة الخطأ من القاعدة */
            return sch_api_error('db_error', sprintf(__('تعذّر تسجيل الدفعة: %s', 'school-system'), $err), 500);
        }

        $payment_id = (int) $wpdb->insert_id;

        // توزيع المبلغ على الأقساط المفتوحة بالتسلسل.
        $left = $amount;
        foreach (self::installments((int) $locked->id) as $inst) {
            if ($left <= 0) {
                break;
            }

            $due = round((float) $inst->amount - (float) $inst->paid, 2);
            if ($due <= 0) {
                continue;
            }

            $apply = min($left, $due);
            $paid  = round((float) $inst->paid + $apply, 2);

            $upd = $wpdb->update(sch_table('installments'), [
                'paid'   => $paid,
                'status' => $paid >= (float) $inst->amount ? 'paid' : 'partial',
            ], ['id' => (int) $inst->id]);

            if ($upd === false) {
                $err = $wpdb->last_error;
                $wpdb->query('ROLLBACK');
                /* translators: %s: رسالة الخطأ من القاعدة */
                return sch_api_error('db_error', sprintf(__('تعذّر توزيع الدفعة: %s', 'school-system'), $err), 500);
            }

            $left = round($left - $apply, 2);
        }

        $total_paid = round((float) $locked->paid + $amount, 2);

        $upd = $wpdb->update(sch_table('invoices'), [
            'paid'       => $total_paid,
            'status'     => $total_paid >= (float) $locked->total ? 'paid' : 'partial',
            'updated_at' => sch_now(),
        ], ['id' => (int) $locked->id]);

        if ($upd === false) {
            $err = $wpdb->last_error;
            $wpdb->query('ROLLBACK');
            /* translators: %s: رسالة الخطأ من القاعدة */
            return sch_api_error('db_error', sprintf(__('تعذّر تحديث الفاتورة: %s', 'school-system'), $err), 500);
        }

        $wpdb->query('COMMIT');

        sch_audit('payment.recorded', 'invoice', (int) $locked->id, ['amount' => $amount, 'method' => $method]);
        SCH_AutoPost::payment_received($payment_id, (int) $locked->id, $amount, (string) $method);
        self::notify_payment((int) $locked->student_id, $amount, round((float) $locked->total - $total_paid, 2));

        return ['id' => $payment_id];
    }

    public static function payments(int $invoice_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT p.*, u.display_name FROM ' . sch_table('payments') . ' p
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = p.received_by
             WHERE p.invoice_id = %d ORDER BY p.paid_at DESC',
            $invoice_id
        )) ?: [];
    }

    // ---------- التقارير ----------

    /** ملخص مالي للسنة النشطة. */
    public static function summary(): array
    {
        global $wpdb;

        $year = SCH_Years::current_id();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(total), 0) AS billed,
                    COALESCE(SUM(paid), 0)  AS collected,
                    COUNT(*)                AS invoices
             FROM " . sch_table('invoices') . "
             WHERE year_id = %d AND status <> 'void'",
            $year
        ));

        $overdue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(i.amount - i.paid), 0)
             FROM " . sch_table('installments') . " i
             INNER JOIN " . sch_table('invoices') . " v ON v.id = i.invoice_id
             WHERE v.year_id = %d AND v.status <> 'void'
               AND i.status <> 'paid' AND i.due_date < %s",
            $year,
            current_time('Y-m-d')
        ));

        $billed    = (float) ($row->billed ?? 0);
        $collected = (float) ($row->collected ?? 0);

        return [
            'billed'      => $billed,
            'collected'   => $collected,
            'outstanding' => round($billed - $collected, 2),
            'overdue'     => round($overdue, 2),
            'invoices'    => (int) ($row->invoices ?? 0),
            'rate'        => $billed > 0 ? round($collected / $billed * 100, 1) : 0.0,
        ];
    }

    /** الأقساط المتأخرة — أهم تقرير تشغيلي للمحاسب. */
    public static function overdue_list(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, v.student_id, s.full_name,
                    (i.amount - i.paid) AS due_amount
             FROM " . sch_table('installments') . " i
             INNER JOIN " . sch_table('invoices') . " v ON v.id = i.invoice_id
             INNER JOIN " . sch_table('students') . " s ON s.id = v.student_id
             WHERE v.year_id = %d AND v.status <> 'void'
               AND i.status <> 'paid' AND i.due_date < %s
             ORDER BY i.due_date
             LIMIT %d",
            SCH_Years::current_id(),
            current_time('Y-m-d'),
            $limit
        )) ?: [];
    }

    public static function of_student(int $student_id): array
    {
        return self::invoices(['student_id' => $student_id])['items'];
    }

    private static function notify_payment(int $student_id, float $amount, float $remaining): void
    {
        foreach (SCH_Guardians::of_student($student_id) as $g) {
            SCH_Comms::notify(
                (int) $g->user_id,
                __('استلام دفعة', 'school-system'),
                sprintf(
                    /* translators: 1: المبلغ المدفوع 2: المتبقي */
                    __('استُلم مبلغ %1$s ر.س. المتبقي %2$s ر.س.', 'school-system'),
                    number_format($amount, 2),
                    number_format($remaining, 2)
                ),
                'finance',
                $student_id
            );
        }
    }
}
