<?php

namespace PluginsKart\LicenseManager\V1\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'PluginsKart\\LicenseManager\\V1\\Licensing\\API' ) ) {

    class API {

        /**
         * Send a request to the PluginsKart licensing API.
         */
        public function request( $action, array $data = [], $method = 'POST' ) {
            $method = strtoupper( $method );
            $url    = trailingslashit( $this->base_url() ) . ltrim( $this->path( $action ), '/' );
            $args   = [
                'method'  => $method,
                'timeout' => 15,
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
            return apply_filters(
                'pluginskart_quick_image_swap_license_api_url',
                rest_url( 'pluginskart-sdk/v1' )
            );
        }

        /**
         * Map an action to its API route and HTTP method.
         */
        private function path( $action ) {
            $paths = [
                'activate'   => 'license/activate',
                'deactivate' => 'license/deactivate',
                'validate'   => 'license/validate',
                'update'     => 'update',
            ];

            return $paths[ $action ] ?? '';
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

                $sanitized[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
            }

            return $sanitized;
        }
    }
}
