<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$one      = isset($_GET['hw']) ? absint($_GET['hw']) : 0;
$classes  = SCH_Classes::list();
$subjects = SCH_Subjects::all();

if ($one > 0) {
    $hw      = SCH_Homework::get($one);
    $rows    = SCH_Homework::submissions($one);
    $summary = SCH_Homework::summary($one);

    if (!$hw) {
        echo '<div class="sch-empty"><strong>' . esc_html__('الواجب غير موجود', 'school-system') . '</strong></div>';
        return;
    }
    ?>
    <a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('homework')); ?>">‹ <?php esc_html_e('الواجبات', 'school-system'); ?></a>
    <?php SCH_Modal::head(__('الواجبات', 'school-system')); ?>
    <p class="sch-sub">
        <?php echo esc_html(trim((string) $hw->grade_level . ' / ' . (string) $hw->section)
            . ' · ' . ($hw->subject_name ?: '') . ' · ' . $hw->due_date); ?>
    </p>

    <div class="sch-stats">
        <div class="sch-stat">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($summary['submitted'])); ?></span>
            <span class="sch-stat__label"><?php esc_html_e('سلّموا', 'school-system'); ?></span>
        </div>
        <div class="sch-stat<?php echo $summary['missing'] > 0 ? ' sch-stat--warn' : ''; ?>">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($summary['missing'])); ?></span>
            <span class="sch-stat__label"><?php esc_html_e('لم يسلّموا', 'school-system'); ?></span>
        </div>
        <div class="sch-stat">
            <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($summary['was_absent'])); ?></span>
            <span class="sch-stat__label"><?php esc_html_e('منهم كانوا غائبين يوم الشرح', 'school-system'); ?></span>
        </div>
    </div>

    <?php if ($summary['was_absent'] > 0) : ?>
        <div class="sch-notice">
            <strong><?php esc_html_e('فرق مهم', 'school-system'); ?></strong>
            <div class="sch-sub"><?php esc_html_e('طالب لم يحضر الشرح ليس كطالب كسِل — عامِلهما مختلفَين.', 'school-system'); ?></div>
        </div>
    <?php endif; ?>

    <div class="sch-card">
        <h2><?php esc_html_e('التسليم', 'school-system'); ?></h2>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الإجابة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الدرجة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($r->full_name); ?></td>
                        <td>
                            <?php if ($r->sub_id) : ?>
                                <span class="sch-badge sch-badge--ok" dir="ltr"><?php echo esc_html(substr((string) $r->submitted_at, 0, 16)); ?></span>
                            <?php elseif (in_array((string) $r->taught_day_status, ['absent', 'excused'], true)) : ?>
                                <span class="sch-badge sch-badge--warn"><?php esc_html_e('كان غائبًا يوم الشرح', 'school-system'); ?></span>
                            <?php else : ?>
                                <span class="sch-badge sch-badge--muted"><?php esc_html_e('لم يسلّم', 'school-system'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="sch-sub">
                            <?php echo esc_html(mb_substr((string) ($r->answer_text ?? ''), 0, 60)); ?>
                            <?php if ($r->file_stored) : ?>
                                <a class="sch-thumb" target="_blank" rel="noopener"
                                   href="<?php echo esc_url(SCH_Files::url('answer', (int) $r->sub_id)); ?>">
                                    <?php echo sch_icon('image', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    <?php esc_html_e('فتح الإجابة', 'school-system'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r->sub_id) : ?>
                                <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar">
                                    <?php wp_nonce_field('sch_grade_homework', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="grade_homework">
                                    <input type="hidden" name="sub_id" value="<?php echo esc_attr((string) $r->sub_id); ?>">
                                    <input type="number" name="score" step="0.5" min="0" max="100" style="width:80px"
                                           value="<?php echo esc_attr((string) ($r->score ?? '')); ?>" aria-label="الدرجة">
                                    <?php
                                    /*
                                     * حقل الملاحظة.
                                     *
                                     * كان المعالج يمرّر `$d['feedback']` والطبقة تكتب
                                     * `sanitize_text_field('') ?: null` — **ولا حقلَ باسمه في
                                     * المشروع كلّه**. فالعمود يُصفَّر مع **كل** حفظ، وشاشة
                                     * الطالب التي تعرض ملاحظة معلّمه لا تعرض شيئًا أبدًا،
                                     * وحتى لو كُتبت قيمةٌ من مسارٍ آخر لمُحيت في أوّل حفظ.
                                     */
                                    ?>
                                    <input type="text" name="feedback" maxlength="190" style="width:180px"
                                           value="<?php echo esc_attr((string) ($r->feedback ?? '')); ?>"
                                           placeholder="<?php esc_attr_e('ملاحظة للطالب (اختيارية)', 'school-system'); ?>"
                                           aria-label="<?php esc_attr_e('ملاحظة المعلم', 'school-system'); ?>">
                                    <button class="sch-btn sch-btn--quiet"><?php esc_html_e('حفظ', 'school-system'); ?></button>
                                </form>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return;
}

$class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
$list     = $class_id > 0 ? SCH_Homework::for_class($class_id) : [];
$past     = $class_id > 0 ? SCH_Homework::for_class($class_id, false) : [];
?>
<?php SCH_Modal::head(__('الواجبات', 'school-system'), __('أسبوعية: تنزل آخر الأسبوع إلى «السابقة» ولا تُحذف — الغائب يحتاجها وأنت تحتاج سجلها.', 'school-system'), 'sch-add-hw', __('واجب جديد', 'school-system'), 'book'); ?>


<div class="sch-card">
    <h2><?php esc_html_e('واجبات الشعبة', 'school-system'); ?></h2>

    <form method="get" class="sch-toolbar">
        <select name="class_id" required aria-label="الشعبة">
            <option value=""><?php esc_html_e('اختر الشعبة…', 'school-system'); ?></option>
            <?php foreach ($classes as $c) : ?>
                <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class_id, (int) $c->id); ?>>
                    <?php echo esc_html(SCH_Classes::label($c)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض', 'school-system'); ?></button>
    </form>

    <?php if ($class_id > 0 && $list === [] && $past === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد واجبات', 'school-system'); ?></strong></div>
    <?php endif; ?>

    <?php foreach ([__('هذا الأسبوع', 'school-system') => $list, __('السابقة', 'school-system') => $past] as $heading => $group) : ?>
        <?php if ($group !== []) : ?>
            <h3 class="sch-h3"><?php echo esc_html($heading); ?></h3>
            <div class="sch-table-wrap">
                <table class="sch-table">
                    <thead><tr>
                        <th><?php esc_html_e('العنوان', 'school-system'); ?></th>
                        <th><?php esc_html_e('المادة', 'school-system'); ?></th>
                        <th><?php esc_html_e('التسليم', 'school-system'); ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($group as $h) : ?>
                        <tr>
                            <td class="sch-name"><?php echo esc_html($h->title); ?></td>
                            <td class="sch-sub"><?php echo esc_html($h->subject_name ?: '—'); ?></td>
                            <td dir="ltr"><?php echo esc_html($h->due_date); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('hw', (int) $h->id, SCH_Dashboard::url('homework'))); ?>">
                                    <?php esc_html_e('التسليمات', 'school-system'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php SCH_Modal::open('sch-add-hw', __('واجب جديد', 'school-system'), __('يظهر في تطبيق الطالب فور نشره', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('واجب جديد', 'school-system'); ?></h2>

    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('sch_add_homework', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_homework">

        <div class="sch-grid">
            <div class="sch-field">
                <label for="h-class"><?php esc_html_e('الشعبة', 'school-system'); ?></label>
                <select id="h-class" name="class_id" required>
                    <option value=""><?php esc_html_e('اختر…', 'school-system'); ?></option>
                    <?php foreach ($classes as $c) : ?>
                        <option value="<?php echo esc_attr((string) $c->id); ?>"><?php echo esc_html(SCH_Classes::label($c)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="h-subject"><?php esc_html_e('المادة', 'school-system'); ?></label>
                <select id="h-subject" name="subject_id">
                    <option value=""><?php esc_html_e('— اختياري —', 'school-system'); ?></option>
                    <?php foreach ($subjects as $s) : ?>
                        <option value="<?php echo esc_attr((string) $s->id); ?>"><?php echo esc_html($s->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="h-title"><?php esc_html_e('العنوان', 'school-system'); ?></label>
                <input id="h-title" type="text" name="title" required>
            </div>

            <div class="sch-field">
                <label for="h-answer"><?php esc_html_e('نوع الإجابة', 'school-system'); ?></label>
                <select id="h-answer" name="answer_type">
                    <?php foreach (SCH_Homework::ANSWER_TYPES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="h-due"><?php esc_html_e('تاريخ التسليم', 'school-system'); ?></label>
                <input id="h-due" type="date" name="due_date" value="<?php echo esc_attr(gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' +7 days'))); ?>">
            </div>

            <div class="sch-field">
                <label for="h-taught"><?php esc_html_e('يوم شرح الدرس', 'school-system'); ?></label>
                <input id="h-taught" type="date" name="taught_on" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
            </div>
        </div>

        <div class="sch-field sch-field--wide">
            <label for="h-body"><?php esc_html_e('نص الواجب', 'school-system'); ?></label>
            <textarea id="h-body" name="body" rows="4"></textarea>
        </div>

        <div class="sch-field sch-field--wide">
            <label for="h-file"><?php esc_html_e('مرفق', 'school-system'); ?></label>
            <input id="h-file" type="file" name="attachment" accept="application/pdf,image/jpeg,image/png">
        </div>

        <p class="sch-sub"><?php esc_html_e('يوم الشرح يجعل النظام يميّز من لم يحضر الدرس عمّن تكاسل.', 'school-system'); ?></p>

        <button class="sch-btn"><?php esc_html_e('نشر الواجب', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
