<?php
/**
 * شهادات الابن — يراها الأب ويحفظها ويرسلها لأهله.
 *
 * والشهادة المفتوحة **لوحٌ يعلو الشبكة لا شاشة تنقل عنها**: الأب يفتح
 * واحدة ثم يغلقها فيجد نفسه في مكانه من القائمة لا في أعلاها. والوثيقة
 * تُقرأ في لحظتها ثم تُحفظ أو تُرسل، فلا تستحقّ رحلة ذهاب وإياب.
 *
 * والمعرّف في العنوان يكتبه من يشاء، فالشهادة لا تُعرض إلا بعد مطابقة
 * صاحبها بالابن المفتوح ملفّه — وهو الحارس نفسه الذي كان.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id    = (int) ($sch_data['id'] ?? 0);
$one   = isset($_GET['c']) ? absint($_GET['c']) : 0;
$items = SCH_Certificates::of_student($id);

$list = SCH_App::url('certificates', $id);

/* الابن وإخوته من قائمة القالب نفسها لا باستعلام جديد */
$me   = null;
$sibs = [];

foreach ((array) ($sch_data['children'] ?? []) as $kid) {
    if ((int) $kid->id === $id) {
        $me = $kid;
        continue;
    }

    $sibs[] = $kid;
}

$first = $me ? (string) ($me->first_name ?: $me->full_name) : '';

/* الشعبة تُطلب في الحالة الفارغة وحدها: الرأس يعرض العدد حين توجد
   شهادات، فاستعلامُ الشعبة هناك يُدفع ثمنه ولا يُقرأ. */
$class = $items === [] ? SCH_Students::current_class($id) : null;

$cert = $one > 0 ? SCH_Certificates::get($one) : null;

if ($cert && (int) $cert->student_id !== $id) {
    $cert = null;
}
?>

