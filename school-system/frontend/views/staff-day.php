<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$staff = SCH_Deputy::staff_sheet();
?>
<h1 class="sch-title"><?php echo sch_icon('check', 22); ?><?php esc_html_e('حضور المعلمين', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('رصد الغياب يولّد مقترحات الاحتياط تلقائيًا.', 'school-system'); ?></p>

<div class="sch-card">
    <?php if ($staff === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا يوجد موظفون', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الموظف', 'school-system'); ?></th>
                    <th><?php esc_html_e('الوظيفة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($staff as $s) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($s->display_name); ?></td>
                        <td class="sch-sub"><?php echo esc_html($s->job_title ?: '—'); ?></td>
                        <td>
                            <?php
                            if ((int) $s->on_leave > 0) {
                                echo sch_wave_pill('leave', __('إجازة معتمدة', 'school-system')); // phpcs:ignore WordPress.Security.EscapeOutput
                            } elseif ($s->status) {
                                $sch_live = in_array($s->status, ['present', 'late'], true);
                                $sch_lbl  = SCH_Deputy::STAFF_STATUSES[$s->status] ?? '';
                                if ($sch_live && !empty($s->checked_at)) {
                                    $sch_lbl .= ' · ' . substr((string) $s->checked_at, 11, 5);
                                }
                                echo sch_wave_pill((string) $s->status, $sch_lbl, -1, $sch_live); // phpcs:ignore WordPress.Security.EscapeOutput
                            } else {
                                echo sch_wave_pill('none'); // phpcs:ignore WordPress.Security.EscapeOutput
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ((int) $s->on_leave === 0) : ?>
                                <div class="sch-inline">
                                    <?php foreach (['present', 'late', 'absent'] as $slug) : ?>
                                        <form method="post">
                                            <?php wp_nonce_field('sch_mark_staff', '_sch_nonce'); ?>
                                            <input type="hidden" name="sch_action" value="mark_staff">
                                            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $s->user_id); ?>">
                                            <input type="hidden" name="status" value="<?php echo esc_attr($slug); ?>">
                                            <button class="sch-btn sch-btn--quiet"><?php echo esc_html(SCH_Deputy::STAFF_STATUSES[$slug]); ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
