<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * النافذة المنبثقة — مكوّن واحد لكل الشاشات.
 *
 * **المبدأ:** الشاشة تعرض ولا تُدخل. والحقول الفارغة تشغل نصف الشاشة وتوحي
 * بعمل مطلوب منك، والموظف غالبًا جاء ليقرأ لا ليكتب.
 *
 * **وقاعدتان تعلّمناهما بالتجربة:**
 * 1. **لا تُغلق بالخلفية ولا بـEsc** — نقرة عابرة تمحو نموذجًا مملوءًا.
 *    الإغلاق بزر X أو «إلغاء» وحدهما.
 * 2. **تبقى مفتوحة إن عاد الحفظ بخطأ** فلا يُعيد الموظف الكتابة.
 */
final class SCH_Modal
{
    /** زر يفتح النافذة. */
    public static function button(string $id, string $label, string $icon = 'plus'): void
    {
        ?>
        <button type="button" class="sch-btn sch-add" data-modal-open="<?php echo esc_attr($id); ?>">
            <?php echo sch_icon($icon, 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php echo esc_html($label); ?>
        </button>
        <?php
    }

    /** بداية النافذة — ما بعدها حتى `close()` هو محتواها. */
    public static function open(string $id, string $title, string $sub = ''): void
    {
        ?>
        <div class="sch-modal" id="<?php echo esc_attr($id); ?>" hidden>
            <div class="sch-modal__veil"></div>

            <div class="sch-modal__box" role="dialog" aria-modal="true"
                 aria-label="<?php echo esc_attr($title); ?>">

                <header class="sch-modal__head">
                    <span class="sch-modal__t">
                        <b><?php echo esc_html($title); ?></b>
                        <?php if ($sub !== '') : ?>
                            <em><?php echo esc_html($sub); ?></em>
                        <?php endif; ?>
                    </span>

                    <button type="button" class="sch-modal__x" data-modal-close
                            aria-label="<?php esc_attr_e('إغلاق', 'school-system'); ?>">
                        <?php echo sch_icon('x', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </button>
                </header>

                <div class="sch-modal__body">
        <?php
    }

    /** نهاية النافذة، ومعها زر الإلغاء. */
    public static function close(): void
    {
        ?>
                </div>

                <footer class="sch-modal__foot">
                    <button type="button" class="sch-btn sch-btn--quiet" data-modal-close>
                        <?php esc_html_e('إلغاء', 'school-system'); ?>
                    </button>
                </footer>
            </div>
        </div>
        <?php
    }

    /**
     * رأس الشاشة: عنوان وعدّاد على طرف، وزر الإضافة على الآخر.
     * يُوحّد شكل كل شاشة فلا تختلف واحدة عن أخرى بلا سبب.
     */
    /**
     * رأس الشاشة — صفّ واحد.
     *
     * كان العنوان سطرًا، والعروض سطرًا، والتصدير سطرًا ثالثًا: ثلاثة أسطر
     * لثلاث معلومات صغيرة، والجدول يُدفع تحت الطيّة. الأدوات (`$tools`) تدخل
     * الصفّ نفسه بين العنوان والفعل الأساسي.
     *
     * @param string $tools HTML مُهرَّب مسبقًا من مكوّنات النظام (منسدلة العروض، روابط التصدير)
     */
    public static function head(string $title, string $count = '', string $btn_id = '', string $btn_label = '', string $icon = 'plus', string $tools = ''): void
    {
        ?>
        <div class="sch-head">
            <div class="sch-head__t">
                <h1 class="sch-title"><?php echo esc_html($title); ?></h1>
                <?php if ($count !== '') : ?>
                    <p class="sch-sub"><?php echo esc_html($count); ?></p>
                <?php endif; ?>
            </div>

            <?php if ($tools !== '' || $btn_id !== '') : ?>
                <div class="sch-head__acts">
                    <?php echo $tools; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php if ($btn_id !== '') : ?>
                        <?php self::button($btn_id, $btn_label, $icon); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
