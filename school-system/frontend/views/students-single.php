<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$student = SCH_Students::get($id);

if (!$student) {
    echo '<div class="sch-empty"><strong>' . esc_html__('الطالب غير موجود', 'school-system') . '</strong></div>';
    return;
}

$class     = SCH_Students::current_class($id);
$guardians = SCH_Guardians::of_student($id);
$classes   = SCH_Classes::list();
$all_g     = SCH_Guardians::list(['per_page' => 200]);
$docs      = SCH_Enrollment::docs($id);
$health    = SCH_Enrollment::health($id);
$sub       = SCH_Routes::subscription_of($id);
$can       = current_user_can('sch_manage_students');
$full      = SCH_Enrollment::full_name($student);
$rate      = SCH_Attendance::rate($id);
$card_data = SCH_Assessment::report_card($id);
$avg       = $card_data !== []
    ? round(array_sum(array_map(static fn (array $s): float => (float) $s['percent'], $card_data)) / count($card_data), 1)
    : null;
$balance   = 0.0;
foreach (SCH_Finance::of_student($id) as $inv) {
    $balance += (float) $inv->total - (float) $inv->paid;
}
$timeline  = SCH_Enrollment::timeline($id);
$photo_url = $student->photo_file
    ? add_query_arg('sch_photo', '1', SCH_Dashboard::url('students', $id))
    : '';
?>
<a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('students')); ?>">&larr; <?php esc_html_e('كل الطلاب', 'school-system'); ?></a>

