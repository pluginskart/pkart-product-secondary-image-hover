<?php

namespace Pkart\LicenseManager\V1;

use Pkart\LicenseManager\V1\Licensing\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pkart\\LicenseManager\\V1\\Licensing' ) ) {

	/**
	 * Sends plugin activate/deactivate lifecycle events to the Pkart API.
	 */
	class Licensing {

		/**
		 * API client.
		 *
		 * @var API
		 */
		private $api;

		/**
		 * Boot lifecycle tracking for a plugin:
		 *
		 *   \Pkart\LicenseManager\V1\Licensing::init( __FILE__ );
		 *
		 * @param string $plugin_file Main plugin file.
		 * @return Licensing
		 */
		public static function init( $plugin_file ) {
			$instance = new self();

			register_activation_hook(
				$plugin_file,
				function () use ( $instance ) {
					$instance->register_plugin_event( 'activate' );
				}
			);

			register_deactivation_hook(
				$plugin_file,
				function () use ( $instance ) {
					$instance->register_plugin_event( 'deactivate' );
				}
			);

			return $instance;
		}

		/**
		 * Create the lifecycle tracker. Prefer Licensing::init().
		 */
		public function __construct() {
			$this->api = new API();
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
				'product_sku' => PKART_PRODUCT_SKU,
				'version'     => PKART_PRODUCT_VERSION,
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

			return $this->api->request( 'plugin', $action, $data );
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
