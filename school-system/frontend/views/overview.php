<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$year     = SCH_Years::current();
$students = SCH_Students::list(['status' => 'active', 'per_page' => 1, 'no_scope' => true]);
$classes  = SCH_Classes::list();
$staff    = current_user_can('sch_manage_staff') ? SCH_Staff::list(['status' => 'active', 'per_page' => 1]) : null;
$guards   = current_user_can('sch_manage_guardians') ? SCH_Guardians::list(['per_page' => 1]) : null;

$seats = array_sum(array_map(static fn ($c): int => (int) $c->capacity, $classes));
$taken = array_sum(array_map(static fn ($c): int => (int) $c->enrolled, $classes));

// مركز القيادة: ما ينتظر المدير اليوم — لا قائمة أقسام. البنود من مُجمِّع
// واحد مشترك مع جرس الشريط العلوي (sch_pending_items)، والنواقص من المحرّك.
$sch_items = sch_pending_items();
$sch_gaps  = class_exists('SCH_Flow') ? SCH_Flow::gaps() : [];
$sch_hour  = (int) wp_date('G');
$sch_hello = $sch_hour < 12
    ? __('صباح الخير', 'school-system')
    : ($sch_hour < 17 ? __('طاب يومك', 'school-system') : __('مساء الخير', 'school-system'));
?>
<h1 class="sch-title"><?php echo esc_html($sch_hello); ?></h1>
<p class="sch-sub"><?php echo esc_html(wp_date('l، j F Y')); ?> — <?php esc_html_e('إليك ما ينتظرك اليوم.', 'school-system'); ?></p>

<?php if (current_user_can('sch_manage_settings') && !SCH_Flow::is_complete('setup')) : ?>
    <?php SCH_Flow::checklist('setup', 0, __('إعداد النظام', 'school-system')); ?>
<?php endif; ?>

<?php if (!$year) : ?>
    <div class="sch-notice sch-notice--error">
        <?php esc_html_e('لا توجد سنة دراسية نشطة — أنشئها من الإعدادات قبل أي شيء آخر.', 'school-system'); ?>
        <a href="<?php echo esc_url(SCH_Dashboard::url('settings')); ?>"><?php esc_html_e('الإعدادات', 'school-system'); ?></a>
    </div>
<?php endif; ?>

<?php $sch_pending = array_sum(array_map(static fn (array $i): int => (int) $i['n'], $sch_items)); ?>

<?php if ($sch_items !== []) : ?>
    <div class="sch-card">
        <h2><?php esc_html_e('ما ينتظرك اليوم', 'school-system'); ?></h2>

        <div class="sch-problems">
            <?php foreach ($sch_items as $sch_it) : $sch_n = (int) $sch_it['n']; ?>
                <?php $sch_cls = $sch_n === 0 ? 'sch-prob--clear' : 'sch-prob--' . $sch_it['level']; ?>
                <?php if ($sch_n > 0) : ?>
                    <a class="sch-prob <?php echo esc_attr($sch_cls); ?>" href="<?php echo esc_url($sch_it['url']); ?>">
                <?php else : ?>
                    <div class="sch-prob <?php echo esc_attr($sch_cls); ?>">
                <?php endif; ?>
                    <span class="sch-prob__n"><?php echo esc_html(number_format_i18n($sch_n)); ?></span>
                    <span class="sch-prob__t">
                        <strong><?php echo esc_html($sch_it['title']); ?></strong>
                        <span><?php echo esc_html($sch_n === 0 ? __('لا شيء ينتظرك هنا', 'school-system') : $sch_it['hint']); ?></span>
                    </span>
                <?php if ($sch_n > 0) : ?>
                    </a>
                <?php else : ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($sch_pending === 0) : ?>
            <p class="sch-sub sch-mt"><?php esc_html_e('كل شيء تحت السيطرة — لا مهمة عاجلة تنتظرك.', 'school-system'); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="sch-stats">
    <a class="sch-stat" href="<?php echo esc_url(SCH_Dashboard::url('students')); ?>">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n((int) $students['total'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('طالب نشط', 'school-system'); ?></span>
    </a>

    <a class="sch-stat" href="<?php echo esc_url(SCH_Dashboard::url('classes')); ?>">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n(count($classes))); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('شعبة', 'school-system'); ?></span>
    </a>

    <?php if ($guards) : ?>
        <a class="sch-stat" href="<?php echo esc_url(SCH_Dashboard::url('guardians')); ?>">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n((int) $guards['total'])); ?></span>
            <span class="sch-stat__label"><?php esc_html_e('ولي أمر', 'school-system'); ?></span>
        </a>
    <?php endif; ?>

    <?php if ($staff) : ?>
        <a class="sch-stat" href="<?php echo esc_url(SCH_Dashboard::url('employees')); ?>">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n((int) $staff['total'])); ?></span>
            <span class="sch-stat__label"><?php esc_html_e('موظف', 'school-system'); ?></span>
        </a>
    <?php endif; ?>
</div>

<?php if ($sch_gaps !== []) : ?>
    <div class="sch-card">
        <h2><?php esc_html_e('ملفات ناقصة', 'school-system'); ?></h2>
        <p class="sch-sub"><?php esc_html_e('السجل الناقص لا يشتكي — فالنظام يشتكي عنه.', 'school-system'); ?></p>
        <div class="sch-gaps">
            <?php foreach ($sch_gaps as $sch_g) : ?>
                <a class="sch-gap sch-gap--<?php echo esc_attr($sch_g['level']); ?>" href="<?php echo esc_url($sch_g['url']); ?>">
                    <b><?php echo esc_html(number_format_i18n((int) $sch_g['n'])); ?></b>
                    <span>
                        <?php echo esc_html($sch_g['label']); ?>
                        <em><?php echo esc_html($sch_g['why']); ?></em>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($seats > 0) : ?>
    <div class="sch-card">
        <h2><?php esc_html_e('إشغال الشعب', 'school-system'); ?></h2>
        <p class="sch-sub">
            <?php echo esc_html(sprintf(
                /* translators: 1: المشغول 2: السعة */
                __('%1$s من %2$s مقعدًا مشغول.', 'school-system'),
                number_format_i18n($taken),
                number_format_i18n($seats)
            )); ?>
        </p>
        <div class="sch-bar"><span style="width: <?php echo esc_attr((string) min(100, (int) round($taken / $seats * 100))); ?>%"></span></div>
    </div>
<?php endif; ?>
