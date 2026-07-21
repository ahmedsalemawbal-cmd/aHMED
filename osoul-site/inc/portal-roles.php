<?php
/**
 * B2B portal — roles, capabilities, accounts and the secure invite flow.
 *
 * Three actors share the /dashboard portal, each with a different view:
 *   - admin   (cap manage_osoul_quotes)  → the single manager: prices every
 *             request, manages branches + reps, sees all statistics.
 *   - branch  (role osoul_branch)        → a regional branch account: registers
 *             its own reps, requests quotes, sees its branch statistics.
 *   - rep     (role osoul_rep)           → a sales rep: browses products WITHOUT
 *             prices, submits requests on behalf of an end customer, tracks them.
 *
 * Branches and reps are real WordPress users (passwords hashed by WP). They are
 * never given a password in code: the admin/branch creates the account, the
 * system returns a one-time invite link, and the person sets their own password.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Capability markers for the portal roles. */
function osoul_branch_cap()  { return 'osoul_branch_portal'; }
function osoul_rep_cap()     { return 'osoul_rep_portal'; }
function osoul_partner_cap() { return 'osoul_partner_portal'; }

/**
 * Register the branch + rep roles. Idempotent; safe to call repeatedly.
 */
function osoul_portal_register_roles() {
	add_role( 'osoul_branch', 'فرع أصول البناء', array(
		'read'                 => true,
		'upload_files'         => true, // profile photo + chat images
		osoul_branch_cap()     => true,
	) );
	add_role( 'osoul_rep', 'مندوب مبيعات', array(
		'read'              => true,
		'upload_files'      => true, // profile photo + chat images
		osoul_rep_cap()     => true,
	) );
	add_role( 'osoul_partner', 'شريك أصول البناء', array(
		'read'                => true,
		'upload_files'        => true, // partner logo + attachments
		osoul_partner_cap()   => true,
	) );
	if ( function_exists( 'osoul_customer_cap' ) ) {
		add_role( 'osoul_customer', 'عميل الموقع', array(
			'read'                => true,
			osoul_customer_cap()  => true,
		) );
	}

	// Make sure existing roles keep their capabilities after a plugin update.
	foreach ( array( 'osoul_branch' => osoul_branch_cap(), 'osoul_rep' => osoul_rep_cap(), 'osoul_partner' => osoul_partner_cap() ) as $rk => $cap ) {
		$role = get_role( $rk );
		if ( $role ) {
			if ( ! $role->has_cap( $cap ) ) { $role->add_cap( $cap ); }
			if ( ! $role->has_cap( 'upload_files' ) ) { $role->add_cap( 'upload_files' ); }
		}
	}
}
add_action( 'init', function () {
	$rep = get_role( 'osoul_rep' );
	if ( ! $rep || ! get_role( 'osoul_branch' ) || ! get_role( 'osoul_partner' ) || ! get_role( 'osoul_customer' ) || ! $rep->has_cap( 'upload_files' ) ) {
		osoul_portal_register_roles();
	}
}, 6 );

/**
 * Resolve the portal role for a user.
 *
 * @param int|WP_User|null $user
 * @return string 'admin'|'branch'|'rep'|''
 */
function osoul_portal_role( $user = null ) {
	$user = $user ? ( $user instanceof WP_User ? $user : get_user_by( 'id', (int) $user ) ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return '';
	}
	if ( user_can( $user, osoul_quotes_cap() ) )  { return 'admin'; }
	if ( user_can( $user, osoul_branch_cap() ) )  { return 'branch'; }
	if ( user_can( $user, osoul_rep_cap() ) )     { return 'rep'; }
	if ( user_can( $user, osoul_partner_cap() ) ) { return 'partner'; }
	return '';
}

/** True when the current (or given) user may use the portal at all. */
function osoul_portal_can_access( $user = null ) {
	return '' !== osoul_portal_role( $user );
}

/** Localised label for a portal role. */
function osoul_portal_role_label( $role ) {
	$map = array(
		'admin'   => 'المدير',
		'branch'  => 'فرع',
		'rep'     => 'مندوب',
		'partner' => 'شريك',
	);
	return $map[ $role ] ?? '';
}

/** English label for a portal role (used on the bilingual invite screen). */
function osoul_portal_role_label_en( $role ) {
	$map = array(
		'admin'   => 'Administrator',
		'branch'  => 'Branch',
		'rep'     => 'Sales Rep',
		'partner' => 'Partner',
	);
	return $map[ $role ] ?? '';
}

/** Urdu label for a portal role (used on the trilingual invite screen). */
function osoul_portal_role_label_ur( $role ) {
	$map = array(
		'admin'   => 'ایڈمنسٹریٹر',
		'branch'  => 'برانچ',
		'rep'     => 'سیلز نمائندہ',
		'partner' => 'پارٹنر',
	);
	return $map[ $role ] ?? '';
}

/* -------------------------------------------------------------------------
 *  Account creation + invite links
 * ---------------------------------------------------------------------- */

