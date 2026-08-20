<?php
/** الصحة المدرسية — الترتيب حسب الخطورة لا حسب التاريخ. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$student = SCH_Students::get($id);

if (!$student) { require SCH_PATH . 'frontend/app/home.php'; return; }

$health = SCH_Enrollment::health($id);
$meds   = SCH_Medication::of_student($id);
$visits = SCH_Clinic::of_student($id);

/* تُقرأ مرّة هنا لأنها تحكم أيّ لافتة تُعرض أعلى الشاشة، والعرض بعدها
   يطبعها كما هي — فلا يُعاد حساب الشرط في ثلاثة مواضع. */
$sch_allergy = trim((string) ($health->allergies ?? ''));
$sch_blood   = trim((string) ($health->blood_type ?? ''));

/* نغمة حبّة الإذن تُشتق من `SCH_Medication::STATUSES` نفسها لا من اسم
   مخترَع: الحالات أربع (`active` · `pending` · `ended` · `rejected`)
   وليس فيها `approved` — فمقارنةٌ به لا تصدق أبدًا، ويخرج «سارٍ»
   كهرمانيًّا كأنّه معلَّق، ويخرج «مرفوض» بالنغمة نفسها. */
$sch_med_tone = [
    'active'   => 'ok',
    'pending'  => 'warn',
    'ended'    => 'mute',
    'rejected' => 'bad',
];

// «اليوم» تُقارن بتاريخ الموقع لا بتاريخ الخادم — فرق المنطقة يقدّم يومًا أو يؤخّره
$sch_today = wp_date('Y-m-d');
?>

