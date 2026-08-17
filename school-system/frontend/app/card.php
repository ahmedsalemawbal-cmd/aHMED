<?php
/** بطاقة الطالب — تُرى دفعة واحدة بلا تمرير. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$student = SCH_Students::get($id);

if (!$student) { require SCH_PATH . 'frontend/app/home.php'; return; }

$class  = SCH_Students::current_class($id);
$sub    = SCH_Routes::subscription_of($id);
$token  = (string) ($student->badge_token ?: $student->qr_token);
$photo  = $student->photo_file ? SCH_App::photo_url($id) : '';
$school = sch_settings('school_name', get_bloginfo('name'));
?>

<h1 class="p-h1"><?php esc_html_e('البطاقة', 'school-system'); ?></h1>

<article class="p-id">
    <header class="p-id__top">
        <span><?php echo esc_html($school); ?></span>
        <em><?php esc_html_e('بطاقة طالب', 'school-system'); ?></em>
    </header>

    <div class="p-id__who">
        <span class="p-id__pic">
            <?php if ($photo) : ?>
                <img src="<?php echo esc_url($photo); ?>" alt="" width="62" height="76" loading="lazy">
            <?php else : ?>
                <?php echo sch_avatar_svg(mb_substr((string) $student->full_name, 0, 1), 62); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php endif; ?>
        </span>

        <span class="p-id__name">
            <b><?php echo esc_html(SCH_Enrollment::full_name($student)); ?></b>
            <?php if ($student->name_en) : ?>
                <em dir="ltr"><?php echo esc_html($student->name_en); ?></em>
            <?php endif; ?>
            <span class="p-tag p-tag--mute"><?php echo esc_html($class ? SCH_Classes::label($class) : __('بلا شعبة', 'school-system')); ?></span>
        </span>
    </div>

    <div class="p-id__no">
        <span><?php esc_html_e('الرقم الأكاديمي', 'school-system'); ?></span>
        <b class="p-nm"><?php echo esc_html($student->academic_no ?: '—'); ?></b>
    </div>

    <dl class="p-id__facts">
        <div>
            <dt><?php esc_html_e('رقم الهوية', 'school-system'); ?></dt>
            <dd class="p-nm"><?php echo esc_html($student->national_id ?: '—'); ?></dd>
        </div>
        <div>
            <dt><?php esc_html_e('تاريخ الميلاد', 'school-system'); ?></dt>
            <dd><?php echo esc_html($student->birth_date ? wp_date('j M Y', strtotime((string) $student->birth_date)) : '—'); ?></dd>
        </div>
        <div>
            <dt><?php esc_html_e('النقل', 'school-system'); ?></dt>
            <dd><?php echo esc_html($sub ? $sub->route_name : __('لا يوجد', 'school-system')); ?></dd>
        </div>
        <div>
            <dt><?php esc_html_e('الحالة الآن', 'school-system'); ?></dt>
            <dd><?php echo esc_html(SCH_Custody::state_label($student->custody_state)); ?></dd>
        </div>
    </dl>

    <div class="p-id__qr">
        <span class="p-qr">
            <?php echo SCH_QR::svg($token, 4, 1); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php if ($photo) : ?>
                <img class="p-qr__face" src="<?php echo esc_url($photo); ?>" alt="" width="34" height="34" loading="lazy">
            <?php endif; ?>
        </span>
        <span class="p-id__hint"><?php esc_html_e('اضغط لعرضه كاملًا', 'school-system'); ?></span>
    </div>

    <button type="button" class="p-id__open" id="p-open-qr"
            aria-label="<?php esc_attr_e('عرض الرمز بملء الشاشة', 'school-system'); ?>"></button>
</article>

<div class="p-full" id="p-full" hidden>
    <div class="p-full__box">
        <span class="p-full__n"><?php echo esc_html(SCH_Enrollment::full_name($student)); ?></span>
        <span class="p-qr p-qr--big">
            <?php echo SCH_QR::svg($token, 8, 1); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php if ($photo) : ?>
                <img class="p-qr__face" src="<?php echo esc_url($photo); ?>" alt="" width="70" height="70">
            <?php endif; ?>
        </span>
        <span class="p-full__no p-nm"><?php echo esc_html($student->academic_no ?: ''); ?></span>
        <span class="p-full__x"><?php esc_html_e('اضغط للإغلاق', 'school-system'); ?></span>
    </div>
</div>

<script>
(function () {
  var box = document.getElementById('p-full');
  var btn = document.getElementById('p-open-qr');
  if (!box || !btn) { return; }

  var lock = null;

  btn.addEventListener('click', function () {
    box.hidden = false;
    /* الشاشة تُضاء لأقصاها: ماسح البوابة يفشل على شاشة خافتة */
    if ('wakeLock' in navigator) {
      navigator.wakeLock.request('screen').then(function (l) { lock = l; }).catch(function () {});
    }
  });

  function hide() {
    box.hidden = true;
    if (lock) { lock.release().catch(function () {}); lock = null; }
  }

  box.addEventListener('click', hide);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hide(); } });
})();
</script>
