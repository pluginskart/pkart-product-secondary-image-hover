<?php

namespace Pkart\LicenseManager\V1;

use Pkart\LicenseManager\V1\Licensing\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pkart\\LicenseManager\\V1\\Licensing' ) ) {

	/**
	 * License facade for one plugin: activate, validate, deactivate,
	 * plugin lifecycle events, and the admin-post form handler.
	 *
	 * Stored option (pkart_{slug}_license):
	 *   [ license_key, product_sku, domain, response, updated_at ]
	 *
	 * Pro features are enabled only when response.status === 'active'.
	 */
	class Licensing {

		/**
		 * API client.
		 *
		 * @var API
		 */
		private $api;

		/**
		 * Package configuration.
		 *
		 * @var PackageConfig
		 */
		private $config;

		/**
		 * Option key for storing license state.
		 *
		 * @var string
		 */
		private $option_key;

		/**
		 * One instance per plugin, keyed by plugin basename.
		 *
		 * @var Licensing[]
		 */
		private static $instances = [];

		/**
		 * Boot licensing for a plugin:
		 *
		 *   \Pkart\LicenseManager\V1\Licensing::init( __FILE__ );
		 *
		 * Pass any file inside the plugin (e.g. load.php). The main plugin
		 * file is found from package.json, so activate/deactivate hooks are
		 * registered on the right file. Returns the same instance per plugin.
		 *
		 * @param string $file Any file inside the plugin.
		 * @return Licensing
		 */
		public static function init( $file ) {
			$config      = new PackageConfig( $file );
			$plugin_file = WP_PLUGIN_DIR . '/' . $config->plugin_basename();
			$key         = $config->product_sku();

			if ( isset( self::$instances[ $key ] ) ) {
				return self::$instances[ $key ];
			}

			$instance = new self( $config );

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

			if ( is_admin() ) {
				new Licensing\Admin( $instance );
			}

			return self::$instances[ $key ] = $instance;
		}

		/**
		 * Create the licensing facade. Prefer Licensing::init().
		 */
		public function __construct( PackageConfig $config ) {
			$this->config     = $config;
			$this->api        = new API( $config );
			$this->option_key = 'pkart_' . $config->product_sku() . '_license';

			add_action( 'admin_post_' . $this->admin_action(), [ $this, 'handle_license_action' ] );
		}

		/*
		 * ------------------------------------------------------------------
		 * API actions
		 * ------------------------------------------------------------------
		 */

		/**
		 * Send a plugin activate/deactivate lifecycle event.
		 *
		 * @param string $action 'activate' or 'deactivate'.
		 * @return array|\WP_Error
		 */
		public function register_plugin_event( $action ) {
			$data = [
				'license_key' => $this->get_stored()['license_key'] ?? '',
				'product_sku' => $this->config->product_sku(),
				'site_url'    => esc_url_raw( site_url() ),
				'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by API.
				'version'     => $this->config->version(),
				'status'      => $action,
			];

			return $this->remote_post( 'plugin', $action, $data );
		}

		/**
		 * POST JSON to {api_base_url}/{type}?action={action} with the WordPress HTTP API.
		 *
		 * @param string $type   'plugin' or 'license'.
		 * @param string $action Route action.
		 * @param array  $data   Scalar values sent as the JSON body.
		 * @return array|\WP_Error Decoded response body plus `_http_status`.
		 */
		private function remote_post( $type, $action, array $data ) {
			$url  = add_query_arg( 'action', $action, trailingslashit( $this->config->api_base_url() ) . $type );
			$body = [];

			foreach ( $data as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$body[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
				}
			}

			$response = wp_remote_post(
				$url,
				[
					'timeout'   => API::REQUEST_TIMEOUT,
					// Skip SSL verification on *.local dev hosts (self-signed certificates).
					'sslverify' => API::ssl_verify( $url ),
					'headers'   => [
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json',
					],
					'body'      => wp_json_encode( $body ),
				]
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status  = wp_remote_retrieve_response_code( $response );
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $decoded ) ) {
				return $this->error( 'pkart_invalid_response', __( 'The licensing API returned an invalid response.', $this->config->product_sku() ) );
			}

			$decoded['_http_status'] = $status;

			return $decoded;
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
				return $this->error( 'pkart_license_missing', __( 'Please enter a license key.', $this->config->product_sku() ) );
			}

			$response = $this->license_request( 'activate', $license_key );

			if ( ! is_wp_error( $response ) && ! empty( $response['success'] ) ) {
				$this->store( $license_key, $response );
			}

			return $response;
		}

		/**
		 * Re-check the stored license and store the latest status, so an
		 * expired or suspended key turns Pro off.
		 *
		 * @return array|\WP_Error
		 */
		public function validate() {
			$license_key = $this->stored_key();

			if ( ! $license_key ) {
				return $this->error( 'pkart_license_missing', __( 'No license is activated.', $this->config->product_sku() ) );
			}

			$response = $this->license_request( 'validate', $license_key );

			if ( ! is_wp_error( $response ) && isset( $response['status'] ) ) {
				$this->store( $license_key, $response );
			}

			return $response;
		}

		/**
		 * Deactivate the stored license and clear local state on success.
		 *
		 * @return array|\WP_Error
		 */
		public function deactivate() {
			$license_key = $this->stored_key();

			if ( ! $license_key ) {
				delete_option( $this->option_key );
				return [ 'success' => true ];
			}

			$response = $this->license_request( 'deactivate', $license_key );

			if ( ! is_wp_error( $response ) && ! empty( $response['success'] ) ) {
				delete_option( $this->option_key );
			}

			return $response;
		}

		/*
		 * ------------------------------------------------------------------
		 * Stored state
		 * ------------------------------------------------------------------
		 */

		/**
		 * Return the locally stored license state.
		 *
		 * @return array
		 */
		public function get_stored() {
			$license = get_option( $this->option_key, [] );

			return is_array( $license ) ? $license : [];
		}

		/**
		 * Whether Pro features should be enabled.
		 *
		 * @return bool
		 */
		public function is_active() {
			return 'active' === ( $this->get_stored()['response']['status'] ?? '' );
		}

		/**
		 * Return the stored license key, or ''.
		 */
		private function stored_key() {
			return (string) ( $this->get_stored()['license_key'] ?? '' );
		}

		/**
		 * Persist a license key and its latest API response.
		 */
		private function store( $license_key, array $response ) {
			update_option(
				$this->option_key,
				[
					'license_key' => $license_key,
					'product_sku' => $this->config->product_sku(),
					'site_url'    => esc_url_raw( home_url() ),
					'response'    => $response,
					'updated_at'  => time(),
				],
				false
			);
		}

		/**
		 * Send a license request with the common payload.
		 */
		private function license_request( $action, $license_key ) {
			return $this->api->request(
				'license',
				$action,
				[
					'license_key'    => $license_key,
					'product_sku'    => $this->config->product_sku(),
					'site_url'       => home_url(),
					'version'        => $this->config->version(),
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
				wp_die( esc_html__( 'You are not allowed to manage this license.', $this->config->product_sku() ) );
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

				case 'validate':
					return $this->validate();

				case 'deactivate':
					return $this->deactivate();

				default:
					return $this->error( 'pkart_invalid_license_action', __( 'Invalid license action.', $this->config->product_sku() ) );
			}
		}

		/**
		 * Return and clear the one-time admin notice for the current user.
		 *
		 * @return array|null [ 'type' => 'updated'|'error', 'message' => string ]
		 */
		public function pop_notice() {
			$key    = $this->notice_key();
			$notice = get_transient( $key );

			if ( ! $notice ) {
				return null;
			}

			delete_transient( $key );

			return $notice;
		}

		/**
		 * Store a one-time admin notice from an API response.
		 */
		private function set_notice( $response ) {
			$success = ! is_wp_error( $response ) && ! empty( $response['success'] );
			$message = is_wp_error( $response )
				? $response->get_error_message()
				: ( $response['message'] ?? __( 'License request completed.', $this->config->product_sku() ) );

			set_transient(
				$this->notice_key(),
				[
					'type'    => $success ? 'updated' : 'error',
					'message' => $message,
				],
				30
			);
		}

		/**
		 * Transient key for the current user's notice.
		 */
		private function notice_key() {
			return $this->admin_action() . '_notice_' . get_current_user_id();
		}

		/*
		 * ------------------------------------------------------------------
		 * Accessors
		 * ------------------------------------------------------------------
		 */

		/**
		 * admin-post action and nonce name for the license form.
		 *
		 * @return string
		 */
		public function admin_action() {
			return 'pkart_' . $this->config->product_sku() . '_license';
		}

		/**
		 * @return PackageConfig
		 */
		public function package_config() {
			return $this->config;
		}

		/**
		 * @return API
		 */
		public function api() {
			return $this->api;
		}

		/**
		 * Build a WP_Error.
		 */
		private function error( $code, $message ) {
			return new \WP_Error( $code, $message );
		}
	}
}
