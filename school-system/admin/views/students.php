<?php
/**
 * شاشة الطلاب.
 * متاح من SCH_Admin::render_students(): $result, $search, $page
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$sch_can_manage = current_user_can('sch_manage_students');
$sch_total      = (int) $result['total'];
?>
<div class="sch-app">

    <h1 class="sch-page-title"><?php esc_html_e('الطلاب', 'school-system'); ?></h1>

    <?php if (isset($_GET['sch_saved'])) : ?>
        <div class="sch-notice sch-notice--ok"><?php esc_html_e('أُضيف الطالب.', 'school-system'); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['sch_error'])) : ?>
        <div class="sch-notice sch-notice--error">
            <?php esc_html_e('تعذّر الحفظ. تأكد من الاسم ومن أن رقم الهوية غير مسجّل لطالب آخر.', 'school-system'); ?>
        </div>
    <?php endif; ?>

    <div class="sch-card">
        <form method="get" class="sch-toolbar">
            <input type="hidden" name="page" value="<?php echo esc_attr(SCH_Admin::SLUG); ?>">
            <input
                type="search"
                name="s"
                value="<?php echo esc_attr($search); ?>"
                placeholder="<?php esc_attr_e('ابحث بالاسم أو رقم الهوية', 'school-system'); ?>">
            <button type="submit" class="sch-btn sch-btn--quiet"><?php esc_html_e('بحث', 'school-system'); ?></button>
            <span class="sch-sub">
                <?php
                printf(
                    /* translators: %s: عدد الطلاب */
                    esc_html__('%s طالب', 'school-system'),
                    esc_html(number_format_i18n($sch_total))
                );
                ?>
            </span>
        </form>

        <?php if ($sch_total === 0) : ?>
            <div class="sch-empty">
                <strong><?php esc_html_e('لا يوجد طلاب بعد', 'school-system'); ?></strong>
                <?php esc_html_e('أضف أول طالب من النموذج أدناه، أو استورد قائمة من ملف Excel.', 'school-system'); ?>
            </div>
        <?php else : ?>
            <div class="sch-table-wrap"><table class="sch-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('الاسم', 'school-system'); ?></th>
                        <th><?php esc_html_e('المرحلة', 'school-system'); ?></th>
                        <th><?php esc_html_e('الصف', 'school-system'); ?></th>
                        <th><?php esc_html_e('الشعبة', 'school-system'); ?></th>
                        <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['items'] as $student) : ?>
                        <tr>
                            <td class="sch-name"><?php echo esc_html($student->full_name); ?></td>
                            <td><?php echo esc_html($student->stage ?: '—'); ?></td>
                            <td><?php echo esc_html($student->grade_level ?: '—'); ?></td>
                            <td><?php echo esc_html($student->section ?: '—'); ?></td>
                            <td><span class="sch-badge sch-badge--ok"><?php esc_html_e('نشط', 'school-system'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>

    <?php if ($sch_can_manage) : ?>
        <div class="sch-card">
            <h2><?php esc_html_e('إضافة طالب', 'school-system'); ?></h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sch_save_student">
                <?php wp_nonce_field('sch_save_student'); ?>

                <div class="sch-grid">
                    <div class="sch-field">
                        <label for="sch-name"><?php esc_html_e('الاسم الكامل', 'school-system'); ?></label>
                        <input id="sch-name" type="text" name="full_name" required>
                    </div>

                    <div class="sch-field">
                        <label for="sch-nid"><?php esc_html_e('رقم الهوية', 'school-system'); ?></label>
                        <input id="sch-nid" type="text" name="national_id" inputmode="numeric">
                    </div>

                    <div class="sch-field">
                        <label for="sch-stage"><?php esc_html_e('المرحلة', 'school-system'); ?></label>
                        <select id="sch-stage" name="stage">
                            <option value="kg"><?php esc_html_e('رياض أطفال', 'school-system'); ?></option>
                            <option value="primary"><?php esc_html_e('ابتدائي', 'school-system'); ?></option>
                            <option value="intermediate"><?php esc_html_e('متوسط', 'school-system'); ?></option>
                            <option value="secondary"><?php esc_html_e('ثانوي', 'school-system'); ?></option>
                        </select>
                    </div>

                    <div class="sch-field">
                        <label for="sch-grade"><?php esc_html_e('الصف', 'school-system'); ?></label>
                        <input id="sch-grade" type="text" name="grade_level">
                    </div>

                    <div class="sch-field">
                        <label for="sch-section"><?php esc_html_e('الشعبة', 'school-system'); ?></label>
                        <input id="sch-section" type="text" name="section">
                    </div>
                </div>

                <button type="submit" class="sch-btn"><?php esc_html_e('حفظ الطالب', 'school-system'); ?></button>
            </form>
        </div>
    <?php endif; ?>

</div>
