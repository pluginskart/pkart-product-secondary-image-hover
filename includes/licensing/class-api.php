<?php

namespace PluginsKart\LicenseManager\V1\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'PluginsKart\\LicenseManager\\V1\\Licensing\\API' ) ) {

    class API {

        const REQUEST_TIMEOUT = 15;

        private $package_config;
        private $paths = [
            'activate'   => 'license/activate',
            'deactivate' => 'license/deactivate',
            'validate'   => 'license/validate',
            'update'     => 'update',
        ];

        /**
         * Send a request to the PluginsKart licensing API.
         */
        public function request( $action, array $data = [], $method = 'POST' ) {
            $path = $this->path( $action );

            if ( ! $path ) {
                return new \WP_Error(
                    'pluginskart_invalid_action',
                    __( 'The licensing action is invalid.', 'quick-image-swap-for-woocommerce' )
                );
            }

            $method = strtoupper( $method );
            $url    = trailingslashit( $this->base_url() ) . ltrim( $path, '/' );
            $args   = [
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
                $args['body'] = wp_json_encode( $data );

                if ( false === $args['body'] ) {
                    return new \WP_Error(
                        'pluginskart_request_encode_failed',
                        __( 'The licensing request could not be encoded.', 'quick-image-swap-for-woocommerce' )
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
                    'pluginskart_invalid_response',
                    __( 'The licensing API returned an invalid response.', 'quick-image-swap-for-woocommerce' ),
                    [ 'status' => $status ]
                );
            }

            $body['_http_status'] = $status;

            return $body;
        }

        /**
         * Return the configured licensing API base URL.
         */
        private function base_url() {
            $package_config = $this->package_config();
            $base_url       = $package_config['pluginskart-data']['api_base_url'] ?? '';

            return is_string( $base_url ) ? trim( $base_url ) : '';
        }

        /**
         * Read and cache the plugin package configuration.
         */
        private function package_config() {
            if ( null !== $this->package_config ) {
                return $this->package_config;
            }

            $package_file = dirname( __DIR__, 2 ) . '/package.json';
            $package_data = is_readable( $package_file )
                ? json_decode( file_get_contents( $package_file ), true )
                : [];

            $this->package_config = is_array( $package_data ) ? $package_data : [];

            return $this->package_config;
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
            $sanitized = [];

            foreach ( $data as $key => $value ) {
                if ( ! is_scalar( $value ) ) {
                    continue;
                }

                $key = sanitize_key( $key );

                if ( '' === $key ) {
                    continue;
                }

                $sanitized[ $key ] = sanitize_text_field( (string) $value );
            }

            return $sanitized;
        }
    }
}
