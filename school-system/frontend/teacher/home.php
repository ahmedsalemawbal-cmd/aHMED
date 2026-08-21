<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$classes   = SCH_Teacher::my_classes(get_current_user_id());
$sch_today = SCH_Org::today_periods(get_current_user_id());
?>

<?php if (!empty($sch_data['denied'])) : ?>
    <?php /* رابطٌ لفصلٍ أو طالبٍ ليس من فصولي — القراءة تُفحص كالكتابة. */ ?>
    <div class="t-alert t-alert--error">
        <?php esc_html_e('هذا الفصل ليس من فصولك.', 'school-system'); ?>
    </div>
<?php endif; ?>

<?php if ($sch_today !== []) : ?>
    <!-- سكة اليوم: يوم المعلم سلسلة حصص، فتُعرض كخط زمني لا كقائمة -->
    <div class="t-sect">
        <h2><?php esc_html_e('سكة اليوم', 'school-system'); ?></h2>
        <span><?php echo esc_html(sprintf(
            /* translators: %d: عدد الحصص */
            _n('حصة', '%d حصص', count($sch_today), 'school-system'),
            count($sch_today)
        )); ?></span>
    </div>

    <div class="t-rail">
        <?php $sch_cur = (int) floor(((int) current_time('G') * 60 + (int) current_time('i') - 450) / 50) + 1; ?>
        <?php foreach ($sch_today as $sch_p) :
            $sch_n = (int) $sch_p->period_no;
            $sch_c = $sch_n < $sch_cur ? ' t-slot--done' : ($sch_n === $sch_cur ? ' t-slot--now' : ''); ?>
            <div class="t-slot<?php echo esc_attr($sch_c); ?>">
                <span class="t-slot__dot"><?php echo esc_html(number_format_i18n($sch_n)); ?></span>
                <span class="t-slot__txt">
                    <b><?php echo esc_html(trim((string) $sch_p->grade_level . ' / ' . (string) $sch_p->section)
                        . ' · ' . (string) ($sch_p->subject_name ?: '')); ?></b>
                    <span><?php echo esc_html($sch_n === $sch_cur ? __('جارية الآن', 'school-system') : ((int) $sch_p->is_sub === 1 ? __('احتياط', 'school-system') : '')); ?></span>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="t-sect">
    <h2><?php esc_html_e('فصولي', 'school-system'); ?></h2>
    <span><?php echo esc_html(number_format_i18n(count($classes))); ?></span>
</div>

<?php if ($classes === []) : ?>
    <div class="t-empty">
        <b><?php esc_html_e('لا توجد فصول مسندة إليك', 'school-system'); ?></b>
        <p><?php esc_html_e('تواصل مع وكيل الشؤون التعليمية.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="t-classes">
    <?php foreach ($classes as $c) :
        $roster   = SCH_Teacher::roster((int) $c->id);
        $unmarked = SCH_Teacher::unmarked($roster);
        $sch_total = max(1, count($roster));
        $sch_pct   = (int) round((($sch_total - count($unmarked)) / $sch_total) * 100);
        $done      = $unmarked === []; ?>
        <a class="t-class t-tap<?php echo $done ? ' t-class--clear' : ''; ?>"
           href="<?php echo esc_url(SCH_Teacher::url('klass', (int) $c->id)); ?>">
            <span class="t-ring<?php echo $done ? '' : ' is-warn'; ?>">
                <svg width="52" height="52" aria-hidden="true">
                    <circle class="bg" cx="26" cy="26" r="23"/>
                    <circle class="fg" cx="26" cy="26" r="23"
                            stroke-dasharray="144.5"
                            stroke-dashoffset="<?php echo esc_attr((string) round(144.5 * (1 - $sch_pct / 100), 1)); ?>"/>
                </svg>
                <b><?php echo esc_html(number_format_i18n($sch_pct)); ?>٪</b>
            </span>
            <span class="t-class__t">
                <b><?php echo esc_html(SCH_Classes::label($c)); ?></b>
                <span>
                    <?php echo esc_html(sprintf(
                        /* translators: %d: عدد الطلاب */
                        _n('%d طالب', '%d طلاب', count($roster), 'school-system'),
                        count($roster)
                    )); ?><?php echo $done
                        ? esc_html(' · ' . __('اكتمل الرصد', 'school-system'))
                        : esc_html(' · ' . sprintf(
                            /* translators: %d: عدد الطلاب */
                            _n('%d بانتظار الرصد', '%d بانتظار الرصد', count($unmarked), 'school-system'),
                            count($unmarked)
                        )); ?>
                </span>
            </span>
            <span class="t-class__e"><?php echo sch_icon('chev', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </a>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
/*
 * زرّ الإشعارات.
 *
 * `SCH_Push::boot()` محمَّلٌ في قالب المعلم منذ v9.4، و`push.js` لا يطلب
 * الإذن إلا من ضغطة صاحب الجهاز (`[data-sch-push]`) — **ولا زرّ في التطبيق
 * كلّه**. فالمعلم لا يستطيع الاشتراك أصلًا، ويبدو أن الإشعارات لا تعمل.
 */
?>
<button type="button" class="t-btn t-btn--quiet t-tap" data-sch-push>
    <span data-sch-push-label><?php esc_html_e('تفعيل الإشعارات', 'school-system'); ?></span>
</button>

<a class="t-btn t-btn--quiet t-tap" href="<?php echo esc_url(sch_logout_url(SCH_Teacher::url())); ?>"><?php esc_html_e('تسجيل الخروج', 'school-system'); ?></a>