<header class="p-sub__h">
    <a class="p-chip" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>"
       aria-label="<?php esc_attr_e('رجوع', 'school-system'); ?>">
        <?php echo sch_icon('chev', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>
    <h1 class="p-h1"><?php esc_html_e('الصحة المدرسية', 'school-system'); ?></h1>
    <span class="p-sub__who">
        <?php echo esc_html((string) (($student->first_name ?? '') ?: ($student->full_name ?? ''))); ?>
    </span>
</header>

<!-- الحساسية أولًا وبأعلى نبرة: هي ما يُقرأ في الطوارئ، فلا تُدفن في قائمة -->
<?php if ($sch_allergy !== '') : ?>
    <div class="p-flag p-flag--bad">
        <span class="p-flag__i"><?php echo sch_icon('shield', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-flag__t">
            <b><?php echo esc_html(sprintf(
                /* translators: %s: ما يتحسّس منه الطالب */
                __('حساسية: %s', 'school-system'),
                $sch_allergy
            )); ?></b>
            <span><?php esc_html_e('مسجّلة لدى عيادة المدرسة والمقصف، ويُعمل بها في كل يوم دراسي.', 'school-system'); ?></span>
        </span>
    </div>
<?php else : ?>
    <div class="p-flag p-flag--ok">
        <span class="p-flag__i"><?php echo sch_icon('check', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-flag__t">
            <b><?php esc_html_e('لا حساسية مسجّلة', 'school-system'); ?></b>
            <span><?php esc_html_e('إن استجدّ شيء فأبلغ عيادة المدرسة لتُسجَّل في ملفّه.', 'school-system'); ?></span>
        </span>
    </div>
<?php endif; ?>

<?php if (!empty($health->chronic)) : ?>
    <div class="p-flag p-flag--warn">
        <span class="p-flag__i"><?php echo sch_icon('heart', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <span class="p-flag__t">
            <b><?php esc_html_e('أمراض مزمنة', 'school-system'); ?></b>
            <span><?php echo esc_html((string) $health->chronic); ?></span>
        </span>
    </div>
<?php endif; ?>

<div class="p-vit">
    <div class="p-vit__c">
        <span class="p-vit__k"><?php esc_html_e('فصيلة الدم', 'school-system'); ?></span>
        <?php /* الفصيلة رمز لاتيني (+O)، فتُعزَل باتجاهها وإلا قفزت الإشارة عن حرفها */ ?>
        <?php if ($sch_blood !== '') : ?>
            <b class="p-vit__v p-nm"><?php echo esc_html($sch_blood); ?></b>
        <?php else : ?>
            <b class="p-vit__v p-vit__v--na"><?php esc_html_e('غير محددة', 'school-system'); ?></b>
        <?php endif; ?>
    </div>

    <div class="p-vit__c">
        <span class="p-vit__k"><?php esc_html_e('زيارات العيادة', 'school-system'); ?></span>
        <?php /* بلا `.p-nm`: الرقم عربيّ الأرقام هنا، و`direction: ltr` عليه
                 يدفعه إلى الطرف الآخر من البطاقة بلا فائدة. */ ?>
        <b class="p-vit__v"><?php echo esc_html(number_format_i18n(count($visits))); ?></b>
        <span class="p-vit__n"><?php esc_html_e('المسجّلة في ملفّه', 'school-system'); ?></span>
    </div>
</div>

<h2 class="p-h2"><?php esc_html_e('أذونات الأدوية', 'school-system'); ?></h2>

<?php if ($meds === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('pill', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا أدوية مسجّلة', 'school-system'); ?></b>
        <p><?php esc_html_e('اطلب إذنًا إن كان ابنك يحتاج دواءً في المدرسة.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <?php foreach ($meds as $med) :
        $sch_st = (string) ($med->status ?? '');
        ?>
        <article class="p-vst">
            <div class="p-vst__h">
                <b class="p-vst__t"><?php echo esc_html((string) $med->drug_name); ?></b>
                <span class="p-tag p-tag--<?php echo esc_attr($sch_med_tone[$sch_st] ?? 'mute'); ?>">
                    <?php echo esc_html(SCH_Medication::STATUSES[$sch_st] ?? ''); ?>
                </span>
            </div>
            <div class="p-vst__b">
                <div class="p-kv">
                    <span class="p-kv__k"><?php esc_html_e('الجرعة', 'school-system'); ?></span>
                    <span class="p-kv__v"><?php echo esc_html(trim((string) $med->dose . ' · ' . (string) $med->times)); ?></span>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($visits !== []) : ?>
    <h2 class="p-h2"><?php esc_html_e('زيارات العيادة', 'school-system'); ?></h2>

    <?php foreach (array_slice($visits, 0, 10) as $v) :
        /* `visit_at` عمود لا يقبل الفراغ، و`?: time()` وحدها احتياطٌ من
           قيمة صفريّة (`0000-00-00`) لأن `wp_date` لا تقبل false. */
        $sch_ts   = strtotime((string) ($v->visit_at ?? '')) ?: time();
        $sch_time = wp_date('g:i', $sch_ts);

        /* «اليوم · ١٢:٠٥» أدلّ من تاريخ كامل لزيارةٍ وقعت قبل ساعة — والأب
           يفتح الشاشة غالبًا في يومها. وما مضى يُكتفى فيه باليوم والشهر. */
        $sch_when = wp_date('Y-m-d', $sch_ts) === $sch_today
            ? sprintf(
                /* translators: %s: وقت الزيارة */
                __('اليوم · %s', 'school-system'),
                $sch_time
            )
            : wp_date('j M', $sch_ts);

        // العمود `action_note` — ولا عمود `action` في الجدول أصلًا
        $sch_act = trim((string) ($v->action_note ?? ''));
        ?>
        <article class="p-vst">
            <div class="p-vst__h">
                <b class="p-vst__t"><?php echo esc_html((string) $v->complaint); ?></b>
                <span class="p-vst__d"><?php echo esc_html($sch_when); ?></span>
            </div>
            <div class="p-vst__b">
                <div class="p-kv">
                    <span class="p-kv__k"><?php esc_html_e('ما فُعل', 'school-system'); ?></span>
                    <span class="p-kv__v"><?php echo esc_html($sch_act !== '' ? $sch_act : __('لم يُذكر', 'school-system')); ?></span>
                </div>
                <div class="p-kv">
                    <?php /* «أُرسل للمنزل» أهم سطر في البطاقة: هو ما يفسّر مكالمةً فاتت الأب،
                             ومعه الساعة — الأب يقابلها بمكالمةٍ رآها في سجلّ جواله. */ ?>
                    <span class="p-kv__k"><?php esc_html_e('أُرسل للمنزل', 'school-system'); ?></span>
                    <span class="p-kv__v p-kv__v--<?php echo esc_attr(!empty($v->sent_home) ? 'warn' : 'ok'); ?>">
                        <?php echo esc_html(!empty($v->sent_home)
                            ? sprintf(
                                /* translators: %s: وقت الزيارة */
                                __('نعم — %s', 'school-system'),
                                $sch_time
                            )
                            : __('لا — عاد إلى الفصل', 'school-system')); ?>
                    </span>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<p class="p-fine"><?php esc_html_e('التشخيصات والأدوية الموصوفة لدى عيادة المدرسة.', 'school-system'); ?></p>
