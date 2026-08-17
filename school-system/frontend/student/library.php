<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$student = $sch_data['student'];
$mode    = SCH_Student::mode($student);
$one     = (int) ($sch_data['id'] ?? 0);
$stage   = (string) $student->stage;

if ($one > 0) {
    $item = SCH_Content::get($one);

    if (!$item || $item->status !== 'published' || $item->stage !== $stage) {
        echo '<div class="schs-empty"><strong>' . esc_html__('غير متاح', 'school-system') . '</strong></div>';
        return;
    }

    SCH_Content::record_open($one, (int) $student->id);
    ?>
    <a class="schs-back" href="<?php echo esc_url(SCH_Student::url('library')); ?>">‹ <?php esc_html_e('المكتبة', 'school-system'); ?></a>
    <h1 class="schs-h1"><?php echo esc_html($item->title); ?></h1>
    <?php if ($item->description) : ?>
        <p class="schs-sub"><?php echo esc_html($item->description); ?></p>
    <?php endif; ?>

    <?php if ($item->content_type === 'game') : ?>
        <!-- اللعبة في صندوق معزول: بلا هوية أصل وبلا شبكة -->
        <div class="schs-stage" data-content="<?php echo esc_attr((string) $one); ?>">
            <?php echo SCH_Content::game_frame($item); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </div>
        <p class="schs-score" id="schs-score" hidden></p>

    <?php elseif ($item->content_type === 'video') : ?>
        <div class="schs-video">
            <iframe src="<?php echo esc_url(SCH_Content::embed_url((string) $item->media_url)); ?>"
                    title="<?php echo esc_attr((string) $item->title); ?>"
                    allow="accelerometer; encrypted-media; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen loading="lazy"></iframe>
        </div>

    <?php elseif ($item->file_stored) : ?>
        <!-- الكتاب يُفتح من المجلد المحمي بمسار يتحقق من الصلاحية -->
        <a class="schs-btn" target="_blank" rel="noopener"
           href="<?php echo esc_url(SCH_Files::url('content', $one)); ?>">
            <?php esc_html_e('فتح الكتاب', 'school-system'); ?>
        </a>
    <?php elseif ($item->media_url) : ?>
        <a class="schs-btn" target="_blank" rel="noopener" href="<?php echo esc_url($item->media_url); ?>">
            <?php esc_html_e('فتح المصدر', 'school-system'); ?>
        </a>
    <?php else : ?>
        <div class="schs-empty"><strong><?php esc_html_e('المصدر غير متاح', 'school-system'); ?></strong></div>
    <?php endif; ?>
    <?php
    return;
}

$filter = isset($_GET['t']) ? sanitize_key(wp_unslash((string) $_GET['t'])) : '';
$items  = SCH_Content::for_stage($stage, isset(SCH_Content::TYPES[$filter]) ? $filter : '');
?>
<h1 class="schs-h1"><?php echo esc_html(SCH_Student::mode_labels($mode)['library']); ?></h1>
<p class="schs-sub"><?php esc_html_e('كتب وفيديوهات وألعاب لمرحلتك', 'school-system'); ?></p>

<div class="schs-filters">
    <a class="schs-chip<?php echo $filter === '' ? ' is-on' : ''; ?>"
       href="<?php echo esc_url(SCH_Student::url('library')); ?>"><?php esc_html_e('الكل', 'school-system'); ?></a>
    <?php foreach (SCH_Content::TYPES as $slug => $label) : ?>
        <a class="schs-chip<?php echo $filter === $slug ? ' is-on' : ''; ?>"
           href="<?php echo esc_url(add_query_arg('t', $slug, SCH_Student::url('library'))); ?>">
            <?php echo esc_html($label); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($items === []) : ?>
    <div class="schs-empty">
        <strong><?php esc_html_e('لا يوجد محتوى بعد', 'school-system'); ?></strong>
        <p><?php esc_html_e('سيضيفه منسق المحتوى قريبًا.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="schs-grid">
        <?php foreach ($items as $c) : ?>
            <a class="schs-card schs-card--<?php echo esc_attr($c->content_type); ?>"
               href="<?php echo esc_url(SCH_Student::url('library', (int) $c->id)); ?>">
                <span class="schs-card__icon"><?php echo sch_icon(SCH_Content::icon_of((string) $c->content_type), 22); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <strong><?php echo esc_html($c->title); ?></strong>
                <span><?php echo esc_html($c->subject_name ?: SCH_Content::TYPES[$c->content_type]); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
