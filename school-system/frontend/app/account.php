<?php
/** ملف ولي الأمر. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$user     = wp_get_current_user();
$guardian = SCH_Guardians::by_user(get_current_user_id());

/* الأبناء يصلون مع كل شاشة من الموجّه — يظهر عددهم في سطر الترويسة
   وفي صفّ «أبنائي»، فلا يُستعلَم عنهم مرّتين في الشاشة الواحدة. */
$sch_kids = (array) ($sch_data['children'] ?? []);
?>

<h1 class="p-h1"><?php esc_html_e('حسابي', 'school-system'); ?></h1>

<!-- ترويسة الملف: صورة قابلة للتبديل + الاسم + صفته وعدد أبنائه -->
<div class="p-prof">
    <form method="post" enctype="multipart/form-data" id="p-av">
        <?php wp_nonce_field('sch_app_avatar', '_sch_nonce'); ?>
        <input type="hidden" name="sch_app_action" value="upload_avatar">

        <?php $sch_av = SCH_App::avatar_url(); ?>
        <label class="p-av" for="p-av-file">
            <?php if ($sch_av !== '') : ?>
                <img id="p-av-img" src="<?php echo esc_url($sch_av); ?>" alt="" width="72" height="72">
            <?php else : ?>
                <?php echo sch_avatar_svg(mb_substr((string) $user->display_name, 0, 1), 72); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php endif; ?>
            <span class="p-av__cam"><?php echo sch_icon('pen', 12); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </label>

        <input id="p-av-file" type="file" name="avatar" accept="image/jpeg,image/png" hidden
               aria-label="<?php esc_attr_e('الصورة الشخصية', 'school-system'); ?>">
    </form>

    <b class="p-prof__name"><?php echo esc_html((string) $user->display_name); ?></b>
    <div class="p-prof__meta">
        <span class="p-prof__note">
            <?php
            echo esc_html(__('وليّ أمر', 'school-system') . ' · ' . sprintf(
                /* translators: %s: عدد الأبناء */
                _n('%s ابن', '%s أبناء', count($sch_kids), 'school-system'),
                number_format_i18n(count($sch_kids))
            ));
            ?>
        </span>
    </div>
</div>

<?php $sch_pk = SCH_Custody::pickers_summary(get_current_user_id()); ?>

