<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$stage  = isset($_GET['stage']) ? sanitize_key(wp_unslash((string) $_GET['stage'])) : '';
$type   = isset($_GET['type']) ? sanitize_key(wp_unslash((string) $_GET['type'])) : '';
$items  = SCH_Content::all(isset(SCH_Classes::STAGES[$stage]) ? $stage : '', isset(SCH_Content::TYPES[$type]) ? $type : '');
$stale  = SCH_Content::needs_review();
$subjects = SCH_Subjects::all();
?>
<?php
SCH_Modal::head(
    __('المحتوى التعليمي', 'school-system'),
    '',
    'sch-add-content',
    __('إضافة محتوى', 'school-system'),
    'book'
);
?>
<p class="sch-sub"><?php esc_html_e('اختر المرحلة وأضف كتابًا أو فيديو أو لعبة. المكتبات تموت بالتراكم لا بالنقص — راجع ما لا يُفتح.', 'school-system'); ?></p>

<?php if ($stale !== []) : ?>
<div class="sch-notice">
    <strong><?php echo esc_html(sprintf(
        /* translators: %d: عدد العناصر */
        _n('عنصر واحد يحتاج مراجعة', '%d عناصر تحتاج مراجعة', count($stale), 'school-system'),
        count($stale)
    )); ?></strong>
    <div class="sch-sub"><?php esc_html_e('مضت سنة على نشره — جدّده أو أرشفه.', 'school-system'); ?></div>
</div>
<?php endif; ?>


<div class="sch-card">
    <h2><?php esc_html_e('المكتبة', 'school-system'); ?></h2>

    <form method="get" class="sch-toolbar">
        <select name="stage" aria-label="المرحلة">
            <option value=""><?php esc_html_e('كل المراحل', 'school-system'); ?></option>
            <?php foreach (SCH_Classes::STAGES as $slug => $label) : ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected($stage, $slug); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" aria-label="النوع">
            <option value=""><?php esc_html_e('كل الأنواع', 'school-system'); ?></option>
            <?php foreach (SCH_Content::TYPES as $slug => $label) : ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected($type, $slug); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('تصفية', 'school-system'); ?></button>
    </form>

    <?php if ($items === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا يوجد محتوى', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('العنوان', 'school-system'); ?></th>
                    <th><?php esc_html_e('النوع', 'school-system'); ?></th>
                    <th><?php esc_html_e('المرحلة', 'school-system'); ?></th>
                    <th><?php esc_html_e('فُتح', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($items as $c) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($c->title); ?></td>
                        <td><?php echo esc_html(SCH_Content::TYPES[$c->content_type] ?? ''); ?></td>
                        <td class="sch-sub"><?php echo esc_html(SCH_Classes::STAGES[$c->stage] ?? ''); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $c->opens)); ?></td>
                        <td>
                            <span class="sch-badge <?php echo $c->status === 'published' ? 'sch-badge--ok' : 'sch-badge--muted'; ?>">
                                <?php echo esc_html(SCH_Content::STATUSES[$c->status] ?? ''); ?>
                            </span>
                        </td>
                        <td>
                            <div class="sch-inline">
                                <?php
                                /*
                                 * «جدّده» كان وعدًا بلا زرّ: الشاشة تقول «مضت سنة على
                                 * نشره — جدّده أو أرشفه»، والأرشفة موجودة والتجديد لا.
                                 * والفعل `content_renew` ومعالجه و`SCH_Content::renew()`
                                 * كلّها مكتوبة منذ البداية بلا نموذجٍ يرسلها.
                                 */
                                $sch_stale = $c->review_at !== null && $c->review_at <= current_time('Y-m-d');
                                ?>
                                <?php if ($sch_stale && $c->status === 'published') : ?>
                                    <form method="post">
                                        <?php wp_nonce_field('sch_content_renew', '_sch_nonce'); ?>
                                        <input type="hidden" name="sch_action" value="content_renew">
                                        <input type="hidden" name="content_id" value="<?php echo esc_attr((string) $c->id); ?>">
                                        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('تجديد', 'school-system'); ?></button>
                                    </form>
                                <?php endif; ?>

                                <?php foreach (['published' => __('نشر', 'school-system'), 'archived' => __('أرشفة', 'school-system')] as $slug => $label) : ?>
                                    <?php if ($c->status !== $slug) : ?>
                                        <form method="post">
                                            <?php wp_nonce_field('sch_content_status', '_sch_nonce'); ?>
                                            <input type="hidden" name="sch_action" value="content_status">
                                            <input type="hidden" name="content_id" value="<?php echo esc_attr((string) $c->id); ?>">
                                            <input type="hidden" name="status" value="<?php echo esc_attr($slug); ?>">
                                            <button class="sch-btn sch-btn--quiet"><?php echo esc_html($label); ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php SCH_Modal::open('sch-add-content', __('إضافة محتوى', 'school-system'), __('كتاب أو فيديو أو لعبة معزولة', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('إضافة محتوى', 'school-system'); ?></h2>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('sch_add_content', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_content">

        <div class="sch-grid">
            <div class="sch-field">
                <label for="c-type"><?php esc_html_e('النوع', 'school-system'); ?></label>
                <select id="c-type" name="content_type" required>
                    <?php foreach (SCH_Content::TYPES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="c-stage"><?php esc_html_e('المرحلة', 'school-system'); ?></label>
                <select id="c-stage" name="stage" required>
                    <?php foreach (SCH_Classes::STAGES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="c-subject"><?php esc_html_e('المادة', 'school-system'); ?></label>
                <select id="c-subject" name="subject_id">
                    <option value=""><?php esc_html_e('— بلا مادة —', 'school-system'); ?></option>
                    <?php foreach ($subjects as $s) : ?>
                        <option value="<?php echo esc_attr((string) $s->id); ?>"><?php echo esc_html($s->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="c-title"><?php esc_html_e('العنوان', 'school-system'); ?></label>
                <input id="c-title" type="text" name="title" required>
            </div>

            <div class="sch-field sch-field--wide">
                <label for="c-desc"><?php esc_html_e('وصف مختصر', 'school-system'); ?></label>
                <input id="c-desc" type="text" name="description" maxlength="500">
            </div>

            <div class="sch-field">
                <label for="c-url"><?php esc_html_e('رابط الفيديو أو المصدر', 'school-system'); ?></label>
                <input id="c-url" type="url" name="media_url" dir="ltr" placeholder="https://">
            </div>

            <div class="sch-field">
                <label for="c-file"><?php esc_html_e('ملف الكتاب', 'school-system'); ?></label>
                <input id="c-file" type="file" name="file" accept="application/pdf,image/jpeg,image/png">
            </div>
        </div>

        <div class="sch-field sch-field--wide">
            <label for="c-code"><?php esc_html_e('كود اللعبة', 'school-system'); ?></label>
            <textarea id="c-code" name="game_code" rows="8" dir="ltr"
                      placeholder="<!DOCTYPE html> ... "></textarea>
            <p class="sch-sub">
                <?php esc_html_e('الصق كود HTML/JS كاملًا. يعمل في صندوق معزول بلا اتصال بالإنترنت ولا وصول لبيانات التطبيق — فحتى الكود الخبيث لا يجد ما يسرقه.', 'school-system'); ?>
                <br>
                <?php esc_html_e('لإرسال النتيجة من اللعبة استدعِ:', 'school-system'); ?>
                <code class="sch-code" dir="ltr">schScore(120)</code>
            </p>
        </div>

        <button class="sch-btn"><?php esc_html_e('حفظ كمسودة', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
