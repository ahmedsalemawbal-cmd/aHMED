<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$buses    = SCH_Buses::all();
$routes   = SCH_Routes::all();
$drivers  = get_users(['role' => 'sch_driver', 'fields' => ['ID', 'display_name']]);
$expiring = SCH_Buses::expiring();
?>
<div class="sch-head">
    <div>
        <h1 class="sch-title"><?php esc_html_e('النقل المدرسي', 'school-system'); ?></h1>
        <p class="sch-sub">
            <?php echo esc_html(sprintf(
                /* translators: 1: عدد المسارات 2: عدد الباصات */
                __('%1$s مسارًا · %2$s باصًا', 'school-system'),
                number_format_i18n(count($routes)),
                number_format_i18n(count($buses))
            )); ?>
        </p>
    </div>

    <div class="sch-head__acts">
        <?php SCH_Modal::button('sch-add-bus', __('إضافة باص', 'school-system'), 'bus'); ?>
        <?php SCH_Modal::button('sch-add-route', __('إضافة مسار', 'school-system')); ?>
    </div>
</div>

<!-- الرحلة تخص مسارًا فمكانها معه لا في قسم مستقل -->
<div class="sch-seg">
    <a class="<?php echo esc_attr(isset($_GET['t']) ? '' : 'is-on'); ?>"
       href="<?php echo esc_url(SCH_Dashboard::url('transport')); ?>"><?php esc_html_e('المسارات والباصات', 'school-system'); ?></a>
    <a class="<?php echo esc_attr(isset($_GET['t']) ? 'is-on' : ''); ?>"
       href="<?php echo esc_url(add_query_arg('t', 'trips', SCH_Dashboard::url('transport'))); ?>"><?php esc_html_e('رحلات اليوم', 'school-system'); ?></a>
</div>

<?php if (isset($_GET['t'])) : ?>
    <?php require SCH_PATH . 'frontend/views/trips.php'; ?>
    <?php return; ?>
<?php endif; ?>

<?php $sch_route = isset($_GET['route_id']) ? absint($_GET['route_id']) : 0; ?>
<?php if ($sch_route > 0) : ?>
    <?php SCH_Flow::next_card('route', $sch_route); ?>
    <?php SCH_Flow::checklist('route', $sch_route, __('خطوات تجهيز المسار', 'school-system')); ?>
<?php endif; ?>

