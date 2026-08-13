<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$classes = SCH_Teacher::my_classes(get_current_user_id());
?>
<?php $sch_today = SCH_Org::today_periods(get_current_user_id()); ?>

<?php if ($sch_today !== []) : ?>
    <!-- سكة اليوم: يوم المعلم سلسلة حصص، فتُعرض كخط زمني لا كقائمة -->
    <div class="sch-sect">
        <h2><?php esc_html_e('سكة اليوم', 'school-system'); ?></h2>
        <span><?php echo esc_html(sprintf(
            /* translators: %d: عدد الحصص */
            _n('حصة', '%d حصص', count($sch_today), 'school-system'),
            count($sch_today)
        )); ?></span>
    </div>

    <div class="sch-rail">
        <?php $sch_cur = (int) floor(((int) current_time('G') * 60 + (int) current_time('i') - 450) / 50) + 1; ?>
        <?php foreach ($sch_today as $sch_p) :
            $sch_n = (int) $sch_p->period_no;
            $sch_c = $sch_n < $sch_cur ? ' sch-slot--done' : ($sch_n === $sch_cur ? ' sch-slot--now' : ''); ?>
            <div class="sch-slot<?php echo esc_attr($sch_c); ?>">
                <span class="sch-slot__dot"><b><?php echo esc_html(number_format_i18n($sch_n)); ?></b></span>
                <span class="sch-slot__txt">
                    <b>
                        <?php echo esc_html(trim((string) $sch_p->grade_level . ' / ' . (string) $sch_p->section)
                            . ' · ' . (string) ($sch_p->subject_name ?: '')); ?>
                        <?php if ((int) $sch_p->is_sub === 1) : ?>
                            <span class="scha-chip scha-chip--bad"><?php esc_html_e('احتياط', 'school-system'); ?></span>
                        <?php endif; ?>
                    </b>
                    <span><?php echo esc_html($sch_n === $sch_cur ? __('جارية الآن', 'school-system') : ''); ?></span>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="sch-sect">
    <h2><?php esc_html_e('فصولي', 'school-system'); ?></h2>
    <span><?php echo esc_html(number_format_i18n(count($classes))); ?></span>
</div>

<?php if ($classes === []) : ?>
    <div class="scha-empty">
        <strong><?php esc_html_e('لا توجد فصول مسندة إليك', 'school-system'); ?></strong>
        <p><?php esc_html_e('تواصل مع وكيل الشؤون التعليمية.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="sch-problems">
    <?php foreach ($classes as $c) :
        $roster   = SCH_Teacher::roster((int) $c->id);
        $unmarked = SCH_Teacher::unmarked($roster); ?>
        <?php
        $sch_total = max(1, count($roster));
        $sch_pct   = (int) round((($sch_total - count($unmarked)) / $sch_total) * 100);
        ?>
        <a class="sch-prob<?php echo $unmarked === [] ? ' sch-prob--clear' : ' sch-prob--a'; ?>"
           href="<?php echo esc_url(SCH_Teacher::url('klass', (int) $c->id)); ?>">
            <span class="sch-ring<?php echo $unmarked === [] ? '' : ' is-warn'; ?>">
                <svg width="46" height="46" aria-hidden="true">
                    <circle class="bg" cx="23" cy="23" r="19"/>
                    <circle class="fg" cx="23" cy="23" r="19"
                            stroke-dasharray="119.4"
                            stroke-dashoffset="<?php echo esc_attr((string) round(119.4 * (1 - $sch_pct / 100), 1)); ?>"/>
                </svg>
                <b><?php echo esc_html(number_format_i18n($sch_pct)); ?>٪</b>
            </span>
            <span class="sch-prob__t">
                <b><?php echo esc_html(SCH_Classes::label($c)); ?></b>
                <span>
                    <?php echo esc_html(sprintf(
                        /* translators: %d: عدد الطلاب */
                        _n('%d طالب', '%d طلاب', count($roster), 'school-system'),
                        count($roster)
                    )); ?>
                    <?php echo $unmarked !== []
                        ? esc_html(' · ' . sprintf(
                            /* translators: %d: عدد الطلاب */
                            _n('%d بانتظار الرصد', '%d بانتظار الرصد', count($unmarked), 'school-system'),
                            count($unmarked)
                        ))
                        : esc_html(' · ' . __('اكتمل الرصد', 'school-system')); ?>
                </span>
            </span>
        </a>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<a class="scha-logout" href="<?php echo esc_url(SCH_Teacher::url('logout')); ?>"><?php esc_html_e('تسجيل الخروج', 'school-system'); ?></a>
