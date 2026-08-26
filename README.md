=== Quick Image Swap for WooCommerce ===

Contributors: PluginsKart
Tags: woocommerce, product image, product gallery, hover image, product hover
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

=== Description ===

Pkart Woo Image Swap adds the first WooCommerce gallery image as a secondary image inside the product image on WooCommerce archive and product loop pages.

The secondary image is inserted after the main product image and receives the CSS class `product-secondary-image`.

The plugin includes its own CSS to display the secondary gallery image when the user hovers over the product image.

The plugin supports WooCommerce Product Collection blocks and the classic WooCommerce product loop. It does not modify the single-product page.

The plugin does not require JavaScript. The hover effect is handled entirely with CSS.

=== How It Works ===

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

=== Features ===

- Adds the first product gallery image to product loop images.
- Supports WooCommerce Product Collection blocks.
- Supports the classic WooCommerce product loop.
- Excludes the single-product page.
- Uses the `woocommerce_thumbnail` image size.
- Adds the `product-secondary-image` CSS class.
- Includes built-in CSS for the hover image effect.
- Automatically loads the plugin CSS.
- Does not add an image when the product has no gallery images.
- Does not require JavaScript.
- Works with CSS-based hover effects.

=== Requirements ===

- WordPress 6.5 or later
- WooCommerce
- PHP 8.1 or later

=== Installation ===

1. Upload the plugin to the `/wp-content/plugins/` directory.
2. Activate Pkart Woo Image Swap from Plugins > Installed Plugins.
3. Visit a WooCommerce archive or product loop page.
4. Hover over a product image to see the first gallery image.

No additional CSS configuration is required.

=== Frequently Asked Questions ===

= Do I need to add custom CSS? =

No. The plugin includes and automatically loads the CSS required for the hover image effect.

= Does the plugin use JavaScript? =

No. The plugin uses CSS for the hover effect.

= Does it work with WooCommerce Product Collection blocks? =

Yes. The plugin supports WooCommerce Product Collection blocks.

= Does it work with the classic WooCommerce product loop? =

Yes. The plugin supports the classic WooCommerce product loop.

= Does it change the single product page? =

No. The plugin intentionally excludes the single-product page.

= What image is displayed on hover? =

The first image from the product's WooCommerce gallery is displayed as the secondary image.

= What happens if a product has no gallery images? =

The plugin does not add a secondary image when the product has no gallery images.
