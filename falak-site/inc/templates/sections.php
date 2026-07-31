<?php
/**
 * قوالب: المعلمون · المعرض · الأسئلة الشائعة · تواصل معنا · التسجيل.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ─────────────────────────────────────────
   المعلمون
───────────────────────────────────────────*/
function falak_tpl_teachers() {
	falak_page_head( 'المعلمون والمعلمات', 'كادرٌ تعليميٌّ مؤهّل ومتخصّص في مختلف المواد' );

	$teachers = array(
		array( 'أ. / أحمد العتيبي', 'مدير المدرسة', 'إدارة تربوية' ),
		array( 'أ. / عبدالرحمن', 'مشرف قسم البنين', 'خبرة تربوية' ),
		array( 'أ. / خالد', 'معلم رياضيات', 'بكالوريوس رياضيات' ),
		array( 'أ. / يوسف', 'معلم لغة عربية', 'بكالوريوس لغة عربية' ),
		array( 'أ. / فاطمة', 'مشرفة قسم البنات', 'خبرة تربوية' ),
		array( 'أ. / مريم', 'معلمة علوم', 'بكالوريوس علوم' ),
		array( 'أ. / نورة', 'معلمة لغة إنجليزية', 'بكالوريوس لغات' ),
		array( 'أ. / سارة', 'معلمة رياض أطفال', 'تخصّص طفولة مبكّرة' ),
	);
	?>
	<section class="fk-teachers" style="background:#fff">
		<div class="fk-sec-head fk-reveal">
			<span class="fk-eyebrow center">كادرنا</span>
			<h2 class="fk-h2">نُخبةٌ من <span>المعلمين والمعلمات</span></h2>
			<p class="fk-lead">يجمع كادرنا بين الكفاءة العلمية والخبرة التربوية والعناية بكل طالب.</p>
		</div>
		<div class="fk-teach-grid">
			<?php foreach ( $teachers as $t ) : ?>
				<article class="fk-teach-card fk-reveal">
					<div class="fk-teach-photo"><span class="ph"><?php falak_icon( 'user', 42 ); ?></span></div>
					<div class="fk-teach-body">
						<h3><?php echo esc_html( $t[0] ); ?></h3>
						<div class="role"><?php echo esc_html( $t[1] ); ?></div>
						<span class="ijaza"><?php falak_icon( 'award', 13 ); ?> <?php echo esc_html( $t[2] ); ?></span>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	falak_cta_band( 'تعليمٌ نوعيٌّ <span>على أيدي نخبة</span>', 'سجّل الآن ليبدأ ابنك أو ابنتك رحلته التعليمية على يد نخبةٍ من المعلمين والمعلمات.' );
}

