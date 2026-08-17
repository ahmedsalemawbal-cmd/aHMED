<?php
/** ملف ولي الأمر. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$user     = wp_get_current_user();
$guardian = SCH_Guardians::by_user(get_current_user_id());
?>

<h1 class="p-h1"><?php esc_html_e('حسابي', 'school-system'); ?></h1>

<!-- ترويسة الملف: صورة قابلة للتبديل + الاسم + شارات -->
<div class="p-prof">
    <form method="post" enctype="multipart/form-data" id="p-av">
        <?php wp_nonce_field('sch_app_avatar', '_sch_nonce'); ?>
        <input type="hidden" name="sch_app_action" value="upload_avatar">

        <?php $sch_av = SCH_App::avatar_url(); ?>
        <label class="p-av" for="p-av-file">
            <?php if ($sch_av !== '') : ?>
                <img id="p-av-img" src="<?php echo esc_url($sch_av); ?>" alt="" width="78" height="78">
            <?php else : ?>
                <?php echo sch_avatar_svg(mb_substr((string) $user->display_name, 0, 1), 78); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php endif; ?>
            <span class="p-av__cam"><?php echo sch_icon('image', 14); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </label>

        <input id="p-av-file" type="file" name="avatar" accept="image/jpeg,image/png" hidden
               aria-label="<?php esc_attr_e('الصورة الشخصية', 'school-system'); ?>">
    </form>

    <b class="p-prof__name"><?php echo esc_html((string) $user->display_name); ?></b>
    <div class="p-prof__meta">
        <span class="p-prof__chip"><?php esc_html_e('ولي أمر', 'school-system'); ?></span>
        <?php if ($guardian && $guardian->parent_no) : ?>
            <span class="p-prof__chip"><span class="p-nm"><?php echo esc_html((string) $guardian->parent_no); ?></span></span>
        <?php endif; ?>
    </div>
</div>

<!-- المعلومات -->
<h2 class="p-h2"><?php esc_html_e('المعلومات', 'school-system'); ?></h2>
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

<!-- الإعدادات -->
<h2 class="p-h2"><?php esc_html_e('الإعدادات', 'school-system'); ?></h2>
<div class="p-list">
    <button type="button" class="p-setrow p-setrow--theme p-theme p-tap">
        <span class="p-setrow__i"><?php echo sch_icon('sun', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-setrow__t"><?php esc_html_e('المظهر', 'school-system'); ?></span>
        <span class="p-setrow__e" aria-hidden="true">
            <svg class="p-theme__moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg class="p-theme__sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </span>
    </button>

    <?php /* الإشعار الفوري: الإذن يُطلب من ضغطة صاحب الجهاز — المتصفح يحجب
             الطلب التلقائي، ومن رفض مرة لا يُسأل ثانية أبدًا. */ ?>
    <?php if (SCH_Push::ready()) : ?>
        <button type="button" class="p-setrow p-tap" data-sch-push>
            <span class="p-setrow__i"><?php echo sch_icon('bell', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <span class="p-setrow__t" data-sch-push-label><?php esc_html_e('تفعيل الإشعارات', 'school-system'); ?></span>
            <span class="p-setrow__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </button>
    <?php endif; ?>
</div>

<!-- كلمة المرور -->
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

<!-- الخروج -->
<div class="p-list">
    <a class="p-setrow p-setrow--out p-tap" href="<?php echo esc_url(SCH_App::url('logout')); ?>">
        <span class="p-setrow__i"><?php echo sch_icon('door', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-setrow__t"><?php esc_html_e('تسجيل الخروج', 'school-system'); ?></span>
        <span class="p-setrow__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
    </a>
</div>

<script>
/* الصورة تتبدّل **قبل** أن تُرفع: نرسم الملف المختار محليًّا في اللحظة نفسها،
   ثم يُرسَل النموذج في الخلفية. الانتظار الذي كان يظهر لم يكن رفعًا بطيئًا —
   بل كاشًا يعرض القديمة، وقد صار العنوان يحمل بصمة الملف فانتهى. */
(function () {
  var input = document.getElementById('p-av-file');
  var form  = document.getElementById('p-av');
  if (!input || !form) { return; }

  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    if (!file) { return; }

    var label = form.querySelector('.p-av');
    var img   = document.getElementById('p-av-img');

    if (!img && label) {
      // من لم يكن له صورة: نستبدل الحرف بصورة فورًا
      var svg = label.querySelector('svg:not(.p-av__cam svg)');
      img = document.createElement('img');
      img.id = 'p-av-img';
      img.width = 78; img.height = 78; img.alt = '';
      if (svg && svg.parentNode === label) { label.replaceChild(img, svg); }
      else { label.insertBefore(img, label.firstChild); }
    }

    if (img) { img.src = URL.createObjectURL(file); }
    if (label) { label.classList.add('is-saving'); }

    form.submit();
  });
}());
</script>
