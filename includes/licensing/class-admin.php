<?php
/**
 * PluginsKart Licensing admin UI.
 *
 * @package PluginsKart\LicenseManager
 */

namespace PluginsKart\LicenseManager\V1;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Admin' ) ) {

	/**
	 * Generic license settings page.
	 */
	class Admin {

		/**
		 * Licensing instance.
		 *
		 * @var Licensing
		 */
		private $licensing;

		/**
		 * Constructor.
		 *
		 * @param Licensing $licensing Licensing instance.
		 */
		public function __construct( Licensing $licensing ) {
			$this->licensing = $licensing;
		}

		/**
		 * Register the license settings page.
		 */
		public function register_menu() {
			$config = $this->licensing->package_config();
			$title  = $config->title();

			add_options_page(
				$title,
				$title,
				'manage_options',
				$this->licensing->admin_page_slug(),
				array( $this, 'render_page' )
			);
		}

		/**
		 * Handle activation, validation and deactivation.
		 */
		public function handle_license_action() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die(
					esc_html__( 'You are not allowed to manage this license.', $this->text_domain() )
				);
			}

			check_admin_referer( $this->licensing->nonce_action() );

			$action = isset( $_POST['license_action'] )
				? sanitize_key( wp_unslash( $_POST['license_action'] ) )
				: '';

			$license_key = isset( $_POST['license_key'] )
				? sanitize_text_field( wp_unslash( $_POST['license_key'] ) )
				: '';

			switch ( $action ) {
				case 'activate':
					$response = $this->licensing->activate( $license_key );
					break;

				case 'validate':
					$response = $this->licensing->validate();
					break;

				case 'deactivate':
					$response = $this->licensing->deactivate();
					break;

				default:
					$response = new \WP_Error(
						'pluginskart_invalid_license_action',
						__( 'Invalid license action.', $this->text_domain() )
					);
					break;
			}

			$this->store_notice( $response );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => $this->licensing->admin_page_slug(),
					),
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}

		/**
		 * Render the license page.
		 */
		public function render_page() {
			$config  = $this->licensing->package_config();
			$license = $this->licensing->get_stored();
			$notice  = get_transient( $this->notice_key() );

			if ( false !== $notice ) {
				delete_transient( $this->notice_key() );
			}

			?>
			<div class="wrap">
				<h1>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: plugin title. */
							__( '%s License', $this->text_domain() ),
							$config->title()
						)
					);
					?>
				</h1>

				<?php if ( is_array( $notice ) ) : ?>
					<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
						<p><?php echo esc_html( $notice['message'] ); ?></p>
					</div>
				<?php endif; ?>

				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: plugin title. */
							__( 'Activate your %s license to enable licensed functionality and plugin updates.', $this->text_domain() ),
							$config->title()
						)
					);
					?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( $this->licensing->nonce_action() ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( $this->licensing->admin_action() ); ?>">

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $this->field_id() ); ?>">
									<?php esc_html_e( 'License key', $this->text_domain() ); ?>
								</label>
							</th>
							<td>
								<input
									type="text"
									class="regular-text"
									id="<?php echo esc_attr( $this->field_id() ); ?>"
									name="license_key"
									value="<?php echo esc_attr( $license['license_key'] ?? '' ); ?>"
									autocomplete="off"
								>
								<?php if ( $this->licensing->is_active() ) : ?>
									<p class="description">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: license status. */
												__( 'License status: %s', $this->text_domain() ),
												$this->licensing->get_status()
											)
										);
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button class="button button-primary" type="submit" name="license_action" value="activate">
							<?php esc_html_e( 'Activate License', $this->text_domain() ); ?>
						</button>

						<button class="button" type="submit" name="license_action" value="validate">
							<?php esc_html_e( 'Validate License', $this->text_domain() ); ?>
						</button>

						<button class="button" type="submit" name="license_action" value="deactivate">
							<?php esc_html_e( 'Deactivate License', $this->text_domain() ); ?>
						</button>
					</p>
				</form>
			</div>
			<?php
		}

		/**
		 * Store a one-time admin notice.
		 *
		 * @param array|\WP_Error $response API response.
		 */
		private function store_notice( $response ) {
			$success = ! is_wp_error( $response ) && ! empty( $response['success'] );

			$message = is_wp_error( $response )
				? $response->get_error_message()
				: ( $response['message'] ?? __( 'License request completed.', $this->text_domain() ) );

			set_transient(
				$this->notice_key(),
				array(
					'type'    => $success ? 'updated' : 'error',
					'message' => sanitize_text_field( $message ),
				),
				30
			);
		}

		/**
		 * Return a per-plugin/per-user notice key.
		 *
		 * @return string
		 */
		private function notice_key() {
			$key = 'pkart_' . $this->licensing->package_config()->plugin_slug() . '_license_notice_' . get_current_user_id();

			return strlen( $key ) <= 172
				? $key
				: 'pkart_license_notice_' . md5( $key );
		}

		/**
		 * Return a per-plugin field ID.
		 *
		 * @return string
		 */
		private function field_id() {
			return 'pkart-' . $this->licensing->package_config()->plugin_slug() . '-license-key';
		}

		/**
		 * Return text domain.
		 *
		 * @return string
		 */
		private function text_domain() {
			return $this->licensing->package_config()->string(
				'text_domain',
				$this->licensing->package_config()->plugin_slug()
			);
		}
	}
}
