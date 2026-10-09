<?php

namespace Pkart\LicenseManager\V1\Licensing;

use Pkart\LicenseManager\V1\Licensing;
use Pkart\LicenseManager\V1\PackageConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pkart\\LicenseManager\\V1\\Licensing\\UpdateNotice' ) ) {

	/**
	 * "New version available" row under the plugin on the Plugins screen.
	 * @see https://developer.wordpress.org/reference/hooks/after_plugin_row_{$plugin_file}/
	 */
	class UpdateNotice {

		/**
		 * Package configuration.
		 *
		 * @var PackageConfig
		 */
		private $config;

		/**
		 * Text domain.
		 *
		 * @var string
		 */
		private $domain;

		/**
		 * Plugin basenames that already have a notice row registered.
		 *
		 * @var bool[]
		 */
		private static $registered = [];

		/**
		 * Register the Plugins screen row.
		 *
		 * @param Licensing|PackageConfig $source Licensing facade or package config.
		 */
		public function __construct( $source ) {
			$config   = $source instanceof Licensing ? $source->package_config() : $source;
			$basename = $config->plugin_basename();

			$this->config = $config;
			$this->domain = $config->product_sku();

			if ( ! $basename || isset( self::$registered[ $basename ] ) ) {
				return;
			}

			if ( ! self::$registered ) {
				add_action( 'load-plugins.php', [ __CLASS__, 'register_inactive_plugins' ] );
			}

			self::$registered[ $basename ] = true;

			add_action( 'after_plugin_row_' . $basename, [ $this, 'render_row' ], 10, 2 );
		}

		/**
		 * Add notice rows for inactive Pkart plugins, whose own code is not
		 * loaded. A Pkart plugin is any plugin folder whose package.json has
		 * pkart-data.
		 */
		public static function register_inactive_plugins() {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			foreach ( array_keys( get_plugins() ) as $plugin_file ) {
				$dir = dirname( $plugin_file );

				if ( '.' === $dir || isset( self::$registered[ $plugin_file ] ) ) {
					continue;
				}

				$package = WP_PLUGIN_DIR . '/' . $dir . '/package.json';

				if ( ! is_readable( $package ) ) {
					continue;
				}

				$config = new PackageConfig( $package );

				if ( $config->data() && $config->plugin_basename() === $plugin_file ) {
					new self( $config );
				}
			}
		}

		/**
		 * Return the newer version, or '' when the plugin is up to date.
		 *
		 * @return string
		 */
		public function available_version() {
			$current = $this->config->version();
			$latest  = $this->config->new_version();

			return ( $current && $latest && version_compare( $latest, $current, '>' ) ) ? $latest : '';
		}

		/**
		 * Render the notice row when a newer version is available.
		 *
		 * @param string $plugin_file Plugin basename.
		 * @param array  $plugin_data Plugin header data.
		 */
		public function render_row( $plugin_file, $plugin_data ) {
			if ( ! current_user_can( 'update_plugins' ) ) {
				return;
			}

			$new_version = $this->available_version();

			if ( ! $new_version ) {
				return;
			}

			$config  = $this->config;
			$name    = $plugin_data['Name'] ?? $config->title();
			$columns = _get_list_table( 'WP_Plugins_List_Table', [ 'screen' => get_current_screen() ] )->get_column_count();
			$active  = is_plugin_active( $plugin_file ) ? 'active' : 'inactive';

			$message = sprintf(
				/* translators: 1: plugin name, 2: new version. */
				__( 'There is a new version of %1$s available. Version %2$s is available, ', $this->domain ),
				$name,
				$new_version
			);
			?>
			<tr class="plugin-update-tr <?php echo esc_attr( $active ); ?>" id="<?php echo esc_attr( $config->plugin_slug() ); ?>-update" data-slug="<?php echo esc_attr( $config->plugin_slug() ); ?>" data-plugin="<?php echo esc_attr( $plugin_file ); ?>">
				<td colspan="<?php echo esc_attr( $columns ); ?>" class="plugin-update colspanchange">
					<div class="update-message notice inline notice-warning notice-alt">
						<p>
							<?php echo esc_html( $message ); ?>
						</p>
					</div>
					<script>
						// Join the notice to the plugin row: core only adds `update` (no divider) for its own updates.
						( function ( row ) { row && row.classList.add( 'update' ); } )( document.querySelector( 'tr[data-plugin="' + <?php echo wp_json_encode( $plugin_file ); ?> + '"]:not(.plugin-update-tr)' ) );
					</script>
				</td>
			</tr>
			<?php
		}

	}
}