/* ─────────────────────────────────────────
   معرض الصور
───────────────────────────────────────────*/
function falak_tpl_gallery() {
	falak_page_head( 'معرض الصور', 'لقطات من فصولنا وأنشطتنا وفعالياتنا المدرسية' );

	$caps = array(
		'الفصول الذكية', 'المختبرات العلمية', 'الأنشطة الرياضية', 'أنشطة قسم البنين',
		'أنشطة قسم البنات', 'مرحلة رياض الأطفال', 'الرحلات المدرسية', 'المعارض والمواهب', 'تكريم المتفوّقين',
	);
	?>
	<section class="fk-gallery">
		<div class="fk-gal-grid">
			<?php foreach ( $caps as $c ) : ?>
				<div class="fk-gal-item fk-reveal">
					<span class="ph"><?php falak_icon( 'image', 46 ); ?></span>
					<span class="cap"><?php echo esc_html( $c ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<p style="text-align:center;color:var(--c-muted);margin-top:32px;padding:0 24px">تُستبدل هذه المربّعات بصور المدرسة الحقيقية من لوحة التحكم لاحقًا.</p>
	</section>
	<?php
	falak_cta_band();
}

/* ─────────────────────────────────────────
   الأسئلة الشائعة
───────────────────────────────────────────*/
function falak_tpl_faq() {
	falak_page_head( 'الأسئلة الشائعة', 'إجابات لأكثر ما يسأل عنه أولياء الأمور' );

	$faqs = array(
		array( 'ما المراحل الدراسية التي تقبلها المدرسة؟', 'نستقبل الطلاب في قسم البنين من الحضانة إلى الصف السادس الابتدائي، وفي قسم البنات من الحضانة إلى الصف الثالث الثانوي.' ),
		array( 'ما المنهج المعتمد في المدرسة؟', 'نعتمد مناهج وزارة التعليم المعتمدة، ونُثريها ببرامجَ وأنشطةٍ إثرائيةٍ ووسائلَ تعليميةٍ حديثة.' ),
		array( 'كيف أسجّل ابني أو ابنتي؟', 'عبّئ نموذج التسجيل في صفحة «التسجيل»، وسيتواصل معك فريقنا لاستكمال الإجراءات وتحديد المرحلة والموعد المناسب.' ),
		array( 'ما رسوم الدراسة؟', 'تختلف الرسوم بحسب المرحلة الدراسية، ويتم توضيحها لك عند التواصل. تواصل معنا لمعرفة التفاصيل والعروض المتاحة.' ),
		array( 'هل يتوفّر نقلٌ مدرسي؟', 'نعم، يتوفّر نقلٌ مدرسيٌّ آمنٌ لبعض الأحياء. يُرجى التواصل مع الإدارة لمعرفة المسارات والتغطية.' ),
		array( 'ما المرافق المتوفّرة في المدرسة؟', 'تضم المدرسة فصولًا مجهّزة، ومختبرات علمية وحاسوبية، ومكتبة، وملاعبَ وصالاتِ أنشطة، ومصلّى — في بيئةٍ آمنةٍ ومهيّأة.' ),
		array( 'هل هناك متابعة لأولياء الأمور؟', 'نعم، نُرسل تقارير دورية عن مستوى الطالب الدراسي والسلوكي، ولدينا تواصل مستمر مع أولياء الأمور.' ),
		array( 'هل تُعنى المدرسة بالتربية الإسلامية؟', 'إلى جانب المنهج الأكاديمي، نُولي القرآن الكريم والتربية الإسلامية والقيم عنايةً خاصة عبر الحصص والبرامج التربوية.' ),
	);
	?>
	<section class="fk-section">
		<div class="fk-faq">
			<?php foreach ( $faqs as $f ) : ?>
				<div class="fk-faq-item fk-reveal">
					<button class="fk-faq-q" type="button">
						<span><?php echo esc_html( $f[0] ); ?></span>
						<span class="chev"><?php falak_icon( 'chevron', 18 ); ?></span>
					</button>
					<div class="fk-faq-a"><div class="fk-faq-a-inner"><?php echo esc_html( $f[1] ); ?></div></div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	falak_cta_band();
}

/* ─────────────────────────────────────────
   تواصل معنا
───────────────────────────────────────────*/
function falak_tpl_contact() {
	falak_page_head( 'تواصل معنا', 'يسعدنا تواصلك واستفساراتك في أي وقت' );

	$phone   = falak_opt( 'phone' );
	$email   = falak_opt( 'email' );
	$address = falak_opt( 'address' );
	$hours   = falak_opt( 'hours' );
	$map     = falak_opt( 'map_embed' );
	?>
	<section class="fk-section">
		<div class="fk-contact-grid">
			<a href="<?php echo esc_url( falak_wa_url( 'السلام عليكم، لدي استفسار' ) ); ?>" target="_blank" rel="noopener" class="fk-contact-card fk-reveal">
				<div class="fk-contact-ic"><?php falak_icon( 'whatsapp', 28 ); ?></div>
				<h3>واتساب</h3>
				<p><?php echo esc_html( falak_opt( 'whatsapp' ) ); ?></p>
			</a>
			<a href="<?php echo esc_url( falak_tel_url() ); ?>" class="fk-contact-card fk-reveal">
				<div class="fk-contact-ic"><?php falak_icon( 'phone', 28 ); ?></div>
				<h3>اتصال هاتفي</h3>
				<p><?php echo esc_html( $phone ); ?></p>
			</a>
			<a href="mailto:<?php echo esc_attr( $email ); ?>" class="fk-contact-card fk-reveal">
				<div class="fk-contact-ic"><?php falak_icon( 'mail', 28 ); ?></div>
				<h3>البريد الإلكتروني</h3>
				<p><?php echo esc_html( $email ); ?></p>
			</a>
		</div>

		<div class="fk-contact-grid" style="margin-bottom:44px">
			<div class="fk-contact-card fk-reveal" style="cursor:default">
				<div class="fk-contact-ic"><?php falak_icon( 'pin', 28 ); ?></div>
				<h3>العنوان</h3>
				<p style="direction:rtl"><?php echo esc_html( $address ); ?></p>
			</div>
			<div class="fk-contact-card fk-reveal" style="cursor:default">
				<div class="fk-contact-ic"><?php falak_icon( 'clock', 28 ); ?></div>
				<h3>أوقات الدوام</h3>
				<p style="direction:rtl"><?php echo esc_html( $hours ); ?></p>
			</div>
			<a href="<?php echo esc_url( home_url( '/enroll/' ) ); ?>" class="fk-contact-card fk-reveal">
				<div class="fk-contact-ic"><?php falak_icon( 'sparkle', 28 ); ?></div>
				<h3>التسجيل</h3>
				<p>سجّل ابنك أو ابنتك الآن</p>
			</a>
		</div>

		<div class="fk-map fk-reveal">
			<?php if ( $map ) : ?>
				<?php echo $map; // phpcs:ignore WordPress.Security.EscapeOutput — مُنقّى في الإعدادات ?>
			<?php else : ?>
				<div class="ph"><?php falak_icon( 'pin', 48 ); ?><span>تُضاف خريطة الموقع من إعدادات الموقع</span></div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/* ─────────────────────────────────────────
   التسجيل
───────────────────────────────────────────*/
function falak_tpl_enroll() {
	falak_page_head( 'التسجيل', 'عبّئ النموذج وسيتواصل معك فريقنا لاستكمال التسجيل' );

	$programs = falak_get_programs();
	$grades   = falak_grades();
	?>
	<section class="fk-enroll">
		<div class="fk-enroll-grid">
			<aside class="fk-enroll-aside fk-reveal">
				<h3>خطوات التسجيل</h3>
				<p>التسجيل في مدرسة الفلك المنير سهلٌ وسريع — أكمل الخطوات التالية:</p>
				<ol class="fk-enroll-steps">
					<li><span class="num">1</span><span><b>عبّئ النموذج</b><small>بيانات الطالب وولي الأمر</small></span></li>
					<li><span class="num">2</span><span><b>تواصل الفريق</b><small>نتّصل بك لتأكيد البيانات</small></span></li>
					<li><span class="num">3</span><span><b>تحديد المرحلة</b><small>نحدّد المرحلة والصف المناسب</small></span></li>
					<li><span class="num">4</span><span><b>ابدأ الرحلة</b><small>ينضم الطالب إلى حلقته</small></span></li>
				</ol>
			</aside>

			<form id="falak-enroll-form" class="fk-form fk-reveal" novalidate>
				<div class="fk-form-msg" role="alert"></div>

				<div class="fk-form-row">
					<div class="fk-field">
						<label>اسم الطالب/ة <span class="req">*</span></label>
						<input type="text" name="student_name" required autocomplete="name">
					</div>
					<div class="fk-field">
						<label>العمر</label>
						<input type="number" name="student_age" min="3" max="60" inputmode="numeric">
					</div>
				</div>

				<div class="fk-form-row">
					<div class="fk-field">
						<label>القسم <span class="req">*</span></label>
						<select name="section" required>
							<option value="">اختر القسم</option>
							<option value="بنين">قسم البنين (حضانة – سادس ابتدائي)</option>
							<option value="بنات">قسم البنات (حضانة – ثالث ثانوي)</option>
						</select>
					</div>
					<div class="fk-field">
						<label>المرحلة الدراسية</label>
						<select name="grade">
							<option value="">اختر المرحلة</option>
							<?php foreach ( $grades as $g ) : ?>
								<option value="<?php echo esc_attr( $g ); ?>"><?php echo esc_html( $g ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="fk-form-row">
					<div class="fk-field">
						<label>البرنامج المطلوب</label>
						<select name="program">
							<option value="">اختر البرنامج</option>
							<?php foreach ( $programs as $p ) : ?>
								<option value="<?php echo esc_attr( $p['title'] ); ?>"><?php echo esc_html( $p['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="fk-field">
						<label>المدرسة السابقة</label>
						<input type="text" name="current_level" placeholder="اسم المدرسة السابقة (إن وُجدت)">
					</div>
				</div>

				<div class="fk-form-row">
					<div class="fk-field">
						<label>اسم ولي الأمر</label>
						<input type="text" name="guardian_name" autocomplete="name">
					</div>
					<div class="fk-field">
						<label>رقم الجوال <span class="req">*</span></label>
						<input type="tel" name="guardian_phone" required inputmode="tel" dir="ltr" placeholder="05xxxxxxxx" autocomplete="tel">
					</div>
				</div>

				<div class="fk-form-row">
					<div class="fk-field">
						<label>البريد الإلكتروني</label>
						<input type="email" name="guardian_email" dir="ltr" autocomplete="email">
					</div>
					<div class="fk-field">
						<label>الوقت المفضّل</label>
						<select name="preferred_time">
							<option value="">غير محدد</option>
							<option value="صباحي">صباحي</option>
							<option value="مسائي">مسائي</option>
							<option value="نهاية الأسبوع">نهاية الأسبوع</option>
						</select>
					</div>
				</div>

				<div class="fk-field">
					<label>ملاحظات إضافية</label>
					<textarea name="notes" placeholder="أي معلومة تودّ إضافتها"></textarea>
				</div>

				<!-- honeypot -->
				<input type="text" name="fk_website" class="fk-hp" tabindex="-1" autocomplete="off" aria-hidden="true">

				<button type="submit" class="fk-btn fk-btn-p fk-form-submit"><?php falak_icon( 'sparkle', 18 ); ?> إرسال طلب التسجيل</button>

				<div class="fk-form-alt">
					أو سجّل مباشرةً عبر
					<a href="<?php echo esc_url( falak_wa_url( 'السلام عليكم، أرغب في تسجيل طالب بمدرسة الفلك المنير' ) ); ?>" target="_blank" rel="noopener"><?php falak_icon( 'whatsapp', 17 ); ?> واتساب</a>
				</div>
			</form>
		</div>
	</section>
	<?php
}
