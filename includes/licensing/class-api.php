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
                    __( 'The licensing action is invalid.', $this->package_value( 'product_sku' ) )
                );
            }

            $method = strtoupper( $method );
            $api_base_url = $this->package_value( 'api_base_url' );
            $url          = trailingslashit( $api_base_url ) . ltrim( $path, '/' );
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
                        __( 'The licensing request could not be encoded.', $this->package_value( 'product_sku' ) )
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
                    __( 'The licensing API returned an invalid response.', $this->package_value( 'product_sku' ) ),
                    [ 'status' => $status ]
                );
            }

            $body['_http_status'] = $status;

            return $body;
        }

        /**
         * Return all values from the plugin package.json configuration.
         *
         * The file is read once per API instance, so changes to package.json
         * are available automatically when the plugin loads a new instance.
         *
         * @return array<string, mixed>
         */
        public function package_config() {
            if ( null !== $this->package_config ) {
                return $this->package_config;
            }

            $package_file = dirname( __DIR__, 2 ) . '/package.json';
            $package_data = is_readable( $package_file )
                ? wp_json_file_decode( $package_file, [ 'associative' => true ] )
                : null;

            $this->package_config = is_array( $package_data ) ? $package_data : [];

            return $this->package_config;
        }

        /**
         * Return one value from the package metadata.
         */
        public function package_value( $key, $default = '' ) {
            $value = $this->package_config()['pluginskart-data'][ $key ] ?? $default;

            return is_string( $value ) && '' !== trim( $value )
                ? sanitize_text_field( $value )
                : $default;
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
