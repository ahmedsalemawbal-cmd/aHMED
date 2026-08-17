<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

// صفحة الإعدادات الرئيسية — بيانات المدرسة + لمحة حالة النظام.
// التنقّل بين أقسام الإعدادات يتم من الشريط الجانبي (يستولي عليه سياق الإعدادات).
$s       = sch_settings();
$missing = SCH_Activator::missing_tables();
$sch_cur = SCH_Years::current();
?>
<h1 class="sch-title"><?php echo sch_icon('cog', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('بيانات المدرسة', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('تظهر هذه البيانات على البطاقات والشهادات والتقارير المطبوعة، وفي رأس كل شاشة.', 'school-system'); ?></p>

<?php if ($missing !== []) : ?>
    <div class="sch-notice sch-notice--error">
        <strong><?php echo esc_html(sprintf(
            /* translators: %d: عدد الجداول */
            _n('جدول واحد لم يُنشأ في قاعدة البيانات', '%d جداول لم تُنشأ في قاعدة البيانات', count($missing), 'school-system'),
            count($missing)
        )); ?></strong>
        <p><?php esc_html_e('كل عملية على هذه الجداول ستفشل. حدّث الإضافة أو راجع صلاحيات قاعدة البيانات.', 'school-system'); ?></p>
        <code dir="ltr"><?php echo esc_html(implode(' · ', $missing)); ?></code>
    </div>
<?php endif; ?>

<div class="sch-set2">
    <div class="sch-set2__main">
        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('cog', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('الملف التعريفي', 'school-system'); ?></h2>
                    <p><?php esc_html_e('اسم المدرسة ووسائل التواصل والرقم الوزاري.', 'school-system'); ?></p>
                </div>
            </header>
            <form method="post">
                <?php wp_nonce_field('sch_save_settings', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="save_settings">
                <div class="sch-grid">
                    <label class="sch-field"><span><?php esc_html_e('اسم المدرسة', 'school-system'); ?></span>
                        <input type="text" name="school_name" aria-label="<?php esc_attr_e('اسم المدرسة', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['school_name'] ?? '')); ?>"></label>
                    <label class="sch-field"><span><?php esc_html_e('جوال المدرسة', 'school-system'); ?></span>
                        <input type="tel" name="school_phone" dir="ltr" aria-label="<?php esc_attr_e('جوال المدرسة', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['school_phone'] ?? '')); ?>"></label>
                    <label class="sch-field"><span><?php esc_html_e('البريد الإلكتروني', 'school-system'); ?></span>
                        <input type="email" name="school_email" dir="ltr" aria-label="<?php esc_attr_e('البريد الإلكتروني', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['school_email'] ?? '')); ?>"></label>
                    <label class="sch-field"><span><?php esc_html_e('الرقم الوزاري', 'school-system'); ?></span>
                        <input type="text" name="moe_id" dir="ltr" aria-label="<?php esc_attr_e('الرقم الوزاري', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['moe_id'] ?? '')); ?>"></label>
                    <label class="sch-field sch-field--wide"><span><?php esc_html_e('العنوان', 'school-system'); ?></span>
                        <input type="text" name="address" aria-label="<?php esc_attr_e('العنوان', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['address'] ?? '')); ?>"></label>
                </div>
                <button class="sch-btn sch-mt"><?php esc_html_e('حفظ البيانات', 'school-system'); ?></button>
            </form>
        </div>

        <?php /* الهوية المرئية: الشعار يظهر في الشريط الجانبي وصفحة الدخول،
                 والأيقونة في تبويب المتصفح. */ ?>
        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('badge', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('الشعار وأيقونة التبويب', 'school-system'); ?></h2>
                    <p><?php esc_html_e('الشعار يحلّ محل الحرف في الشريط الجانبي، والأيقونة تظهر في تبويب المتصفح.', 'school-system'); ?></p>
                </div>
            </header>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('sch_save_brand', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="save_brand">

                <div class="sch-blogogrid">
                    <?php
                    $sch_logo = SCH_Brand::logo();
                    $sch_fav  = SCH_Brand::favicon();
                    $sch_mark = mb_substr((string) sch_settings('school_name', get_bloginfo('name')), 0, 1);
                    ?>

                    <div class="sch-blogo">
                        <span class="sch-blogo__prev sch-blogo__prev--lg">
                            <?php if ($sch_logo !== '') : ?>
                                <img src="<?php echo esc_url($sch_logo); ?>" alt="<?php esc_attr_e('شعار المدرسة', 'school-system'); ?>">
                            <?php else : ?>
                                <i aria-hidden="true"><?php echo esc_html($sch_mark); ?></i>
                            <?php endif; ?>
                        </span>
                        <div class="sch-blogo__b">
                            <label class="sch-field">
                                <span><?php esc_html_e('شعار المدرسة', 'school-system'); ?></span>
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                                       aria-label="<?php esc_attr_e('ملف شعار المدرسة', 'school-system'); ?>">
                            </label>
                            <p class="sch-sub"><?php esc_html_e('PNG أو JPG أو WEBP · مربّع يفضَّل · حتى ٢ ميجابايت', 'school-system'); ?></p>
                            <?php if ($sch_logo !== '') : ?>
                                <label class="sch-check">
                                    <input type="checkbox" name="drop_logo" value="1">
                                    <?php esc_html_e('إزالة الشعار', 'school-system'); ?>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="sch-blogo">
                        <span class="sch-blogo__prev">
                            <?php if ($sch_fav !== '') : ?>
                                <img src="<?php echo esc_url($sch_fav); ?>" alt="<?php esc_attr_e('أيقونة التبويب', 'school-system'); ?>">
                            <?php else : ?>
                                <i aria-hidden="true"><?php echo esc_html($sch_mark); ?></i>
                            <?php endif; ?>
                        </span>
                        <div class="sch-blogo__b">
                            <label class="sch-field">
                                <span><?php esc_html_e('أيقونة التبويب', 'school-system'); ?></span>
                                <input type="file" name="favicon" accept="image/png,image/x-icon,image/webp"
                                       aria-label="<?php esc_attr_e('ملف أيقونة التبويب', 'school-system'); ?>">
                            </label>
                            <p class="sch-sub"><?php esc_html_e('PNG أو ICO · مربّعة ٥١٢×٥١٢ أو أصغر', 'school-system'); ?></p>
                            <?php if ($sch_fav !== '') : ?>
                                <label class="sch-check">
                                    <input type="checkbox" name="drop_favicon" value="1">
                                    <?php esc_html_e('إزالة الأيقونة', 'school-system'); ?>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <button class="sch-btn sch-mt"><?php esc_html_e('حفظ الهوية', 'school-system'); ?></button>
            </form>
        </div>
    </div>

    <aside class="sch-set2__side">
        <?php /* روابط التطبيقين — هذه ما يُرفع للمتاجر وما يُرسل لأولياء الأمور */ ?>
        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('badge', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('تطبيقا الجوال', 'school-system'); ?></h2>
                    <p><?php esc_html_e('تطبيقان فقط: الأسرة والموظفون. هذه روابطهما للتثبيت وللرفع على المتاجر.', 'school-system'); ?></p>
                </div>
            </header>
            <div class="sch-set__stat">
                <?php foreach (SCH_Apps::packs() as $sch_pk => $sch_pv) : ?>
                    <div class="sch-set__row">
                        <span><?php echo esc_html((string) $sch_pv['short']); ?></span>
                        <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(SCH_Apps::url($sch_pk)); ?>" target="_blank" rel="noopener">
                            <?php esc_html_e('فتح', 'school-system'); ?>
                        </a>
                    </div>
                    <div class="sch-set__row">
                        <span class="sch-sub"><?php esc_html_e('الرابط', 'school-system'); ?></span>
                        <code class="sch-code" dir="ltr"><?php echo esc_html(SCH_Apps::url($sch_pk)); ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('shield', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('حالة النظام', 'school-system'); ?></h2>
                    <p><?php esc_html_e('لمحة سريعة على سلامة التركيب.', 'school-system'); ?></p>
                </div>
            </header>
            <div class="sch-set__stat">
                <div class="sch-set__row">
                    <span><?php esc_html_e('إصدار النظام', 'school-system'); ?></span>
                    <b dir="ltr"><?php echo esc_html(SCH_VERSION); ?></b>
                </div>
                <div class="sch-set__row">
                    <span><?php esc_html_e('قاعدة البيانات', 'school-system'); ?></span>
                    <?php if ($missing === []) : ?>
                        <b class="is-ok"><?php echo sch_icon('check', 14); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('سليمة', 'school-system'); ?></b>
                    <?php else : ?>
                        <b class="is-bad"><?php echo esc_html(sprintf(
                            /* translators: %d: عدد الجداول */
                            _n('جدول ناقص', '%d جداول ناقصة', count($missing), 'school-system'),
                            count($missing)
                        )); ?></b>
                    <?php endif; ?>
                </div>
                <div class="sch-set__row">
                    <span><?php esc_html_e('السنة النشطة', 'school-system'); ?></span>
                    <b><?php echo esc_html($sch_cur->name ?? '—'); ?></b>
                </div>
            </div>
        </div>
    </aside>
</div>
