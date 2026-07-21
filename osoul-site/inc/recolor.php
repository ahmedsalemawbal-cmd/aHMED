<?php
/**
 * Palette refinement — make navy the lead colour and keep red as an accent only
 * on primary call-to-action buttons.
 *
 * Decorative red (tags, lines, headings, icons, badges) becomes:
 *   - navy (#0d1f3c) on light backgrounds,
 *   - soft steel (#aebfda) on dark/navy backgrounds (so it stays readable).
 * Primary CTA buttons are intentionally left red.
 *
 * Emitted last (wp_footer priority 999) with !important so it overrides both the
 * global stylesheet and the per-page inline <style> blocks.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_footer', 'osoul_recolor_css', 999 );
if ( ! function_exists( 'osoul_recolor_css' ) ) {
	function osoul_recolor_css() {
		?>
<style id="osoul-recolor">
/* ===== Decorative red → NAVY (light backgrounds) ===== */
.ohp-sec-tag{color:#0d1f3c!important}
.ohp-sec-tag::after{background:linear-gradient(90deg,rgba(13,31,60,.25),transparent)!important}
.ohp-about-h i,.ohp-sec-h2 i,.osrv-cta-h i,.opr-h1 i,.opdtl-related h2 i{color:#0d1f3c!important}
.ohp-about-pt svg{stroke:#0d1f3c!important}
.opp-card-cat,.odps-card-sub,.osrv-card-cta,.obls-read-more,.opg-stat-n{color:#0d1f3c!important}
.opp-card-badge,.ohp-pm-cat,.opg-card-cat,.obls-card-cat{background:#0d1f3c!important;color:#fff!important}
.osrv-card-h,.osrv-cta-h{color:#0d1f3c!important}
.osrv-card::before{background:linear-gradient(90deg,transparent,#0d1f3c,transparent)!important}
.osrv-ic{background:rgba(13,31,60,.06)!important;border-color:rgba(13,31,60,.18)!important}
.osrv-ic svg{stroke:#0d1f3c!important}
.osrv-card:hover .osrv-ic{background:#0d1f3c!important;border-color:#0d1f3c!important}
.osrv-card:hover .osrv-ic svg{stroke:#fff!important}
.ohp-clients-tag,.ohp-clients-h i{color:#0d1f3c!important}
.ohp-pm-loc svg,.opg-card-meta svg,.obls-card-meta svg,.oct-info-ic svg{stroke:#0d1f3c!important}
.opg-card-year::before{background:rgba(13,31,60,.4)!important}
.oct-info-ic{background:rgba(13,31,60,.06)!important;border-color:rgba(13,31,60,.18)!important}
.oct-info-txt a:hover,.oct-social a:hover{color:#0d1f3c!important}
.oct-social a:hover{background:#0d1f3c!important;border-color:#0d1f3c!important;color:#fff!important}
.opr-section h2{border-color:#0d1f3c!important}
.opdtl-cert{background:rgba(13,31,60,.05)!important;border-color:rgba(13,31,60,.18)!important;color:#0d1f3c!important}
.opp-card:hover,.odps-card:hover,.osrv-card:hover,.opg-card:hover,.obls-card:hover{border-color:rgba(13,31,60,.12)!important}
/* About — light sections */
.oab-sec-tag,.oab-sec-tag::before{color:#0d1f3c!important}
.oab-sec-tag::before{background:#0d1f3c!important}
.oab-sec-h em{color:#0d1f3c!important}
.oab-v-card{border-top-color:#0d1f3c!important}
.oab-step::before{background:#0d1f3c!important}
.oab-step-icon{color:#0d1f3c!important;border-color:rgba(13,31,60,.2)!important}
.oab-cert-sub{color:rgba(13,31,60,.45)!important}

/* ===== Decorative red → SOFT STEEL (dark/navy backgrounds) ===== */
/* Home hero */
#ohero .tag{color:#aebfda!important}
#ohero .tag::before{background:#aebfda!important}
#ohero h1 i{color:#aebfda!important}
#ohero .sn{background:none!important;-webkit-text-fill-color:#aebfda!important;color:#aebfda!important}
#ohero .st{border-left-color:rgba(174,191,218,.2)!important;border-right-color:rgba(174,191,218,.2)!important}
/* Industry cards (dark imagery) */
.ohp-ind-num{color:rgba(174,191,218,.12)!important}
.ohp-ind-tag{color:#aebfda!important;border-color:rgba(174,191,218,.4)!important}
.ohp-ind-ic{border-color:rgba(174,191,218,.35)!important;background:rgba(174,191,218,.1)!important}
.ohp-ind-ic svg{stroke:#aebfda!important}
.ohp-ind-card:hover .ohp-ind-ic{background:#aebfda!important;border-color:#aebfda!important}
.ohp-ind-card:hover .ohp-ind-ic svg{stroke:#0d1f3c!important}
.ohp-ind-link{color:#aebfda!important;border-color:rgba(174,191,218,.5)!important}
.ohp-ind-link:hover{background:#aebfda!important;color:#0d1f3c!important;border-color:#aebfda!important}
/* Featured project (navy card) */
.ohp-pf-cat,.ohp-pf-h i{color:#aebfda!important}
.ohp-pf-mi svg{stroke:#aebfda!important}
.ohp-pf-badge{background:#aebfda!important;color:#0d1f3c!important}
/* Why-us (navy) */
.ohp-why::after{background:linear-gradient(90deg,transparent,#aebfda,transparent)!important}
.ohp-why h2 i,.ohp-why .ohp-sec-tag{color:#aebfda!important}
.ohp-why-icon{border-color:rgba(174,191,218,.4)!important;background:rgba(174,191,218,.12)!important}
.ohp-why-icon svg{stroke:#aebfda!important}
.ohp-why-item:hover .ohp-why-icon{background:#aebfda!important;border-color:#aebfda!important;box-shadow:0 0 24px rgba(174,191,218,.25)!important}
.ohp-why-item:hover .ohp-why-icon svg{stroke:#0d1f3c!important}
/* Page heroes (navy) — top line, tags, italic headings */
.opp-hero::before,.odp-hero::before,.odps-hero::before,.opdtl-hero::before,.osrv-hero::before,.osi-hero::before,.opg-hero::before,.obl-hero::before,.obls-hero::before,.oct-hero::before,.opr-hero::before,.oab-hero::before,.osrv-cta-bar::after{background:linear-gradient(90deg,transparent,#aebfda,transparent)!important}
.opp-tag,.odp-tag,.osrv-tag,.osi-tag,.opg-tag,.oct-tag,.opr-tag,.opdtl-cat-tag,.osi-tag a{color:#aebfda!important}
.opp-tag::before,.odp-tag::before,.osrv-tag::before,.osi-tag::before,.opg-tag::before,.oct-tag::before,.opr-tag::before{background:#aebfda!important}
.opp-h1 i,.odp-h1 i,.osrv-h1 i,.osi-h1 i,.opg-h1 i,.oct-h1 i,.opdtl-h1 i,.odps-hero h1 i,.obls-h1 i{color:#aebfda!important}
.opdtl-breadcrumb a:hover{color:#aebfda!important}
/* About — dark sections */
.oab-tag,.oab-tag::before{color:#aebfda!important}
.oab-tag::before{background:#aebfda!important}
.oab-h1 em,.oab-ceo-q,.oab-ceo-name + .oab-ceo-title{color:#aebfda!important}
.oab-ceo-tag,.oab-ceo-title,.oab-ceo-ctitle,.oab-meta-l{color:#aebfda!important}
.oab-ceo::after{background:#aebfda!important}
.oab-m-card{border-top-color:#aebfda!important}
/* Header (navy) */
#osoul-header .osh-top{border-bottom-color:rgba(174,191,218,.2)!important}
#osoul-header .osh-main{border-bottom-color:rgba(174,191,218,.25)!important}
.osh-top-item svg{stroke:#aebfda!important}
.osh-top-item:hover,.osh-top-social a:hover{color:#aebfda!important}
.osh-nav>li>a::after{background:#aebfda!important}
.osh-nav>li:hover>a,.osh-nav>li.active>a{color:#aebfda!important}
.osh-dropdown{border-top-color:#aebfda!important}
.osh-dropdown li a::before{background:#aebfda!important}
.osh-dropdown li a:hover{color:#fff!important;border-right-color:#aebfda!important}
body.lang-en .osh-dropdown li a:hover{border-left-color:#aebfda!important}
.osh-mob-nav>li>a:hover{color:#aebfda!important}
.osh-mob-sub li a::before{background:#aebfda!important}
.osh-mob-sub li a:hover,.osh-mob-contact a svg{color:#aebfda!important}
.osh-mob-contact a svg{stroke:#aebfda!important}
#oql-trigger{border-color:#0d1f3c!important}
/* Footer (navy) */
#osoul-footer::before{background:linear-gradient(90deg,#0d1f3c,#aebfda,#0d1f3c)!important}
.osf-name-en,.osf-bottom-brand b{color:#aebfda!important}
.osf-heading::after{background:linear-gradient(90deg,rgba(174,191,218,.5),transparent)!important}
body.lang-en .osf-heading::after{background:linear-gradient(270deg,rgba(174,191,218,.5),transparent)!important}
.osf-links a::before{background:#aebfda!important}
.osf-links a:hover,.osf-contact a:hover,.osf-bottom-links a:hover{color:#fff!important}
.osf-contact-ic{background:rgba(174,191,218,.12)!important;border-color:rgba(174,191,218,.25)!important}
.osf-contact-ic svg{stroke:#aebfda!important}
.osf-social a:hover{background:#0d1f3c!important;border-color:#aebfda!important;color:#fff!important}
.osf-divider{background:linear-gradient(90deg,transparent,rgba(174,191,218,.3) 20%,rgba(174,191,218,.3) 80%,transparent)!important}

/* ===== Primary CTAs stay RED (untouched). Floating lang active → navy ===== */
.olf-btn.active,.osh-lang-btn.active,.osh-mob-lang-btn.active{background:#0d1f3c!important}
</style>
		<?php
	}
}
