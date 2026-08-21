<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

$sch_error = (string) ($sch_data['error'] ?? '');
?>
<div class="sch-auth">
    <div class="sch-auth__card">

        <h1 class="sch-auth__title"><?php esc_html_e('نظام المدرسة', 'school-system'); ?></h1>
        <p class="sch-auth__sub"><?php esc_html_e('بوابة دخول الإدارة والموظفين', 'school-system'); ?></p>

        <?php if ($sch_error !== '') : ?>
            <div class="sch-notice sch-notice--error"><?php echo esc_html($sch_error); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" autocomplete="on">
            <?php wp_nonce_field('sch_login', '_sch_nonce'); ?>
            <input type="hidden" name="sch_login" value="1">

            <div class="sch-field">
                <label for="sch-username"><?php esc_html_e('اسم المستخدم أو البريد', 'school-system'); ?></label>
                <input id="sch-username" type="text" name="username" required autofocus autocomplete="username">
            </div>

            <div class="sch-field">
                <label for="sch-password"><?php esc_html_e('كلمة المرور', 'school-system'); ?></label>
                <input id="sch-password" type="password" name="password" required autocomplete="current-password">
            </div>

            <label class="sch-check">
                <input type="checkbox" name="remember" value="1">
                <?php esc_html_e('أبقني مسجّلًا', 'school-system'); ?>
            </label>

            <button type="submit" class="sch-btn sch-btn--block"><?php esc_html_e('دخول', 'school-system'); ?></button>
        </form>

        <p class="sch-auth__note">
            <?php esc_html_e('نسيت كلمة المرور؟ راجع إدارة المدرسة لإعادة تعيينها.', 'school-system'); ?>
        </p>

    </div>
</div>
