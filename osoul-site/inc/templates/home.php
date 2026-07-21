<?php
/**
 * Home page (hero + sections) and About page.
 *
 * These two are "full-bleed" pages: their JS lifts the block to the very top of
 * <body> (above the header padding) rather than placing it after the header.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================
 * Home — hero (video)
 * ========================================================= */
add_action( 'wp_footer', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$video = 'https://osoulalbinaa.com/wp-content/uploads/2026/06/تغطية-المصنع-1.mp4';
	$home  = home_url( '/' );
	?>
<style id="osoul-hero-css">
#ohero,#ohero *{box-sizing:border-box;margin:0;padding:0}
#ohero{position:relative;width:100%;height:100vh;min-height:560px;overflow:hidden;background:#061020;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl}
body.lang-en #ohero{direction:ltr}
#ohero video{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:0}
#ohero .ov{position:absolute;inset:0;background:linear-gradient(to top,rgba(6,16,32,.97) 0%,rgba(13,31,60,.8) 45%,rgba(13,31,60,.15) 100%);z-index:1}
#ohero .body{position:absolute;bottom:0;left:0;right:0;z-index:2;padding:0 64px 56px}
#ohero .tag{font-size:11px;letter-spacing:5px;color:#D21217;text-transform:uppercase;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px}
#ohero .tag::before{content:'';width:32px;height:1px;background:#D21217}
#ohero h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(48px,8vw,96px);font-weight:700;color:#fff;line-height:1.0;letter-spacing:-2px;margin-bottom:18px}
#ohero h1 i{font-style:italic;color:#D21217}
#ohero .sub{font-size:16px;color:rgba(255,255,255,.65);line-height:1.65;font-weight:300;max-width:580px;margin-bottom:32px}
#ohero .btns{display:flex;align-items:center;gap:14px;margin-bottom:44px;flex-wrap:wrap}
#ohero .bp{display:inline-flex;align-items:center;gap:8px;background:#D21217;color:#fff;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:14px 32px;text-decoration:none;border:2px solid #D21217;transition:all .25s;min-height:44px;position:relative;z-index:10;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif}
#ohero .bp:hover{background:#e8464b;border-color:#e8464b}
#ohero .bs{display:inline-flex;align-items:center;gap:8px;background:transparent;color:rgba(255,255,255,.75);font-size:13px;font-weight:600;letter-spacing:1px;padding:14px 28px;text-decoration:none;border:1.5px solid rgba(255,255,255,.25);transition:all .25s;min-height:44px;position:relative;z-index:10;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif}
#ohero .bs:hover{border-color:rgba(255,255,255,.7)}
#ohero .stats{display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid rgba(255,255,255,.07);padding-top:28px}
#ohero .st{padding:0 0 0 24px;border-left:1px solid rgba(210,18,23,.2)}
body.lang-en #ohero .st{padding:0 24px 0 0;border-left:none;border-right:1px solid rgba(210,18,23,.2)}
#ohero .st:last-child{border-left:none;border-right:none;padding-left:0;padding-right:0}
#ohero .sn{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:36px;font-weight:700;color:#D21217;line-height:1;background:linear-gradient(135deg,#D21217,#ff5858);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
#ohero .sl{font-size:10px;color:rgba(255,255,255,.4);margin-top:4px;letter-spacing:1.5px;text-transform:uppercase}
@media(max-width:1024px){#ohero .body{padding:0 28px 44px}#ohero .stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){#ohero h1{letter-spacing:-1px}#ohero .btns{flex-direction:column;align-items:stretch}#ohero .bp,#ohero .bs{justify-content:center;width:100%}#ohero .sn{font-size:28px}#ohero .body{padding-bottom:56px}}
</style>
<div id="ohero">
<video id="ohero-video" autoplay muted loop playsinline preload="auto"><source src="<?php echo esc_url( $video ); ?>" type="video/mp4"></video>
<button id="ohero-sound-btn" onclick="oheroToggleSound()" aria-label="تشغيل الصوت" style="position:absolute;bottom:28px;right:28px;z-index:10;width:42px;height:42px;border-radius:50%;background:rgba(13,31,60,.7);border:1.5px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .25s;backdrop-filter:blur(8px)">
	<svg id="ohero-icon-muted" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
	<svg id="ohero-icon-sound" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 010 7.07"/><path d="M19.07 4.93a10 10 0 010 14.14"/></svg>
</button>
<div class="ov"></div>
<div class="body">
<div class="tag"><?php osoul_bilingual( 'أصول البناء للصناعة — جدة، المملكة العربية السعودية', 'Osoul Albinaa Industrial Co. — Jeddah, Saudi Arabia' ); ?></div>
<h1 data-lang="ar">نصنع <i>الجودة</i><br>ونبني <i>الثقة</i></h1>
<h1 data-lang="en">We Build <i>Quality</i><br>We Earn <i>Trust</i></h1>
<p class="sub"><?php osoul_bilingual( 'شريكك الموثوق في تصنيع الأبواب المعدنية المقاومة للحريق، وأنظمة الجبس بورد، وإكسسوارات التكييف — لخدمة المشاريع الحكومية والتجارية في المملكة', 'Your trusted partner in manufacturing fire-resistant metal doors, gypsum board systems, and HVAC accessories — serving government and commercial projects across Saudi Arabia' ); ?></p>
<div class="btns">
<a href="<?php echo esc_url( $home . 'products/' ); ?>" class="bp"><?php osoul_bilingual( 'استعرض المنتجات', 'View Products' ); ?></a>
<a href="<?php echo esc_url( $home . 'contact/' ); ?>" class="bs"><?php osoul_bilingual( 'تواصل معنا', 'Contact Us' ); ?></a>
</div>
<div class="stats">
<div class="st"><div class="sn">+500</div><div class="sl"><?php osoul_bilingual( 'مشروع منجز', 'Projects Done' ); ?></div></div>
<div class="st"><div class="sn">+20</div><div class="sl"><?php osoul_bilingual( 'سنة خبرة', 'Years Exp.' ); ?></div></div>
<div class="st"><div class="sn">95%</div><div class="sl"><?php osoul_bilingual( 'رضا العملاء', 'Satisfaction' ); ?></div></div>
<div class="st"><div class="sn">ISO</div><div class="sl"><?php osoul_bilingual( 'معتمد دولياً', 'Certified' ); ?></div></div>
</div>
</div>
</div>
<script>!function(){var h=document.getElementById('ohero');if(!h)return;document.body.insertBefore(h,document.body.firstChild);document.body.style.paddingTop='0';}();</script>
	<?php
}, 1 );

