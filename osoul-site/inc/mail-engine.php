<?php
/**
 * Mail engine — a self-contained IMAP client + MIME parser + SMTP sender.
 *
 * Deliberately depends on NOTHING beyond core PHP (sockets + openssl + mbstring/
 * iconv) so it works on any host regardless of whether the PHP `imap` extension
 * is enabled — the same design Roundcube and other serious PHP webmail clients
 * use. Sending goes through the PHPMailer that ships with WordPress, using the
 * employee's own Hostinger SMTP credentials, and the sent copy is IMAP-APPENDed
 * to the Sent folder so it appears in every mail client.
 *
 *   Osoul_IMAP   — TLS socket IMAP client with a correct response parser
 *                  (handles literals, nested lists, quoted strings).
 *   Osoul_MIME   — RFC822/MIME parser (multipart, transfer-encodings, charsets,
 *                  attachments, inline images) with HTML made safe for preview.
 *   osoul_mail_* — high-level helpers the REST layer calls.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================================
 *  Osoul_IMAP — low-level + high-level IMAP over TLS
 * ====================================================================== */

class Osoul_IMAP {

	/** @var resource|null */
	private $sock = null;
	private $buf  = '';
	private $pos  = 0;
	private $tag  = 0;
	private $eof  = false;
	private $caps = array();
	public  $error = '';
	private $timeout;

	public function __construct( $timeout = 20 ) {
		$this->timeout = (int) $timeout;
	}

	/* ---- connection ---- */

	public function connect( $host, $port = 993, $ssl = 'ssl' ) {
		$verify  = (bool) apply_filters( 'osoul_mail_ssl_verify', true, $host );
		$ctx     = stream_context_create( array( 'ssl' => array(
			'verify_peer'       => $verify,
			'verify_peer_name'  => $verify,
			'allow_self_signed' => ! $verify,
			'SNI_enabled'       => true,
			'peer_name'         => $host,
		) ) );
		$scheme  = ( 'ssl' === $ssl ) ? 'ssl://' : 'tcp://';
		$errno   = 0; $errstr = '';
		$this->sock = @stream_socket_client(
			$scheme . $host . ':' . (int) $port,
			$errno, $errstr, $this->timeout,
			STREAM_CLIENT_CONNECT, $ctx
		);
		if ( ! $this->sock ) {
			$this->error = 'تعذّر الاتصال بخادم البريد الوارد (' . $host . ':' . (int) $port . '): ' . $errstr;
			return false;
		}
		stream_set_timeout( $this->sock, $this->timeout );
		// Read the server greeting.
		try {
			$greet = $this->readResponse();
			if ( ! $greet || strtoupper( (string) ( $greet[1] ?? '' ) ) === 'BYE' ) {
				$this->error = 'رفض خادم البريد الاتصال.';
				return false;
			}
		} catch ( Exception $e ) {
			$this->error = 'خطأ عند بدء الجلسة: ' . $e->getMessage();
			return false;
		}
		if ( 'tls' === $ssl ) {
			$r = $this->run( array( ' STARTTLS' ) );
			if ( 'OK' !== $r['status'] ) { $this->error = 'فشل STARTTLS.'; return false; }
			if ( ! @stream_socket_enable_crypto( $this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT ) ) {
				$this->error = 'فشل تشفير TLS.'; return false;
			}
		}
		return true;
	}

	public function login( $user, $pass ) {
		try {
			$r = $this->run( array( ' LOGIN ', array( 'lit' => (string) $user ), ' ', array( 'lit' => (string) $pass ) ) );
		} catch ( Exception $e ) {
			$this->error = 'خطأ أثناء تسجيل الدخول: ' . $e->getMessage();
			return false;
		}
		if ( 'OK' !== $r['status'] ) {
			$this->error = 'بيانات البريد غير صحيحة (تحقّق من البريد وكلمة المرور).';
			return false;
		}
		$this->capability();
		return true;
	}

	public function capability() {
		$r = $this->run( array( ' CAPABILITY' ) );
		foreach ( $r['untagged'] as $line ) {
			if ( isset( $line[1] ) && 'CAPABILITY' === strtoupper( (string) $line[1] ) ) {
				$this->caps = array_map( 'strtoupper', array_slice( $line, 2 ) );
			}
		}
		return $this->caps;
	}

	public function has_cap( $c ) { return in_array( strtoupper( $c ), $this->caps, true ); }

	public function logout() {
		if ( $this->sock ) {
			try { $this->run( array( ' LOGOUT' ) ); } catch ( Exception $e ) { /* ignore */ }
			@fclose( $this->sock );
			$this->sock = null;
		}
	}

	/* ---- high level ---- */

	/**
	 * List selectable folders with special-use classification + unread counts.
	 *
	 * @return array[] each { name, raw, display, special, unseen, total }
	 */
	public function folders() {
		$r   = $this->run( array( ' LIST "" "*"' ) );
		$out = array();
		foreach ( $r['untagged'] as $line ) {
			if ( ! isset( $line[1] ) || 'LIST' !== strtoupper( (string) $line[1] ) ) { continue; }
			$flags = is_array( $line[2] ) ? array_map( 'strtolower', $line[2] ) : array();
			if ( in_array( '\\noselect', $flags, true ) ) { continue; }
			$raw     = (string) ( $line[4] ?? '' );
			if ( '' === $raw ) { continue; }
			$special = $this->classify_folder( $raw, $flags );
			$out[]   = array(
				'raw'     => $raw,
				'name'    => $raw,
				'display' => $this->mutf7_decode( $raw ),
				'special' => $special,
			);
		}
		// Fetch unread + total per folder.
		foreach ( $out as &$f ) {
			$st           = $this->status( $f['raw'] );
			$f['unseen']  = $st['unseen'];
			$f['total']   = $st['messages'];
		}
		unset( $f );
		return $this->sort_folders( $out );
	}

