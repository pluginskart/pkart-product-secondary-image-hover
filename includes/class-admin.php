<?php

namespace PluginsKart\QuickImageSwap;

use PluginsKart\LicenseManager\V1\Licensing;
use PluginsKart\LicenseManager\V1\Licensing\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Admin' ) ) {

    class Admin {

        /** @var Licensing */
        private $licensing;
        /** @var API */
        private $api;
        private $product_sku;

        /**
         * Register the PRO license admin workflow.
         */
        public function __construct( Licensing $licensing ) {
            $this->licensing = $licensing;
            $this->api       = $licensing->api();
            $this->product_sku = $licensing->package_config()->product_sku();

            add_action( 'admin_menu', [ $this, 'register_menu' ] );
            add_filter( 'pluginskart_license_action_redirect', [ $this, 'license_action_redirect' ] );
        }

        /**
         * Return the plugin-owned Licensing tab after an action.
         */
        public function license_action_redirect() {
            return admin_url( 'options-general.php?page=pkart-quick-image-swap-settings&tab=license' );
        }

        /**
         * Add the plugin settings screen under Settings.
         */
        public function register_menu() {
            add_options_page(
                __( 'Quick Image Swap Settings', $this->api->package_value( 'product_sku' ) ),
                __( 'Quick Image Swap', $this->api->package_value( 'product_sku' ) ),
                'manage_options',
                'pkart-quick-image-swap-settings',
                [ $this, 'render_page' ]
            );
        }

        /**
         * Render the PRO license page.
         */
        public function render_page() {
            $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'license';
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Quick Image Swap Settings', $this->api->package_value( 'product_sku' ) ); ?></h1>
                <nav class="nav-tab-wrapper">
                    <a class="nav-tab <?php echo 'license' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=pkart-quick-image-swap-settings&tab=license' ) ); ?>">
                        <?php esc_html_e( 'Licensing', $this->api->package_value( 'product_sku' ) ); ?>
                    </a>
                </nav>

                <?php if ( 'license' === $tab ) : ?>
                    <?php $this->render_license_section(); ?>
                <?php endif; ?>
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
            $notice   = $this->licensing->get_admin_notice();
            ?>
            <h1><?php echo esc_html( $args['title'] ); ?></h1>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <p><?php echo esc_html( $args['description'] ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( $this->licensing->nonce_action() ); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr( $this->licensing->admin_action() ); ?>">
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
                    <button class="button button-primary" type="submit" name="license_action" value="activate"><?php esc_html_e( 'Activate License', $this->api->package_value( 'product_sku' ) ); ?></button>
                    <button class="button" type="submit" name="license_action" value="validate"><?php esc_html_e( 'Validate License', $this->api->package_value( 'product_sku' ) ); ?></button>
                    <button class="button" type="submit" name="license_action" value="deactivate"><?php esc_html_e( 'Deactivate License', $this->api->package_value( 'product_sku' ) ); ?></button>
                </p>
            </form>
            <?php
        }
    }
}