/* =========================================================
 * Home — main content after the hero
 * ========================================================= */
add_action( 'wp_footer', 'osoul_home_main_output', 5 );
if ( ! function_exists( 'osoul_home_main_output' ) ) {
	function osoul_home_main_output() {
		if ( ! is_front_page() && ! is_home() ) {
			return;
		}
		if ( is_home() && ! is_front_page() ) {
			return;
		}
		$home = home_url( '/' );
		$base = 'https://osoulalbinaa.com/wp-content/uploads/2026/06/';
		?>
<style id="osoul-home-styles">
.ohp{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;overflow-x:hidden}
.ohp *{box-sizing:border-box;margin:0;padding:0}
body.lang-en .ohp{direction:ltr}
.ohp-about{display:grid;grid-template-columns:1fr 1fr;min-height:520px;direction:ltr}
.ohp-about-img{position:relative;overflow:hidden;background:var(--n2);box-shadow:var(--sh-4);border-radius:var(--r-md)}
.ohp-about-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 6s ease}
.ohp-about-img:hover img{transform:scale(1.04)}
.ohp-about-badge{position:absolute;bottom:28px;right:28px;background:var(--o);color:#fff;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:8px 18px;border-radius:var(--r-md);direction:rtl;pointer-events:none;box-shadow:var(--sh-red)}
.ohp-about-txt{background:#fff;padding:72px 56px;display:flex;flex-direction:column;justify-content:center;direction:rtl;position:relative}
.ohp-about-txt::before{content:'';position:absolute;top:0;left:0;bottom:0;width:4px;background:linear-gradient(to bottom,var(--o),rgba(210,18,23,0));pointer-events:none}
.ohp-sec-tag{font-size:11px;letter-spacing:5px;color:var(--o);text-transform:uppercase;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:12px}
.ohp-sec-tag::after{content:'';flex:1;height:1px;background:rgba(210,18,23,.25)}
.ohp-about-h{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(26px,3vw,38px);font-weight:700;color:var(--n);line-height:1.25;margin-bottom:18px}
.ohp-about-h i{font-style:italic;color:var(--o)}
.ohp-about-d{font-size:15px;color:rgba(26,23,20,.65);line-height:1.65;font-weight:300;margin-bottom:28px}
.ohp-about-pts{display:flex;flex-direction:column;gap:12px;margin-bottom:32px}
.ohp-about-pt{display:flex;align-items:flex-start;gap:12px;font-size:14px;color:rgba(26,23,20,.7);line-height:1.6}
.ohp-about-pt svg{width:18px;height:18px;stroke:var(--o);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:2px;pointer-events:none}
.ohp-about-cta{display:inline-flex;align-items:center;gap:8px;background:var(--n);color:#fff;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:13px 28px;text-decoration:none;border-radius:3px;transition:all .25s;width:fit-content;min-height:44px;position:relative;z-index:10}
.ohp-about-cta:hover{background:var(--o)}
.ohp-about-cta svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.ohp-products{background:var(--cr);padding:88px 60px}
.ohp-sec-hdr{text-align:center;margin-bottom:56px}
.ohp-sec-h2{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(28px,4vw,44px);font-weight:700;color:var(--n);line-height:1.15;letter-spacing:-.5px;margin-bottom:10px}
.ohp-sec-h2 i{font-style:italic;color:var(--o)}
.ohp-sec-sub{font-size:15px;color:rgba(26,23,20,.55);max-width:520px;margin:0 auto;line-height:1.65;font-weight:300}
.ohp-ind-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:3px;direction:ltr}
.ohp-ind-card{position:relative;overflow:hidden;min-height:420px;display:flex;flex-direction:column;justify-content:flex-end;direction:rtl;border-radius:var(--r-md);transition:transform var(--t-med),box-shadow var(--t-med)}
.ohp-ind-card:hover{transform:translateY(-6px);box-shadow:var(--sh-4)}
.ohp-ind-bg{position:absolute;inset:0;z-index:0;background:var(--n2)}
.ohp-ind-bg img{width:100%;height:100%;object-fit:cover;display:block;filter:brightness(.32) saturate(.85);transition:transform 6s ease,filter .4s}
.ohp-ind-card:hover .ohp-ind-bg img{transform:scale(1.06);filter:brightness(.4) saturate(1)}
.ohp-ind-ov{position:absolute;inset:0;z-index:1;background:linear-gradient(to top,rgba(6,16,32,.97) 0%,rgba(13,31,60,.75) 45%,rgba(13,31,60,.35) 100%);transition:background .4s}
.ohp-ind-card:hover .ohp-ind-ov{background:linear-gradient(to top,rgba(6,16,32,.98) 0%,rgba(13,31,60,.85) 45%,rgba(13,31,60,.45) 100%)}
.ohp-ind-body{position:relative;z-index:2;padding:32px}
.ohp-ind-num{position:absolute;top:24px;right:24px;font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:72px;font-weight:700;color:rgba(210,18,23,.08);line-height:1;pointer-events:none;transition:opacity var(--t-med)}
.ohp-ind-card:hover .ohp-ind-num{opacity:.15}
.ohp-ind-tag{display:inline-block;font-size:10px;letter-spacing:2.5px;color:var(--o);text-transform:uppercase;font-weight:700;margin-bottom:14px;border:1px solid rgba(210,18,23,.4);padding:4px 12px;border-radius:20px}
.ohp-ind-ic{width:46px;height:46px;background:rgba(210,18,23,.1);border:1.5px solid rgba(210,18,23,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:16px;transition:all .3s}
.ohp-ind-card:hover .ohp-ind-ic{background:var(--o);border-color:var(--o);transform:scale(1.1);box-shadow:var(--sh-red)}
.ohp-ind-ic svg{width:22px;height:22px;stroke:#D21217;fill:none;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;transition:stroke .3s}
.ohp-ind-card:hover .ohp-ind-ic svg{stroke:#fff}
.ohp-ind-h{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:20px;font-weight:700;color:#fff;line-height:1.3;margin-bottom:12px}
.ohp-ind-d{font-size:13px;color:rgba(255,255,255,.6);line-height:1.7;font-weight:300;margin-bottom:20px}
.ohp-ind-link{display:inline-flex;align-items:center;gap:7px;border:1.5px solid rgba(210,18,23,.5);color:var(--o);font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:10px 20px;text-decoration:none;border-radius:2px;transition:all .25s}
.ohp-ind-link:hover{background:var(--o);color:#fff;border-color:var(--o)}
.ohp-ind-link svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
@media(max-width:1024px){.ohp-ind-grid{grid-template-columns:1fr}.ohp-ind-card{min-height:320px}}
.ohp-projects{background:#fff;padding:88px 60px}
.ohp-proj-featured{display:grid;grid-template-columns:1.3fr 1fr;background:var(--n);border-radius:12px;overflow:hidden;direction:ltr;margin-bottom:32px;box-shadow:var(--sh-4);transition:box-shadow var(--t-med)}
.ohp-proj-featured:hover{box-shadow:0 24px 72px rgba(13,31,60,.22),0 8px 24px rgba(13,31,60,.1)}
.ohp-pf-img{position:relative;overflow:hidden;min-height:380px}
.ohp-pf-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 6s ease}
.ohp-proj-featured:hover .ohp-pf-img img{transform:scale(1.04)}
.ohp-pf-badge{position:absolute;top:20px;right:20px;background:var(--o);color:#fff;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:5px 12px;border-radius:2px;pointer-events:none}
.ohp-pf-txt{padding:52px 44px;display:flex;flex-direction:column;justify-content:center;direction:rtl}
.ohp-pf-cat{font-size:10px;letter-spacing:4px;color:var(--o);text-transform:uppercase;font-weight:700;margin-bottom:14px}
.ohp-pf-h{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(20px,2.5vw,28px);font-weight:700;color:#fff;line-height:1.3;margin-bottom:14px}
.ohp-pf-h i{font-style:italic;color:var(--o)}
.ohp-pf-d{font-size:14px;color:rgba(255,255,255,.58);line-height:1.65;font-weight:300;margin-bottom:24px}
.ohp-pf-meta{display:flex;flex-direction:column;gap:8px;margin-bottom:28px;padding:16px;background:rgba(255,255,255,.04);border-radius:6px;border:1px solid rgba(255,255,255,.07);pointer-events:none}
.ohp-pf-mi{display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,.65)}
.ohp-pf-mi svg{width:15px;height:15px;stroke:var(--o);fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;pointer-events:none}
.ohp-pf-cta{display:inline-flex;align-items:center;gap:8px;background:var(--o);color:#fff;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:12px 24px;text-decoration:none;border-radius:3px;transition:all .25s;width:fit-content;min-height:44px}
.ohp-pf-cta:hover{background:var(--o2)}
.ohp-pf-cta svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.ohp-proj-mini{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;direction:ltr}
.ohp-pm-card{background:var(--cr);border-radius:8px;overflow:hidden;direction:rtl;transition:all .3s;box-shadow:var(--sh-2)}
.ohp-pm-card:hover{box-shadow:var(--sh-4);transform:translateY(-6px)}
.ohp-pm-img{height:180px;overflow:hidden;background:var(--n2);position:relative}
.ohp-pm-img img{width:100%;height:100%;object-fit:contain;background:#fff;display:block;transition:transform .5s;pointer-events:none}
.ohp-pm-card:hover .ohp-pm-img img{transform:scale(1.07)}
.ohp-pm-cat{position:absolute;bottom:12px;right:12px;background:var(--n);color:var(--o);font-size:9px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:3px 9px;border-radius:2px;pointer-events:none}
.ohp-pm-body{padding:20px}
.ohp-pm-h{font-size:15px;font-weight:700;color:var(--n);line-height:1.35;margin-bottom:8px}
.ohp-pm-loc{display:flex;align-items:center;gap:5px;font-size:12px;color:rgba(26,23,20,.5)}
.ohp-pm-loc svg{width:12px;height:12px;stroke:var(--o);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.ohp-proj-more{text-align:center;margin-top:40px}
.ohp-proj-more a{display:inline-flex;align-items:center;gap:8px;border:2px solid var(--n);color:var(--n);font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:13px 32px;text-decoration:none;border-radius:3px;transition:all .25s;min-height:44px}
.ohp-proj-more a:hover{background:var(--n);color:#fff}
.ohp-proj-more a svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.ohp-why{background:var(--n);padding:88px 60px;position:relative;overflow:hidden}
.ohp-why::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 59px,rgba(210,18,23,.04) 59px,rgba(210,18,23,.04) 60px),repeating-linear-gradient(90deg,transparent,transparent 59px,rgba(210,18,23,.04) 59px,rgba(210,18,23,.04) 60px);pointer-events:none}
.ohp-why::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,var(--o),transparent);pointer-events:none}
.ohp-why-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:2px;position:relative}
.ohp-why-item{padding:36px 28px;border-right:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.02);transition:background var(--t-med),border-color var(--t-med)}
.ohp-why-item:hover{background:rgba(210,18,23,.06)}
.ohp-why-item:last-child{border-right:none}
.ohp-why-icon{width:56px;height:56px;border:1.5px solid rgba(210,18,23,.6);background:rgba(210,18,23,.14);border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:20px;pointer-events:none;transition:all var(--t-med)}
.ohp-why-item:hover .ohp-why-icon{background:var(--o);border-color:var(--o);transform:scale(1.08);box-shadow:0 0 24px rgba(210,18,23,.35)}
.ohp-why-icon svg{width:26px;height:26px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.ohp-why-h{font-size:16px;font-weight:700;color:#fff;margin-bottom:10px}
.ohp-why-h{font-size:17px}
.ohp-why-d{font-size:13.5px;color:rgba(255,255,255,.78);line-height:1.7;font-weight:400}
.ohp-clients{background:var(--cr);padding:56px 0;overflow:hidden;position:relative}
.ohp-clients-head{text-align:center;margin-bottom:40px;direction:rtl;position:relative;z-index:1;padding:0 60px}
.ohp-clients-tag{font-size:11px;letter-spacing:4px;color:var(--o);text-transform:uppercase;font-weight:700;margin-bottom:10px}
.ohp-clients-h{font-size:24px;font-weight:700;color:var(--n);font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif}
.ohp-clients-h i{font-style:italic;color:var(--o)}
.ohp-clients-track-outer{position:relative;width:100%;direction:ltr!important}
.ohp-clients-track-outer::before,.ohp-clients-track-outer::after{content:'';position:absolute;top:0;bottom:0;width:120px;z-index:2;pointer-events:none}
.ohp-clients-track-outer::before{right:0;background:linear-gradient(to left,var(--cr),transparent)}
.ohp-clients-track-outer::after{left:0;background:linear-gradient(to right,var(--cr),transparent)}
.ohp-clients-track{display:flex;gap:64px;width:max-content;align-items:center;animation:ohp-clients-scroll 55s linear infinite;will-change:transform;direction:ltr!important}
.ohp-clients-track:hover{animation-play-state:paused}
@keyframes ohp-clients-scroll{from{transform:translateX(0)}to{transform:translateX(-25%)}}
.ohp-client-logo{flex-shrink:0;height:64px;display:flex;align-items:center;justify-content:center}
.ohp-client-logo img{max-height:100%;max-width:140px;object-fit:contain;filter:grayscale(1) opacity(.55);transition:filter var(--t-med),transform var(--t-fast);pointer-events:none}
.ohp-client-logo:hover img{filter:grayscale(0) opacity(1);transform:scale(1.06)}
.ohp-cta{padding:80px 60px;text-align:center;position:relative;overflow:hidden;background:linear-gradient(135deg,#b50f13 0%,#D21217 40%,#c41015 70%,#a80d11 100%)}
.ohp-cta::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 20% 50%,rgba(255,255,255,.06) 0%,transparent 60%),radial-gradient(ellipse at 80% 30%,rgba(0,0,0,.12) 0%,transparent 50%),repeating-linear-gradient(45deg,transparent,transparent 40px,rgba(255,255,255,.03) 40px,rgba(255,255,255,.03) 41px);pointer-events:none}
.ohp-cta-tag{font-size:11px;letter-spacing:5px;color:rgba(255,255,255,.7);text-transform:uppercase;font-weight:700;margin-bottom:16px;position:relative}
.ohp-cta h2{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(28px,4.5vw,50px);font-weight:700;color:#fff;line-height:1.2;margin-bottom:14px;position:relative}
.ohp-cta h2 i{font-style:italic;color:rgba(255,255,255,.75)}
.ohp-cta p{font-size:16px;color:rgba(255,255,255,.75);max-width:500px;margin:0 auto 40px;line-height:1.65;font-weight:300;position:relative}
.ohp-cta-btns{display:flex;align-items:center;justify-content:center;gap:16px;flex-wrap:wrap;position:relative;z-index:10}
.ohp-cta-btn-p{display:inline-flex;align-items:center;gap:8px;background:#fff;color:var(--o);font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:15px 36px;text-decoration:none;border:2px solid #fff;transition:all .25s;border-radius:3px;min-height:44px}
.ohp-cta-btn-p:hover{background:transparent;color:#fff}
.ohp-cta-btn-s{display:inline-flex;align-items:center;gap:8px;background:transparent;color:#fff;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:13px;font-weight:600;letter-spacing:1px;padding:15px 28px;text-decoration:none;border:1.5px solid rgba(255,255,255,.5);transition:all .25s;border-radius:3px;min-height:44px}
.ohp-cta-btn-s:hover{border-color:#fff;background:rgba(255,255,255,.1)}
@media(max-width:1024px){.ohp-about{grid-template-columns:1fr}.ohp-about-img{min-height:300px}.ohp-about-txt{padding:48px 28px}.ohp-products,.ohp-projects,.ohp-why,.ohp-cta{padding:60px 28px}.ohp-proj-featured{grid-template-columns:1fr}.ohp-pf-img{min-height:280px}.ohp-pf-txt{padding:40px 28px}.ohp-proj-mini{grid-template-columns:repeat(2,1fr)}.ohp-why-grid{grid-template-columns:repeat(2,1fr)}.ohp-why-item{border-right:none;border-bottom:1px solid rgba(255,255,255,.07)}}
@media(max-width:640px){.ohp-proj-mini{grid-template-columns:1fr}.ohp-why-grid{grid-template-columns:1fr}.ohp-cta-btns{flex-direction:column;align-items:stretch}.ohp-cta-btn-p,.ohp-cta-btn-s{justify-content:center;width:100%}.ohp-pm-card:hover,.ohp-proj-featured:hover{transform:none}}
</style>
<div class="ohp" id="ohp-wrap">
	<!-- About -->
	<div class="ohp-about">
		<div class="ohp-about-img">
			<img src="https://osoulalbinaa.com/wp-content/uploads/2026/06/DSC_2341-scaled-1.webp" alt="مصنع أصول البناء" loading="lazy">
			<div class="ohp-about-badge"><?php osoul_bilingual( '+20 سنة خبرة', '20+ Years Exp.' ); ?></div>
		</div>
		<div class="ohp-about-txt">
			<div class="ohp-sec-tag"><?php osoul_bilingual( 'من نحن', 'About Us' ); ?></div>
			<h2 class="ohp-about-h"><span data-lang="ar">شركة سعودية<br><i>تصنع الفرق</i></span><span data-lang="en">A Saudi Company<br><i>Built to Make a Difference</i></span></h2>
			<p class="ohp-about-d"><?php osoul_bilingual( 'أصول البناء للصناعة — شركة سعودية متخصصة تأسست في جدة، تعمل في تصنيع وتوريد الأبواب المعدنية المقاومة للحريق، وأنظمة الجبس بورد، وإكسسوارات التكييف، وأنظمة تثبيت الدعامات لخدمة المشاريع الكبرى في المملكة.', 'Osoul Albinaa Industrial Co. is a Saudi specialized company founded in Jeddah, manufacturing fire-resistant metal doors, gypsum board systems, HVAC accessories, and strut fixing systems for major projects across the Kingdom.' ); ?></p>
			<div class="ohp-about-pts">
				<?php
				$pts = array(
					array( 'منتجات معتمدة دولياً بمعايير ISO وSASO', 'Internationally certified products — ISO & SASO' ),
					array( 'أكثر من 500 مشروع منجز في 15 مدينة سعودية', '500+ completed projects in 15 Saudi cities' ),
					array( 'فريق هندسي متخصص وضمان شامل على جميع المنتجات', 'Specialized engineering team with full product warranty' ),
					array( 'خطوط إنتاج متكاملة بأحدث التقنيات الصناعية', 'Fully integrated production lines with the latest industrial technology' ),
				);
				foreach ( $pts as $pt ) : ?>
				<div class="ohp-about-pt"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?php osoul_bilingual( $pt[0], $pt[1] ); ?></div>
				<?php endforeach; ?>
			</div>
			<a href="<?php echo esc_url( $home . 'about-us/' ); ?>" class="ohp-about-cta"><?php osoul_bilingual( 'تعرف علينا أكثر', 'Learn More About Us' ); ?><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
		</div>
	</div>

	<!-- Products / industries -->
	<div class="ohp-products">
		<div class="ohp-sec-hdr">
			<div class="ohp-sec-tag" style="justify-content:center"><?php osoul_bilingual( 'منتجاتنا', 'Our Products' ); ?></div>
			<h2 class="ohp-sec-h2"><span data-lang="ar">حلول صناعية <i>متكاملة</i></span><span data-lang="en">Integrated <i>Industrial Solutions</i></span></h2>
			<p class="ohp-sec-sub"><?php osoul_bilingual( 'نوفر طيفاً واسعاً من المنتجات الصناعية عالية الجودة لتلبية احتياجات المشاريع الحكومية والتجارية والصناعية', 'We provide a wide range of high-quality industrial products to meet government, commercial and industrial project needs' ); ?></p>
		</div>
		<div class="ohp-ind-grid">
			<?php
			$inds = array(
				array( 'num' => '01', 'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/ابواب-الطوارئ.png', 'tag' => 'أبواب صناعية', 'tag_en' => 'Industrial Doors', 'ic' => '<rect x="3" y="2" width="18" height="20" rx="1"/><circle cx="16" cy="12" r="1.2" fill="currentColor"/>', 'h_ar' => 'الأبواب بأنواعها<br>وتفاصيلها الكاملة', 'h_en' => 'All Door Types<br>With Full Details', 'd' => 'أبواب معدنية مجوفة ومقاومة للحريق بمختلف المقاسات والمواصفات، معتمدة بدرجات مقاومة من 30 إلى 120 دقيقة', 'd_en' => 'Hollow and fire-resistant metal doors in various sizes, certified with fire resistance from 30 to 120 minutes', 'url' => $home . 'doors/' ),
				array( 'num' => '02', 'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/DSC_2482-scaled.jpg', 'tag' => 'أنظمة التشطيب', 'tag_en' => 'Finishing Systems', 'ic' => '<rect x="2" y="4" width="20" height="5" rx="0.5"/><rect x="2" y="11" width="20" height="5" rx="0.5"/><rect x="2" y="18" width="20" height="2.5" rx="0.5"/>', 'h_ar' => 'بروفايلات الجبسوم<br>والسمنت بورد', 'h_en' => 'Gypsum Board<br>& Cement Board Profiles', 'd' => 'أنظمة بروفايلات متكاملة لتركيب ألواح الجبسوم بورد في الأسقف والجدران، من الصلب المجلفن عالي الجودة', 'd_en' => 'Integrated profile systems for gypsum board installation in ceilings and walls, made from high-quality galvanized steel', 'url' => $home . 'gypsum/' ),
				array( 'num' => '03', 'img' => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/DSC_2483-scaled.jpg', 'tag' => 'أنظمة التثبيت', 'tag_en' => 'Fixing Systems', 'ic' => '<rect x="3" y="10" width="18" height="4" rx="1"/><rect x="3" y="3" width="4" height="18" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/>', 'h_ar' => 'بروفايلات وإكسسوارات<br>التثبيت والتعليق', 'h_en' => 'Strut Profiles<br>& Suspension Accessories', 'd' => 'منظومة متكاملة من بروفايلات Strut وإكسسوارات التثبيت والتعليق المجلفنة لمختلف الأنظمة الصناعية', 'd_en' => 'A complete system of Strut profiles and galvanized fixing & suspension accessories for various industrial systems', 'url' => $home . 'strut/' ),
			);
			foreach ( $inds as $ind ) : ?>
			<div class="ohp-ind-card">
				<div class="ohp-ind-bg"><img src="<?php echo esc_url( $ind['img'] ); ?>" alt="" loading="lazy"></div>
				<div class="ohp-ind-ov"></div>
				<div class="ohp-ind-body">
					<div class="ohp-ind-num"><?php echo esc_html( $ind['num'] ); ?></div>
					<div class="ohp-ind-tag"><?php osoul_bilingual( $ind['tag'], $ind['tag_en'] ); ?></div>
					<div class="ohp-ind-ic"><svg viewBox="0 0 24 24"><?php echo $ind['ic']; // phpcs:ignore WordPress.Security.EscapeOutput — static SVG ?></svg></div>
					<h3 class="ohp-ind-h"><span data-lang="ar"><?php echo wp_kses_post( $ind['h_ar'] ); ?></span><span data-lang="en"><?php echo wp_kses_post( $ind['h_en'] ); ?></span></h3>
					<p class="ohp-ind-d"><?php osoul_bilingual( $ind['d'], $ind['d_en'] ); ?></p>
					<a href="<?php echo esc_url( $ind['url'] ); ?>" class="ohp-ind-link"><?php osoul_bilingual( 'استعرض المنتج', 'View Product' ); ?><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Featured projects -->
	<div class="ohp-projects">
		<div class="ohp-sec-hdr">
			<div class="ohp-sec-tag" style="justify-content:center"><?php osoul_bilingual( 'مشاريعنا', 'Our Projects' ); ?></div>
			<h2 class="ohp-sec-h2"><span data-lang="ar">إنجازات <i>نفخر بها</i></span><span data-lang="en">Achievements <i>We're Proud Of</i></span></h2>
			<p class="ohp-sec-sub"><?php osoul_bilingual( 'من أبرز مشاريعنا المنجزة في المملكة العربية السعودية', 'Highlights from our completed projects across Saudi Arabia' ); ?></p>
		</div>
		<div class="ohp-proj-featured">
			<div class="ohp-pf-img">
				<img src="<?php echo esc_url( $base . 'DSC_2387-scaled.jpg' ); ?>" alt="مشروع توريد وتركيب أبواب مقاومة للحريق جدة" loading="lazy">
				<div class="ohp-pf-badge"><?php osoul_bilingual( 'مشروع مميز', 'Featured Project' ); ?></div>
			</div>
			<div class="ohp-pf-txt">
				<div class="ohp-pf-cat"><?php osoul_bilingual( 'أحدث مشاريعنا', 'Latest Project' ); ?></div>
				<h3 class="ohp-pf-h"><span data-lang="ar">توريد وتركيب أبواب<br><i>مقاومة للحريق</i></span><span data-lang="en">Supply & Installation of<br><i>Fire-Resistant Doors</i></span></h3>
				<p class="ohp-pf-d"><?php osoul_bilingual( 'مشروع توريد وتركيب أكثر من 450 باباً معدنياً مقاوماً للحريق في مجمع سكني وتجاري كبير بمدينة جدة — مع ضمان شامل 3 سنوات', 'Supply and installation of 450+ fire-resistant metal doors for a large residential and commercial complex in Jeddah — with a 3-year comprehensive warranty' ); ?></p>
				<div class="ohp-pf-meta">
					<div class="ohp-pf-mi"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg><?php osoul_bilingual( 'جدة — المملكة العربية السعودية', 'Jeddah — Saudi Arabia' ); ?></div>
					<div class="ohp-pf-mi"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>2025 — <?php osoul_bilingual( 'مشروع مكتمل', 'Completed' ); ?></div>
					<div class="ohp-pf-mi"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>+450 <?php osoul_bilingual( 'باب — معتمد ISO', 'Doors — ISO Certified' ); ?></div>
				</div>
				<a href="<?php echo esc_url( $home . 'projects/' ); ?>" class="ohp-pf-cta"><?php osoul_bilingual( 'شاهد كل المشاريع', 'View All Projects' ); ?><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
			</div>
		</div>
		<div class="ohp-proj-mini">
			<?php
			$mini = array(
				array( 'img' => 'DSC_2377-scaled.jpg', 'cat' => 'حكومي', 'cat_en' => 'Government', 'h' => 'وزارة التعليم — مدارس جدة', 'h_en' => 'Ministry of Education — Jeddah Schools', 'loc' => 'جدة', 'loc_en' => 'Jeddah' ),
				array( 'img' => 'DSC_2382-scaled.jpg', 'cat' => 'صحي', 'cat_en' => 'Healthcare', 'h' => 'مستشفى الملك فهد', 'h_en' => 'King Fahd Hospital', 'loc' => 'جدة', 'loc_en' => 'Jeddah' ),
				array( 'img' => 'DSC_2368-scaled.jpg', 'cat' => 'تجاري', 'cat_en' => 'Commercial', 'h' => 'مجمع تجاري — حي الرحاب', 'h_en' => 'Al Rehab District Complex', 'loc' => 'جدة', 'loc_en' => 'Jeddah' ),
			);
			foreach ( $mini as $m ) : ?>
			<div class="ohp-pm-card">
				<div class="ohp-pm-img">
					<img src="<?php echo esc_url( $base . $m['img'] ); ?>" alt="<?php echo esc_attr( $m['h'] ); ?>" loading="lazy">
					<div class="ohp-pm-cat"><?php osoul_bilingual( $m['cat'], $m['cat_en'] ); ?></div>
				</div>
				<div class="ohp-pm-body">
					<div class="ohp-pm-h"><?php osoul_bilingual( $m['h'], $m['h_en'] ); ?></div>
					<div class="ohp-pm-loc"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg><?php osoul_bilingual( $m['loc'], $m['loc_en'] ); ?></div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<div class="ohp-proj-more">
			<a href="<?php echo esc_url( $home . 'projects/' ); ?>"><?php osoul_bilingual( 'استعرض جميع المشاريع', 'View All Projects' ); ?><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
		</div>
	</div>

	<!-- Why us -->
	<div class="ohp-why">
		<div style="text-align:center;margin-bottom:56px;position:relative">
			<div class="ohp-sec-tag" style="justify-content:center;color:var(--o)"><?php osoul_bilingual( 'لماذا نحن', 'Why Choose Us' ); ?></div>
			<h2 style="font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(28px,4vw,44px);font-weight:700;color:#fff;line-height:1.2"><span data-lang="ar">نقدم <i style="font-style:italic;color:var(--o)">أكثر من منتج</i></span><span data-lang="en">We Deliver <i style="font-style:italic;color:var(--o)">More Than Products</i></span></h2>
		</div>
		<div class="ohp-why-grid">
			<?php
			$whys = array(
				array( 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'h' => 'جودة معتمدة دولياً', 'h_en' => 'Internationally Certified', 'd' => 'منتجاتنا مطابقة لمعايير ISO وSASO وتخضع لاختبارات صارمة قبل التسليم', 'd_en' => 'Our products comply with ISO and SASO standards and undergo rigorous testing before delivery' ),
				array( 'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', 'h' => 'التسليم في الوقت', 'h_en' => 'On-Time Delivery', 'd' => 'نلتزم بمواعيد التسليم المتفق عليها ونحرص على ديمومة سلسلة الإمداد', 'd_en' => 'We honor agreed delivery schedules and maintain a reliable supply chain' ),
				array( 'icon' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>', 'h' => 'فريق متخصص', 'h_en' => 'Expert Team', 'd' => 'مهندسون وفنيون ذوو خبرة عالية جاهزون لدعمك في كل مراحل المشروع', 'd_en' => 'Qualified engineers and technicians ready to support you at every project phase' ),
				array( 'icon' => '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.1 1.18 2 2 0 012.08 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.09a16 16 0 006 6z"/>', 'h' => 'دعم ما بعد البيع', 'h_en' => 'After-Sales Support', 'd' => 'نقدم ضماناً شاملاً ودعماً فنياً مستمراً لضمان رضاك التام عن منتجاتنا', 'd_en' => 'We provide comprehensive warranty and ongoing technical support for your complete satisfaction' ),
			);
			foreach ( $whys as $w ) : ?>
			<div class="ohp-why-item">
				<div class="ohp-why-icon"><svg viewBox="0 0 24 24"><?php echo $w['icon']; // phpcs:ignore WordPress.Security.EscapeOutput — static SVG ?></svg></div>
				<div class="ohp-why-h"><?php osoul_bilingual( $w['h'], $w['h_en'] ); ?></div>
				<p class="ohp-why-d"><?php osoul_bilingual( $w['d'], $w['d_en'] ); ?></p>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Clients slider -->
	<div class="ohp-clients">
		<div class="ohp-clients-head">
			<div class="ohp-clients-tag"><?php osoul_bilingual( 'شركاؤنا وعملاؤنا', 'Our Partners & Clients' ); ?></div>
			<div class="ohp-clients-h"><span data-lang="ar">موثوقون من <i>كبرى الجهات</i></span><span data-lang="en">Trusted by <i>Leading Organizations</i></span></div>
		</div>
		<div class="ohp-clients-track-outer">
			<div class="ohp-clients-track" id="ohp-clients-track">
				<?php
				$logos = array( '9980.png', '54545.png', '8776.png', '4543.png', '3545.png', '121.png', '1231.png', '343.png', '231.png' );
				for ( $r = 0; $r < 4; $r++ ) {
					foreach ( $logos as $li => $lg ) {
						echo '<div class="ohp-client-logo"><img src="' . esc_url( $base . $lg ) . '" alt="شعار عميل ' . ( (int) $li + 1 ) . '" loading="lazy"></div>';
					}
				}
				?>
			</div>
		</div>
	</div>

	<!-- CTA -->
	<div class="ohp-cta">
		<div class="ohp-cta-tag"><?php osoul_bilingual( 'ابدأ الآن', 'Get Started' ); ?></div>
		<h2><span data-lang="ar">مشروعك القادم<br><i>يبدأ من هنا</i></span><span data-lang="en">Your Next Project<br><i>Starts Here</i></span></h2>
		<p><?php osoul_bilingual( 'تواصل معنا اليوم واحصل على عرض سعر مخصص لمشروعك — فريقنا جاهز للرد', 'Contact us today and get a customized quote — our team is ready to respond' ); ?></p>
		<div class="ohp-cta-btns">
			<a href="<?php echo esc_url( $home . 'contact/' ); ?>" class="ohp-cta-btn-p"><?php osoul_bilingual( 'اطلب عرض سعر', 'Request a Quote' ); ?></a>
			<a href="https://wa.me/966556847029" target="_blank" rel="noopener" class="ohp-cta-btn-s"><?php osoul_bilingual( 'واتساب مباشر', 'WhatsApp Us' ); ?></a>
		</div>
	</div>
</div>
<script>
(function(){
	var el=document.getElementById('ohp-wrap');
	if(!el)return;
	var allSections=Array.prototype.slice.call(document.querySelectorAll('.elementor-section,.e-con,.elementor-top-section')).filter(function(s){
		return !s.closest('#ohp-wrap')&&!s.closest('#osoul-footer')&&!s.closest('#osoul-header');
	});
	var hero=allSections[0];
	if(hero&&hero.parentElement){hero.parentElement.insertBefore(el,hero.nextSibling);}
	else{var footer=document.getElementById('osoul-footer');if(footer)document.body.insertBefore(el,footer);}
	allSections.slice(1).forEach(function(s){s.style.setProperty('display','none','important');});
	el.style.cssText+=';display:block!important;width:100%!important;max-width:100%!important;margin:0!important;';
	var p=el.parentElement;
	while(p&&p!==document.body){p.style.cssText+=';padding:0!important;margin:0!important;max-width:none!important;width:100%!important;';p=p.parentElement;}
})();
</script>
		<?php
	}
}

/* =========================================================
 * About — /about-us/
 * ========================================================= */
require_once OSOUL_DIR . 'inc/templates/about.php';