<header class="p-hd">
    <a class="p-hd__back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>"
       aria-label="<?php esc_attr_e('رجوع', 'school-system'); ?>">
        <?php echo sch_icon('chev', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>

    <h1 class="p-h1 p-hd__t"><?php esc_html_e('الشهادات', 'school-system'); ?></h1>

    <?php /* العدد حين توجد شهادات، وهويّة الابن حين لا توجد: الشاشة
             الفارغة تخصّ ابنًا بعينه، ومن لا يعرف أيَّ ابن يقرأ يظنّ
             الشاشة فارغةً للأسرة كلّها. */ ?>
    <span class="p-hd__m">
        <?php if ($items !== []) : ?>
            <?php echo esc_html(sprintf(
                /* translators: %s: عدد الشهادات */
                _n('%s شهادة', '%s شهادات', count($items), 'school-system'),
                number_format_i18n(count($items))
            )); ?>
        <?php elseif ($me) : ?>
            <?php echo esc_html(trim(
                $first . ($class ? ' · ' . SCH_Classes::label($class) : '')
            )); ?>
        <?php endif; ?>
    </span>
</header>

<?php if ($items === []) : ?>

    <?php /* الحلقة المتقطّعة تقول «مكانٌ محجوز لم يُملأ بعد» — والوسام
             داخلها يقول أيَّ مكان. والنصّ يقول متى تصل ومن يعتمدها،
             فالفراغ الصامت يبدو عطلًا في التطبيق. */ ?>
    <div class="p-empty p-empty--cert">
        <span class="p-empty__i">
            <span class="p-empty__ring">
                <?php echo sch_icon('award', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </span>
        </span>

        <b><?php esc_html_e('لا شهادات بعد', 'school-system'); ?></b>

        <p>
            <?php echo esc_html($first !== ''
                ? sprintf(
                    /* translators: %s: الاسم الأول للابن */
                    __('تظهر شهادات %s هنا فور اعتمادها من المعلّمة — عادةً في نهاية كل فصل دراسي، أو بعد المسابقات والأنشطة.', 'school-system'),
                    $first
                )
                : __('تظهر الشهادات هنا فور اعتمادها من المعلّمة — عادةً في نهاية كل فصل دراسي، أو بعد المسابقات والأنشطة.', 'school-system')); ?>
        </p>

        <?php
        /* مخرجٌ من الشاشة الفارغة: لإخوته شهادات غالبًا، وبابٌ إليها
           خيرٌ من شاشة تنتهي بلا شيء يُفعل فيها. */
        if ($sibs !== []) :
            $sib_names = [];

            foreach ($sibs as $sib) {
                $sib_names[] = (string) ($sib->first_name ?: $sib->full_name);
            }

            /* «سامي ولمى» لا «سامي، لمى»: الواو تصل في العربية، والفاصلة تعدّ */
            $sib_label = count($sib_names) === 1
                ? $sib_names[0]
                : implode('، ', array_slice($sib_names, 0, -1)) . ' و' . end($sib_names);
            ?>
            <a class="p-empty__a p-tap" href="<?php echo esc_url(SCH_App::url('certificates', (int) $sibs[0]->id)); ?>">
                <?php echo esc_html(sprintf(
                    /* translators: %s: أسماء الإخوة */
                    __('عرض شهادات %s', 'school-system'),
                    $sib_label
                )); ?>
            </a>
        <?php endif; ?>
    </div>

<?php else : ?>

    <div class="p-certs">
        <?php foreach ($items as $c) :
            /* التاريخ والنوع في سطرٍ واحد كما في التصميم: سطران متتاليان
               بلون واحد يُقرآن سطرًا واحدًا مكسورًا. والوصل بالشرط لا
               بـ`trim` على «·» — المحرف عربيُّ الجوار متعدّد البايتات،
               و`trim` تقطع بالبايت فتُفسد آخر حرف. */
            $c_kind = (string) (SCH_Certificates::TYPES[$c->type][0] ?? '');
            $c_meta = wp_date('j M', strtotime((string) $c->issued_at));

            if ($c_kind !== '') {
                $c_meta .= ' · ' . $c_kind;
            }
            ?>
            <a class="p-certcard p-certcard--<?php echo esc_attr((string) $c->template); ?> p-tap"
               href="<?php echo esc_url(add_query_arg('c', (int) $c->id, $list)); ?>">
                <span class="p-certcard__i"><?php echo sch_icon('award', 20); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <span class="p-certcard__t">
                    <b><?php echo esc_html((string) $c->title); ?></b>
                    <span><?php echo esc_html($c_meta); ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php if ($cert) : ?>

    <?php /* الستارة رابطٌ إلى القائمة نفسها: الإغلاق باللمس خارج اللوح
             سلوكٌ متوقَّع، وربطه بعنوانٍ حقيقي يُبقيه عاملًا بلا جافاسكربت
             ويجعل زرّ الرجوع في المتصفّح يفعل الشيء نفسه. */ ?>
    <a class="p-scrim" href="<?php echo esc_url($list); ?>"
       aria-label="<?php esc_attr_e('إغلاق الشهادة', 'school-system'); ?>"></a>

    <aside class="p-sheet" aria-label="<?php echo esc_attr((string) $cert->title); ?>">
        <span class="p-sheet__grab" aria-hidden="true"></span>

        <div class="p-cert">
            <?php echo SCH_Certificates::svg($cert); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </div>

        <div class="p-sheet__acts">
            <button type="button" class="p-btn p-sheet__b p-tap" onclick="window.print()">
                <?php echo sch_icon('download', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php esc_html_e('حفظ', 'school-system'); ?>
            </button>

            <?php /* «مشاركة» تفتح ورقة النظام فيرسلها الأب في تطبيقه
                     المعتاد. والرقم التسلسلي يسافر مع الرابط: الشهادة
                     وثيقة تُراجَع، ورقمها هو ما تُطلب به. */ ?>
            <button type="button" class="p-btn p-btn--quiet p-sheet__b p-tap" id="p-cert-share"
                    data-url="<?php echo esc_url(add_query_arg('c', (int) $cert->id, $list)); ?>"
                    data-title="<?php echo esc_attr((string) $cert->title); ?>"
                    data-text="<?php echo esc_attr((string) $cert->serial); ?>"
                    data-done="<?php esc_attr_e('نُسخ الرابط', 'school-system'); ?>">
                <?php echo sch_icon('upload', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <span class="p-sheet__lbl"><?php esc_html_e('مشاركة', 'school-system'); ?></span>
            </button>
        </div>
    </aside>

    <script>
    (function () {
      var btn = document.getElementById('p-cert-share');
      if (!btn) { return; }

      var lbl = btn.querySelector('.p-sheet__lbl');

      btn.addEventListener('click', function () {
        var data = { title: btn.dataset.title, text: btn.dataset.text, url: btn.dataset.url };

        /* المشاركة الأصلية أولًا — وما لا يدعمها ينسخ الرابط **ويقول
           إنه نسخ**: الزرّ الذي لا يُظهر أثره يُضغط مرّة أخرى. */
        if (navigator.share) {
          navigator.share(data).catch(function () {});
          return;
        }

        if (navigator.clipboard) {
          navigator.clipboard.writeText(data.url).then(function () {
            if (lbl) { lbl.textContent = btn.dataset.done; }
          }).catch(function () {});
        }
      });
    })();
    </script>

<?php endif; ?>
