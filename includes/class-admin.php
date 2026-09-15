<?php

namespace PluginsKart\QuickImageSwap;

use PluginsKart\LicenseManager\V1\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Admin' ) ) {

    class Admin {

        /** @var Licensing */
        private $licensing;
        private $product_sku;

        /**
         * Register the PRO license admin workflow.
         */
        public function __construct( Licensing $licensing ) {
            $this->licensing   = $licensing;
            $this->product_sku = $this->package_value( 'product_sku' );

            add_action( 'admin_menu', [ $this, 'register_menu' ] );
            add_action( 'admin_post_pkart_quick_image_swap_license', [ $this, 'handle_license_action' ] );
        }

        /**
         * Add the license screen under Settings.
         */
        public function register_menu() {
            add_options_page(
                __( 'Quick Image Swap PRO', 'quick-image-swap-for-woocommerce' ),
                __( 'Quick Image Swap PRO', 'quick-image-swap-for-woocommerce' ),
                'manage_options',
                'pkart-quick-image-swap-license',
                [ $this, 'render_page' ]
            );
        }

        /**
         * Process activation, validation, and deactivation requests.
         */
        public function handle_license_action() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You are not allowed to manage this license.', 'quick-image-swap-for-woocommerce' ) );
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
                    return $this->licensing->activate_for_site(
                        sanitize_text_field( $license_key ),
                        $this->product_sku,
                        home_url(),
                        PKART_QUICK_IMAGE_SWAP_VERSION
                    );

                case 'validate':
                    return $this->licensing->validate_stored();

                case 'deactivate':
                    return $this->licensing->deactivate_stored();

                default:
                    return new \WP_Error(
                        'pluginskart_invalid_license_action',
                        __( 'Invalid license action.', 'quick-image-swap-for-woocommerce' )
                    );
            }
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
         * Return one value from the package metadata.
         */
        private function package_value( $key, $default = '' ) {
            $config = $this->licensing->package_config();
            $value  = $config['pluginskart-data'][ $key ] ?? $default;

            return is_string( $value ) && '' !== trim( $value )
                ? sanitize_text_field( $value )
                : $default;
        }

        /**
         * Store a one-time admin notice for the license page.
         */
        private function store_license_notice( $response ) {
            $success = ! is_wp_error( $response ) && ! empty( $response['success'] );
            $message = is_wp_error( $response )
                ? $response->get_error_message()
                : ( $response['message'] ?? __( 'License request completed.', 'quick-image-swap-for-woocommerce' ) );

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
         * Render the PRO license page.
         */
        public function render_page() {
            ?>
            <div class="wrap">
                <?php $this->render_license_section(); ?>
            </div>
            <?php
        }

        /**
         * Render the reusable licensing section on any admin page.
         *
         * @param array $args Optional title and description overrides.
         */
        public function render_license_section( array $args = [] ) {
            $defaults = [
                'title'       => __( 'Quick Image Swap PRO License', 'quick-image-swap-for-woocommerce' ),
                'description' => __( 'Activate your license key to enable PRO updates and licensing validation.', 'quick-image-swap-for-woocommerce' ),
            ];
            $args     = wp_parse_args( $args, $defaults );
            $license  = $this->licensing->get_stored();
            $notice   = get_transient( 'pkart_quick_image_swap_license_notice_' . get_current_user_id() );

            if ( $notice ) {
                delete_transient( 'pkart_quick_image_swap_license_notice_' . get_current_user_id() );
            }
            ?>
            <h1><?php echo esc_html( $args['title'] ); ?></h1>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <p><?php echo esc_html( $args['description'] ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'pkart_quick_image_swap_license' ); ?>
                <input type="hidden" name="action" value="pkart_quick_image_swap_license">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="pkart-license-key"><?php esc_html_e( 'License key', 'quick-image-swap-for-woocommerce' ); ?></label></th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="pkart-license-key"
                                name="license_key"
                                value="<?php echo esc_attr( $license['license_key'] ?? '' ); ?>"
                                autocomplete="off"
                            >
                            <p class="description"><?php esc_html_e( 'Mock valid key: PKMOCK-2026-TEST-0001', 'quick-image-swap-for-woocommerce' ); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button class="button button-primary" type="submit" name="license_action" value="activate"><?php esc_html_e( 'Activate License', 'quick-image-swap-for-woocommerce' ); ?></button>
                    <button class="button" type="submit" name="license_action" value="validate"><?php esc_html_e( 'Validate License', 'quick-image-swap-for-woocommerce' ); ?></button>
                    <button class="button" type="submit" name="license_action" value="deactivate"><?php esc_html_e( 'Deactivate License', 'quick-image-swap-for-woocommerce' ); ?></button>
                </p>
            </form>
            <?php
        }
    }
}
