<?php
/**
 * Plugin Name:           Quick Image Swap for WooCommerce
 * Plugin URI:            https://github.com/pluginskart/quick-image-swap-woocommerce
 * Description:           Automatically displays the first WooCommerce product gallery image when hovering over product images.
 * Version:               1.0.0
 * Requires at least:     6.7
 * Requires PHP:          8.1
 * Author:                PluginsKart
 * Author URI:            https://www.pluginskart.com/
 * License:               GPL-2.0-or-later
 * License URI:           https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:           quick-image-swap-for-woocommerce
 * Requires Plugins:      woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PKART_QUICK_IMAGE_SWAP_DIR', plugin_dir_path( __FILE__ ) );

require_once PKART_QUICK_IMAGE_SWAP_DIR . 'includes/licensing/class-package-config.php';
require_once PKART_QUICK_IMAGE_SWAP_DIR . 'includes/licensing/class-api.php';
require_once PKART_QUICK_IMAGE_SWAP_DIR . 'includes/licensing/class-licensing.php';

/**
 * Initialize the Pkart licensing API for this plugin.
 * @return \Pkart\LicenseManager\V1\Licensing
 */
$pkart_quick_image_swap_licensing = \Pkart\LicenseManager\V1\Licensing::init( __FILE__ );

/**
 * Initialize the admin settings page for this plugin.
 */
if ( is_admin() ) {
	require_once PKART_QUICK_IMAGE_SWAP_DIR . 'includes/class-admin.php';
	new \Pkart\QuickImageSwap\Admin( $pkart_quick_image_swap_licensing );
}


/**
 * Enqueue plugin CSS.
 */
function pkart_product_image_swap_enqueue_styles() {

	wp_enqueue_style(
		'quick-image-swap-for-woocommerce',
		plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
		array(),
		'1.0.0'
	);
}

add_action( 'wp_enqueue_scripts', 'pkart_product_image_swap_enqueue_styles' );


/**
 * Append the first gallery image after the main product thumbnail
 * inside the woocommerce/product-image block, enabling CSS hover-swap effects.
 *
 * Works with Product Collection (block context postId) and the classic loop.
 *
 * @param string   $block_content   Rendered HTML.
 * @param array    $block           Parsed block data.
 * @param WP_Block $block_instance  Block instance (carries context).
 * @return string
 */
function pkart_product_image_append_gallery( $block_content, $block, $block_instance ) {

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
	'pkart_product_image_append_gallery',
	10,
	3
);
