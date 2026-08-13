<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$student = SCH_Students::get($id);

if (!$student) {
    echo '<div class="scha-empty"><strong>' . esc_html__('الطالب غير موجود', 'school-system') . '</strong></div>';
    return;
}

$name     = SCH_Enrollment::full_name($student);
$photo    = SCH_Teacher::photo_url($student);
$class    = SCH_Students::current_class($id);
$previous = SCH_Nerve::previous_summary($id);
$notes    = SCH_Notes::of_student($id, 8);
?>
<a class="scha-back" href="<?php echo esc_url($class ? SCH_Teacher::url('klass', (int) $class->id) : SCH_Teacher::url()); ?>">‹ <?php esc_html_e('الفصل', 'school-system'); ?></a>

<div class="scha-card">
    <div class="scha-pupil__head">
        <?php if ($photo) : ?>
            <img src="<?php echo esc_url($photo); ?>" alt="" width="64" height="80">
        <?php endif; ?>
        <div>
            <strong class="scha-card__name"><?php echo esc_html($name); ?></strong>
            <span class="scha-card__sub"><?php echo esc_html($class ? SCH_Classes::label($class) : ''); ?></span>
            <span class="scha-card__sub"><?php echo esc_html(SCH_Custody::state_label($student->custody_state)); ?></span>
        </div>
    </div>
</div>

<?php if ($previous) : ?>
    <!-- الذاكرة عبر السنوات: ما تعلّمته معلمة العام الماضي لا يضيع في الصيف -->
    <div class="sch-memo">
        <span class="sch-memo__k">
            <?php echo esc_html(sprintf(
                /* translators: %s: اسم السنة الدراسية */
                __('من معلم العام السابق — %s', 'school-system'),
                (string) $previous->year_name
            )); ?>
        </span>
        <dl>
            <?php if ($previous->works) : ?>
                <div><dt><?php esc_html_e('ينفع معه', 'school-system'); ?></dt><dd><?php echo esc_html($previous->works); ?></dd></div>
            <?php endif; ?>
            <?php if ($previous->avoid) : ?>
                <div><dt><?php esc_html_e('تجنّب', 'school-system'); ?></dt><dd><?php echo esc_html($previous->avoid); ?></dd></div>
            <?php endif; ?>
            <?php if ($previous->notes) : ?>
                <div><dt><?php esc_html_e('ما يجب معرفته', 'school-system'); ?></dt><dd><?php echo esc_html($previous->notes); ?></dd></div>
            <?php endif; ?>
        </dl>
    </div>
<?php endif; ?>

<!-- تسجيل ملاحظة: تصنيف + سطر واحد. دور المعلم ينتهي هنا -->
<div class="scha-card">
    <span class="scha-card__label"><?php esc_html_e('تسجيل ملاحظة', 'school-system'); ?></span>

    <form method="post" id="scha-note-form">
        <?php wp_nonce_field('sch_tch_note', '_sch_nonce'); ?>
        <input type="hidden" name="sch_tch_action" value="note">
        <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
        <input type="hidden" name="category" id="scha-cat" value="positive">

        <div class="scha-cats" id="scha-cats">
            <?php foreach (SCH_Notes::CATEGORIES as $slug => $meta) : ?>
                <button type="button" class="scha-cat<?php echo $slug === 'positive' ? ' is-on' : ''; ?>"
                        data-cat="<?php echo esc_attr($slug); ?>">
                    <?php echo esc_html($meta[0]); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <label class="scha-field-label" for="scha-body"><?php esc_html_e('سطر واحد محدد', 'school-system'); ?></label>
        <input class="scha-input" id="scha-body" type="text" name="body" required
               placeholder="<?php esc_attr_e('مثال: شرح الدرس لزملائه اليوم', 'school-system'); ?>">

        <div class="scha-route" id="scha-route">
            <?php echo sch_icon('check', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <span id="scha-route-t"><?php esc_html_e('يصل لولي الأمر صامتًا في خطه الزمني — بلا إزعاج.', 'school-system'); ?></span>
        </div>

        <button class="scha-btn" type="submit"><?php esc_html_e('حفظ', 'school-system'); ?></button>
    </form>
</div>

<?php if ($notes !== []) : ?>
    <h2 class="scha-h2"><?php esc_html_e('آخر الملاحظات', 'school-system'); ?></h2>
    <?php foreach ($notes as $n) : ?>
        <div class="scha-item">
            <strong><?php echo esc_html(SCH_Notes::CATEGORIES[$n->category][0] ?? ''); ?></strong>
            <span class="scha-card__sub"><?php echo esc_html($n->body ?: ''); ?></span>
            <span class="scha-time" dir="ltr"><?php echo esc_html(substr((string) $n->created_at, 0, 16)); ?></span>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
(function () {
  var wrap = document.getElementById('scha-cats');
  var field = document.getElementById('scha-cat');
  if (!wrap || !field) { return; }

  var ROUTES = {
    positive: <?php echo wp_json_encode(__('يصل لولي الأمر صامتًا في خطه الزمني — بلا إزعاج.', 'school-system')); ?>,
    participation: <?php echo wp_json_encode(__('تصل لولي الأمر صامتة — بلا إزعاج.', 'school-system')); ?>,
    followup: <?php echo wp_json_encode(__('تذهب لصندوق الأخصائية · ولو تُركت ٣ أيام تُرفع للوكيل.', 'school-system')); ?>,
    health: <?php echo wp_json_encode(__('تذهب للعيادة فورًا · ولو لم تُفتح خلال ١٠ دقائق تُرفع للوكيل.', 'school-system')); ?>
  };

  wrap.addEventListener('click', function (e) {
    var btn = e.target.closest('.scha-cat');
    if (!btn) { return; }
    field.value = btn.dataset.cat;
    wrap.querySelectorAll('.scha-cat').forEach(function (b) {
      b.classList.toggle('is-on', b === btn);
    });
    var t = document.getElementById('scha-route-t');
    if (t && ROUTES[btn.dataset.cat]) { t.textContent = ROUTES[btn.dataset.cat]; }
  });
})();
</script>
