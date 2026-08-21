<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$assets = SCH_Assets::all();
$works  = SCH_Assets::work_orders();
?>
<h1 class="sch-title"><?php echo sch_icon('tool', 22); ?><?php esc_html_e('الأصول والصيانة', 'school-system'); ?></h1>

<div class="sch-card">
    <h2><?php esc_html_e('أوامر الصيانة', 'school-system'); ?></h2>
    <?php if ($works === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد أوامر', 'school-system'); ?></strong><?php esc_html_e('سجّل بلاغ صيانة ليظهر هنا مرتبًا حسب الأولوية.', 'school-system'); ?></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('البلاغ', 'school-system'); ?></th>
                    <th><?php esc_html_e('الأصل', 'school-system'); ?></th>
                    <th><?php esc_html_e('الموقع', 'school-system'); ?></th>
                    <th><?php esc_html_e('الأولوية', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($works as $w) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($w->title); ?></td>
                        <td><?php echo esc_html($w->asset_name ?: '—'); ?></td>
                        <td><?php echo esc_html($w->location ?: '—'); ?></td>
                        <td><?php echo esc_html(SCH_Assets::PRIORITIES[$w->priority] ?? $w->priority); ?></td>
                        <td>
                            <span class="sch-badge <?php echo $w->status === 'done' ? 'sch-badge--ok' : ''; ?>">
                                <?php echo esc_html(SCH_Assets::WORK_STATUSES[$w->status] ?? $w->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($w->status !== 'done') : ?>
                                <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-inline">
                                    <?php wp_nonce_field('sch_set_work', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="set_work">
                                    <input type="hidden" name="work_id" value="<?php echo esc_attr((string) $w->id); ?>">
                                    <select name="status" aria-label="الحالة">
                                        <?php foreach (SCH_Assets::WORK_STATUSES as $slug => $label) : ?>
                                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($w->status, $slug); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="sch-btn sch-btn--quiet"><?php esc_html_e('تحديث', 'school-system'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-mt">
        <?php wp_nonce_field('sch_add_work', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_work">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="w-title"><?php esc_html_e('عنوان البلاغ', 'school-system'); ?></label>
                <input id="w-title" type="text" name="title" required>
            </div>
            <div class="sch-field">
                <label for="w-asset"><?php esc_html_e('الأصل', 'school-system'); ?></label>
                <select id="w-asset" name="asset_id">
                    <option value=""><?php esc_html_e('غير مرتبط بأصل', 'school-system'); ?></option>
                    <?php foreach ($assets as $a) : ?>
                        <option value="<?php echo esc_attr((string) $a->id); ?>"><?php echo esc_html($a->asset_code . ' — ' . $a->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="w-loc"><?php esc_html_e('الموقع', 'school-system'); ?></label>
                <input id="w-loc" type="text" name="location">
            </div>
            <div class="sch-field">
                <label for="w-pri"><?php esc_html_e('الأولوية', 'school-system'); ?></label>
                <select id="w-pri" name="priority">
                    <?php foreach (SCH_Assets::PRIORITIES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug, 'medium'); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('تسجيل بلاغ', 'school-system'); ?></button>
    </form>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('سجل الأصول', 'school-system'); ?></h2>
    <?php if ($assets === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد أصول', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الرمز', 'school-system'); ?></th>
                    <th><?php esc_html_e('الأصل', 'school-system'); ?></th>
                    <th><?php esc_html_e('الفئة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الموقع', 'school-system'); ?></th>
                    <th><?php esc_html_e('التكلفة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($assets as $a) : ?>
                    <tr>
                        <td class="sch-mono" dir="ltr"><?php echo esc_html($a->asset_code); ?></td>
                        <td class="sch-name"><?php echo esc_html($a->name); ?></td>
                        <td><?php echo esc_html($a->category ?: '—'); ?></td>
                        <td><?php echo esc_html($a->location ?: '—'); ?></td>
                        <td><?php echo esc_html(sch_money($a->cost)); ?></td>
                        <td><?php echo esc_html(SCH_Assets::STATUSES[$a->status] ?? $a->status); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar sch-mt">
        <?php wp_nonce_field('sch_add_asset', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_asset">
        <input type="text" name="asset_code" placeholder="<?php esc_attr_e('الرمز', 'school-system'); ?>" dir="ltr" required style="max-width:120px">
        <input type="text" name="name" placeholder="<?php esc_attr_e('اسم الأصل', 'school-system'); ?>" required>
        <input type="text" name="category" placeholder="<?php esc_attr_e('الفئة', 'school-system'); ?>">
        <input type="text" name="location" placeholder="<?php esc_attr_e('الموقع', 'school-system'); ?>">
        <input type="number" name="cost" step="0.01" min="0" placeholder="<?php esc_attr_e('التكلفة', 'school-system'); ?>">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إضافة أصل', 'school-system'); ?></button>
    </form>
</div>
