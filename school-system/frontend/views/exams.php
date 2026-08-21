<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$classes  = SCH_Classes::list();
$exams    = SCH_Assessment::exams();
$exam_id  = isset($_GET['exam']) ? absint($_GET['exam']) : 0;
$exam     = $exam_id > 0 ? SCH_Assessment::get_exam($exam_id) : null;
$sheet    = $exam ? SCH_Assessment::result_sheet($exam_id) : [];
$subjects = SCH_Subjects::all();
?>
<?php
SCH_Modal::head(
    __('الاختبارات', 'school-system'),
    '',
    'sch-add-exam',
    __('إنشاء اختبار', 'school-system')
);
?>

<?php if ($exam) : ?>
<div class="sch-card">
    <a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('exams')); ?>">&larr; <?php esc_html_e('كل الاختبارات', 'school-system'); ?></a>
    <h2><?php echo esc_html($exam->title . ' — ' . $exam->subject_name); ?></h2>
    <p class="sch-sub"><?php echo esc_html(sprintf(
        /* translators: 1: الصف 2: الشعبة 3: الدرجة العظمى */
        __('%1$s / %2$s — الدرجة العظمى %3$s', 'school-system'),
        $exam->grade_level, $exam->section, $exam->max_score
    )); ?></p>

    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
        <?php wp_nonce_field('sch_save_scores', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="save_scores">
        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">

        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th style="width:140px"><?php esc_html_e('الدرجة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($sheet as $row) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($row->full_name); ?></td>
                        <td>
                            <input type="number" step="0.25" min="0" max="<?php echo esc_attr((string) $exam->max_score); ?>"
                                   name="score[<?php echo esc_attr((string) $row->id); ?>]"
                                   value="<?php echo esc_attr($row->score !== null ? (string) (float) $row->score : ''); ?>"
                                   style="max-width:120px" aria-label="الدرجة">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button class="sch-btn sch-mt"><?php esc_html_e('حفظ الدرجات', 'school-system'); ?></button>
    </form>
</div>
<?php else : ?>

<div class="sch-card">
    <?php if ($exams === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد اختبارات', 'school-system'); ?></strong><?php esc_html_e('أنشئ اختبارًا ثم ارصد درجاته.', 'school-system'); ?></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الاختبار', 'school-system'); ?></th>
                    <th><?php esc_html_e('المادة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الشعبة', 'school-system'); ?></th>
                    <th><?php esc_html_e('النوع', 'school-system'); ?></th>
                    <th><?php esc_html_e('التاريخ', 'school-system'); ?></th>
                    <th><?php esc_html_e('مرصود', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($exams as $e) : ?>
                    <tr>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(add_query_arg('exam', (int) $e->id, SCH_Dashboard::url('exams'))); ?>"><?php echo esc_html($e->title); ?></a>
                        </td>
                        <td><?php echo esc_html($e->subject_name); ?></td>
                        <td><?php echo esc_html($e->grade_level . ' / ' . $e->section); ?></td>
                        <td><?php echo esc_html(SCH_Assessment::EXAM_TYPES[$e->exam_type] ?? $e->exam_type); ?></td>
                        <td dir="ltr"><?php echo esc_html($e->exam_date ?: '—'); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $e->graded)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php SCH_Modal::open('sch-add-exam', __('إنشاء اختبار', 'school-system'), __('يظهر في تطبيق الطالب وولي الأمر فور إنشائه', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('إنشاء اختبار', 'school-system'); ?></h2>
    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
        <?php wp_nonce_field('sch_add_exam', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_exam">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="x-title"><?php esc_html_e('العنوان', 'school-system'); ?></label>
                <input id="x-title" type="text" name="title" required>
            </div>
            <div class="sch-field">
                <label for="x-class"><?php esc_html_e('الشعبة', 'school-system'); ?></label>
                <select id="x-class" name="class_id" required>
                    <?php foreach ($classes as $c) : ?>
                        <option value="<?php echo esc_attr((string) $c->id); ?>"><?php echo esc_html(SCH_Classes::label($c)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="x-subject"><?php esc_html_e('المادة', 'school-system'); ?></label>
                <select id="x-subject" name="subject_id" required>
                    <?php foreach ($subjects as $s) : ?>
                        <option value="<?php echo esc_attr((string) $s->id); ?>"><?php echo esc_html($s->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="x-type"><?php esc_html_e('النوع', 'school-system'); ?></label>
                <select id="x-type" name="exam_type">
                    <?php foreach (SCH_Assessment::EXAM_TYPES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="x-date"><?php esc_html_e('التاريخ', 'school-system'); ?></label>
                <input id="x-date" type="date" name="exam_date">
            </div>
            <div class="sch-field">
                <label for="x-max"><?php esc_html_e('الدرجة العظمى', 'school-system'); ?></label>
                <input id="x-max" type="number" name="max_score" value="100" min="1" step="0.5">
            </div>
            <div class="sch-field">
                <label for="x-weight"><?php esc_html_e('الوزن %', 'school-system'); ?></label>
                <input id="x-weight" type="number" name="weight" value="100" min="0" max="100" step="1">
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('إنشاء', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
