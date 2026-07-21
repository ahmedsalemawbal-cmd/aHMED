<?php
/**
 * Public quote page at /quote/{token} — a branded, bilingual, print-ready
 * quotation the customer opens from the WhatsApp/email link. Includes
 * "Save as PDF" (browser print) and Accept / Reject buttons.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render the public quote document.
 *
 * @param string $token
 */
function osoul_render_public_quote( $token ) {
	nocache_headers();
	$post = osoul_get_quote_by_token( $token );
	if ( ! $post ) {
		status_header( 404 );
		osoul_app_head( __( 'العرض غير موجود', 'osoul' ), 'ar' );
		echo '<div class="osa-wrap"><div class="osa-card">' . esc_html__( 'هذا الرابط غير صحيح أو انتهت صلاحيته.', 'osoul' ) . '</div></div>';
		osoul_app_foot();
		return;
	}
	$qid = $post->ID;
	status_header( 200 );

	// Accept / reject (the token in the URL authorises the customer).
	$flash     = '';
	$cur_stage = get_post_meta( $qid, '_osoul_stage', true );
	// Lock the decision once made — the token holder can't flip won/lost repeatedly.
	if ( isset( $_POST['osoul_decision'] ) && ! in_array( $cur_stage, array( 'won', 'lost' ), true ) ) {
		$decision = sanitize_key( $_POST['osoul_decision'] );
		if ( in_array( $decision, array( 'accept', 'reject' ), true ) ) {
			update_post_meta( $qid, '_osoul_stage', 'accept' === $decision ? 'won' : 'lost' );
			update_post_meta( $qid, '_osoul_decided', current_time( 'mysql' ) );
			$flash = 'accept' === $decision ? 'accepted' : 'rejected';
		}
	}

	// Language.
	$lang = isset( $_GET['lang'] ) ? sanitize_key( $_GET['lang'] ) : ( get_post_meta( $qid, '_osoul_lang', true ) ?: 'ar' );
	$lang = ( 'en' === $lang ) ? 'en' : 'ar';
	$ar   = ( 'ar' === $lang );
	$t    = function ( $a, $e ) use ( $ar ) { return $ar ? $a : $e; };

	// Data.
	$cust     = (array) get_post_meta( $qid, '_osoul_customer', true );
	$items    = (array) get_post_meta( $qid, '_osoul_items', true );
	$discount = (float) get_post_meta( $qid, '_osoul_discount', true );
	$totals   = osoul_quote_totals( $items, $discount );
	$number   = get_post_meta( $qid, '_osoul_number', true );
	$issued   = get_post_meta( $qid, '_osoul_issued', true );
	$validity = (int) ( get_post_meta( $qid, '_osoul_validity', true ) ?: osoul_opt( 'validity_days' ) );
	$stage    = get_post_meta( $qid, '_osoul_stage', true ) ?: 'new';
	$terms    = $ar ? ( get_post_meta( $qid, '_osoul_terms_ar', true ) ?: osoul_opt( 'terms_ar' ) ) : ( get_post_meta( $qid, '_osoul_terms_en', true ) ?: osoul_opt( 'terms_en' ) );

	$issued_ts   = $issued ? strtotime( $issued ) : current_time( 'timestamp' );
	$valid_until = gmdate( 'Y-m-d', $issued_ts + $validity * DAY_IN_SECONDS );
	// White-label: a partner customer quote carries an immutable brand snapshot
	// (_osoul_brand) so it renders under the partner's identity; ordinary Osoul
	// quotes fall back to the global Site Settings.
	$brand = function_exists( 'osoul_quote_brand' ) ? osoul_quote_brand( $qid ) : null;
	if ( $brand ) {
		$company = $ar ? ( $brand['company_ar'] ?: $brand['company_en'] ) : ( $brand['company_en'] ?: $brand['company_ar'] );
		$address = $ar ? $brand['address_ar'] : ( $brand['address_en'] ?: $brand['address_ar'] );
		$logo    = $brand['logo'];
		$vat_no  = $brand['vat'];
		$cr_no   = $brand['cr'];
		$bank    = $brand['bank'];
	} else {
		$company = $ar ? osoul_opt( 'company_name_ar' ) : osoul_opt( 'company_name_en' );
		$address = $ar ? osoul_opt( 'national_address_ar' ) : osoul_opt( 'national_address_en' );
		$logo    = $ar ? osoul_opt( 'logo_url' ) : ( osoul_opt( 'logo_url_en' ) ?: osoul_opt( 'logo_url' ) );
		$vat_no  = osoul_opt( 'vat_number' );
		$cr_no   = osoul_opt( 'cr_number' );
		$bank    = osoul_opt( 'bank_accounts' );
	}
	// Brand colours: a partner quote is themed with the partner's colours
	// (snapshotted at issue time); Osoul quotes keep the house navy/red.
	$hex       = function ( $v, $d ) { return ( is_string( $v ) && preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v ) ) ? $v : $d; };
	$c_primary = $hex( is_array( $brand ) ? ( $brand['color'] ?? '' ) : '', '#0d1f3c' );
	$c_accent  = $hex( is_array( $brand ) ? ( $brand['accent'] ?? '' ) : '', '#D21217' );
	$dir         = $ar ? 'rtl' : 'ltr';
	$decided     = in_array( $stage, array( 'won', 'lost' ), true );
	$cur         = $t( 'ر.س', 'SAR' );
	?><!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $dir ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $t( 'عرض سعر', 'Quotation' ) . ' ' . $number ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--n:<?php echo $c_primary; ?>;--o:<?php echo $c_accent; ?>;--line:#e3e7ee;--muted:#6b7280}