<!-- الهوية: بطاقة ثابتة تُقرأ ولا يُلعب بها -->
<div class="sch-idbar">
    <a class="sch-idbar__dl" target="_blank" rel="noopener"
       href="<?php echo esc_url(add_query_arg('sch_doc', '1', SCH_Dashboard::url('students', $id))); ?>"
       title="<?php esc_attr_e('تنزيل وثيقة A4', 'school-system'); ?>"
       aria-label="<?php esc_attr_e('تنزيل وثيقة A4', 'school-system'); ?>">
        <?php echo sch_icon('download', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>
</div>

<div class="sch-stage" id="sch-stage">
    <article class="sch-idc" id="sch-idc">
        <header class="sch-idc__head">
            <svg class="sch-guilloche" viewBox="0 0 600 60" preserveAspectRatio="none" aria-hidden="true">
                <g fill="none" stroke="#94A3B8" stroke-width=".6">
                    <path d="M0 30 Q 25 4 50 30 T 100 30 T 150 30 T 200 30 T 250 30 T 300 30 T 350 30 T 400 30 T 450 30 T 500 30 T 550 30 T 600 30"/>
                    <path d="M0 30 Q 25 56 50 30 T 100 30 T 150 30 T 200 30 T 250 30 T 300 30 T 350 30 T 400 30 T 450 30 T 500 30 T 550 30 T 600 30"/>
                    <path d="M0 30 Q 40 8 80 30 T 160 30 T 240 30 T 320 30 T 400 30 T 480 30 T 560 30 T 640 30"/>
                </g>
            </svg>
            <span class="sch-idc__school"><?php echo esc_html(sch_settings('school_name', get_bloginfo('name'))); ?></span>
            <span class="sch-idc__doc">STUDENT IDENTITY</span>
        </header>

        <div class="sch-idc__body">
            <div class="sch-idc__photo">
                <div class="sch-idc__frame">
                    <?php if ($photo_url) : ?>
                        <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($full); ?>" width="150" height="186">
                    <?php else : ?>
                        <svg viewBox="0 0 100 124" width="150" height="186" aria-hidden="true">
                            <rect width="100" height="124" fill="#EEF2FF"/>
                            <circle cx="50" cy="44" r="20" fill="#5170FF" opacity=".22"/>
                            <path d="M14 124c0-22 16-34 36-34s36 12 36 34z" fill="#5170FF" opacity=".22"/>
                        </svg>
                    <?php endif; ?>
                </div>
                <span class="sch-idc__ring"></span>
                <span class="sch-idc__tag">
                    <?php echo esc_html($class ? SCH_Classes::label($class) : __('غير مسجَّل', 'school-system')); ?>
                </span>
            </div>

            <div>
                <div class="sch-idc__who">
                    <h1><?php echo esc_html($full); ?></h1>
                    <span dir="ltr"><bdi><?php echo esc_html($student->name_en ?: ''); ?></bdi></span>
                </div>

                <dl class="sch-idc__fields">
                    <div><dt><?php esc_html_e('الرقم الأكاديمي', 'school-system'); ?></dt>
                        <dd><span class="sch-emboss"><?php echo esc_html($student->academic_no ?: '—'); ?></span></dd></div>
                    <div><dt><?php esc_html_e('رقم الطالب', 'school-system'); ?></dt><dd dir="ltr"><?php echo esc_html($student->student_no ?: '—'); ?></dd></div>
                    <div><dt><?php esc_html_e('رقم الهوية', 'school-system'); ?></dt><dd dir="ltr"><?php echo esc_html($student->national_id ?: '—'); ?></dd></div>
                    <div><dt><?php esc_html_e('الجنسية', 'school-system'); ?></dt><dd><?php echo esc_html($student->nationality ?: '—'); ?></dd></div>
                    <div><dt><?php esc_html_e('تاريخ الميلاد', 'school-system'); ?></dt><dd dir="ltr"><?php echo esc_html($student->birth_date ?: '—'); ?></dd></div>
                    <div><dt><?php esc_html_e('الجنس', 'school-system'); ?></dt><dd><?php echo esc_html($student->gender === 'female' ? __('أنثى', 'school-system') : __('ذكر', 'school-system')); ?></dd></div>
                    <div><dt><?php esc_html_e('المرحلة', 'school-system'); ?></dt><dd><?php echo esc_html(SCH_Classes::STAGES[$student->stage] ?? '—'); ?></dd></div>
                    <div><dt><?php esc_html_e('الصف والفصل', 'school-system'); ?></dt><dd><?php echo esc_html($class ? $class->grade_level . ' / ' . $class->section : '—'); ?></dd></div>
                    <div><dt><?php esc_html_e('تاريخ التسجيل', 'school-system'); ?></dt><dd dir="ltr"><?php echo esc_html($student->enrolled_at ?: '—'); ?></dd></div>
                </dl>
            </div>

            <div class="sch-idc__qr">
                <div class="sch-idc__qrbox">
                    <?php echo SCH_QR::svg((string) ($student->academic_no ?: $student->qr_token), 4, 2); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>
                <span class="sch-mono" dir="ltr"><?php echo esc_html($student->academic_no ?: ''); ?></span>
            </div>
        </div>
    </article>
</div>

<!-- بيانات دخول الطالب: تظهر مرة عند التسجيل ثم تُفقد،
     فمن فقدها كان يعلق. تُولَّد من جديد بضغطة. -->
<div class="sch-card sch-cred">
    <div>
        <h2><?php esc_html_e('دخول الطالب للتطبيق', 'school-system'); ?></h2>
        <p class="sch-sub">
            <?php echo esc_html($student->user_id
                ? __('اسم المستخدم هو حرف s متبوعًا برقمه الأكاديمي. ولّد كلمة مرور جديدة وسلّمها له.', 'school-system')
                : __('لا حساب لهذا الطالب بعد — ستُنشئه الضغطة.', 'school-system')); ?>
        </p>
    </div>

    <form method="post">
        <?php wp_nonce_field('sch_student_login', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="student_login">
        <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $student->id); ?>">
        <button class="sch-btn">
            <?php echo esc_html($student->user_id
                ? __('كلمة مرور جديدة', 'school-system')
                : __('إنشاء حساب الطالب', 'school-system')); ?>
        </button>
    </form>
</div>

<?php SCH_Flow::next_card('student', (int) $student->id); ?>
<?php SCH_Flow::checklist('student', (int) $student->id, __('خطوات إكمال الملف', 'school-system')); ?>



<div class="sch-stats">
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format($rate, 1)); ?>%</span>
        <span class="sch-stat__label"><?php esc_html_e('نسبة الحضور — 60 يومًا', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo $avg !== null ? esc_html(number_format($avg, 1) . '%') : '—'; ?></span>
        <span class="sch-stat__label"><?php esc_html_e('المعدل العام', 'school-system'); ?></span>
    </div>
    <div class="sch-stat<?php echo $balance > 0 ? ' sch-stat--warn' : ''; ?>">
        <span class="sch-stat__num"><?php echo esc_html(number_format($balance, 0)); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('المتبقي من الرسوم (ر.س)', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html($sub ? $sub->route_name : __('لا يوجد', 'school-system')); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('النقل المدرسي', 'school-system'); ?></span>
    </div>
</div>

<?php if ($card_data !== [] || $timeline !== []) : ?>
<div class="sch-panels">
    <?php if ($card_data !== []) : ?>
        <section class="sch-card">
            <h2><?php esc_html_e('الأداء بالمواد', 'school-system'); ?></h2>
            <?php foreach ($card_data as $subject => $data) : ?>
                <div class="sch-row">
                    <span><?php echo esc_html($subject); ?></span>
                    <strong><?php echo esc_html(number_format((float) $data['percent'], 1)); ?>%</strong>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($timeline !== []) : ?>
        <section class="sch-card">
            <h2><?php esc_html_e('الخط الزمني', 'school-system'); ?></h2>
            <div class="sch-tl">
                <?php foreach ($timeline as $item) : ?>
                    <div class="sch-tl__i">
                        <strong><?php echo esc_html($item['title']); ?></strong>
                        <span dir="ltr"><?php echo esc_html($item['when']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php endif; ?>


<?php if ($can) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('الفصل', 'school-system'); ?></h2>
    <form method="post" class="sch-toolbar">
        <?php wp_nonce_field('sch_enroll_student', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="enroll_student">
        <select name="class_id" required aria-label="الشعبة">
            <?php foreach ($classes as $c) : ?>
                <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class && (int) $class->id === (int) $c->id); ?>>
                    <?php echo esc_html(SCH_Classes::label($c) . ' (' . $c->enrolled . '/' . $c->capacity . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('نقل', 'school-system'); ?></button>
    </form>
</div>
<?php endif; ?>

<div class="sch-card">
    <h2><?php esc_html_e('أولياء الأمور', 'school-system'); ?></h2>
    <?php if ($guardians === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا يوجد ولي أمر مرتبط', 'school-system'); ?></strong>
            <?php esc_html_e('بلا ربط لن يستطيع أحد متابعة الطالب من التطبيق.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الاسم', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصلة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الجوال', 'school-system'); ?></th>
                    <th><?php esc_html_e('أساسي', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($guardians as $g) : ?>
                    <tr>
                        <td class="sch-name">
                            <?php if (!empty($g->id)) : ?>
                                <a href="<?php echo esc_url(SCH_Dashboard::url('guardians', (int) $g->id)); ?>"><?php echo esc_html($g->display_name); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($g->display_name); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(SCH_Guardians::relation_label($g->relation)); ?></td>
                        <td dir="ltr"><?php echo esc_html($g->phone ?: '—'); ?></td>
                        <td><?php echo $g->is_primary ? '<span class="sch-badge sch-badge--ok">' . esc_html__('نعم', 'school-system') . '</span>' : '—'; ?></td>
                        <td>
                            <?php if (current_user_can('sch_manage_guardians')) : ?>
                                <form method="post" onsubmit="return confirm('<?php esc_attr_e('فك الارتباط؟', 'school-system'); ?>');">
                                    <?php wp_nonce_field('sch_unlink_guardian', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="unlink_guardian">
                                    <input type="hidden" name="guardian_user_id" value="<?php echo esc_attr((string) $g->user_id); ?>">
                                    <button class="sch-link-danger"><?php esc_html_e('فك', 'school-system'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (current_user_can('sch_manage_guardians')) : ?>
        <form method="post" class="sch-toolbar sch-mt">
            <?php wp_nonce_field('sch_link_guardian', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="link_guardian">
            <select name="guardian_id" required aria-label="ولي الأمر">
                <option value=""><?php esc_html_e('اختر ولي أمر…', 'school-system'); ?></option>
                <?php foreach ($all_g['items'] as $g) : ?>
                    <option value="<?php echo esc_attr((string) $g->id); ?>"><?php echo esc_html($g->display_name . ' — ' . $g->phone); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="relation" aria-label="صلة القرابة">
                <?php foreach (SCH_Guardians::RELATIONS as $slug => $label) : ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sch-check"><input type="checkbox" name="is_primary" value="1"> <?php esc_html_e('أساسي', 'school-system'); ?></label>
            <button class="sch-btn sch-btn--quiet"><?php esc_html_e('ربط', 'school-system'); ?></button>
        </form>
    <?php endif; ?>
</div>

<?php if (current_user_can('sch_manage_docs')) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('المستندات', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('مخزّنة خارج المجلد العام. كل فتح لمستند يُسجَّل في سجل النظام.', 'school-system'); ?></p>

    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th><?php esc_html_e('المستند', 'school-system'); ?></th>
                <th><?php esc_html_e('الملف', 'school-system'); ?></th>
                <th><?php esc_html_e('الحجم', 'school-system'); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach (SCH_Enrollment::DOC_TYPES as $slug => $label) :
                $doc = null;
                foreach ($docs as $d) {
                    if ($d->doc_type === $slug) { $doc = $d; break; }
                } ?>
                <tr>
                    <td class="sch-name"><?php echo esc_html($label); ?></td>
                    <td>
                        <?php if ($doc) : ?>
                            <a target="_blank" rel="noopener"
                               href="<?php echo esc_url(add_query_arg('sch_file', (int) $doc->id, SCH_Dashboard::url('students', $id))); ?>">
                                <?php echo esc_html($doc->file_name); ?>
                            </a>
                        <?php else : ?>
                            <span class="sch-badge sch-badge--muted"><?php esc_html_e('غير مرفوع', 'school-system'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $doc ? esc_html(size_format((int) $doc->file_size)) : '—'; ?></td>
                    <td>
                        <?php if ($doc) : ?>
                            <form method="post" onsubmit="return confirm('<?php esc_attr_e('حذف المستند؟', 'school-system'); ?>');">
                                <?php wp_nonce_field('sch_delete_doc', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="delete_doc">
                                <input type="hidden" name="doc_id" value="<?php echo esc_attr((string) $doc->id); ?>">
                                <button class="sch-link-danger"><?php esc_html_e('حذف', 'school-system'); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="post" enctype="multipart/form-data" class="sch-toolbar sch-mt">
        <?php wp_nonce_field('sch_upload_doc', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="upload_doc">
        <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
        <select name="doc_type" required aria-label="نوع المستند">
            <?php foreach (SCH_Enrollment::DOC_TYPES as $slug => $label) : ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="file" name="doc" accept="image/jpeg,image/png,application/pdf" required aria-label="الملف">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('رفع', 'school-system'); ?></button>
    </form>
</div>
<?php endif; ?>

<?php if (current_user_can('sch_view_health')) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('السجل الصحي', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('يراه المدير والصحة المدرسية فقط — لا المعلم ولا السائق ولا المحاسب.', 'school-system'); ?></p>

    <form method="post">
        <?php wp_nonce_field('sch_save_health', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="save_health">
        <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="hh-blood"><?php esc_html_e('فصيلة الدم', 'school-system'); ?></label>
                <select id="hh-blood" name="blood_type">
                    <option value=""><?php esc_html_e('غير محددة', 'school-system'); ?></option>
                    <?php foreach (SCH_Enrollment::BLOOD_TYPES as $bt) : ?>
                        <option value="<?php echo esc_attr($bt); ?>" <?php selected($health->blood_type ?? '', $bt); ?>><?php echo esc_html($bt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="hh-chronic"><?php esc_html_e('أمراض مزمنة', 'school-system'); ?></label>
                <input id="hh-chronic" type="text" name="chronic" value="<?php echo esc_attr((string) ($health->chronic ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="hh-allergy"><?php esc_html_e('حساسية', 'school-system'); ?></label>
                <input id="hh-allergy" type="text" name="allergies" value="<?php echo esc_attr((string) ($health->allergies ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="hh-needs"><?php esc_html_e('احتياجات خاصة', 'school-system'); ?></label>
                <input id="hh-needs" type="text" name="special_needs" value="<?php echo esc_attr((string) ($health->special_needs ?? '')); ?>">
            </div>
        </div>
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('حفظ السجل الصحي', 'school-system'); ?></button>
    </form>
</div>
<?php endif; ?>

<?php if (current_user_can('sch_handle_notes')) :
    $sch_summary = SCH_Nerve::summary($id);
    $sch_prev    = SCH_Nerve::previous_summary($id); ?>
<div class="sch-card">
    <h2><?php esc_html_e('الذاكرة عبر السنوات', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('ثلاثة أسطر تفتحها معلمة العام القادم في أول يوم — بدل ثلاثة أشهر تكتشفها فيها.', 'school-system'); ?></p>

    <?php if ($sch_prev) : ?>
        <div class="sch-notice">
            <strong><?php echo esc_html(sprintf(
                /* translators: %s: اسم السنة */
                __('من العام السابق — %s', 'school-system'),
                (string) $sch_prev->year_name
            )); ?></strong>
            <div class="sch-sub"><?php echo esc_html(trim(implode(' · ', array_filter([
                $sch_prev->works, $sch_prev->avoid, $sch_prev->notes,
            ])))); ?></div>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('sch_save_summary', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="save_summary">
        <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">

        <div class="sch-field">
            <label for="y-works"><?php esc_html_e('ينفع معه', 'school-system'); ?></label>
            <input id="y-works" type="text" name="works" maxlength="300"
                   value="<?php echo esc_attr((string) ($sch_summary->works ?? '')); ?>"
                   placeholder="<?php esc_attr_e('مثال: يستجيب للمدح الفردي أمام مجموعة صغيرة', 'school-system'); ?>">
        </div>
        <div class="sch-field">
            <label for="y-avoid"><?php esc_html_e('تجنّب', 'school-system'); ?></label>
            <input id="y-avoid" type="text" name="avoid" maxlength="300"
                   value="<?php echo esc_attr((string) ($sch_summary->avoid ?? '')); ?>">
        </div>
        <div class="sch-field">
            <label for="y-notes"><?php esc_html_e('ما يجب معرفته', 'school-system'); ?></label>
            <input id="y-notes" type="text" name="notes" maxlength="300"
                   value="<?php echo esc_attr((string) ($sch_summary->notes ?? '')); ?>">
        </div>

        <p class="sch-sub"><?php esc_html_e('وقائعية لا وصفية: «يستجيب للمدح الفردي» نعم، «طفل صعب» لا.', 'school-system'); ?></p>
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('حفظ', 'school-system'); ?></button>
    </form>
</div>
<?php endif; ?>

<?php $sch_notes = SCH_Notes::of_student($id, 15); ?>
<?php if ($sch_notes !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('الملاحظات', 'school-system'); ?></h2>
    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th><?php esc_html_e('التصنيف', 'school-system'); ?></th>
                <th><?php esc_html_e('الملاحظة', 'school-system'); ?></th>
                <th><?php esc_html_e('كاتبها', 'school-system'); ?></th>
                <th><?php esc_html_e('الأثر', 'school-system'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($sch_notes as $n) : ?>
                <tr>
                    <td class="sch-name"><?php echo esc_html(SCH_Notes::CATEGORIES[$n->category][0] ?? ''); ?></td>
                    <td><?php echo esc_html($n->body ?: ''); ?></td>
                    <td class="sch-sub"><?php echo esc_html($n->author_name ?: '—'); ?></td>
                    <td>
                        <?php if ($n->recheck_result) : ?>
                            <span class="sch-badge <?php echo $n->recheck_result === 'improved' ? 'sch-badge--ok' : ''; ?>">
                                <?php echo esc_html(match ($n->recheck_result) {
                                    'improved' => __('تحسّن', 'school-system'),
                                    'worse'    => __('تراجع', 'school-system'),
                                    default    => __('كما هو', 'school-system'),
                                }); ?>
                            </span>
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
<?php endif; ?>

<?php if ($sub) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('النقل', 'school-system'); ?></h2>
    <dl class="sch-dl">
        <div><dt><?php esc_html_e('المسار', 'school-system'); ?></dt><dd><?php echo esc_html($sub->route_name); ?></dd></div>
        <div><dt><?php esc_html_e('نقطة التوقف', 'school-system'); ?></dt><dd><?php echo esc_html($sub->stop_name ?: '—'); ?></dd></div>
        <div><dt><?php esc_html_e('الرسوم', 'school-system'); ?></dt><dd><?php echo esc_html(sch_money($sub->fee_amount)); ?></dd></div>
    </dl>
</div>
<?php endif; ?>
