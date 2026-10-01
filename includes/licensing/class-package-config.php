<?php
/**
 * Pkart package configuration.
 *
 * @package Pkart\LicenseManager
 */

namespace Pkart\LicenseManager\V1;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\PackageConfig' ) ) {

	/**
	 * Read and expose the plugin's package.json metadata.
	 *
	 * A package path is kept per instance so multiple plugins can use the
	 * same SDK classes without sharing package metadata.
	 */
	final class PackageConfig {

		/**
		 * Complete decoded package data.
		 *
		 * @var array
		 */
		private $config = array();

		/**
		 * Package file path.
		 *
		 * @var string
		 */
		private $package_file = '';

		/**
		 * Constructor.
		 *
		 * @param string $plugin_file A file inside the plugin.
		 */
		public function __construct( $plugin_file ) {
			$this->package_file = self::locate_package_file( $plugin_file );

			if ( $this->package_file && is_readable( $this->package_file ) ) {
				$data = wp_json_file_decode(
					$this->package_file,
					array( 'associative' => true )
				);

				if ( is_array( $data ) ) {
					$this->config = $data;
				}
			}
		}

		/**
		 * Locate package.json from a plugin file.
		 *
		 * @param string $plugin_file A file inside the plugin.
		 * @return string
		 */
		private static function locate_package_file( $plugin_file ) {
			$path = wp_normalize_path( (string) $plugin_file );

			if ( ! $path ) {
				return '';
			}

			$directory = is_dir( $path ) ? $path : dirname( $path );

			for ( $i = 0; $i < 4; $i++ ) {
				$candidate = trailingslashit( $directory ) . 'package.json';

				if ( is_readable( $candidate ) ) {
					return $candidate;
				}

				$parent = dirname( $directory );

				if ( $parent === $directory ) {
					break;
				}

				$directory = $parent;
			}

			return '';
		}

		/**
		 * Return complete package data.
		 *
		 * @return array
		 */
		public function all() {
			return $this->config;
		}

		/**
		 * Return the Pkart metadata section.
		 *
		 * @return array
		 */
		public function data() {
			$data = isset( $this->config['pkart-data'] )
				? $this->config['pkart-data']
				: array();

			return is_array( $data ) ? $data : array();
		}

		/**
		 * Return one value from pkart-data.
		 *
		 * @param string $key     Metadata key.
		 * @param mixed  $default Default value.
		 * @return mixed
		 */
		public function get( $key, $default = '' ) {
			$value = $this->data();

			return array_key_exists( $key, $value ) ? $value[ $key ] : $default;
		}

		/**
		 * Return a sanitized string metadata value.
		 *
		 * @param string $key     Metadata key.
		 * @param string $default Default value.
		 * @return string
		 */
		public function string( $key, $default = '' ) {
			$value = $this->get( $key, $default );

			if ( ! is_scalar( $value ) ) {
				return $default;
			}

			$value = sanitize_text_field( (string) $value );

			return '' !== trim( $value ) ? $value : $default;
		}

		/**
		 * Return a sanitized plugin slug.
		 *
		 * plugin_slug is preferred. product_sku is retained as a backwards-
		 * compatible fallback for existing package.json files.
		 *
		 * @return string
		 */
		public function plugin_slug() {
			$slug = $this->string( 'plugin_slug' );

			if ( ! $slug ) {
				$slug = $this->string( 'product_sku' );
			}

			return sanitize_key( $slug );
		}

		/**
		 * Return the product SKU.
		 *
		 * @return string
		 */
		public function product_sku() {
			return sanitize_text_field( $this->string( 'product_sku', $this->plugin_slug() ) );
		}

		/**
		 * Return the plugin version.
		 *
		 * @return string
		 */
		public function version() {
			return $this->string( 'version', '' );
		}

		/**
		 * Return the plugin title.
		 *
		 * @return string
		 */
		public function title() {
			return $this->string( 'title', $this->product_sku() );
		}

		/**
		 * Return the API base URL.
		 *
		 * @return string
		 */
		public function api_base_url() {
			return $this->string( 'api_base_url', 'https://wordpress.local/wp-json/pkart-sdk/v1/' );
		}

		/**
		 * Return the plugin main file basename.
		 *
		 * The title from package.json is used to identify the WordPress
		 * plugin header, avoiding another plugin-specific setting.
		 *
		 * @return string
		 */
		public function plugin_basename() {
			$title = $this->title();

			if ( $title ) {
				$root = $this->package_file ? dirname( $this->package_file ) : '';

				if ( $root && is_dir( $root ) ) {
					$files = glob( trailingslashit( $root ) . '*.php' );

					if ( is_array( $files ) ) {
						foreach ( $files as $file ) {
							$headers = get_file_data(
								$file,
								array( 'Name' => 'Plugin Name' ),
								'plugin'
							);

							if ( ! empty( $headers['Name'] ) && $headers['Name'] === $title ) {
								return plugin_basename( $file );
							}
						}
					}
				}
			}

			$slug = $this->product_sku();

			return $slug ? $slug . '/' . $slug . '.php' : '';
		}

		/**
		 * Return the package.json path.
		 *
		 * @return string
		 */
		public function package_file() {
			return $this->package_file;
		}

		/**
		 * Check whether a usable package configuration exists.
		 *
		 * @return bool
		 */
		public function is_valid() {
			return ! empty( $this->config['pkart-data'] )
				&& is_array( $this->data() )
				&& '' !== $this->product_sku();
		}
	}
}
