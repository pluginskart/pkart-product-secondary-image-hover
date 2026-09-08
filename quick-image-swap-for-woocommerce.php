<?php
/**
 * Plugin Name:           Quick Image Swap for WooCommerce
 * Plugin URI:            https://github.com/pluginskart/quick-image-swap-woocommerce
 * Description:           Adds the first WooCommerce gallery image as a hover image inside the product-image block on archive and loop pages.
 * Version:               1.0.1
 * Requires at least:     6.7
 * Requires PHP:          8.1
 * Author:                PluginsKart
 * Author URI:            https://www.pluginskart.com/
 * License:               GPL-2.0-or-later
 * License URI:           https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:           quick-image-swap-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Enqueue plugin CSS.
 */
function pkart_product_image_swap_enqueue_styles() {

	wp_enqueue_style(
		'quick-image-swap-for-woocommerce',
		plugin_dir_url( __FILE__ ) . 'index.css',
		array(),
		'1.0.1'
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
