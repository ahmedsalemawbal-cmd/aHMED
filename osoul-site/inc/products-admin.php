<?php
/**
 * Custom "Site Products" dashboard — a standalone admin page where a staff
 * member adds / edits / deletes products (with an image picked from the media
 * library) and they appear on the site automatically.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Top-level admin menu.
 */
add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'Site Products', 'osoul' ),
		__( 'منتجات الموقع', 'osoul' ),
		'manage_options',
		'osoul-products',
		'osoul_products_admin_page',
		'dashicons-archive',
		25
	);
} );

/**
 * Load the media library only on our page.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_osoul-products' === $hook ) {
		wp_enqueue_media();
	}
} );

/**
 * Group options for the select boxes.
 *
 * @return array<string,string>
 */
function osoul_product_group_choices() {
	$out = array();
	foreach ( osoul_group_labels() as $key => $label ) {
		$out[ $key ] = $label['ar'] . ' / ' . $label['en'];
	}
	return $out;
}

/**
 * Render the dashboard page (list / add / edit).
 */
function osoul_products_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
	$base   = admin_url( 'admin.php?page=osoul-products' );

	// Notices.
	if ( isset( $_GET['msg'] ) ) {
		$msgs = array(
			'saved'   => array( 'success', __( 'تم حفظ المنتج بنجاح.', 'osoul' ) ),
			'deleted' => array( 'success', __( 'تم حذف المنتج.', 'osoul' ) ),
			'error'   => array( 'error', __( 'تعذّر حفظ المنتج. تأكّد من البيانات وحاول مرة أخرى.', 'osoul' ) ),
		);
		$m = sanitize_key( $_GET['msg'] );
		if ( isset( $msgs[ $m ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $msgs[ $m ][0] ) . ' is-dismissible"><p>' . esc_html( $msgs[ $m ][1] ) . '</p></div>';
		}
	}

	if ( 'edit' === $action || 'new' === $action ) {
		osoul_products_admin_form( $base );
		return;
	}

	osoul_products_admin_list( $base );
}

/**
 * The products table.
 *
 * @param string $base
 */