/**
 * Create a branch or rep account and return its one-time invite URL.
 *
 * No password is set here: the user is created with a long random password and
 * a short-lived invite token; they choose a real password through the link.
 *
 * @param array $args {
 *   @type string $role        'branch'|'rep'  (required)
 *   @type string $name        Display name    (required)
 *   @type string $email       Login email     (required, unique)
 *   @type string $phone       Contact phone
 *   @type string $city        Branch city           (branch only)
 *   @type string $manager     Branch manager name   (branch only)
 *   @type int    $branch_id   Parent branch user id (rep only; 0 = independent)
 * }
 * @param int $created_by      User id of the creator (for branch-scoping reps).
 * @return array|WP_Error  array{ user_id:int, invite_url:string }
 */
function osoul_portal_create_account( $args, $created_by = 0 ) {
	$role  = ( 'branch' === ( $args['role'] ?? '' ) ) ? 'branch' : 'rep';
	$name  = sanitize_text_field( $args['name'] ?? '' );
	$email = sanitize_email( $args['email'] ?? '' );
	$phone = sanitize_text_field( $args['phone'] ?? '' );

	if ( '' === $name ) {
		return new WP_Error( 'osoul_no_name', 'الاسم مطلوب.' );
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'osoul_bad_email', 'بريد إلكتروني غير صحيح.' );
	}
	if ( email_exists( $email ) || username_exists( $email ) ) {
		return new WP_Error( 'osoul_dupe', 'هذا البريد مسجّل مسبقاً.' );
	}

	$user_id = wp_insert_user( array(
		'user_login'   => $email,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 40, true, true ),
		'display_name' => $name,
		'nickname'     => $name,
		'role'         => ( 'branch' === $role ) ? 'osoul_branch' : 'osoul_rep',
	) );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	update_user_meta( $user_id, '_osoul_phone', $phone );
	update_user_meta( $user_id, '_osoul_active', 1 );
	update_user_meta( $user_id, '_osoul_created_by', (int) $created_by );

	if ( 'branch' === $role ) {
		update_user_meta( $user_id, '_osoul_city', sanitize_text_field( $args['city'] ?? '' ) );
		update_user_meta( $user_id, '_osoul_manager', sanitize_text_field( $args['manager'] ?? '' ) );
	} else {
		// A rep belongs to a branch (the creating branch, or one chosen by admin),
		// or 0 when the admin creates an independent rep.
		$branch_id = (int) ( $args['branch_id'] ?? 0 );
		if ( $branch_id && 'branch' !== osoul_portal_role( $branch_id ) ) {
			$branch_id = 0;
		}
		update_user_meta( $user_id, '_osoul_rep_branch', $branch_id );
	}

	$token = osoul_portal_new_invite( $user_id );

	return array(
		'user_id'    => $user_id,
		'invite_url' => osoul_portal_invite_url( $token ),
	);
}

/** Generate + store a fresh invite token (7-day expiry) for a user. */
function osoul_portal_new_invite( $user_id ) {
	$token = function_exists( 'random_bytes' ) ? bin2hex( random_bytes( 20 ) ) : wp_generate_password( 40, false, false );
	update_user_meta( $user_id, '_osoul_invite', $token );
	update_user_meta( $user_id, '_osoul_invite_exp', time() + 7 * DAY_IN_SECONDS );
	return $token;
}

/** Build the invite URL the new user opens to set their password. */
function osoul_portal_invite_url( $token ) {
	return add_query_arg( 'invite', rawurlencode( $token ), home_url( '/dashboard/' ) );
}

/**
 * Find the user a (valid, unexpired) invite token belongs to.
 *
 * @param string $token
 * @return WP_User|null
 */
function osoul_portal_find_invite( $token ) {
	$token = preg_replace( '/[^a-f0-9]/', '', (string) $token );
	if ( strlen( $token ) < 20 ) {
		return null;
	}
	$users = get_users( array(
		'meta_key'   => '_osoul_invite',
		'meta_value' => $token,
		'number'     => 1,
		'fields'     => array( 'ID' ),
	) );
	if ( ! $users ) {
		return null;
	}
	$user_id = (int) $users[0]->ID;
	$exp     = (int) get_user_meta( $user_id, '_osoul_invite_exp', true );
	if ( $exp && $exp < time() ) {
		return null;
	}
	return get_user_by( 'id', $user_id );
}

/** Consume the invite, set the chosen password, activate the account. */
function osoul_portal_accept_invite( $user_id, $password ) {
	$user_id = (int) $user_id;
	wp_set_password( $password, $user_id );
	delete_user_meta( $user_id, '_osoul_invite' );
	delete_user_meta( $user_id, '_osoul_invite_exp' );
	update_user_meta( $user_id, '_osoul_active', 1 );
}

/* -------------------------------------------------------------------------
 *  Relationships + attribution
 * ---------------------------------------------------------------------- */

/** The branch a rep belongs to (0 = independent / admin-managed). */
function osoul_rep_branch_id( $user_id ) {
	return (int) get_user_meta( (int) $user_id, '_osoul_rep_branch', true );
}

