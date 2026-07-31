<?php
/**
 * قالب الصفحة الرئيسية.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function falak_tpl_home() {
	$home     = home_url( '/' );
	$name     = falak_opt( 'school_name' );
	$ayah     = falak_opt( 'ayah' );
	$sections = falak_sections();
	?>

	<!-- الهيرو -->
	<section class="fk-hero">
		<div class="fk-hero-inner">
			<?php if ( $ayah ) : ?>
				<span class="fk-hero-ayah fk-reveal">﴿ <?php echo esc_html( $ayah ); ?> ﴾</span>
			<?php endif; ?>
			<h1 class="fk-reveal">نبني جيلًا <span>متعلّمًا</span><br>واثقًا بنفسه، معتزًّا بقيمه</h1>
			<p class="fk-hero-sub fk-reveal">في «مدرسة الفلك المنير» نُقدّم تعليمًا نوعيًّا وفق مناهج وزارة التعليم، بكادرٍ مؤهّل وبيئةٍ تربويةٍ آمنة ومرافقَ حديثة — لأبنائنا وبناتنا من رياض الأطفال حتى المرحلة الثانوية.</p>
			<div class="fk-hero-btns fk-reveal">
				<a href="<?php echo esc_url( $home . 'enroll/' ); ?>" class="fk-btn fk-btn-g"><?php falak_icon( 'sparkle', 18 ); ?> سجّل الآن</a>
				<a href="<?php echo esc_url( $home . 'programs/' ); ?>" class="fk-btn fk-btn-ghost">تعرّف على برامجنا <?php falak_icon( 'arrow-l', 18 ); ?></a>
			</div>
			<div class="fk-hero-stats fk-reveal">
				<div class="fk-stat"><div class="n"><?php echo esc_html( falak_opt( 'stat_students' ) ); ?></div><div class="l">طالب وطالبة</div></div>
				<div class="fk-stat"><div class="n"><?php echo esc_html( falak_opt( 'stat_grads' ) ); ?></div><div class="l">خريج ومتخرّجة</div></div>
				<div class="fk-stat"><div class="n"><?php echo esc_html( falak_opt( 'stat_teachers' ) ); ?></div><div class="l">معلم ومعلمة</div></div>
				<div class="fk-stat nostar"><div class="n"><?php echo esc_html( falak_opt( 'stat_years' ) ); ?></div><div class="l">سنة من العطاء</div></div>
			</div>
		</div>
	</section>

	<!-- من نحن -->
	<section class="fk-about">
		<div class="fk-about-grid">
			<div class="fk-about-media fk-reveal">
				<div class="fk-about-frame"></div>
				<img class="main" src="<?php echo esc_url( falak_pattern_placeholder( 'مدرسة الفلك المنير', 'book' ) ); ?>" alt="مدرسة الفلك المنير">
				<div class="fk-about-badge"><b><?php echo esc_html( falak_opt( 'stat_years' ) ); ?>+</b><small>سنة من التميّز</small></div>
			</div>
			<div class="fk-about-text fk-reveal">
				<span class="fk-eyebrow">من نحن</span>
				<h2 class="fk-h2">مدرسةٌ تبني <span>الإنسان</span> والمعرفة</h2>
				<p class="fk-lead">«مدرسة الفلك المنير» صرحٌ تعليميٌّ تربويٌّ يُقدّم تعليمًا نوعيًّا وفق مناهج وزارة التعليم، ويجمع بين التميّز الأكاديمي وحُسن التربية وبناء الشخصية المتوازنة، في بيئةٍ آمنةٍ ومحفّزة لأبنائنا وبناتنا.</p>
				<div class="fk-about-pts">
					<?php
					$pts = array(
						'مناهج وزارة التعليم المعتمدة مع إثراءٍ وأنشطةٍ حديثة',
						'كادرٌ تعليميٌّ مؤهّل ومتابعةٌ فرديةٌ دقيقة لكل طالب',
						'قسمان مستقلّان للبنين والبنات ببيئةٍ تربويةٍ آمنة',
						'مرافق حديثة: فصولٌ ذكية، ومختبرات، وملاعبُ وأنشطة',
					);
					foreach ( $pts as $pt ) : ?>
						<div class="fk-about-pt"><span class="ic"><?php falak_icon( 'check', 16 ); ?></span><span><?php echo esc_html( $pt ); ?></span></div>
					<?php endforeach; ?>
				</div>
				<a href="<?php echo esc_url( $home . 'about/' ); ?>" class="fk-btn fk-btn-p">تعرّف علينا أكثر <?php falak_icon( 'arrow-l', 18 ); ?></a>
			</div>
		</div>
	</section>

	<!-- البرامج -->
	<section class="fk-programs">
		<div class="fk-sec-head fk-reveal">
			<span class="fk-eyebrow center">مراحلنا وبرامجنا</span>
			<h2 class="fk-h2">مراحل دراسية <span>متكاملة</span></h2>
			<p class="fk-lead">من رياض الأطفال حتى المرحلة الثانوية — نرافق الطالب في كل مرحلة من رحلته التعليمية.</p>
		</div>
		<?php falak_programs_cards( 6 ); ?>
		<div style="text-align:center;margin-top:44px" class="fk-reveal">
			<a href="<?php echo esc_url( $home . 'programs/' ); ?>" class="fk-btn fk-btn-o">كل البرامج <?php falak_icon( 'arrow-l', 18 ); ?></a>
		</div>
	</section>

	<!-- الأقسام (بنين/بنات) -->
	<section class="fk-sections">
		<div class="fk-sec-head fk-reveal">
			<span class="fk-eyebrow center">أقسام المدرسة</span>
			<h2 class="fk-h2">قسمان مستقلّان <span>للبنين والبنات</span></h2>
			<p class="fk-lead">بيئة تربوية مناسبة لكل قسم، بكادرٍ متخصّص ومراحل دراسية واضحة.</p>
		</div>
		<div class="fk-sec-grid">
			<div class="fk-sec-card boys fk-reveal">
				<span class="ic"><?php falak_icon( 'boy', 30 ); ?></span>
				<h3><?php echo esc_html( $sections['boys']['label'] ); ?></h3>
				<p><?php echo esc_html( $sections['boys']['stages'] ); ?></p>
				<span class="badge">مقاعد متاحة للتسجيل</span>
			</div>
			<div class="fk-sec-card girls fk-reveal">
				<span class="ic"><?php falak_icon( 'girl', 30 ); ?></span>
				<h3><?php echo esc_html( $sections['girls']['label'] ); ?></h3>
				<p><?php echo esc_html( $sections['girls']['stages'] ); ?></p>
				<span class="badge">مقاعد متاحة للتسجيل</span>
			</div>
		</div>
	</section>

	<!-- لماذا نحن -->
	<section class="fk-why">
		<div class="fk-why-inner">
			<div class="fk-sec-head fk-reveal">
				<span class="fk-eyebrow center">لماذا الفلك المنير</span>
				<h2 class="fk-h2">أكثر من مجرّد <span>دراسة</span></h2>
			</div>
			<div class="fk-why-grid">
				<?php
				$whys = array(
					array( 'award', 'كادرٌ تعليميٌّ مؤهّل', 'معلمون ومعلمات أكْفاء في مختلف التخصصات، يجمعون بين العلم والخبرة التربوية.' ),
					array( 'target', 'مناهج وتقنية حديثة', 'مناهج وزارة التعليم مع وسائلَ وتقنياتٍ تعليميةٍ حديثة تُثري تجربة التعلّم.' ),
					array( 'shield', 'بيئة آمنة ومحفّزة', 'أجواء تربوية آمنة ومرافق مجهّزة تُراعي كل فئة عمرية بقسمها المستقل.' ),
					array( 'heart', 'متابعة ورعاية', 'تواصلٌ مستمر مع أولياء الأمور وتقاريرُ دوريةٌ عن مستوى الطالب.' ),
				);
				foreach ( $whys as $w ) : ?>
					<div class="fk-why-item fk-reveal">
						<div class="fk-why-ic"><?php falak_icon( $w[0], 28 ); ?></div>
						<h3><?php echo esc_html( $w[1] ); ?></h3>
						<p><?php echo esc_html( $w[2] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- الإحصائيات -->
	<?php falak_stats_band(); ?>

	<!-- شهادات أولياء الأمور (سلايدر) -->
	<section class="fk-testi">
		<div class="fk-sec-head fk-reveal">
			<span class="fk-eyebrow center">قالوا عنا</span>
			<h2 class="fk-h2">ثقةُ <span>أولياء الأمور</span></h2>
			<p class="fk-lead">مرّر يمينًا ويسارًا لاستعراض آراء أولياء أمور طلابنا.</p>
		</div>
		<?php falak_render_testimonials(); ?>
		<div style="text-align:center;margin-top:34px" class="fk-reveal">
			<a href="<?php echo esc_url( $home . 'review/' ); ?>" class="fk-btn fk-btn-o"><?php falak_icon( 'star', 18 ); ?> أضف تقييمك</a>
		</div>
	</section>

	<!-- دعوة للتسجيل -->
	<?php falak_cta_band(); ?>

	<?php
}

/**
 * صورة نائبة (placeholder) SVG بلون أخضر مع أيقونة — لحين رفع صور حقيقية.
 */
function falak_pattern_placeholder( $label = '', $icon = 'quran' ) {
	$icon_svg = falak_icon_path( $icon );
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">'
		. '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
		. '<stop offset="0" stop-color="#1F9D5A"/><stop offset="1" stop-color="#0E4D33"/></linearGradient></defs>'
		. '<rect width="800" height="600" fill="url(#g)"/>'
		. '<g fill="none" stroke="#ffffff" stroke-opacity="0.12" stroke-width="2">'
		. '<path d="M400 100l120 120-120 120-120-120z"/><path d="M400 260l120 120-120 120-120-120z"/>'
		. '<circle cx="400" cy="300" r="200"/></g>'
		. '<g transform="translate(360,255) scale(3.5)" fill="none" stroke="#C6A15B" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $icon_svg . '</g>'
		. '</svg>';
	return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
}
