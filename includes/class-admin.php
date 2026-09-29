<?php

namespace Pkart\QuickImageSwap;

use Pkart\LicenseManager\V1\Licensing;
use Pkart\LicenseManager\V1\Licensing\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Admin' ) ) {

    class Admin {

        /** @var API */
        private $api;
        private $licensing;
        private $product_sku;

        /**
         * Register the PRO license admin workflow.
         */
        public function __construct( Licensing $licensing ) {
            $this->licensing = $licensing;
            $this->api       = $licensing->api();
            $config          = $licensing->package_config();
            $this->product_sku = $config->product_sku();

            add_action( 'admin_menu', [ $this, 'register_menu' ] );

        }

        /**
         * Add the license screen under Settings.
         */
        public function register_menu() {
            add_options_page(
                __( 'Quick Image Swap PRO', $this->api->package_value( 'product_sku' ) ),
                __( 'Quick Image Swap PRO', $this->api->package_value( 'product_sku' ) ),
                'manage_options',
                'pkart-quick-image-swap-license',
                [ $this, 'render_page' ]
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
                'title'       => __( 'Quick Image Swap PRO License', $this->api->package_value( 'product_sku' ) ),
                'description' => __( 'Activate your license key to enable PRO updates and licensing validation.', $this->api->package_value( 'product_sku' ) ),
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
                        <th scope="row"><label for="pkart-license-key"><?php esc_html_e( 'License key', $this->api->package_value( 'product_sku' ) ); ?></label></th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="pkart-license-key"
                                name="license_key"
                                value="<?php echo esc_attr( $license['license_key'] ?? '' ); ?>"
                                autocomplete="off"
                            >
                            <p class="description"><?php esc_html_e( 'Mock valid key: PKMOCK-2026-TEST-0001', $this->api->package_value( 'product_sku' ) ); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <?php if ( $this->licensing->is_active() ) : ?>
                        <button class="button" type="submit" name="license_action" value="deactivate">
                            <?php esc_html_e( 'Deactivate License', $this->api->package_value( 'product_sku' ) ); ?>
                        </button>
                    <?php else : ?>
                        <button class="button button-primary" type="submit" name="license_action" value="activate">
                            <?php esc_html_e( 'Activate License', $this->api->package_value( 'product_sku' ) ); ?>
                        </button>
                    <?php endif; ?>
                </p>
            </form>
            <?php
        }
    }
}
