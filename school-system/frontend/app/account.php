<?php
/** ملف ولي الأمر. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$user     = wp_get_current_user();
$guardian = SCH_Guardians::by_user(get_current_user_id());
?>

<h1 class="p-h1"><?php esc_html_e('حسابي', 'school-system'); ?></h1>

<div class="p-card" style="text-align:center">
    <form method="post" enctype="multipart/form-data" id="p-av">
        <?php wp_nonce_field('sch_app_avatar', '_sch_nonce'); ?>
        <input type="hidden" name="sch_app_action" value="upload_avatar">

        <label class="p-av" for="p-av-file">
            <?php if (SCH_App::avatar_url()) : ?>
                <img src="<?php echo esc_url(SCH_App::avatar_url()); ?>" alt="" width="78" height="78">
            <?php else : ?>
                <?php echo sch_avatar_svg(mb_substr((string) $user->display_name, 0, 1), 78); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php endif; ?>
            <span class="p-av__cam"><?php echo sch_icon('badge', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </label>

        <input id="p-av-file" type="file" name="avatar" accept="image/jpeg,image/png" hidden
               aria-label="<?php esc_attr_e('الصورة الشخصية', 'school-system'); ?>"
               onchange="document.getElementById('p-av').submit()">
    </form>

    <b style="display:block;font-size:var(--p-t3);margin-top:10px"><?php echo esc_html((string) $user->display_name); ?></b>
    <?php if ($guardian && $guardian->parent_no) : ?>
        <span class="p-nm" style="font-size:var(--p-t5);color:var(--p-mute)"><?php echo esc_html((string) $guardian->parent_no); ?></span>
    <?php endif; ?>
</div>

<div class="p-list">
    <div class="p-row">
        <span class="p-row__t"><span><?php esc_html_e('الجوال', 'school-system'); ?></span></span>
        <span class="p-row__e p-nm"><?php echo esc_html((string) ($guardian->phone ?? '—')); ?></span>
    </div>
    <div class="p-row">
        <span class="p-row__t"><span><?php esc_html_e('الهوية', 'school-system'); ?></span></span>
        <span class="p-row__e p-nm"><?php echo esc_html((string) ($guardian->national_id ?? '—')); ?></span>
    </div>
    <div class="p-row">
        <span class="p-row__t"><span><?php esc_html_e('المدينة', 'school-system'); ?></span></span>
        <span class="p-row__e"><?php echo esc_html((string) ($guardian->city ?? '—')); ?></span>
    </div>
</div>

<h2 class="p-h2"><?php esc_html_e('كلمة المرور', 'school-system'); ?></h2>

<form method="post" class="p-card">
    <?php wp_nonce_field('sch_change_password', '_sch_nonce'); ?>
    <input type="hidden" name="sch_app_action" value="change_password">

    <div class="p-field">
        <label for="p-cur"><?php esc_html_e('الحالية', 'school-system'); ?></label>
        <input class="p-in" id="p-cur" type="password" name="current" required autocomplete="current-password">
    </div>
    <div class="p-field">
        <label for="p-new"><?php esc_html_e('الجديدة', 'school-system'); ?></label>
        <input class="p-in" id="p-new" type="password" name="new" required minlength="8" autocomplete="new-password">
    </div>
    <div class="p-field">
        <label for="p-cnf"><?php esc_html_e('تأكيدها', 'school-system'); ?></label>
        <input class="p-in" id="p-cnf" type="password" name="confirm" required minlength="8" autocomplete="new-password">
    </div>

    <button class="p-btn p-btn--brand p-tap"><?php esc_html_e('حفظ', 'school-system'); ?></button>
</form>

<a class="p-btn p-btn--quiet p-tap" href="<?php echo esc_url(SCH_App::url('logout')); ?>">
    <?php esc_html_e('تسجيل الخروج', 'school-system'); ?>
</a>
