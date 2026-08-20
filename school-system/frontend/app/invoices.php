<?php
/** الرسوم — رقم واحد كبير، ثم متى الدفعة القادمة. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id       = (int) ($sch_data['id'] ?? 0);
$invoices = SCH_Finance::invoices_of_student($id);
$today    = current_time('Y-m-d');
?>

<h1 class="p-h1"><?php esc_html_e('الرسوم', 'school-system'); ?></h1>

<?php if ($invoices === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('wallet', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا توجد رسوم', 'school-system'); ?></b>
        <p><?php esc_html_e('لم تُصدَر فاتورة لهذه السنة بعد.', 'school-system'); ?></p>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php
/**
 * جمعٌ واحد لكل فواتير السنة.
 *
 * الشاشة تبدأ برقم واحد لا برقم لكل فاتورة: الأب يسأل «كم عليّ؟» مرة
 * واحدة، وتفصيل الفواتير يأتي بعده في قائمته.
 */
$sum_total = 0.0;
$sum_paid  = 0.0;
$steps     = [];
$bills     = [];

foreach ($invoices as $inv) {
    $rows  = SCH_Finance::installments((int) $inv->id);
    $total = (float) $inv->total;
    $paid  = (float) $inv->paid;

    $sum_total += $total;
    $sum_paid  += $paid;

    // أوّل قسط لم يكتمل سداده هو «القادم» — والطبقة ترجعها مرتّبة بـseq
    $next = null;
    foreach ($rows as $r) {
        if ((float) $r->paid < (float) $r->amount) { $next = $r; break; }
    }

    foreach ($rows as $r) {
        $r_done = (float) $r->paid >= (float) $r->amount;
        $steps[] = [
            'due'    => (string) $r->due_date,
            'amount' => (float) $r->amount,
            'done'   => $r_done,
            'late'   => !$r_done && (string) $r->due_date !== '' && (string) $r->due_date < $today,
        ];
    }

    $bills[] = [
        'inv'  => $inv,
        'left' => max(0, round($total - $paid, 2)),
        'late' => $next !== null && (string) $next->due_date !== '' && (string) $next->due_date < $today,
    ];
}

$sum_left = max(0, round($sum_total - $sum_paid, 2));
$pct      = $sum_total > 0 ? min(100, (int) round($sum_paid / $sum_total * 100)) : 0;

// أقساط الفواتير كلها على سكّة واحدة مرتّبة بالاستحقاق: الأب يقرأ زمنًا لا فواتير
usort($steps, static fn (array $a, array $b): int => strcmp($a['due'], $b['due']));

$due_i = null;
foreach ($steps as $i => $s) {
    if (!$s['done']) { $due_i = $i; break; }
}

$due  = $due_i !== null ? $steps[$due_i] : null;
$late = $due !== null && $due['late'];
$days = $due !== null && $due['due'] !== ''
    ? (int) floor((strtotime($due['due']) - strtotime($today)) / 86400)
    : 0;
?>

<section class="p-fee">
    <span class="p-fee__k"><?php esc_html_e('المتبقّي على العام الدراسي', 'school-system'); ?></span>

    <b class="p-fee__v">
        <span class="p-amt">
            <span class="p-nm"><?php echo esc_html(number_format_i18n($sum_left, 0)); ?></span>
            <span class="p-cur"><?php esc_html_e('ر.س', 'school-system'); ?></span>
        </span>
    </b>

    <?php /* سطر واحد يقول متى — والنقطة تحمل نبرته قبل قراءة الكلمات */ ?>
    <span class="p-fee__when<?php echo esc_attr($due === null ? ' is-clear' : ($late ? ' is-late' : '')); ?>">
        <i class="p-fee__dot" aria-hidden="true"></i>
        <?php if ($due === null) : ?>
            <?php esc_html_e('اكتمل السداد — شكرًا لك', 'school-system'); ?>
        <?php else : ?>
            <?php echo esc_html($late
                ? sprintf(
                    /* translators: %s: عدد الأيام */
                    _n('تأخّر القسط يومًا واحدًا', 'تأخّر القسط %s أيام', abs($days), 'school-system'),
                    number_format_i18n(abs($days))
                )
                : sprintf(
                    /* translators: %s: عدد الأيام */
                    _n('القسط القادم غدًا', 'القسط القادم بعد %s يومًا', abs($days), 'school-system'),
                    number_format_i18n(abs($days))
                )); ?>
        <?php endif; ?>
    </span>

    <div class="p-fee__bar">
        <div class="p-fee__of">
            <span>
                <?php esc_html_e('سُدِّد', 'school-system'); ?>
                <b class="p-nm"><?php echo esc_html(number_format_i18n($sum_paid, 0)); ?></b>
                <?php esc_html_e('من', 'school-system'); ?>
                <b class="p-nm"><?php echo esc_html(number_format_i18n($sum_total, 0)); ?></b>
                <?php esc_html_e('ر.س', 'school-system'); ?>
            </span>
            <span class="p-fee__pct">
                <span class="p-nm"><?php echo esc_html(number_format_i18n($pct)); ?></span>٪
            </span>
        </div>

        <span class="p-fee__track">
            <i class="p-fee__fill" style="width:<?php echo esc_attr((string) $pct); ?>%"></i>
        </span>
    </div>
