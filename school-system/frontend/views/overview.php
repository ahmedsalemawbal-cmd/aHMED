<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$year     = SCH_Years::current();
$students = SCH_Students::list(['status' => 'active', 'per_page' => 1]);
$classes  = SCH_Classes::list();
$staff    = current_user_can('sch_manage_staff') ? SCH_Staff::list(['status' => 'active', 'per_page' => 1]) : null;
$guards   = current_user_can('sch_manage_guardians') ? SCH_Guardians::list(['per_page' => 1]) : null;

$seats = array_sum(array_map(static fn ($c): int => (int) $c->capacity, $classes));
$taken = array_sum(array_map(static fn ($c): int => (int) $c->enrolled, $classes));
?>
<h1 class="sch-title"><?php esc_html_e('نظرة عامة', 'school-system'); ?></h1>

<?php if (current_user_can('sch_manage_settings') && !SCH_Flow::is_complete('setup')) : ?>
    <?php SCH_Flow::next_card('setup'); ?>
    <?php SCH_Flow::checklist('setup', 0, __('إعداد النظام', 'school-system')); ?>
<?php endif; ?>

<?php if (!$year) : ?>
    <div class="sch-notice sch-notice--error">
        <?php esc_html_e('لا توجد سنة دراسية نشطة — أنشئها من الإعدادات قبل أي شيء آخر.', 'school-system'); ?>
        <a href="<?php echo esc_url(SCH_Dashboard::url('settings')); ?>"><?php esc_html_e('الإعدادات', 'school-system'); ?></a>
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

<div class="sch-card">
    <h2><?php esc_html_e('المرحلة الحالية', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('المرحلة 2 من 10 — النواة الإدارية مكتملة. التالي: النقل المدرسي والتتبع الحي.', 'school-system'); ?></p>
</div>
