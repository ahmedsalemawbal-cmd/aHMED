<?php
/**
 * About page — /about-us/ (full-bleed body-insert page).
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_footer', 'osoul_about_output', 5 );
if ( ! function_exists( 'osoul_about_output' ) ) {
	function osoul_about_output() {
		if ( ! is_page( 'about-us' ) ) {
			return;
		}
		$home = home_url( '/' );
		$base = 'https://osoulalbinaa.com/wp-content/uploads/2026/06/';
		?>
<style id="osoul-about-css">
.oab{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#f5f2ee;overflow-x:hidden;display:block!important}
.oab-hero{background:#0d1f3c;padding:80px 60px 64px;position:relative;overflow:hidden}
.oab-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:#D21217}
.oab-hero::after{content:'';position:absolute;inset:0;background:url('<?php echo esc_url( $base ); ?>DSC_2478-scaled.jpg') center/cover;opacity:.1;pointer-events:none}
.oab-hero-inner{position:relative;z-index:1;max-width:860px}
.oab-tag{font-size:11px;letter-spacing:5px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px}
.oab-tag::before{content:'';width:28px;height:1px;background:#D21217}
.oab-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(30px,4.5vw,52px);font-weight:700;color:#fff;line-height:1.15;margin-bottom:14px}
.oab-h1 em{font-style:italic;color:#D21217}
.oab-sub{font-size:15px;color:rgba(255,255,255,.55);max-width:560px;line-height:1.65;font-weight:300;margin-bottom:28px}
.oab-hero-meta{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:rgba(255,255,255,.07);border-radius:4px;overflow:hidden;max-width:760px}
.oab-meta-i{background:rgba(255,255,255,.04);padding:10px 14px}
.oab-meta-l{font-size:8px;color:#D21217;letter-spacing:2px;text-transform:uppercase;margin-bottom:3px}
.oab-meta-v{font-size:12px;color:#fff;font-weight:600}
.oab-hero-btns{display:flex;gap:12px;margin-bottom:32px;flex-wrap:wrap}
.oab-btn-p{display:inline-flex;align-items:center;gap:7px;background:#D21217;color:#fff;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:11px 24px;border-radius:2px;text-decoration:none;min-height:44px}
.oab-btn-s{display:inline-flex;align-items:center;gap:7px;background:transparent;color:rgba(255,255,255,.75);font-size:11px;font-weight:600;padding:11px 18px;border-radius:2px;border:1.5px solid rgba(255,255,255,.2);text-decoration:none;min-height:44px}
.oab-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background-color:rgba(255,255,255,.2)}
.oab-stat{background:#D21217;padding:20px 24px;text-align:center}
.oab-stat-n{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:36px;font-weight:700;color:#fff;line-height:1}
.oab-stat-l{font-size:10px;color:rgba(255,255,255,.7);margin-top:5px;letter-spacing:1.5px;text-transform:uppercase}
.oab-whowe{background:#fff;padding:60px}
.oab-whowe-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:48px;align-items:start;max-width:1200px;margin:0 auto}
.oab-sec-tag{font-size:9px;letter-spacing:4px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.oab-sec-tag::before{content:'';width:20px;height:1px;background:#D21217}
.oab-sec-h{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(22px,2.5vw,28px);font-weight:700;color:#0d1f3c;line-height:1.3;margin-bottom:16px}
.oab-sec-h em{color:#D21217;font-style:italic}
.oab-body-txt{font-size:13px;color:rgba(13,31,60,.65);line-height:1.8;font-weight:300;margin-bottom:14px}
.oab-body-txt strong{color:#0d1f3c;font-weight:600}
.oab-lines-label{font-size:8px;letter-spacing:2px;color:#D21217;font-weight:700;text-transform:uppercase;margin-bottom:10px;padding-top:16px;border-top:1px solid rgba(13,31,60,.08)}
.oab-lines-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}
.oab-line-item{font-size:11.5px;color:rgba(13,31,60,.7);padding:6px 0;border-bottom:.5px solid rgba(13,31,60,.06);font-weight:300}
.oab-vm-cards{display:flex;flex-direction:column;gap:14px}
.oab-v-card{background:#f5f2ee;border-radius:6px;padding:24px 22px;border-top:3px solid #D21217}
.oab-m-card{background:#f5f2ee;border-radius:6px;padding:24px 22px;border-top:3px solid #0d1f3c}
.oab-card-label{font-size:8px;letter-spacing:3px;text-transform:uppercase;font-weight:700;margin-bottom:8px}
.oab-card-h{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:16px;font-weight:700;color:#0d1f3c;margin-bottom:8px}
.oab-card-txt{font-size:11.5px;color:rgba(13,31,60,.6);line-height:1.65;font-weight:300}
.oab-2030{background:#0d1f3c;border-radius:6px;padding:16px 22px;display:flex;align-items:center;gap:14px}
.oab-2030-txt strong{font-size:12px;font-weight:700;color:#fff;display:block}
.oab-2030-txt span{font-size:10px;color:rgba(255,255,255,.45);margin-top:2px;display:block}
.oab-ceo{background:#0d1f3c;position:relative;overflow:hidden}
.oab-ceo::before{content:'';position:absolute;inset:0;background:url('<?php echo esc_url( $base ); ?>DSC_2461-scaled.jpg') center/cover;opacity:.08;pointer-events:none}
.oab-ceo::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:#D21217;pointer-events:none}
.oab-ceo-grid{position:relative;z-index:1;display:grid;grid-template-columns:1.15fr 1fr;max-width:100%}
.oab-ceo-txt{padding:56px 52px;border-left:1px solid rgba(255,255,255,.07);display:flex;flex-direction:column;justify-content:center}
.oab-ceo-tag{font-size:8px;letter-spacing:4px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:18px}
.oab-ceo-q-open{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:90px;color:rgba(210,18,23,.2);line-height:.7;font-style:italic}
.oab-ceo-q{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:18.5px;font-weight:700;color:#fff;line-height:1.55;font-style:italic;margin:6px 0 5px}
.oab-ceo-q-close{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:90px;color:rgba(210,18,23,.2);line-height:.5;text-align:left;font-style:italic;margin-bottom:24px}
.oab-ceo-body{font-size:12.5px;color:rgba(255,255,255,.55);line-height:1.8;font-weight:300;max-width:420px;margin-bottom:26px}
.oab-ceo-sig{border-top:1px solid rgba(255,255,255,.09);padding-top:20px}
.oab-ceo-name{font-size:18px;font-weight:700;color:#fff;margin-bottom:5px}
.oab-ceo-title{font-size:8px;color:#D21217;letter-spacing:2px;font-weight:700;margin-bottom:4px}
.oab-ceo-photo{background:#061524;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:460px;position:relative;overflow:hidden}
.oab-ceo-circle-wrap{position:relative;z-index:1;text-align:center}
.oab-ceo-circle-outer{width:180px;height:180px;border-radius:50%;border:1px solid rgba(210,18,23,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 20px}
.oab-ceo-circle-mid{width:150px;height:150px;border-radius:50%;border:2px solid rgba(210,18,23,.4);display:flex;align-items:center;justify-content:center;background:rgba(210,18,23,.08)}
.oab-ceo-circle-in{width:120px;height:120px;border-radius:50%;background:url('<?php echo esc_url( $base ); ?>تصميم-بدون-عنوان-19.png') center top/cover;border:2px solid rgba(210,18,23,.5);overflow:hidden}
.oab-ceo-cname{font-size:16px;font-weight:700;color:#fff;margin-bottom:5px}
.oab-ceo-ctitle{font-size:8px;color:#D21217;letter-spacing:2px;font-weight:700;margin-bottom:3px;text-transform:uppercase}
.oab-ceo-divider{width:40px;height:1px;background:rgba(210,18,23,.4);margin:14px auto 0}
.oab-ceo-corner-tl{position:absolute;top:18px;left:18px;width:44px;height:44px;border-top:2px solid rgba(210,18,23,.5);border-left:2px solid rgba(210,18,23,.5)}
.oab-ceo-corner-br{position:absolute;bottom:18px;right:18px;width:44px;height:44px;border-bottom:2px solid rgba(210,18,23,.45);border-right:2px solid rgba(210,18,23,.45)}
.oab-ceo-red-line{position:absolute;top:0;left:0;bottom:0;width:3px;background:linear-gradient(to bottom,transparent,rgba(210,18,23,.65) 50%,transparent)}
.oab-how{background:#f5f2ee;padding:60px}
.oab-how-inner{max-width:1200px;margin:0 auto}
.oab-how-hd{text-align:center;margin-bottom:36px}
.oab-how-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:3px}
.oab-step{background:#fff;padding:24px 18px;border-radius:2px;position:relative}
.oab-step::before{content:'';position:absolute;top:0;right:0;left:0;height:2px;background:#D21217}
.oab-step:last-child::before{background:#0d1f3c}
.oab-step-n{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:34px;color:rgba(210,18,23,.12);font-weight:700;margin-bottom:10px}
.oab-step:last-child .oab-step-n{color:rgba(13,31,60,.1)}
.oab-step-icon{width:36px;height:36px;border:1px solid rgba(210,18,23,.3);border-radius:7px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;font-size:16px;color:#D21217}
.oab-step:last-child .oab-step-icon{color:#0d1f3c;border-color:rgba(13,31,60,.2)}
.oab-step-h{font-size:13px;font-weight:700;color:#0d1f3c;margin-bottom:6px}
.oab-step-d{font-size:11px;color:rgba(13,31,60,.5);line-height:1.6;font-weight:300}
.oab-gallery{background:#fff;padding:60px}
.oab-gallery-inner{max-width:1200px;margin:0 auto}
.oab-gallery-hd{text-align:center;margin-bottom:32px}
.oab-gallery-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.oab-gallery-item{position:relative;border-radius:5px;overflow:hidden;background:#0d1f3c}
.oab-gallery-img{width:100%;height:180px;object-fit:cover;display:block;opacity:.82;transition:opacity .3s,transform .4s}
.oab-gallery-item:hover .oab-gallery-img{opacity:1;transform:scale(1.04)}
.oab-gallery-badge{position:absolute;bottom:8px;right:8px;background:#D21217;color:#fff;font-size:8px;font-weight:700;letter-spacing:1px;padding:3px 9px;border-radius:1px;text-transform:uppercase}
.oab-gallery-caption{font-size:12px;font-weight:700;color:#0d1f3c;margin-top:7px}
.oab-certs{background:#f5f2ee;padding:56px 60px}
.oab-certs-inner{max-width:1200px;margin:0 auto}
.oab-certs-hd{text-align:center;margin-bottom:32px}
.oab-certs-row{display:flex;justify-content:center;gap:20px;flex-wrap:wrap;margin-bottom:24px}
.oab-cert-card{width:145px;text-align:center}
.oab-cert-frame{height:190px;background:#fff;border-radius:6px;overflow:hidden;border:.5px solid rgba(13,31,60,.08);display:flex;align-items:center;justify-content:center;margin-bottom:10px;padding:8px}
.oab-cert-frame img{width:100%;height:100%;object-fit:contain}
.oab-cert-name{font-size:12px;font-weight:700;color:#0d1f3c;margin-bottom:2px}
.oab-cert-sub{font-size:8px;color:rgba(13,31,60,.45);letter-spacing:1px;text-transform:uppercase}
.oab-tags-row{display:flex;justify-content:center;flex-wrap:wrap;gap:6px}
.oab-badge{background:#fff;color:#0d1f3c;font-size:9px;font-weight:600;padding:4px 12px;border-radius:20px;border:.5px solid rgba(13,31,60,.12)}
.oab-cta{background:#D21217;padding:52px 60px;text-align:center;position:relative;overflow:hidden}
.oab-cta::before{content:'';position:absolute;inset:0;background:url('<?php echo esc_url( $base ); ?>DSC_2466-scaled.jpg') center/cover;opacity:.1;pointer-events:none}
.oab-cta-inner{position:relative;z-index:1}
.oab-cta-sup{font-size:8px;letter-spacing:4px;color:rgba(255,255,255,.6);text-transform:uppercase;margin-bottom:10px}
.oab-cta-h{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(22px,3vw,30px);font-weight:700;color:#fff;margin-bottom:8px;line-height:1.2}
.oab-cta-h em{font-style:italic}
.oab-cta-sub{font-size:13px;color:rgba(255,255,255,.65);margin-bottom:26px;font-weight:300}
.oab-cta-btns{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
.oab-cta-btn-p{display:inline-flex;align-items:center;gap:7px;background:#fff;color:#D21217;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:12px 26px;border-radius:2px;text-decoration:none;min-height:44px}
.oab-cta-btn-s{display:inline-flex;align-items:center;gap:7px;border:1.5px solid rgba(255,255,255,.4);color:#fff;font-size:11px;font-weight:600;padding:12px 20px;border-radius:2px;text-decoration:none;min-height:44px}
@media(max-width:1024px){.oab-whowe-grid{grid-template-columns:1fr}.oab-ceo-grid{grid-template-columns:1fr}.oab-ceo-photo{min-height:320px}.oab-how-grid{grid-template-columns:1fr 1fr}.oab-gallery-grid{grid-template-columns:1fr 1fr}.oab-stats{grid-template-columns:repeat(2,1fr)}.oab-whowe,.oab-how,.oab-gallery,.oab-certs,.oab-cta{padding:44px 28px}.oab-ceo-txt{padding:44px 28px}.oab-hero{padding:56px 28px 44px}.oab-hero-meta{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.oab-how-grid{grid-template-columns:1fr}.oab-gallery-grid{grid-template-columns:1fr}.oab-stats{grid-template-columns:1fr 1fr}.oab-certs-row{gap:12px}.oab-cert-card{width:120px}.oab-cert-frame{height:155px}}
</style>
<div class="oab">
	<!-- Hero -->
	<div class="oab-hero"><div class="oab-hero-inner">
		<div class="oab-tag"><?php osoul_bilingual( 'من نحن', 'About Us' ); ?></div>
		<h1 class="oab-h1"><span data-lang="ar">شركة وطنية رائدة<br><em>في قلب الصناعة السعودية</em></span><span data-lang="en">A Leading National Company<br><em>At the Heart of Saudi Industry</em></span></h1>
		<p class="oab-sub"><?php osoul_bilingual( 'المدينة الصناعية الثالثة — جدة · معادن · أبواب · جبس بورد · هياكل معدنية', 'Third Industrial City — Jeddah · Metals · Doors · Gypsum Board · Steel Structures' ); ?></p>
		<div class="oab-hero-btns">
			<a href="<?php echo esc_url( $home . 'contact/' ); ?>" class="oab-btn-p"><?php osoul_bilingual( 'تواصل معنا', 'Contact Us' ); ?></a>
			<a href="<?php echo esc_url( $home . 'products/' ); ?>" class="oab-btn-s"><?php osoul_bilingual( 'استعرض منتجاتنا', 'Our Products' ); ?></a>
		</div>
		<div class="oab-hero-meta">
			<div class="oab-meta-i"><div class="oab-meta-l"><?php osoul_bilingual( 'الموقع', 'Location' ); ?></div><div class="oab-meta-v"><?php osoul_bilingual( 'المدينة الصناعية — جدة', 'Industrial City — Jeddah' ); ?></div></div>
			<div class="oab-meta-i"><div class="oab-meta-l"><?php osoul_bilingual( 'التقنية', 'Technology' ); ?></div><div class="oab-meta-v">Roll Forming & CNC</div></div>
			<div class="oab-meta-i"><div class="oab-meta-l"><?php osoul_bilingual( 'الجودة', 'Quality' ); ?></div><div class="oab-meta-v"><?php osoul_bilingual( 'ISO معتمد دولياً', 'ISO Certified' ); ?></div></div>
			<div class="oab-meta-i"><div class="oab-meta-l"><?php osoul_bilingual( 'الكادر', 'Team' ); ?></div><div class="oab-meta-v"><?php osoul_bilingual( '+50 مهندس', '+50 Engineers' ); ?></div></div>
		</div>
	</div></div>

	<!-- Stats -->
	<div class="oab-stats">
		<div class="oab-stat"><div class="oab-stat-n" data-counting="500" data-suffix="+">+500</div><div class="oab-stat-l"><?php osoul_bilingual( 'مشروع منجز', 'Projects Done' ); ?></div></div>
		<div class="oab-stat"><div class="oab-stat-n" data-counting="20" data-suffix="+">+20</div><div class="oab-stat-l"><?php osoul_bilingual( 'سنة خبرة', 'Years Exp.' ); ?></div></div>
		<div class="oab-stat"><div class="oab-stat-n" data-counting="95" data-suffix="%">95%</div><div class="oab-stat-l"><?php osoul_bilingual( 'رضا العملاء', 'Client Satisfaction' ); ?></div></div>
		<div class="oab-stat"><div class="oab-stat-n" data-counting="50" data-suffix="+">+50</div><div class="oab-stat-l"><?php osoul_bilingual( 'مهندس ومتخصص', 'Engineers' ); ?></div></div>
	</div>

	<!-- Who we are -->
	<div class="oab-whowe"><div class="oab-whowe-grid">
		<div>
			<div class="oab-sec-tag"><?php osoul_bilingual( 'من نحن', 'Who We Are' ); ?></div>
			<h2 class="oab-sec-h"><span data-lang="ar">شركة أصول البناء الصناعية<br><em>شريك التصنيع الوطني الموثوق</em></span><span data-lang="en">Osoul Albinaa Industrial<br><em>Your Trusted National Manufacturing Partner</em></span></h2>
			<p class="oab-body-txt"><span data-lang="ar">شركة أصول البناء الصناعية هي شركة وطنية رائدة، تتخذ من <strong>المدينة الصناعية الثالثة بجدة</strong> مقراً لها. منذ انطلاقتنا وضعنا الجودة والكفاءة الهندسية كركيزة أساسية لعملنا، مما أهّلنا لنكون الشريك الموثوق للكثير من الجهات في القطاعين الحكومي والخاص.</span><span data-lang="en">Osoul Albinaa Industrial Co. is a leading national company headquartered at the <strong>Third Industrial City in Jeddah</strong>. Since our founding, we have placed quality and engineering excellence as our core pillars, making us the trusted partner for many government and private sector entities.</span></p>
			<p class="oab-body-txt"><span data-lang="ar">نجمع في "أصول البناء" بين الخبرات البشرية المؤهلة وتقنيات صناعية متطورة؛ حيث يضم مصنعنا خطوط إنتاج متكاملة للأعمال المعدنية، إلى جانب قسم متخصص في تشكيل المعادن <strong>(Roll Forming)</strong>، مما يمنحنا القدرة على تنفيذ أعقد المشاريع بدقة متناهية.</span><span data-lang="en">At Osoul Albinaa, we combine qualified human expertise with advanced industrial technologies. Our factory features integrated metal production lines alongside a specialized <strong>Roll Forming</strong> division, enabling us to execute the most complex industrial and construction projects with unmatched precision.</span></p>
			<div class="oab-lines-label"><?php osoul_bilingual( 'خطوط الإنتاج المتخصصة', 'Specialized Production Lines' ); ?></div>
			<div class="oab-lines-grid">
				<?php
				$lines = array(
					array( '· الأبواب المعدنية المجوفة', '· Hollow Metal Doors' ),
					array( '· أنظمة الجبس والإسمنت', '· Gypsum & Cement Systems' ),
					array( '· ملحقات مجاري الهواء', '· Duct Accessories' ),
					array( '· الهياكل المعدنية (WPC)', '· Metal Structures (WPC)' ),
					array( '· الأبواب المقاومة للحريق', '· Fire-Resistant Doors' ),
					array( '· الألمنيوم والزجاج', '· Aluminium & Glass' ),
				);
				foreach ( $lines as $ln ) : ?>
				<div class="oab-line-item"><?php osoul_bilingual( $ln[0], $ln[1] ); ?></div>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="oab-vm-cards">
			<div class="oab-v-card">
				<div class="oab-card-label" style="color:#D21217"><?php osoul_bilingual( 'الرؤية', 'Vision' ); ?></div>
				<div class="oab-card-h"><?php osoul_bilingual( 'الشريك الوطني الأكثر موثوقية', 'The Most Trusted National Partner' ); ?></div>
				<p class="oab-card-txt"><?php osoul_bilingual( 'أن نكون الشريك الوطني الأكثر موثوقية والأقوى أثراً في المشاركة في صياغة مستقبل النهضة الصناعية والعمرانية في المملكة العربية السعودية، من خلال تقديم حلول هندسية وصناعية متكاملة فائقة الجودة.', "To be the most trusted and impactful national partner in shaping Saudi Arabia's industrial and urban renaissance, through delivering comprehensive, premium-quality engineering and industrial solutions." ); ?></p>
			</div>
			<div class="oab-m-card">
				<div class="oab-card-label" style="color:#0d1f3c"><?php osoul_bilingual( 'الرسالة', 'Mission' ); ?></div>
				<div class="oab-card-h"><?php osoul_bilingual( 'معايير هندسية لا تقبل المساومة', 'Engineering Standards That Speak for Themselves' ); ?></div>
				<p class="oab-card-txt"><?php osoul_bilingual( 'نلتزم في "أصول البناء" بتوفير منتجات حديدية ومعدنية متطورة بأعلى معايير الكفاءة الهندسية، ملتزمين بالدقة المتناهية ومستندين إلى بنية تحتية تقنية متطورة تلبي تطلعات شركائنا في القطاعين الحكومي والخاص.', 'At Osoul Albinaa, we are committed to providing advanced metal products at the highest engineering standards, with meticulous precision and cutting-edge infrastructure to meet the ambitions of our government and private sector partners.' ); ?></p>
			</div>
			<div class="oab-2030">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#D21217" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
				<div class="oab-2030-txt"><strong><?php osoul_bilingual( 'نساهم في رؤية 2030', 'Contributing to Vision 2030' ); ?></strong><span><?php osoul_bilingual( 'نهضة صناعية وطنية مستدامة', 'A Sustainable National Industrial Renaissance' ); ?></span></div>
			</div>
		</div>
	</div></div>

	<!-- CEO message -->
	<div class="oab-ceo"><div class="oab-ceo-grid">
		<div class="oab-ceo-txt">
			<div class="oab-ceo-tag"><?php osoul_bilingual( 'كلمة المدير التنفيذي', "CEO's Message" ); ?></div>
			<div class="oab-ceo-q-open">"</div>
			<blockquote class="oab-ceo-q"><?php osoul_bilingual( 'إن سياسة شركة أصول البناء ترتكز في مقامها الأول على تقديم منتجات وخدمات عالية الجودة، مطابقة لأعلى المواصفات والمعايير الفنية — بهدف نيل رضا عملائنا الكرام وتجاوز تطلعاتهم', "Osoul Albinaa's core policy is built on delivering high-quality products and services that strictly meet the highest technical specifications — with the goal of earning and exceeding our valued clients' satisfaction" ); ?></blockquote>
			<div class="oab-ceo-q-close">"</div>
			<p class="oab-ceo-body"><?php osoul_bilingual( 'تلتزم الشركة بتحقيق نمو مستمر ومطرد عبر تطبيق نظام صارم لإدارة الجودة يتوافق مع المعايير الدولية والمهنية، مستندةً إلى كوادر بشرية مؤهلة وأحدث التقنيات الصناعية.', 'The company is committed to achieving continuous growth through a rigorous quality management system aligned with international standards, relying on qualified talent and the latest industrial technologies.' ); ?></p>
			<div class="oab-ceo-sig">
				<div class="oab-ceo-name"><?php osoul_bilingual( 'م. عبد الرحمن غالب', 'Eng. Abdulrahman Galib' ); ?></div>
				<div class="oab-ceo-title"><?php osoul_bilingual( 'المدير التنفيذي — شركة أصول البناء للصناعة', 'CEO — Osoul Albinaa Industrial Co.' ); ?></div>
			</div>
		</div>
		<div class="oab-ceo-photo">
			<div class="oab-ceo-corner-tl"></div>
			<div class="oab-ceo-corner-br"></div>
			<div class="oab-ceo-red-line"></div>
			<div class="oab-ceo-circle-wrap">
				<div class="oab-ceo-circle-outer"><div class="oab-ceo-circle-mid"><div class="oab-ceo-circle-in"></div></div></div>
				<div class="oab-ceo-cname"><?php osoul_bilingual( 'م. عبد الرحمن غالب', 'Eng. Abdulrahman Galib' ); ?></div>
				<div class="oab-ceo-ctitle"><?php osoul_bilingual( 'المدير التنفيذي', 'CEO' ); ?></div>
				<div class="oab-ceo-divider"></div>
			</div>
		</div>
	</div></div>

	<!-- How we work -->
	<div class="oab-how"><div class="oab-how-inner">
		<div class="oab-how-hd">
			<div class="oab-sec-tag" style="justify-content:center"><?php osoul_bilingual( 'كيف نعمل', 'How We Work' ); ?></div>
			<h2 class="oab-sec-h" style="text-align:center"><span data-lang="ar">من طلبك إلى <em>تسليمك</em></span><span data-lang="en">From Your Request to <em>Delivery</em></span></h2>
		</div>
		<div class="oab-how-grid">
			<?php
			$steps = array(
				array( 'n' => '01', 'svg' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>', 'h' => 'تقديم الطلب', 'h_en' => 'Submit Request', 'd' => 'مقاساتك + متطلباتك — نرد بعرض خلال 24 ساعة', 'd_en' => 'Your specs + requirements — we respond within 24 hours' ),
				array( 'n' => '02', 'svg' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>', 'h' => 'التصنيع', 'h_en' => 'Manufacturing', 'd' => 'Roll Forming & CNC — دقة وتحكم كامل', 'd_en' => 'Roll Forming & CNC — full precision and control' ),
				array( 'n' => '03', 'svg' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'h' => 'فحص الجودة', 'h_en' => 'Quality Control', 'd' => 'كل منتج يخضع لاختبارات ISO قبل التسليم', 'd_en' => 'Every product undergoes ISO testing before delivery' ),
				array( 'n' => '04', 'svg' => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>', 'h' => 'التسليم + الضمان', 'h_en' => 'Delivery + Warranty', 'd' => 'في الموعد + وثائق رسمية + خدمة ما بعد البيع', 'd_en' => 'On-time + official docs + after-sales service', 'last' => true ),
			);
			foreach ( $steps as $st ) : ?>
			<div class="oab-step">
				<div class="oab-step-n"><?php echo esc_html( $st['n'] ); ?></div>
				<div class="oab-step-icon"<?php echo ! empty( $st['last'] ) ? ' style="color:#0d1f3c;border-color:rgba(13,31,60,.2)"' : ''; ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px" aria-hidden="true"><?php echo $st['svg']; // phpcs:ignore WordPress.Security.EscapeOutput — static SVG ?></svg></div>
				<div class="oab-step-h"><?php osoul_bilingual( $st['h'], $st['h_en'] ); ?></div>
				<div class="oab-step-d"><?php osoul_bilingual( $st['d'], $st['d_en'] ); ?></div>
			</div>
			<?php endforeach; ?>
		</div>
	</div></div>

	<!-- Factory gallery -->
	<div class="oab-gallery"><div class="oab-gallery-inner">
		<div class="oab-gallery-hd">
			<div class="oab-sec-tag" style="justify-content:center"><?php osoul_bilingual( 'معرض المصنع', 'Factory Gallery' ); ?></div>
			<h2 class="oab-sec-h" style="text-align:center"><span data-lang="ar">داخل <em>عالمنا</em> الصناعي</span><span data-lang="en">Inside <em>Our</em> Industrial World</span></h2>
		</div>
		<div class="oab-gallery-grid">
			<?php
			$gallery = array(
				array( 'DSC_2478-scaled.jpg', 'خطوط الإنتاج الرئيسية', 'Main Production Lines', 'الإنتاج', 'Production' ),
				array( 'DSC_2482-scaled.jpg', 'تقنية Roll Forming', 'Roll Forming Technology', 'Roll Forming', 'Roll Forming' ),
				array( 'DSC_2467-scaled.jpg', 'خط التجميع والتشطيب', 'Assembly & Finishing Line', 'التجميع', 'Assembly' ),
				array( 'DSC_2460-scaled.jpg', 'مستودع المواد الخام', 'Raw Materials Warehouse', 'المواد', 'Materials' ),
				array( 'DSC_2461-scaled.jpg', 'كادرنا الهندسي', 'Our Engineering Team', 'الكادر', 'Team' ),
				array( 'DSC_2483-scaled.jpg', 'منطقة الفرز والشحن', 'Sorting & Shipping Area', 'الشحن', 'Shipping' ),
			);
			foreach ( $gallery as $g ) : ?>
			<div class="oab-gallery-item">
				<img class="oab-gallery-img" src="<?php echo esc_url( $base . $g[0] ); ?>" alt="<?php echo esc_attr( $g[1] ); ?>" loading="lazy">
				<div class="oab-gallery-badge"><?php osoul_bilingual( $g[3], $g[4] ); ?></div>
				<div class="oab-gallery-caption"><?php osoul_bilingual( $g[1], $g[2] ); ?></div>
			</div>
			<?php endforeach; ?>
		</div>
	</div></div>

	<!-- Certifications -->
	<div class="oab-certs"><div class="oab-certs-inner">
		<div class="oab-certs-hd">
			<div class="oab-sec-tag" style="justify-content:center"><?php osoul_bilingual( 'الشهادات والاعتمادات', 'Certifications & Accreditations' ); ?></div>
			<h2 class="oab-sec-h" style="text-align:center"><span data-lang="ar">جودة <em>موثقة ومعتمدة</em></span><span data-lang="en">Quality <em>Documented & Certified</em></span></h2>
		</div>
		<div class="oab-certs-row">
			<?php
			$certs = array(
				array( '١٨.webp', 'شهادة الجودة', 'Quality Certificate', 'ISO CERTIFIED' ),
				array( '١٥.webp', 'شهادة الاعتماد', 'Accreditation Certificate', 'SGS ACCREDITED' ),
				array( '3.webp', 'شهادة السلامة', 'Safety Certificate', 'FIRE CERTIFIED' ),
			);
			foreach ( $certs as $c ) : ?>
			<div class="oab-cert-card">
				<div class="oab-cert-frame"><img src="<?php echo esc_url( $base . $c[0] ); ?>" alt="<?php echo esc_attr( $c[1] ); ?>" loading="lazy"></div>
				<div class="oab-cert-name"><?php osoul_bilingual( $c[1], $c[2] ); ?></div>
				<div class="oab-cert-sub"><?php echo esc_html( $c[3] ); ?></div>
			</div>
			<?php endforeach; ?>
		</div>
		<div class="oab-tags-row">
			<span class="oab-badge"><?php osoul_bilingual( 'ISO 9001 إدارة الجودة', 'ISO 9001 Quality Management' ); ?></span>
			<span class="oab-badge"><?php osoul_bilingual( 'معايير السلامة من الحريق', 'Fire Safety Standards' ); ?></span>
			<span class="oab-badge"><?php osoul_bilingual( 'SGS للجودة والاختبار', 'SGS Quality & Testing' ); ?></span>
			<span class="oab-badge"><?php osoul_bilingual( 'اعتماد IAF', 'IAF Accreditation' ); ?></span>
		</div>
	</div></div>

	<!-- CTA -->
	<div class="oab-cta"><div class="oab-cta-inner">
		<div class="oab-cta-sup"><?php osoul_bilingual( 'ابدأ اليوم', 'Start Today' ); ?></div>
		<h2 class="oab-cta-h"><span data-lang="ar">جاهزون لبناء <em>مشروعك</em> معك</span><span data-lang="en">Ready to Build <em>Your Project</em> With You</span></h2>
		<p class="oab-cta-sub"><?php osoul_bilingual( 'تواصل معنا اليوم ودعنا نقدم لك أفضل الحلول الصناعية', 'Contact us today and let us provide you with the best industrial solutions' ); ?></p>
		<div class="oab-cta-btns">
			<a href="tel:+966563627063" class="oab-cta-btn-p"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.62 3.38 2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.77a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><?php osoul_bilingual( 'اتصل بنا الآن', 'Call Us Now' ); ?></a>
			<a href="https://wa.me/966556847029" class="oab-cta-btn-s" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg><?php osoul_bilingual( 'واتساب مباشر', 'WhatsApp Direct' ); ?></a>
		</div>
	</div></div>
</div>
<script>!function(){
	var oab=document.querySelector('.oab');
	if(!oab)return;
	document.body.insertBefore(oab,document.body.firstChild);
	document.body.style.paddingTop='0';
	/* Hide the empty theme/Elementor content that leaves a white gap before the footer. */
	var keep=['osoul-header','osoul-footer','oql-drawer','oql-overlay','osoul-cookie','osh-mobile-menu','osh-overlay','osoul-lang-float','cert-lightbox-overlay'];
	Array.prototype.forEach.call(document.body.children,function(n){
		if(n===oab||n.tagName==='SCRIPT'||n.tagName==='STYLE'||n.tagName==='LINK')return;
		if(n.classList&&n.classList.contains('oab'))return;
		if(keep.indexOf(n.id)!==-1)return;
		if(n.querySelector&&n.querySelector('.oab'))return;
		n.style.setProperty('display','none','important');
	});
}();</script>
		<?php
	}
}
