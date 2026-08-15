<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

$id       = (int) ($sch_data['id'] ?? 0);
$guardian = SCH_Guardians::get($id);

if (!$guardian) {
    echo '<div class="sch-empty"><strong>' . esc_html__('ولي الأمر غير موجود', 'school-system') . '</strong></div>';
    return;
}

$user     = get_user_by('id', (int) $guardian->user_id);
$name     = $user instanceof WP_User ? $user->display_name : '';
$children = SCH_Guardians::children_of_guardian((int) $guardian->user_id);
?>
<a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('guardians')); ?>"><?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e('كل أولياء الأمور', 'school-system'); ?></a>

<div class="sch-idcard">
    <div class="sch-idcard__head">
        <span class="sch-idcard__school"><?php echo esc_html($name); ?></span>
        <span class="sch-idcard__doc"><?php esc_html_e('ملف ولي أمر', 'school-system'); ?></span>
    </div>

    <div class="sch-idcard__body sch-idcard__body--wide">
        <dl class="sch-idcard__fields">
            <div><dt><?php esc_html_e('رقم ولي الأمر', 'school-system'); ?></dt><dd class="sch-mono" dir="ltr"><?php echo esc_html($guardian->parent_no ?: '—'); ?></dd></div>
            <div><dt><?php esc_html_e('رقم الهوية', 'school-system'); ?></dt><dd class="sch-mono" dir="ltr"><?php echo esc_html($guardian->national_id ?: '—'); ?></dd></div>
            <div><dt><?php esc_html_e('الجوال', 'school-system'); ?></dt><dd class="sch-mono" dir="ltr"><?php echo esc_html($guardian->phone ?: '—'); ?></dd></div>
            <div><dt><?php esc_html_e('جوال بديل', 'school-system'); ?></dt><dd class="sch-mono" dir="ltr"><?php echo esc_html($guardian->alt_phone ?: '—'); ?></dd></div>
            <div><dt><?php esc_html_e('البريد', 'school-system'); ?></dt><dd dir="ltr"><?php echo esc_html($user && !str_ends_with((string) $user->user_email, '@guardian.local') ? $user->user_email : '—'); ?></dd></div>
            <div><dt><?php esc_html_e('المدينة', 'school-system'); ?></dt><dd><?php echo esc_html($guardian->city ?: '—'); ?></dd></div>
            <div><dt><?php esc_html_e('العنوان', 'school-system'); ?></dt><dd><?php echo esc_html($guardian->address ?: '—'); ?></dd></div>
            <div><dt><?php esc_html_e('جهة العمل', 'school-system'); ?></dt><dd><?php echo esc_html($guardian->workplace ?: '—'); ?></dd></div>
            <div><dt><?php esc_html_e('اسم الدخول للتطبيق', 'school-system'); ?></dt><dd class="sch-mono" dir="ltr"><?php echo esc_html($user->user_login ?? '—'); ?></dd></div>
            <div><dt><?php esc_html_e('عدد الأبناء', 'school-system'); ?></dt><dd><?php echo esc_html(number_format_i18n(count($children))); ?></dd></div>
        </dl>
    </div>
</div>