<!-- بطاقة واحدة تجمع صفوف الحساب: الفواصل شعرة لا فجوة بين بطاقات -->
<div class="p-set">

    <?php /* «بياناتي» تُفتح في مكانها لا في شاشة أخرى: ثلاثة أسطر ورقمُ
             وليّ الأمر لا تستحقّ انتقالًا وعودة، ومن جاء ليقرأ رقمه
             يقرأه بضغطة واحدة. */ ?>
    <details class="p-set__acc">
        <summary class="p-set__row">
            <span class="p-set__i p-set__i--pri"><?php echo sch_icon('user', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <span class="p-set__t"><?php esc_html_e('بياناتي', 'school-system'); ?></span>
            <span class="p-set__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </summary>

        <div class="p-set__body">
            <div class="p-set__kv">
                <span class="p-set__k"><?php esc_html_e('الجوال', 'school-system'); ?></span>
                <span class="p-set__v p-nm"><?php echo esc_html((string) ($guardian->phone ?? '—')); ?></span>
            </div>
            <div class="p-set__kv">
                <span class="p-set__k"><?php esc_html_e('الهوية', 'school-system'); ?></span>
                <span class="p-set__v p-nm"><?php echo esc_html((string) ($guardian->national_id ?? '—')); ?></span>
            </div>
            <div class="p-set__kv">
                <span class="p-set__k"><?php esc_html_e('المدينة', 'school-system'); ?></span>
                <span class="p-set__v"><?php echo esc_html((string) ($guardian->city ?? '—')); ?></span>
            </div>
            <?php if ($guardian && $guardian->parent_no) : ?>
                <div class="p-set__kv">
                    <span class="p-set__k"><?php esc_html_e('رقم وليّ الأمر', 'school-system'); ?></span>
                    <span class="p-set__v p-nm"><?php echo esc_html((string) $guardian->parent_no); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </details>

    <?php if ($sch_kids !== []) : ?>
        <details class="p-set__acc">
            <summary class="p-set__row">
                <span class="p-set__i p-set__i--ok"><?php echo sch_icon('users', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <span class="p-set__t"><?php esc_html_e('أبنائي', 'school-system'); ?></span>
                <span class="p-set__v p-nm"><?php echo esc_html(number_format_i18n(count($sch_kids))); ?></span>
                <span class="p-set__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            </summary>

            <div class="p-set__body">
                <?php foreach ($sch_kids as $sch_kid) : ?>
                    <?php $sch_cls = SCH_Students::current_class((int) $sch_kid->id); ?>
                    <a class="p-set__kv p-set__kid p-tap" href="<?php echo esc_url(SCH_App::url('child', (int) $sch_kid->id)); ?>">
                        <span class="p-set__k"><?php echo esc_html((string) $sch_kid->full_name); ?></span>
                        <span class="p-set__v"><?php echo esc_html($sch_cls ? SCH_Classes::label($sch_cls) : ''); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <?php /* الصفّ البارز (`--on`): أخطر صفٍّ في الشاشة، وسطحه أغمق درجةً
             فتقع عليه العين أوّلًا. والرقم في الصفّ يغني عن فتح الشاشة
             للاطمئنان — ومن رأى تفويضًا ساريًا لا يتذكّره فتحها لسبب. */ ?>
    <a class="p-set__row p-set__row--on p-tap" href="<?php echo esc_url(SCH_App::url('pickers')); ?>">
        <span class="p-set__i p-set__i--bad"><?php echo sch_icon('shield', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-set__t">
            <b><?php esc_html_e('من يحقّ له استلام أبنائي', 'school-system'); ?></b>
            <em>
                <?php
                $sch_pk_txt = sprintf(
                    /* translators: %s: عدد المخوَّلين */
                    _n('%s مخوَّل', '%s مخوَّلين', $sch_pk['people'], 'school-system'),
                    number_format_i18n($sch_pk['people'])
                );

                if ($sch_pk['temp'] > 0) {
                    $sch_pk_txt .= ' · ' . sprintf(
                        /* translators: %s: عدد التفويضات المؤقّتة */
                        _n('تفويض مؤقّت واحد نشط', '%s تفويضات مؤقّتة نشطة', $sch_pk['temp'], 'school-system'),
                        number_format_i18n($sch_pk['temp'])
                    );
                }

                echo esc_html($sch_pk_txt);
                ?>
            </em>
        </span>
        <span class="p-set__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
    </a>

    <?php /* الإشعار الفوري: الإذن يُطلب من ضغطة صاحب الجهاز — المتصفح يحجب
             الطلب التلقائي، ومن رفض مرة لا يُسأل ثانية أبدًا. */ ?>
    <?php if (SCH_Push::ready()) : ?>
        <button type="button" class="p-set__row p-tap" data-sch-push>
            <span class="p-set__i p-set__i--warn"><?php echo sch_icon('bell', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <span class="p-set__t" data-sch-push-label><?php esc_html_e('الإشعارات', 'school-system'); ?></span>
            <span class="p-set__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </button>
    <?php endif; ?>

    <?php /* اللغة صفٌّ يُخبر ولا يفتح: العربية هي اللغة الوحيدة المبنيّة
             اليوم، وسهمٌ لا يقود إلى شيء يُعلّم أن الأسهم لا تقود. */ ?>
    <div class="p-set__row p-set__row--flat">
        <span class="p-set__i p-set__i--slate"><?php echo sch_icon('book', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-set__t"><?php esc_html_e('اللغة', 'school-system'); ?></span>
        <span class="p-set__v">
            <?php echo esc_html(
                str_starts_with((string) get_locale(), 'ar')
                    ? __('العربية', 'school-system')
                    : __('English', 'school-system')
            ); ?>
        </span>
    </div>

    <?php /* `.p-theme` عقدٌ مع سكربت القالب: هو ما يلتقطه `closest`.
             ولا مقاس على الصنف — الأيقونتان تتبادلان بقاعدة `:root[data-theme]`
             القائمة، والمفتاح يتبع السمة نفسها فلا حالة ثانية تُحفظ. */ ?>
    <button type="button" class="p-set__row p-theme p-tap">
        <span class="p-set__i p-set__i--slate" aria-hidden="true">
            <svg class="p-theme__moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8 8 0 0 1 9.5 4a8.2 8.2 0 1 0 10.5 10.5z"/></svg>
            <svg class="p-theme__sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </span>
        <span class="p-set__t"><?php esc_html_e('الوضع الليلي', 'school-system'); ?></span>
        <span class="p-sw" aria-hidden="true"><span class="p-sw__k"></span></span>
    </button>

    <?php /* كلمة المرور صفٌّ مطويّ لا نموذجٌ مفتوح: الحقول الفارغة توحي
             بعملٍ مطلوب، ومن فتح «حسابي» غالبًا جاء ليقرأ لا ليكتب.
             وموضعها آخر البطاقة — تُغيَّر مرّةً في العام لا كل يوم. */ ?>
    <details class="p-set__acc">
        <summary class="p-set__row">
            <span class="p-set__i p-set__i--slate"><?php echo sch_icon('lock', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <span class="p-set__t"><?php esc_html_e('كلمة المرور', 'school-system'); ?></span>
            <span class="p-set__e"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        </summary>

        <form method="post" class="p-set__body">
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
    </details>
</div>

<!-- الخروج بطاقة وحده: فعلٌ لا إعداد، وفصله يمنع ضغطة عابرة -->
<div class="p-set p-set--solo">
    <a class="p-set__row p-set__row--out p-tap" href="<?php echo esc_url(SCH_App::url('logout')); ?>">
        <span class="p-set__i p-set__i--bad"><?php echo sch_icon('logout', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-set__t"><?php esc_html_e('تسجيل الخروج', 'school-system'); ?></span>
    </a>
</div>

<p class="p-ver">
    <?php
    /* الاسم من حزمة «الأسرة» نفسها لا نصًّا مكتوبًا هنا: يوم يتغيّر اسم
       التطبيق يتغيّر في مكان واحد. والرقم يُطلب في مكالمة دعم فيُقرأ. */
    echo esc_html(sprintf(
        /* translators: %1$s: اسم التطبيق، %2$s: رقم الإصدار */
        __('%1$s · الإصدار %2$s', 'school-system'),
        (string) (SCH_Apps::packs()['family']['short'] ?? get_bloginfo('name')),
        /* رقم الإصدار ليس عددًا فلا يمرّ بـ`number_format_i18n`، ولا تاريخًا
           فلا يمرّ بـ`wp_date` — فيبقى وحده لاتينيًّا في شاشة كلّها هندية. */
        sch_ar_digits(SCH_VERSION)
    ));
    ?>
</p>

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
      img.width = 72; img.height = 72; img.alt = '';
      if (svg && svg.parentNode === label) { label.replaceChild(img, svg); }
      else { label.insertBefore(img, label.firstChild); }
    }

    if (img) { img.src = URL.createObjectURL(file); }
    if (label) { label.classList.add('is-saving'); }

    form.submit();
  });
}());
</script>
