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

<?php foreach ($invoices as $inv) :
    $rows  = SCH_Finance::installments((int) $inv->id);
    $total = (float) $inv->total;
    $paid  = (float) $inv->paid;
    $left  = max(0, round($total - $paid, 2));
    $pct   = $total > 0 ? min(100, (int) round($paid / $total * 100)) : 0;

    $next = null;
    foreach ($rows as $r) {
        if ((float) $r->paid < (float) $r->amount) { $next = $r; break; }
    }

    $late = $next !== null && (string) $next->due_date < $today;
    $days = $next ? (int) floor((strtotime((string) $next->due_date) - strtotime($today)) / 86400) : 0;
    ?>

    <section class="p-bill">

        <div class="p-bill__hero">
            <span class="p-bill__chip">
                <?php echo esc_html(count($rows) === 1
                    ? __('دفعة واحدة', 'school-system')
                    : sprintf(
                        /* translators: %s: عدد الدفعات */
                        __('%s دفعات', 'school-system'),
                        number_format_i18n(count($rows))
                    )); ?>
            </span>

            <span class="p-bill__k"><?php esc_html_e('المتبقي عليك', 'school-system'); ?></span>
            <b class="p-bill__v">
                <span class="p-amt">
                    <span class="p-nm"><?php echo esc_html(number_format($left, 2)); ?></span>
                    <span class="p-cur"><?php esc_html_e('ر.س', 'school-system'); ?></span>
                </span>
            </b>

            <!-- الشريط مقسّم بعدد الدفعات: الحالة تُقرأ بلمحة بلا رقم -->
            <span class="p-bill__bar">
                <?php foreach ($rows as $r) : ?>
                    <i class="<?php echo esc_attr((float) $r->paid >= (float) $r->amount ? 'is-on' : ''); ?>"></i>
                <?php endforeach; ?>
            </span>

            <span class="p-bill__of">
                <span class="p-bill__pct"><?php echo esc_html(number_format_i18n($pct)); ?>٪</span>
                <span>
                    <?php esc_html_e('دفعت', 'school-system'); ?>
                    <b class="p-nm"><?php echo esc_html(number_format($paid, 0)); ?></b>
                    <?php esc_html_e('من', 'school-system'); ?>
                    <b class="p-nm"><?php echo esc_html(number_format($total, 0)); ?></b>
                </span>
            </span>

            <?php if ((float) $inv->discount > 0) : ?>
                <span class="p-bill__won">
                    <?php echo sch_icon('check', 13); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php echo esc_html(sprintf(
                        /* translators: 1: النسبة 2: المبلغ */
                        __('خصم %1$s٪ · وفّرت %2$s', 'school-system'),
                        number_format_i18n((float) $inv->discount_pct, 1),
                        number_format((float) $inv->discount, 0)
                    )); ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (count($rows) > 1) : ?>
            <h2 class="p-h2"><?php esc_html_e('الدفعات', 'school-system'); ?></h2>

            <!-- دوائر متدرجة: تُقرأ الحالة من الامتلاء قبل النص -->
            <div class="p-pays">
                <?php foreach ($rows as $i => $r) :
                    $done = (float) $r->paid >= (float) $r->amount;
                    $now  = $next !== null && (int) $r->id === (int) $next->id;
                    $cls  = $done ? 'is-done' : ($now ? ($late ? 'is-late' : 'is-next') : '');
                    $fill = (int) round((($i + 1) / count($rows)) * 100); ?>
                    <div class="p-pay <?php echo esc_attr($cls); ?>">
                        <span class="p-pay__ring" style="--f:<?php echo esc_attr((string) $fill); ?>%">
                            <?php if ($done) : ?>
                                <?php echo sch_icon('check', 13); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <?php else : ?>
                                <b><?php echo esc_html(number_format_i18n($i + 1)); ?></b>
                            <?php endif; ?>
                        </span>
                        <b class="p-pay__a p-nm"><?php echo esc_html(number_format((float) $r->amount, 0)); ?></b>
                        <span class="p-pay__d"><?php echo esc_html(wp_date('j M', strtotime((string) $r->due_date))); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="p-due<?php echo esc_attr($late ? ' is-late' : ($next ? '' : ' is-clear')); ?>">
            <div class="p-due__b">
                <div class="p-due__row">
                    <span class="p-due__i">
                        <?php echo sch_icon($next ? ($late ? 'flag' : 'clock') : 'check', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </span>

                    <span class="p-due__t">
                        <span><?php echo esc_html($next
                            ? ($late ? __('دفعة متأخرة', 'school-system') : __('الدفعة القادمة', 'school-system'))
                            : __('اكتمل السداد', 'school-system')); ?></span>

                        <?php /* التأخير جملةً لا رقمًا معزولًا في عمود ثالث */ ?>
                        <?php if ($next) : ?>
                            <em><?php echo esc_html($late
                                ? sprintf(
                                    /* translators: %s: عدد الأيام */
                                    _n('تأخّرت يومًا واحدًا', 'تأخّرت %s أيام', abs($days), 'school-system'),
                                    number_format_i18n(abs($days))
                                )
                                : sprintf(
                                    /* translators: %s: عدد الأيام */
                                    _n('تستحق غدًا', 'تستحق بعد %s أيام', abs($days), 'school-system'),
                                    number_format_i18n(abs($days))
                                )); ?></em>
                        <?php else : ?>
                            <em><?php esc_html_e('شكرًا لك — لا مستحقات', 'school-system'); ?></em>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($next) : ?>
                    <b class="p-due__v">
                        <span class="p-amt">
                            <span class="p-nm"><?php echo esc_html(number_format((float) $next->amount - (float) $next->paid, 2)); ?></span>
                            <span class="p-cur"><?php esc_html_e('ر.س', 'school-system'); ?></span>
                        </span>
                    </b>
                <?php endif; ?>
            </div>

            <?php if ($next) : ?>
                <!-- مكان الزر محجوز: يوم تُربط البوابة يعمل بلا تغيير في الشاشة -->
                <button type="button" class="p-btn p-due__go" disabled>
                    <?php esc_html_e('الدفع من التطبيق — قريبًا', 'school-system'); ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($next) : ?>
            <div class="p-ways">
                <?php foreach (['مدى', 'Apple Pay', 'تابي', 'تمارا', 'STC'] as $way) : ?>
                    <span><?php echo esc_html($way); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