	private function classify_folder( $raw, $flags ) {
		$map = array( '\\inbox' => 'inbox', '\\sent' => 'sent', '\\drafts' => 'drafts', '\\trash' => 'trash', '\\junk' => 'junk', '\\archive' => 'archive', '\\flagged' => 'flagged' );
		foreach ( $map as $flag => $sp ) {
			if ( in_array( $flag, $flags, true ) ) { return $sp; }
		}
		$base = strtolower( preg_replace( '#^INBOX[./]#i', '', $raw ) );
		$base = strtolower( $raw );
		if ( 'inbox' === $base ) { return 'inbox'; }
		$leaf = strtolower( (string) preg_replace( '#^.*[./]#', '', $raw ) );
		$guess = array(
			'sent' => 'sent', 'sent items' => 'sent', 'sent messages' => 'sent',
			'drafts' => 'drafts', 'draft' => 'drafts',
			'trash' => 'trash', 'deleted' => 'trash', 'deleted items' => 'trash', 'bin' => 'trash',
			'junk' => 'junk', 'spam' => 'junk', 'junk e-mail' => 'junk',
			'archive' => 'archive', 'archives' => 'archive',
		);
		return $guess[ $leaf ] ?? '';
	}

	private function sort_folders( $folders ) {
		$order = array( 'inbox' => 0, 'sent' => 1, 'drafts' => 2, 'junk' => 3, 'trash' => 4, 'archive' => 5, '' => 9 );
		usort( $folders, function ( $a, $b ) use ( $order ) {
			$oa = $order[ $a['special'] ] ?? 8;
			$ob = $order[ $b['special'] ] ?? 8;
			if ( $oa === $ob ) { return strcasecmp( $a['display'], $b['display'] ); }
			return $oa <=> $ob;
		} );
		return $folders;
	}

	public function status( $folder ) {
		$out = array( 'messages' => 0, 'unseen' => 0, 'uidnext' => 0, 'uidvalidity' => 0 );
		try {
			$r = $this->run( array( ' STATUS ' . $this->quote( $folder ) . ' (MESSAGES UNSEEN UIDNEXT UIDVALIDITY)' ) );
		} catch ( Exception $e ) { return $out; }
		foreach ( $r['untagged'] as $line ) {
			if ( isset( $line[1] ) && 'STATUS' === strtoupper( (string) $line[1] ) && is_array( end( $line ) ) ) {
				$kv = end( $line );
				for ( $i = 0; $i + 1 < count( $kv ); $i += 2 ) {
					$k = strtolower( (string) $kv[ $i ] );
					$v = (int) $kv[ $i + 1 ];
					if ( 'messages' === $k ) { $out['messages'] = $v; }
					elseif ( 'unseen' === $k ) { $out['unseen'] = $v; }
					elseif ( 'uidnext' === $k ) { $out['uidnext'] = $v; }
					elseif ( 'uidvalidity' === $k ) { $out['uidvalidity'] = $v; }
				}
			}
		}
		return $out;
	}

	private $selected = '';
	private $exists   = 0;

	public function select( $folder ) {
		$r = $this->run( array( ' SELECT ' . $this->quote( $folder ) ) );
		if ( 'OK' !== $r['status'] ) { $this->error = 'تعذّر فتح المجلد.'; return false; }
		$this->selected = $folder;
		$this->exists   = 0;
		foreach ( $r['untagged'] as $line ) {
			if ( isset( $line[1], $line[2] ) && 'EXISTS' === strtoupper( (string) $line[2] ) ) {
				$this->exists = (int) $line[1];
			}
		}
		return true;
	}

	/**
	 * List a page of messages (newest first). Returns { total, messages[] }.
	 *
	 * @param string $folder
	 * @param int    $page     0-based
	 * @param int    $per
	 * @param string $search   optional free-text search
	 */
	public function list_messages( $folder, $page = 0, $per = 25, $search = '' ) {
		if ( ! $this->select( $folder ) ) { return array( 'total' => 0, 'messages' => array() ); }

		$search = trim( (string) $search );
		if ( '' !== $search ) {
			$uids  = $this->search_uids( $search );
			$total = count( $uids );
			rsort( $uids, SORT_NUMERIC );
			$slice = array_slice( $uids, $page * $per, $per );
			if ( ! $slice ) { return array( 'total' => $total, 'messages' => array() ); }
			$set = implode( ',', $slice );
			$rows = $this->fetch_overview( $set, true );
		} else {
			$total = $this->exists;
			if ( $total <= 0 ) { return array( 'total' => 0, 'messages' => array() ); }
			$end   = $total - ( $page * $per );
			$start = $end - $per + 1;
			if ( $end < 1 ) { return array( 'total' => $total, 'messages' => array() ); }
			if ( $start < 1 ) { $start = 1; }
			$rows = $this->fetch_overview( $start . ':' . $end, false );
		}
		// Newest first.
		usort( $rows, function ( $a, $b ) { return $b['uid'] <=> $a['uid']; } );
		return array( 'total' => $total, 'messages' => array_values( $rows ) );
	}

	/** UID SEARCH returning an array of ints. */
	public function search_uids( $term ) {
		$term  = (string) $term;
		$ascii = ( '' === $term ) || ( 1 !== preg_match( '/[\x80-\xff]/', $term ) );
		if ( '' === $term ) {
			$r = $this->run( array( ' UID SEARCH ALL' ) );
		} elseif ( $ascii ) {
			$q = $this->quote( $term );
			$r = $this->run( array( ' UID SEARCH OR OR FROM ' . $q . ' TO ' . $q . ' OR SUBJECT ' . $q . ' BODY ' . $q ) );
		} else {
			// Non-ASCII: CHARSET UTF-8 with a literal term.
			$r = $this->run( array( ' UID SEARCH CHARSET UTF-8 TEXT ', array( 'lit' => $term ) ) );
		}
		$uids = array();
		foreach ( $r['untagged'] as $line ) {
			if ( isset( $line[1] ) && 'SEARCH' === strtoupper( (string) $line[1] ) ) {
				foreach ( array_slice( $line, 2 ) as $n ) {
					if ( ctype_digit( (string) $n ) ) { $uids[] = (int) $n; }
				}
			}
		}
		return $uids;
	}

