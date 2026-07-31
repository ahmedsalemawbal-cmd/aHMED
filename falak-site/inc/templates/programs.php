<?php
/**
 * قالب صفحة «البرامج».
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function falak_tpl_programs() {
	$sections = falak_sections();
	falak_page_head( 'المراحل والبرامج', 'مراحل دراسية وبرامج تعليمية متكاملة تناسب جميع الأعمار' );
	?>
	<section class="fk-programs" style="background:#fff">
		<?php falak_programs_cards( 0 ); ?>
	</section>

	<!-- الأقسام والمراحل -->
	<section class="fk-sections" style="background:var(--c-soft)">
		<div class="fk-sec-head fk-reveal">
			<span class="fk-eyebrow center">الأقسام والمراحل</span>
			<h2 class="fk-h2">لكل قسمٍ <span>مراحله</span></h2>
		</div>
		<div class="fk-sec-grid">
			<div class="fk-sec-card boys fk-reveal">
				<span class="ic"><?php falak_icon( 'boy', 30 ); ?></span>
				<h3><?php echo esc_html( $sections['boys']['label'] ); ?></h3>
				<p><?php echo esc_html( $sections['boys']['stages'] ); ?></p>
				<span class="badge">مقاعد متاحة</span>
			</div>
			<div class="fk-sec-card girls fk-reveal">
				<span class="ic"><?php falak_icon( 'girl', 30 ); ?></span>
				<h3><?php echo esc_html( $sections['girls']['label'] ); ?></h3>
				<p><?php echo esc_html( $sections['girls']['stages'] ); ?></p>
				<span class="badge">مقاعد متاحة</span>
			</div>
		</div>
	</section>

	<?php
	falak_cta_band( 'اختر المرحلة المناسبة <span>لابنك أو ابنتك</span>', 'فريقنا مستعد لمساعدتك في اختيار المرحلة والبرنامج الأنسب. سجّل الآن أو تواصل معنا.' );
}
