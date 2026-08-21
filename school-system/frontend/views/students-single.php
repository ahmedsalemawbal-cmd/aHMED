<?php
/**
 * ملفّ الطالب — سبع صفحات في شاشة واحدة.
 *
 * كل تبويبة رابط حقيقي (`?tab=`) يعمل بلا جافاسكربت، والتبديل الفوري تحسينٌ
 * فوقه في `list-tools.js`. والبيانات تُقرأ مرة واحدة هنا لأن الصفحات السبع
 * كلّها في المستند — التبديل إظهارٌ لا طلبُ صفحة.
 */
declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$student = SCH_Students::get($id);

if (!$student) {
    echo '<div class="sch-empty"><strong>' . esc_html__('الطالب غير موجود', 'school-system') . '</strong></div>';
    return;
}

$can        = current_user_can('sch_manage_students');
$can_g      = current_user_can('sch_manage_guardians');
$can_docs   = current_user_can('sch_manage_docs');
$can_health = current_user_can('sch_view_health');
$can_notes  = current_user_can('sch_handle_notes');

$full      = SCH_Enrollment::full_name($student);
$class     = SCH_Students::current_class($id);
$classes   = SCH_Classes::list();

/*
 * شعبة الطالب تُؤخذ من القائمة لا من `SCH_Classes::get()`.
 *
 * الأخيرة `SELECT *` على جدول الشعب، وليس فيه عمود `enrolled` — إنما تحسبه
 * `list()` باستعلامٍ فرعيّ. فكان لوح السعة يقرأ خاصّيةً غير موجودة: العدد
 * يظهر فارغًا، و«ممتلئة» لا تظهر أبدًا لأن الطرح يصير `capacity - 0`.
 * والقائمة محمّلة أصلًا في هذه الشاشة — فلا استعلام إضافيّ.
 */
if ($class) {
    foreach ($classes as $sch_c) {
        if ((int) $sch_c->id === (int) $class->id) {
            $class = $sch_c;
            break;
        }
    }
}
$guardians = SCH_Guardians::of_student($id);
$all_g     = SCH_Guardians::list(['per_page' => 200]);
$docs      = SCH_Enrollment::docs($id);
$health    = $can_health ? SCH_Enrollment::health($id) : null;
$sub       = SCH_Routes::subscription_of($id);
$rate      = SCH_Attendance::rate($id);
$heat      = SCH_Attendance::last_days($id, 30);
$timeline  = SCH_Enrollment::timeline($id, 6);
$flow      = SCH_Flow::progress('student', $id);
$fin       = SCH_Finance::student_summary($id);
$invoices  = SCH_Finance::invoices_of_student($id);
$state     = SCH_Students::state_now($student);
$summary   = $can_notes ? SCH_Nerve::summary($id) : null;
$prev_year = $can_notes ? SCH_Nerve::previous_summary($id) : null;
$log       = SCH_Audit::query(['object_type' => 'student', 'object_id' => $id, 'per_page' => 8])['items'];

$card_data = SCH_Assessment::report_card($id);
$avg       = $card_data !== []
    ? round(array_sum(array_map(static fn (array $s): float => (float) $s['percent'], $card_data)) / count($card_data), 1)
    : null;

$photo_url = $student->photo_file
    ? add_query_arg('sch_photo', '1', SCH_Dashboard::url('students', $id))
    : '';

$initial = mb_substr(trim($full), 0, 1);
$base    = SCH_Dashboard::url('students', $id);

/* الخطوة التالية في إكمال الملف — اسمها ورابطها معًا، فالزر يقود لمكانها. */
$next_key   = (string) ($flow['next'] ?? '');
$next_step  = $next_key !== '' ? $flow['steps'][$next_key] : null;
$done_all   = $next_step === null;

/* ═══ التبويبات ═══
   مفاتيح لا أرقام: الترقيم يتغيّر مع الصلاحيات، والمفتاح لا يتغيّر — فرابطٌ
   مُرسَل لزميل يفتح الصفحة نفسها عنده مهما اختلف ما يراه. */
$tabs = ['id' => __('الهوية والدخول', 'school-system')];
$tabs['setup'] = __('إكمال الملف', 'school-system');
$tabs['day']   = __('الخط الزمني والفصل', 'school-system');
$tabs['ppl']   = __('أولياء الأمور', 'school-system');
if ($can_docs) {
    $tabs['docs'] = __('المستندات', 'school-system');
}
if ($can_health || $can_notes) {
    $tabs['care'] = __('الصحة والذاكرة', 'school-system');
}
$tabs['money'] = __('الرسوم والنقل', 'school-system');

$tab_keys = array_keys($tabs);
$tab      = sanitize_key((string) ($_GET['tab'] ?? ''));
if (!isset($tabs[$tab])) {
    $tab = $tab_keys[0];
}
$at    = (int) array_search($tab, $tab_keys, true);
$prev  = $tab_keys[$at - 1] ?? '';
$next  = $tab_keys[$at + 1] ?? '';

