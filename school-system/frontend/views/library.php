<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$books    = SCH_Library::books();
$loans    = SCH_Library::open_loans();
$students = SCH_Students::list(['status' => 'active', 'per_page' => 300]);
$today    = current_time('Y-m-d');
?>
<?php
SCH_Modal::head(
    __('المكتبة', 'school-system'),
    '',
    'sch-add-book',
    __('إضافة كتاب', 'school-system'),
    'book'
);
?>

<div class="sch-card">
    <h2><?php esc_html_e('الإعارات المفتوحة', 'school-system'); ?></h2>
    <?php if ($loans === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد إعارات', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الكتاب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('تاريخ الإعارة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الإرجاع', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($loans as $l) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($l->title); ?></td>
                        <td><?php echo esc_html($l->full_name); ?></td>
                        <td dir="ltr"><?php echo esc_html($l->loaned_at); ?></td>
                        <td dir="ltr">
                            <?php echo esc_html($l->due_date); ?>
                            <?php if ($l->due_date < $today) : ?>
                                <span class="sch-badge"><?php esc_html_e('متأخر', 'school-system'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
                                <?php wp_nonce_field('sch_return_book', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="return_book">
                                <input type="hidden" name="loan_id" value="<?php echo esc_attr((string) $l->id); ?>">
                                <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إرجاع', 'school-system'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($books !== []) : ?>
    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar sch-mt">
        <?php wp_nonce_field('sch_loan_book', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="loan_book">
        <select name="book_id" required aria-label="<?php esc_attr_e('الكتاب', 'school-system'); ?>">
            <option value=""><?php esc_html_e('الكتاب…', 'school-system'); ?></option>
            <?php foreach ($books as $b) : ?>
                <option value="<?php echo esc_attr((string) $b->id); ?>">
                    <?php echo esc_html($b->title . ' (' . ((int) $b->copies - (int) $b->out_count) . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="student_id" required aria-label="<?php esc_attr_e('الطالب', 'school-system'); ?>">
            <option value=""><?php esc_html_e('الطالب…', 'school-system'); ?></option>
            <?php foreach ($students['items'] as $s) : ?>
                <option value="<?php echo esc_attr((string) $s->id); ?>"><?php echo esc_html($s->full_name); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="due_date" aria-label="<?php esc_attr_e('تاريخ الاستحقاق', 'school-system'); ?>">
        <button class="sch-btn"><?php esc_html_e('إعارة', 'school-system'); ?></button>
    </form>
    <?php endif; ?>
</div>

<?php SCH_Modal::open('sch-add-book', __('إضافة كتاب', 'school-system'), __('يدخل المكتبة ويصير متاحًا للإعارة', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('الكتب', 'school-system'); ?></h2>
    <?php if ($books === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد كتب', 'school-system'); ?></strong><?php esc_html_e('أضف كتابًا لتبدأ الإعارة.', 'school-system'); ?></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('العنوان', 'school-system'); ?></th>
                    <th><?php esc_html_e('المؤلف', 'school-system'); ?></th>
                    <th><?php esc_html_e('النسخ', 'school-system'); ?></th>
                    <th><?php esc_html_e('المتاح', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($books as $b) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($b->title); ?></td>
                        <td><?php echo esc_html($b->author ?: '—'); ?></td>
                        <td><?php echo esc_html((string) $b->copies); ?></td>
                        <td><?php echo esc_html((string) ((int) $b->copies - (int) $b->out_count)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar sch-mt">
        <?php wp_nonce_field('sch_add_book', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_book">
        <input type="text" name="title" placeholder="<?php esc_attr_e('العنوان', 'school-system'); ?>" required>
        <input type="text" name="author" placeholder="<?php esc_attr_e('المؤلف', 'school-system'); ?>">
        <input type="number" name="copies" value="1" min="1" max="999" title="<?php esc_attr_e('عدد النسخ', 'school-system'); ?>" aria-label="<?php esc_attr_e('عدد النسخ', 'school-system'); ?>">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إضافة كتاب', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