<?php if ($expiring !== []) : ?>
    <div class="sch-notice sch-notice--error">
        <strong><?php esc_html_e('استمارات على وشك الانتهاء', 'school-system'); ?></strong>
        <?php foreach ($expiring as $b) : ?>
            <div class="sch-sub"><?php echo esc_html($b->plate_no . ' — ' . $b->registration_expiry); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="sch-card">
    <h2><?php esc_html_e('المسارات', 'school-system'); ?></h2>
    <?php if ($routes === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا توجد مسارات', 'school-system'); ?></strong>
            <?php esc_html_e('أضف باصًا ثم مسارًا بالأزرار أعلى الشاشة، ثم اشترك الطلاب فيه.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('المسار', 'school-system'); ?></th>
                    <th><?php esc_html_e('الباص', 'school-system'); ?></th>
                    <th><?php esc_html_e('الاتجاه', 'school-system'); ?></th>
                    <th><?php esc_html_e('نقاط التوقف', 'school-system'); ?></th>
                    <th><?php esc_html_e('المشتركون', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($routes as $r) : ?>
                    <tr>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(SCH_Dashboard::url('transport', (int) $r->id)); ?>"><?php echo esc_html($r->name); ?></a>
                        </td>
                        <td dir="ltr"><?php echo esc_html($r->plate_no ?: '—'); ?></td>
                        <td><?php echo esc_html($r->direction === 'morning' ? __('ذهاب', 'school-system') : ($r->direction === 'afternoon' ? __('عودة', 'school-system') : __('ذهاب وعودة', 'school-system'))); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $r->stops)); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $r->riders)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<div class="sch-card">
    <h2><?php esc_html_e('الباصات', 'school-system'); ?></h2>
    <?php if ($buses === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد باصات', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('اللوحة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الموديل', 'school-system'); ?></th>
                    <th><?php esc_html_e('السعة', 'school-system'); ?></th>
                    <th><?php esc_html_e('السائق', 'school-system'); ?></th>
                    <th><?php esc_html_e('انتهاء الاستمارة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($buses as $b) :
                    $driver = $b->driver_user_id ? get_user_by('id', (int) $b->driver_user_id) : null; ?>
                    <tr>
                        <td class="sch-name" dir="ltr"><?php echo esc_html($b->plate_no); ?></td>
                        <td><?php echo esc_html($b->model ?: '—'); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $b->capacity)); ?></td>
                        <td><?php echo esc_html($driver ? $driver->display_name : '—'); ?></td>
                        <td dir="ltr"><?php echo esc_html($b->registration_expiry ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php SCH_Modal::open('sch-add-route', __('إضافة مسار', 'school-system'), __('النقاط وإحداثياتها تُضاف بعد الإنشاء', 'school-system')); ?>
    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-mt">
        <?php wp_nonce_field('sch_add_route', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_route">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="r-name"><?php esc_html_e('اسم المسار', 'school-system'); ?></label>
                <input id="r-name" type="text" name="name" placeholder="<?php esc_attr_e('حي النرجس', 'school-system'); ?>" required>
            </div>
            <div class="sch-field">
                <label for="r-bus"><?php esc_html_e('الباص', 'school-system'); ?></label>
                <select id="r-bus" name="bus_id">
                    <option value=""><?php esc_html_e('بلا باص', 'school-system'); ?></option>
                    <?php foreach ($buses as $b) : ?>
                        <option value="<?php echo esc_attr((string) $b->id); ?>"><?php echo esc_html($b->plate_no); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="r-dir"><?php esc_html_e('الاتجاه', 'school-system'); ?></label>
                <select id="r-dir" name="direction">
                    <option value="both"><?php esc_html_e('ذهاب وعودة', 'school-system'); ?></option>
                    <option value="morning"><?php esc_html_e('ذهاب فقط', 'school-system'); ?></option>
                    <option value="afternoon"><?php esc_html_e('عودة فقط', 'school-system'); ?></option>
                </select>
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('إضافة مسار', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>

<?php SCH_Modal::open('sch-add-bus', __('إضافة باص', 'school-system'), __('اللوحة والسعة والسائق', 'school-system')); ?>
    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-mt">
        <?php wp_nonce_field('sch_add_bus', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_bus">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="b-plate"><?php esc_html_e('رقم اللوحة', 'school-system'); ?></label>
                <input id="b-plate" type="text" name="plate_no" dir="ltr" required>
            </div>
            <div class="sch-field">
                <label for="b-model"><?php esc_html_e('الموديل', 'school-system'); ?></label>
                <input id="b-model" type="text" name="model">
            </div>
            <div class="sch-field">
                <label for="b-cap"><?php esc_html_e('السعة', 'school-system'); ?></label>
                <input id="b-cap" type="number" name="capacity" value="20" min="1" max="80">
            </div>
            <div class="sch-field">
                <label for="b-driver"><?php esc_html_e('السائق', 'school-system'); ?></label>
                <select id="b-driver" name="driver_user_id">
                    <option value=""><?php esc_html_e('بلا سائق', 'school-system'); ?></option>
                    <?php foreach ($drivers as $u) : ?>
                        <option value="<?php echo esc_attr((string) $u->ID); ?>"><?php echo esc_html($u->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="b-exp"><?php esc_html_e('انتهاء الاستمارة', 'school-system'); ?></label>
                <input id="b-exp" type="date" name="registration_expiry">
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('إضافة باص', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
