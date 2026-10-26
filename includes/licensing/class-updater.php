<?php

namespace Pkart\LicenseManager\V1\Licensing;

use Pkart\LicenseManager\V1\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pkart\\LicenseManager\\V1\\Licensing\\Updater' ) ) {

	/**
	 * Expected `plugin?action=update-checker` response (only for a valid license):
	 *   {
	 *     "success": true,
	 *     "name": "Product Secondary Image Hover",
	 *     "new_version": "1.1.0", "homepage": "https://...",
	 *     "requires": "6.7", "tested": "7.1", "requires_php": "8.1",
	 *     "last_updated": "2026-10-24"
	 *   }
	 */
	class Updater {

		/**
		 * @var Licensing
		 */
		private $licensing;

		/**
		 * Plugin basename, e.g. my-plugin/my-plugin.php.
		 *
		 * @var string
		 */
		private $basename;

		/**
		 * Plugin slug (directory name).
		 *
		 * @var string
		 */
		private $slug;

		/**
		 * Installed plugin version.
		 *
		 * @var string
		 */
		private $version;

		/**
		 * API result for the current request only; null until the API has been called.
		 *
		 * @var array|false|null
		 */
		private $remote = null;

		/**
		 * @param Licensing $licensing   Licensing instance used to call the API.
		 */
		public function __construct( Licensing $licensing, $plugin_file, $version ) {

			$this->licensing = $licensing;
			$this->basename  = plugin_basename( $plugin_file );
			$this->slug      = dirname( $this->basename );
			$this->version   = $version;

			add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
			add_action( 'load-plugins.php', [ $this, 'replace_update_row' ], 30 );

		}

		/**
		 * Add this plugin to the update_plugins transient when WordPress checks for updates.
		 */
		public function check_for_update( $transient ) {
			if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
				return $transient;
			}

			$remote = $this->get_remote();

			if ( ! $remote ) {
				return $transient;
			}

			$item = (object) [
				'id'           => $this->basename,
				'slug'         => $this->slug,
				'plugin'       => $this->basename,
				'new_version'  => $remote['new_version'],
				'url'          => $remote['homepage'] ?? '',
				'requires'     => $remote['requires'] ?? '',
				'tested'       => $remote['tested'] ?? '',
				'requires_php' => $remote['requires_php'] ?? '',
			];

			if ( version_compare( $this->version, $remote['new_version'], '<' ) ) {
				$transient->response[ $this->basename ] = $item;
				unset( $transient->no_update[ $this->basename ] );
			} else {
				$transient->no_update[ $this->basename ] = $item;
				unset( $transient->response[ $this->basename ] );
			}

			return $transient;
		}

		/**
		 * Call `plugin?action=update-checker` once per request and normalise the response.
		 * @return array|false API response or false if no update is available.
		 */
		private function get_remote() {
			if ( null !== $this->remote ) {
				return $this->remote;
			}

			$response = $this->licensing->plugin_update_checker();

			if ( is_wp_error( $response ) || empty( $response['success'] ) ) {
				return $this->remote = false;
			}

			$data = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;

			$data['new_version'] = $data['new_version'] ?? $data['version'] ?? '';

			if ( ! $data['new_version'] ) {
				return $this->remote = false;
			}

			return $this->remote = $data;
		}

		public function replace_update_row() {
			remove_action( "after_plugin_row_{$this->basename}", 'wp_plugin_update_row', 10 );
			add_action( "after_plugin_row_{$this->basename}", [ $this, 'update_row' ], 10, 2 );
		}

		public function update_row( $file, $plugin_data ) {
			$update = get_site_transient( 'update_plugins' )->response[ $file ] ?? null;
			if ( ! $update ) {
				return;
			}

			// Use the URL from the API if it sent one, otherwise the default site page.
			$details_url = ! empty( $update->url ) ? $update->url : 'https://www.pluginskart.com/';

			printf(
				'<tr class="plugin-update-tr active"><td colspan="4" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s<span class="screen-reader-text"> %4$s</span></a></p></div></td></tr>',
				esc_html( sprintf( 'Version %s of %s is available for your licensed site.', $update->new_version, $plugin_data['Name'] ) ),
				esc_url( $details_url ),
				esc_html__( 'View details', $this->basename ),
				esc_html__( '(opens in a new tab)', $this->basename )
			);
		}

	}
}
