<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$areas = (array) ($sch_data['areas'] ?? []);
$user  = wp_get_current_user();
?>
<div class="sch-hub">
    <h1 class="sch-hub__title"><?php echo esc_html(sch_settings('school_name', get_bloginfo('name'))); ?></h1>
    <p class="sch-hub__sub">
        <?php echo esc_html(sprintf(
            /* translators: %s: اسم المستخدم */
            __('أهلًا %s — اختر القسم الذي تريد العمل فيه.', 'school-system'),
            $user->display_name
        )); ?>
    </p>

    <div class="sch-hub__grid">
        <?php foreach ($areas as $key => $area) : ?>
            <a class="sch-hub__card sch-hub__card--<?php echo esc_attr($key); ?>"
               href="<?php echo esc_url(SCH_Dashboard::url((string) $area['landing'])); ?>">
                <span class="sch-hub__mark"></span>
                <span class="sch-hub__icon"><?php echo sch_icon($area['icon'], 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <strong class="sch-hub__name"><?php echo esc_html($area[0]); ?></strong>
                <span class="sch-hub__desc"><?php echo esc_html($area[1]); ?></span>
                <span class="sch-hub__count">
                    <?php echo esc_html(sprintf(
                        /* translators: %d: عدد الشاشات */
                        _n('%d شاشة', '%d شاشات', (int) $area['count'], 'school-system'),
                        (int) $area['count']
                    )); ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <a class="sch-hub__out" href="<?php echo esc_url(sch_logout_url(SCH_Dashboard::url())); ?>">
        <?php esc_html_e('تسجيل الخروج', 'school-system'); ?>
    </a>
</div>
