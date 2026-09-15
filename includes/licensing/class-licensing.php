<?php

namespace PluginsKart\LicenseManager\V1;

use PluginsKart\LicenseManager\V1\Licensing\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'PluginsKart\\LicenseManager\\V1\\Licensing' ) ) {

    class Licensing {

        /** @var API */
        private $api;
        private $option_key;

        /**
         * Create the licensing facade for a plugin slug.
         */
        public function __construct( API $api = null, $plugin_slug = 'plugin' ) {
            $this->api        = $api ?: new API();
            $this->option_key = $this->build_option_key( $plugin_slug );
        }

        /**
         * Activate a license.
         */
        public function activate( $license_key, $product_sku, $domain = '', $plugin_version = '' ) {
            return $this->license_request( 'activate', $license_key, $product_sku, $domain, $plugin_version );
        }

        /**
         * Deactivate a license.
         */
        public function deactivate( $license_key, $product_sku, $domain = '' ) {
            return $this->license_request( 'deactivate', $license_key, $product_sku, $domain );
        }

        /**
         * Validate a license.
         */
        public function validate( $license_key, $product_sku, $domain = '', $plugin_version = '' ) {
            return $this->license_request( 'validate', $license_key, $product_sku, $domain, $plugin_version );
        }

        /**
         * Check for a plugin update.
         */
        public function update( $license_key, $product_sku, $plugin_version ) {
            return $this->api->request(
                'update',
                [
                    'license_key'    => $license_key,
                    'product_sku'    => $product_sku,
                    'plugin_version' => $plugin_version,
                ],
                'GET'
            );
        }

        /**
         * Activate and persist a license response.
         */
        public function activate_for_site( $license_key, $product_sku, $domain, $plugin_version ) {
            $response = $this->activate( $license_key, $product_sku, $domain, $plugin_version );

            if ( is_wp_error( $response ) || empty( $response['success'] ) ) {
                return $response;
            }

            update_option(
                $this->option_key,
                [
                    'license_key' => sanitize_text_field( $license_key ),
                    'product_sku' => sanitize_text_field( $product_sku ),
                    'domain'      => esc_url_raw( $domain ),
                    'response'    => $response,
                    'updated_at'  => time(),
                ],
                false
            );

            return $response;
        }

        /**
         * Validate the stored license against the API.
         */
        public function validate_stored() {
            $license = $this->get_stored();

            if ( ! $this->has_stored_license( $license ) ) {
                return $this->missing_license_error();
            }

            return $this->validate(
                $license['license_key'],
                $license['product_sku'],
                $license['domain'] ?? home_url(),
                PKART_QUICK_IMAGE_SWAP_VERSION
            );
        }

        /**
         * Deactivate the stored license and clear local state on success.
         */
        public function deactivate_stored() {
            $license = $this->get_stored();

            if ( ! $this->has_stored_license( $license ) ) {
                $this->clear_stored_license();
                return [ 'success' => true ];
            }

            $response = $this->deactivate(
                $license['license_key'],
                $license['product_sku'],
                $license['domain'] ?? home_url()
            );

            if ( ! is_wp_error( $response ) && ! empty( $response['success'] ) ) {
                $this->clear_stored_license();
            }

            return $response;
        }

        /**
         * Return the locally stored license state.
         */
        public function get_stored() {
            $license = get_option( $this->option_key, [] );

            return is_array( $license ) ? $license : [];
        }

        /**
         * Check whether a successful license response is stored.
         */
        public function is_active() {
            $license = $this->get_stored();

            return ! empty( $license['response']['success'] );
        }

        /**
         * Send a license action request.
         */
        private function license_request( $action, $license_key, $product_sku, $domain, $plugin_version = '' ) {
            return $this->api->request(
                $action,
                $this->request_data( $license_key, $product_sku, $domain, $plugin_version )
            );
        }

        /**
         * Build the common license request payload.
         */
        private function request_data( $license_key, $product_sku, $domain, $plugin_version = '' ) {
            return [
                'license_key'    => $license_key,
                'product_sku'    => $product_sku,
                'domain'         => $domain ?: home_url(),
                'plugin_version' => $plugin_version,
            ];
        }

        /**
         * Check whether stored license data is complete enough to use.
         */
        private function has_stored_license( array $license ) {
            return ! empty( $license['license_key'] ) && ! empty( $license['product_sku'] );
        }

        /**
         * Return the standard missing-license error.
         */
        private function missing_license_error() {
            return new \WP_Error(
                'pluginskart_license_missing',
                __( 'No license is activated.', 'quick-image-swap-for-woocommerce' )
            );
        }

        /**
         * Remove the stored license state.
         */
        private function clear_stored_license() {
            delete_option( $this->option_key );
        }

        /**
         * Build the unique option key for a plugin.
         */
        private function build_option_key( $plugin_slug ) {
            $plugin_slug = sanitize_key( $plugin_slug ) ?: 'plugin';

            return 'pkart_' . $plugin_slug . '_license';
        }
    }
}