/** Whether a portal account is active (not suspended by the admin/branch). */
function osoul_portal_is_active( $user_id ) {
	$v = get_user_meta( (int) $user_id, '_osoul_active', true );
	return ( '' === $v ) ? true : (bool) $v;
}

/** Ids of every rep that belongs to a branch. */
function osoul_branch_rep_ids( $branch_id ) {
	$reps = get_users( array(
		'role'       => 'osoul_rep',
		'meta_key'   => '_osoul_rep_branch',
		'meta_value' => (int) $branch_id,
		'fields'     => array( 'ID' ),
	) );
	return array_map( static function ( $u ) { return (int) $u->ID; }, $reps );
}

/**
 * Stamp a quote with its origin (web / rep / branch) and the responsible
 * rep + branch ids, so the admin can filter and branches/reps see only theirs.
 *
 * @param int          $quote_id
 * @param WP_User|null $actor    The logged-in rep/branch, or null for the website.
 */
function osoul_quote_set_attribution( $quote_id, $actor = null ) {
	if ( ! $actor || ! $actor->exists() ) {
		update_post_meta( $quote_id, '_osoul_source', 'web' );
		update_post_meta( $quote_id, '_osoul_rep_id', 0 );
		update_post_meta( $quote_id, '_osoul_branch_id', 0 );
		return;
	}
	$role = osoul_portal_role( $actor );
	if ( 'rep' === $role ) {
		update_post_meta( $quote_id, '_osoul_source', 'rep' );
		update_post_meta( $quote_id, '_osoul_rep_id', $actor->ID );
		update_post_meta( $quote_id, '_osoul_branch_id', osoul_rep_branch_id( $actor->ID ) );
	} elseif ( 'branch' === $role ) {
		update_post_meta( $quote_id, '_osoul_source', 'branch' );
		update_post_meta( $quote_id, '_osoul_rep_id', 0 );
		update_post_meta( $quote_id, '_osoul_branch_id', $actor->ID );
	} elseif ( 'partner' === $role ) {
		update_post_meta( $quote_id, '_osoul_source', 'partner' );
		update_post_meta( $quote_id, '_osoul_rep_id', 0 );
		update_post_meta( $quote_id, '_osoul_branch_id', 0 );
		update_post_meta( $quote_id, '_osoul_partner_id', $actor->ID );
	} else {
		update_post_meta( $quote_id, '_osoul_source', 'web' );
		update_post_meta( $quote_id, '_osoul_rep_id', 0 );
		update_post_meta( $quote_id, '_osoul_branch_id', 0 );
	}
}

/**
 * Can the given user see this quote?
 *   admin  → everything
 *   branch → quotes whose _osoul_branch_id is this branch (its own + its reps')
 *   rep    → quotes whose _osoul_rep_id is this rep
 */
function osoul_quote_visible_to( $quote_id, $user = null ) {
	$role = osoul_portal_role( $user );
	if ( 'admin' === $role ) {
		// Partner→customer quotes are private to the partner; Osoul never sees them.
		return 'cust' !== get_post_meta( $quote_id, '_osoul_kind', true );
	}
	$user = $user instanceof WP_User ? $user : wp_get_current_user();
	if ( 'branch' === $role ) {
		return (int) get_post_meta( $quote_id, '_osoul_branch_id', true ) === (int) $user->ID;
	}
	if ( 'rep' === $role ) {
		return (int) get_post_meta( $quote_id, '_osoul_rep_id', true ) === (int) $user->ID;
	}
	if ( 'partner' === $role ) {
		return (int) get_post_meta( $quote_id, '_osoul_partner_id', true ) === (int) $user->ID;
	}
	return false;
}

/**
 * A meta clause that excludes partner→customer quotes (kind='cust'), which are
 * private to the partner and must never surface in Osoul's views. Uses
 * OR(NOT EXISTS, != 'cust') so ordinary quotes that never set _osoul_kind still
 * match (WP '!=' alone skips rows where the meta key is absent).
 *
 * @return array
 */
function osoul_quote_exclude_cust_clause() {
	return array(
		'relation' => 'OR',
		array( 'key' => '_osoul_kind', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_osoul_kind', 'value' => 'cust', 'compare' => '!=' ),
	);
}

/**
 * The meta_query fragment that limits a WP_Query to the quotes a user may see.
 *
 * @return array  meta_query clauses.
 */
function osoul_quote_scope_meta_query( $user = null ) {
	$role = osoul_portal_role( $user );
	$user = $user instanceof WP_User ? $user : wp_get_current_user();
	if ( 'branch' === $role ) {
		return array( array( 'key' => '_osoul_branch_id', 'value' => (int) $user->ID ) );
	}
	if ( 'rep' === $role ) {
		return array( array( 'key' => '_osoul_rep_id', 'value' => (int) $user->ID ) );
	}
	if ( 'partner' === $role ) {
		return array( array( 'key' => '_osoul_partner_id', 'value' => (int) $user->ID ) );
	}
	// Admin: everything EXCEPT partner→customer quotes.
	return array( osoul_quote_exclude_cust_clause() );
}
