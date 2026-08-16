<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

$sch_search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
$sch_list   = SCH_Staff::list(['search' => $sch_search, 'per_page' => 50]);
?>
<?php
SCH_Modal::head(
    __('الموظفون', 'school-system'),
    sprintf(
        /* translators: %s: العدد */
        _n('%s موظف', '%s موظفين', (int) ($sch_list['total'] ?? 0), 'school-system'),
        number_format_i18n((int) ($sch_list['total'] ?? 0))
    ),
    'sch-add-emp',
    __('إضافة موظف', 'school-system')
);
?>

<?php $sch_emp = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0; ?>
<?php if ($sch_emp > 0) : ?>
    <?php SCH_Flow::next_card('staff', $sch_emp); ?>
    <?php SCH_Flow::checklist('staff', $sch_emp, __('خطوات تجهيز الموظف', 'school-system')); ?>
<?php endif; ?>

<?php
$sch_edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$sch_edit    = $sch_edit_id > 0 ? SCH_Staff::get($sch_edit_id) : null;
?>

<?php if ($sch_edit) : ?>

    <a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('employees')); ?>">
        <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <?php esc_html_e('الموظفون', 'school-system'); ?>
    </a>

    <h1 class="sch-title"><?php echo esc_html((string) $sch_edit->display_name); ?></h1>
    <p class="sch-sub"><?php esc_html_e('تعديل بيانات الموظف. الصلاحيات لها شاشتها المستقلة.', 'school-system'); ?></p>

    <div class="sch-card">
        <form method="post">
            <?php wp_nonce_field('sch_update_employee', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="update_employee">
            <input type="hidden" name="employee_id" value="<?php echo esc_attr((string) $sch_edit->id); ?>">

            <div class="sch-grid">
                <div class="sch-field">
                    <label for="u-name"><?php esc_html_e('الاسم الكامل', 'school-system'); ?></label>
                    <input id="u-name" type="text" name="full_name" required
                           value="<?php echo esc_attr((string) $sch_edit->display_name); ?>">
                </div>

                <div class="sch-field">
                    <label for="u-role"><?php esc_html_e('الدور', 'school-system'); ?></label>
                    <select id="u-role" name="role">
                        <?php foreach (SCH_Org::creatable_roles() as $sch_slug => $sch_label) : ?>
                            <option value="<?php echo esc_attr($sch_slug); ?>" <?php selected($sch_edit->role, $sch_slug); ?>>
                                <?php echo esc_html($sch_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sch-field">
                    <label for="u-title"><?php esc_html_e('المسمى الوظيفي', 'school-system'); ?></label>
                    <input id="u-title" type="text" name="job_title"
                           value="<?php echo esc_attr((string) ($sch_edit->job_title ?? '')); ?>">
                </div>

                <div class="sch-field">
                    <label for="u-dept"><?php esc_html_e('القسم', 'school-system'); ?></label>
                    <input id="u-dept" type="text" name="department"
                           value="<?php echo esc_attr((string) ($sch_edit->department ?? '')); ?>">
                </div>

                <div class="sch-field">
                    <label for="u-phone"><?php esc_html_e('الجوال', 'school-system'); ?></label>
                    <input id="u-phone" type="tel" name="phone" dir="ltr" inputmode="tel"
                           value="<?php echo esc_attr((string) ($sch_edit->phone ?? '')); ?>">
                </div>

                <div class="sch-field">
                    <label for="u-no"><?php esc_html_e('الرقم الوظيفي', 'school-system'); ?></label>
                    <input id="u-no" type="text" name="employee_no"
                           value="<?php echo esc_attr((string) ($sch_edit->employee_no ?? '')); ?>">
                </div>

                <div class="sch-field">
                    <label for="u-nid"><?php esc_html_e('رقم الهوية', 'school-system'); ?></label>
                    <input id="u-nid" type="text" name="national_id" dir="ltr"
                           value="<?php echo esc_attr((string) ($sch_edit->national_id ?? '')); ?>">
                </div>

                <div class="sch-field">
                    <label for="u-status"><?php esc_html_e('الحالة', 'school-system'); ?></label>
                    <select id="u-status" name="status">
                        <?php foreach ([
                            'active'    => __('على رأس العمل', 'school-system'),
                            'suspended' => __('موقوف', 'school-system'),
                            'left'      => __('انتهت خدمته', 'school-system'),
                        ] as $sch_slug => $sch_label) : ?>
                            <option value="<?php echo esc_attr($sch_slug); ?>" <?php selected($sch_edit->status, $sch_slug); ?>>
                                <?php echo esc_html($sch_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button class="sch-btn"><?php esc_html_e('حفظ التعديلات', 'school-system'); ?></button>
        </form>
    </div>

    <!-- الصلاحيات شاشة مستقلة: خلطها بالبيانات يجعل النموذج طويلًا ومربكًا -->
    <div class="sch-card sch-cred">
        <div>
            <h2><?php esc_html_e('صلاحيات هذا الموظف', 'school-system'); ?></h2>
            <p class="sch-sub"><?php esc_html_e('ما يظهر له في النظام، وعلى مَن يرى البيانات.', 'school-system'); ?></p>
        </div>
        <a class="sch-btn sch-btn--quiet"
           href="<?php echo esc_url(add_query_arg('user_id', (int) $sch_edit->user_id, SCH_Dashboard::url('perms'))); ?>">
            <?php esc_html_e('فتح الصلاحيات', 'school-system'); ?>
        </a>
    </div>

    <?php SCH_Flow::next_card('staff', (int) $sch_edit->user_id); ?>
    <?php SCH_Flow::checklist('staff', (int) $sch_edit->user_id, __('خطوات تجهيز الموظف', 'school-system')); ?>

    <?php return; ?>
<?php endif; ?>

<div class="sch-card">
    <form method="get" class="sch-toolbar">
        <input type="search" name="s" value="<?php echo esc_attr($sch_search); ?>"
               placeholder="<?php esc_attr_e('ابحث بالاسم أو البريد أو الرقم الوظيفي', 'school-system'); ?>">
        <button type="submit" class="sch-btn sch-btn--quiet"><?php esc_html_e('بحث', 'school-system'); ?></button>
        <span class="sch-sub"><?php echo esc_html(number_format_i18n((int) $sch_list['total'])); ?> <?php esc_html_e('موظف', 'school-system'); ?></span>
    </form>

    <?php if ((int) $sch_list['total'] === 0) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا يوجد موظفون بعد', 'school-system'); ?></strong>
            <?php esc_html_e('أضف أول موظف من النموذج أدناه. سيصله حساب دخول خاص به.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead>
                    <tr>
                        <th class="sch-th-pick"><?php SCH_Bulk::pick_all(); ?></th>
                        <th><?php esc_html_e('الاسم', 'school-system'); ?></th>
                        <th><?php esc_html_e('الدور', 'school-system'); ?></th>
                        <th><?php esc_html_e('المسمى الوظيفي', 'school-system'); ?></th>
                        <th><?php esc_html_e('الجوال', 'school-system'); ?></th>
                        <th><?php esc_html_e('الصلاحيات', 'school-system'); ?></th>
                        <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sch_list['items'] as $emp) :
                        $sch_uid    = (int) $emp->user_id;
                        $sch_custom = SCH_Perms::is_custom($sch_uid);
                        $sch_gone   = SCH_Perms::expired($sch_uid); ?>
                        <tr>
                            <td class="sch-th-pick"><?php SCH_Bulk::pick((int) $emp->id); ?></td>
                            <td class="sch-name">
                                <?php echo esc_html($emp->display_name); ?>
                                <span class="sch-sub sch-block"><?php echo esc_html($emp->user_email); ?></span>
                            </td>
                            <td><?php echo esc_html(SCH_Staff::role_label((string) $emp->role)); ?></td>
                            <td><?php echo esc_html($emp->job_title ?: '—'); ?></td>
                            <td dir="ltr"><?php echo esc_html($emp->phone ?: '—'); ?></td>

                            <td>
                                <?php if ($sch_gone) : ?>
                                    <span class="sch-badge sch-badge--danger"><?php esc_html_e('انتهت المدة', 'school-system'); ?></span>
                                <?php elseif ($sch_custom) : ?>
                                    <span class="sch-badge sch-badge--ok"><?php esc_html_e('مخصصة', 'school-system'); ?></span>
                                <?php else : ?>
                                    <span class="sch-badge sch-badge--muted"><?php esc_html_e('صلاحيات دوره', 'school-system'); ?></span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($emp->status === 'active') : ?>
                                    <span class="sch-badge sch-badge--ok"><?php esc_html_e('على رأس العمل', 'school-system'); ?></span>
                                <?php else : ?>
                                    <span class="sch-badge sch-badge--muted"><?php esc_html_e('موقوف', 'school-system'); ?></span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="sch-rowacts">
                                    <a href="<?php echo esc_url(add_query_arg('user_id', $sch_uid, SCH_Dashboard::url('perms'))); ?>"
                                       title="<?php esc_attr_e('الصلاحيات', 'school-system'); ?>"
                                       aria-label="<?php esc_attr_e('الصلاحيات', 'school-system'); ?>">
                                        <?php echo sch_icon('shield', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    </a>

                                    <a href="<?php echo esc_url(add_query_arg('edit', (int) $emp->id, SCH_Dashboard::url('employees'))); ?>"
                                       title="<?php esc_attr_e('تعديل البيانات', 'school-system'); ?>"
                                       aria-label="<?php esc_attr_e('تعديل البيانات', 'school-system'); ?>">
                                        <?php echo sch_icon('user', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    </a>

                                    <?php if ($emp->status === 'active') : ?>
                                        <form method="post" onsubmit="return confirm('<?php esc_attr_e('إيقاف وصول هذا الموظف للنظام؟ لا يُحذف — يمكنك إعادته لاحقًا.', 'school-system'); ?>');">
                                            <?php wp_nonce_field('sch_suspend_employee', '_sch_nonce'); ?>
                                            <input type="hidden" name="sch_action" value="suspend_employee">
                                            <input type="hidden" name="employee_id" value="<?php echo esc_attr((string) $emp->id); ?>">
                                            <button type="submit" title="<?php esc_attr_e('إيقاف', 'school-system'); ?>" aria-label="<?php esc_attr_e('إيقاف', 'school-system'); ?>">
                                                <?php echo sch_icon('x', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                            </button>
                                        </form>
                                    <?php else : ?>
                                        <!-- الموظف يُوقَف لا يُحذف — وإعادته بضغطة لا بإنشاء حساب جديد -->
                                        <form method="post">
                                            <?php wp_nonce_field('sch_restore_employee', '_sch_nonce'); ?>
                                            <input type="hidden" name="sch_action" value="restore_employee">
                                            <input type="hidden" name="employee_id" value="<?php echo esc_attr((string) $emp->id); ?>">
                                            <button type="submit" title="<?php esc_attr_e('إعادة للعمل', 'school-system'); ?>" aria-label="<?php esc_attr_e('إعادة للعمل', 'school-system'); ?>">
                                                <?php echo sch_icon('check', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php SCH_Bulk::bar('employees'); ?>
    <?php endif; ?>
</div>

<!-- إضافة موظف: خطوتان في نموذج واحد — بياناته ثم ما يظهر له -->


<?php SCH_Modal::open('sch-add-emp', __('إضافة موظف', 'school-system'), __('بياناته ثم ما يظهر له في النظام', 'school-system')); ?>
    <form method="post" id="sch-wiz-form" novalidate>
        <?php wp_nonce_field('sch_add_employee', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_employee">

        <div class="sch-wiz__body">
            <!-- الخطوتان على اليمين كما في الأنظمة العالمية -->
            <nav class="sch-wiz__nav">
                <button type="button" class="sch-wiz__tab is-on" data-step="1">
                    <span class="sch-wiz__n">١</span>
                    <span><b><?php esc_html_e('بيانات الموظف', 'school-system'); ?></b>
                    <em><?php esc_html_e('الاسم والبريد والدور', 'school-system'); ?></em></span>
                </button>
                <button type="button" class="sch-wiz__tab" data-step="2">
                    <span class="sch-wiz__n">٢</span>
                    <span><b><?php esc_html_e('الصلاحيات', 'school-system'); ?></b>
                    <em><?php esc_html_e('ما يظهر له في النظام', 'school-system'); ?></em></span>
                </button>
            </nav>

            <div class="sch-wiz__panes">

                <!-- ═══ الخطوة الأولى ═══ -->
                <section class="sch-wiz__pane is-on" data-pane="1">
                    <div class="sch-grid">
                        <div class="sch-field">
                            <label for="e-name"><?php esc_html_e('الاسم الكامل', 'school-system'); ?> <b class="sch-req">*</b></label>
                            <input id="e-name" type="text" name="full_name" required>
                        </div>

                        <div class="sch-field">
                            <label for="e-email"><?php esc_html_e('البريد الإلكتروني', 'school-system'); ?> <b class="sch-req">*</b></label>
                            <input id="e-email" type="email" name="email" required dir="ltr">
                        </div>

                        <div class="sch-field">
                            <label for="e-role"><?php esc_html_e('الدور', 'school-system'); ?> <b class="sch-req">*</b></label>
                            <select id="e-role" name="role" required>
                                <?php foreach (SCH_Org::creatable_roles() as $sch_slug => $sch_label) : ?>
                                    <option value="<?php echo esc_attr($sch_slug); ?>"><?php echo esc_html($sch_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sch-field">
                            <label for="e-title"><?php esc_html_e('المسمى الوظيفي', 'school-system'); ?></label>
                            <input id="e-title" type="text" name="job_title">
                        </div>

                        <div class="sch-field">
                            <label for="e-dept"><?php esc_html_e('القسم', 'school-system'); ?></label>
                            <input id="e-dept" type="text" name="department">
                        </div>

                        <div class="sch-field">
                            <label for="e-phone"><?php esc_html_e('الجوال', 'school-system'); ?></label>
                            <input id="e-phone" type="tel" name="phone" dir="ltr" inputmode="tel">
                        </div>

                        <div class="sch-field">
                            <label for="e-no"><?php esc_html_e('الرقم الوظيفي', 'school-system'); ?></label>
                            <input id="e-no" type="text" name="employee_no">
                        </div>

                        <div class="sch-field">
                            <label for="e-scope"><?php esc_html_e('نطاق الإشراف', 'school-system'); ?></label>
                            <select id="e-scope" name="stage_scope">
                                <?php foreach (SCH_Org::SCOPES as $sch_slug => $sch_label) : ?>
                                    <option value="<?php echo esc_attr($sch_slug); ?>"><?php echo esc_html($sch_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sch-field">
                            <label for="e-quota"><?php esc_html_e('النصاب الأسبوعي', 'school-system'); ?></label>
                            <input id="e-quota" type="number" name="weekly_quota" min="1" max="40" value="24">
                        </div>
                    </div>

                    <p class="sch-sub">
                        <?php esc_html_e('لا يُنشئ أحد من هو في مستواه أو فوقه — لذلك لا ترى كل الأدوار في القائمة.', 'school-system'); ?>
                    </p>

                    <div class="sch-wiz__foot">
                        <button type="button" class="sch-btn" data-next><?php esc_html_e('التالي', 'school-system'); ?></button>
                    </div>
                </section>

                <!-- ═══ الخطوة الثانية ═══ -->
                <section class="sch-wiz__pane" data-pane="2">
                    <div class="sch-permtools">
                        <div class="sch-permtools__f">
                            <label for="e-data-scope"><?php esc_html_e('على مَن يرى البيانات؟', 'school-system'); ?></label>
                            <select id="e-data-scope" name="scope">
                                <?php foreach (SCH_Perms::SCOPES as $sch_slug => $sch_label) : ?>
                                    <option value="<?php echo esc_attr($sch_slug); ?>"><?php echo esc_html($sch_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sch-permtools__f">
                            <label for="e-exp"><?php esc_html_e('تنتهي صلاحياته في', 'school-system'); ?></label>
                            <input id="e-exp" type="date" name="expires_at">
                        </div>

                        <div class="sch-permtools__f sch-permtools__f--grow">
                            <label for="e-find"><?php esc_html_e('ابحث في القائمة', 'school-system'); ?></label>
                            <input id="e-find" type="search" placeholder="<?php esc_attr_e('مثال: حضور', 'school-system'); ?>" autocomplete="off">
                        </div>

                        <div class="sch-permtools__acts">
                            <button type="button" class="sch-btn sch-btn--quiet" data-all><?php esc_html_e('فتح الكل', 'school-system'); ?></button>
                            <button type="button" class="sch-btn sch-btn--quiet" data-none><?php esc_html_e('إغلاق الكل', 'school-system'); ?></button>
                        </div>
                    </div>

                    <p class="sch-sub">
                        <?php esc_html_e('اختيار الدور يفتح مفاتيحه تلقائيًا — عدّل ما تشاء بعده.', 'school-system'); ?>
                    </p>

                    <div class="sch-permgrid sch-permgrid--norail">
                        <div class="sch-permgrid__main" id="sch-perm-list">
                            <?php foreach (SCH_Perms::grouped() as $sch_key => $sch_sections) :
                                [$sch_card, $sch_group] = explode('|', $sch_key);
                                $sch_areas = SCH_Dashboard::areas();
                                $sch_head  = trim(($sch_areas[$sch_card][0] ?? '') . ($sch_group !== '' ? ' · ' . $sch_group : '')); ?>

                                <section class="sch-pg" data-group>
                                    <div class="sch-pg__h">
                                        <button type="button" class="sch-pg__caretbtn" data-toggle aria-label="<?php esc_attr_e('طيّ أو فتح المجموعة', 'school-system'); ?>">
                                            <svg class="sch-pg__caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 10 6 6 6-6"/></svg>
                                        </button>
                                        <b><?php echo esc_html($sch_head); ?></b>
                                        <span class="sch-pg__count" data-count></span>
                                        <label class="sch-switch sch-switch--master" title="<?php esc_attr_e('تفعيل أو تعطيل المجموعة كاملة', 'school-system'); ?>">
                                            <input type="checkbox" data-master aria-label="<?php echo esc_attr($sch_head); ?>">
                                            <span class="sch-switch__t"></span>
                                        </label>
                                    </div>

                                    <div class="sch-pg__rows">
                                        <?php foreach ($sch_sections as $sch_slug => $sch_meta) :
                                            $sch_locked = isset(SCH_Perms::LOCKED[$sch_slug]);
                                            $sch_hot    = in_array($sch_slug, SCH_Perms::SENSITIVE, true); ?>

                                            <div class="sch-pr<?php echo $sch_locked ? ' is-locked' : ''; ?>"
                                                 data-row data-name="<?php echo esc_attr((string) $sch_meta[0]); ?>">
                                                <?php if ($sch_locked) : ?>
                                                    <span class="sch-pr__t">
                                                        <?php echo sch_icon((string) ($sch_meta[4] ?? 'dot'), 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                                        <b><?php echo esc_html((string) $sch_meta[0]); ?></b>
                                                    </span>
                                                    <span class="sch-pr__lock" title="<?php echo esc_attr(SCH_Perms::LOCKED[$sch_slug]); ?>">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                                                        <?php esc_html_e('مقفل', 'school-system'); ?>
                                                    </span>
                                                <?php else : ?>
                                                    <!-- نمط HighLevel: مربّع للوصول + مفتاح «يعدّل» لا يُفعَّل إلا بعده -->
                                                    <input type="hidden" name="perm[<?php echo esc_attr($sch_slug); ?>][mode]" value="none" data-mode>
                                                    <label class="sch-pr__chk">
                                                        <input type="checkbox" data-access aria-label="<?php echo esc_attr((string) $sch_meta[0]); ?>">
                                                        <span class="sch-pr__box" aria-hidden="true">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6.5 9.5 17 4 11.5"/></svg>
                                                        </span>
                                                    </label>
                                                    <span class="sch-pr__t">
                                                        <?php echo sch_icon((string) ($sch_meta[4] ?? 'dot'), 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                                        <b><?php echo esc_html((string) $sch_meta[0]); ?></b>
                                                        <?php if ($sch_hot) : ?>
                                                            <em class="sch-hot" title="<?php esc_attr_e('يصل إشعار للمدير عند منحها', 'school-system'); ?>">
                                                                <?php esc_html_e('حسّاس', 'school-system'); ?>
                                                            </em>
                                                        <?php endif; ?>
                                                    </span>
                                                    <label class="sch-switch sch-switch--edit" title="<?php esc_attr_e('يعدّل — تحكّم كامل', 'school-system'); ?>">
                                                        <input type="checkbox" data-edit>
                                                        <span class="sch-switch__t"></span>
                                                        <em><?php esc_html_e('يعدّل', 'school-system'); ?></em>
                                                    </label>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>

                        <aside class="sch-permside">
                            <div class="sch-permside__box">
                                <span class="sch-sum__k"><?php esc_html_e('ما سيراه', 'school-system'); ?></span>
                                <strong class="sch-sum__n" id="e-count">٠</strong>
                                <span class="sch-sum__of" id="e-of"></span>

                                <div class="sch-sum__bar"><i id="e-bar"></i></div>

                                <ul class="sch-sum__list" id="e-preview"></ul>
                            </div>
                        </aside>
                    </div>

                    <div class="sch-wiz__foot">
                        <button type="button" class="sch-btn sch-btn--quiet" data-back><?php esc_html_e('رجوع', 'school-system'); ?></button>
                        <button class="sch-btn" type="submit"><?php esc_html_e('حفظ الموظف', 'school-system'); ?></button>
                    </div>
                </section>

            </div>
        </div>
    </form>
<?php SCH_Modal::close(); ?>

<script>
(function () {
  var form = document.getElementById('sch-wiz-form');
  if (!form) { return; }
  var wiz = form; // النموذج هو حاوية المعالج — كان يبحث عن id غير موجود فيتعطّل الزر
  var TITLES = <?php echo wp_json_encode(array_map(
      static fn (array $m): string => (string) $m[0],
      SCH_Dashboard::sections()
  )); ?>;
  var TEMPLATES = <?php echo wp_json_encode(array_map(
      static fn (string $r): array => array_keys(SCH_Perms::template($r)),
      array_combine(array_keys(SCH_Org::creatable_roles()), array_keys(SCH_Org::creatable_roles()))
  )); ?>;

  /* ---------- الخطوتان ---------- */
  function step(n) {
    wiz.querySelectorAll('[data-pane]').forEach(function (p) {
      p.classList.toggle('is-on', p.dataset.pane === String(n));
    });
    wiz.querySelectorAll('[data-step]').forEach(function (t) {
      t.classList.toggle('is-on', t.dataset.step === String(n));
      t.classList.toggle('is-done', Number(t.dataset.step) < n);
    });
    wiz.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }

  wiz.querySelector('[data-next]').addEventListener('click', function () {
    var bad = null;
    wiz.querySelectorAll('[data-pane="1"] [required]').forEach(function (f) {
      if (!bad && !f.checkValidity()) { bad = f; }
    });
    if (bad) { bad.reportValidity(); return; }
    step(2);
  });

  /* الحفظ يفحص الخطوة الأولى أيضًا ولو كانت مخفية */
  form.addEventListener('submit', function (e) {
    var bad = null;
    wiz.querySelectorAll('[data-pane="1"] [required]').forEach(function (f) {
      if (!bad && !f.checkValidity()) { bad = f; }
    });
    if (bad) {
      e.preventDefault();
      step(1);
      setTimeout(function () { bad.focus(); bad.reportValidity(); }, 220);
    }
  });

  wiz.querySelector('[data-back]').addEventListener('click', function () { step(1); });
  wiz.querySelectorAll('[data-step]').forEach(function (t) {
    t.addEventListener('click', function () {
      if (t.dataset.step === '2') { wiz.querySelector('[data-next]').click(); } else { step(1); }
    });
  });

  /* ---------- طيّ المجموعات ---------- */
  wiz.querySelectorAll('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.closest('[data-group]').classList.toggle('is-open');
    });
  });

  /* ---------- ضبط صف (نمط HighLevel) ---------- */
  /* الخريطة: بلا وصول=none(مخفي) · وصول=view(يرى) · وصول+يعدّل=edit(يعدّل). */
  function rowMode(row) {
    var h = row.querySelector('[data-mode]');
    return h ? h.value : 'none';
  }
  function apply(row, mode) {
    var h = row.querySelector('[data-mode]');
    if (!h) { return; } // صف مقفل — يُتجاوز
    var acc = row.querySelector('[data-access]');
    var ed  = row.querySelector('[data-edit]');
    h.value = mode;
    if (acc) { acc.checked = mode !== 'none'; }
    if (ed)  { ed.checked = mode === 'edit'; ed.disabled = mode === 'none'; }
    row.classList.toggle('is-on', mode !== 'none');
    row.classList.toggle('is-edit', mode === 'edit');
  }
  function recompute(row) {
    var acc = row.querySelector('[data-access]');
    if (!acc) { return; }
    var ed = row.querySelector('[data-edit]');
    apply(row, !acc.checked ? 'none' : (ed && ed.checked ? 'edit' : 'view'));
  }

  /* ---------- قالب الدور ---------- */
  function applyTemplate(role) {
    var open = TEMPLATES[role] || [];
    wiz.querySelectorAll('[data-row]').forEach(function (row) {
      var h = row.querySelector('[data-mode]');
      if (!h) { return; }
      var slug = h.name.slice(5, h.name.indexOf(']'));
      apply(row, open.indexOf(slug) !== -1 ? 'edit' : 'none');
    });
    sync();
  }

  document.getElementById('e-role').addEventListener('change', function () { applyTemplate(this.value); });

  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches('[data-access]')) { recompute(t.closest('[data-row]')); sync(); }
    else if (t.matches('[data-edit]')) {
      var row = t.closest('[data-row]'), acc = row.querySelector('[data-access]');
      if (t.checked && acc && !acc.checked) { acc.checked = true; }
      recompute(row); sync();
    } else if (t.matches('[data-master]')) {
      var g = t.closest('[data-group]'), on = t.checked;
      g.querySelectorAll('[data-row]').forEach(function (r) { apply(r, on ? 'view' : 'none'); });
      sync();
    }
  });

  wiz.querySelector('[data-all]').addEventListener('click', function () {
    wiz.querySelectorAll('[data-row]').forEach(function (r) { apply(r, 'edit'); });
    sync();
  });
  wiz.querySelector('[data-none]').addEventListener('click', function () {
    wiz.querySelectorAll('[data-row]').forEach(function (r) { apply(r, 'none'); });
    sync();
  });

  /* ---------- البحث ---------- */
  document.getElementById('e-find').addEventListener('input', function () {
    var q = this.value.trim();
    wiz.querySelectorAll('[data-row]').forEach(function (r) {
      r.hidden = q !== '' && r.dataset.name.indexOf(q) === -1;
    });
    wiz.querySelectorAll('[data-group]').forEach(function (g) {
      var any = g.querySelector('[data-row]:not([hidden])');
      g.hidden = !any;
      if (q !== '' && any) { g.classList.add('is-open'); }
    });
  });

  /* ---------- الملخّص ---------- */
  function sync() {
    var total = 0;
    var on = 0;

    wiz.querySelectorAll('[data-group]').forEach(function (g) {
      var cnt = 0, grp = 0;
      g.querySelectorAll('[data-row]').forEach(function (r) {
        if (!r.querySelector('[data-access]')) { return; } // تجاوز المقفل
        cnt++;
        if (rowMode(r) !== 'none') { grp++; }
      });
      total += cnt;
      on += grp;

      var c = g.querySelector('[data-count]');
      if (c) {
        c.textContent = grp + ' / ' + cnt;
        c.className = 'sch-pg__count' + (grp === 0 ? ' is-off' : (grp === cnt ? ' is-full' : ''));
      }
      var m = g.querySelector('[data-master]');
      if (m) { m.checked = cnt > 0 && grp === cnt; m.indeterminate = grp > 0 && grp < cnt; }
      g.classList.toggle('is-empty', grp === 0);
    });

    document.getElementById('e-count').textContent = on;
    document.getElementById('e-of').textContent = 'من ' + total + ' قسمًا';

    var bar = document.getElementById('e-bar');
    bar.style.width = total ? Math.round((on / total) * 100) + '%' : '0';
    bar.className = on === 0 ? 'is-zero' : '';

    var list = document.getElementById('e-preview');
    list.innerHTML = '';

    if (on === 0) {
      var li = document.createElement('li');
      li.className = 'sch-sum__warn';
      li.textContent = 'لن يستطيع الدخول للنظام.';
      list.appendChild(li);
      return;
    }

    wiz.querySelectorAll('[data-row]').forEach(function (row) {
      var m = rowMode(row);
      if (m === 'none') { return; }
      var li = document.createElement('li');
      li.textContent = row.dataset.name;
      if (m === 'edit') {
        var b = document.createElement('em');
        b.textContent = 'يعدّل';
        li.appendChild(b);
      }
      list.appendChild(li);
    });
  }

  applyTemplate(document.getElementById('e-role').value);
  var first = wiz.querySelector('[data-group]');
  if (first) { first.classList.add('is-open'); }
})();
</script>
