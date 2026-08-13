<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$subjects = SCH_Subjects::all();
$classes  = SCH_Classes::list();
$class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : (int) ($classes[0]->id ?? 0);
$assigned = $class_id > 0 ? SCH_Subjects::of_class($class_id) : [];
$grid     = $class_id > 0 ? SCH_Timetable::grid($class_id) : [];
$teachers = get_users(['role' => 'sch_teacher', 'fields' => ['ID', 'display_name']]);
?>
<?php
SCH_Modal::head(
    __('المواد الدراسية', 'school-system'),
    '',
    'sch-add-subject',
    __('إضافة مادة', 'school-system'),
    'book'
);
?>


<?php if ($classes !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('مواد الشعبة ومعلموها', 'school-system'); ?></h2>

    <form method="get" class="sch-toolbar">
        <select name="class_id" onchange="this.form.submit()" aria-label="الشعبة">
            <?php foreach ($classes as $c) : ?>
                <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class_id, (int) $c->id); ?>>
                    <?php echo esc_html(SCH_Classes::label($c)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض', 'school-system'); ?></button></noscript>
    </form>

    <?php if ($assigned !== []) : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('المادة', 'school-system'); ?></th>
                    <th><?php esc_html_e('المعلم', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($assigned as $a) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($a->subject_name); ?></td>
                        <td><?php echo esc_html($a->teacher_name ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" class="sch-toolbar sch-mt">
        <?php wp_nonce_field('sch_assign_subject', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="assign_subject">
        <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $class_id); ?>">
        <select name="subject_id" required aria-label="المادة">
            <option value=""><?php esc_html_e('المادة…', 'school-system'); ?></option>
            <?php foreach ($subjects as $s) : ?>
                <option value="<?php echo esc_attr((string) $s->id); ?>"><?php echo esc_html($s->name); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="teacher_user_id" aria-label="المعلم">
            <option value=""><?php esc_html_e('بلا معلم', 'school-system'); ?></option>
            <?php foreach ($teachers as $u) : ?>
                <option value="<?php echo esc_attr((string) $u->ID); ?>"><?php echo esc_html($u->display_name); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إسناد', 'school-system'); ?></button>
    </form>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('الجدول الأسبوعي', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('النظام يمنع إسناد المعلم لشعبتين في نفس الحصة.', 'school-system'); ?></p>

    <div class="sch-table-wrap">
        <table class="sch-table sch-timetable">
            <thead><tr>
                <th><?php esc_html_e('الحصة', 'school-system'); ?></th>
                <?php foreach (SCH_Timetable::DAYS as $label) : ?>
                    <th><?php echo esc_html($label); ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php for ($p = 1; $p <= SCH_Timetable::PERIODS; $p++) : ?>
                <tr>
                    <td class="sch-name"><?php echo esc_html((string) $p); ?></td>
                    <?php foreach (array_keys(SCH_Timetable::DAYS) as $day) :
                        $slot = $grid[$day][$p] ?? null; ?>
                        <td>
                            <?php if ($slot) : ?>
                                <strong><?php echo esc_html($slot->subject_name); ?></strong>
                                <span class="sch-sub sch-block"><?php echo esc_html($slot->teacher_name ?: '—'); ?></span>
                            <?php else : ?>
                                <span class="sch-sub">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <?php if ($assigned !== []) : ?>
    <form method="post" class="sch-toolbar sch-mt">
        <?php wp_nonce_field('sch_set_slot', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="set_slot">
        <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $class_id); ?>">
        <select name="day_of_week" required aria-label="اليوم">
            <?php foreach (SCH_Timetable::DAYS as $num => $label) : ?>
                <option value="<?php echo esc_attr((string) $num); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="period_no" required aria-label="الحصة">
            <?php for ($p = 1; $p <= SCH_Timetable::PERIODS; $p++) : ?>
                <option value="<?php echo esc_attr((string) $p); ?>"><?php echo esc_html(sprintf(__('الحصة %d', 'school-system'), $p)); ?></option>
            <?php endfor; ?>
        </select>
        <select name="subject_id" required aria-label="المادة">
            <?php foreach ($assigned as $a) : ?>
                <option value="<?php echo esc_attr((string) $a->subject_id); ?>" data-teacher="<?php echo esc_attr((string) $a->teacher_user_id); ?>">
                    <?php echo esc_html($a->subject_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="teacher_user_id" aria-label="المعلم">
            <option value=""><?php esc_html_e('بلا معلم', 'school-system'); ?></option>
            <?php foreach ($teachers as $u) : ?>
                <option value="<?php echo esc_attr((string) $u->ID); ?>"><?php echo esc_html($u->display_name); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="sch-btn"><?php esc_html_e('وضع الحصة', 'school-system'); ?></button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php SCH_Modal::open('sch-add-subject', __('إضافة مادة', 'school-system'), __('تُربط بالجدول والدرجات والواجبات', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('المواد الدراسية', 'school-system'); ?></h2>
    <?php if ($subjects === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد مواد', 'school-system'); ?></strong><?php esc_html_e('أضف المواد ثم أسندها للشعب.', 'school-system'); ?></div>
    <?php else : ?>
        <p class="sch-chips">
            <?php foreach ($subjects as $s) : ?>
                <span class="sch-chip"><?php echo esc_html($s->name); ?></span>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <form method="post" class="sch-toolbar sch-mt">
        <?php wp_nonce_field('sch_add_subject', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_subject">
        <input type="text" name="name" placeholder="<?php esc_attr_e('اسم المادة', 'school-system'); ?>" required>
        <input type="text" name="code" placeholder="<?php esc_attr_e('الرمز (اختياري)', 'school-system'); ?>" dir="ltr">
        <select name="stage" aria-label="المرحلة">
            <option value=""><?php esc_html_e('كل المراحل', 'school-system'); ?></option>
            <?php foreach (SCH_Classes::STAGES as $slug => $label) : ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="sch-btn"><?php esc_html_e('إضافة', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
