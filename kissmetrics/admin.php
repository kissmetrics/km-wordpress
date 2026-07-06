<?php
/**
 * Kissmetrics settings screen (Settings → Kissmetrics).
 *
 * @package Kissmetrics
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Kissmetrics_Admin' ) ) {

	/**
	 * Renders and persists the plugin configuration.
	 */
	class Kissmetrics_Admin {

		/**
		 * Boolean options toggled by checkboxes on the settings screen.
		 *
		 * Maps the form field name => the option name stored in wp_options.
		 *
		 * @var array
		 */
		private static $checkbox_options = array(
			'km_identify'              => 'kissmetrics_identify_users',
			'km_login'                 => 'kissmetrics_track_login',
			'km_signup_view'           => 'kissmetrics_track_signup_view',
			'km_signup'                => 'kissmetrics_track_signup',
			'km_views'                 => 'kissmetrics_track_views',
			'km_links'                 => 'kissmetrics_track_links',
			'km_comment_links'         => 'kissmetrics_track_comment_links',
			'km_social_buttons'        => 'kissmetrics_track_social',
			'km_search'                => 'kissmetrics_track_search',
			'km_comment'               => 'kissmetrics_track_comment',
			'km_identify_unregistered' => 'kissmetrics_identify_unregistered',
			'km_ecommerce'             => 'kissmetrics_track_ecommerce',
		);

		/**
		 * Add "Kissmetrics" under the Settings menu.
		 */
		public static function add_config_page() {
			add_submenu_page(
				'options-general.php',
				__( 'Kissmetrics Configuration', 'kissmetrics' ),
				__( 'Kissmetrics', 'kissmetrics' ),
				'manage_options',
				'kissmetrics',
				array( __CLASS__, 'config_page' )
			);
		}

		/**
		 * Persist the submitted settings (nonce-checked, capability-checked).
		 */
		private static function maybe_save() {
			if ( ! isset( $_POST['submit'] ) ) {
				return false;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to change these settings.', 'kissmetrics' ) );
			}
			check_admin_referer( 'km_config_action' );

			$key = isset( $_POST['km_key'] ) ? sanitize_text_field( wp_unslash( $_POST['km_key'] ) ) : '';
			update_option( 'kissmetrics_key', $key );

			foreach ( self::$checkbox_options as $field => $option ) {
				update_option( $option, isset( $_POST[ $field ] ) ? 1 : 0 );
			}

			return true;
		}

		/**
		 * Output a single checkbox row helper.
		 *
		 * @param string $field Form field / id.
		 * @param string $option Option name to read the checked state from.
		 * @param string $label Translated label.
		 * @param string $description Optional translated description.
		 */
		private static function checkbox( $field, $option, $label, $description = '' ) {
			printf(
				'<label for="%1$s"><input id="%1$s" name="%1$s" type="checkbox"%2$s> <span>%3$s</span></label>',
				esc_attr( $field ),
				checked( get_option( $option ), 1, false ),
				esc_html( $label )
			);
			if ( $description ) {
				echo ' <span class="description">' . esc_html( $description ) . '</span>';
			}
			echo '<br>';
		}

		/**
		 * Render the settings screen.
		 */
		public static function config_page() {
			$saved = self::maybe_save();
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Kissmetrics', 'kissmetrics' ); ?></h1>

				<?php if ( $saved ) : ?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Your settings have been saved.', 'kissmetrics' ); ?></p>
					</div>
				<?php endif; ?>

				<p>
					<?php esc_html_e( 'This official Kissmetrics plugin installs your tracking code and captures how visitors engage with your site — pageviews, sign-ups, search, comments, and WooCommerce purchases.', 'kissmetrics' ); ?>
				</p>

				<form action="" method="post" id="kissmetrics-config">
					<?php wp_nonce_field( 'km_config_action' ); ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="km_key"><?php esc_html_e( 'API Key', 'kissmetrics' ); ?></label></th>
								<td>
									<input id="km_key" name="km_key" type="text" class="regular-text code"
										value="<?php echo esc_attr( get_option( 'kissmetrics_key' ) ); ?>" />
									<p class="description">
										<?php
										printf(
											/* translators: %s: link to the Kissmetrics settings page. */
											esc_html__( 'Get your API key from the %s page.', 'kissmetrics' ),
											'<a href="https://app.kissmetrics.io/settings" target="_blank" rel="noopener">' . esc_html__( 'Kissmetrics Settings', 'kissmetrics' ) . '</a>'
										);
										?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Identity', 'kissmetrics' ); ?></th>
								<td>
									<?php
									self::checkbox( 'km_identify', 'kissmetrics_identify_users', __( 'Identify authenticated users', 'kissmetrics' ) );
									self::checkbox( 'km_login', 'kissmetrics_track_login', __( 'Track login event', 'kissmetrics' ) );
									?>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Registration', 'kissmetrics' ); ?></th>
								<td>
									<?php
									self::checkbox( 'km_signup_view', 'kissmetrics_track_signup_view', __( 'Track sign up page view', 'kissmetrics' ) );
									self::checkbox( 'km_signup', 'kissmetrics_track_signup', __( 'Track registration event', 'kissmetrics' ) );
									?>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'General tracking', 'kissmetrics' ); ?></th>
								<td>
									<?php
									self::checkbox( 'km_views', 'kissmetrics_track_views', __( 'Post/page views', 'kissmetrics' ) );
									self::checkbox( 'km_links', 'kissmetrics_track_links', __( 'Links in posts/pages', 'kissmetrics' ) );
									self::checkbox( 'km_comment_links', 'kissmetrics_track_comment_links', __( 'Links in comments', 'kissmetrics' ) );
									self::checkbox( 'km_social_buttons', 'kissmetrics_track_social', __( 'Social buttons (Facebook, Twitter)', 'kissmetrics' ) );
									self::checkbox( 'km_search', 'kissmetrics_track_search', __( 'Search queries', 'kissmetrics' ) );
									?>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Comments', 'kissmetrics' ); ?></th>
								<td>
									<?php
									self::checkbox( 'km_comment', 'kissmetrics_track_comment', __( 'Track comment submission', 'kissmetrics' ) );
									self::checkbox(
										'km_identify_unregistered',
										'kissmetrics_identify_unregistered',
										__( 'Identify unregistered users by comment email address', 'kissmetrics' ),
										__( '(A user can only be identified after they submit a comment.)', 'kissmetrics' )
									);
									?>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'WooCommerce', 'kissmetrics' ); ?></th>
								<td>
									<?php
									self::checkbox(
										'km_ecommerce',
										'kissmetrics_track_ecommerce',
										__( 'Track purchases (revenue, items, order ID)', 'kissmetrics' )
									);
									if ( ! class_exists( 'WooCommerce' ) ) {
										echo '<p class="description">' . esc_html__( 'WooCommerce is not active — this option has no effect until it is installed.', 'kissmetrics' ) . '</p>';
									}
									?>
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}
	}
}

add_action( 'admin_menu', array( 'Kissmetrics_Admin', 'add_config_page' ) );
