<?php

namespace Pkart\LicenseManager\V1;

use Pkart\LicenseManager\V1\Licensing\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pkart\\LicenseManager\\V1\\Licensing' ) ) {

	/**
	 * Sends plugin lifecycle events to the Pkart API.
	 */
	class Licensing {

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
		 * @param string $product_sku Product SKU.
		 * @param string $version     Plugin version.
		 */
		public function __construct($file, $product_sku, $version ) {
			$this->product_sku = $product_sku;
			$this->version     = $version;
			register_activation_hook( $file,  $this->register_plugin_event( 'activate' ) );
			register_deactivation_hook( $file, $this->register_plugin_event( 'deactivate' ) );
		}

		/**
		 * Send a plugin activate/deactivate lifecycle event.
		 *
		 * @param string $action 'activate' or 'deactivate'.
		 * @return array|\WP_Error
		 */
		public function register_plugin_event( $action ) {
			$data = [
				'license_key' => 'PKMOCK-2026-TEST-0001',
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

			// $write_plugins = fopen('plugins-register-log.txt', 'a');
			// fwrite($write_plugins, print_r($data, true) . PHP_EOL);
			// fclose($write_plugins);

			return ( new API() )->request( 'plugin', $action, $data );
		}

		/**
		 * Activate a license key for this site and store the result.
		 *
		 * @param string $license_key License key.
		 * @return array|\WP_Error
		 */
		public function activate( $license_key ) {
			$license_key = sanitize_text_field( $license_key );

			if ( ! $license_key ) {
				return $this->error( 'pkart_license_missing', __( 'Please enter a license key.', $this->product_sku ) );
			}

			$response = $this->license_request( 'activate', $license_key );

			return $response;
		}

		/**
		 * Send a license request with the common payload.
		 */
		private function license_request( $action, $license_key ) {
			return ( new API() )->request(
				'license',
				$action,
				[
					'license_key' => $license_key,
					'product_sku' => $this->product_sku,
					'site_url'    => home_url(),
					'version'     => $this->version,
				]
			);
		}

		/*
		 * ------------------------------------------------------------------
		 * Admin form handling
		 * ------------------------------------------------------------------
		 */

		/**
		 * Handle the license form submitted to admin-post.php.
		 */
		public function handle_license_action() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to manage this license.', $this->product_sku ) );
			}

			check_admin_referer( $this->admin_action() );

			$action      = sanitize_key( wp_unslash( $_POST['license_action'] ?? '' ) );
			$license_key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );

			$this->set_notice( $this->process_license_action( $action, $license_key ) );

			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		/**
		 * Run one license action. Also usable by other plugin integrations.
		 *
		 * @param string $action      activate, validate, or deactivate.
		 * @param string $license_key License key (activate only).
		 * @return array|\WP_Error
		 */
		public function process_license_action( $action, $license_key = '' ) {
			switch ( $action ) {
				case 'activate':
					return $this->activate( $license_key );

				default:
					return $this->error( 'pkart_invalid_license_action', __( 'Invalid license action.', $this->product_sku ) );
			}
		}

		/**
		 * admin-post action and nonce name for the license form.
		 *
		 * @return string
		 */
		public function admin_action() {
			return 'pkart_' . $this->product_sku . '_license';
		}

		/**
		 * Whether Pro features should be enabled.
		 *
		 * @return bool
		 */
		public function is_active() {
			return 'active' === ( '' );
		}

		/**
		 * Build a WP_Error.
		 */
		private function error( $code, $message, $data = '' ) {
			return new \WP_Error( $code, $message, $data );
		}

		/**
		 * Detect the site environment.
		 *
		 * wp_get_environment_type() defaults to 'production' when WP_ENVIRONMENT_TYPE
		 * is not defined, so fall back to inspecting the host name.
		 *
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
	}
}
