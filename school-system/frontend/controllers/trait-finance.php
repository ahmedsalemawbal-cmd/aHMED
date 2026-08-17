<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الرسوم والفواتير والمحاسبة والرواتب والعقود
 *
 * سمة (trait) تُدمَج في `SCH_Dashboard` — فالمعالجات تبقى كما كانت تمامًا:
 * `private static`، تنادَى بـ`self::`، وتُسجَّل في `actions()` المركزي حيث
 * يُفرض النونس ثم الصلاحية قبل أي منها. **نُقلت ولم تُغيَّر.**
 *
 * لإضافة فعل: دالة `do_*` هنا + سطر في `SCH_Dashboard::actions()`. لا مسار ثانٍ.
 */
trait SCH_Ctl_Finance
{
    private static function do_add_fee_plan(array $d, int $id): array|WP_Error
    {
        return SCH_Finance::create_plan($d);
    }

    private static function do_issue_invoice(array $d, int $id): array|WP_Error
    {
        return SCH_Finance::issue_invoice($d);
    }

    private static function do_record_payment(array $d, int $id): array|WP_Error
    {
        return SCH_Finance::record_payment($d);
    }

    private static function do_void_invoice(array $d, int $id): bool|WP_Error
    {
        return SCH_Finance::void_invoice(absint($d['invoice_id'] ?? 0), (string) ($d['reason'] ?? ''));
    }

    private static function do_refund_payment(array $d, int $id): bool|WP_Error
    {
        return SCH_Finance::refund_payment(absint($d['payment_id'] ?? 0), (string) ($d['reason'] ?? ''));
    }

    private static function do_add_account(array $d, int $id): array|WP_Error
    {
        return SCH_Accounts::create($d);
    }

    private static function do_add_journal(array $d, int $id): array|WP_Error
    {
        $lines = [];

        foreach ((array) ($d['line_account'] ?? []) as $i => $account_id) {
            $lines[] = [
                'account_id'  => absint($account_id),
                'debit'       => (float) (($d['line_debit'][$i] ?? 0) ?: 0),
                'credit'      => (float) (($d['line_credit'][$i] ?? 0) ?: 0),
                'description' => (string) ($d['line_note'][$i] ?? ''),
            ];
        }

        return SCH_Journal::create($d, $lines, empty($d['as_draft']));
    }

    private static function do_post_journal(array $d, int $id): bool|WP_Error
    {
        return SCH_Journal::post(absint($d['entry_id'] ?? 0));
    }

    private static function do_reverse_journal(array $d, int $id): array|WP_Error
    {
        return SCH_Journal::reverse(absint($d['entry_id'] ?? 0));
    }

    private static function do_add_contract(array $d, int $id): array|WP_Error
    {
        return SCH_HR::create_contract($d);
    }

    private static function do_request_leave(array $d, int $id): array|WP_Error
    {
        return SCH_HR::request_leave($d);
    }

    private static function do_decide_leave(array $d, int $id): bool|WP_Error
    {
        return SCH_HR::decide_leave(absint($d['leave_id'] ?? 0), (string) ($d['decision'] ?? ''));
    }

    private static function do_run_payroll(array $d, int $id): array|WP_Error
    {
        return SCH_Payroll::generate((int) ($d['period_year'] ?? 0), (int) ($d['period_month'] ?? 0));
    }

    private static function do_post_payroll(array $d, int $id): bool|WP_Error
    {
        return SCH_Payroll::post(absint($d['run_id'] ?? 0));
    }
}