	/**
	 * Fetch lightweight overview rows for a sequence/UID set.
	 *
	 * @param string $set
	 * @param bool   $by_uid  When true, $set is UIDs (UID FETCH); else sequence.
	 */
	private function fetch_overview( $set, $by_uid ) {
		// NB: request the whole HEADER (not HEADER.FIELDS (...)) so the response
		// data-item key stays a single atom `BODY[HEADER]`. A `.FIELDS (...)`
		// section prints with a space + nested parens, which no IMAP token parser
		// can read as one key. We extract the fields we want from the block below.
		$cmd  = ( $by_uid ? ' UID FETCH ' : ' FETCH ' ) . $set
			. ' (UID FLAGS RFC822.SIZE INTERNALDATE BODY.PEEK[HEADER])';
		$r    = $this->run( array( $cmd ) );
		$rows = array();
		foreach ( $r['untagged'] as $line ) {
			if ( ! isset( $line[2] ) || 'FETCH' !== strtoupper( (string) $line[2] ) || ! is_array( $line[3] ) ) { continue; }
			$kv      = $line[3];
			$item    = array( 'uid' => 0, 'flags' => array(), 'size' => 0, 'date' => '', 'headers' => '' );
			for ( $i = 0; $i + 1 < count( $kv ); $i += 2 ) {
				$k = strtoupper( (string) $kv[ $i ] );
				$v = $kv[ $i + 1 ];
				if ( 'UID' === $k ) { $item['uid'] = (int) $v; }
				elseif ( 'FLAGS' === $k ) { $item['flags'] = is_array( $v ) ? $v : array(); }
				elseif ( 'RFC822.SIZE' === $k ) { $item['size'] = (int) $v; }
				elseif ( 'INTERNALDATE' === $k ) { $item['date'] = (string) $v; }
				elseif ( 0 === strpos( $k, 'BODY[' ) ) { $item['headers'] = (string) $v; }
			}
			if ( ! $item['uid'] ) { continue; }
			$h       = Osoul_MIME::parse_headers( $item['headers'] );
			$flags   = array_map( 'strtolower', $item['flags'] );
			$from    = Osoul_MIME::address( $h['from'] ?? '' );
			$ctype   = strtolower( $h['content-type'] ?? '' );
			$rows[]  = array(
				'uid'         => $item['uid'],
				'seen'        => in_array( '\\seen', $flags, true ),
				'flagged'     => in_array( '\\flagged', $flags, true ),
				'answered'    => in_array( '\\answered', $flags, true ),
				'from'        => $from,
				'to'          => Osoul_MIME::address_list( $h['to'] ?? '' ),
				'subject'     => Osoul_MIME::decode_header( $h['subject'] ?? '' ),
				'date'        => $item['date'],
				'ts'          => $item['date'] ? strtotime( $item['date'] ) : 0,
				'size'        => $item['size'],
				'has_attach'  => ( false !== strpos( $ctype, 'multipart/mixed' ) ),
			);
		}
		return $rows;
	}

	/** Fetch + parse a single full message by UID. */
	public function get_message( $folder, $uid ) {
		if ( ! $this->select( $folder ) ) { return null; }
		$raw = $this->fetch_raw( $uid );
		if ( null === $raw ) { return null; }
		$msg = Osoul_MIME::parse( $raw );
		// Current flags.
		$fr    = $this->run( array( ' UID FETCH ' . (int) $uid . ' (FLAGS)' ) );
		$flags = array();
		foreach ( $fr['untagged'] as $line ) {
			if ( isset( $line[2] ) && 'FETCH' === strtoupper( (string) $line[2] ) && is_array( $line[3] ) ) {
				for ( $i = 0; $i + 1 < count( $line[3] ); $i += 2 ) {
					if ( 'FLAGS' === strtoupper( (string) $line[3][ $i ] ) && is_array( $line[3][ $i + 1 ] ) ) {
						$flags = array_map( 'strtolower', $line[3][ $i + 1 ] );
					}
				}
			}
		}
		$msg['uid']      = (int) $uid;
		$msg['seen']     = in_array( '\\seen', $flags, true );
		$msg['flagged']  = in_array( '\\flagged', $flags, true );
		$msg['answered'] = in_array( '\\answered', $flags, true );
		return $msg;
	}

	/** Raw RFC822 source of a message (returns null on failure). */
	public function fetch_raw( $uid ) {
		$r = $this->run( array( ' UID FETCH ' . (int) $uid . ' (BODY.PEEK[])' ) );
		foreach ( $r['untagged'] as $line ) {
			if ( isset( $line[2] ) && 'FETCH' === strtoupper( (string) $line[2] ) && is_array( $line[3] ) ) {
				$kv = $line[3];
				for ( $i = 0; $i + 1 < count( $kv ); $i += 2 ) {
					if ( 0 === strpos( strtoupper( (string) $kv[ $i ] ), 'BODY[' ) ) {
						return (string) $kv[ $i + 1 ];
					}
				}
			}
		}
		return null;
	}

	public function set_flag( $folder, $uid, $flag, $on = true ) {
		if ( ! $this->select( $folder ) ) { return false; }
		$op = $on ? '+FLAGS' : '-FLAGS';
		$r  = $this->run( array( ' UID STORE ' . (int) $uid . ' ' . $op . ' (' . $flag . ')' ) );
		return 'OK' === $r['status'];
	}

	public function set_flag_many( $folder, $uids, $flag, $on = true ) {
		if ( ! $uids || ! $this->select( $folder ) ) { return false; }
		$set = implode( ',', array_map( 'intval', $uids ) );
		$op  = $on ? '+FLAGS' : '-FLAGS';
		$r   = $this->run( array( ' UID STORE ' . $set . ' ' . $op . ' (' . $flag . ')' ) );
		return 'OK' === $r['status'];
	}

	/** Move a message to another folder (MOVE, or COPY + delete fallback). */
	public function move( $folder, $uid, $dest ) {
		if ( ! $this->select( $folder ) ) { return false; }
		$uid = (int) $uid;
		if ( $this->has_cap( 'MOVE' ) ) {
			$r = $this->run( array( ' UID MOVE ' . $uid . ' ' . $this->quote( $dest ) ) );
			return 'OK' === $r['status'];
		}
		$c = $this->run( array( ' UID COPY ' . $uid . ' ' . $this->quote( $dest ) ) );
		if ( 'OK' !== $c['status'] ) { return false; }
		$this->run( array( ' UID STORE ' . $uid . ' +FLAGS (\\Deleted)' ) );
		if ( $this->has_cap( 'UIDPLUS' ) ) {
			$this->run( array( ' UID EXPUNGE ' . $uid ) );
		} else {
			$this->run( array( ' EXPUNGE' ) );
		}
		return true;
	}

