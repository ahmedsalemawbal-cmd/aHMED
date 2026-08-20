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
$year   = SCH_Years::current();

/* اسم الشعبة يُطبع في موضعين (شارة البطاقة وسطر ملء الشاشة) فيُحسب مرّة */
$class_label = $class ? SCH_Classes::label($class) : __('بلا شعبة', 'school-system');

/* سطر الترويسة الثاني: العام الجاري إن كان معرَّفًا، وإلا الوصف وحده —
   ولا نخترع سنةً حين لا تكون في القاعدة. */
$year_name = (string) ($year->name ?? '');
$kind      = $year_name !== ''
    /* translators: %s: اسم السنة الدراسية */
    ? sprintf(__('بطاقة طالب · العام %s', 'school-system'), $year_name)
    : __('بطاقة طالب', 'school-system');

/* «الحالة الآن» نغمتها من الحالة نفسها لا ثابتة: نقطة خضراء دائمة تكذب
   نصف اليوم — الطفل في الباص أو غادر مبكرًا وهي تقول «في المدرسة». */
$state = (string) ($student->custody_state ?: 'home');
$tone  = match ($state) {
    'at_school'  => 'ok',
    'on_bus'     => 'pri',
    'left_early' => 'warn',
    default      => 'mute',
};
?>

<h1 class="p-h1"><?php esc_html_e('البطاقة', 'school-system'); ?></h1>

<article class="p-id">
    <header class="p-id__top">
        <span class="p-id__seal">
            <?php echo sch_icon('home', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </span>
        <span class="p-id__ttl">
            <b><?php echo esc_html($school); ?></b>
            <em><?php echo esc_html($kind); ?></em>
        </span>
    </header>

    <div class="p-id__who">
        <span class="p-id__pic">
            <?php if ($photo) : ?>
                <img src="<?php echo esc_url($photo); ?>" alt="" width="66" height="66" loading="lazy">
            <?php else : ?>
                <?php echo sch_avatar_svg(mb_substr((string) $student->full_name, 0, 1), 66); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php endif; ?>
        </span>

        <span class="p-id__name">
            <b><?php echo esc_html(SCH_Enrollment::full_name($student)); ?></b>
            <?php if ($student->name_en) : ?>
                <em><?php echo esc_html($student->name_en); ?></em>
            <?php endif; ?>
            <span class="p-id__cls"><?php echo esc_html($class_label); ?></span>
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
            <dd class="p-id__live p-id__live--<?php echo esc_attr($tone); ?>"><?php echo esc_html(SCH_Custody::state_label($student->custody_state)); ?></dd>
        </div>
    </dl>

    <?php /* الزرّ على الرمز وحده: كان يغطّي البطاقة كاملةً فتفتح من أي حقل */ ?>
    <div class="p-id__qr">
        <span class="p-id__stamp">
            <span class="p-qr">
                <?php echo SCH_QR::svg($token, 4, 1); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php if ($photo) : ?>
                    <img class="p-qr__face" src="<?php echo esc_url($photo); ?>" alt="" width="34" height="34" loading="lazy">
                <?php endif; ?>
            </span>
            <?php /* بعد الرمز لا قبله: عنصران موضَّعان بلا z-index يفوز آخرهما،
                     والزرّ قبل الرمز يعني أن كل نقرة تقع على الرمز لا عليه. */ ?>
            <button type="button" class="p-id__open" id="p-open-qr"
                    aria-label="<?php esc_attr_e('عرض الرمز بملء الشاشة', 'school-system'); ?>"></button>
        </span>
        <span class="p-id__hint"><?php esc_html_e('اضغط لعرضه كاملًا', 'school-system'); ?></span>
    </div>
</article>

<?php /* ملء الشاشة: أرضية داكنة ثابتة لا تتبع الوضع — الرمز يُمسح بماسح
         بوابة، والرمز المعكوس في الوضع الليلي لا يُقرأ. */ ?>
<div class="p-full" id="p-full" hidden>
    <div class="p-full__box">
        <div class="p-full__mid">
            <span class="p-full__hd">
                <b class="p-full__n"><?php echo esc_html(SCH_Enrollment::full_name($student)); ?></b>
                <em class="p-full__sub"><?php echo esc_html($class_label . ' · ' . $school); ?></em>
            </span>

            <span class="p-qr p-qr--big">
                <?php echo SCH_QR::svg($token, 8, 1); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php if ($photo) : ?>
                    <img class="p-qr__face" src="<?php echo esc_url($photo); ?>" alt="" width="70" height="70">
                <?php endif; ?>
            </span>

            <span class="p-full__id">
                <span class="p-full__lbl"><?php esc_html_e('الرقم الأكاديمي', 'school-system'); ?></span>
                <span class="p-full__no p-nm"><?php echo esc_html($student->academic_no ?: ''); ?></span>
            </span>
        </div>

        <span class="p-full__x"><?php esc_html_e('اضغط للإغلاق', 'school-system'); ?></span>
    </div>
</div>

<script>
(function () {
  var box = document.getElementById('p-full');
  if (!box) { return; }

  var lock = null;
  var armed = false;

  function show(e) {
    e.preventDefault();
    e.stopPropagation();
    box.hidden = false;

    /* **اللمسة الواحدة كانت تفتح وتُغلق.** الطبقة تظهر تحت الإصبع، فالنقر
       الذي يصطنعه المتصفح بعد رفعه يقع عليها فتُغلق في الجزء نفسه من الثانية
       — فتبدو كأنها لا تعمل أصلًا. لا نُسلّح الإغلاق إلا بعد أن ترتفع اليد. */
    armed = false;
    window.setTimeout(function () { armed = true; }, 350);

    /* الشاشة تُضاء لأقصاها: ماسح البوابة يفشل على شاشة خافتة */
    if ('wakeLock' in navigator) {
      navigator.wakeLock.request('screen').then(function (l) { lock = l; }).catch(function () {});
    }
  }

  function hide() {
    box.hidden = true;
    armed = false;
    if (lock) { lock.release().catch(function () {}); lock = null; }
  }

  /* بالتفويض: يعمل مهما تغيّر ترتيب العناصر أو تأخّر بناؤها */
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('#p-open-qr')) { show(e); }
  });

  box.addEventListener('click', function () { if (armed) { hide(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden) { hide(); } });
})();
</script>