<!-- إضافة ابن بالرقم الأكاديمي -->
<div class="sch-card">
    <h2><?php esc_html_e('إضافة ابن', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('اكتب الرقم الأكاديمي للطالب — يظهر لك اسمه وصفه للتأكد قبل الربط.', 'school-system'); ?></p>

    <div class="sch-lookup">
        <div class="sch-toolbar">
            <input type="text" id="sch-lookup-no" dir="ltr" inputmode="numeric"
                   placeholder="<?php esc_attr_e('14470001', 'school-system'); ?>" autocomplete="off">
            <button type="button" class="sch-btn sch-btn--quiet" id="sch-lookup-btn"><?php esc_html_e('بحث', 'school-system'); ?></button>
        </div>

        <div id="sch-lookup-result" class="sch-lookup__result" hidden></div>

        <form method="post" id="sch-link-form" hidden>
            <?php wp_nonce_field('sch_add_child', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="add_child">
            <input type="hidden" name="academic_no" id="sch-link-no">

            <div class="sch-toolbar">
                <select name="relation" aria-label="<?php esc_attr_e('صلة القرابة', 'school-system'); ?>">
                    <?php foreach (SCH_Guardians::RELATIONS as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="sch-check">
                    <input type="checkbox" name="is_primary" value="1" checked>
                    <?php esc_html_e('ولي الأمر الأساسي', 'school-system'); ?>
                </label>
                <button type="submit" class="sch-btn"><?php esc_html_e('ربط الطالب', 'school-system'); ?></button>
            </div>
        </form>
    </div>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('الأبناء', 'school-system'); ?></h2>

    <?php if ($children === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا يوجد أبناء مرتبطون', 'school-system'); ?></strong>
            <?php esc_html_e('بلا ربط لن يرى ولي الأمر شيئًا في التطبيق.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الرقم الأكاديمي', 'school-system'); ?></th>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصف', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصلة', 'school-system'); ?></th>
                    <th><?php esc_html_e('أساسي', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($children as $child) :
                    $class = SCH_Students::current_class((int) $child->id); ?>
                    <tr>
                        <td class="sch-mono" dir="ltr"><?php echo esc_html($child->academic_no ?: '—'); ?></td>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(SCH_Dashboard::url('students', (int) $child->id)); ?>">
                                <?php echo esc_html(SCH_Enrollment::full_name($child)); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($class ? SCH_Classes::label($class) : '—'); ?></td>
                        <td><?php echo esc_html(SCH_Guardians::relation_label($child->relation)); ?></td>
                        <td><?php echo $child->is_primary ? '<span class="sch-badge sch-badge--ok">' . esc_html__('نعم', 'school-system') . '</span>' : '—'; ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('<?php esc_attr_e('فك ارتباط هذا الطالب؟', 'school-system'); ?>');">
                                <?php wp_nonce_field('sch_unlink_guardian', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="unlink_guardian">
                                <input type="hidden" name="guardian_user_id" value="<?php echo esc_attr((string) $guardian->user_id); ?>">
                                <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $child->id); ?>">
                                <button class="sch-link-danger"><?php esc_html_e('فك', 'school-system'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('تعديل البيانات', 'school-system'); ?></h2>

    <form method="post">
        <?php wp_nonce_field('sch_update_guardian', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="update_guardian">

        <div class="sch-grid">
            <div class="sch-field">
                <label for="e-phone"><?php esc_html_e('رقم الجوال', 'school-system'); ?></label>
                <input id="e-phone" type="tel" name="phone" dir="ltr" value="<?php echo esc_attr((string) ($guardian->phone ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="e-alt"><?php esc_html_e('جوال بديل', 'school-system'); ?></label>
                <input id="e-alt" type="tel" name="alt_phone" dir="ltr" value="<?php echo esc_attr((string) ($guardian->alt_phone ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="e-nid"><?php esc_html_e('رقم الهوية', 'school-system'); ?></label>
                <input id="e-nid" type="text" name="national_id" dir="ltr" value="<?php echo esc_attr((string) ($guardian->national_id ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="e-city"><?php esc_html_e('المدينة', 'school-system'); ?></label>
                <input id="e-city" type="text" name="city" value="<?php echo esc_attr((string) ($guardian->city ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="e-addr"><?php esc_html_e('العنوان', 'school-system'); ?></label>
                <input id="e-addr" type="text" name="address" value="<?php echo esc_attr((string) ($guardian->address ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="e-work"><?php esc_html_e('جهة العمل', 'school-system'); ?></label>
                <input id="e-work" type="text" name="workplace" value="<?php echo esc_attr((string) ($guardian->workplace ?? '')); ?>">
            </div>
        </div>

        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('حفظ التعديلات', 'school-system'); ?></button>
    </form>
</div>

<script>
(function () {
  var api      = <?php echo wp_json_encode(esc_url_raw(rest_url(SCH_API_NS . '/students/lookup'))); ?>;
  var nonce    = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
  var guardian = <?php echo wp_json_encode((int) $guardian->user_id); ?>;

  var input  = document.getElementById('sch-lookup-no');
  var btn    = document.getElementById('sch-lookup-btn');
  var box    = document.getElementById('sch-lookup-result');
  var form   = document.getElementById('sch-link-form');
  var hidden = document.getElementById('sch-link-no');

  var T = {
    searching: <?php echo wp_json_encode(__('جارٍ البحث…', 'school-system')); ?>,
    notFound:  <?php echo wp_json_encode(__('لا يوجد طالب بهذا الرقم. تأكد من الرقم الأكاديمي.', 'school-system')); ?>,
    linked:    <?php echo wp_json_encode(__('هذا الطالب مرتبط بولي الأمر مسبقًا.', 'school-system')); ?>,
    failed:    <?php echo wp_json_encode(__('تعذّر البحث. تحقق من الاتصال.', 'school-system')); ?>,
    academic:  <?php echo wp_json_encode(__('الرقم الأكاديمي', 'school-system')); ?>,
    klass:     <?php echo wp_json_encode(__('الصف', 'school-system')); ?>,
    birth:     <?php echo wp_json_encode(__('تاريخ الميلاد', 'school-system')); ?>
  };

  function message(text, bad) {
    box.hidden = false;
    box.className = 'sch-lookup__result' + (bad ? ' is-bad' : '');
    box.textContent = text;
    form.hidden = true;
  }

  function card(data) {
    box.hidden = false;
    box.className = 'sch-lookup__result is-found';
    box.innerHTML = '';

    var name = document.createElement('strong');
    name.textContent = data.name;
    box.appendChild(name);

    [[T.academic, data.academic_no], [T.klass, data.class || '—'], [T.birth, data.birth_date || '—']]
      .forEach(function (pair) {
        var row = document.createElement('span');
        var k = document.createElement('em');
        k.textContent = pair[0];
        row.appendChild(k);
        row.appendChild(document.createTextNode(' ' + pair[1]));
        box.appendChild(row);
      });

    if (data.linked) {
      var warn = document.createElement('span');
      warn.className = 'sch-lookup__warn';
      warn.textContent = T.linked;
      box.appendChild(warn);
      form.hidden = true;
      return;
    }

    hidden.value = data.academic_no;
    form.hidden = false;
  }

  function lookup() {
    var value = input.value.trim();
    if (!value) { return; }

    message(T.searching, false);

    fetch(api + '?no=' + encodeURIComponent(value) + '&guardian_user_id=' + guardian, {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': nonce }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) { data && data.found ? card(data) : message(T.notFound, true); })
      .catch(function () { message(T.failed, true); });
  }

  btn.addEventListener('click', lookup);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); lookup(); }
  });
})();
</script>
