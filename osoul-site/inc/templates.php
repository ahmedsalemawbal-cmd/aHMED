<?php
/**
 * Page-template loader.
 *
 * Each page of the site is rendered by a function hooked to wp_footer that
 * injects its markup into position (the legacy "move the DOM node" technique
 * is preserved). The renderers are grouped into sub-files:
 *
 *   templates/products.php  — products portal, the 3 archive pages, product detail
 *   templates/sections.php  — services, single service, projects, blog, contact, privacy
 *   templates/home.php       — home page + about page (full-bleed body-insert pages)
 *
 * Page-specific CSS stays inline in each renderer because it only loads on that
 * one page; the always-on global CSS already moved to assets/css/osoul.css.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once OSOUL_DIR . 'inc/templates/products.php';
require_once OSOUL_DIR . 'inc/templates/sections.php';
require_once OSOUL_DIR . 'inc/templates/home.php';

/**
 * Shared helper: the small JS that lifts a rendered block out of the theme's
 * content area and places it right after the fixed header, then hides every
 * other top-level node. Returns the script body (without <script> tags).
 *
 * @param string $wrap_id The element id to promote.
 * @return string
 */
function osoul_promote_script( $wrap_id ) {
	$wrap_id = esc_js( $wrap_id );
	return "(function(){\n"
		. "var el=document.getElementById('" . $wrap_id . "');\n"
		. "if(!el)return;\n"
		. "var hdr=document.getElementById('osoul-header');\n"
		. "if(hdr){hdr.insertAdjacentElement('afterend',el);}\n"
		. "else{var ft=document.getElementById('osoul-footer');if(ft)document.body.insertBefore(el,ft);}\n"
		. "var k=['osoul-header','osoul-footer','" . $wrap_id . "','oql-drawer','oql-overlay','osoul-cookie','osh-mobile-menu','osh-overlay','osoul-lang-float','cert-lightbox-overlay'];\n"
		. "document.body.childNodes.forEach(function(n){if(n.nodeType===1&&!k.includes(n.id)){n.style.setProperty('display','none','important');}});\n"
		. "})();";
}