function osoul_products_admin_list( $base ) {
	$q = new WP_Query( array(
		'post_type'      => 'osoul_product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
		'no_found_rows'  => true,
	) );
	$labels = osoul_group_labels();
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'منتجات الموقع', 'osoul' ); ?></h1>
		<a href="<?php echo esc_url( add_query_arg( 'action', 'new', $base ) ); ?>" class="page-title-action"><?php esc_html_e( 'أضف منتج جديد', 'osoul' ); ?></a>
		<hr class="wp-header-end">
		<p><?php esc_html_e( 'هذه المنتجات تظهر مباشرة في صفحات الموقع (الكتالوج، الأقسام، صفحة المنتج).', 'osoul' ); ?></p>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr>
				<th style="width:64px"><?php esc_html_e( 'الصورة', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الاسم', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الفئة', 'osoul' ); ?></th>
				<th><?php esc_html_e( 'الرابط (slug)', 'osoul' ); ?></th>
				<th style="width:160px"><?php esc_html_e( 'إجراءات', 'osoul' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $q->posts ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'لا توجد منتجات بعد.', 'osoul' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $q->posts as $post ) :
				$group = get_post_meta( $post->ID, '_osoul_group', true );
				$gl    = $labels[ $group ] ?? array( 'ar' => $group, 'en' => '' );
				$img   = get_post_meta( $post->ID, '_osoul_img', true );
				if ( ! $img ) { $img = get_the_post_thumbnail_url( $post->ID, 'thumbnail' ); }
				$edit  = add_query_arg( array( 'action' => 'edit', 'id' => $post->ID ), $base );
				$del   = wp_nonce_url( admin_url( 'admin-post.php?action=osoul_delete_product&id=' . $post->ID ), 'osoul_delete_' . $post->ID );
				?>
				<tr>
					<td><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="" style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #ddd"><?php endif; ?></td>
					<td><strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( get_post_meta( $post->ID, '_osoul_name_ar', true ) ?: $post->post_title ); ?></a></strong><br><span style="color:#777"><?php echo esc_html( get_post_meta( $post->ID, '_osoul_name_en', true ) ); ?></span></td>
					<td><?php echo esc_html( $gl['ar'] ); ?></td>
					<td><code><?php echo esc_html( $post->post_name ); ?></code></td>
					<td>
						<a href="<?php echo esc_url( $edit ); ?>" class="button button-small"><?php esc_html_e( 'تعديل', 'osoul' ); ?></a>
						<a href="<?php echo esc_url( $del ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'حذف هذا المنتج؟', 'osoul' ) ); ?>')" style="color:#b32d2e"><?php esc_html_e( 'حذف', 'osoul' ); ?></a>
						<a href="<?php echo esc_url( home_url( '/' . $post->post_name . '/' ) ); ?>" class="button button-small" target="_blank"><?php esc_html_e( 'عرض', 'osoul' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * The add / edit form.
 *
 * @param string $base
 */
function osoul_products_admin_form( $base ) {
	$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$post = $id ? get_post( $id ) : null;
	$is_edit = ( $post && 'osoul_product' === $post->post_type );

	$g = function ( $key, $default = '' ) use ( $id ) {
		return $id ? ( get_post_meta( $id, '_osoul_' . $key, true ) ?: $default ) : $default;
	};
	$slug = $is_edit ? $post->post_name : '';
	$img  = $g( 'img' );
	?>
	<div class="wrap">
		<h1><?php echo $is_edit ? esc_html__( 'تعديل منتج', 'osoul' ) : esc_html__( 'إضافة منتج جديد', 'osoul' ); ?></h1>
		<p><a href="<?php echo esc_url( $base ); ?>">&larr; <?php esc_html_e( 'العودة لقائمة المنتجات', 'osoul' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="osoul_save_product">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $id ); ?>">
			<?php wp_nonce_field( 'osoul_save_product' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th><label><?php esc_html_e( 'الفئة', 'osoul' ); ?> *</label></th>
					<td><select name="group">
						<?php foreach ( osoul_product_group_choices() as $k => $lab ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $g( 'group', 'doors' ), $k ); ?>><?php echo esc_html( $lab ); ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'الاسم (عربي)', 'osoul' ); ?> *</label></th>
					<td><input type="text" name="name_ar" value="<?php echo esc_attr( $g( 'name_ar' ) ); ?>" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'الاسم (إنجليزي)', 'osoul' ); ?></label></th>
					<td><input type="text" name="name_en" value="<?php echo esc_attr( $g( 'name_en' ) ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'الرابط (slug)', 'osoul' ); ?></label></th>
					<td><input type="text" name="slug" value="<?php echo esc_attr( $slug ); ?>" class="regular-text" placeholder="fire-resistant-doors">
						<p class="description"><?php esc_html_e( 'بالإنجليزية وبدون مسافات. اتركه فارغاً ليتولّد تلقائياً. هذا هو رابط صفحة المنتج.', 'osoul' ); ?></p></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'سطر وصف قصير (عربي)', 'osoul' ); ?></label></th>
					<td><input type="text" name="sub_ar" value="<?php echo esc_attr( $g( 'sub_ar' ) ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'سطر وصف قصير (إنجليزي)', 'osoul' ); ?></label></th>
					<td><input type="text" name="sub_en" value="<?php echo esc_attr( $g( 'sub_en' ) ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'السعر الافتراضي للقطعة (ر.س)', 'osoul' ); ?></label></th>
					<td><input type="number" step="0.01" min="0" name="price" value="<?php echo esc_attr( $g( 'price' ) ); ?>" class="regular-text" placeholder="0.00">
						<p class="description"><?php esc_html_e( 'يُستخدم كسعر مبدئي عند تسعير عروض الأسعار، ويقدر الموظف يعدّله في كل عرض.', 'osoul' ); ?></p></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'الصورة', 'osoul' ); ?></label></th>
					<td>
						<input type="hidden" id="osoul_img" name="img" value="<?php echo esc_attr( $img ); ?>">
						<div id="osoul_img_preview" style="margin-bottom:8px"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" style="max-width:160px;height:auto;border:1px solid #ddd;background:#fff;padding:4px"><?php endif; ?></div>
						<button type="button" class="button" id="osoul_img_pick"><?php esc_html_e( 'اختر صورة من المكتبة', 'osoul' ); ?></button>
						<button type="button" class="button" id="osoul_img_clear"><?php esc_html_e( 'إزالة', 'osoul' ); ?></button>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'الوصف الكامل (عربي)', 'osoul' ); ?></label></th>
					<td><textarea name="desc_ar" rows="3" class="large-text"><?php echo esc_textarea( $g( 'desc_ar' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'الوصف الكامل (إنجليزي)', 'osoul' ); ?></label></th>
					<td><textarea name="desc_en" rows="3" class="large-text"><?php echo esc_textarea( $g( 'desc_en' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'المواصفات (عربي)', 'osoul' ); ?></label></th>
					<td><textarea name="specs_ar" rows="5" class="large-text" placeholder="مقاومة الحريق: 30-120 دقيقة&#10;السماكة: 1.2 مم"><?php echo esc_textarea( $g( 'specs_ar' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'مواصفة واحدة في كل سطر. يُفضّل صيغة "العنوان: القيمة".', 'osoul' ); ?></p></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'المواصفات (إنجليزي)', 'osoul' ); ?></label></th>
					<td><textarea name="specs_en" rows="5" class="large-text" placeholder="Fire rating: 30-120 min&#10;Thickness: 1.2 mm"><?php echo esc_textarea( $g( 'specs_en' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One spec per line, same order as the Arabic specs.', 'osoul' ); ?></p></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'شارة (عربي)', 'osoul' ); ?></label></th>
					<td><input type="text" name="badge_ar" value="<?php echo esc_attr( $g( 'badge_ar' ) ); ?>" class="regular-text" placeholder="الأكثر طلباً">
						<p class="description"><?php esc_html_e( 'اختياري — تظهر كملصق صغير على الصورة.', 'osoul' ); ?></p></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'شارة (إنجليزي)', 'osoul' ); ?></label></th>
					<td><input type="text" name="badge_en" value="<?php echo esc_attr( $g( 'badge_en' ) ); ?>" class="regular-text" placeholder="Best Seller"></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'الشهادات', 'osoul' ); ?></label></th>
					<td><input type="text" name="certs" value="<?php echo esc_attr( $g( 'certs' ) ); ?>" class="large-text" placeholder="ISO 9001, SASO, Made in KSA">
						<p class="description"><?php esc_html_e( 'اختياري — افصل بينها بفاصلة.', 'osoul' ); ?></p></td>
				</tr>
			</tbody></table>
			<?php submit_button( $is_edit ? __( 'حفظ التعديلات', 'osoul' ) : __( 'إضافة المنتج', 'osoul' ) ); ?>
		</form>
	</div>
	<script>
	jQuery(function($){
		var frame;
		$('#osoul_img_pick').on('click', function(e){
			e.preventDefault();
			if(frame){ frame.open(); return; }
			frame = wp.media({ title: '<?php echo esc_js( __( 'اختر صورة المنتج', 'osoul' ) ); ?>', button:{ text: '<?php echo esc_js( __( 'استخدم هذه الصورة', 'osoul' ) ); ?>' }, multiple:false });
			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				var url = (att.sizes && att.sizes.large) ? att.sizes.large.url : att.url;
				$('#osoul_img').val(url);
				$('#osoul_img_preview').html('<img src="'+url+'" style="max-width:160px;height:auto;border:1px solid #ddd;background:#fff;padding:4px">');
			});
			frame.open();
		});
		$('#osoul_img_clear').on('click', function(e){
			e.preventDefault();
			$('#osoul_img').val('');
			$('#osoul_img_preview').empty();
		});
	});
	</script>
	<?php
}

