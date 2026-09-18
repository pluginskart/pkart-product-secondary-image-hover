<?php

namespace PluginsKart\LicenseManager\V1;

use PluginsKart\LicenseManager\V1\Licensing\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'PluginsKart\\LicenseManager\\V1\\Licensing' ) ) {

    class Licensing {

		/**
		 * API client.
		 *
		 * @var API
		 */
		private $api;

		/**
		 * Option key for storing license state.
		 *
		 * @var string
		 */
		private $option_key;

		/**
		 * Package configuration.
		 *
		 * @var PackageConfig
		 */
		private $config;

        /**
         * Create the licensing facade for a plugin slug.
         */
		public function __construct( PackageConfig $config ) {
			$this->config               = $config;
			$this->api                  = new API( $config );
			$this->option_key           = 'pkart_' . $config->plugin_slug() . '_license';

			add_action( 'admin_post_' . $this->admin_action(), [ $this, 'handle_license_action' ] );
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
                $this->api->package_value( 'version' )
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
                __( 'No license is activated.', $this->api->package_value( 'product_sku' ) )
            );
        }

        /**
         * Remove the stored license state.
         */
        private function clear_stored_license() {
            delete_option( $this->option_key );
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
         * Process activation, validation, and deactivation requests.
         */
        public function handle_license_action() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You are not allowed to manage this license.', $this->api->package_value( 'product_sku' ) ) );
            }

            check_admin_referer( 'pkart_quick_image_swap_license' );

            $response = $this->process_license_action(
                $this->request_action(),
                $this->request_license_key()
            );
            $this->store_license_notice( $response );

            wp_safe_redirect( admin_url( 'options-general.php?page=pkart-quick-image-swap-license' ) );
            exit;
        }

        /**
         * Return the requested admin license action.
         */
        private function request_action() {
            return isset( $_POST['license_action'] )
                ? sanitize_key( wp_unslash( $_POST['license_action'] ) )
                : '';
        }

		/**
         * Return the submitted license key.
         */
        private function request_license_key() {
            return isset( $_POST['license_key'] )
                ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) )
                : '';
        }

        /**
         * Process one supported license action.
         *
         * This public method can also be called by another plugin integration.
         *
         * @param string $action     License action: activate, validate, or deactivate.
         * @param string $license_key License key for activation.
         * @return array|\WP_Error
         */
        public function process_license_action( $action, $license_key = '' ) {
            switch ( $action ) {
                case 'activate':
                    return $this->activate_for_site(
                        sanitize_text_field( $license_key ),
                        $this->api->package_value( 'product_sku' ),
                        home_url(),
                        $this->api->package_value( 'version' )
                    );

                case 'validate':
                    return $this->validate_stored();

                case 'deactivate':
                    return $this->deactivate_stored();

                default:
                    return new \WP_Error(
                        'pluginskart_invalid_license_action',
                        __( 'Invalid license action.', $this->api->package_value( 'product_sku' ) )
                    );
            }
        }

        /**
         * Store a one-time admin notice for the license page.
         */
        private function store_license_notice( $response ) {
            $success = ! is_wp_error( $response ) && ! empty( $response['success'] );
            $message = is_wp_error( $response )
                ? $response->get_error_message()
                : ( $response['message'] ?? __( 'License request completed.', $this->api->package_value( 'product_sku' ) ) );

            set_transient(
                'pkart_quick_image_swap_license_notice_' . get_current_user_id(),
                [
                    'type'    => $success ? 'updated' : 'error',
                    'message' => $message,
                ],
                30
            );
        }

		/**
		 * Return the generated admin action.
		 *
		 * @return string
		 */
		public function admin_action() {
			return 'pkart_' . $this->config->plugin_slug() . '_license';
		}


    }
}
