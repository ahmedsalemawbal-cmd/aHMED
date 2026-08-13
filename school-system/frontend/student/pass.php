<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$student = $sch_data['student'];
$class   = SCH_Students::current_class((int) $student->id);
$pass    = SCH_Pass::current($student);
?>
<div class="schs-pass" id="schs-pass"
     data-api="<?php echo esc_attr(rest_url(SCH_API_NS . '/student/pass')); ?>"
     data-left="<?php echo esc_attr((string) $pass['expires_in']); ?>"
     data-window="<?php echo esc_attr((string) $pass['window']); ?>">

    <div class="schs-pass__qr" id="schs-pass-qr">
        <?php echo SCH_Pass::svg($student); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </div>

    <strong class="schs-pass__name"><?php echo esc_html(SCH_Enrollment::full_name($student)); ?></strong>
    <span class="schs-pass__meta">
        <?php echo esc_html(($class ? SCH_Classes::label($class) : '') . ' · ' . ($student->academic_no ?: '')); ?>
    </span>

    <div class="schs-pass__timer">
        <svg class="schs-ring" viewBox="0 0 26 26" aria-hidden="true">
            <circle class="schs-ring__bg" cx="13" cy="13" r="11"/>
            <circle class="schs-ring__fg" id="schs-ring" cx="13" cy="13" r="11"
                    stroke-dasharray="69.1" stroke-dashoffset="0"/>
        </svg>
        <span id="schs-left"></span>
    </div>

    <p class="schs-pass__note">
        <?php esc_html_e('الرمز يتغيّر كل نصف دقيقة — لقطة الشاشة تنتهي صلاحيتها فلا يستطيع أحد الدخول باسمك.', 'school-system'); ?>
    </p>

    <a class="schs-btn schs-btn--quiet" href="<?php echo esc_url(SCH_Student::url('me')); ?>">
        <?php esc_html_e('إغلاق', 'school-system'); ?>
    </a>
</div>
