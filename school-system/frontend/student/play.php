<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$student = $sch_data['student'];
$mode    = SCH_Student::mode($student);
$games   = SCH_Content::for_stage((string) $student->stage, 'game');
?>
<h1 class="schs-h1"><?php echo esc_html(SCH_Student::mode_labels($mode)['play']); ?></h1>

<?php if ($mode === 'senior') : ?>
    <p class="schs-sub"><?php esc_html_e('تدريبات قصيرة على ما تحتاجه', 'school-system'); ?></p>
<?php elseif ($mode === 'mid') : ?>
    <p class="schs-sub"><?php esc_html_e('تحدَّ نفسك وفصلك', 'school-system'); ?></p>
<?php else : ?>
    <p class="schs-sub"><?php esc_html_e('العب وتعلّم', 'school-system'); ?></p>
<?php endif; ?>

<?php if ($games === []) : ?>
    <div class="schs-empty">
        <strong><?php esc_html_e('لا توجد ألعاب بعد', 'school-system'); ?></strong>
        <p><?php esc_html_e('ستُضاف قريبًا.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="schs-grid">
        <?php foreach ($games as $g) : ?>
            <a class="schs-card schs-card--game" href="<?php echo esc_url(SCH_Student::url('library', (int) $g->id)); ?>">
                <span class="schs-card__icon"><?php echo sch_icon('award', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <strong><?php echo esc_html($g->title); ?></strong>
                <span><?php echo esc_html($g->subject_name ?: ''); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (SCH_Student::shows_ranking($mode)) : ?>
    <!-- التنافس ينفع في المتوسط وحده — وبين الفصول لا بين الأفراد -->
    <h2 class="schs-h2"><?php esc_html_e('تحدي الفصول', 'school-system'); ?></h2>
    <div class="schs-flat">
        <p class="schs-sub"><?php esc_html_e('مجموع نقاط فصلك يظهر هنا حين تبدأ التحديات.', 'school-system'); ?></p>
    </div>
<?php endif; ?>
