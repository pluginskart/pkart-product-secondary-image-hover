<?php

namespace Pkart\LicenseManager\V1\Licensing;

use Pkart\LicenseManager\V1\PackageConfig;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Pkart\\LicenseManager\\V1\\Licensing\\API' ) ) {

    class API {

        const REQUEST_TIMEOUT = 15;

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

        private $paths = [
            'activate'            => 'license/activate',
            'deactivate'          => 'license/deactivate',
            'validate'            => 'license/validate',
			'plugin_activation'   => 'plugin/activation',
			'plugin_deactivation' => 'plugin/deactivation',
            'plugin_update'       => 'plugin/update',
        ];

        /**
		 * Send a request to the Pkart licensing API.
         */
        public function request( $action, array $data = [], $method = 'POST' ) {
            $path = $this->path( $action );

            if ( ! $path ) {
                return new \WP_Error(
                    'pkart_invalid_action',
					__( 'The licensing action is invalid.', $this->config->product_sku() )
                );
            }

            $method  = strtoupper( $method );
			$url     = trailingslashit( $this->config->api_base_url() ) . ltrim( $path, '/' );
            $args    = [
                'method'  => $method,
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ];

            $data = $this->sanitize_data( $data );

            if ( 'GET' === $method ) {
                $url = add_query_arg( $data, $url );
            } else {
				$body_data = in_array( $action, [ 'plugin_activation', 'plugin_deactivation' ], true )
					? [ 'data' => $data ]
					: $data;
				$args['body'] = wp_json_encode( $body_data );

                if ( false === $args['body'] ) {
                    return new \WP_Error(
                        'pkart_request_encode_failed',
						__( 'The licensing request could not be encoded.', $this->config->product_sku() )
                    );
                }
            }

            $response = wp_remote_request( $url, $args );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $status = wp_remote_retrieve_response_code( $response );
            $body   = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( ! is_array( $body ) ) {
                return new \WP_Error(
                    'pkart_invalid_response',
					__( 'The licensing API returned an invalid response.', $this->config->product_sku() ),
                    [ 'status' => $status ]
                );
            }

            $body['_http_status'] = $status;

            return $body;
        }

		/**
		 * Send a plugin activation or deactivation event to the Pkart licensing API.
		 *
		 * @param string $action Either 'activate' or 'deactivate'.
		 * @param array  $data   Data to send with the event.
		 *
		 * @return array|\WP_Error
		 */
		public function send_plugin_event( $action, $data ) {
			if ( ! in_array( $action, [ 'activate', 'deactivate' ], true ) ) {
				return new \WP_Error(
					'pkart_invalid_action',
					__( 'The plugin lifecycle action is invalid.', $this->config->product_sku() )
				);
			}

			return $this->request( 'plugin_' . ( 'activate' === $action ? 'activation' : 'deactivation' ), $data );
		}

        /**
         * Map an action to its API route and HTTP method.
         */
        private function path( $action ) {
            return $this->paths[ $action ] ?? '';
        }

        /**
         * Sanitize scalar API values and reject arrays or objects.
         */
        private function sanitize_data( array $data ) {
			$sanitized = array();

			foreach ( $data as $key => $value ) {
				$key = sanitize_key( $key );

				if ( '' === $key || ! is_scalar( $value ) ) {
					continue;
				}

				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}

			return $sanitized;
		}

		/**
		 * Check whether API URL belongs to local development.
		 *
		 * SSL verification is disabled for local development only.
		 *
		 * @param string $url API URL.
		 *
		 * @return bool
		 */
		private function is_local_api_url( $url ) {

			$host = strtolower(
				(string) wp_parse_url( $url, PHP_URL_HOST )
			);

			if ( in_array(
				$host,
				array(
					'localhost',
					'127.0.0.1',
					'::1',
				),
				true
			) ) {
				return true;
			}

			return (bool) preg_match( '/\.local$/', $host );
		}

		/**
		 * Get package configuration value.
		 *
		 * Kept for backward compatibility.
		 *
		 * Prefer using PackageConfig directly in new code.
		 *
		 * @param string $key     Configuration key.
		 * @param mixed  $default Default value.
		 *
		 * @return mixed
		 */
		public function package_value( $key, $default = '' ) {
			return $this->config->get( $key, $default );
		}

    }
}
