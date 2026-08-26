=== Quick Image Swap for WooCommerce ===

Contributors: PluginsKart
Tags: woocommerce, product image, product gallery, hover image, product hover
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds the first WooCommerce gallery image as a secondary product image on archive and loop pages, displayed on hover using the plugin's built-in CSS.

== Description ==

Pkart Woo Image Swap adds the first WooCommerce gallery image as a secondary image inside the product image on WooCommerce archive and product loop pages.

The secondary image is inserted after the main product image and receives the CSS class `product-secondary-image`.

The plugin includes and automatically loads the CSS required to display the secondary image when the user hovers over the product image. No additional CSS configuration is required.

The plugin supports WooCommerce Product Collection blocks and the classic WooCommerce product loop. It does not modify the single-product page.

The plugin does not require JavaScript. The hover effect is handled entirely with CSS.

== How It Works ==

Pkart Woo Image Swap works by detecting WooCommerce product image blocks on archive and product loop pages.

When a product has gallery images, the plugin:

1. Gets the current product from the WooCommerce product context.
2. Retrieves the product's gallery image IDs.
3. Selects the first image from the gallery.
4. Generates the secondary image using the WooCommerce image size configured by the plugin.
5. Adds the `product-secondary-image` CSS class to the secondary image.
6. Inserts the secondary image after the main product image.
7. Loads the plugin's built-in CSS.
8. Displays the secondary image when the user hovers over the product image.

The plugin supports both WooCommerce Product Collection blocks and the classic WooCommerce product loop.

The plugin does not modify the single-product page and does not require JavaScript. The hover behavior is controlled using the CSS included with the plugin.

== Features ==

* Adds the first product gallery image to product loop images.
* Supports WooCommerce Product Collection blocks.
* Supports the classic WooCommerce product loop.
* Excludes the single-product page.
* Uses the `woocommerce_single` image size.
* Adds the `product-secondary-image` CSS class.
* Includes built-in CSS for the hover image effect.
* Automatically loads the plugin CSS.
* Does not add an image when the product has no gallery images.
* Does not require JavaScript.
* Works with CSS-based hover effects.

== Requirements ==

* WordPress 6.5 or later
* WooCommerce
* PHP 8.1 or later

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory.
2. Activate Pkart Woo Image Swap from Plugins > Installed Plugins.
3. Visit a WooCommerce archive or product loop page.
4. Hover over a product image to see the first gallery image.

No additional CSS configuration is required.

== Frequently Asked Questions ==

= Does the plugin work with WooCommerce Product Collection blocks? =

Yes. The plugin supports WooCommerce Product Collection blocks.

= Does the plugin work with the classic WooCommerce product loop? =

Yes. The plugin supports the classic WooCommerce product loop.

= Does the plugin work on single product pages? =

No. The plugin does not add the secondary gallery image to the single-product page.

= What happens if a product has no gallery images? =

No secondary image is added when the product does not have any gallery images.

= Which gallery image is displayed? =

The first image from the product's WooCommerce gallery is used as the secondary image.

= Does the plugin require JavaScript? =

No. The plugin does not require JavaScript. The hover effect is handled entirely with CSS.

= Do I need to add CSS manually? =

No. The plugin includes and automatically loads the CSS required for the hover image effect.

= Can I customize the hover effect? =

Yes. The secondary image uses the `product-secondary-image` CSS class, so you can override or customize its appearance and transition using your theme's CSS.

== Changelog ==

= 1.0.1 =
* Added support for WooCommerce Product Collection blocks.
* Added support for the classic WooCommerce product loop.
* Added the first gallery image as a secondary product image.
* Added the `product-secondary-image` CSS class.
* Added built-in CSS for the hover effect.
* Excluded the single-product page from image swapping.
