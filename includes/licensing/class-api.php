<?php

namespace Pkart\LicenseManager\V1\Licensing;

use Pkart\LicenseManager\V1\PackageConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pkart\\LicenseManager\\V1\\Licensing\\API' ) ) {

	/**
	 * HTTP client for the Pkart licensing API.
	 *
	 * Every call is: POST {api_base_url}/{type}?action={action} with a JSON body.
	 */
	class API {

		const REQUEST_TIMEOUT = 15;

		/**
		 * Allowed actions per route type. Mirrors the API server.
		 */
		const ACTIONS = [
			'plugin'  => [ 'activate', 'deactivate', 'update-check' ],
			'license' => [ 'activate', 'deactivate', 'validate' ],
		];

		/**
		 * Plugin package configuration.
		 *
		 * @var PackageConfig
		 */
		private $config;

		/**
		 * Constructor.
		 *
		 * @param PackageConfig $config Plugin package configuration.
		 */
		public function __construct( PackageConfig $config ) {
			$this->config = $config;
		}

		/**
		 * Send a request to the licensing API.
		 *
		 * @param string $type   'plugin' or 'license'.
		 * @param string $action Action allowed for that type (see ACTIONS).
		 * @param array  $data   Scalar values sent as the JSON body.
		 * @return array|\WP_Error Decoded response body plus `_http_status`.
		 */
		public function request( $type, $action, array $data = [] ) {
			if ( ! in_array( $action, self::ACTIONS[ $type ] ?? [], true ) ) {
				return $this->error( 'pkart_invalid_action', __( 'The licensing action is invalid.', $this->config->product_sku() ) );
			}

			$url      = add_query_arg( 'action', $action, trailingslashit( $this->config->api_base_url() ) . $type );
			$response = wp_remote_post( $url, $this->request_args( $url, $data ) );

			return is_wp_error( $response ) ? $response : $this->parse_response( $response );
		}

		/**
		 * Build wp_remote_post() arguments with a JSON body.
		 *
		 * sanitize_data() leaves only valid UTF-8 strings, so encoding cannot fail.
		 */
		private function request_args( $url, array $data ) {
			return [
				'timeout'   => self::REQUEST_TIMEOUT,
				'sslverify' => self::ssl_verify( $url ),
				'headers'   => [
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				],
				'body'      => wp_json_encode( $this->sanitize_data( $data ) ),
			];
		}

		/**
		 * Verify SSL except on *.local dev hosts (self-signed certificates).
		 * Override with the `pkart_sdk_sslverify` filter.
		 */
		public static function ssl_verify( $url ) {
			$is_local_host = str_ends_with( (string) wp_parse_url( $url, PHP_URL_HOST ), '.local' );

			return (bool) apply_filters( 'pkart_sdk_sslverify', ! $is_local_host, $url );
		}

		/**
		 * Decode a JSON response and add `_http_status`.
		 *
		 * @param array $response wp_remote_post() response.
		 * @return array|\WP_Error
		 */
		private function parse_response( $response ) {
			$status  = wp_remote_retrieve_response_code( $response );
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $decoded ) ) {
				return $this->error(
					'pkart_invalid_response',
					__( 'The licensing API returned an invalid response.', $this->config->product_sku() ),
					[ 'status' => $status ]
				);
			}

			$decoded['_http_status'] = $status;

			return $decoded;
		}

		/**
		 * Keep scalar values only, sanitized as text.
		 */
		private function sanitize_data( array $data ) {
			$sanitized = [];

			foreach ( $data as $key => $value ) {
				$key = sanitize_key( $key );

				if ( '' !== $key && is_scalar( $value ) ) {
					$sanitized[ $key ] = sanitize_text_field( (string) $value );
				}
			}

			return $sanitized;
		}

		/**
		 * Build a WP_Error.
		 */
		private function error( $code, $message, $data = '' ) {
			return new \WP_Error( $code, $message, $data );
		}
	}
}
