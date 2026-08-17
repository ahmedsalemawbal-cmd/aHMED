<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$student  = $sch_data['student'];
$progress = SCH_Student::progress((int) $student->id);
$class    = SCH_Students::current_class((int) $student->id);
?>
<div class="schs-profile">
    <span class="schs-profile__pic">
        <?php echo sch_avatar_svg(mb_substr((string) $student->full_name, 0, 1), 96); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </span>
    <h1 class="schs-h1"><?php echo esc_html($student->full_name); ?></h1>
    <p class="schs-sub"><?php echo esc_html($class ? SCH_Classes::label($class) : ''); ?></p>
</div>

<div class="schs-two">
    <div class="schs-d3">
        <span class="schs-label"><?php esc_html_e('دقائق التعلّم', 'school-system'); ?></span>
        <strong class="schs-hero"><?php echo esc_html(number_format_i18n($progress['minutes'])); ?></strong>
        <span class="schs-sub"><?php esc_html_e('هذا الأسبوع', 'school-system'); ?></span>
    </div>
    <div class="schs-d3">
        <span class="schs-label"><?php esc_html_e('واجبات سلّمتها', 'school-system'); ?></span>
        <strong class="schs-hero"><?php echo esc_html(number_format_i18n($progress['homework'])); ?></strong>
    </div>
</div>

<div class="schs-flat">
    <div class="schs-row"><span><?php esc_html_e('الرقم الأكاديمي', 'school-system'); ?></span><strong dir="ltr"><?php echo esc_html($student->academic_no ?: '—'); ?></strong></div>
    <div class="schs-row"><span><?php esc_html_e('حضورك', 'school-system'); ?></span><strong><?php echo esc_html(number_format($progress['attendance'], 1)); ?>%</strong></div>
</div>

<a class="schs-logout" href="<?php echo esc_url(SCH_Student::url('logout')); ?>"><?php esc_html_e('تسجيل الخروج', 'school-system'); ?></a>
