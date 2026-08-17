<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$classes  = SCH_KG::classes();
$class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : (int) ($classes[0]->id ?? 0);
$date     = isset($_GET['d']) ? (sch_sanitize_date(wp_unslash((string) $_GET['d'])) ?? current_time('Y-m-d')) : current_time('Y-m-d');
$sheet    = $class_id > 0 ? SCH_KG::sheet($class_id, $date) : [];
?>
<h1 class="sch-title"><?php echo sch_icon('sun', 22); ?><?php esc_html_e('رياض الأطفال', 'school-system'); ?></h1>

<?php if ($classes === []) : ?>
    <div class="sch-card">
        <div class="sch-empty">
            <strong><?php esc_html_e('لا توجد شعب لرياض الأطفال', 'school-system'); ?></strong>
            <?php esc_html_e('أنشئ شعبة بمرحلة «رياض أطفال» من الصفوف والشعب.', 'school-system'); ?>
        </div>
    </div>
<?php else : ?>

<div class="sch-card">
    <h2><?php esc_html_e('التقرير اليومي', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('لا درجات ولا اختبارات — وجبة، نوم، مزاج، نشاط. يصل إشعار لولي الأمر عند أول حفظ.', 'school-system'); ?></p>

    <form method="get" class="sch-toolbar">
        <select name="class_id" onchange="this.form.submit()" aria-label="الشعبة">
            <?php foreach ($classes as $c) : ?>
                <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class_id, (int) $c->id); ?>>
                    <?php echo esc_html(SCH_Classes::label($c)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="d" value="<?php echo esc_attr($date); ?>" aria-label="التاريخ">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض', 'school-system'); ?></button>
    </form>

    <?php if ($sheet === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا يوجد أطفال في هذه الشعبة', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-kg-list">
            <?php foreach ($sheet as $child) : ?>
                <form method="post" class="sch-kg-card">
                    <?php wp_nonce_field('sch_save_kg', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="save_kg">
                    <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $child->id); ?>">
                    <input type="hidden" name="log_date" value="<?php echo esc_attr($date); ?>">

                    <strong class="sch-kg-name"><?php echo esc_html($child->full_name); ?></strong>

                    <div class="sch-grid">
                        <div class="sch-field">
                            <label><?php esc_html_e('المزاج', 'school-system'); ?></label>
                            <select name="mood" aria-label="المزاج">
                                <option value=""><?php esc_html_e('—', 'school-system'); ?></option>
                                <?php foreach (SCH_KG::MOODS as $slug => $label) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($child->mood, $slug); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sch-field">
                            <label><?php esc_html_e('الوجبة', 'school-system'); ?></label>
                            <select name="meal" aria-label="الوجبة">
                                <option value=""><?php esc_html_e('—', 'school-system'); ?></option>
                                <?php foreach (SCH_KG::MEALS as $slug => $label) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($child->meal, $slug); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sch-field">
                            <label><?php esc_html_e('النوم (دقيقة)', 'school-system'); ?></label>
                            <input type="number" name="nap_minutes" min="0" max="600" step="5"
                                   value="<?php echo esc_attr($child->nap_minutes !== null ? (string) $child->nap_minutes : ''); ?>" aria-label="دقائق النوم">
                        </div>
                        <div class="sch-field">
                            <label><?php esc_html_e('الأنشطة', 'school-system'); ?></label>
                            <input type="text" name="activities" value="<?php echo esc_attr((string) ($child->activities ?? '')); ?>" aria-label="الأنشطة">
                        </div>
                    </div>

                    <div class="sch-field">
                        <label><?php esc_html_e('ملاحظة لولي الأمر', 'school-system'); ?></label>
                        <input type="text" name="note" value="<?php echo esc_attr((string) ($child->note ?? '')); ?>" aria-label="ملاحظة">
                    </div>

                    <button class="sch-btn sch-btn--quiet"><?php esc_html_e('حفظ التقرير', 'school-system'); ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
