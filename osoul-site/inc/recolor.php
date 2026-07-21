<?php
/**
 * Palette hierarchy — enforce a 3-tier blue system across the site.
 *
 * Decorative elements (tags, lines, headings, icons, badges) are pinned to the
 * PRIMARY brand blue so the brighter accent stays reserved for real CTAs:
 *   - primary blue (#005784) on light backgrounds,
 *   - soft steel (#7CB6D6) on dark backgrounds (so it stays readable).
 * Primary CTA buttons keep the bright accent blue (#0074A4) from the base styles.
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
/* ===== Decorative → PRIMARY blue (light backgrounds) ===== */
.ohp-sec-tag{color:#005784!important}
.ohp-sec-tag::after{background:linear-gradient(90deg,rgba(0,52,79,.25),transparent)!important}
.ohp-about-h i,.ohp-sec-h2 i,.osrv-cta-h i,.opr-h1 i,.opdtl-related h2 i{color:#005784!important}
.ohp-about-pt svg{stroke:#005784!important}
.opp-card-cat,.odps-card-sub,.osrv-card-cta,.obls-read-more,.opg-stat-n{color:#005784!important}
.opp-card-badge,.ohp-pm-cat,.opg-card-cat,.obls-card-cat{background:#005784!important;color:#fff!important}
.osrv-card-h,.osrv-cta-h{color:#005784!important}
.osrv-card::before{background:linear-gradient(90deg,transparent,#005784,transparent)!important}
.osrv-ic{background:rgba(0,52,79,.06)!important;border-color:rgba(0,52,79,.18)!important}
.osrv-ic svg{stroke:#005784!important}
.osrv-card:hover .osrv-ic{background:#005784!important;border-color:#005784!important}
.osrv-card:hover .osrv-ic svg{stroke:#fff!important}
.ohp-clients-tag,.ohp-clients-h i{color:#005784!important}
.ohp-pm-loc svg,.opg-card-meta svg,.obls-card-meta svg,.oct-info-ic svg{stroke:#005784!important}
.opg-card-year::before{background:rgba(0,52,79,.4)!important}
.oct-info-ic{background:rgba(0,52,79,.06)!important;border-color:rgba(0,52,79,.18)!important}
.oct-info-txt a:hover,.oct-social a:hover{color:#005784!important}
.oct-social a:hover{background:#005784!important;border-color:#005784!important;color:#fff!important}
.opr-section h2{border-color:#005784!important}
.opdtl-cert{background:rgba(0,52,79,.05)!important;border-color:rgba(0,52,79,.18)!important;color:#005784!important}
.opp-card:hover,.odps-card:hover,.osrv-card:hover,.opg-card:hover,.obls-card:hover{border-color:rgba(0,52,79,.12)!important}
/* About — light sections */
.oab-sec-tag,.oab-sec-tag::before{color:#005784!important}
.oab-sec-tag::before{background:#005784!important}
.oab-sec-h em{color:#005784!important}
.oab-v-card{border-top-color:#005784!important}
.oab-step::before{background:#005784!important}
.oab-step-icon{color:#005784!important;border-color:rgba(0,52,79,.2)!important}
.oab-cert-sub{color:rgba(0,52,79,.45)!important}

/* ===== Decorative → SOFT STEEL (dark backgrounds) ===== */
/* Home hero */
#ohero .tag{color:#7CB6D6!important}
#ohero .tag::before{background:#7CB6D6!important}
#ohero h1 i{color:#7CB6D6!important}
#ohero .sn{background:none!important;-webkit-text-fill-color:#7CB6D6!important;color:#7CB6D6!important}
#ohero .st{border-left-color:rgba(124,182,214,.2)!important;border-right-color:rgba(124,182,214,.2)!important}
/* Industry cards (dark imagery) */
.ohp-ind-num{color:rgba(124,182,214,.12)!important}
.ohp-ind-tag{color:#7CB6D6!important;border-color:rgba(124,182,214,.4)!important}
.ohp-ind-ic{border-color:rgba(124,182,214,.35)!important;background:rgba(124,182,214,.1)!important}
.ohp-ind-ic svg{stroke:#7CB6D6!important}
.ohp-ind-card:hover .ohp-ind-ic{background:#7CB6D6!important;border-color:#7CB6D6!important}
.ohp-ind-card:hover .ohp-ind-ic svg{stroke:#005784!important}
.ohp-ind-link{color:#7CB6D6!important;border-color:rgba(124,182,214,.5)!important}
.ohp-ind-link:hover{background:#7CB6D6!important;color:#005784!important;border-color:#7CB6D6!important}
/* Featured project (navy card) */
.ohp-pf-cat,.ohp-pf-h i{color:#7CB6D6!important}
.ohp-pf-mi svg{stroke:#7CB6D6!important}
.ohp-pf-badge{background:#7CB6D6!important;color:#005784!important}
/* Why-us (navy) */
.ohp-why::after{background:linear-gradient(90deg,transparent,#7CB6D6,transparent)!important}
.ohp-why h2 i,.ohp-why .ohp-sec-tag{color:#7CB6D6!important}
.ohp-why-icon{border-color:rgba(124,182,214,.4)!important;background:rgba(124,182,214,.12)!important}
.ohp-why-icon svg{stroke:#7CB6D6!important}
.ohp-why-item:hover .ohp-why-icon{background:#7CB6D6!important;border-color:#7CB6D6!important;box-shadow:0 0 24px rgba(124,182,214,.25)!important}
.ohp-why-item:hover .ohp-why-icon svg{stroke:#005784!important}
/* Page heroes (navy) — top line, tags, italic headings */
.opp-hero::before,.odp-hero::before,.odps-hero::before,.opdtl-hero::before,.osrv-hero::before,.osi-hero::before,.opg-hero::before,.obl-hero::before,.obls-hero::before,.oct-hero::before,.opr-hero::before,.oab-hero::before,.osrv-cta-bar::after{background:linear-gradient(90deg,transparent,#7CB6D6,transparent)!important}
.opp-tag,.odp-tag,.osrv-tag,.osi-tag,.opg-tag,.oct-tag,.opr-tag,.opdtl-cat-tag,.osi-tag a{color:#7CB6D6!important}
.opp-tag::before,.odp-tag::before,.osrv-tag::before,.osi-tag::before,.opg-tag::before,.oct-tag::before,.opr-tag::before{background:#7CB6D6!important}
.opp-h1 i,.odp-h1 i,.osrv-h1 i,.osi-h1 i,.opg-h1 i,.oct-h1 i,.opdtl-h1 i,.odps-hero h1 i,.obls-h1 i{color:#7CB6D6!important}
.opdtl-breadcrumb a:hover{color:#7CB6D6!important}
/* About — dark sections */
.oab-tag,.oab-tag::before{color:#7CB6D6!important}
.oab-tag::before{background:#7CB6D6!important}
.oab-h1 em,.oab-ceo-q,.oab-ceo-name + .oab-ceo-title{color:#7CB6D6!important}
.oab-ceo-tag,.oab-ceo-title,.oab-ceo-ctitle,.oab-meta-l{color:#7CB6D6!important}
.oab-ceo::after{background:#7CB6D6!important}
.oab-m-card{border-top-color:#7CB6D6!important}
/* Header (navy) */
#osoul-header .osh-top{border-bottom-color:rgba(124,182,214,.2)!important}
#osoul-header .osh-main{border-bottom-color:rgba(124,182,214,.25)!important}
.osh-top-item svg{stroke:#7CB6D6!important}
.osh-top-item:hover,.osh-top-social a:hover{color:#7CB6D6!important}
.osh-nav>li>a::after{background:#7CB6D6!important}
.osh-nav>li:hover>a,.osh-nav>li.active>a{color:#7CB6D6!important}
.osh-dropdown{border-top-color:#7CB6D6!important}
.osh-dropdown li a::before{background:#7CB6D6!important}
.osh-dropdown li a:hover{color:#fff!important;border-right-color:#7CB6D6!important}
body.lang-en .osh-dropdown li a:hover{border-left-color:#7CB6D6!important}
.osh-mob-nav>li>a:hover{color:#7CB6D6!important}
.osh-mob-sub li a::before{background:#7CB6D6!important}
.osh-mob-sub li a:hover,.osh-mob-contact a svg{color:#7CB6D6!important}
.osh-mob-contact a svg{stroke:#7CB6D6!important}
#oql-trigger{border-color:#005784!important}
/* Footer (navy) */
#osoul-footer::before{background:linear-gradient(90deg,#005784,#7CB6D6,#005784)!important}
.osf-name-en,.osf-bottom-brand b{color:#7CB6D6!important}
.osf-heading::after{background:linear-gradient(90deg,rgba(124,182,214,.5),transparent)!important}
body.lang-en .osf-heading::after{background:linear-gradient(270deg,rgba(124,182,214,.5),transparent)!important}
.osf-links a::before{background:#7CB6D6!important}
.osf-links a:hover,.osf-contact a:hover,.osf-bottom-links a:hover{color:#fff!important}
.osf-contact-ic{background:rgba(124,182,214,.12)!important;border-color:rgba(124,182,214,.25)!important}
.osf-contact-ic svg{stroke:#7CB6D6!important}
.osf-social a:hover{background:#005784!important;border-color:#7CB6D6!important;color:#fff!important}
.osf-divider{background:linear-gradient(90deg,transparent,rgba(124,182,214,.3) 20%,rgba(124,182,214,.3) 80%,transparent)!important}

/* ===== Primary CTAs keep the bright accent blue. Floating lang active → primary ===== */
.olf-btn.active,.osh-lang-btn.active,.osh-mob-lang-btn.active{background:#005784!important}

/* ===== Parallelogram product buttons (inline so an optimizer/CDN cache of the
   external CSS can never strip the slant). The skew is on a ::before layer and
   the labels are lifted with position:relative so the text stays upright. ===== */
.opp-card-quote,.opp-card-detail{position:relative!important;background:transparent!important;border:none!important;overflow:visible!important}
.opp-card-quote::before,.opp-card-detail::before{content:''!important;position:absolute!important;inset:0!important;transform:skewX(-11deg)!important;border-radius:3px!important;transition:all .2s!important}
.opp-card-quote::before{background:#0074A4!important;border:none!important}
.opp-card-quote:hover::before{background:#0089BE!important}
.opp-card-detail::before{background:transparent!important;border:1.5px solid rgba(0,52,79,.2)!important}
.opp-card-detail:hover::before{background:#00344F!important;border-color:#00344F!important}
.opp-card-detail:hover{color:#fff!important}
.opp-card-quote>span,.opp-card-detail>span{position:relative!important}
</style>
		<?php
	}
}
