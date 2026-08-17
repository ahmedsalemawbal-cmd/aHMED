<?php
/** تثبيت التطبيق على الشاشة الرئيسية. */

declare(strict_types=1);
defined('ABSPATH') || exit;
?>
<div class="p-auth">
    <div class="p-auth__mark"><?php echo esc_html(mb_substr(sch_settings('school_name', get_bloginfo('name')), 0, 2)); ?></div>
    <h1><?php esc_html_e('ثبّت التطبيق', 'school-system'); ?></h1>
    <p class="p-auth__s"><?php esc_html_e('ليفتح من شاشتك الرئيسية كأي تطبيق.', 'school-system'); ?></p>

    <div class="p-auth__box">
        <div class="p-steps">
            <div><b>١</b><span><?php esc_html_e('افتح قائمة المتصفح (النقاط الثلاث)', 'school-system'); ?></span></div>
            <div><b>٢</b><span><?php esc_html_e('اختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية»', 'school-system'); ?></span></div>
            <div><b>٣</b><span><?php esc_html_e('أكّد — تظهر الأيقونة على شاشتك', 'school-system'); ?></span></div>
        </div>

        <button class="p-btn p-btn--brand p-tap" id="p-install" hidden>
            <?php esc_html_e('تثبيت الآن', 'school-system'); ?>
        </button>

        <a class="p-btn p-btn--quiet p-tap" href="<?php echo esc_url(SCH_App::url()); ?>">
            <?php esc_html_e('لاحقًا', 'school-system'); ?>
        </a>
    </div>
</div>

<script>
(function () {
  var btn = document.getElementById('p-install');
  var prompt = null;
  if (!btn) { return; }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    prompt = e;
    btn.hidden = false;
  });

  btn.addEventListener('click', function () {
    if (!prompt) { return; }
    prompt.prompt();
    prompt = null;
    btn.hidden = true;
  });
})();
</script>