	/** Permanently delete a message (mark \Deleted + expunge). */
	public function delete( $folder, $uid ) {
		if ( ! $this->select( $folder ) ) { return false; }
		$uid = (int) $uid;
		$this->run( array( ' UID STORE ' . $uid . ' +FLAGS (\\Deleted)' ) );
		$r = $this->has_cap( 'UIDPLUS' )
			? $this->run( array( ' UID EXPUNGE ' . $uid ) )
			: $this->run( array( ' EXPUNGE' ) );
		return 'OK' === $r['status'];
	}

	/**
	 * APPEND a raw message into a folder with the given flags (e.g. \Seen).
	 *
	 * Exception-safe: when the folder does not exist the server answers the
	 * command line with a tagged NO [TRYCREATE] instead of the "+" continuation,
	 * which surfaces here as an exception. We swallow it and return false so the
	 * caller can try another folder name on the same (still-clean) connection —
	 * the literal was never sent, so the stream is not desynced.
	 */
	public function append( $folder, $raw, $flags = '\\Seen' ) {
		try {
			$raw = preg_replace( '/\r?\n/', "\r\n", (string) $raw );
			$r   = $this->run( array( ' APPEND ' . $this->quote( $folder ) . ' (' . $flags . ') ', array( 'lit' => $raw ) ) );
			return 'OK' === $r['status'];
		} catch ( Exception $e ) {
			return false;
		}
	}

	/** Create (and subscribe) a mailbox folder. Returns true if it exists after. */
	public function create_folder( $name ) {
		$r = $this->run( array( ' CREATE ' . $this->quote( $name ) ) );
		$this->run( array( ' SUBSCRIBE ' . $this->quote( $name ) ) );
		if ( 'OK' === $r['status'] ) { return true; }
		// NO because it already exists still counts as "present".
		$tagged = isset( $r['tagged'] ) ? strtoupper( implode( ' ', array_map( 'strval', (array) $r['tagged'] ) ) ) : '';
		return false !== strpos( $tagged, 'ALREADYEXISTS' ) || false !== strpos( $tagged, 'EXISTS' );
	}

	/* ---- IMAP protocol core ---- */

	private function quote( $s ) {
		return '"' . addcslashes( (string) $s, "\"\\" ) . '"';
	}

	/**
	 * Run a command built from segments. String segments are appended verbatim
	 * (caller includes spaces); an array segment ['lit'=>bytes] is sent as a
	 * synchronizing IMAP literal (waits for the server's "+" continuation).
	 *
	 * @return array { status, untagged[], tagged }
	 */
	private function run( $segments ) {
		$tag = 'A' . ( ++$this->tag );
		$this->compact();
		$out = $tag;
		foreach ( $segments as $seg ) {
			if ( is_array( $seg ) && array_key_exists( 'lit', $seg ) ) {
				$data = (string) $seg['lit'];
				$this->write( $out . '{' . strlen( $data ) . "}\r\n" );
				$this->expect_continuation();
				$out = $data;
			} else {
				$out .= $seg;
			}
		}
		$this->write( $out . "\r\n" );

		$untagged = array();
		while ( true ) {
			$line = $this->readResponse();
			if ( ! $line ) { continue; }
			$first = (string) $line[0];
			if ( $first === $tag ) {
				return array( 'status' => strtoupper( (string) ( $line[1] ?? '' ) ), 'untagged' => $untagged, 'tagged' => $line );
			}
			$untagged[] = $line;
		}
	}

	private function expect_continuation() {
		$line = $this->readResponse();
		if ( ! $line || '+' !== (string) $line[0] ) {
			throw new Exception( 'expected continuation, got: ' . wp_json_encode( $line ) );
		}
	}

	private function write( $data ) {
		if ( null === $this->sock ) { throw new Exception( 'not connected' ); }
		$len = strlen( $data );
		$w   = 0;
		while ( $w < $len ) {
			$n = @fwrite( $this->sock, substr( $data, $w ) );
			if ( false === $n || 0 === $n ) { throw new Exception( 'write failed' ); }
			$w += $n;
		}
	}

	/** Read + parse one complete response line (tokens), honouring literals. */
	private function readResponse() {
		return $this->parse_list( true );
	}

	private function parse_list( $top ) {
		$out = array();
		while ( true ) {
			$c = $this->peek();
			if ( ' ' === $c ) { $this->pos++; continue; }
			if ( "\r" === $c ) { $this->pos++; if ( $this->has_byte() && "\n" === $this->peek() ) { $this->pos++; } if ( $top ) { return $out; } continue; }
			if ( "\n" === $c ) { $this->pos++; if ( $top ) { return $out; } continue; }
			if ( ! $top && ')' === $c ) { $this->pos++; return $out; }
			if ( '(' === $c ) { $this->pos++; $out[] = $this->parse_list( false ); continue; }
			if ( '"' === $c ) { $out[] = $this->parse_quoted(); continue; }
			if ( '{' === $c ) { $out[] = $this->parse_literal(); continue; }
			$out[] = $this->parse_atom();
		}
	}

	private function parse_quoted() {
		$this->pos++; // opening "
		$s = '';
		while ( true ) {
			$c = $this->getc();
			if ( '\\' === $c ) { $s .= $this->getc(); continue; }
			if ( '"' === $c ) { return $s; }
			$s .= $c;
		}
	}

	private function parse_literal() {
		$this->pos++; // {
		$num = '';
		while ( '}' !== ( $c = $this->getc() ) ) { $num .= $c; }
		$c = $this->getc();
		if ( "\r" === $c ) { if ( $this->peek() === "\n" ) { $this->pos++; } }
		return $this->readBytes( (int) $num );
	}

	private function parse_atom() {
		$s = '';
		while ( true ) {
			if ( ! $this->has_byte() ) { break; }
			$c = $this->buf[ $this->pos ];
			if ( ' ' === $c || '(' === $c || ')' === $c || '{' === $c || "\r" === $c || "\n" === $c ) { break; }
			$s .= $c; $this->pos++;
		}
		return ( 0 === strcasecmp( $s, 'NIL' ) ) ? null : $s;
	}

	/* ---- byte-level buffer ---- */

	private function compact() {
		if ( $this->pos > 0 ) {
			$this->buf = substr( $this->buf, $this->pos );
			$this->pos = 0;
		}
	}

