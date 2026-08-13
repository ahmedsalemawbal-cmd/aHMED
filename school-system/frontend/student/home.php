<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$student  = $sch_data['student'];
$mode     = SCH_Student::mode($student);
$progress = SCH_Student::progress((int) $student->id);
$missed   = SCH_Student::missed($student);
$hw       = SCH_Homework::for_student((int) $student->id);
$due      = array_values(array_filter($hw, static fn (object $h): bool => $h->sub_id === null));
$delta    = $progress['minutes'] - $progress['prev_minutes'];
?>
<h1 class="schs-h1"><?php echo esc_html(SCH_Student::mode_labels($mode)['greet']); ?></h1>
<p class="schs-sub"><?php echo esc_html($student->first_name ?: $student->full_name); ?></p>

<?php if ($mode === 'senior') : ?>
    <!-- الثانوي: أرقام لا ألعاب. وقته ضيق ومن يوفّره عليه يكسبه -->
    <div class="schs-two">
        <div class="schs-d3">
            <span class="schs-label"><?php esc_html_e('واجبات تنتظرك', 'school-system'); ?></span>
            <strong class="schs-hero"><?php echo esc_html(number_format_i18n(count($due))); ?></strong>
        </div>
        <div class="schs-d3">
            <span class="schs-label"><?php esc_html_e('حضورك', 'school-system'); ?></span>
            <strong class="schs-hero"><?php echo esc_html(number_format($progress['attendance'], 0)); ?><span>%</span></strong>
        </div>
    </div>
<?php else : ?>
    <!-- الابتدائي والمتوسط: المقارنة مع نفسك أمس لا مع زملائك -->
    <div class="schs-d3 schs-d3--hero">
        <span class="schs-label"><?php esc_html_e('تعلّمت هذا الأسبوع', 'school-system'); ?></span>
        <strong class="schs-hero"><?php echo esc_html(number_format_i18n($progress['minutes'])); ?><span><?php esc_html_e('دقيقة', 'school-system'); ?></span></strong>
        <?php if ($progress['prev_minutes'] > 0) : ?>
            <span class="schs-delta<?php echo $delta >= 0 ? ' is-up' : ''; ?>">
                <?php echo esc_html($delta >= 0
                    ? sprintf(__('+%s عن الأسبوع الماضي', 'school-system'), number_format_i18n(abs($delta)))
                    : sprintf(__('-%s عن الأسبوع الماضي', 'school-system'), number_format_i18n(abs($delta)))); ?>
            </span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($missed !== []) : ?>
    <!-- ما فاتك: النظام يعرف جدول مدرستك — ولا منصة أخرى تعرفه -->
    <h2 class="schs-h2"><?php esc_html_e('ما فاتك', 'school-system'); ?></h2>
    <?php foreach (array_slice($missed, 0, 4) as $m) : ?>
        <div class="schs-missed">
            <span class="schs-missed__day" dir="ltr"><?php echo esc_html((string) $m->date); ?></span>
            <strong><?php echo esc_html($m->subject_name); ?></strong>
            <span class="schs-sub">
                <?php echo esc_html(sprintf(
                    /* translators: %d: رقم الحصة */
                    __('الحصة %d', 'school-system'),
                    (int) $m->period_no
                )); ?>
            </span>

            <?php if ($m->resources !== []) : ?>
                <div class="schs-missed__res">
                    <?php foreach ($m->resources as $r) : ?>
                        <a class="schs-pill" href="<?php echo esc_url(SCH_Student::url('library', (int) $r->id)); ?>">
                            <?php echo sch_icon(SCH_Content::icon_of((string) $r->content_type), 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <?php echo esc_html(mb_substr((string) $r->title, 0, 26)); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($due !== []) : ?>
    <h2 class="schs-h2"><?php esc_html_e('واجبات هذا الأسبوع', 'school-system'); ?></h2>
    <?php foreach (array_slice($due, 0, 4) as $h) : ?>
        <a class="schs-item" href="<?php echo esc_url(SCH_Student::url('homework', (int) $h->id)); ?>">
            <strong><?php echo esc_html($h->title); ?></strong>
            <span><?php echo esc_html($h->subject_name ?: ''); ?></span>
            <span class="schs-due" dir="ltr"><?php echo esc_html((string) $h->due_date); ?></span>
        </a>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($missed === [] && $due === []) : ?>
    <div class="schs-empty">
        <strong><?php esc_html_e('لا شيء ينتظرك', 'school-system'); ?></strong>
        <p><?php esc_html_e('افتح المكتبة وتعلّم شيئًا جديدًا.', 'school-system'); ?></p>
    </div>
<?php endif; ?>
