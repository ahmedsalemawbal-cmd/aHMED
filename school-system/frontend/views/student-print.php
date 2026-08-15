<?php
/**
 * وثيقة الطالب — صفحة A4 مستقلة للطباعة أو الحفظ كـPDF.
 * لا شريط جانبي ولا أزرار: ورقة واحدة نظيفة.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$student = SCH_Students::get($id);

if (!$student) {
    status_header(404);
    exit;
}

$class     = SCH_Students::current_class($id);
$guardians = SCH_Guardians::of_student($id);
$sub       = SCH_Routes::subscription_of($id);
$full      = SCH_Enrollment::full_name($student);
$school    = sch_settings('school_name', get_bloginfo('name'));
$photo_url = $student->photo_file
    ? add_query_arg('sch_photo', '1', SCH_Dashboard::url('students', $id))
    : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($full . ' — ' . ($student->academic_no ?: '')); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">

    <style>
    @page { size: A4 portrait; margin: 14mm 14mm 12mm; }

    :root {
      --ink:#0F1720; --muted:#64748B; --line:#E2E8F0; --line2:#CBD5E1;
      --accent:#1F5F52; --soft:#E8F0ED; --paper:#F8FAFC;
    }

    * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    body {
      margin: 0;
      background: #E9EEF3;
      color: var(--ink);
      font-family: 'Cairo', system-ui, sans-serif;
      font-size: 11pt;
      line-height: 1.7;
      -webkit-font-smoothing: antialiased;
    }

    /* شريط الأدوات — يختفي عند الطباعة */
    .bar {
      position: sticky; top: 0; z-index: 5;
      display: flex; align-items: center; justify-content: center; gap: 10px;
      padding: 12px; background: #fff; border-bottom: 1px solid var(--line);
    }
    .bar button, .bar a {
      padding: 8px 18px; font-family: inherit; font-size: 14px; font-weight: 700;
      border-radius: 4px; cursor: pointer; text-decoration: none; border: 1px solid var(--line2);
      background: #fff; color: var(--ink);
    }
    .bar .primary { background: var(--accent); border-color: var(--accent); color: #fff; }
    .bar .hint { font-size: 12px; font-weight: 400; color: var(--muted); border: 0; background: none; cursor: default; }

    /* الورقة */
    .sheet {
      width: 210mm; min-height: 297mm;
      margin: 18px auto; padding: 14mm;
      background: #fff;
      box-shadow: 0 10px 30px -14px rgba(15,23,32,.35);
    }

    .head {
      display: flex; align-items: flex-start; justify-content: space-between; gap: 12mm;
      padding-bottom: 5mm; border-bottom: 2px solid var(--accent);
    }
    .head h1 { margin: 0; font-size: 16pt; font-weight: 700; line-height: 1.35; }
    .head .sub { color: var(--muted); font-size: 9.5pt; }
    .head .doc { text-align: end; font-size: 9pt; color: var(--muted); letter-spacing: .06em; }

    .top {
      display: grid; grid-template-columns: 34mm 1fr 30mm; gap: 8mm;
      padding: 7mm 0; border-bottom: 1px solid var(--line);
    }
    .photo {
      width: 34mm; height: 43mm; overflow: hidden;
      border: 1px solid var(--line2); border-radius: 2mm; background: var(--soft);
    }
    .photo img { width: 100%; height: 100%; object-fit: cover; display: block; }

    .name { font-size: 15pt; font-weight: 700; line-height: 1.35; margin: 0 0 1mm; }
    .name-en { color: var(--muted); font-size: 9.5pt; letter-spacing: .04em; }

    .idbox {
      display: inline-block; margin-top: 3mm; padding: 2mm 5mm;
      background: var(--paper); border: 1px solid var(--line2); border-radius: 1.5mm;
      font-family: ui-monospace, monospace; font-size: 13pt; letter-spacing: .12em;
    }
    .idbox small { display: block; font-family: 'Cairo', sans-serif; font-size: 7.5pt; letter-spacing: 0; color: var(--muted); }

    .qr { text-align: center; }
    .qr svg { width: 28mm; height: 28mm; display: block; }
    .qr span { display: block; margin-top: 1.5mm; font-family: ui-monospace, monospace; font-size: 8pt; color: var(--muted); }

    h2 {
      margin: 6mm 0 3mm; font-size: 10.5pt; font-weight: 700;
      padding-bottom: 1.5mm; border-bottom: 1px solid var(--line);
    }

    .fields { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm 8mm; margin: 0; }
    .fields > div { min-width: 0; }
    .fields dt { font-size: 8.5pt; color: var(--muted); }
    .fields dd { margin: 0; font-weight: 700; font-size: 10.5pt; font-variant-numeric: tabular-nums; word-break: break-word; }
    dd[dir="ltr"] { text-align: end; }

    table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    th {
      text-align: start; font-size: 8.5pt; font-weight: 700; color: var(--muted);
      padding: 2mm 2mm; border-bottom: 1px solid var(--line2);
    }
    td { padding: 2.5mm 2mm; border-bottom: 1px solid var(--line); font-variant-numeric: tabular-nums; }
    tr:last-child td { border-bottom: 0; }

    .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 16mm; margin-top: 12mm; }
    .sign div { border-top: 1px solid var(--line2); padding-top: 2mm; font-size: 9pt; color: var(--muted); text-align: center; }

    .foot {
      margin-top: 8mm; padding-top: 3mm; border-top: 1px solid var(--line);
      display: flex; justify-content: space-between;
      font-size: 8pt; color: var(--muted);
    }

    @media print {
      body { background: #fff; }
      .bar { display: none; }
      .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    }
    </style>
</head>
<body>

<div class="bar">
    <button type="button" class="primary" onclick="window.print()"><?php esc_html_e('طباعة أو حفظ PDF', 'school-system'); ?></button>
    <a href="<?php echo esc_url(SCH_Dashboard::url('students', $id)); ?>"><?php esc_html_e('رجوع للملف', 'school-system'); ?></a>
    <span class="hint"><?php esc_html_e('من نافذة الطباعة اختر «حفظ كـPDF» للتحميل', 'school-system'); ?></span>
</div>

<div class="sheet">

    <header class="head">
        <div>
            <h1><?php echo esc_html($school); ?></h1>
            <span class="sub"><?php esc_html_e('ملف الطالب — وثيقة رسمية', 'school-system'); ?></span>
        </div>
        <div class="doc">
            STUDENT RECORD<br>
            <span dir="ltr"><?php echo esc_html($student->academic_no ?: ''); ?></span>
        </div>
    </header>

    <section class="top">
        <div class="photo">
            <?php if ($photo_url) : ?>
                <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($full); ?>">
            <?php else : ?>
                <svg viewBox="0 0 100 124" width="100%" height="100%" aria-hidden="true">
                    <rect width="100" height="124" fill="var(--soft)"/>
                    <circle cx="50" cy="44" r="20" fill="var(--accent)" opacity=".22"/>
                    <path d="M14 124c0-22 16-34 36-34s36 12 36 34z" fill="var(--accent)" opacity=".22"/>
                </svg>
            <?php endif; ?>
        </div>

        <div>
            <p class="name"><?php echo esc_html($full); ?></p>
            <span class="name-en" dir="ltr"><bdi><?php echo esc_html($student->name_en ?: ''); ?></bdi></span>

            <div class="idbox">
                <?php echo esc_html($student->academic_no ?: '—'); ?>
                <small><?php esc_html_e('الرقم الأكاديمي', 'school-system'); ?></small>
            </div>
        </div>

        <div class="qr">
            <?php echo SCH_QR::svg((string) ($student->academic_no ?: $student->qr_token), 4, 1); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <span dir="ltr"><?php echo esc_html($student->student_no ?: ''); ?></span>
        </div>
    </section>

    <h2><?php esc_html_e('البيانات الأساسية', 'school-system'); ?></h2>
    <dl class="fields">
        <div><dt><?php esc_html_e('رقم الطالب', 'school-system'); ?></dt><dd dir="ltr"><?php echo esc_html($student->student_no ?: '—'); ?></dd></div>
        <div><dt><?php echo esc_html(SCH_Enrollment::ID_TYPES[$student->id_type] ?? __('رقم الهوية', 'school-system')); ?></dt><dd dir="ltr"><?php echo esc_html($student->national_id ?: '—'); ?></dd></div>
        <div><dt><?php esc_html_e('الجنسية', 'school-system'); ?></dt><dd><?php echo esc_html($student->nationality ?: '—'); ?></dd></div>
        <div><dt><?php esc_html_e('تاريخ الميلاد', 'school-system'); ?></dt><dd dir="ltr"><?php echo esc_html($student->birth_date ?: '—'); ?></dd></div>
        <div><dt><?php esc_html_e('الجنس', 'school-system'); ?></dt><dd><?php echo esc_html($student->gender === 'female' ? __('أنثى', 'school-system') : __('ذكر', 'school-system')); ?></dd></div>
        <div><dt><?php esc_html_e('الحالة', 'school-system'); ?></dt><dd><?php esc_html_e('نشط', 'school-system'); ?></dd></div>
        <div><dt><?php esc_html_e('المرحلة', 'school-system'); ?></dt><dd><?php echo esc_html(SCH_Classes::STAGES[$student->stage] ?? '—'); ?></dd></div>
        <div><dt><?php esc_html_e('الصف والفصل', 'school-system'); ?></dt><dd><?php echo esc_html($class ? $class->grade_level . ' / ' . $class->section : '—'); ?></dd></div>
        <div><dt><?php esc_html_e('تاريخ التسجيل', 'school-system'); ?></dt><dd dir="ltr"><?php echo esc_html($student->enrolled_at ?: '—'); ?></dd></div>
    </dl>

    <h2><?php esc_html_e('أولياء الأمور', 'school-system'); ?></h2>
    <?php if ($guardians === []) : ?>
        <p class="name-en"><?php esc_html_e('لا يوجد ولي أمر مرتبط.', 'school-system'); ?></p>
    <?php else : ?>
        <table>
            <thead><tr>
                <th><?php esc_html_e('الاسم', 'school-system'); ?></th>
                <th><?php esc_html_e('صلة القرابة', 'school-system'); ?></th>
                <th><?php esc_html_e('الجوال', 'school-system'); ?></th>
                <th><?php esc_html_e('رقم الهوية', 'school-system'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($guardians as $g) : ?>
                <tr>
                    <td><?php echo esc_html($g->display_name); ?></td>
                    <td><?php echo esc_html(SCH_Guardians::relation_label($g->relation)); ?></td>
                    <td dir="ltr"><?php echo esc_html($g->phone ?: '—'); ?></td>
                    <td dir="ltr"><?php echo esc_html($g->national_id ?: '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($sub) : ?>
        <h2><?php esc_html_e('النقل المدرسي', 'school-system'); ?></h2>
        <dl class="fields">
            <div><dt><?php esc_html_e('المسار', 'school-system'); ?></dt><dd><?php echo esc_html($sub->route_name); ?></dd></div>
            <div><dt><?php esc_html_e('نقطة التوقف', 'school-system'); ?></dt><dd><?php echo esc_html($sub->stop_name ?: '—'); ?></dd></div>
            <div><dt><?php esc_html_e('الرسوم السنوية', 'school-system'); ?></dt><dd><?php echo esc_html(sch_money($sub->fee_amount)); ?></dd></div>
        </dl>
    <?php endif; ?>

    <div class="sign">
        <div><?php esc_html_e('توقيع ولي الأمر', 'school-system'); ?></div>
        <div><?php esc_html_e('ختم وتوقيع إدارة المدرسة', 'school-system'); ?></div>
    </div>

    <footer class="foot">
        <span><?php echo esc_html($school); ?></span>
        <span dir="ltr"><?php echo esc_html(current_time('Y-m-d H:i')); ?></span>
    </footer>

</div>

</body>
</html>