	private function fill() {
		if ( null === $this->sock ) { $this->eof = true; return; }
		$chunk = @fread( $this->sock, 65536 );
		if ( '' === $chunk || false === $chunk ) {
			$meta = stream_get_meta_data( $this->sock );
			if ( ! empty( $meta['timed_out'] ) ) { throw new Exception( 'timeout' ); }
			if ( feof( $this->sock ) ) { $this->eof = true; }
			return;
		}
		$this->buf .= $chunk;
	}

	private function ensure( $n ) {
		$guard = 0;
		while ( strlen( $this->buf ) - $this->pos < $n ) {
			if ( $this->eof ) { throw new Exception( 'unexpected end of stream' ); }
			$this->fill();
			if ( ++$guard > 200000 ) { throw new Exception( 'read overflow' ); }
		}
	}

	private function has_byte() {
		if ( strlen( $this->buf ) - $this->pos >= 1 ) { return true; }
		if ( $this->eof ) { return false; }
		$this->fill();
		return strlen( $this->buf ) - $this->pos >= 1;
	}

	private function peek()   { $this->ensure( 1 ); return $this->buf[ $this->pos ]; }
	private function getc()   { $this->ensure( 1 ); return $this->buf[ $this->pos++ ]; }
	private function readBytes( $n ) {
		if ( $n <= 0 ) { return ''; }
		$this->ensure( $n );
		$s = substr( $this->buf, $this->pos, $n );
		$this->pos += $n;
		return $s;
	}

	/* ---- test seam: preload a buffer with no socket ---- */
	public function _test_feed( $data ) { $this->buf = $data; $this->pos = 0; $this->sock = null; $this->eof = true; }
	public function _test_parse()       { return $this->parse_list( true ); }
	public function _test_attach( $sock ) { $this->sock = $sock; $this->buf = ''; $this->pos = 0; $this->eof = false; }

	/* ---- modified UTF-7 (RFC 3501) decode for folder display names ---- */
	private function mutf7_decode( $s ) {
		$s = (string) $s;
		if ( false === strpos( $s, '&' ) ) { return $s; }
		return preg_replace_callback( '/&([^-]*)-/', function ( $m ) {
			if ( '' === $m[1] ) { return '&'; }
			$b64 = strtr( $m[1], ',', '/' );
			$bin = base64_decode( $b64 . str_repeat( '=', ( 4 - strlen( $b64 ) % 4 ) % 4 ), true );
			if ( false === $bin ) { return $m[0]; }
			// UTF-16BE → UTF-8
			$out = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $bin, 'UTF-8', 'UTF-16BE' ) : $m[0];
			return $out;
		}, $s );
	}
}

/* =========================================================================
 *  Osoul_MIME — RFC822 / MIME parser
 * ====================================================================== */

class Osoul_MIME {

	/** Parse a raw header block into a lowercased map (last value wins for singletons). */
	public static function parse_headers( $raw ) {
		$raw   = preg_replace( "/\r\n[ \t]+/", ' ', str_replace( "\n", "\r\n", str_replace( "\r\n", "\n", (string) $raw ) ) );
		$out   = array();
		foreach ( explode( "\r\n", $raw ) as $line ) {
			if ( '' === trim( $line ) ) { continue; }
			$p = strpos( $line, ':' );
			if ( false === $p ) { continue; }
			$k = strtolower( trim( substr( $line, 0, $p ) ) );
			$v = ltrim( substr( $line, $p + 1 ) );
			if ( isset( $out[ $k ] ) ) { $out[ $k ] .= ', ' . $v; } else { $out[ $k ] = $v; }
		}
		return $out;
	}

	/** Decode an RFC2047 encoded header value to UTF-8. */
	public static function decode_header( $v ) {
		$v = (string) $v;
		if ( '' === $v ) { return ''; }
		if ( function_exists( 'iconv_mime_decode' ) ) {
			$d = @iconv_mime_decode( $v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8' );
			if ( false !== $d && '' !== $d ) { return $d; }
		}
		if ( function_exists( 'mb_decode_mimeheader' ) ) {
			return mb_decode_mimeheader( $v );
		}
		return $v;
	}

	/** Parse a single address into { name, email }. */
	public static function address( $v ) {
		$list = self::address_list( $v );
		return $list ? $list[0] : array( 'name' => '', 'email' => '' );
	}

	/** Parse an address-list header into [{ name, email }]. */
	public static function address_list( $v ) {
		$v = self::decode_header( (string) $v );
		if ( '' === trim( $v ) ) { return array(); }
		$out   = array();
		// Split on commas that are not inside quotes.
		$parts = preg_split( '/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $v );
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) { continue; }
			$name = ''; $email = '';
			if ( preg_match( '/^(.*)<([^>]*)>\s*$/', $part, $m ) ) {
				$name  = trim( $m[1], " \t\"'" );
				$email = trim( $m[2] );
			} else {
				$email = trim( $part, " \t<>" );
			}
			if ( '' === $name ) { $name = $email; }
			$out[] = array( 'name' => $name, 'email' => $email );
		}
		return $out;
	}

