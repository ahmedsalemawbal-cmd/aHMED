<?php
/**
 * نموذج تسجيل الطالب — جزء مشترك.
 *
 * يُستدعى من شاشة الطلاب داخل نافذة، ومن قسم «تسجيل طالب» مستقلًا.
 * فالنموذج واحد لا نسختان تتباعدان مع الوقت.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;
?>
<form method="post" enctype="multipart/form-data" class="sch-wizard" id="sch-wizard" novalidate>
    <?php wp_nonce_field('sch_register_student', '_sch_nonce'); ?>
    <input type="hidden" name="sch_action" value="register_student">

    <ol class="sch-steps-bar">
        <li class="is-active" data-step="1"><span>1</span><?php esc_html_e('بيانات الطالب', 'school-system'); ?></li>
        <li data-step="2"><span>2</span><?php esc_html_e('المستندات', 'school-system'); ?></li>
        <li data-step="3"><span>3</span><?php esc_html_e('بيانات إضافية', 'school-system'); ?></li>
        <li data-step="4"><span>4</span><?php esc_html_e('المراجعة', 'school-system'); ?></li>
    </ol>

    <!-- الخطوة 1 -->
    <section class="sch-card sch-step is-active" data-step="1">
        <h2><?php esc_html_e('بيانات الطالب', 'school-system'); ?></h2>

        <div class="sch-grid">
            <div class="sch-field">
                <label for="f-first"><?php esc_html_e('الاسم الأول', 'school-system'); ?> *</label>
                <input id="f-first" type="text" name="first_name" required data-review="<?php esc_attr_e('الاسم الأول', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="f-father"><?php esc_html_e('اسم الأب', 'school-system'); ?> *</label>
                <input id="f-father" type="text" name="father_name" required data-review="<?php esc_attr_e('اسم الأب', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="f-grand"><?php esc_html_e('اسم الجد', 'school-system'); ?> *</label>
                <input id="f-grand" type="text" name="grand_name" required data-review="<?php esc_attr_e('اسم الجد', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="f-family"><?php esc_html_e('اسم العائلة', 'school-system'); ?> *</label>
                <input id="f-family" type="text" name="family_name" required data-review="<?php esc_attr_e('اسم العائلة', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="f-en"><?php esc_html_e('الاسم بالإنجليزية', 'school-system'); ?> *</label>
                <input id="f-en" type="text" name="name_en" dir="ltr" required data-review="<?php esc_attr_e('الاسم بالإنجليزية', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="f-idtype"><?php esc_html_e('نوع الهوية', 'school-system'); ?> *</label>
                <select id="f-idtype" name="id_type" data-review="<?php esc_attr_e('نوع الهوية', 'school-system'); ?>">
                    <?php foreach (SCH_Enrollment::ID_TYPES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="f-nid"><?php esc_html_e('رقم الهوية', 'school-system'); ?> *</label>
                <input id="f-nid" type="text" name="national_id" dir="ltr" inputmode="numeric" pattern="\d{10}" maxlength="10" required data-review="<?php esc_attr_e('رقم الهوية', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="f-nat"><?php esc_html_e('الجنسية', 'school-system'); ?> *</label>
                <input id="f-nat" type="text" name="nationality" required data-review="<?php esc_attr_e('الجنسية', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="f-gender"><?php esc_html_e('الجنس', 'school-system'); ?> *</label>
                <select id="f-gender" name="gender" required data-review="<?php esc_attr_e('الجنس', 'school-system'); ?>">
                    <option value="male"><?php esc_html_e('ذكر', 'school-system'); ?></option>
                    <option value="female"><?php esc_html_e('أنثى', 'school-system'); ?></option>
                </select>
            </div>
            <div class="sch-field">
                <label for="f-birth"><?php esc_html_e('تاريخ الميلاد', 'school-system'); ?> *</label>
                <input id="f-birth" type="date" name="birth_date" required data-review="<?php esc_attr_e('تاريخ الميلاد', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="f-class"><?php esc_html_e('المرحلة والصف والفصل', 'school-system'); ?> *</label>
                <select id="f-class" name="class_id" required data-review="<?php esc_attr_e('الفصل', 'school-system'); ?>">
                    <?php foreach ($classes as $c) : ?>
                        <option value="<?php echo esc_attr((string) $c->id); ?>"><?php echo esc_html(SCH_Classes::label($c)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="f-photo"><?php esc_html_e('صورة الطالب', 'school-system'); ?> *</label>
                <input id="f-photo" type="file" name="photo" accept="image/jpeg,image/png" required>
            </div>
        </div>
    </section>

    <!-- الخطوة 2 -->
    <section class="sch-card sch-step" data-step="2">
        <h2><?php esc_html_e('المستندات', 'school-system'); ?></h2>
        <p class="sch-sub"><?php esc_html_e('JPG أو PNG أو PDF، وحتى 5 ميجابايت للملف. تُحفظ في مجلد محمي لا يُفتح برابط مباشر.', 'school-system'); ?></p>
        <div class="sch-grid">
            <?php foreach (SCH_Enrollment::DOC_TYPES as $slug => $label) : ?>
                <div class="sch-field">
                    <label for="d-<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?> *</label>
                    <input id="d-<?php echo esc_attr($slug); ?>" type="file" name="doc_<?php echo esc_attr($slug); ?>"
                           accept="image/jpeg,image/png,application/pdf" required>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- الخطوة 3 -->
    <section class="sch-card sch-step" data-step="3">
        <h2><?php esc_html_e('بيانات إضافية', 'school-system'); ?></h2>
        <p class="sch-sub"><?php esc_html_e('كلها اختيارية. البيانات الصحية يراها المدير والصحة المدرسية فقط، وكل اطلاع عليها يُسجَّل.', 'school-system'); ?></p>

        <?php if ($can_health) : ?>
            <div class="sch-grid">
                <div class="sch-field">
                    <label for="h-blood"><?php esc_html_e('فصيلة الدم', 'school-system'); ?></label>
                    <select id="h-blood" name="blood_type">
                        <option value=""><?php esc_html_e('غير محددة', 'school-system'); ?></option>
                        <?php foreach (SCH_Enrollment::BLOOD_TYPES as $bt) : ?>
                            <option value="<?php echo esc_attr($bt); ?>"><?php echo esc_html($bt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sch-field">
                    <label for="h-chronic"><?php esc_html_e('أمراض مزمنة', 'school-system'); ?></label>
                    <input id="h-chronic" type="text" name="chronic">
                </div>
                <div class="sch-field">
                    <label for="h-allergy"><?php esc_html_e('حساسية', 'school-system'); ?></label>
                    <input id="h-allergy" type="text" name="allergies">
                </div>
                <div class="sch-field">
                    <label for="h-needs"><?php esc_html_e('احتياجات خاصة', 'school-system'); ?></label>
                    <input id="h-needs" type="text" name="special_needs">
                </div>
            </div>
        <?php endif; ?>

        <div class="sch-grid">
            <div class="sch-field">
                <label for="t-route"><?php esc_html_e('الاشتراك في النقل', 'school-system'); ?></label>
                <select id="t-route" name="transport_route">
                    <option value=""><?php esc_html_e('غير مشترك', 'school-system'); ?></option>
                    <?php foreach ($routes as $r) : ?>
                        <option value="<?php echo esc_attr((string) $r->id); ?>"><?php echo esc_html($r->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="t-fee"><?php esc_html_e('رسوم النقل السنوية', 'school-system'); ?></label>
                <input id="t-fee" type="number" name="transport_fee" step="0.01" min="0" value="0">
            </div>
        </div>

        <div class="sch-field">
            <label for="f-notes"><?php esc_html_e('ملاحظات', 'school-system'); ?></label>
            <textarea id="f-notes" name="notes" rows="3"></textarea>
        </div>
    </section>

    <!-- الخطوة 4 -->
    <section class="sch-card sch-step" data-step="4">
        <h2><?php esc_html_e('مراجعة البيانات', 'school-system'); ?></h2>
        <p class="sch-sub"><?php esc_html_e('عند الحفظ يُولَّد الرقم الأكاديمي ورقم الطالب ورمز QR والملف الشخصي الدائم.', 'school-system'); ?></p>
        <div id="sch-review" class="sch-review"></div>
    </section>

    <div class="sch-wizard__nav">
        <button type="button" class="sch-btn sch-btn--quiet" id="sch-prev" hidden><?php esc_html_e('السابق', 'school-system'); ?></button>
        <button type="button" class="sch-btn" id="sch-next"><?php esc_html_e('التالي', 'school-system'); ?></button>
        <button type="submit" class="sch-btn" id="sch-save" hidden><?php esc_html_e('حفظ وتسجيل الطالب', 'school-system'); ?></button>
    </div>
</form>

<script>
(function () {
  var form = document.getElementById('sch-wizard');
  var steps = form.querySelectorAll('.sch-step');
  var bar = form.querySelectorAll('.sch-steps-bar li');
  var prev = document.getElementById('sch-prev');
  var next = document.getElementById('sch-next');
  var save = document.getElementById('sch-save');
  var review = document.getElementById('sch-review');
  var cur = 1;

  function show(n) {
    cur = n;
    steps.forEach(function (s) { s.classList.toggle('is-active', +s.dataset.step === n); });
    bar.forEach(function (b) {
      b.classList.toggle('is-active', +b.dataset.step === n);
      b.classList.toggle('is-done', +b.dataset.step < n);
    });
    prev.hidden = n === 1;
    next.hidden = n === 4;
    save.hidden = n !== 4;
    if (n === 4) { buildReview(); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function valid(n) {
    var section = form.querySelector('.sch-step[data-step="' + n + '"]');
    var fields = section.querySelectorAll('[required]');
    for (var i = 0; i < fields.length; i++) {
      if (!fields[i].checkValidity()) { fields[i].reportValidity(); return false; }
    }
    return true;
  }

  function buildReview() {
    review.innerHTML = '';
    form.querySelectorAll('[data-review]').forEach(function (el) {
      var val = el.tagName === 'SELECT' ? el.options[el.selectedIndex].text : el.value;
      var row = document.createElement('div');
      row.className = 'sch-review__row';
      var k = document.createElement('span');
      k.textContent = el.dataset.review;
      var v = document.createElement('strong');
      v.textContent = val || '—';
      row.appendChild(k); row.appendChild(v);
      review.appendChild(row);
    });

    var files = [];
    form.querySelectorAll('input[type="file"]').forEach(function (f) {
      if (f.files && f.files[0]) { files.push(f.files[0].name); }
    });
    var row = document.createElement('div');
    row.className = 'sch-review__row';
    var k = document.createElement('span');
    k.textContent = '<?php echo esc_js(__('الملفات المرفقة', 'school-system')); ?>';
    var v = document.createElement('strong');
    v.textContent = files.length ? files.length + ' <?php echo esc_js(__('ملف', 'school-system')); ?>' : '—';
    row.appendChild(k); row.appendChild(v);
    review.appendChild(row);
  }

  /* عند الحفظ نفحص كل الخطوات: لو نقص حقل ننتقل لخطوته ونُبرزه.
     بلا هذا يمنع المتصفح الإرسال بصمت لأن الحقل الناقص مخفي. */
  form.addEventListener('submit', function (e) {
    for (var n = 1; n <= 4; n++) {
      var sec = form.querySelector('.sch-step[data-step="' + n + '"]');
      if (!sec) { continue; }
      var flds = sec.querySelectorAll('[required]');
      for (var i = 0; i < flds.length; i++) {
        if (!flds[i].checkValidity()) {
          e.preventDefault();
          show(n);
          setTimeout(function (f) {
            return function () { f.focus(); f.reportValidity(); };
          }(flds[i]), 220);
          return;
        }
      }
    }
  });

  next.addEventListener('click', function () { if (valid(cur)) { show(cur + 1); } });
  prev.addEventListener('click', function () { show(cur - 1); });
  bar.forEach(function (b) {
    b.addEventListener('click', function () {
      var target = +b.dataset.step;
      if (target < cur || valid(cur)) { show(target); }
    });
  });

  show(1);
})();
</script>
