<?php
/**
 * PluginsKart reusable Licensing SDK.
 *
 * @package PluginsKart\LicenseManager
 */

namespace PluginsKart\LicenseManager\V1;

use PluginsKart\LicenseManager\V1\Licensing\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Licensing' ) ) {

	/**
	 * Per-plugin licensing facade.
	 *
	 * Each plugin gets its own PackageConfig/API/option/transient namespace.
	 * The same PHP classes can therefore be loaded by multiple plugins.
	 */
	class Licensing {

		/**
		 * SDK instances keyed by package.json path.
		 *
		 * @var array<string, self>
		 */
		private static $instances = array();

		/**
		 * Package configuration.
		 *
		 * @var PackageConfig
		 */
		private $config;

		/**
		 * API client.
		 *
		 * @var API
		 */
		private $api;

		/**
		 * License option key.
		 *
		 * @var string
		 */
		private $option_key;

		/**
		 * Update transient key.
		 *
		 * @var string
		 */
		private $update_transient_key;

		/**
		 * Plugin basename.
		 *
		 * @var string
		 */
		private $plugin_basename;

		/**
		 * Initialize the licensing SDK for the calling plugin.
		 *
		 * The optional file is useful for explicit integrations. With no
		 * argument, the immediate caller is used, so a plugin can simply call:
		 *
		 *     Licensing::init();
		 *
		 * @param string $plugin_file Optional file inside the plugin.
		 * @return self|\WP_Error
		 */
		public static function init( $plugin_file = '' ) {
			if ( ! $plugin_file ) {
				$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
				$plugin_file = isset( $trace[1]['file'] ) ? $trace[1]['file'] : '';
			}

			$config = new PackageConfig( $plugin_file );

			if ( ! $config->is_valid() ) {
				return new \WP_Error(
					'pluginskart_package_config_invalid',
					__( 'The PluginsKart package.json configuration could not be loaded.', 'pluginskart-license-manager' )
				);
			}

			$key = $config->package_file();

			if ( isset( self::$instances[ $key ] ) ) {
				return self::$instances[ $key ];
			}

			$instance = new self( $config );
			self::$instances[ $key ] = $instance;

			$instance->register_hooks();

			return $instance;
		}

		/**
		 * Constructor.
		 *
		 * @param PackageConfig $config Package configuration.
		 */
		public function __construct( PackageConfig $config ) {
			$this->config               = $config;
			$this->api                  = new API( $config );
			$this->option_key           = 'pkart_' . $config->plugin_slug() . '_license';
			$this->update_transient_key = $this->build_update_transient_key();
			$this->plugin_basename      = $config->plugin_basename();
		}

		/**
		 * Register WordPress hooks used by the SDK.
		 */
		private function register_hooks() {
			if ( is_admin() ) {
				$admin = new Admin( $this );

				add_action( 'admin_menu', array( $admin, 'register_menu' ) );
				add_action( 'admin_post_' . $this->admin_action(), array( $admin, 'handle_license_action' ) );
			}

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ) );
			add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
		}

		/**
		 * Activate and persist the license for this site.
		 *
		 * @param string $license_key License key.
		 * @return array|\WP_Error
		 */
		public function activate( $license_key ) {
			$license_key = sanitize_text_field( $license_key );

			if ( ! $license_key ) {
				return new \WP_Error(
					'pluginskart_license_key_missing',
					__( 'Please enter a license key.', $this->text_domain() )
				);
			}

			$response = $this->api->request(
				'activate',
				$this->request_data( $license_key )
			);

			if ( is_wp_error( $response ) || empty( $response['success'] ) ) {
				return $response;
			}

			$this->save_license( $license_key, $response );

			return $response;
		}

		/**
		 * Deactivate the stored license.
		 *
		 * @return array|\WP_Error
		 */
		public function deactivate() {
			$license = $this->get_stored();

			if ( ! $this->has_stored_license( $license ) ) {
				$this->clear_stored_license();

				return array( 'success' => true );
			}

			$response = $this->api->request(
				'deactivate',
				$this->request_data( $license['license_key'], $license['domain'] ?? home_url() )
			);

			if ( ! is_wp_error( $response ) && ! empty( $response['success'] ) ) {
				$this->clear_stored_license();
			}

			return $response;
		}

		/**
		 * Validate the stored license against the API.
		 *
		 * @return array|\WP_Error
		 */
		public function validate() {
			$license = $this->get_stored();

			if ( ! $this->has_stored_license( $license ) ) {
				return $this->missing_license_error();
			}

			$response = $this->api->request(
				'validate',
				$this->request_data(
					$license['license_key'],
					$license['domain'] ?? home_url()
				)
			);

			if ( ! is_wp_error( $response ) ) {
				$this->save_license( $license['license_key'], $response, $license['domain'] ?? home_url() );
			}

			return $response;
		}

		/**
		 * Backwards-compatible aliases used by the current plugin.
		 *
		 * @param string $license_key License key.
		 * @param string $product_sku Ignored; product SKU comes from package.json.
		 * @param string $domain      Ignored; current site is used by default.
		 * @param string $version     Ignored; version comes from package.json.
		 * @return array|\WP_Error
		 */
		public function activate_for_site( $license_key, $product_sku = '', $domain = '', $version = '' ) {
			return $this->activate( $license_key );
		}

		/**
		 * Backwards-compatible stored validation alias.
		 *
		 * @return array|\WP_Error
		 */
		public function validate_stored() {
			return $this->validate();
		}

		/**
		 * Backwards-compatible stored deactivation alias.
		 *
		 * @return array|\WP_Error
		 */
		public function deactivate_stored() {
			return $this->deactivate();
		}

		/**
		 * Return a direct API validation request.
		 *
		 * @param string $license_key License key.
		 * @return array|\WP_Error
		 */
		public function validate_license( $license_key ) {
			return $this->api->request(
				'validate',
				$this->request_data( sanitize_text_field( $license_key ) )
			);
		}

		/**
		 * Request update information using the active license.
		 *
		 * @return array|\WP_Error
		 */
		public function update() {
			$license = $this->get_stored();

			if ( ! $this->has_stored_license( $license ) || ! $this->is_active() ) {
				return $this->missing_license_error();
			}

			return $this->get_update_data( $license['license_key'] );
		}

		/**
		 * Return locally stored license state.
		 *
		 * @return array
		 */
		public function get_stored() {
			$license = get_option( $this->option_key, array() );

			return is_array( $license ) ? $license : array();
		}

		/**
		 * Return whether the last known license state is active.
		 *
		 * @return bool
		 */
		public function is_active() {
			$license = $this->get_stored();

			return ! empty( $license['response']['success'] )
				&& 'deactivated' !== $this->get_status();
		}

		/**
		 * Return normalized local license status.
		 *
		 * @return string
		 */
		public function get_status() {
			$license = $this->get_stored();
			$data    = isset( $license['response']['data'] ) && is_array( $license['response']['data'] )
				? $license['response']['data']
				: array();

			if ( ! empty( $data['status'] ) ) {
				return sanitize_key( $data['status'] );
			}

			if ( ! empty( $license['response']['code'] ) ) {
				$code = sanitize_key( $license['response']['code'] );

				if ( 'license_active' === $code ) {
					return 'active';
				}
			}

			return ! empty( $license['response']['success'] ) ? 'active' : 'inactive';
		}

		/**
		 * Return this plugin's license option name.
		 *
		 * @return string
		 */
		public function get_option_key() {
			return $this->option_key;
		}

		/**
		 * Return package metadata.
		 *
		 * @return PackageConfig
		 */
		public function package_config() {
			return $this->config;
		}

		/**
		 * Return the API client.
		 *
		 * @return API
		 */
		public function api() {
			return $this->api;
		}

		/**
		 * Return the generated admin page slug.
		 *
		 * @return string
		 */
		public function admin_page_slug() {
			return 'pkart-' . $this->config->plugin_slug() . '-license';
		}

		/**
		 * Return the generated admin action.
		 *
		 * @return string
		 */
		public function admin_action() {
			return 'pkart_' . $this->config->plugin_slug() . '_license';
		}

		/**
		 * Return the generated nonce action.
		 *
		 * @return string
		 */
		public function nonce_action() {
			return $this->admin_action();
		}

		/**
		 * Check for a plugin update.
		 *
		 * @param object $transient WordPress update transient.
		 * @return object
		 */
		public function check_for_plugin_update( $transient ) {
			if ( ! is_object( $transient ) || ! $this->plugin_basename || ! $this->is_active() ) {
				return $transient;
			}

			$update = $this->get_update_data(
				$this->get_stored()['license_key'] ?? ''
			);

			if ( is_wp_error( $update ) || empty( $update['success'] ) || empty( $update['data'] ) ) {
				return $transient;
			}

			$data = $update['data'];

			if ( empty( $data['new_version'] ) || version_compare( $this->config->version(), $data['new_version'], '>=' ) ) {
				return $transient;
			}

			$package = ! empty( $data['download_url'] ) ? esc_url_raw( $data['download_url'] ) : '';

			if ( ! $package ) {
				return $transient;
			}

			$transient->response[ $this->plugin_basename ] = (object) array(
				'id'          => $this->config->product_sku(),
				'slug'        => $this->config->plugin_slug(),
				'plugin'      => $this->plugin_basename,
				'new_version' => sanitize_text_field( $data['new_version'] ),
				'url'         => esc_url_raw( $data['details_url'] ?? '' ),
				'package'     => $package,
				'tested'      => sanitize_text_field( $data['tested'] ?? $this->config->get( 'tested', '' ) ),
				'requires'    => sanitize_text_field( $data['requires'] ?? $this->config->get( 'requires', '' ) ),
				'requires_php'=> sanitize_text_field( $data['requires_php'] ?? $this->config->get( 'requires_php', '' ) ),
			);

			return $transient;
		}

		/**
		 * Supply plugin information for the WordPress update details screen.
		 *
		 * @param false|object $result Existing result.
		 * @param string       $action WordPress plugin API action.
		 * @param object       $args   WordPress API arguments.
		 * @return false|object
		 */
		public function plugin_information( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || ! is_object( $args ) ) {
				return $result;
			}

			if ( $this->config->plugin_slug() !== sanitize_key( $args->slug ?? '' ) ) {
				return $result;
			}

			$license = $this->get_stored();

			if ( ! $this->has_stored_license( $license ) || ! $this->is_active() ) {
				return $result;
			}

			$update = $this->get_update_data( $license['license_key'] );

			if ( is_wp_error( $update ) || empty( $update['success'] ) || empty( $update['data'] ) ) {
				return $result;
			}

			$data = $update['data'];

			$info = new \stdClass();
			$info->name         = $this->config->title();
			$info->slug         = $this->config->plugin_slug();
			$info->version      = sanitize_text_field( $data['new_version'] ?? $this->config->version() );
			$info->author       = sanitize_text_field( $this->config->get( 'author', '' ) );
			$info->homepage     = esc_url_raw( $this->config->get( 'download_url', '' ) );
			$info->download_link= esc_url_raw( $data['download_url'] ?? '' );
			$info->requires     = sanitize_text_field( $data['requires'] ?? $this->config->get( 'requires', '' ) );
			$info->tested       = sanitize_text_field( $data['tested'] ?? $this->config->get( 'tested', '' ) );
			$info->requires_php = sanitize_text_field( $data['requires_php'] ?? $this->config->get( 'requires_php', '' ) );
			$info->last_updated = sanitize_text_field( $data['release_date'] ?? $this->config->get( 'last_updated', '' ) );
			$info->sections     = array(
				'description' => wp_kses_post( $this->config->get( 'description', '' ) ),
			);

			return $info;
		}

		/**
		 * Build request data from package metadata.
		 *
		 * @param string $license_key License key.
		 * @param string $domain      Domain.
		 * @return array
		 */
		private function request_data( $license_key, $domain = '' ) {
			return array(
				'license_key'    => sanitize_text_field( $license_key ),
				'product_sku'    => $this->config->product_sku(),
				'domain'         => esc_url_raw( $domain ?: home_url() ),
				'plugin_version' => $this->config->version(),
			);
		}

		/**
		 * Persist license data.
		 *
		 * @param string $license_key License key.
		 * @param array  $response    API response.
		 * @param string $domain      Site domain.
		 */
		private function save_license( $license_key, array $response, $domain = '' ) {
			$existing = $this->get_stored();

			update_option(
				$this->option_key,
				array(
					'license_key' => sanitize_text_field( $license_key ),
					'product_sku' => $this->config->product_sku(),
					'domain'      => esc_url_raw( $domain ?: ( $existing['domain'] ?? home_url() ) ),
					'response'    => $response,
					'updated_at'  => time(),
				),
				false
			);
		}

		/**
		 * Fetch update data, using a per-plugin transient cache.
		 *
		 * @param string $license_key License key.
		 * @return array|\WP_Error
		 */
		private function get_update_data( $license_key ) {
			if ( ! $license_key ) {
				return $this->missing_license_error();
			}

			$cached = get_transient( $this->update_transient_key );

			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}

			$response = $this->api->request(
				'update',
				$this->request_data( $license_key )
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			set_transient( $this->update_transient_key, $response, 12 * HOUR_IN_SECONDS );

			return $response;
		}

		/**
		 * Check whether stored license data is complete enough to use.
		 *
		 * @param array $license License data.
		 * @return bool
		 */
		private function has_stored_license( array $license ) {
			return ! empty( $license['license_key'] )
				&& $this->config->product_sku() === ( $license['product_sku'] ?? '' );
		}

		/**
		 * Return the standard missing-license error.
		 *
		 * @return \WP_Error
		 */
		private function missing_license_error() {
			return new \WP_Error(
				'pluginskart_license_missing',
				__( 'No license is activated.', $this->text_domain() )
			);
		}

		/**
		 * Remove stored license and cached update information.
		 */
		private function clear_stored_license() {
			delete_option( $this->option_key );
			delete_transient( $this->update_transient_key );
		}

		/**
		 * Build a bounded per-plugin update transient name.
		 *
		 * @return string
		 */
		private function build_update_transient_key() {
			$key = 'pkart_' . $this->config->plugin_slug() . '_update_check';

			return strlen( $key ) <= 172
				? $key
				: 'pkart_update_' . md5( $this->config->package_file() );
		}

		/**
		 * Return plugin text domain.
		 *
		 * @return string
		 */
		private function text_domain() {
			return $this->config->string( 'text_domain', $this->config->plugin_slug() );
		}
	}
}
