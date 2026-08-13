<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$q    = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
$list = SCH_Guardians::list(['search' => $q]);
?>
<?php
SCH_Modal::head(
    __('أولياء الأمور', 'school-system'),
    sprintf(
        /* translators: %s: العدد */
        __('%s ولي أمر', 'school-system'),
        number_format_i18n((int) $list['total'])
    ),
    'sch-add-guardian',
    __('إضافة ولي أمر', 'school-system')
);
?>

<div class="sch-card">
    <form method="get" class="sch-toolbar">
        <input type="search" name="s" value="<?php echo esc_attr($q); ?>" placeholder="<?php esc_attr_e('ابحث بالاسم أو الجوال أو الهوية', 'school-system'); ?>">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('بحث', 'school-system'); ?></button>
    </form>

    <?php if ((int) $list['total'] === 0) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا يوجد أولياء أمور بعد', 'school-system'); ?></strong>
            <?php esc_html_e('أضفه بالزر أعلى الشاشة، أو استورد الطلاب مع بيانات أولياء أمورهم.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('رقم ولي الأمر', 'school-system'); ?></th>
                    <th><?php esc_html_e('الاسم', 'school-system'); ?></th>
                    <th><?php esc_html_e('الجوال', 'school-system'); ?></th>
                    <th><?php esc_html_e('رقم الهوية', 'school-system'); ?></th>
                    <th><?php esc_html_e('الأبناء', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($list['items'] as $g) : ?>
                    <tr>
                        <td class="sch-mono" dir="ltr"><?php echo esc_html($g->parent_no ?: '—'); ?></td>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(SCH_Dashboard::url('guardians', (int) $g->id)); ?>"><?php echo esc_html($g->display_name); ?></a>
                        </td>
                        <td dir="ltr"><?php echo esc_html($g->phone ?: '—'); ?></td>
                        <td dir="ltr"><?php echo esc_html($g->national_id ?: '—'); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $g->children_count)); ?></td>
                        <td>
                            <?php if ($g->status === 'active') : ?>
                                <span class="sch-badge sch-badge--ok"><?php esc_html_e('نشط', 'school-system'); ?></span>
                            <?php else : ?>
                                <span class="sch-badge sch-badge--muted"><?php esc_html_e('موقوف', 'school-system'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php SCH_Modal::open('sch-add-guardian', __('إضافة ولي أمر', 'school-system'), __('يُنشأ حسابه فورًا، وكلمة المرور تظهر مرة واحدة', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('إضافة ولي أمر', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('رقم الجوال هو اسم الدخول للتطبيق. تظهر كلمة المرور مرة واحدة بعد الحفظ.', 'school-system'); ?></p>

    <form method="post">
        <?php wp_nonce_field('sch_add_guardian', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_guardian">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="g-first"><?php esc_html_e('الاسم الأول', 'school-system'); ?> *</label>
                <input id="g-first" type="text" name="first_name" required>
            </div>
            <div class="sch-field">
                <label for="g-father"><?php esc_html_e('اسم الأب', 'school-system'); ?></label>
                <input id="g-father" type="text" name="father_name">
            </div>
            <div class="sch-field">
                <label for="g-grand"><?php esc_html_e('اسم الجد', 'school-system'); ?></label>
                <input id="g-grand" type="text" name="grand_name">
            </div>
            <div class="sch-field">
                <label for="g-family"><?php esc_html_e('اسم العائلة', 'school-system'); ?> *</label>
                <input id="g-family" type="text" name="family_name" required>
            </div>
            <div class="sch-field">
                <label for="g-idtype"><?php esc_html_e('نوع الهوية', 'school-system'); ?></label>
                <select id="g-idtype" name="id_type">
                    <?php foreach (SCH_Enrollment::ID_TYPES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="g-nid"><?php esc_html_e('رقم الهوية', 'school-system'); ?></label>
                <input id="g-nid" type="text" name="national_id" dir="ltr" inputmode="numeric" pattern="\d{10}" maxlength="10">
            </div>
            <div class="sch-field">
                <label for="g-phone"><?php esc_html_e('رقم الجوال', 'school-system'); ?> *</label>
                <input id="g-phone" type="tel" name="phone" dir="ltr" inputmode="tel" placeholder="05xxxxxxxx" required>
            </div>
            <div class="sch-field">
                <label for="g-email"><?php esc_html_e('البريد الإلكتروني', 'school-system'); ?></label>
                <input id="g-email" type="email" name="email" dir="ltr">
            </div>
            <div class="sch-field">
                <label for="g-city"><?php esc_html_e('المدينة', 'school-system'); ?></label>
                <input id="g-city" type="text" name="city">
            </div>
            <div class="sch-field">
                <label for="g-addr"><?php esc_html_e('العنوان', 'school-system'); ?></label>
                <input id="g-addr" type="text" name="address">
            </div>
            <div class="sch-field">
                <label for="g-work"><?php esc_html_e('جهة العمل', 'school-system'); ?></label>
                <input id="g-work" type="text" name="workplace">
            </div>
            <div class="sch-field">
                <label for="g-alt"><?php esc_html_e('جوال بديل', 'school-system'); ?></label>
                <input id="g-alt" type="tel" name="alt_phone" dir="ltr">
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('حفظ ولي الأمر', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
