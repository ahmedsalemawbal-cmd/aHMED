<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$classes  = SCH_Classes::list();
$class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
$only_new = isset($_GET['unprinted']);

$list = $class_id > 0
    ? SCH_Students::list(['status' => 'active', 'class_id' => $class_id, 'per_page' => 300])
    : ['items' => []];

$items = $list['items'];

if ($only_new) {
    $items = array_values(array_filter($items, static fn (object $s): bool => empty($s->badge_printed_at)));
}

$school     = sch_settings('school_name', get_bloginfo('name'));
$unprinted  = count(array_filter($list['items'], static fn (object $s): bool => empty($s->badge_printed_at)));
?>

<h1 class="sch-title"><?php echo sch_icon('badge', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('بطاقات الطلاب', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('الباركود يحمل رمزًا عشوائيًا لا الرقم الأكاديمي — لأن البطاقة تُرى في الشارع وتُصوَّر.', 'school-system'); ?></p>

<div class="sch-card sch-noprint">
    <form method="get" class="sch-toolbar">
        <?php foreach (['sch_section' => 'badges'] as $k => $v) : ?>
            <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>">
        <?php endforeach; ?>

        <select name="class_id" required aria-label="<?php esc_attr_e('الشعبة', 'school-system'); ?>">
            <option value=""><?php esc_html_e('اختر الشعبة…', 'school-system'); ?></option>
            <?php foreach ($classes as $c) : ?>
                <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class_id, (int) $c->id); ?>>
                    <?php echo esc_html(SCH_Classes::label($c)); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض', 'school-system'); ?></button>

        <?php if ($class_id > 0 && $unprinted > 0) : ?>
            <a class="sch-chipbtn<?php echo $only_new ? ' is-on' : ''; ?>"
               href="<?php echo esc_url($only_new
                   ? add_query_arg('class_id', $class_id, SCH_Dashboard::url('badges'))
                   : add_query_arg(['class_id' => $class_id, 'unprinted' => 1], SCH_Dashboard::url('badges'))); ?>">
                <?php echo esc_html(sprintf(
                    /* translators: %d: عدد البطاقات */
                    __('غير المطبوعة فقط (%d)', 'school-system'),
                    $unprinted
                )); ?>
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if ($class_id > 0 && $items === []) : ?>
    <div class="sch-blank">
        <span class="sch-blank__ic"><?php echo sch_icon('badge', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <strong><?php esc_html_e('لا بطاقات هنا', 'school-system'); ?></strong>
        <p><?php echo esc_html($only_new
            ? __('كل بطاقات هذه الشعبة طُبعت من قبل.', 'school-system')
            : __('لا طلاب في هذه الشعبة.', 'school-system')); ?></p>
    </div>
<?php endif; ?>

<?php if ($items !== []) : ?>

    <!-- شريط التحديد: دفعة أول العام تُطبع كلها، والطالب المتأخر بطاقته وحده -->
    <div class="sch-card sch-noprint">
        <div class="sch-pickbar">
            <label class="sch-ck"><input type="checkbox" id="sch-all"><span></span></label>
            <span><?php esc_html_e('تحديد الكل', 'school-system'); ?></span>
            <b id="sch-count" class="sch-pickbar__n">٠</b>

            <span class="sch-pickbar__acts">
                <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" id="sch-print-form">
                    <?php wp_nonce_field('sch_print_badges', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="print_badges">
                    <input type="hidden" name="ids" id="sch-ids">
                    <button class="sch-btn" id="sch-print" disabled>
                        <?php esc_html_e('طباعة المحدد', 'school-system'); ?>
                    </button>
                </form>
            </span>
        </div>
    </div>

    <div class="sch-badges" id="sch-badges">
        <?php foreach ($items as $s) :
            $token   = (string) ($s->badge_token ?: $s->qr_token);
            $photo   = $s->photo_file ? SCH_Dashboard::photo_url((int) $s->id) : '';
            $printed = !empty($s->badge_printed_at); ?>

            <label class="sch-badge2<?php echo $printed ? ' is-printed' : ''; ?>">
                <input type="checkbox" class="sch-badge2__pick" value="<?php echo esc_attr((string) $s->id); ?>">

                <span class="sch-badge2__card">
                    <span class="sch-badge2__blob" aria-hidden="true"></span>
                    <span class="sch-badge2__glass" aria-hidden="true"></span>

                    <span class="sch-badge2__in">
                        <span class="sch-badge2__school"><?php echo esc_html($school); ?></span>

                        <span class="sch-badge2__photo">
                            <?php if ($photo) : ?>
                                <img src="<?php echo esc_url($photo); ?>" alt="" width="112" height="112" loading="lazy">
                            <?php else : ?>
                                <?php echo sch_avatar_svg(mb_substr((string) $s->full_name, 0, 1), 112); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <?php endif; ?>
                        </span>

                        <b class="sch-badge2__name"><?php echo esc_html(SCH_Enrollment::full_name($s)); ?></b>
                        <span class="sch-badge2__class">
                            <?php echo esc_html(trim((string) $s->grade_level . ' / ' . (string) $s->section)); ?>
                        </span>

                        <span class="sch-badge2__qr">
                            <?php echo SCH_QR::svg($token, 3, 1); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <?php if ($photo) : ?>
                                <img class="sch-badge2__face" src="<?php echo esc_url($photo); ?>" alt="" width="26" height="26" loading="lazy">
                            <?php endif; ?>
                        </span>

                        <span class="sch-badge2__no" dir="ltr"><?php echo esc_html($s->academic_no ?: ''); ?></span>
                    </span>
                </span>

                <?php if ($printed) : ?>
                    <span class="sch-badge2__flag sch-noprint">
                        <?php echo esc_html(sprintf(
                            /* translators: %s: التاريخ */
                            __('طُبعت %s', 'school-system'),
                            substr((string) $s->badge_printed_at, 0, 10)
                        )); ?>
                    </span>
                <?php endif; ?>

                <span class="sch-badge2__tick sch-noprint"><?php echo sch_icon('check', 14); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <script>
    (function () {
      var wrap = document.getElementById('sch-badges');
      var all = document.getElementById('sch-all');
      var btn = document.getElementById('sch-print');
      var out = document.getElementById('sch-ids');
      var num = document.getElementById('sch-count');
      if (!wrap || !btn) { return; }

      var AR = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
      function ar(n) { return String(n).split('').map(function (d) { return AR[+d]; }).join(''); }

      function picks() {
        return Array.prototype.slice.call(wrap.querySelectorAll('.sch-badge2__pick:checked'));
      }

      function sync() {
        var on = picks();
        var total = wrap.querySelectorAll('.sch-badge2__pick').length;

        num.textContent = ar(on.length);
        btn.disabled = on.length === 0;
        out.value = on.map(function (c) { return c.value; }).join(',');

        wrap.querySelectorAll('.sch-badge2').forEach(function (b) {
          b.classList.toggle('is-picked', b.querySelector('.sch-badge2__pick').checked);
        });

        if (all) {
          all.checked = on.length > 0 && on.length === total;
          all.indeterminate = on.length > 0 && on.length < total;
        }
      }

      wrap.addEventListener('change', sync);

      if (all) {
        all.addEventListener('change', function () {
          wrap.querySelectorAll('.sch-badge2__pick').forEach(function (c) { c.checked = all.checked; });
          sync();
        });
      }

      /* Shift + نقرة تحدد مدى — خمسون بطاقة بضغطتين */
      var last = null;
      wrap.addEventListener('click', function (e) {
        var c = e.target.closest('.sch-badge2__pick');
        if (!c) { return; }

        if (e.shiftKey && last) {
          var arr = Array.prototype.slice.call(wrap.querySelectorAll('.sch-badge2__pick'));
          var a = arr.indexOf(last), b = arr.indexOf(c);
          arr.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (x) { x.checked = c.checked; });
          sync();
        }
        last = c;
      });

      /* الطباعة أولًا ثم التعليم: إرسال النموذج ينقل الصفحة فلا تصل
         لحظة الطباعة أصلًا. فنطبع في هذه الصفحة، ثم نُعلّم بعد إغلاق نافذة
         الطباعة — والتعليم يفشل لا يمنع الورقة من الخروج. */
      var form = document.getElementById('sch-print-form');

      form.addEventListener('submit', function (e) {
        e.preventDefault();

        var ids = picks().map(function (c) { return c.value; });
        if (!ids.length) { return; }

        wrap.querySelectorAll('.sch-badge2').forEach(function (b) {
          b.classList.toggle('is-skip', !b.querySelector('.sch-badge2__pick').checked);
        });

        out.value = ids.join(',');

        function afterPrint() {
          window.removeEventListener('afterprint', afterPrint);
          wrap.querySelectorAll('.sch-badge2').forEach(function (b) { b.classList.remove('is-skip'); });
          form.submit();
        }

        window.addEventListener('afterprint', afterPrint);

        /* شبكة أمان: متصفح لا يُطلق afterprint لا يترك التعليم معلّقًا */
        setTimeout(function () {
          if (form.dataset.sent !== '1') { afterPrint(); }
        }, 4000);

        setTimeout(function () { window.print(); }, 80);
      });

      sync();
    })();
    </script>
<?php endif; ?>