</section>

<?php if (count($steps) > 1) : ?>
    <section class="p-plan">
        <h2 class="p-plan__h"><?php esc_html_e('الأقساط', 'school-system'); ?></h2>

        <!-- سكّة النقاط: الحالة تُقرأ من امتلاء النقطة قبل الكلمة تحتها -->
        <div class="p-plan__grid">
            <span class="p-plan__line" aria-hidden="true"></span>

            <?php foreach ($steps as $i => $s) :
                $cls = $s['done'] ? 'is-done' : ($s['late'] ? 'is-late' : ($i === $due_i ? 'is-next' : '')); ?>
                <div class="p-plan__i <?php echo esc_attr($cls); ?>">
                    <span class="p-plan__dot">
                        <?php if ($s['done']) : ?>
                            <?php echo sch_icon('check', 12); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        <?php elseif ($s['late']) : ?>
                            <b aria-hidden="true">!</b>
                        <?php endif; ?>
                    </span>

                    <span class="p-plan__d"><?php echo esc_html($s['due'] !== ''
                        ? wp_date('j F', strtotime($s['due']))
                        : __('بلا تاريخ', 'school-system')); ?></span>

                    <b class="p-plan__a p-nm"><?php echo esc_html(number_format_i18n($s['amount'], 0)); ?></b>

                    <span class="p-plan__s"><?php echo esc_html($s['done']
                        ? __('مدفوع', 'school-system')
                        : ($s['late'] ? __('متأخر', 'school-system') : __('قادم', 'school-system'))); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<div class="p-sect">
    <h2 class="p-sect__h"><?php esc_html_e('الفواتير', 'school-system'); ?></h2>
    <?php /* عدد لا رابط: «عرض الكل» تحتاج شاشة ثانية، والقائمة كلها هنا أصلًا */ ?>
    <span class="p-sect__a"><?php echo esc_html(sprintf(
        /* translators: %s: عدد الفواتير */
        _n('%s فاتورة', '%s فواتير', count($bills), 'school-system'),
        number_format_i18n(count($bills))
    )); ?></span>
</div>

<div class="p-inv">
    <?php foreach ($bills as $b) :
        $inv  = $b['inv'];
        $when = (string) ($inv->due_date ?: $inv->created_at);

        if ((string) $inv->status === 'paid' || $b['left'] <= 0) {
            $tone = 'ok';
            $word = __('مسدَّد', 'school-system');
        } elseif ($b['late']) {
            $tone = 'bad';
            $word = __('متأخر', 'school-system');
        } elseif ((string) $inv->status === 'partial') {
            $tone = 'warn';
            $word = __('قيد السداد', 'school-system');
        } else {
            $tone = 'mute';
            $word = __('مستحق', 'school-system');
        } ?>

        <article class="p-inv__i">
            <div class="p-inv__t">
                <b class="p-inv__n"><?php echo esc_html($inv->plan_name ?: sprintf(
                    /* translators: %s: رقم الفاتورة */
                    __('فاتورة رقم %s', 'school-system'),
                    (string) $inv->id
                )); ?></b>

                <span class="p-inv__d"><?php echo esc_html($when !== ''
                    ? wp_date('j F Y', strtotime($when))
                    : ''); ?></span>

                <?php if ((float) $inv->discount > 0) : ?>
                    <!-- الخصم يُذكر صراحةً: الأب يرى في تطبيقه أنه استفاد -->
                    <span class="p-inv__off">
                        <?php echo sch_icon('check', 11); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        <?php echo esc_html(sprintf(
                            /* translators: 1: النسبة 2: المبلغ */
                            __('خصم %1$s٪ · وفّرت %2$s', 'school-system'),
                            number_format_i18n((float) $inv->discount_pct, 1),
                            number_format_i18n((float) $inv->discount, 0)
                        )); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="p-inv__r">
                <b class="p-inv__a">
                    <span class="p-amt">
                        <span class="p-nm"><?php echo esc_html(number_format_i18n((float) $inv->total, 0)); ?></span>
                        <span class="p-cur"><?php esc_html_e('ر.س', 'school-system'); ?></span>
                    </span>
                </b>
                <span class="p-tag p-tag--<?php echo esc_attr($tone); ?>"><?php echo esc_html($word); ?></span>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($sum_left > 0) : ?>
    <!-- مكان الزر محجوز: يوم تُربط البوابة يعمل بلا تغيير في الشاشة -->
    <div class="p-paybar">
        <button type="button" class="p-btn p-paybar__go" disabled
                title="<?php esc_attr_e('الدفع من التطبيق — قريبًا', 'school-system'); ?>">
            <span><?php echo esc_html(sprintf(
                /* translators: %s: المبلغ المتبقي */
                __('سدّد %s ر.س', 'school-system'),
                number_format_i18n($sum_left, 0)
            )); ?></span>
            <span class="p-paybar__soon"><?php esc_html_e('قريبًا', 'school-system'); ?></span>
        </button>
    </div>
<?php endif; ?>
