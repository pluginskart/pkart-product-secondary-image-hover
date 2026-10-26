<?php

namespace Pkart\LicenseManager\V1;

use Pkart\LicenseManager\V1\Licensing\API;
use Pkart\LicenseManager\V1\Licensing\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-api.php';
require_once __DIR__ . '/class-updater.php';

if ( ! class_exists( 'Pkart\\LicenseManager\\V1\\Licensing' ) ) {

	/**
	 * Sends plugin lifecycle events to the Pkart API.
	 */
	class Licensing {

		/**
		 * Main plugin file (passed from the main plugin file).
		 *
		 * @var string
		 */
		private $plugin_file;

		/**
		 * Product SKU.
		 *
		 * @var string
		 */
		private $product_sku;

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		private $version;

		/**
		 * Option key for storing license state.
		 *
		 * @var string
		 */
		private $option_key;

		/**
		 * Update checker.
		 *
		 * @var Updater
		 */
		private $updater;

		/**
		 * @param string $plugin_file Main plugin file (pass __FILE__ from the main plugin file).
		 * @param string $product_sku Product SKU.
		 * @param string $version     Plugin version.
		 */
		public function __construct( $plugin_file, $product_sku, $version ) {

			$this->plugin_file = $plugin_file;
			$this->product_sku = $product_sku;
			$this->version     = $version;
			$this->option_key  = $product_sku . '_license';

			register_activation_hook(
				$this->plugin_file,
				[ $this, 'plugin_activate' ]
			);

			register_deactivation_hook(
				$this->plugin_file,
				[ $this, 'plugin_deactivate' ]
			);

			add_action( 'admin_post_' . $this->product_sku . '_license', [ $this, 'handle_license_action' ] );

			$this->updater = new Updater( $this, $this->plugin_file, $this->version );

		}

		/**
		 * Plugin activation callback.
		 */
		public function plugin_activate() {
			$this->register_plugin_event( 'activate' );
		}

		/**
		 * Plugin deactivation callback.
		 */
		public function plugin_deactivate() {
			$this->register_plugin_event( 'deactivate' );
		}

		/**
		 * Ask the API whether an update is available for this license.
		 */
		public function plugin_update_checker() {
			$license_key = $this->get_stored()['pkart_data']['license_key'] ?? '';
			if ( ! $license_key ) {
				return $this->error( 'pkart_license_missing', __( 'No active license for update checks.', $this->product_sku ) );
			}

			$data = [
				'license_key' => $license_key,
				'product_sku' => $this->product_sku,
				'version'     => $this->version,
			];
			return ( new API() )->request( 'plugin', 'update-checker', $data );
		}

		/**
		 * Send a plugin activate/deactivate lifecycle event.
		 *
		 * @param string $action 'activate' or 'deactivate'.
		 * @return array|\WP_Error
		 */
		public function register_plugin_event( $action ) {
			$data = $this->get_pluginskart_data();
			return ( new API() )->request( 'plugin', $action, $data );
		}

		/**
		 * Handle the license form submitted to admin-post.php.
		 */
		public function handle_license_action() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to manage this license.', $this->product_sku ) );
			}

			check_admin_referer( $this->product_sku . '_license' );

			$action      = sanitize_key( wp_unslash( $_POST['license_action'] ?? '' ) );
			$license_key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );

			$result = $this->process_license_action( $action, $license_key );

			$this->set_notice( $result );

			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		/**
		 * Run one license action. Also usable by other plugin integrations.
		 *
		 * @param string $action      activate, validate, or deactivate.
		 * @return array|\WP_Error
		 */
		public function process_license_action( $action, $license_key = '' ) {
			switch ( $action ) {
				case 'activate':
					return $this->activate( $license_key );

				case 'deactivate':
					return $this->deactivate();

				default:
					return $this->error( 'pkart_invalid_license_action', __( 'Invalid license action.', $this->product_sku ) );
			}
		}

		/**
		 * Activate a license key for this site and store the result.
		 */
		public function activate( $license_key ) {
			$license_key = sanitize_text_field( $license_key );
			if ( ! $license_key ) {
				return $this->error( 'pkart_license_missing', __( 'Please enter a license key.', $this->product_sku ) );
			}
			$response = $this->license_request( 'activate', $license_key );
			if ( ! is_wp_error( $response ) && ! empty( $response['success'] ) ) {
				$this->store( $license_key );
			}
			return $response;
		}

		/**
		 * Deactivate the stored license key for this site and clear local state.
		 */
		public function deactivate() {
			delete_option( $this->option_key );
			return $this->license_request( 'deactivate' );
		}

		/**
		 * Send a license request with the common payload.
		 */
		private function license_request( $action, $license_key = null ) {
			$data = $this->get_pluginskart_data( $license_key );
			return ( new API() )->request( 'license', $action, $data );
		}

		/**
		 * Get common plugin environment data.
		 *
		 * @param string|null $license_key Key to send; null reads the stored key.
		 */
		private function get_pluginskart_data( $license_key = null ): array {
			return [
				'license_key' => $license_key ?? ( $this->get_stored()['pkart_data']['license_key'] ?? '' ),
				'product_sku' => $this->product_sku,
				'version'     => $this->version,
				'site_url'    => esc_url_raw( home_url() ),
				'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
				'wp_version'  => get_bloginfo( 'version' ),
				'php_version' => PHP_VERSION,
				'wc_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'multisite'   => is_multisite() ? 1 : 0,
				'language'    => get_locale(),
				'environment' => self::get_environment_type(),
			];
		}

		/**
		 * Detect the site environment.
		 * @return string 'local', 'staging', 'development' or 'production'.
		 */
		public static function get_environment_type() {
			$env = wp_get_environment_type();

			if ( 'production' !== $env ) {
				return $env;
			}

			$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

			if ( in_array( $host, [ 'localhost', '127.0.0.1' ], true )
				|| preg_match( '/\.(local|test|localhost|dev\.cc)$/', $host ) ) {
				return 'local';
			}

			if ( preg_match( '/^(staging|stage|dev)\./', $host ) ) {
				return 'staging';
			}

			return 'production';
		}

		/**
		 * Persist a license key after a successful activation; the status is always stored as active.
		 */
		private function store( $license_key ) {
			update_option(
				$this->option_key,
				[
					'pkart_data' => $this->get_pluginskart_data( $license_key ),
					'status'     => 'active',
					'updated_at' => time(),
				],
				false
			);
		}

		/**
		 * Return the locally stored license state.
		 */
		public function get_stored() {
			$license = get_option( $this->option_key, [] );
			return is_array( $license ) ? $license : [];
		}

		/**
		 * Keep the result of the last license action for the current user.
		 *
		 * @param array|\WP_Error $result Response from process_license_action().
		 */
		private function set_notice( array|\WP_Error $result ): void {
			$notice = $result instanceof \WP_Error
				? [ 'type' => 'error', 'message' => $result->get_error_message() ]
				: [
					'type'    => empty( $result['success'] ) ? 'error' : 'success',
					'message' => (string) ( $result['message'] ?? __( 'The licensing API returned an unexpected response.', $this->product_sku ) ),
				];

			set_transient( $this->notice_key(), $notice, MINUTE_IN_SECONDS );
		}

		/**
		 * Per-user transient key for license notices.
		 */
		private function notice_key(): string {
			return "{$this->option_key}_notice_" . get_current_user_id();
		}

		/**
		 * Build a WP_Error.
		 */
		private function error( $code, $message, $data = '' ) {
			return new \WP_Error( $code, $message, $data );
		}
	}
}
