<?php
/**
 * Employee webmail — AI email assistant (OpenAI).
 *
 * A deliberately *narrow* agent: it only drafts, replies to, improves,
 * shortens or translates business emails. Anything outside that scope is
 * politely refused by the system prompt. One company OpenAI key (stored
 * encrypted, admin-managed — see employees.php) is used server-side for every
 * employee, so the key never reaches the browser.
 *
 *   draft   : a short instruction  → a complete, ready-to-send email
 *   improve : the current draft    → a polished, professional rewrite
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 *  Route
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'osoul/v1', '/mail/ai', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'osoul_rest_mail_ai',
		'permission_callback' => 'osoul_webmail_perm',
	) );
} );

/* -------------------------------------------------------------------------
 *  Handler
 * ---------------------------------------------------------------------- */

function osoul_rest_mail_ai( WP_REST_Request $req ) {
	if ( ! function_exists( 'osoul_ai_is_ready' ) || ! osoul_ai_is_ready() ) {
		return new WP_Error( 'osoul_ai_off', 'ميزة الذكاء الاصطناعي غير مُفعّلة بعد — اطلب من المسؤول إدخال مفتاح OpenAI من لوحة التحكم.', array( 'status' => 400 ) );
	}

	$mode    = ( 'improve' === $req->get_param( 'mode' ) ) ? 'improve' : 'draft';
	$text    = trim( (string) $req->get_param( 'text' ) );
	$subject = sanitize_text_field( (string) $req->get_param( 'subject' ) );

	if ( '' === $text ) {
		return new WP_Error(
			'osoul_ai_empty',
			( 'improve' === $mode ) ? 'اكتب نص الرسالة أولاً حتى أحسّنه.' : 'اكتب فكرة أو طلباً قصيراً أولاً.',
			array( 'status' => 422 )
		);
	}
	// Guard against runaway token usage.
	if ( strlen( $text ) > 8000 ) { $text = substr( $text, 0, 8000 ); }

	// Light per-user throttle to stop accidental double-submits.
	$uid = get_current_user_id();
	$rl  = 'osoul_ai_rl_' . $uid;
	if ( get_transient( $rl ) ) {
		return new WP_Error( 'osoul_ai_rate', 'انتظر لحظة ثم حاول مجدداً.', array( 'status' => 429 ) );
	}
	set_transient( $rl, 1, 3 );

	$out = osoul_ai_generate( $mode, $text, array( 'subject' => $subject ) );
	if ( is_wp_error( $out ) ) {
		return new WP_Error( $out->get_error_code(), $out->get_error_message(), array( 'status' => 400 ) );
	}

	$body = (string) $out['body'];
	return rest_ensure_response( array(
		'ok'      => true,
		'mode'    => $mode,
		'subject' => (string) ( $out['subject'] ?? '' ),
		'html'    => osoul_ai_text_to_html( $body ),
		'text'    => $body,
	) );
}

/* -------------------------------------------------------------------------
 *  Engine
 * ---------------------------------------------------------------------- */

/**
 * Ask OpenAI to draft or improve an email.
 *
 * @param string $mode  'draft' | 'improve'
 * @param string $input instruction (draft) or existing body text (improve)
 * @param array  $opts  { subject? }
 * @return array|WP_Error  { subject, body }
 */