	/**
	 * Full parse. Returns:
	 * { subject, from, to[], cc[], reply_to, date, ts, message_id, in_reply_to,
	 *   references, html, text, attachments[], inline{cid:dataUri} }
	 */
	public static function parse( $raw ) {
		$raw = (string) $raw;
		list( $head, $body ) = self::split( $raw );
		$h = self::parse_headers( $head );

		$result = array(
			'subject'     => self::decode_header( $h['subject'] ?? '' ),
			'from'        => self::address( $h['from'] ?? '' ),
			'to'          => self::address_list( $h['to'] ?? '' ),
			'cc'          => self::address_list( $h['cc'] ?? '' ),
			'reply_to'    => self::address( $h['reply-to'] ?? ( $h['from'] ?? '' ) ),
			'date'        => $h['date'] ?? '',
			'ts'          => isset( $h['date'] ) ? strtotime( $h['date'] ) : 0,
			'message_id'  => trim( $h['message-id'] ?? '' ),
			'in_reply_to' => trim( $h['in-reply-to'] ?? '' ),
			'references'  => trim( $h['references'] ?? '' ),
			'html'        => '',
			'text'        => '',
			'attachments' => array(),
			'inline'      => array(),
		);

		$parts = array();
		self::walk( $h, $body, $parts );

		foreach ( $parts as $p ) {
			$ctype = strtolower( $p['type'] );
			$isAtt = ( 'attachment' === $p['disposition'] ) || ( '' !== $p['filename'] && 'text/html' !== $ctype && 'text/plain' !== $ctype );
			if ( ! $isAtt && 'text/html' === $ctype ) {
				$result['html'] .= $p['content'];
			} elseif ( ! $isAtt && 'text/plain' === $ctype ) {
				$result['text'] .= $p['content'];
			} elseif ( 'inline' === $p['disposition'] && '' !== $p['cid'] && 0 === strpos( $ctype, 'image/' ) && strlen( $p['content'] ) < 1600000 ) {
				$result['inline'][ trim( $p['cid'], '<>' ) ] = 'data:' . $ctype . ';base64,' . base64_encode( $p['content'] );
			} else {
				$result['attachments'][] = array(
					'index'    => count( $result['attachments'] ),
					'name'     => '' !== $p['filename'] ? $p['filename'] : ( 'ملف-' . ( count( $result['attachments'] ) + 1 ) ),
					'mime'     => $ctype,
					'size'     => strlen( $p['content'] ),
					'cid'      => trim( $p['cid'], '<>' ),
					'inline'   => ( 'inline' === $p['disposition'] ),
				);
			}
		}

		// Build a safe HTML body for preview (or convert text → html).
		if ( '' !== $result['html'] ) {
			$result['html_safe'] = self::safe_html( $result['html'], $result['inline'] );
		} elseif ( '' !== $result['text'] ) {
			$result['html_safe'] = '<pre style="white-space:pre-wrap;font-family:inherit;margin:0">' . esc_html( $result['text'] ) . '</pre>';
		} else {
			$result['html_safe'] = '';
		}
		if ( '' === $result['text'] && '' !== $result['html'] ) {
			$result['text'] = trim( wp_strip_all_tags( $result['html'] ) );
		}
		return $result;
	}

	/** Return the decoded content of the Nth attachment (0-based) or null. */
	public static function attachment( $raw, $index ) {
		$parts = array();
		list( $head, $body ) = self::split( (string) $raw );
		self::walk( self::parse_headers( $head ), $body, $parts );
		$n = 0;
		foreach ( $parts as $p ) {
			$ctype = strtolower( $p['type'] );
			$isAtt = ( 'attachment' === $p['disposition'] ) || ( '' !== $p['filename'] && 'text/html' !== $ctype && 'text/plain' !== $ctype );
			$isInlineNotEmbedded = ( 'inline' === $p['disposition'] && ! ( '' !== $p['cid'] && 0 === strpos( $ctype, 'image/' ) ) );
			if ( $isAtt || $isInlineNotEmbedded ) {
				if ( $n === (int) $index ) {
					return array(
						'name'    => '' !== $p['filename'] ? $p['filename'] : ( 'file-' . ( $n + 1 ) ),
						'mime'    => '' !== $ctype ? $ctype : 'application/octet-stream',
						'content' => $p['content'],
					);
				}
				$n++;
			}
		}
		return null;
	}

	/* ---- internals ---- */

	private static function split( $raw ) {
		$raw = str_replace( "\r\n", "\n", $raw );
		$p   = strpos( $raw, "\n\n" );
		if ( false === $p ) { return array( $raw, '' ); }
		return array( substr( $raw, 0, $p ), substr( $raw, $p + 2 ) );
	}

	/** Recursively collect leaf parts as { type, disposition, filename, cid, content }. */
	private static function walk( $headers, $body, &$parts, $depth = 0 ) {
		if ( $depth > 20 ) { return; }
		$ctype_raw = $headers['content-type'] ?? 'text/plain';
		$type      = strtolower( trim( explode( ';', $ctype_raw )[0] ) );

		if ( 0 === strpos( $type, 'multipart/' ) ) {
			$boundary = self::param( $ctype_raw, 'boundary' );
			if ( '' === $boundary ) { return; }
			$segments = self::split_multipart( $body, $boundary );
			foreach ( $segments as $seg ) {
				list( $sh, $sb ) = self::split( $seg );
				self::walk( self::parse_headers( $sh ), $sb, $parts, $depth + 1 );
			}
			return;
		}

		// Leaf part.
		$cte      = strtolower( trim( $headers['content-transfer-encoding'] ?? '7bit' ) );
		$content  = self::decode_body( $body, $cte );
		$charset  = self::param( $ctype_raw, 'charset' );
		if ( 0 === strpos( $type, 'text/' ) && '' !== $charset ) {
			$content = self::to_utf8( $content, $charset );
		}
		$disp_raw    = strtolower( $headers['content-disposition'] ?? '' );
		$disposition = '';
		if ( 0 === strpos( $disp_raw, 'attachment' ) ) { $disposition = 'attachment'; }
		elseif ( 0 === strpos( $disp_raw, 'inline' ) ) { $disposition = 'inline'; }
		$filename = self::param( $headers['content-disposition'] ?? '', 'filename' );
		if ( '' === $filename ) { $filename = self::param( $ctype_raw, 'name' ); }
		$filename = self::decode_header( $filename );
		$cid      = trim( $headers['content-id'] ?? '' );

		$parts[] = array(
			'type'        => $type,
			'disposition' => $disposition,
			'filename'    => $filename,
			'cid'         => $cid,
			'content'     => $content,
		);
	}

	private static function split_multipart( $body, $boundary ) {
		$out    = array();
		$marker = '--' . $boundary;
		$lines  = explode( "\n", $body );
		$buf    = null;
		foreach ( $lines as $line ) {
			$trim = rtrim( $line, "\r" );
			if ( $trim === $marker || $trim === $marker . '--' ) {
				if ( null !== $buf ) { $out[] = substr( $buf, 0, -1 ); } // drop trailing \n
				if ( $trim === $marker . '--' ) { $buf = null; break; }
				$buf = '';
				continue;
			}
			if ( null !== $buf ) { $buf .= $line . "\n"; }
		}
		return $out;
	}

