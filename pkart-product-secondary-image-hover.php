<?php
/**
 * Plugin Name:       Pkart Product Secondary Image Hover
 * Plugin URI:        https://github.com/pluginskart/pkart-product-secondary-image-hover
 * Description:       Automatically displays the first WooCommerce product gallery image when hovering over product images.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            PluginsKart
 * Author URI:        https://www.pluginskart.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pkart-product-secondary-image-hover
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU     = 'pkart-product-secondary-image-hover';
const PKART_PRODUCT_SECONDARY_IMAGE_HOVER_VERSION = '1.0.0';

if ( ! class_exists( '\Pkart\LicenseManager\V1\Licensing', false ) ) {
	require_once __DIR__ . '/includes/licensing/class-licensing.php';
}

// Main plugin file
new \Pkart\LicenseManager\V1\Licensing( __FILE__, PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU, PKART_PRODUCT_SECONDARY_IMAGE_HOVER_VERSION );

/**
 * Add the license screen under Settings.
 */
function pkart_product_secondary_image_hover_register_menu(): void {
	add_options_page(
		'Product Secondary Image Hover License',
		'Product Secondary Image Hover License',
		'manage_options',
		PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU . '-license',
		'pkart_product_secondary_image_hover_render_license_page'
	);
}
add_action( 'admin_menu', 'pkart_product_secondary_image_hover_register_menu' );

/**
 * Render the license page.
 */
function pkart_product_secondary_image_hover_render_license_page(): void {
	$action    = PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU . '_license';
	$is_active = get_option( $action, [] )['status'] ?? false;
	$field_id  = PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU . '-license-key';
	?>
	<div class="wrap">
	<h1><?php esc_html_e( 'Product Secondary Image Hover', PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU ); ?></h1>
	<p><?php esc_html_e( 'Activate your license key to enable PRO updates and licensing validation', PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU ); ?></p>
	<?php
	$notice_key = $action . '_notice_' . get_current_user_id();
	$notice     = get_transient( $notice_key );
	if ( is_array( $notice ) ) {
		delete_transient( $notice_key );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( 'success' === ( $notice['type'] ?? '' ) ? 'success' : 'error' ),
			esc_html( $notice['message'] ?? '' )
		);
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( $action ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'License key', PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="<?php echo esc_attr( $field_id ); ?>" name="license_key" value="<?php echo esc_attr( get_option( $action, [] )['pkart_data']['license_key'] ?? '' ); ?>" autocomplete="off" <?php echo $is_active ? 'readonly' : ''; ?>>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button
				type="submit"
				class="<?php echo esc_attr( $is_active ? 'button' : 'button button-primary' ); ?>"
				name="license_action"
				value="<?php echo esc_attr( $is_active ? 'deactivate' : 'activate' ); ?>"
			>
				<?php echo esc_html( $is_active ? __( 'Deactivate License', PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU ) : __( 'Activate License', PKART_PRODUCT_SECONDARY_IMAGE_HOVER_SKU ) ); ?>
			</button>
		</p>
	</form>
	</div>
	<?php
}

/**
 * Enqueue plugin CSS.
 */
function pkart_product_secondary_image_hover_enqueue_styles() {

	wp_enqueue_style(
		'pkart-product-secondary-image-hover',
		plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
		array(),
		'1.0.0'
	);
}
add_action( 'wp_enqueue_scripts', 'pkart_product_secondary_image_hover_enqueue_styles' );


/**
 * Append the first gallery image after the main product thumbnail
 * inside the WooCommerce product image block.
 *
 * Works with Product Collection blocks and classic product loops.
 *
 * @param string   $block_content   Rendered HTML.
 * @param array    $block           Parsed block data.
 * @param WP_Block $block_instance  Block instance.
 * @return string
 */
function pkart_product_secondary_image_hover_append_gallery( $block_content, $block, $block_instance) {

	// Only target the WooCommerce product image block.
	if (
		empty( $block['blockName'] ) ||
		'woocommerce/product-image' !== $block['blockName'] ||
		is_product()
	) {
		return $block_content;
	}

	// Make sure WooCommerce is available.
	if ( ! function_exists( 'wc_get_product' ) ) {
		return $block_content;
	}

	// Product Collection passes postId via block context.
	// Classic loops use the current post ID.
	$post_id = $block_instance->context['postId'] ?? get_the_ID();

	$product = wc_get_product( $post_id );

	if ( ! $product ) {
		return $block_content;
	}

	// Get product gallery images.
	$gallery_ids = $product->get_gallery_image_ids();

	if ( empty( $gallery_ids ) ) {
		return $block_content;
	}

	// Use the first gallery image as the hover image.
	$second_img = wp_get_attachment_image(
		$gallery_ids[0],
		'woocommerce_single',
		false,
		array(
			'class' => 'product-secondary-image',
		)
	);

	if ( empty( $second_img ) ) {
		return $block_content;
	}

	// Append the secondary image immediately after the first <img>.
	$result = preg_replace_callback(
		'/(<img\b[^>]*\/?>)/i',
		function ( $matches ) use ( $second_img ) {
			return $matches[1] . $second_img;
		},
		$block_content,
		1
	);

	return $result ?? $block_content;
}

add_filter(
	'render_block',
	'pkart_product_secondary_image_hover_append_gallery',
	10,
	3
);