body{font-family:<?php echo $ar ? "'Cairo'" : "'IBM Plex Sans','Cairo'"; ?>,sans-serif;background:#eef1f5;color:#1f2937;direction:<?php echo esc_attr( $dir ); ?>;padding:20px;line-height:1.6}
.qbar{max-width:800px;margin:0 auto 14px;display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap}
.qbtn{display:inline-flex;align-items:center;gap:7px;border:none;border-radius:8px;padding:11px 18px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;color:#fff;background:var(--o);min-height:42px}
.qbtn.sec{background:#fff;color:var(--n);border:1px solid var(--line)}
.qbtn.green{background:#16a34a}.qbtn.red{background:#dc2626}
.qlang a{color:var(--n);text-decoration:none;font-weight:700;font-size:13px;padding:6px 10px;border:1px solid var(--line);border-radius:6px;background:#fff}
.qlang a.on{background:var(--n);color:#fff}
.sheet{max-width:800px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:8px;padding:38px 40px;box-shadow:0 6px 24px rgba(13,31,60,.08);position:relative;overflow:hidden}
.sheet::after{content:'';position:absolute;inset:0;background:url('<?php echo esc_url( $logo ); ?>') center/55% no-repeat;opacity:.04;pointer-events:none}
.qhead{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:3px solid var(--n);padding-bottom:16px;margin-bottom:6px;position:relative}
.qhead img{height:74px;width:auto}
.qco{font-size:13px;color:var(--muted);text-align:<?php echo $ar ? 'left' : 'right'; ?>}
.qco b{color:var(--n);font-size:15px;display:block;margin-bottom:4px}
.qtitle{text-align:center;font-size:26px;font-weight:700;color:var(--n);letter-spacing:1px;margin:18px 0 4px}
.qtitle span{color:var(--o)}
.qmeta{display:flex;justify-content:center;gap:24px;flex-wrap:wrap;font-size:13px;color:var(--muted);margin-bottom:20px}
.qmeta b{color:var(--n)}
.qto{background:#f7f9fc;border:1px solid var(--line);border-radius:8px;padding:14px 16px;margin-bottom:18px;font-size:14px}
.qto b{color:var(--n)}
table.qit{width:100%;border-collapse:collapse;font-size:14px;margin-bottom:16px}
table.qit th{background:var(--n);color:#fff;padding:10px 12px;text-align:start;font-weight:600;font-size:13px}
table.qit td{padding:10px 12px;border-bottom:1px solid var(--line)}
.num{font-family:'IBM Plex Sans',sans-serif;font-variant-numeric:tabular-nums;white-space:nowrap}
.qtot{margin-inline-start:auto;max-width:340px;font-size:14px}
.qtot div{display:flex;justify-content:space-between;padding:5px 0}
.qtot .grand{font-weight:700;font-size:18px;color:var(--n);border-top:2px solid var(--n);margin-top:6px;padding-top:10px}
.qtot .grand span:last-child{color:var(--o)}
.qsec{margin-top:22px;font-size:13px}
.qsec h4{font-size:13px;color:var(--o);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.qsec p{color:#374151;white-space:pre-line}
.qfoot{display:flex;justify-content:space-between;align-items:flex-end;margin-top:34px;padding-top:16px;border-top:1px solid var(--line);font-size:13px;color:var(--muted)}
.qsign{text-align:center}.qsign .line{width:160px;border-top:1px solid #9aa3b2;margin-top:40px;padding-top:6px}
.qflash{max-width:800px;margin:0 auto 14px;padding:14px 18px;border-radius:8px;font-weight:700;text-align:center}
.qflash.ok{background:#e7f7ee;color:#1a7e42;border:1px solid #b6e7c9}
.qflash.no{background:#fdeaea;color:#b32d2e;border:1px solid #f3c2c2}
@media print{body{background:#fff;padding:0}.qbar,.qlang{display:none!important}.sheet{box-shadow:none;border:none;border-radius:0;max-width:none;padding:0}}
@media(max-width:560px){.sheet{padding:22px 18px}.qhead{flex-direction:column;align-items:center;text-align:center}.qco{text-align:center}}
</style>
</head>
<body>
	<?php if ( 'accepted' === $flash ) : ?><div class="qflash ok"><?php echo esc_html( $t( 'شكراً لك! تم تأكيد موافقتك على العرض وسيتواصل معك فريقنا.', 'Thank you! Your acceptance has been recorded and our team will contact you.' ) ); ?></div><?php endif; ?>
	<?php if ( 'rejected' === $flash ) : ?><div class="qflash no"><?php echo esc_html( $t( 'تم تسجيل رفض العرض.', 'The quote has been marked as declined.' ) ); ?></div><?php endif; ?>

	<div class="qbar">
		<div style="display:flex;gap:8px">
			<button class="qbtn" onclick="window.print()"><?php echo esc_html( $t( 'تحميل / طباعة PDF', 'Download / Print PDF' ) ); ?></button>
		</div>
		<div class="qlang">
			<a class="<?php echo $ar ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'lang', 'ar' ) ); ?>">عربي</a>
			<a class="<?php echo $ar ? '' : 'on'; ?>" href="<?php echo esc_url( add_query_arg( 'lang', 'en' ) ); ?>">EN</a>
		</div>
	</div>

	<div class="sheet">
		<div class="qhead">
			<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt=""><?php endif; ?>
			<div class="qco">
				<b><?php echo esc_html( $company ); ?></b>
				<?php echo esc_html( $address ); ?><br>
				<?php echo esc_html( $t( 'الرقم الضريبي', 'VAT No.' ) ); ?>: <?php echo esc_html( $vat_no ); ?><br>
				<?php echo esc_html( $t( 'السجل التجاري', 'CR No.' ) ); ?>: <?php echo esc_html( $cr_no ); ?>
			</div>
		</div>

		<div class="qtitle"><?php echo esc_html( $t( 'عرض ', 'QUOTA' ) ); ?><span><?php echo esc_html( $t( 'سعر', 'TION' ) ); ?></span></div>
		<div class="qmeta">
			<span><?php echo esc_html( $t( 'رقم العرض', 'Quote No.' ) ); ?>: <b class="num"><?php echo esc_html( $number ? $number : '—' ); ?></b></span>
			<span><?php echo esc_html( $t( 'التاريخ', 'Date' ) ); ?>: <b class="num"><?php echo esc_html( gmdate( 'Y-m-d', $issued_ts ) ); ?></b></span>
			<span><?php echo esc_html( $t( 'صالح حتى', 'Valid until' ) ); ?>: <b class="num"><?php echo esc_html( $valid_until ); ?></b></span>
		</div>

		<div class="qto">
			<b><?php echo esc_html( $t( 'مقدّم إلى', 'Bill To' ) ); ?>:</b>
			<?php echo esc_html( $cust['name'] ?? '' ); ?>
			<?php if ( ! empty( $cust['phone'] ) ) : ?> &nbsp;|&nbsp; <span class="num"><?php echo esc_html( $cust['phone'] ); ?></span><?php endif; ?>
			<?php if ( ! empty( $cust['email'] ) ) : ?> &nbsp;|&nbsp; <?php echo esc_html( $cust['email'] ); ?><?php endif; ?>
		</div>

		<table class="qit">
			<thead><tr>
				<th style="width:34px">#</th>
				<th><?php echo esc_html( $t( 'المنتج', 'Item' ) ); ?></th>
				<th style="width:70px"><?php echo esc_html( $t( 'الكمية', 'Qty' ) ); ?></th>
				<th style="width:110px"><?php echo esc_html( $t( 'سعر القطعة', 'Unit Price' ) ); ?></th>
				<th style="width:120px"><?php echo esc_html( $t( 'الإجمالي', 'Total' ) ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $items as $i => $it ) :
				$qty  = (int) ( $it['qty'] ?? 0 );
				$unit = (float) ( $it['unit_price'] ?? 0 );
				$name = $ar ? ( $it['name'] ?? '' ) : ( $it['name_en'] ?: ( $it['name'] ?? '' ) );
				?>
				<tr>
					<td class="num"><?php echo (int) $i + 1; ?></td>
					<td><?php echo esc_html( $name ); ?></td>
					<td class="num"><?php echo esc_html( $qty ); ?></td>
					<td class="num"><?php echo esc_html( osoul_money( $unit ) ); ?></td>
					<td class="num"><?php echo esc_html( osoul_money( $unit * $qty ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="qtot">
			<div><span><?php echo esc_html( $t( 'المجموع الفرعي', 'Subtotal' ) ); ?></span><span class="num"><?php echo esc_html( osoul_money( $totals['subtotal'] ) ); ?></span></div>
			<?php if ( $totals['discount'] > 0 ) : ?><div><span><?php echo esc_html( $t( 'الخصم', 'Discount' ) ); ?></span><span class="num">- <?php echo esc_html( osoul_money( $totals['discount'] ) ); ?></span></div><?php endif; ?>
			<div><span><?php echo esc_html( $t( 'ضريبة القيمة المضافة 15%', 'VAT 15%' ) ); ?></span><span class="num"><?php echo esc_html( osoul_money( $totals['vat'] ) ); ?></span></div>
			<div class="grand"><span><?php echo esc_html( $t( 'الإجمالي النهائي', 'Grand Total' ) ); ?></span><span class="num"><?php echo esc_html( osoul_money( $totals['total'] ) . ' ' . $cur ); ?></span></div>
		</div>

		<div class="qsec">
			<h4><?php echo esc_html( $t( 'الحسابات البنكية', 'Bank Accounts' ) ); ?></h4>
			<p><?php echo esc_html( $bank ); ?></p>
		</div>
		<?php if ( $terms ) : ?>
		<div class="qsec">
			<h4><?php echo esc_html( $t( 'الشروط والأحكام', 'Terms & Conditions' ) ); ?></h4>
			<p><?php echo esc_html( $terms ); ?></p>
		</div>
		<?php endif; ?>

		<div class="qfoot">
			<div><?php echo esc_html( $t( 'شكراً لتعاملكم معنا', 'Thank you for your business' ) ); ?></div>
			<div class="qsign"><div class="line"><?php echo esc_html( $t( 'التوقيع / الختم', 'Signature / Stamp' ) ); ?></div></div>
		</div>
	</div>

	<?php if ( ! $decided && $number ) : ?>
	<div class="qbar" style="margin-top:14px">
		<form method="post" style="display:flex;gap:10px;width:100%;justify-content:center">
			<button class="qbtn green" type="submit" name="osoul_decision" value="accept" onclick="return confirm('<?php echo esc_js( $t( 'تأكيد الموافقة على العرض؟', 'Confirm acceptance of this quote?' ) ); ?>')"><?php echo esc_html( $t( 'موافق على العرض', 'Accept Quote' ) ); ?></button>
			<button class="qbtn red" type="submit" name="osoul_decision" value="reject"><?php echo esc_html( $t( 'رفض', 'Decline' ) ); ?></button>
		</form>
	</div>
	<?php endif; ?>
</body>
</html>
	<?php
}
