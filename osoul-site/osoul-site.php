<?php
/**
 * Plugin Name:       Osoul Albinaa Site
 * Plugin URI:        https://osoulalbinaa.com
 * Description:        Front-end, bilingual (AR/EN) site layer for Osoul Albinaa Industrial Co. — header, footer, pages, product catalog, quote list and a real server-side quote/contact backend. Refactored from a single WPCode snippet into a maintainable, performance-oriented plugin.
 * Version:           2.21.8
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Osoul Albinaa
 * Text Domain:       osoul
 *
 * NOTE: This replaces the legacy ~2,500-line functions.php / WPCode snippet.
 * See README.md for the migration guide and a map of what moved where.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Guard against double-load (kept from the original WPCode protection).
if ( defined( 'OSOUL_VERSION' ) ) { return; }

define( 'OSOUL_VERSION', '2.21.8' );
define( 'OSOUL_FILE', __FILE__ );
define( 'OSOUL_DIR', plugin_dir_path( __FILE__ ) );
define( 'OSOUL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Module loader.
 *
 * Order matters: helpers + catalog provide data the rest depend on,
 * security hardens request globals before anything reads them.
 */
$osoul_modules = array(
	'inc/helpers.php',        // shared utilities (secure URL, slug parsing, catalog access)
	'inc/settings.php',       // admin-managed contact info (osoul_opt) + settings page
	'inc/security.php',       // request hardening, XML-RPC, generator removal
	'inc/catalog.php',        // built-in product catalog defaults (seed data)
	'inc/products-cpt.php',   // product post type, DB-backed catalog, seeder
	'inc/products-admin.php', // custom "Site Products" dashboard page
	'inc/quotes-cpt.php',     // quotation data layer (CPT, agent role, helpers)
	'inc/quotes-rfq.php',     // customer quote-request submission
	'inc/quotes-dashboard.php', // public /quote route + standalone-page chrome
	'inc/quotes-public.php',  // public /quote/{token} page (bilingual, print, accept)
	'inc/portal-roles.php',   // B2B portal: branch + rep roles, accounts, secure invites
	'inc/portal-rest.php',    // B2B portal: REST API (quotes, stats, products, accounts)
	'inc/portal-chat.php',    // B2B portal: live chat, profile, image uploads
	'inc/portal-app.php',     // B2B portal: /dashboard SPA (admin / branch / rep)
	'inc/employees.php',      // Employee webmail: role, admin manager, credential vault
	'inc/mail-engine.php',    // Employee webmail: self-contained IMAP client + MIME + SMTP
	'inc/webmail-rest.php',   // Employee webmail: mailbox REST API (osoul/v1/mail/*)
	'inc/webmail-app.php',    // Employee webmail: /dashboard mail client (rendered for employees)
	'inc/partners.php',       // Partner Portal: white-label reseller data layer
	'inc/partners-app.php',   // Partner Portal: registration, dashboard, admin mgmt
	'inc/datasheet.php',      // white-label product catalog / datasheet (print-to-PDF)
	'inc/customers.php',      // website customer accounts (register/login/Google) + quote gate
	'inc/admin-data.php',     // admin Excel exports + quote archive (soft-delete)
	'inc/contacts.php',       // Customer 360 profiles + unified timeline (CRM)
	'inc/pages.php',          // create/clean up the required WP pages
	'inc/performance.php',    // asset enqueue, dequeue, resource hints, emoji removal
	'inc/i18n.php',           // language switching system (front-end)
	'inc/seo.php',            // meta, canonical, hreflang, schema.org
	'inc/favicon.php',        // favicon / site icon head tags + /favicon.ico (Google visibility)
	'inc/forms.php',          // NEW: server-side quote/contact submissions (CPT + email)
	'inc/header.php',         // site header markup
	'inc/footer.php',         // site footer + floating widgets
	'inc/templates.php',      // page-template renderers (home, about, products, …)
	'inc/recolor.php',        // palette hierarchy: blue tiers (decorative vs CTA)
);

foreach ( $osoul_modules as $osoul_module ) {
	$osoul_path = OSOUL_DIR . $osoul_module;
	if ( is_readable( $osoul_path ) ) {
		require_once $osoul_path;
	}
}
unset( $osoul_modules, $osoul_module, $osoul_path );

/**
 * Activation: ensure pages exist and rewrite rules are fresh.
 */
register_activation_hook( __FILE__, function () {
	if ( function_exists( 'osoul_create_missing_pages' ) ) {
		osoul_create_missing_pages( true );
	}
	if ( function_exists( 'osoul_seed_products' ) ) {
		osoul_seed_products();
	}
	if ( function_exists( 'osoul_register_quote_role' ) ) {
		osoul_register_quote_role();
	}
	if ( function_exists( 'osoul_portal_register_roles' ) ) {
		osoul_portal_register_roles();
	}
	if ( function_exists( 'osoul_employee_register_role' ) ) {
		osoul_employee_register_role();
	}
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
