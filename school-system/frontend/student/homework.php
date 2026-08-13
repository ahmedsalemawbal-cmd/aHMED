<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$student = $sch_data['student'];
$one     = (int) ($sch_data['id'] ?? 0);

if ($one > 0) {
    $hw    = SCH_Homework::get($one);
    $class = SCH_Students::current_class((int) $student->id);

    if (!$hw || !$class || (int) $class->id !== (int) $hw->class_id) {
        echo '<div class="schs-empty"><strong>' . esc_html__('غير متاح', 'school-system') . '</strong></div>';
        return;
    }

    $mine = null;
    foreach (SCH_Homework::for_student((int) $student->id, true) as $row) {
        if ((int) $row->id === $one) { $mine = $row; break; }
    }
    if (!$mine) {
        foreach (SCH_Homework::for_student((int) $student->id, false) as $row) {
            if ((int) $row->id === $one) { $mine = $row; break; }
        }
    }
    ?>
    <a class="schs-back" href="<?php echo esc_url(SCH_Student::url('homework')); ?>">‹ <?php esc_html_e('واجباتي', 'school-system'); ?></a>
    <h1 class="schs-h1"><?php echo esc_html($hw->title); ?></h1>
    <p class="schs-sub"><?php echo esc_html(($hw->subject_name ?: '') . ' · ' . $hw->due_date); ?></p>

    <?php if ($hw->body) : ?>
        <div class="schs-flat"><?php echo esc_html($hw->body); ?></div>
    <?php endif; ?>

    <?php if ($hw->file_stored) : ?>
        <a class="schs-btn schs-btn--quiet" target="_blank" rel="noopener"
           href="<?php echo esc_url(SCH_Files::url('homework', $one)); ?>">
            <?php esc_html_e('فتح مرفق الواجب', 'school-system'); ?>
        </a>
    <?php endif; ?>

    <?php if ($mine && $mine->sub_id) : ?>
        <div class="schs-flash schs-flash--ok"><?php esc_html_e('سلّمت هذا الواجب.', 'school-system'); ?></div>
        <?php if ($mine->score !== null) : ?>
            <div class="schs-d3">
                <span class="schs-label"><?php esc_html_e('درجتك', 'school-system'); ?></span>
                <strong class="schs-hero"><?php echo esc_html(number_format((float) $mine->score, 1)); ?></strong>
                <?php if ($mine->feedback) : ?>
                    <span class="schs-sub"><?php echo esc_html($mine->feedback); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($hw->answer_type !== 'none') : ?>
        <form method="post" enctype="multipart/form-data" id="schs-hw-form">
            <?php wp_nonce_field('sch_stu_hw', '_sch_nonce'); ?>
            <input type="hidden" name="sch_stu_action" value="submit_hw">
            <input type="hidden" name="homework_id" value="<?php echo esc_attr((string) $one); ?>">
            <input type="hidden" name="drawing" id="schs-draw-data">

            <?php if ($hw->answer_type === 'drawing') : ?>
                <!-- الرسم كإجابة: الاسترجاع بالرسم يثبّت أقوى من إعادة القراءة -->
                <h2 class="schs-h2"><?php esc_html_e('ارسم إجابتك', 'school-system'); ?></h2>
                <div class="schs-draw">
                    <canvas id="schs-canvas" width="900" height="620"></canvas>
                    <div class="schs-draw__tools">
                        <button type="button" class="schs-tool" data-color="#0F1720" style="--c:#0F1720"></button>
                        <button type="button" class="schs-tool" data-color="#5170FF" style="--c:#5170FF"></button>
                        <button type="button" class="schs-tool" data-color="#B45309" style="--c:#B45309"></button>
                        <button type="button" class="schs-tool" data-color="#B91C1C" style="--c:#B91C1C"></button>
                        <button type="button" class="schs-tool schs-tool--wide" id="schs-undo"><?php esc_html_e('تراجع', 'school-system'); ?></button>
                        <button type="button" class="schs-tool schs-tool--wide" id="schs-clear"><?php esc_html_e('مسح', 'school-system'); ?></button>
                    </div>
                </div>
            <?php elseif ($hw->answer_type === 'file') : ?>
                <label class="schs-field-label" for="hw-file"><?php esc_html_e('أرفق ملفك', 'school-system'); ?></label>
                <input class="schs-input" id="hw-file" type="file" name="answer_file" accept="image/jpeg,image/png,application/pdf">
            <?php else : ?>
                <label class="schs-field-label" for="hw-text"><?php esc_html_e('إجابتك', 'school-system'); ?></label>
                <textarea class="schs-input" id="hw-text" name="answer_text" rows="5"><?php echo esc_textarea((string) ($mine->answer_text ?? '')); ?></textarea>
            <?php endif; ?>

            <button class="schs-btn" type="submit"><?php esc_html_e('تسليم', 'school-system'); ?></button>
        </form>
    <?php endif; ?>
    <?php
    return;
}

$current = SCH_Homework::for_student((int) $student->id, true);
$past    = SCH_Homework::for_student((int) $student->id, false);
?>
<h1 class="schs-h1"><?php esc_html_e('واجباتي', 'school-system'); ?></h1>
<p class="schs-sub"><?php esc_html_e('هذا الأسبوع', 'school-system'); ?></p>

<?php if ($current === []) : ?>
    <div class="schs-empty"><strong><?php esc_html_e('لا واجبات هذا الأسبوع', 'school-system'); ?></strong></div>
<?php else : ?>
    <?php foreach ($current as $h) : ?>
        <a class="schs-item<?php echo $h->sub_id ? ' is-done' : ''; ?>"
           href="<?php echo esc_url(SCH_Student::url('homework', (int) $h->id)); ?>">
            <strong><?php echo esc_html($h->title); ?></strong>
            <span><?php echo esc_html($h->subject_name ?: ''); ?></span>
            <span class="schs-due<?php echo $h->sub_id ? ' is-ok' : ''; ?>" dir="ltr">
                <?php echo esc_html($h->sub_id ? __('سُلّم', 'school-system') : (string) $h->due_date); ?>
            </span>
        </a>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($past !== []) : ?>
    <!-- لا تُحذف: الغائب يحتاجها والمعلم يحتاج سجلها -->
    <h2 class="schs-h2"><?php esc_html_e('السابقة', 'school-system'); ?></h2>
    <?php foreach (array_slice($past, 0, 10) as $h) : ?>
        <a class="schs-item is-past" href="<?php echo esc_url(SCH_Student::url('homework', (int) $h->id)); ?>">
            <strong><?php echo esc_html($h->title); ?></strong>
            <span><?php echo esc_html($h->subject_name ?: ''); ?></span>
        </a>
    <?php endforeach; ?>
<?php endif; ?>