/** حالة الشاشة تعود مع كل نموذج — من يعدّل في الصفحة الرابعة يعود إليها. */
$keep = static function () use ($tab): void {
    ?><input type="hidden" name="keep[tab]" value="<?php echo esc_attr($tab); ?>"><?php
};
?>
<div class="sch-sp" data-sch-tabs>

    <!-- ══ المسار وأفعال الرأس ══ -->
    <div class="sch-sp__crumb">
        <div class="sch-sp__path">
            <a class="sch-sp__up" href="<?php echo esc_url(SCH_Dashboard::url('students')); ?>"><?php esc_html_e('كل الطلاب', 'school-system'); ?></a>
            <span class="sch-sp__sep" aria-hidden="true">›</span>
            <span class="sch-sp__here"><?php echo esc_html($full); ?></span>
        </div>

        <div class="sch-sp__acts">
            <button type="button" class="sch-sp__act" data-sch-print><?php esc_html_e('طباعة الملف', 'school-system'); ?></button>
            <a class="sch-sp__act" href="<?php echo esc_url(add_query_arg('student', $id, SCH_Dashboard::url('certificates'))); ?>"><?php esc_html_e('إصدار شهادة', 'school-system'); ?></a>
            <?php if ($can) : ?>
                <button type="button" class="sch-sp__act sch-sp__act--go" data-modal-open="sch-edit-student"><?php esc_html_e('تعديل الملف', 'school-system'); ?></button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ شريط الهويّة المختصر — يبقى فوق كل صفحة ══ -->
    <div class="sch-sp__strip">
        <span class="sch-sp__mark" aria-hidden="true"><?php echo esc_html($initial); ?></span>

        <span class="sch-sp__sname">
            <b><?php echo esc_html($full); ?></b>
            <span class="sch-sp__smeta">
                <code dir="ltr"><?php echo esc_html($student->academic_no ?: '—'); ?></code>
                <span class="sch-sp__bar" aria-hidden="true"></span>
                <span><?php echo esc_html($class ? SCH_Classes::label($class) : __('غير مسجَّل في شعبة', 'school-system')); ?></span>
            </span>
        </span>

        <span class="sch-sp__pill is-<?php echo esc_attr($state['key']); ?>">
            <i aria-hidden="true"></i><?php echo esc_html($state['label']); ?>
        </span>

        <span class="sch-sp__pill <?php echo $done_all ? 'is-done' : 'is-todo'; ?>">
            <em><?php esc_html_e('إكمال الملف', 'school-system'); ?></em>
            <b dir="ltr"><?php echo esc_html($flow['done'] . ' / ' . $flow['total']); ?></b>
        </span>
    </div>

    <!-- ══ التبويبات السبع ══ -->
    <nav class="sch-sp__tabs" aria-label="<?php esc_attr_e('أقسام ملفّ الطالب', 'school-system'); ?>">
        <?php $n = 0; foreach ($tabs as $key => $label) : $n++; ?>
            <a class="sch-sp__tab<?php echo $key === $tab ? ' is-on' : ''; ?>"
               href="<?php echo esc_url(add_query_arg('tab', $key, $base)); ?>"
               data-tab-go="<?php echo esc_attr($key); ?>"
               data-tab-label="<?php echo esc_attr($label); ?>"
               aria-current="<?php echo $key === $tab ? 'true' : 'false'; ?>">
                <span class="sch-sp__tabn"><?php echo esc_html((string) $n); ?></span>
                <span><?php echo esc_html($label); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sch-sp__pages">

    <!-- ══════════ ① الهوية والدخول ══════════ -->
    <section class="sch-sp__pane<?php echo $tab === 'id' ? ' is-on' : ''; ?>" data-tab-pane="id"
             aria-label="<?php echo esc_attr($tabs['id']); ?>">

        <article class="sch-sp__id">
            <header class="sch-sp__idh">
                <b>STUDENT IDENTITY</b>
                <span><?php echo esc_html(sch_settings('school_name', get_bloginfo('name'))); ?></span>
            </header>

            <div class="sch-sp__idb">
                <div class="sch-sp__qr">
                    <div class="sch-sp__qrbox">
                        <?php echo SCH_QR::svg((string) ($student->academic_no ?: $student->qr_token), 4, 2); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </div>
                    <code dir="ltr"><?php echo esc_html($student->academic_no ?: '—'); ?></code>
                </div>

                <div class="sch-sp__who">
                    <div>
                        <h1><?php echo esc_html($full); ?></h1>
                        <?php if ($student->name_en) : ?>
                            <span class="sch-sp__whoen" dir="ltr"><?php echo esc_html($student->name_en); ?></span>
                        <?php endif; ?>
                    </div>

                    <dl class="sch-sp__facts">
                        <div><dt><?php esc_html_e('الرقم الأكاديمي', 'school-system'); ?></dt>
                            <dd class="is-num is-key" dir="ltr"><?php echo esc_html($student->academic_no ?: '—'); ?></dd></div>
                        <div><dt><?php esc_html_e('رقم الطالب', 'school-system'); ?></dt>
                            <dd class="is-num" dir="ltr"><?php echo esc_html($student->student_no ?: '—'); ?></dd></div>
                        <div><dt><?php esc_html_e('رقم الهوية', 'school-system'); ?></dt>
                            <dd class="is-num" dir="ltr"><?php echo esc_html($student->national_id ?: '—'); ?></dd></div>
                        <div><dt><?php esc_html_e('الجنسية', 'school-system'); ?></dt>
                            <dd><?php echo esc_html($student->nationality ?: '—'); ?></dd></div>
                        <div><dt><?php esc_html_e('تاريخ الميلاد', 'school-system'); ?></dt>
                            <dd class="is-num" dir="ltr"><?php echo esc_html($student->birth_date ?: '—'); ?></dd></div>
                        <div><dt><?php esc_html_e('الجنس', 'school-system'); ?></dt>
                            <dd><?php echo esc_html($student->gender === 'female' ? __('أنثى', 'school-system') : __('ذكر', 'school-system')); ?></dd></div>
                        <div><dt><?php esc_html_e('المرحلة', 'school-system'); ?></dt>
                            <dd><?php echo esc_html(SCH_Classes::STAGES[$student->stage] ?? '—'); ?></dd></div>
                        <div><dt><?php esc_html_e('الصف والفصل', 'school-system'); ?></dt>
                            <dd><?php echo esc_html($class ? $class->grade_level . ' / ' . $class->section : '—'); ?></dd></div>
                        <div><dt><?php esc_html_e('تاريخ التسجيل', 'school-system'); ?></dt>
                            <dd class="is-num" dir="ltr"><?php echo esc_html($student->enrolled_at ?: '—'); ?></dd></div>
                    </dl>
                </div>

                <div class="sch-sp__face">
                    <span class="sch-sp__facep">
                        <?php if ($photo_url) : ?>
                            <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($full); ?>" width="118" height="142">
                        <?php else : ?>
                            <?php echo esc_html($initial); ?>
                        <?php endif; ?>
                    </span>
                    <span class="sch-sp__facet"><?php echo esc_html($class ? SCH_Classes::label($class) : __('غير مسجَّل', 'school-system')); ?></span>
                </div>
            </div>
        </article>

        <?php /* بيانات الدخول تظهر مرة عند التسجيل ثم تُفقد — والزرّ يُنشئ الحساب إن كان ناقصًا. */ ?>
        <div class="sch-sp__cred">
            <div class="sch-sp__ct">
                <b><?php esc_html_e('دخول الطالب للتطبيق', 'school-system'); ?></b>
                <span><?php echo esc_html($student->user_id
                    ? __('اسم المستخدم هو حرف s متبوعًا برقمه الأكاديمي. ولّد كلمة مرور جديدة وسلّمها له — القديمة تتوقف فورًا.', 'school-system')
                    : __('لا حساب لهذا الطالب بعد — ستُنشئه الضغطة، وتظهر كلمة المرور مرة واحدة.', 'school-system')); ?></span>
            </div>

            <div class="sch-sp__row">
                <?php if ($student->academic_no) : ?>
                    <span class="sch-sp__user">
                        <span><?php esc_html_e('المستخدم', 'school-system'); ?></span>
                        <code dir="ltr">s<?php echo esc_html($student->academic_no); ?></code>
                    </span>
                <?php endif; ?>

                <?php if ($can) : ?>
                    <form method="post">
                        <?php wp_nonce_field('sch_student_login', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="student_login">
                        <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $student->id); ?>">
                        <?php $keep(); ?>
                        <button class="sch-sp__act sch-sp__act--go">
                            <?php echo esc_html($student->user_id
                                ? __('كلمة مرور جديدة', 'school-system')
                                : __('إنشاء حساب الطالب', 'school-system')); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ══════════ ② إكمال الملف ══════════ -->
    <section class="sch-sp__pane<?php echo $tab === 'setup' ? ' is-on' : ''; ?>" data-tab-pane="setup"
             aria-label="<?php echo esc_attr($tabs['setup']); ?>">

        <div class="sch-sp__c">
            <div class="sch-sp__row">
                <span class="sch-sp__ring<?php echo $done_all ? ' is-done' : ''; ?>" dir="ltr"><?php echo esc_html($flow['done'] . ' / ' . $flow['total']); ?></span>

                <div class="sch-sp__ct sch-sp__ct--grow">
                    <b><?php echo esc_html($done_all
                        ? __('الملف مكتمل — لا ينقصه شيء', 'school-system')
                        : sprintf(
                            /* translators: %d: عدد الخطوات المتبقية */
                            _n('خطوة واحدة تفصل الملف عن الاكتمال', '%d خطوات تفصل الملف عن الاكتمال', $flow['total'] - $flow['done'], 'school-system'),
                            $flow['total'] - $flow['done']
                        )); ?></b>
                    <span><?php echo esc_html($done_all
                        ? sprintf(
                            /* translators: %d: عدد الخطوات */
                            __('أُنجزت %d خطوات كاملة.', 'school-system'),
                            $flow['done']
                        )
                        : sprintf(
                            /* translators: 1: المنجَز 2: الإجمالي 3: اسم الخطوة التالية */
                            __('أُنجزت %1$d خطوات من %2$d — المتبقي: %3$s', 'school-system'),
                            $flow['done'],
                            $flow['total'],
                            (string) $next_step['label']
                        )); ?></span>
                </div>

                <?php if ($next_step && $next_step['url']) : ?>
                    <a class="sch-sp__act sch-sp__act--go" href="<?php echo esc_url((string) $next_step['url']); ?>">
                        <?php echo esc_html(sprintf(
                            /* translators: %s: اسم الخطوة */
                            __('إكمال %s', 'school-system'),
                            (string) $next_step['label']
                        )); ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="sch-sp__bars" aria-hidden="true">
                <?php foreach ($flow['steps'] as $s) : ?>
                    <span class="<?php echo in_array($s['state'], ['done', 'na'], true) ? 'is-done' : ''; ?>"></span>
                <?php endforeach; ?>
            </div>

            <div class="sch-sp__steps">
                <?php foreach ($flow['steps'] as $s) :
                    $s_done = in_array($s['state'], ['done', 'na'], true); ?>
                    <div class="sch-sp__step<?php echo $s_done ? '' : ' is-todo'; ?>">
                        <i aria-hidden="true"><?php echo $s_done ? '✓' : ''; ?></i>
                        <span title="<?php echo esc_attr((string) $s['why']); ?>"><?php echo esc_html((string) $s['label']); ?></span>
                        <?php if (!$s_done && $s['url']) : ?>
                            <a class="sch-sp__mini" href="<?php echo esc_url((string) $s['url']); ?>"><?php esc_html_e('افتح', 'school-system'); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
        $kpi_att  = $rate >= 75 ? 'is-ok' : 'is-bad';
        $paid_pct = $fin['pct'];
        ?>
        <div class="sch-sp__kpis">
            <div class="sch-sp__kpi">
                <span><?php esc_html_e('نسبة الحضور — 60 يومًا', 'school-system'); ?></span>
                <span class="sch-sp__kpiv is-num <?php echo esc_attr($kpi_att); ?>" dir="ltr"><?php echo esc_html(number_format($rate, 1)); ?>%</span>
                <span class="sch-sp__meter <?php echo esc_attr($kpi_att); ?>"><span style="width:<?php echo esc_attr((string) min(100, max(0, $rate))); ?>%"></span></span>
                <span><?php echo esc_html($rate >= 75
                    ? __('فوق حد المتابعة (75%)', 'school-system')
                    : __('أقل من حد المتابعة (75%)', 'school-system')); ?></span>
            </div>

            <div class="sch-sp__kpi">
                <span><?php esc_html_e('المعدل العام', 'school-system'); ?></span>
                <span class="sch-sp__kpiv is-num" dir="ltr"><?php echo $avg !== null ? esc_html(number_format($avg, 1) . '%') : '—'; ?></span>
                <span class="sch-sp__meter"><span style="width:<?php echo esc_attr((string) ($avg !== null ? min(100, max(0, $avg)) : 0)); ?>%"></span></span>
                <span><?php echo esc_html($avg !== null
                    ? sprintf(
                        /* translators: %d: عدد المواد */
                        __('على %d مواد مرصودة', 'school-system'),
                        count($card_data)
                    )
                    : __('لم تُرصد درجات بعد', 'school-system')); ?></span>
            </div>

            <div class="sch-sp__kpi">
                <span><?php esc_html_e('المتبقي من الرسوم (ر.س)', 'school-system'); ?></span>
                <span class="sch-sp__kpiv is-num <?php echo $fin['remaining'] > 0 ? 'is-bad' : 'is-ok'; ?>" dir="ltr"><?php echo esc_html(number_format($fin['remaining'], 0)); ?></span>
                <span class="sch-sp__meter <?php echo $fin['remaining'] > 0 ? 'is-bad' : 'is-ok'; ?>"><span style="width:<?php echo esc_attr((string) (100 - $paid_pct)); ?>%"></span></span>
                <span><?php echo esc_html($fin['total'] > 0
                    ? sprintf(
                        /* translators: %s: الإجمالي */
                        __('من إجمالي %s', 'school-system'),
                        number_format($fin['total'], 0)
                    )
                    : __('لم تُصدر فاتورة بعد', 'school-system')); ?></span>
            </div>

            <div class="sch-sp__kpi">
                <span><?php esc_html_e('النقل المدرسي', 'school-system'); ?></span>
                <span class="sch-sp__kpiv"><?php echo esc_html($sub ? $sub->route_name : __('لا يوجد', 'school-system')); ?></span>
                <span class="sch-sp__meter"><span style="width:<?php echo $sub ? '100' : '0'; ?>%"></span></span>
                <span><?php echo esc_html($sub
                    ? ($sub->stop_name ?: __('نقطة التوقف غير محددة', 'school-system'))
                    : __('غير مشترك في النقل', 'school-system')); ?></span>
            </div>
        </div>
    </section>

    <!-- ══════════ ③ الخط الزمني والفصل ══════════ -->
    <section class="sch-sp__pane<?php echo $tab === 'day' ? ' is-on' : ''; ?>" data-tab-pane="day"
             aria-label="<?php echo esc_attr($tabs['day']); ?>">

        <div class="sch-sp__two">
            <div class="sch-sp__c">
                <div class="sch-sp__ch">
                    <span class="sch-sp__ct"><b><?php esc_html_e('الخط الزمني', 'school-system'); ?></b></span>
                    <span class="sch-sp__hint"><?php esc_html_e('كل حدث مسجّل بالثانية', 'school-system'); ?></span>
                </div>

                <?php if ($timeline === []) : ?>
                    <div class="sch-empty"><strong><?php esc_html_e('لا أحداث بعد', 'school-system'); ?></strong></div>
                <?php else : ?>
                    <div class="sch-sp__tl">
                        <?php foreach ($timeline as $e) : ?>
                            <div class="sch-sp__tli is-<?php echo esc_attr((string) ($e['kind'] ?? 'base')); ?>">
                                <span class="sch-sp__rail"><i aria-hidden="true"></i><span aria-hidden="true"></span></span>
                                <div class="sch-sp__tlb">
                                    <div class="sch-sp__tlt">
                                        <b><?php echo esc_html((string) $e['title']); ?></b>
                                        <?php if (!empty($e['tag'])) : ?>
                                            <span class="sch-sp__tag"><?php echo esc_html((string) $e['tag']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="sch-sp__when" dir="ltr"><?php echo esc_html((string) $e['when']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sch-sp__col">
                <div class="sch-sp__c">
                    <span class="sch-sp__ct"><b><?php esc_html_e('الفصل', 'school-system'); ?></b></span>

                    <?php if ($can) : ?>
                        <form method="post" class="sch-sp__row">
                            <?php wp_nonce_field('sch_enroll_student', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="enroll_student">
                            <?php $keep(); ?>
                            <select class="sch-sp__pick" name="class_id" required aria-label="<?php esc_attr_e('الشعبة', 'school-system'); ?>">
                                <?php foreach ($classes as $c) : ?>
                                    <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class && (int) $class->id === (int) $c->id); ?>>
                                        <?php echo esc_html(SCH_Classes::label($c) . ' (' . $c->enrolled . '/' . $c->capacity . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="sch-sp__act sch-sp__act--go"><?php esc_html_e('نقل', 'school-system'); ?></button>
                        </form>
                    <?php endif; ?>

                    <?php if ($class) :
                        $room = (int) $class->capacity - (int) $class->enrolled; ?>
                        <div class="sch-sp__cap">
                            <span><?php echo esc_html($room > 0
                                ? __('الشعبة الحالية بها متسع', 'school-system')
                                : __('الشعبة الحالية ممتلئة', 'school-system')); ?></span>
                            <b dir="ltr"><?php echo esc_html($class->enrolled . ' / ' . $class->capacity); ?></b>
                        </div>
                    <?php else : ?>
                        <div class="sch-sp__warn">
                            <i aria-hidden="true"></i>
                            <span><?php esc_html_e('غير مسجَّل في شعبة — بلا شعبة لا حضور ولا جدول ولا معلم.', 'school-system'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="sch-sp__c">
                    <span class="sch-sp__ct"><b><?php esc_html_e('حضور آخر 30 يومًا', 'school-system'); ?></b></span>

                    <div class="sch-sp__heat">
                        <?php foreach ($heat['days'] as $d) : ?>
                            <span class="is-<?php echo esc_attr($d['key']); ?>"
                                  title="<?php echo esc_attr($d['date'] . ' · ' . $d['label']); ?>"></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="sch-sp__legend">
                        <?php foreach (['present', 'late', 'absent', 'excused', 'off'] as $k) : ?>
                            <span>
                                <i class="is-<?php echo esc_attr($k); ?>" aria-hidden="true"></i>
                                <em><?php echo esc_html($k === 'off'
                                    ? __('لا دوام', 'school-system')
                                    : SCH_Attendance::STATUSES[$k]); ?></em>
                                <b dir="ltr"><?php echo esc_html((string) $heat['counts'][$k]); ?></b>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════ ④ أولياء الأمور ══════════ -->
    <section class="sch-sp__pane<?php echo $tab === 'ppl' ? ' is-on' : ''; ?>" data-tab-pane="ppl"
             aria-label="<?php echo esc_attr($tabs['ppl']); ?>">

        <div class="sch-sp__c">
            <div class="sch-sp__ch">
                <span class="sch-sp__ct"><b><?php esc_html_e('أولياء الأمور', 'school-system'); ?></b></span>
                <span class="sch-sp__hint"><?php esc_html_e('الأساسي يستلم الإشعارات — ومن يستلم الطالب يظهر للحارس', 'school-system'); ?></span>
            </div>

            <?php if ($guardians === []) : ?>
                <div class="sch-empty">
                    <strong><?php esc_html_e('لا يوجد ولي أمر مرتبط', 'school-system'); ?></strong>
                    <?php esc_html_e('بلا ربط لا يصل عنه إشعار واحد، ولا أحد يستلمه عند البوابة.', 'school-system'); ?>
                </div>
            <?php else : ?>
                <?php foreach ($guardians as $g) : ?>
                    <div class="sch-sp__prow">
                        <span class="sch-sp__av" aria-hidden="true"><?php echo esc_html(mb_substr(trim((string) $g->display_name), 0, 1)); ?></span>

                        <span class="sch-sp__pwho">
                            <?php if (!empty($g->id)) : ?>
                                <a class="sch-sp__pname" href="<?php echo esc_url(SCH_Dashboard::url('guardians', (int) $g->id)); ?>"><?php echo esc_html($g->display_name); ?></a>
                            <?php else : ?>
                                <span class="sch-sp__pname"><?php echo esc_html($g->display_name); ?></span>
                            <?php endif; ?>
                            <span class="sch-sp__pmeta">
                                <span><?php echo esc_html(SCH_Guardians::relation_label($g->relation)); ?></span>
                                <code dir="ltr"><?php echo esc_html($g->phone ?: '—'); ?></code>
                            </span>
                        </span>

                        <span class="sch-sp__pacts">
                            <?php if (!empty($g->is_primary)) : ?>
                                <span class="sch-sp__pill is-in"><i aria-hidden="true"></i><?php esc_html_e('أساسي', 'school-system'); ?></span>
                            <?php endif; ?>

                            <?php /* حقّ الاستلام يُنفَّذ عند البوابة — فهو حالة تُبدَّل لا وسمٌ يُقرأ. */ ?>
                            <?php if ($can_g) : ?>
                                <form method="post">
                                    <?php wp_nonce_field('sch_toggle_pickup', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="toggle_pickup">
                                    <input type="hidden" name="guardian_user_id" value="<?php echo esc_attr((string) $g->user_id); ?>">
                                    <input type="hidden" name="on" value="<?php echo empty($g->can_pickup) ? '1' : '0'; ?>">
                                    <?php $keep(); ?>
                                    <button class="sch-sp__mini<?php echo empty($g->can_pickup) ? '' : ' sch-sp__mini--ok'; ?>"
                                            title="<?php esc_attr_e('اضغط للتبديل', 'school-system'); ?>">
                                        <?php if (!empty($g->can_pickup)) : ?><i aria-hidden="true"></i><?php endif; ?>
                                        <?php echo empty($g->can_pickup)
                                            ? esc_html__('لا يستلم', 'school-system')
                                            : esc_html__('يستلم الطالب', 'school-system'); ?>
                                    </button>
                                </form>
                            <?php elseif (!empty($g->can_pickup)) : ?>
                                <span class="sch-sp__pill is-in"><i aria-hidden="true"></i><?php esc_html_e('يستلم الطالب', 'school-system'); ?></span>
                            <?php endif; ?>

                            <?php if (current_user_can('sch_send_messages') && !empty($g->user_id)) : ?>
                                <a class="sch-sp__mini" href="<?php echo esc_url(add_query_arg('to', (int) $g->user_id, SCH_Dashboard::url('messages'))); ?>"><?php esc_html_e('مراسلة', 'school-system'); ?></a>
                            <?php endif; ?>

                            <?php if ($can_g) : ?>
                                <form method="post" onsubmit="return confirm('<?php esc_attr_e('فك الارتباط؟', 'school-system'); ?>');">
                                    <?php wp_nonce_field('sch_unlink_guardian', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="unlink_guardian">
                                    <input type="hidden" name="guardian_user_id" value="<?php echo esc_attr((string) $g->user_id); ?>">
                                    <?php $keep(); ?>
                                    <button class="sch-sp__mini sch-sp__mini--bad"><?php esc_html_e('فك', 'school-system'); ?></button>
                                </form>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($can_g) : ?>
                <form method="post" class="sch-sp__add">
                    <?php wp_nonce_field('sch_link_guardian', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="link_guardian">
                    <?php $keep(); ?>

                    <span class="sch-sp__f">
                        <label for="lg-who"><?php esc_html_e('ولي أمر مسجّل', 'school-system'); ?></label>
                        <select id="lg-who" name="guardian_id" required>
                            <option value=""><?php esc_html_e('اختر ولي أمر…', 'school-system'); ?></option>
                            <?php foreach ($all_g['items'] as $gg) : ?>
                                <option value="<?php echo esc_attr((string) $gg->id); ?>"><?php echo esc_html($gg->display_name . ' — ' . $gg->phone); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>

                    <span class="sch-sp__f sch-sp__f--sm">
                        <label for="lg-rel"><?php esc_html_e('الصلة', 'school-system'); ?></label>
                        <select id="lg-rel" name="relation">
                            <?php foreach (SCH_Guardians::RELATIONS as $slug => $label) : ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>

                    <label class="sch-sp__check">
                        <input type="checkbox" name="is_primary" value="1">
                        <?php esc_html_e('أساسي', 'school-system'); ?>
                    </label>

                    <button class="sch-sp__act sch-sp__act--go"><?php esc_html_e('ربط', 'school-system'); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <?php /* «خالته اليوم فقط» — حالة يومية في كل مدرسة، تصير هنا سجلًّا: من أذن ولمن وإلى متى. */ ?>
        <?php if ($can_g) :
            $dels = SCH_Custody::delegations($id, 10);
            $now  = current_time('Y-m-d\TH:i'); ?>
            <div class="sch-sp__c">
                <span class="sch-sp__ct">
                    <b><?php esc_html_e('تفويض استلام مؤقّت', 'school-system'); ?></b>
                    <span><?php esc_html_e('لمن ليس وليَّ أمر — يسري إلى وقت تحدّده، ويراه الحارس عند البوابة.', 'school-system'); ?></span>
                </span>

                <?php foreach ($dels as $dl) :
                    $live = ((string) $dl->status === 'active' && strtotime((string) $dl->valid_until) > time()); ?>
                    <div class="sch-sp__prow">
                        <span class="sch-sp__pwho">
                            <span class="sch-sp__pname"><?php echo esc_html((string) $dl->person_name); ?></span>
                            <span class="sch-sp__pmeta"><span><?php echo esc_html((string) ($dl->relation ?: '—')); ?></span></span>
                        </span>

                        <span class="sch-sp__ptill">
                            <span><?php esc_html_e('يسري حتى', 'school-system'); ?></span>
                            <b><?php echo esc_html(mysql2date('j M · H:i', (string) $dl->valid_until)); ?></b>
                        </span>

                        <span class="sch-sp__pacts">
                            <?php if ($live) : ?>
                                <span class="sch-sp__pill is-in"><i aria-hidden="true"></i><?php esc_html_e('ساري', 'school-system'); ?></span>
                                <form method="post">
                                    <?php wp_nonce_field('sch_revoke_delegation', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="revoke_delegation">
                                    <input type="hidden" name="delegation_id" value="<?php echo esc_attr((string) $dl->id); ?>">
                                    <?php $keep(); ?>
                                    <button class="sch-sp__mini sch-sp__mini--bad"><?php esc_html_e('سحب', 'school-system'); ?></button>
                                </form>
                            <?php elseif ((string) $dl->status === 'revoked') : ?>
                                <span class="sch-sp__pill is-absent"><i aria-hidden="true"></i><?php esc_html_e('مسحوب', 'school-system'); ?></span>
                            <?php else : ?>
                                <span class="sch-sp__pill"><i aria-hidden="true"></i><?php esc_html_e('انتهى', 'school-system'); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>

                <form method="post" class="sch-sp__add">
                    <?php wp_nonce_field('sch_delegate_pickup', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="delegate_pickup">
                    <?php $keep(); ?>

                    <span class="sch-sp__f">
                        <label for="dg-name"><?php esc_html_e('اسم من سيستلم', 'school-system'); ?></label>
                        <input id="dg-name" type="text" name="person_name" required maxlength="190"
                               placeholder="<?php esc_attr_e('الاسم الكامل', 'school-system'); ?>">
                    </span>
                    <span class="sch-sp__f sch-sp__f--sm">
                        <label for="dg-rel"><?php esc_html_e('الصلة', 'school-system'); ?></label>
                        <input id="dg-rel" type="text" name="relation" maxlength="60"
                               placeholder="<?php esc_attr_e('خالته مثلًا', 'school-system'); ?>">
                    </span>
                    <span class="sch-sp__f sch-sp__f--sm">
                        <label for="dg-nid"><?php esc_html_e('رقم الهوية', 'school-system'); ?></label>
                        <input id="dg-nid" type="text" name="id_number" maxlength="40" dir="ltr" placeholder="1xxxxxxxxx">
                    </span>
                    <span class="sch-sp__f sch-sp__f--sm">
                        <label for="dg-till"><?php esc_html_e('يسري حتى', 'school-system'); ?></label>
                        <input id="dg-till" type="datetime-local" name="valid_until" required dir="ltr"
                               min="<?php echo esc_attr($now); ?>">
                    </span>

                    <button class="sch-sp__act sch-sp__act--go"><?php esc_html_e('تفويض', 'school-system'); ?></button>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <!-- ══════════ ⑤ المستندات ══════════ -->
    <?php if ($can_docs) : ?>
    <section class="sch-sp__pane<?php echo $tab === 'docs' ? ' is-on' : ''; ?>" data-tab-pane="docs"
             aria-label="<?php echo esc_attr($tabs['docs']); ?>">

        <div class="sch-sp__c">
            <div class="sch-sp__ch">
                <span class="sch-sp__ct">
                    <b><?php esc_html_e('المستندات', 'school-system'); ?></b>
                    <span><?php esc_html_e('مخزّنة خارج المجلد العام. كل فتح لمستند يُسجّل في سجل النظام.', 'school-system'); ?></span>
                </span>
                <button type="button" class="sch-sp__act sch-sp__act--go" data-modal-open="sch-upload-doc"><?php esc_html_e('رفع مستند', 'school-system'); ?></button>
            </div>

            <div class="sch-sp__docs">
                <?php
                $extra = [];
                foreach ($docs as $d) {
                    if (!array_key_exists((string) $d->doc_type, SCH_Enrollment::DOC_TYPES)) {
                        $extra[] = $d;
                    }
                }
                $cards = SCH_Enrollment::DOC_TYPES;
                foreach ($cards as $slug => $label) :
                    $doc = null;
                    foreach ($docs as $d) {
                        if ((string) $d->doc_type === $slug) { $doc = $d; break; }
                    }
                    $ext = $doc ? strtoupper((string) pathinfo((string) $doc->file_name, PATHINFO_EXTENSION)) : '';
                    ?>
                    <div class="sch-sp__doc">
                        <div class="sch-sp__dh">
                            <span class="sch-sp__ext<?php echo $doc ? '' : ' is-none'; ?>"><?php echo esc_html($ext !== '' ? $ext : '—'); ?></span>
                            <span class="sch-sp__dt">
                                <b><?php echo esc_html($label); ?></b>
                                <span><?php echo $doc
                                    ? esc_html(size_format((int) $doc->file_size) . ' · ' . sprintf(
                                        /* translators: %s: تاريخ الرفع */
                                        __('رُفع %s', 'school-system'),
                                        mysql2date('j F', (string) $doc->created_at)
                                    ))
                                    : esc_html__('غير مرفوع', 'school-system'); ?></span>
                            </span>
                        </div>

                        <div class="sch-sp__dacts">
                            <?php if ($doc) : ?>
                                <a class="sch-sp__mini sch-sp__mini--wide" target="_blank" rel="noopener"
                                   href="<?php echo esc_url(add_query_arg('sch_file', (int) $doc->id, $base)); ?>"><?php esc_html_e('فتح', 'school-system'); ?></a>
                                <button type="button" class="sch-sp__mini" data-modal-open="sch-upload-doc"
                                        data-pick-name="doc_type" data-pick-value="<?php echo esc_attr($slug); ?>"><?php esc_html_e('استبدال', 'school-system'); ?></button>
                                <form method="post" onsubmit="return confirm('<?php esc_attr_e('حذف المستند؟', 'school-system'); ?>');">
                                    <?php wp_nonce_field('sch_delete_doc', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="delete_doc">
                                    <input type="hidden" name="doc_id" value="<?php echo esc_attr((string) $doc->id); ?>">
                                    <?php $keep(); ?>
                                    <button class="sch-sp__mini sch-sp__mini--bad"><?php esc_html_e('حذف', 'school-system'); ?></button>
                                </form>
                            <?php else : ?>
                                <button type="button" class="sch-sp__mini sch-sp__mini--wide" data-modal-open="sch-upload-doc"
                                        data-pick-name="doc_type" data-pick-value="<?php echo esc_attr($slug); ?>"><?php esc_html_e('رفع', 'school-system'); ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($extra as $d) :
                    $ext = strtoupper((string) pathinfo((string) $d->file_name, PATHINFO_EXTENSION)); ?>
                    <div class="sch-sp__doc">
                        <div class="sch-sp__dh">
                            <span class="sch-sp__ext"><?php echo esc_html($ext !== '' ? $ext : '—'); ?></span>
                            <span class="sch-sp__dt">
                                <b><?php echo esc_html((string) $d->file_name); ?></b>
                                <span><?php echo esc_html(size_format((int) $d->file_size) . ' · ' . mysql2date('j F', (string) $d->created_at)); ?></span>
                            </span>
                        </div>
                        <div class="sch-sp__dacts">
                            <a class="sch-sp__mini sch-sp__mini--wide" target="_blank" rel="noopener"
                               href="<?php echo esc_url(add_query_arg('sch_file', (int) $d->id, $base)); ?>"><?php esc_html_e('فتح', 'school-system'); ?></a>
                            <form method="post" onsubmit="return confirm('<?php esc_attr_e('حذف المستند؟', 'school-system'); ?>');">
                                <?php wp_nonce_field('sch_delete_doc', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="delete_doc">
                                <input type="hidden" name="doc_id" value="<?php echo esc_attr((string) $d->id); ?>">
                                <?php $keep(); ?>
                                <button class="sch-sp__mini sch-sp__mini--bad"><?php esc_html_e('حذف', 'school-system'); ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="sch-sp__more">
                    <b><?php esc_html_e('مستند إضافي', 'school-system'); ?></b>
                    <span><?php esc_html_e('تقرير طبي، خطاب تحويل، أو أي مرفق', 'school-system'); ?></span>
                    <button type="button" class="sch-sp__mini" data-modal-open="sch-upload-doc"
                            data-pick-name="doc_type" data-pick-value="other"><?php esc_html_e('رفع', 'school-system'); ?></button>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ══════════ ⑥ الصحة والذاكرة ══════════ -->
    <?php if ($can_health || $can_notes) : ?>
    <section class="sch-sp__pane<?php echo $tab === 'care' ? ' is-on' : ''; ?>" data-tab-pane="care"
             aria-label="<?php echo esc_attr($tabs['care']); ?>">

        <?php if ($can_health) :
            $allergy = trim((string) ($health->allergies ?? ''));
            $chronic = trim((string) ($health->chronic ?? ''));
            $needs   = trim((string) ($health->special_needs ?? ''));
            $blood   = trim((string) ($health->blood_type ?? ''));

            $tiles = [
                [
                    'k'    => __('فصيلة الدم', 'school-system'),
                    'v'    => $blood !== '' ? $blood : __('غير مسجّلة', 'school-system'),
                    'note' => $blood !== '' ? __('تظهر لولي الأمر في بطاقة ابنه', 'school-system') : __('تُؤخذ من شهادة الميلاد', 'school-system'),
                    'tone' => $blood !== '' ? '' : 'is-mute',
                ],
                [
                    'k'    => __('أمراض مزمنة', 'school-system'),
                    'v'    => $chronic !== '' ? __('نعم', 'school-system') : __('لا', 'school-system'),
                    'note' => $chronic !== '' ? $chronic : __('لا يوجد', 'school-system'),
                    'tone' => $chronic !== '' ? 'is-flag' : 'is-mute',
                ],
                [
                    'k'    => __('حساسية', 'school-system'),
                    'v'    => $allergy !== '' ? __('نعم', 'school-system') : __('لا', 'school-system'),
                    'note' => $allergy !== '' ? $allergy : __('لا يوجد', 'school-system'),
                    'tone' => $allergy !== '' ? 'is-flag' : 'is-mute',
                ],
                [
                    'k'    => __('احتياجات خاصة', 'school-system'),
                    'v'    => $needs !== '' ? __('نعم', 'school-system') : __('لا', 'school-system'),
                    'note' => $needs !== '' ? $needs : __('لا يوجد', 'school-system'),
                    'tone' => $needs !== '' ? '' : 'is-mute',
                ],
            ]; ?>
            <div class="sch-sp__c">
                <div class="sch-sp__ch">
                    <span class="sch-sp__ct">
                        <b><?php esc_html_e('السجل الصحي', 'school-system'); ?></b>
                        <span><?php esc_html_e('يراه المدير والصحة المدرسية فقط — لا المعلم ولا السائق ولا المحاسب.', 'school-system'); ?></span>
                    </span>
                    <button type="button" class="sch-sp__act" data-modal-open="sch-edit-health"><?php esc_html_e('تعديل السجل الصحي', 'school-system'); ?></button>
                </div>

                <div class="sch-sp__health">
                    <?php foreach ($tiles as $t) : ?>
                        <div class="sch-sp__hc <?php echo esc_attr($t['tone']); ?>">
                            <span><?php echo esc_html($t['k']); ?></span>
                            <span class="sch-sp__hv"><?php echo esc_html($t['v']); ?></span>
                            <span><?php echo esc_html($t['note']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_notes) :
            $mem = [
                ['k' => __('ينفع معه', 'school-system'),      'v' => (string) ($summary->works ?? ''), 'tone' => ''],
                ['k' => __('تجنّب', 'school-system'),         'v' => (string) ($summary->avoid ?? ''), 'tone' => 'is-warn'],
                ['k' => __('ما يجب معرفته', 'school-system'), 'v' => (string) ($summary->notes ?? ''), 'tone' => 'is-info'],
            ]; ?>
            <div class="sch-sp__c">
                <div class="sch-sp__ch">
                    <span class="sch-sp__ct">
                        <b><?php esc_html_e('الذاكرة عبر السنوات', 'school-system'); ?></b>
                        <span><?php esc_html_e('ثلاثة أسطر تفتحها معلمة العام القادم في أول يوم — بدل ثلاثة أشهر تكتشفها فيها.', 'school-system'); ?></span>
                    </span>
                    <button type="button" class="sch-sp__act" data-modal-open="sch-edit-memory"><?php esc_html_e('تعديل الذاكرة', 'school-system'); ?></button>
                </div>

                <div class="sch-sp__mem">
                    <?php foreach ($mem as $m) : ?>
                        <div class="sch-sp__mrow <?php echo esc_attr($m['tone']); ?>">
                            <b><?php echo esc_html($m['k']); ?></b>
                            <span class="<?php echo $m['v'] === '' ? 'is-empty' : ''; ?>"><?php echo esc_html($m['v'] !== '' ? $m['v'] : __('لم يُكتب بعد', 'school-system')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($prev_year) : ?>
                    <div class="sch-sp__cap">
                        <span><?php echo esc_html(sprintf(
                            /* translators: 1: اسم السنة 2: ملخّصها */
                            __('من العام السابق — %1$s: %2$s', 'school-system'),
                            (string) $prev_year->year_name,
                            trim(implode(' · ', array_filter([$prev_year->works, $prev_year->avoid, $prev_year->notes])))
                        )); ?></span>
                    </div>
                <?php endif; ?>

                <span class="sch-sp__hint"><?php esc_html_e('وقائعية لا وصفية: «يستجيب للمدح الفردي» نعم، «طفل صعب» لا.', 'school-system'); ?></span>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ══════════ ⑦ الرسوم والنقل ══════════ -->
    <section class="sch-sp__pane<?php echo $tab === 'money' ? ' is-on' : ''; ?>" data-tab-pane="money"
             aria-label="<?php echo esc_attr($tabs['money']); ?>">

        <div class="sch-sp__two">
            <div class="sch-sp__c">
                <div class="sch-sp__ch">
                    <span class="sch-sp__ct"><b><?php esc_html_e('الرسوم', 'school-system'); ?></b></span>
                    <?php if (current_user_can('sch_manage_finance')) : ?>
                        <a class="sch-sp__act sch-sp__act--go" href="<?php echo esc_url(SCH_Dashboard::url('finance')); ?>"><?php esc_html_e('تسجيل دفعة', 'school-system'); ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($invoices === [] && !$sub) : ?>
                    <div class="sch-empty"><strong><?php esc_html_e('لم تُصدر فاتورة بعد', 'school-system'); ?></strong></div>
                <?php else : ?>
                    <div class="sch-sp__fees">
                        <?php foreach ($invoices as $inv) : ?>
                            <div class="sch-sp__fee">
                                <span class="sch-sp__fk">
                                    <b><?php echo esc_html($inv->plan_name ?: __('الرسوم الدراسية', 'school-system')); ?></b>
                                    <span><?php echo esc_html($inv->due_date
                                        ? sprintf(
                                            /* translators: %s: تاريخ الاستحقاق */
                                            __('الاستحقاق %s', 'school-system'),
                                            mysql2date('j F', (string) $inv->due_date)
                                        )
                                        : __('بلا تاريخ استحقاق', 'school-system')); ?></span>
                                </span>
                                <span class="sch-sp__fv" dir="ltr"><?php echo esc_html(number_format((float) $inv->total, 0)); ?></span>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($sub) : ?>
                            <div class="sch-sp__fee">
                                <span class="sch-sp__fk">
                                    <b><?php esc_html_e('رسوم النقل', 'school-system'); ?></b>
                                    <span><?php echo esc_html(sprintf(
                                        /* translators: %s: اسم المسار */
                                        __('مسار %s', 'school-system'),
                                        (string) $sub->route_name
                                    )); ?></span>
                                </span>
                                <span class="sch-sp__fv" dir="ltr"><?php echo esc_html(number_format((float) $sub->fee_amount, 0)); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="sch-sp__fee">
                            <span class="sch-sp__fk">
                                <b><?php esc_html_e('المدفوع', 'school-system'); ?></b>
                                <span><?php echo esc_html(sprintf(
                                    /* translators: %d: عدد الدفعات */
                                    _n('دفعة واحدة', '%d دفعات', $fin['payments'], 'school-system'),
                                    $fin['payments']
                                )); ?></span>
                            </span>
                            <span class="sch-sp__fv is-ok" dir="ltr"><?php echo esc_html(number_format($fin['paid'], 0)); ?></span>
                        </div>

                        <div class="sch-sp__fee<?php echo $fin['remaining'] > 0 ? ' is-due' : ''; ?>">
                            <span class="sch-sp__fk">
                                <b><?php esc_html_e('المتبقي', 'school-system'); ?></b>
                                <span><?php echo esc_html($fin['next_due']
                                    ? sprintf(
                                        /* translators: %s: تاريخ الاستحقاق */
                                        __('الاستحقاق %s', 'school-system'),
                                        mysql2date('j F', $fin['next_due'])
                                    )
                                    : __('لا قسط مستحق', 'school-system')); ?></span>
                            </span>
                            <span class="sch-sp__fv <?php echo $fin['remaining'] > 0 ? 'is-bad' : 'is-ok'; ?>" dir="ltr"><?php echo esc_html(number_format($fin['remaining'], 0)); ?></span>
                        </div>
                    </div>

                    <span class="sch-sp__pay"><span style="width:<?php echo esc_attr((string) $fin['pct']); ?>%"></span></span>
                    <span class="sch-sp__hint"><?php echo esc_html(sprintf(
                        /* translators: 1: النسبة 2: الإجمالي */
                        __('سُدّد %1$d%% من إجمالي %2$s', 'school-system'),
                        $fin['pct'],
                        sch_money($fin['total'])
                    )); ?></span>
                <?php endif; ?>
            </div>

            <div class="sch-sp__col">
                <div class="sch-sp__c">
                    <div class="sch-sp__ch">
                        <span class="sch-sp__ct"><b><?php esc_html_e('النقل', 'school-system'); ?></b></span>
                        <?php if (current_user_can('sch_manage_transport')) : ?>
                            <a class="sch-sp__act" href="<?php echo esc_url(SCH_Dashboard::url('transport')); ?>"><?php esc_html_e('تغيير المسار', 'school-system'); ?></a>
                        <?php endif; ?>
                    </div>

                    <?php if (!$sub) : ?>
                        <div class="sch-empty"><strong><?php esc_html_e('غير مشترك في النقل', 'school-system'); ?></strong></div>
                    <?php else : ?>
                        <div class="sch-sp__trans">
                            <div class="sch-sp__tc">
                                <span><?php esc_html_e('المسار', 'school-system'); ?></span>
                                <b><?php echo esc_html((string) $sub->route_name); ?></b>
                            </div>
                            <div class="sch-sp__tc">
                                <span><?php esc_html_e('نقطة التوقف', 'school-system'); ?></span>
                                <b class="<?php echo $sub->stop_name ? '' : 'is-empty'; ?>"><?php echo esc_html($sub->stop_name ?: __('غير محددة', 'school-system')); ?></b>
                            </div>
                            <div class="sch-sp__tc">
                                <span><?php esc_html_e('رسوم النقل', 'school-system'); ?></span>
                                <b><?php echo esc_html(sch_money($sub->fee_amount)); ?></b>
                            </div>
                        </div>

                        <?php if (!$sub->stop_name) : ?>
                            <div class="sch-sp__warn">
                                <i aria-hidden="true"></i>
                                <span><?php esc_html_e('نقطة التوقف غير محددة — الحارس لا يرى مكان الإنزال.', 'school-system'); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="sch-sp__c">
                    <span class="sch-sp__ct"><b><?php esc_html_e('سجل التغييرات', 'school-system'); ?></b></span>

                    <?php if ($log === []) : ?>
                        <div class="sch-empty"><strong><?php esc_html_e('لا تغييرات مسجّلة', 'school-system'); ?></strong></div>
                    <?php else : ?>
                        <div class="sch-sp__log">
                            <?php foreach ($log as $r) : ?>
                                <div class="sch-sp__lrow">
                                    <b><?php echo esc_html(SCH_Audit::label((string) $r->action)); ?></b>
                                    <span><?php echo esc_html($r->display_name ?: __('النظام', 'school-system')); ?></span>
                                    <code dir="ltr"><?php echo esc_html(mysql2date('d-m H:i', (string) $r->created_at)); ?></code>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    </div><!-- /pages -->

    <!-- ══ الترقيم السفلي ══ -->
    <?php /* الجارتان روابط حقيقية، و«البداية»/«النهاية» تحلّان محلّ الغائبة —
             والأربعة موجودة دائمًا في المستند ليُبدّلها التبديل الفوري. */ ?>
    <div class="sch-sp__pager">
        <span>
            <a class="sch-sp__pg" data-tab-step="-1" <?php echo $prev === '' ? 'hidden' : ''; ?>
               href="<?php echo esc_url(add_query_arg('tab', $prev !== '' ? $prev : $tab, $base)); ?>">
                <span aria-hidden="true">‹</span> <span data-tab-label><?php echo esc_html($prev !== '' ? $tabs[$prev] : ''); ?></span>
            </a>
            <span class="sch-sp__pg" data-tab-edge="start" <?php echo $prev === '' ? '' : 'hidden'; ?>><?php esc_html_e('البداية', 'school-system'); ?></span>
        </span>

        <div class="sch-sp__dots">
            <?php foreach ($tabs as $key => $label) : ?>
                <a class="sch-sp__d<?php echo $key === $tab ? ' is-on' : ''; ?>"
                   href="<?php echo esc_url(add_query_arg('tab', $key, $base)); ?>"
                   data-tab-go="<?php echo esc_attr($key); ?>"
                   data-tab-label="<?php echo esc_attr($label); ?>"
                   title="<?php echo esc_attr($label); ?>"
                   aria-label="<?php echo esc_attr($label); ?>"></a>
            <?php endforeach; ?>
        </div>

        <span>
            <a class="sch-sp__pg sch-sp__pg--go" data-tab-step="1" <?php echo $next === '' ? 'hidden' : ''; ?>
               href="<?php echo esc_url(add_query_arg('tab', $next !== '' ? $next : $tab, $base)); ?>">
                <span data-tab-label><?php echo esc_html($next !== '' ? $tabs[$next] : ''); ?></span> <span aria-hidden="true">›</span>
            </a>
            <span class="sch-sp__pg" data-tab-edge="end" <?php echo $next === '' ? '' : 'hidden'; ?>><?php esc_html_e('النهاية', 'school-system'); ?></span>
        </span>
    </div>
</div>

<?php /* ══════════ النوافذ ══════════ */ ?>

<?php if ($can) : ?>
    <?php SCH_Modal::open('sch-edit-student', __('تعديل الملف', 'school-system'), __('الاسم والمرحلة والصف والحالة — والشعبة تُغيَّر من صفحة «الفصل»', 'school-system')); ?>
        <form method="post">
            <?php wp_nonce_field('sch_update_student', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="update_student">
            <?php $keep(); ?>

            <div class="sch-field">
                <label for="es-name"><?php esc_html_e('الاسم الكامل', 'school-system'); ?></label>
                <input id="es-name" type="text" name="full_name" required maxlength="190"
                       value="<?php echo esc_attr((string) $student->full_name); ?>">
            </div>

            <div class="sch-grid">
                <div class="sch-field">
                    <label for="es-stage"><?php esc_html_e('المرحلة', 'school-system'); ?></label>
                    <select id="es-stage" name="stage">
                        <option value=""><?php esc_html_e('غير محددة', 'school-system'); ?></option>
                        <?php foreach (SCH_Classes::STAGES as $slug => $label) : ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected((string) $student->stage, $slug); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sch-field">
                    <label for="es-grade"><?php esc_html_e('الصف', 'school-system'); ?></label>
                    <input id="es-grade" type="text" name="grade_level" maxlength="60"
                           value="<?php echo esc_attr((string) $student->grade_level); ?>">
                </div>
                <div class="sch-field">
                    <label for="es-sec"><?php esc_html_e('الشعبة', 'school-system'); ?></label>
                    <input id="es-sec" type="text" name="section" maxlength="20"
                           value="<?php echo esc_attr((string) $student->section); ?>">
                </div>
                <div class="sch-field">
                    <label for="es-status"><?php esc_html_e('الحالة', 'school-system'); ?></label>
                    <select id="es-status" name="status">
                        <?php foreach ([
                            'active'      => __('مستمر', 'school-system'),
                            'transferred' => __('منقول', 'school-system'),
                            'withdrawn'   => __('منسحب', 'school-system'),
                            'graduated'   => __('متخرّج', 'school-system'),
                        ] as $slug => $label) : ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected((string) $student->status, $slug); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button class="sch-btn"><?php esc_html_e('حفظ', 'school-system'); ?></button>
        </form>
    <?php SCH_Modal::close(); ?>
<?php endif; ?>

<?php if ($can_docs) : ?>
    <?php SCH_Modal::open('sch-upload-doc', __('رفع مستند', 'school-system'), __('يُخزَّن خارج المجلد العام، ويُسجَّل كل فتح له في سجل النظام', 'school-system')); ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sch_upload_doc', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="upload_doc">
            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
            <?php $keep(); ?>

            <div class="sch-field">
                <label for="doc-type"><?php esc_html_e('نوع المستند', 'school-system'); ?></label>
                <select id="doc-type" name="doc_type" required>
                    <?php foreach (SCH_Enrollment::DOC_TYPES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                    <option value="other"><?php esc_html_e('مستند إضافي', 'school-system'); ?></option>
                </select>
            </div>
            <div class="sch-field">
                <label for="doc-file"><?php esc_html_e('الملف', 'school-system'); ?></label>
                <input id="doc-file" type="file" name="doc" accept="image/jpeg,image/png,application/pdf" required>
                <span class="sch-sp__hint"><?php esc_html_e('JPG أو PNG أو PDF · حتى 5 ميغابايت', 'school-system'); ?></span>
            </div>

            <button class="sch-btn"><?php esc_html_e('رفع', 'school-system'); ?></button>
        </form>
    <?php SCH_Modal::close(); ?>
<?php endif; ?>

<?php if ($can_health) : ?>
    <?php SCH_Modal::open('sch-edit-health', __('السجل الصحي', 'school-system'), __('يراه المدير والصحة المدرسية فقط', 'school-system')); ?>
        <form method="post">
            <?php wp_nonce_field('sch_save_health', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="save_health">
            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
            <?php $keep(); ?>

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

            <div class="sch-field">
                <label for="hh-detail"><?php esc_html_e('تفاصيل الحساسية وما يجب فعله', 'school-system'); ?></label>
                <textarea id="hh-detail" name="notes" rows="3"
                          placeholder="<?php esc_attr_e('نوع الحساسية، الأعراض، والدواء المتاح في غرفة الصحة', 'school-system'); ?>"><?php echo esc_textarea((string) ($health->notes ?? '')); ?></textarea>
            </div>

            <button class="sch-btn"><?php esc_html_e('حفظ السجل الصحي', 'school-system'); ?></button>
        </form>
    <?php SCH_Modal::close(); ?>
<?php endif; ?>

<?php if ($can_notes) : ?>
    <?php SCH_Modal::open('sch-edit-memory', __('الذاكرة عبر السنوات', 'school-system'), __('ثلاثة أسطر تفتحها معلمة العام القادم في أول يوم', 'school-system')); ?>
        <form method="post">
            <?php wp_nonce_field('sch_save_summary', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="save_summary">
            <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $id); ?>">
            <?php $keep(); ?>

            <div class="sch-field">
                <label for="y-works"><?php esc_html_e('ينفع معه', 'school-system'); ?></label>
                <input id="y-works" type="text" name="works" maxlength="300"
                       value="<?php echo esc_attr((string) ($summary->works ?? '')); ?>"
                       placeholder="<?php esc_attr_e('مثال: يستجيب للمدح الفردي أمام مجموعة صغيرة', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="y-avoid"><?php esc_html_e('تجنّب', 'school-system'); ?></label>
                <input id="y-avoid" type="text" name="avoid" maxlength="300"
                       value="<?php echo esc_attr((string) ($summary->avoid ?? '')); ?>"
                       placeholder="<?php esc_attr_e('مثال: توبيخه أمام الصف', 'school-system'); ?>">
            </div>
            <div class="sch-field">
                <label for="y-notes"><?php esc_html_e('ما يجب معرفته', 'school-system'); ?></label>
                <input id="y-notes" type="text" name="notes" maxlength="300"
                       value="<?php echo esc_attr((string) ($summary->notes ?? '')); ?>"
                       placeholder="<?php esc_attr_e('مثال: يجلس في الصف الأول لضعف النظر', 'school-system'); ?>">
            </div>

            <p class="sch-sub"><?php esc_html_e('وقائعية لا وصفية: «يستجيب للمدح الفردي» نعم، «طفل صعب» لا.', 'school-system'); ?></p>
            <button class="sch-btn"><?php esc_html_e('حفظ', 'school-system'); ?></button>
        </form>
    <?php SCH_Modal::close(); ?>
<?php endif; ?>