/* =========================================================
 * Save / delete handlers (admin-post)
 * ========================================================= */

add_action( 'admin_post_osoul_save_product', 'osoul_handle_save_product' );
if ( ! function_exists( 'osoul_handle_save_product' ) ) {
	function osoul_handle_save_product() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'غير مصرّح.', 'osoul' ) );
		}
		check_admin_referer( 'osoul_save_product' );

		$id    = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$name  = sanitize_text_field( wp_unslash( $_POST['name_ar'] ?? '' ) );
		$slug  = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
		if ( ! $slug ) {
			$slug = sanitize_title( $name );
		}

		$postarr = array(
			'post_type'   => 'osoul_product',
			'post_status' => 'publish',
			'post_title'  => $name,
		);
		if ( $slug ) {
			$postarr['post_name'] = $slug;
		}

		if ( $id ) {
			$postarr['ID'] = $id;
			$id = wp_update_post( $postarr );
		} else {
			$id = wp_insert_post( $postarr );
		}

		// Report the real outcome — the old code always said "saved" even when
		// wp_insert_post() failed, hiding the error from the user.
		if ( ! $id || is_wp_error( $id ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=osoul-products&msg=error' ) );
			exit;
		}

		foreach ( osoul_product_meta_fields() as $f ) {
			$val = wp_unslash( $_POST[ $f ] ?? '' );
			if ( in_array( $f, array( 'specs_ar', 'specs_en', 'desc_ar', 'desc_en' ), true ) ) {
				$val = sanitize_textarea_field( $val );
			} elseif ( 'img' === $f ) {
				$val = esc_url_raw( $val );
			} elseif ( 'group' === $f ) {
				$val = in_array( $val, array( 'doors', 'gypsum', 'strut' ), true ) ? $val : 'doors';
			} else {
				$val = sanitize_text_field( $val );
			}
			update_post_meta( $id, '_osoul_' . $f, $val );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=osoul-products&msg=saved' ) );
		exit;
	}
}

add_action( 'admin_post_osoul_delete_product', 'osoul_handle_delete_product' );
if ( ! function_exists( 'osoul_handle_delete_product' ) ) {
	function osoul_handle_delete_product() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'غير مصرّح.', 'osoul' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'osoul_delete_' . $id );
		if ( $id ) {
			wp_trash_post( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=osoul-products&msg=deleted' ) );
		exit;
	}
}