	private static function param( $header, $name ) {
		$header = (string) $header;
		// RFC 2231 extended (name*=charset''value or name*0*=...) — best effort.
		if ( preg_match( '/;\s*' . preg_quote( $name, '/' ) . '\*\s*=\s*([^\';]+)\'[^\';]*\'([^;\r\n]+)/i', $header, $m ) ) {
			return rawurldecode( trim( $m[2] ) );
		}
		if ( preg_match( '/;\s*' . preg_quote( $name, '/' ) . '\s*=\s*"([^"]*)"/i', $header, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/;\s*' . preg_quote( $name, '/' ) . '\s*=\s*([^;\r\n]+)/i', $header, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}

	private static function decode_body( $body, $cte ) {
		switch ( $cte ) {
			case 'base64':
				return base64_decode( preg_replace( '/\s+/', '', $body ), false );
			case 'quoted-printable':
				return quoted_printable_decode( $body );
			default:
				return $body;
		}
	}

	private static function to_utf8( $s, $charset ) {
		$charset = strtoupper( trim( $charset, " \t\"'" ) );
		if ( '' === $charset || 'UTF-8' === $charset || 'US-ASCII' === $charset || 'ASCII' === $charset ) { return $s; }
		if ( function_exists( 'iconv' ) ) {
			$r = @iconv( $charset, 'UTF-8//TRANSLIT', $s );
			if ( false !== $r ) { return $r; }
		}
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$r = @mb_convert_encoding( $s, 'UTF-8', $charset );
			if ( false !== $r && '' !== $r ) { return $r; }
		}
		return $s;
	}

	/**
	 * Make an email's HTML safe to drop into a sandboxed preview iframe:
	 * strip scripts/embeds/handlers, neutralise javascript: URLs, and swap
	 * cid: image references for their inline data URIs.
	 */
	public static function safe_html( $html, $inline = array() ) {
		$html = (string) $html;
		// Remove dangerous elements entirely (with content).
		$html = preg_replace( '#<(script|style|iframe|frame|frameset|object|embed|applet|meta|link|base|form)\b[^>]*>.*?</\1>#is', '', $html );
		$html = preg_replace( '#<(script|iframe|frame|frameset|object|embed|applet|meta|link|base|form|input|button)\b[^>]*/?>#is', '', $html );
		// Strip inline event handlers (on*="...").
		$html = preg_replace( '/\son[a-z]+\s*=\s*"[^"]*"/i', '', $html );
		$html = preg_replace( "/\son[a-z]+\s*=\s*'[^']*'/i", '', $html );
		$html = preg_replace( '/\son[a-z]+\s*=\s*[^\s>]+/i', '', $html );
		// Neutralise javascript:/vbscript: in href/src.
		$html = preg_replace( '#(href|src|action)\s*=\s*(["\']?)\s*(javascript|vbscript)\s*:#i', '$1=$2#', $html );
		// Swap cid: images for inline data URIs.
		if ( $inline ) {
			$html = preg_replace_callback( '/(src\s*=\s*["\']?)cid:([^"\'>\s]+)/i', function ( $m ) use ( $inline ) {
				$key = trim( $m[2] );
				return isset( $inline[ $key ] ) ? $m[1] . $inline[ $key ] : $m[1];
			}, $html );
		} else {
			$html = preg_replace( '/src\s*=\s*["\']?cid:[^"\'>\s]+/i', 'src=""', $html );
		}
		return $html;
	}
}

/* =========================================================================
 *  High-level helpers used by the REST layer
 * ====================================================================== */

/**
 * Open + authenticate an IMAP session for a user. Returns Osoul_IMAP or WP_Error.
 *
 * @param int         $user_id
 * @param string|null $override_pass  Test a not-yet-saved password (connect flow).
 * @return Osoul_IMAP|WP_Error
 */
function osoul_mail_open( $user_id, $override_pass = null ) {
	$cfg  = osoul_mail_config( $user_id, true );
	$pass = ( null !== $override_pass ) ? $override_pass : ( $cfg['password'] ?? '' );
	if ( '' === $cfg['email'] || '' === $pass ) {
		return new WP_Error( 'osoul_mail_nocreds', 'لم يتم ربط البريد بعد.' );
	}
	$imap = new Osoul_IMAP( (int) apply_filters( 'osoul_mail_timeout', 20 ) );
	if ( ! $imap->connect( $cfg['imap_host'], $cfg['imap_port'], 'ssl' ) ) {
		return new WP_Error( 'osoul_mail_conn', $imap->error ?: 'تعذّر الاتصال بخادم البريد.' );
	}
	if ( ! $imap->login( $cfg['email'], $pass ) ) {
		$err = $imap->error;
		$imap->logout();
		return new WP_Error( 'osoul_mail_auth', $err ?: 'فشل تسجيل الدخول للبريد.' );
	}
	return $imap;
}

/**
 * Verify credentials (IMAP login + SMTP handshake) without persisting yet.
 *
 * @return true|WP_Error
 */
function osoul_mail_test( $user_id, $email, $pass, $imap_host, $imap_port, $smtp_host, $smtp_port ) {
	// IMAP check.
	$imap = new Osoul_IMAP( (int) apply_filters( 'osoul_mail_timeout', 20 ) );
	if ( ! $imap->connect( $imap_host, $imap_port, 'ssl' ) ) {
		return new WP_Error( 'osoul_mail_conn', $imap->error );
	}
	if ( ! $imap->login( $email, $pass ) ) {
		$err = $imap->error; $imap->logout();
		return new WP_Error( 'osoul_mail_auth', $err );
	}
	$imap->logout();

	// SMTP check.
	$smtp = osoul_mail_smtp_verify( $email, $pass, $smtp_host, $smtp_port );
	if ( is_wp_error( $smtp ) ) { return $smtp; }
	return true;
}

/** Load WordPress's bundled PHPMailer classes. */
function osoul_mail_load_phpmailer() {
	if ( ! class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
	}
}

/** Build a configured PHPMailer bound to the employee's SMTP. */
function osoul_mail_make_phpmailer( $email, $pass, $smtp_host, $smtp_port ) {
	osoul_mail_load_phpmailer();
	$mail = new PHPMailer\PHPMailer\PHPMailer( true );
	$mail->isSMTP();
	$mail->Host        = $smtp_host;
	$mail->Port        = (int) $smtp_port;
	$mail->SMTPAuth    = true;
	$mail->Username    = $email;
	$mail->Password    = $pass;
	$mail->SMTPSecure  = ( 465 === (int) $smtp_port ) ? 'ssl' : 'tls';
	$mail->CharSet     = 'UTF-8';
	$mail->Timeout     = (int) apply_filters( 'osoul_mail_timeout', 20 );
	if ( ! (bool) apply_filters( 'osoul_mail_ssl_verify', true, $smtp_host ) ) {
		$mail->SMTPOptions = array( 'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true ) );
	}
	return $mail;
}

/** SMTP connect+auth only (used by the connect test). @return true|WP_Error */
function osoul_mail_smtp_verify( $email, $pass, $smtp_host, $smtp_port ) {
	try {
		$mail = osoul_mail_make_phpmailer( $email, $pass, $smtp_host, $smtp_port );
		if ( ! $mail->smtpConnect() ) {
			return new WP_Error( 'osoul_smtp', 'تعذّر الاتصال بخادم البريد الصادر (SMTP).' );
		}
		$mail->smtpClose();
		return true;
	} catch ( Exception $e ) {
		return new WP_Error( 'osoul_smtp', 'فشل التحقق من الإرسال (SMTP): ' . $e->getMessage() );
	}
}

/**
 * Send a message as the employee and file a copy into Sent.
 *
 * @param int   $user_id
 * @param array $args { to, cc, bcc, subject, body_html, attachments[], in_reply_to, references, is_draft }
 * @return array|WP_Error { ok, message_id }
 */
function osoul_mail_send( $user_id, $args ) {
	$cfg = osoul_mail_config( $user_id, true );
	if ( '' === $cfg['email'] || '' === ( $cfg['password'] ?? '' ) ) {
		return new WP_Error( 'osoul_mail_nocreds', 'لم يتم ربط البريد بعد.' );
	}
	try {
		$mail = osoul_mail_make_phpmailer( $cfg['email'], $cfg['password'], $cfg['smtp_host'], $cfg['smtp_port'] );
		$mail->setFrom( $cfg['email'], $cfg['from_name'] ?: $cfg['email'] );
		$mail->Sender = $cfg['email'];

		foreach ( (array) ( $args['to'] ?? array() ) as $a )  { if ( is_email( $a ) ) { $mail->addAddress( $a ); } }
		foreach ( (array) ( $args['cc'] ?? array() ) as $a )  { if ( is_email( $a ) ) { $mail->addCC( $a ); } }
		foreach ( (array) ( $args['bcc'] ?? array() ) as $a ) { if ( is_email( $a ) ) { $mail->addBCC( $a ); } }

		$mail->Subject = (string) ( $args['subject'] ?? '' );
		$html          = (string) ( $args['body_html'] ?? '' );
		$mail->isHTML( true );
		$mail->Body    = $html;
		$mail->AltBody = trim( wp_strip_all_tags( $html ) );

		if ( ! empty( $args['in_reply_to'] ) ) {
			$mail->addCustomHeader( 'In-Reply-To', (string) $args['in_reply_to'] );
		}
		if ( ! empty( $args['references'] ) ) {
			$mail->addCustomHeader( 'References', (string) $args['references'] );
		}

		foreach ( (array) ( $args['attachments'] ?? array() ) as $att ) {
			$path = isset( $att['path'] ) ? $att['path'] : '';
			if ( $path && file_exists( $path ) ) {
				$mail->addAttachment( $path, (string) ( $att['name'] ?? basename( $path ) ) );
			}
		}

		$mail->send();
		$raw = $mail->getSentMIMEMessage();
		$mid = trim( $mail->getLastMessageID() );
	} catch ( Exception $e ) {
		return new WP_Error( 'osoul_send_fail', 'تعذّر الإرسال: ' . $e->getMessage() );
	}

	// File a copy into Sent (best effort; failure doesn't fail the send).
	$filed = '';
	$imap  = osoul_mail_open( $user_id );
	if ( ! is_wp_error( $imap ) ) {
		$filed = osoul_mail_file_copy( $imap, 'sent', $raw );
		$imap->logout();
	}
	return array( 'ok' => true, 'message_id' => $mid, 'filed' => $filed );
}

/**
 * File a raw message into a special folder (sent/drafts), trying the detected
 * folder first and then the common Hostinger/Dovecot names, creating the folder
 * as a last resort. Returns the folder used, or '' if none worked.
 *
 * @param Osoul_IMAP $imap
 * @param string     $special  'sent' | 'drafts'
 * @param string     $raw      RFC822 message
 * @param string     $flags    IMAP flags to stamp (default \Seen)
 * @return string
 */
function osoul_mail_file_copy( Osoul_IMAP $imap, $special, $raw, $flags = '\\Seen' ) {
	$detected = osoul_mail_special_folder( $imap, $special );
	$common   = ( 'drafts' === $special )
		? array( 'INBOX.Drafts', 'Drafts', 'INBOX.INBOX.Drafts' )
		: array( 'INBOX.Sent', 'Sent', 'Sent Items', 'Sent Messages', 'INBOX.Sent Items' );

	$candidates = array();
	foreach ( array_merge( array( (string) $detected ), $common ) as $c ) {
		$c = trim( (string) $c );
		if ( '' !== $c && ! in_array( $c, $candidates, true ) ) { $candidates[] = $c; }
	}
	foreach ( $candidates as $folder ) {
		if ( $imap->append( $folder, $raw, $flags ) ) { return $folder; }
	}
	// Nothing matched — create the canonical name and append there. Try the
	// INBOX-prefixed name (Dovecot default) and the bare name.
	$leaf = ( 'drafts' === $special ) ? 'Drafts' : 'Sent';
	foreach ( array( 'INBOX.' . $leaf, $leaf ) as $make ) {
		if ( $imap->create_folder( $make ) && $imap->append( $make, $raw, $flags ) ) { return $make; }
	}
	return '';
}

/** Resolve a special-use folder's raw name (e.g. 'sent','drafts','trash','junk'). */
function osoul_mail_special_folder( Osoul_IMAP $imap, $special, $fallback = '' ) {
	static $cache = array();
	$key = spl_object_id( $imap );
	if ( ! isset( $cache[ $key ] ) ) { $cache[ $key ] = $imap->folders(); }
	foreach ( $cache[ $key ] as $f ) {
		if ( $f['special'] === $special ) { return $f['raw']; }
	}
	return $fallback;
}
