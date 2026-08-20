<?php
/**
 * شاشة الدخول.
 *
 * البنية من تصميم «أُصول»: هويةٌ موسَّطة، ثم حقلان كلٌّ في غلافٍ واحد
 * بأيقونته، ثم زرٌّ عريض، ثم سطر الدعم.
 *
 * والمدخل يبقى رقم الجوال — الأب يتذكر جواله ولا يتذكر بريده،
 * و`SCH_App::resolve_login()` تترجم الرقم إلى اسم الدخول.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$err    = (string) ($sch_data['error'] ?? '');
$school = sch_settings('school_name', get_bloginfo('name'));
?>
<div class="p-auth p-auth--login">

    <div class="p-auth__brand">
        <span class="p-auth__mark"><?php echo sch_icon('home', 38); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <h1><?php esc_html_e('أُصول', 'school-system'); ?></h1>
        <p class="p-auth__org"><?php echo esc_html($school); ?></p>
    </div>

    <form class="p-auth__form" method="post">
        <?php wp_nonce_field('sch_app_login', '_sch_nonce'); ?>
        <input type="hidden" name="sch_app_login" value="1">

        <div class="p-auth__fields">

            <?php if ($err !== '') : ?>
                <div class="p-auth__err"><?php echo esc_html($err); ?></div>
            <?php endif; ?>

            <div class="p-auth__f">
                <label class="p-auth__lb" for="p-u"><?php esc_html_e('رقم الجوال', 'school-system'); ?></label>
                <span class="p-auth__shell">
                    <span class="p-auth__ico"><?php echo sch_icon('phone', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                    <input class="p-auth__inp" id="p-u" type="tel" name="username" dir="ltr"
                           inputmode="numeric" autocomplete="username" required
                           placeholder="05xxxxxxxx">
                </span>
            </div>

            <div class="p-auth__f">
                <label class="p-auth__lb" for="p-p"><?php esc_html_e('كلمة المرور', 'school-system'); ?></label>
                <span class="p-auth__shell">
                    <span class="p-auth__ico"><?php echo sch_icon('lock', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                    <input class="p-auth__inp" id="p-p" type="password" name="password"
                           autocomplete="current-password" required>
                    <?php /* الكشف زرّ حقيقي لا أيقونة زينة: من يكتب كلمة سرّه على جوال
                             في باص يحتاج أن يراها. والاسم يتبدّل مع الحالة. */ ?>
                    <button class="p-auth__eye" type="button" data-p-eye
                            aria-pressed="false" aria-controls="p-p"
                            data-show="<?php esc_attr_e('إظهار كلمة المرور', 'school-system'); ?>"
                            data-hide="<?php esc_attr_e('إخفاء كلمة المرور', 'school-system'); ?>"
                            aria-label="<?php esc_attr_e('إظهار كلمة المرور', 'school-system'); ?>">
                        <?php echo sch_icon('eye', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </button>
                </span>
            </div>

            <?php /* «نسيت كلمة المرور؟» ليس رابطًا: لا إعادة تعيين ذاتية في النظام،
                     فالجواب يُطوى تحت السؤال بدل رابطٍ ميت يوهم بميزة. */ ?>
            <details class="p-auth__forgot">
                <summary><?php esc_html_e('نسيت كلمة المرور؟', 'school-system'); ?></summary>
                <p><?php esc_html_e('راجع إدارة المدرسة لإعادة تعيينها.', 'school-system'); ?></p>
            </details>

        </div>

        <button class="p-btn p-btn--brand p-tap p-auth__go"><?php esc_html_e('تسجيل الدخول', 'school-system'); ?></button>
    </form>

    <p class="p-auth__hint"><?php esc_html_e('للدعم الفني تواصل مع إدارة المدرسة', 'school-system'); ?></p>

</div>

<script>
(function () {
  var eye = document.querySelector('[data-p-eye]');
  var pw  = document.getElementById('p-p');
  if (!eye || !pw) { return; }

  eye.addEventListener('click', function () {
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    eye.setAttribute('aria-pressed', show ? 'true' : 'false');
    eye.setAttribute('aria-label', show ? eye.dataset.hide : eye.dataset.show);
    pw.focus();
  });
})();
</script>
