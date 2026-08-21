<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * جاهزية النظام.
 *
 * الشاشة تجيب سؤالًا واحدًا: **هل النظام يعمل كما ينبغي الآن؟** والجواب في
 * السطر الأوّل لا بعد قراءة قائمة — فمن يفتحها يفتحها قلقًا.
 *
 * وما كان متفرّقًا يجتمع هنا: الجداول الناقصة كانت في «بيانات المدرسة»،
 * وتوقّف الكرون في «لوحة العهدة»، ومفتاح الدفع في «الإشعارات» — **ومن يفتح
 * ثلاث شاشات ليطمئنّ لا يطمئنّ**.
 */

$sch_items = SCH_Ready::items();
$sch_sum   = SCH_Ready::summary();

/* الأسوأ أوّلًا: من يفتح الشاشة يريد ما يحتاج إصلاحًا لا ما هو سليم. */
$sch_rank = ['bad' => 0, 'warn' => 1, 'ok' => 2];
usort($sch_items, static fn (array $a, array $b): int
    => ($sch_rank[$a['level']] ?? 3) <=> ($sch_rank[$b['level']] ?? 3));
?>
<h1 class="sch-title"><?php echo sch_icon('check', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('جاهزية النظام', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('كل ما يجب أن يعمل ليعمل النظام — في شاشة واحدة.', 'school-system'); ?></p>

<?php /* ═════ الجواب في السطر الأوّل ═════ */ ?>
<div class="sch-notice sch-notice--<?php echo esc_attr($sch_sum['bad'] > 0 ? 'error' : 'ok'); ?>">
    <strong>
        <?php if ($sch_sum['bad'] > 0) : ?>
            <?php echo esc_html(sprintf(
                /* translators: %s: العدد */
                _n('بندٌ واحد يحتاج إصلاحًا', '%s بنود تحتاج إصلاحًا', $sch_sum['bad'], 'school-system'),
                number_format_i18n($sch_sum['bad'])
            )); ?>
        <?php elseif ($sch_sum['warn'] > 0) : ?>
            <?php echo esc_html(sprintf(
                /* translators: %s: العدد */
                _n('النظام يعمل — وبندٌ واحد يستحقّ الانتباه', 'النظام يعمل — و%s بنود تستحقّ الانتباه', $sch_sum['warn'], 'school-system'),
                number_format_i18n($sch_sum['warn'])
            )); ?>
        <?php else : ?>
            <?php esc_html_e('النظام جاهز', 'school-system'); ?>
        <?php endif; ?>
    </strong>
    <div class="sch-sub">
        <?php echo esc_html($sch_sum['bad'] > 0
            ? __('ابدأ بالبنود الحمراء — كلٌّ منها يوقف شيئًا يعتمد عليه أحد.', 'school-system')
            : __('راجع هذه الشاشة بعد كل ترقية، وقبل أوّل يومٍ دراسيّ.', 'school-system')); ?>
    </div>
</div>

<div class="sch-daylist sch-problems">
    <?php foreach ($sch_items as $sch_it) : ?>
        <?php
        /* اللون يقول الحالة قبل قراءة سطرٍ واحد — وهو المعنى نفسه في يوم الوكيل. */
        $sch_cls = match ($sch_it['level']) {
            'bad'   => 'sch-prob--r',
            'warn'  => 'sch-prob--a',
            default => 'sch-prob--clear',
        };
        ?>
        <?php if ($sch_it['url'] !== '') : ?>
            <a class="sch-prob <?php echo esc_attr($sch_cls); ?>" href="<?php echo esc_url($sch_it['url']); ?>">
        <?php else : ?>
            <div class="sch-prob <?php echo esc_attr($sch_cls); ?>">
        <?php endif; ?>

            <span class="sch-prob__n" aria-hidden="true">
                <?php /* خانة `__n` مقاسها ٣٨ بكسل ومركَّزة — تحمل رقمًا عادةً،
                         وبنود الجاهزية لا أرقام لها فتحمل إشارةً. والأسماء من
                         `includes/icons.php` وحدها: لا `alert` فيها. */ ?>
                <?php echo sch_icon(match ($sch_it['level']) {
                    'bad'   => 'flag',
                    'warn'  => 'bell',
                    default => 'check',
                }, 20); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </span>

            <span class="sch-prob__t">
                <?php /* `b` لا `strong`: `.sch-prob__t b` هو ما يُنسّقه المكوّن
                         (كتلة بمقاس `--sch-text-base`)، و`strong` يرث الغليظ
                         وحده فيفقد المقاس. */ ?>
                <b><?php echo esc_html($sch_it['title']); ?></b>
                <span><?php echo esc_html($sch_it['detail']); ?></span>
            </span>

            <?php /* لا صنفَ جديدًا لدعوة الفعل: الصفّ نفسه رابط، و`.sch-sub`
                     معرَّفة وتؤدّي ما يُراد. */ ?>
            <?php if ($sch_it['cta'] !== '') : ?>
                <span class="sch-sub"><?php echo esc_html($sch_it['cta']); ?> ←</span>
            <?php endif; ?>

        <?php if ($sch_it['url'] !== '') : ?>
            </a>
        <?php else : ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php
/*
 * متطلّبات التشغيل مكتوبةً.
 *
 * ثلاثةٌ لا يستطيع النظام ضبطها بنفسه — تُضبط في لوحة الاستضافة. وذكرُها
 * هنا لا في ملفٍّ خارجيّ لأن من يقرؤها هو من يقف أمام المشكلة الآن.
 */
?>
<div class="sch-card">
    <h2><?php esc_html_e('ما يُضبَط في لوحة الاستضافة', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('ثلاثة أشياء لا يستطيع النظام ضبطها بنفسه — ويحتاجها ليعمل كاملًا.', 'school-system'); ?></p>

    <div class="sch-table-wrap sch-mt">
        <table class="sch-table">
            <tbody>
                <tr>
                    <td class="sch-name"><?php esc_html_e('Cron حقيقيّ', 'school-system'); ?></td>
                    <td>
                        <?php esc_html_e('كل خمس دقائق على:', 'school-system'); ?>
                        <code dir="ltr"><?php echo esc_html(home_url('/wp-cron.php')); ?></code>
                        <span class="sch-sub sch-block"><?php esc_html_e('ومع تفعيله يُضاف DISABLE_WP_CRON إلى wp-config حتى لا يعمل مرّتين.', 'school-system'); ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="sch-name"><?php esc_html_e('استثناء الداشبورد من الكاش', 'school-system'); ?></td>
                    <td>
                        <code dir="ltr">/dashboard</code> · <code dir="ltr">/app</code> · <code dir="ltr">/gate</code> · <code dir="ltr">/driver</code>
                        <span class="sch-sub sch-block"><?php esc_html_e('صفحةٌ مكشوفة تعرض بيانات طالبٍ آخر لمن يفتحها بعده.', 'school-system'); ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="sch-name"><?php esc_html_e('شهادة SSL', 'school-system'); ?></td>
                    <td>
                        <?php esc_html_e('الكاميرا والعمل بلا شبكة والإشعارات — يمنعها المتصفّح بدونها.', 'school-system'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
