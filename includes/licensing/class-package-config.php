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
		 * How many directories to walk up looking for package.json.
		 */
		const MAX_DEPTH = 4;

		/**
		 * Complete decoded package data.
		 *
		 * @var array
		 */
		private $config = array();

		/**
		 * The pkart-data section of the package.
		 *
		 * @var array
		 */
		private $data = array();

		/**
		 * Package file path.
		 *
		 * @var string
		 */
		private $package_file = '';

		/**
		 * Memoized derived values.
		 *
		 * @var array
		 */
		private $cache = array();

		/**
		 * Constructor.
		 *
		 * @param string $plugin_file A file inside the plugin.
		 */
		public function __construct( $plugin_file ) {
			$this->package_file = self::locate_package_file( $plugin_file );

			if ( ! $this->package_file ) {
				return;
			}

			$config = wp_json_file_decode( $this->package_file, array( 'associative' => true ) );

			if ( is_array( $config ) ) {
				$this->config = $config;

				if ( isset( $config['pkart-data'] ) && is_array( $config['pkart-data'] ) ) {
					$this->data = $config['pkart-data'];
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

			$directory = is_dir( $path ) ? untrailingslashit( $path ) : dirname( $path );

			for ( $i = 0; $i < self::MAX_DEPTH; $i++ ) {
				$candidate = $directory . '/package.json';

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
		 * Return a memoized value, computing it on first access.
		 *
		 * @param string   $key      Cache key.
		 * @param callable $callback Producer.
		 * @return mixed
		 */
		private function remember( $key, $callback ) {
			if ( ! array_key_exists( $key, $this->cache ) ) {
				$this->cache[ $key ] = $callback();
			}

			return $this->cache[ $key ];
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
			return $this->data;
		}

		/**
		 * Return one value from pkart-data.
		 *
		 * @param string $key     Metadata key.
		 * @param mixed  $default Default value.
		 * @return mixed
		 */
		public function get( $key, $default = '' ) {
			return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
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

			return '' !== $value ? $value : $default;
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
			return $this->remember(
				'plugin_slug',
				function () {
					$slug = $this->string( 'plugin_slug' );

					return sanitize_key( $slug ? $slug : $this->string( 'product_sku' ) );
				}
			);
		}

		/**
		 * Return the product SKU.
		 *
		 * @return string
		 */
		public function product_sku() {
			return $this->remember(
				'product_sku',
				function () {
					return $this->string( 'product_sku', $this->plugin_slug() );
				}
			);
		}

		/**
		 * Return the plugin version.
		 *
		 * @return string
		 */
		public function version() {
			return $this->string( 'version' );
		}

		/**
		 * Return the plugin new version.
		 *
		 * @return string
		 */
		public function new_version() {
			return $this->string( 'new_version' );
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
		 * The conventional {slug}/{slug}.php file is checked first. Otherwise
		 * the title from package.json identifies the WordPress plugin header,
		 * avoiding another plugin-specific setting.
		 *
		 * @return string
		 */
		public function plugin_basename() {
			return $this->remember( 'plugin_basename', array( $this, 'find_plugin_basename' ) );
		}

		/**
		 * Resolve the plugin main file basename.
		 *
		 * @return string
		 */
		private function find_plugin_basename() {
			$slug  = $this->product_sku();
			$title = $this->title();
			$root  = $this->package_file ? dirname( $this->package_file ) : '';

			if ( $root && $title ) {
				$candidates = glob( $root . '/*.php' );
				$candidates = is_array( $candidates ) ? $candidates : array();

				// Check the conventional main file first to avoid reading every header.
				$preferred = $root . '/' . $slug . '.php';
				$index     = array_search( $preferred, $candidates, true );

				if ( false !== $index ) {
					unset( $candidates[ $index ] );
					array_unshift( $candidates, $preferred );
				}

				foreach ( $candidates as $file ) {
					$headers = get_file_data( $file, array( 'Name' => 'Plugin Name' ), 'plugin' );

					if ( $headers['Name'] === $title ) {
						return plugin_basename( $file );
					}
				}
			}

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
			return ! empty( $this->data ) && '' !== $this->product_sku();
		}
	}
}
