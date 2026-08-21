<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$role_filter = isset($_GET['role']) ? sanitize_text_field((string) $_GET['role']) : '';
$only_new    = isset($_GET['unprinted']);

$all   = SCH_Staff::badges($role_filter !== '' ? ['role' => $role_filter] : []);
$items = $all;

if ($only_new) {
    $items = array_values(array_filter($items, static fn (object $e): bool => empty($e->badge_printed_at)));
}

$unprinted = count(array_filter($all, static fn (object $e): bool => empty($e->badge_printed_at)));
$school    = sch_settings('school_name', get_bloginfo('name'));

// الأدوار الموجودة فعلًا بين الموظفين — لبناء مرشّح لا يعرض أدوارًا فارغة.
$present_roles = [];
foreach ($all as $e) {
    if ($e->role !== '' && !isset($present_roles[$e->role])) {
        $present_roles[$e->role] = SCH_Staff::role_label($e->role);
    }
}
asort($present_roles);
?>

<h1 class="sch-title"><?php echo sch_icon('badge', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('بطاقات الموظفين', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('بطاقة كبطاقة الطالب — يمسحها الموظف عند البوابة فيُسجَّل حضوره. الباركود يحمل رمزًا عشوائيًا لا رقمه الوظيفي.', 'school-system'); ?></p>

<div class="sch-card sch-noprint">
    <form method="get" class="sch-toolbar">
        <input type="hidden" name="sch_section" value="staff-badges">

        <label class="sch-field">
            <span><?php esc_html_e('الدور', 'school-system'); ?></span>
            <select name="role" aria-label="<?php esc_attr_e('الدور', 'school-system'); ?>">
                <option value=""><?php esc_html_e('كل الأدوار', 'school-system'); ?></option>
                <?php foreach ($present_roles as $r => $label) : ?>
                    <option value="<?php echo esc_attr($r); ?>" <?php selected($role_filter, $r); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض', 'school-system'); ?></button>

        <?php if ($unprinted > 0) : ?>
            <a class="sch-chipbtn<?php echo $only_new ? ' is-on' : ''; ?>"
               href="<?php echo esc_url($only_new
                   ? add_query_arg(array_filter(['role' => $role_filter]), SCH_Dashboard::url('staff-badges'))
                   : add_query_arg(array_filter(['role' => $role_filter, 'unprinted' => 1]), SCH_Dashboard::url('staff-badges'))); ?>">
                <?php echo esc_html(sprintf(
                    /* translators: %d: عدد البطاقات */
                    __('غير المطبوعة فقط (%d)', 'school-system'),
                    $unprinted
                )); ?>
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if ($items === []) : ?>
    <div class="sch-blank">
        <span class="sch-blank__ic"><?php echo sch_icon('badge', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <strong><?php esc_html_e('لا بطاقات هنا', 'school-system'); ?></strong>
        <p><?php echo esc_html($only_new
            ? __('كل بطاقات الموظفين طُبعت من قبل.', 'school-system')
            : __('لا موظفين نشطين لعرض بطاقاتهم.', 'school-system')); ?></p>
    </div>
<?php else : ?>

    <!-- شريط التحديد: دفعة أول العام تُطبع كلها، والموظف الجديد بطاقته وحده -->
    <div class="sch-card sch-noprint">
        <div class="sch-pickbar">
            <label class="sch-ck"><input type="checkbox" id="sch-all"><span></span></label>
            <span><?php esc_html_e('تحديد الكل', 'school-system'); ?></span>
            <b id="sch-count" class="sch-pickbar__n">٠</b>

            <span class="sch-pickbar__acts">
                <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" id="sch-print-form">
                    <?php wp_nonce_field('sch_print_staff_badges', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="print_staff_badges">
                    <input type="hidden" name="ids" id="sch-ids">
                    <button class="sch-btn" id="sch-print" disabled>
                        <?php esc_html_e('طباعة المحدد', 'school-system'); ?>
                    </button>
                </form>
            </span>
        </div>
    </div>

    <div class="sch-badges" id="sch-badges">
        <?php foreach ($items as $e) :
            $token     = (string) ($e->badge_token ?? '');
            $role_name = SCH_Staff::role_label((string) $e->role);
            $printed   = !empty($e->badge_printed_at); ?>

            <label class="sch-badge2 sch-badge2--staff<?php echo $printed ? ' is-printed' : ''; ?>">
                <input type="checkbox" class="sch-badge2__pick" value="<?php echo esc_attr((string) $e->id); ?>">

                <span class="sch-badge2__card">
                    <span class="sch-badge2__blob" aria-hidden="true"></span>
                    <span class="sch-badge2__glass" aria-hidden="true"></span>
                    <span class="sch-badge2__ribbon" aria-hidden="true"><?php esc_html_e('موظف', 'school-system'); ?></span>

                    <span class="sch-badge2__in">
                        <span class="sch-badge2__school"><?php echo esc_html($school); ?></span>

                        <span class="sch-badge2__photo">
                            <?php echo sch_avatar_svg(mb_substr((string) $e->display_name, 0, 1), 112); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </span>

                        <b class="sch-badge2__name"><?php echo esc_html((string) $e->display_name); ?></b>
                        <span class="sch-badge2__class sch-badge2__class--staff">
                            <?php echo esc_html($role_name); ?>
                        </span>
                        <?php if (!empty($e->job_title)) : ?>
                            <span class="sch-badge2__job"><?php echo esc_html((string) $e->job_title); ?></span>
                        <?php endif; ?>

                        <span class="sch-badge2__qr">
                            <?php if ($token !== '') : ?>
                                <?php echo SCH_QR::svg($token, 3, 1); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <?php else : ?>
                                <span class="sch-badge2__noqr"><?php esc_html_e('لا رمز', 'school-system'); ?></span>
                            <?php endif; ?>
                        </span>

                        <span class="sch-badge2__no" dir="ltr"><?php echo esc_html((string) ($e->employee_no ?: '')); ?></span>
                    </span>
                </span>

                <?php if ($printed) : ?>
                    <span class="sch-badge2__flag sch-noprint">
                        <?php echo esc_html(sprintf(
                            /* translators: %s: التاريخ */
                            __('طُبعت %s', 'school-system'),
                            substr((string) $e->badge_printed_at, 0, 10)
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

      /* Shift + نقرة تحدد مدى */
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

      /* الطباعة أولًا ثم التعليم — إرسال النموذج ينقل الصفحة فلا تصل الطباعة أصلًا */
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
