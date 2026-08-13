<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$supervisors = SCH_Org::supervisors();
$creatable   = SCH_Org::creatable_roles();
$my_level    = SCH_Org::level_of();
$principal   = SCH_Org::principal_user_id();
$deputy      = SCH_Org::deputy_user_id();
?>
<h1 class="sch-title"><?php echo sch_icon('users', 22); ?><?php esc_html_e('الهيكل التنظيمي', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('من يملك القرار يملك الإضافة، ومن يملك التنفيذ يملك التفاصيل.', 'school-system'); ?></p>

<!-- الشجرة: يفهمها الموظف الجديد في نظرة -->
<div class="sch-card">
    <h2><?php esc_html_e('السلسلة', 'school-system'); ?></h2>

    <div class="sch-tree">
        <div class="sch-tree__node sch-tree__node--top">
            <strong><?php esc_html_e('مدير المدرسة', 'school-system'); ?></strong>
            <span><?php echo esc_html($principal ? (get_userdata($principal)->display_name ?? '') : __('غير معيّن', 'school-system')); ?></span>
            <em><?php esc_html_e('يعتمد الجدول · يرى ما تعثّر', 'school-system'); ?></em>
        </div>

        <div class="sch-tree__link"></div>

        <div class="sch-tree__node">
            <strong><?php esc_html_e('وكيل الشؤون التعليمية', 'school-system'); ?></strong>
            <span><?php echo esc_html($deputy ? (get_userdata($deputy)->display_name ?? '') : __('غير معيّن', 'school-system')); ?></span>
            <em><?php esc_html_e('يبني الجدول · يوزّع النصاب · يضيف المشرفين', 'school-system'); ?></em>
        </div>

        <div class="sch-tree__link"></div>

        <div class="sch-tree__row">
            <?php foreach (['early', 'middle', 'high'] as $scope) :
                $who = null;
                foreach ($supervisors as $s) {
                    if ($s->stage_scope === $scope) { $who = $s; break; }
                } ?>
                <div class="sch-tree__node<?php echo $who ? '' : ' is-empty'; ?>">
                    <strong><?php echo esc_html(SCH_Org::SCOPES[$scope]); ?></strong>
                    <span><?php echo esc_html($who->display_name ?? __('لا يوجد مشرف', 'school-system')); ?></span>
                    <em><?php esc_html_e('يغطي حصص معلميه يوميًا', 'school-system'); ?></em>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <p class="sch-sub sch-mt">
        <?php esc_html_e('الأخصائية والصحة المدرسية والحارس والسائقون خدمات على مستوى المدرسة — لا يتبعون مشرفًا.', 'school-system'); ?>
    </p>
</div>

<!-- سلسلة الإنشاء -->
<div class="sch-card">
    <h2><?php esc_html_e('من تستطيع إضافته', 'school-system'); ?></h2>
    <p class="sch-sub">
        <?php esc_html_e('لا يُنشئ أحد من هو في مستواه أو فوقه — بلا هذا القيد يصنع موظف لنفسه صلاحيات أعلى ولا تعرف.', 'school-system'); ?>
    </p>

    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th><?php esc_html_e('الدور', 'school-system'); ?></th>
                <th><?php esc_html_e('المستوى', 'school-system'); ?></th>
                <th><?php esc_html_e('تستطيع إضافته', 'school-system'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach (SCH_Staff::ROLES as $role => $label) :
                $level = SCH_Org::LEVELS[$role] ?? 0; ?>
                <tr>
                    <td class="sch-name"><?php echo esc_html($label); ?></td>
                    <td><?php echo esc_html((string) $level); ?></td>
                    <td>
                        <?php if (isset($creatable[$role])) : ?>
                            <span class="sch-badge sch-badge--ok"><?php esc_html_e('نعم', 'school-system'); ?></span>
                        <?php else : ?>
                            <span class="sch-badge sch-badge--muted"><?php esc_html_e('لا', 'school-system'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="sch-sub sch-mt">
        <?php echo esc_html(sprintf(
            /* translators: %d: مستوى المستخدم */
            __('مستواك: %d', 'school-system'),
            $my_level
        )); ?>
        · <a href="<?php echo esc_url(SCH_Dashboard::url('employees')); ?>"><?php esc_html_e('إضافة موظف', 'school-system'); ?></a>
    </p>
</div>

<?php if ($supervisors !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('المشرفون', 'school-system'); ?></h2>
    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th><?php esc_html_e('المشرف', 'school-system'); ?></th>
                <th><?php esc_html_e('النطاق', 'school-system'); ?></th>
                <th><?php esc_html_e('الجوال', 'school-system'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($supervisors as $s) : ?>
                <tr>
                    <td class="sch-name"><?php echo esc_html($s->display_name); ?></td>
                    <td><?php echo esc_html(SCH_Org::SCOPES[$s->stage_scope] ?? ''); ?></td>
                    <td dir="ltr"><?php echo esc_html($s->phone ?: '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
