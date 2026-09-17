<?php
/**
 * PluginsKart Licensing API client.
 *
 * @package PluginsKart\LicenseManager
 */

namespace PluginsKart\LicenseManager\V1\Licensing;

use PluginsKart\LicenseManager\V1\PackageConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\API' ) ) {

	/**
	 * HTTP client for the PluginsKart licensing/update API.
	 */
	class API {

		const REQUEST_TIMEOUT = 15;

		/**
		 * Package configuration.
		 *
		 * @var PackageConfig
		 */
		private $config;

		/**
		 * API paths.
		 *
		 * @var array
		 */
		private $paths = array(
			'activate'   => 'license/activate',
			'deactivate' => 'license/deactivate',
			'validate'   => 'license/validate',
			'update'     => 'update',
		);

		/**
		 * Constructor.
		 *
		 * @param PackageConfig $config Package configuration.
		 */
		public function __construct( PackageConfig $config ) {
			$this->config = $config;
		}

		/**
		 * Send a request to the PluginsKart API.
		 *
		 * @param string $action API action.
		 * @param array  $data   Request data.
		 * @param string $method HTTP method.
		 * @return array|\WP_Error
		 */
		public function request( $action, array $data = array(), $method = 'POST' ) {
			$path = $this->path( $action );

			if ( ! $path ) {
				return new \WP_Error(
					'pluginskart_invalid_action',
					__( 'The licensing action is invalid.', $this->text_domain() )
				);
			}

			$api_base_url = $this->config->api_base_url();

			if ( ! $api_base_url || ! wp_http_validate_url( $api_base_url ) ) {
				return new \WP_Error(
					'pluginskart_invalid_api_url',
					__( 'The licensing API URL is invalid.', $this->text_domain() )
				);
			}

			$method = strtoupper( $method );
			$url    = trailingslashit( $api_base_url ) . ltrim( $path, '/' );

			$args = array(
				'method'      => $method,
				'timeout'     => self::REQUEST_TIMEOUT,
				'redirection' => 3,
				'sslverify'   => (bool) apply_filters(
					'pluginskart_license_api_sslverify',
					! $this->is_local_api_url( $api_base_url ),
					$api_base_url
				),
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'user-agent'  => 'PluginsKart License SDK/' . $this->config->version(),
			);

			$data = $this->sanitize_data( $data );

			if ( 'GET' === $method ) {
				$url = add_query_arg( $data, $url );
			} else {
				$args['body'] = wp_json_encode( $data );

				if ( false === $args['body'] ) {
					return new \WP_Error(
						'pluginskart_request_encode_failed',
						__( 'The licensing request could not be encoded.', $this->text_domain() )
					);
				}
			}

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				return new \WP_Error(
					'pluginskart_http_error',
					$response->get_error_message(),
					array( 'previous_error' => $response )
				);
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$body   = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $body ) ) {
				return new \WP_Error(
					'pluginskart_invalid_response',
					__( 'The licensing API returned an invalid response.', $this->text_domain() ),
					array( 'status' => $status )
				);
			}

			$body['_http_status'] = $status;

			return $body;
		}

		/**
		 * Return one value from pluginskart-data.
		 *
		 * Kept as a compatibility helper for existing integrations. New code
		 * should use PackageConfig directly.
		 *
		 * @param string $key     Metadata key.
		 * @param mixed  $default Default value.
		 * @return mixed
		 */
		public function package_value( $key, $default = '' ) {
			return $this->config->get( $key, $default );
		}

		/**
		 * Return the complete package configuration.
		 *
		 * Kept for backwards compatibility with the existing plugin.
		 *
		 * @return array
		 */
		public function package_config() {
			return $this->config->all();
		}

		/**
		 * Map an action to an API path.
		 *
		 * @param string $action Action.
		 * @return string
		 */
		private function path( $action ) {
			return isset( $this->paths[ $action ] ) ? $this->paths[ $action ] : '';
		}

		/**
		 * Identify local development API hosts.
		 *
		 * @param string $url API URL.
		 * @return bool
		 */
		private function is_local_api_url( $url ) {
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

			return 'localhost' === $host
				|| '127.0.0.1' === $host
				|| '::1' === $host
				|| (bool) preg_match( '/\.local$/', $host );
		}

		/**
		 * Sanitize request data without damaging URLs.
		 *
		 * @param array $data Request data.
		 * @return array
		 */
		private function sanitize_data( array $data ) {
			$sanitized = array();

			foreach ( $data as $key => $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}

				$key = sanitize_key( $key );

				if ( '' === $key ) {
					continue;
				}

				$value = (string) $value;

				if ( in_array( $key, array( 'domain', 'api_base_url', 'download_url' ), true ) ) {
					$sanitized[ $key ] = esc_url_raw( $value );
				} elseif ( 'license_key' === $key ) {
					$sanitized[ $key ] = sanitize_text_field( $value );
				} else {
					$sanitized[ $key ] = sanitize_text_field( $value );
				}
			}

			return $sanitized;
		}

		/**
		 * Validate a trusted package.json API endpoint.
		 *
		 * HTTPS is required for non-local endpoints. HTTP localhost/loopback
		 * endpoints remain available for the local Mock API environment.
		 *
		 * @param string $url API URL.
		 * @return bool
		 */
		private function is_valid_api_url( $url ) {
			$parts = wp_parse_url( $url );

			if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
				return false;
			}

			$scheme = strtolower( $parts['scheme'] );
			$host   = strtolower( $parts['host'] );

			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return false;
			}

			if ( 'http' === $scheme && ! in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
				return false;
			}

			if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
				return true;
			}

			return (bool) wp_http_validate_url( $url );
		}

		/**
		 * Return the plugin text domain.
		 *
		 * @return string
		 */
		private function text_domain() {
			return $this->config->plugin_slug();
		}
	}
}
