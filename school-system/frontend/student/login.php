<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * شاشة «حسابك غير مرتبط بسجل طالب».
 *
 * كانت نموذج دخولٍ كامل بحقلين ونونس `sch_stu_login` — **ولا معالج له**:
 * بوابة الدخول الموحّدة (v3.2.0) صارت الباب الوحيد، و`render_login()` تعيد
 * التوجيه إليها بلا شرط. فالنموذج يُرسَل ولا يفعل شيئًا بصمت.
 *
 * والمسار الحيّ الوحيد لهذه الشاشة هو حالةٌ أخرى تمامًا: طالبٌ دخل بحسابه
 * فعلًا لكن حسابه غير مربوط بسجلّ طالب — وهي حالة إدارية لا تُصلَح بكلمة
 * مرور، فتُقال كما هي ويُعطى المستخدم مخرجًا.
 */

$sch_error = (string) ($sch_data['error'] ?? '');
?>
<div class="schs-auth">
    <div class="schs-auth__brand">
        <span class="schs-auth__mark"></span>
        <h1><?php esc_html_e('تعلّم', 'school-system'); ?></h1>
        <p><?php esc_html_e('واجباتك ومكتبتك وألعابك', 'school-system'); ?></p>
    </div>

    <div class="schs-flash schs-flash--bad">
        <?php echo esc_html($sch_error !== ''
            ? $sch_error
            : __('حسابك غير مرتبط بسجل طالب.', 'school-system')); ?>
    </div>

    <p class="schs-auth__note">
        <?php esc_html_e('راجع إدارة المدرسة لربط حسابك — لا يُصلَح هذا بكلمة مرور.', 'school-system'); ?>
    </p>

    <a class="schs-btn" href="<?php echo esc_url(sch_logout_url(SCH_Student::url())); ?>">
        <?php esc_html_e('تسجيل الخروج', 'school-system'); ?>
    </a>
</div>
