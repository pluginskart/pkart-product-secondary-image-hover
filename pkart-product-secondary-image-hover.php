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

if ( ! class_exists( '\Pkart\LicenseManager\V1\Licensing', false ) ) {
	require_once __DIR__ . '/includes/licensing/class-api.php';
	require_once __DIR__ . '/includes/licensing/class-licensing.php';
}

// Main plugin file
\Pkart\LicenseManager\V1\Licensing::init( __FILE__, 'pkart-product-secondary-image-hover', '1.0.0' );


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
function pkart_product_secondary_image_hover_append_gallery(
	$block_content,
	$block,
	$block_instance
) {

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
