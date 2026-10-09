<?php

namespace Pkart\LicenseManager\V1\Licensing;

use Pkart\LicenseManager\V1\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pkart\\LicenseManager\\V1\\Licensing\\Admin' ) ) {

	/**
	 * License settings page under Settings.
	 *
	 * Menu label comes from package.json pkart-data.menu_title (falls back to title).
	 * Created automatically by Licensing::init() in wp-admin.
	 */
	class Admin {

		/**
		 * Licensing facade.
		 *
		 * @var Licensing
		 */
		private $licensing;

		/**
		 * Text domain.
		 *
		 * @var string
		 */
		private $domain;

		/**
		 * Menu label.
		 *
		 * @var string
		 */
		private $menu_title;

		/**
		 * Register the license page.
		 */
		public function __construct( Licensing $licensing ) {
			$config = $licensing->package_config();

			$this->licensing  = $licensing;
			$this->domain     = $config->product_sku();
			$this->menu_title = $config->title() ?: $config->product_sku();

			add_action( 'admin_menu', [ $this, 'register_menu' ] );
		}

		/**
		 * Add the license screen under Settings.
		 */
		public function register_menu() {
			add_options_page(
				$this->menu_title,
				$this->menu_title,
				'manage_options',
				'pkart-' . $this->licensing->package_config()->product_sku() . '-license',
				[ $this, 'render_page' ]
			);
		}

		/**
		 * Render the license page.
		 */
		public function render_page() {
			?>
			<div class="wrap">
				<?php $this->render_license_section(); ?>
			</div>
			<?php
		}

		/**
		 * Render the reusable licensing section on any admin page.
		 *
		 * @param array $args Optional title and description overrides.
		 */
		public function render_license_section( array $args = [] ) {
			// Fetch the plugin's licensing instance directly (init() returns the existing one).
			$licensing  = Licensing::init( __FILE__ );
			$config     = $licensing->package_config();
			$domain     = $config->product_sku();
			$menu_title = $config->title() ?: $config->product_sku();

			$args = wp_parse_args(
				$args,
				[
					/* translators: %s: plugin menu title. */
					'title'       => sprintf( __( '%s License', $domain ), $menu_title ),
					'description' => __( 'Activate your license key to enable PRO updates and licensing validation.', $domain ),
				]
			);

			$license = $licensing->get_stored();
			$notice  = $licensing->pop_notice();
			?>
			<h1><?php echo esc_html( $args['title'] ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<p><?php echo esc_html( $args['description'] ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( $licensing->admin_action() ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( $licensing->admin_action() ); ?>">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pkart-license-key"><?php esc_html_e( 'License key', $domain ); ?></label></th>
						<td>
							<input
								type="text"
								class="regular-text"
								id="pkart-license-key"
								name="license_key"
								value="<?php echo esc_attr( $license['license_key'] ?? '' ); ?>"
								autocomplete="off"
							>
							<p class="description"><?php esc_html_e( 'Mock valid key: PKMOCK-2026-TEST-0001', $domain ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<?php if ( $licensing->is_active() ) : ?>
						<button class="button" type="submit" name="license_action" value="deactivate">
							<?php esc_html_e( 'Deactivate License', $domain ); ?>
						</button>
					<?php else : ?>
						<button class="button button-primary" type="submit" name="license_action" value="activate">
							<?php esc_html_e( 'Activate License', $domain ); ?>
						</button>
					<?php endif; ?>
				</p>
			</form>
			<?php
		}
	}
}