function osoul_ai_generate( $mode, $input, $opts = array() ) {
	$key = osoul_ai_key();
	if ( '' === $key ) {
		return new WP_Error( 'osoul_ai_off', 'مفتاح الذكاء الاصطناعي غير مُدخل.' );
	}
	$model = osoul_ai_model();

	if ( 'improve' === $mode ) {
		$system = "You are the professional email-writing assistant for employees of Osoul Albinaa Industrial Company.\n"
			. "Rewrite and improve the email text the user gives you so it reads clearly, is well structured, polite and professional in a business register.\n"
			. "Rules:\n"
			. "- Keep the SAME language as the input (Arabic stays Modern Standard Arabic; English stays English).\n"
			. "- Keep the SAME core intent and every fact the user provided. Never invent names, numbers, prices or dates.\n"
			. "- You only ever produce professional email content. If the input is clearly not an email or a message, reply with one short polite sentence, in the input's language, saying you only help with writing emails.\n"
			. "- Output ONLY the improved email body: no 'Subject:' line, no markdown, no code fences, no notes or commentary.";
		$user = $input;
	} else {
		$system = "You are the professional email-writing assistant for employees of Osoul Albinaa Industrial Company.\n"
			. "Your ONLY job is to write, reply to, translate, shorten or improve professional business emails.\n"
			. "If the user's request is NOT about composing an email, do not comply — answer with exactly one short polite sentence, in the user's language, saying you only help with writing emails.\n"
			. "Rules:\n"
			. "- Write in the SAME language as the user's request (Arabic → Modern Standard Arabic; English → English).\n"
			. "- Produce a complete, ready-to-send business email: a suitable greeting, a clear well-structured body, and a professional closing.\n"
			. "- Do NOT invent specific facts (names, prices, dates, figures) the user did not give. Use a neutral placeholder only when truly unavoidable.\n"
			. "- No markdown, no code fences, no commentary.\n"
			. "- Output format EXACTLY: the first line must be 'SUBJECT: <a concise subject>', then one blank line, then the email body.";
		$user = $input;
		if ( '' !== (string) ( $opts['subject'] ?? '' ) ) {
			$user .= "\n\n(For context, the subject box currently says: \"" . $opts['subject'] . "\". Improve it if useful.)";
		}
	}

	$system = (string) apply_filters( 'osoul_ai_system_prompt', $system, $mode );

	$payload = array(
		'model'       => $model,
		'messages'    => array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user',   'content' => $user ),
		),
		'temperature' => 0.5,
		'max_tokens'  => 1200,
	);

	$res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
		'timeout' => 45,
		'headers' => array(
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
		),
		'body'    => wp_json_encode( $payload ),
	) );

	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'osoul_ai_net', 'تعذّر الاتصال بخدمة الذكاء الاصطناعي: ' . $res->get_error_message() );
	}

	$code = (int) wp_remote_retrieve_response_code( $res );
	$raw  = (string) wp_remote_retrieve_body( $res );
	$json = json_decode( $raw, true );

	if ( 200 !== $code ) {
		if ( 401 === $code ) {
			return new WP_Error( 'osoul_ai_key', 'مفتاح OpenAI غير صحيح أو منتهي — راجع المسؤول.' );
		}
		if ( 429 === $code ) {
			return new WP_Error( 'osoul_ai_quota', 'تم تجاوز حد الاستخدام في OpenAI حالياً، حاول لاحقاً.' );
		}
		$msg = ( is_array( $json ) && isset( $json['error']['message'] ) ) ? $json['error']['message'] : ( 'رمز الخطأ ' . $code );
		return new WP_Error( 'osoul_ai_api', 'خدمة الذكاء الاصطناعي ردّت بخطأ: ' . $msg );
	}

	$content = '';
	if ( is_array( $json ) && isset( $json['choices'][0]['message']['content'] ) ) {
		$content = (string) $json['choices'][0]['message']['content'];
	}
	$content = trim( $content );
	if ( '' === $content ) {
		return new WP_Error( 'osoul_ai_empty', 'لم أتمكّن من توليد نص. حاول مرة أخرى بصياغة أوضح.' );
	}

	return osoul_ai_split_subject( $content, ( 'improve' === $mode ) );
}

/**
 * Split a "SUBJECT: …\n\nbody" reply into { subject, body }.
 * In improve mode we never expect a subject line.
 *
 * @param string $content
 * @param bool   $improve
 * @return array { subject, body }
 */
function osoul_ai_split_subject( $content, $improve = false ) {
	$content = str_replace( array( "\r\n", "\r" ), "\n", (string) $content );
	$subject = '';
	$body    = $content;
	if ( ! $improve && preg_match( '/^\s*(?:subject|الموضوع)\s*[:：]\s*(.+?)\n(.*)$/is', $content, $m ) ) {
		$subject = trim( $m[1] );
		$body    = ltrim( $m[2] );
	}
	// Strip any stray markdown code fences the model may add.
	$body = trim( preg_replace( '/^```[a-zA-Z]*\n?|\n?```$/m', '', $body ) );
	return array( 'subject' => $subject, 'body' => $body );
}

/**
 * Convert the model's plain-text email into safe HTML for the contenteditable
 * compose editor (paragraphs on blank lines, <br> on single newlines).
 *
 * @param string $text
 * @return string
 */
function osoul_ai_text_to_html( $text ) {
	$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	$text = trim( $text );
	if ( '' === $text ) { return '<p></p>'; }
	$out = '';
	foreach ( preg_split( '/\n{2,}/', $text ) as $para ) {
		$out .= '<p dir="auto">' . nl2br( esc_html( $para ) ) . '</p>';
	}
	return $out;
}
