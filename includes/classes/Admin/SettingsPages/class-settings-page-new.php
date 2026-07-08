<?php
/**
 * Settings New - a new settings page implemented as a static settings class.
 *
 * @package    wp2fa
 * @subpackage settings-pages
 */

namespace WP2FA\Admin\SettingsPages;

use WP2FA\WP2FA;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Settings_Page;
use WP2FA\Admin\SettingsPages\Settings_Page_General;
use WP2FA\Admin\SettingsPages\Settings_Page_White_Label;
use WP2FA\Admin\Helpers\Email_Templates;

if ( ! class_exists( '\WP2FA\Admin\SettingsPages\Settings_Page_New' ) ) {
	/**
	 * Class Settings_Page_New
	 *
	 * @package WP2FA\Admin\SettingsPages
	 */
	class Settings_Page_New {

		public const PAGE_SLUG = 'wp-2fa-settings-new';

		/**
		 * Initialize hooks for this settings page.
		 *
		 * @since 3.1.1.2
		 */
		public static function init() {

			// Register AJAX handlers — must be outside any page-specific hook.
			\add_action( 'wp_ajax_wp2fa_save_settings_new', array( __CLASS__, 'ajax_save' ) );
			\add_action( 'wp_ajax_wp2fa_send_test_email_new', array( __CLASS__, 'ajax_send_test_email' ) );

			\add_action(
				'admin_enqueue_scripts',
				function ( $hook ) {
					if ( 'wp-2fa_page_' . constant( __CLASS__ . '::PAGE_SLUG' ) === $hook ) {
						\wp_enqueue_style(
						'wp_2fa_settings_new_css',
							WP_2FA_URL . 'includes/assets/css/' . \sanitize_file_name( 'settings' ) . '.css',
							array(),
							WP_2FA_VERSION
						);

						// Color picker (WP core).
						\wp_enqueue_style( 'wp-color-picker' );
						\wp_enqueue_script( 'wp-color-picker' );

						// Media uploader (WP core).
						\wp_enqueue_media();

						// Customize styles JS.
						\wp_enqueue_script(
							'wp_2fa_customize_styles',
							WP_2FA_URL . 'includes/assets/js/customize-styles.js',
							array( 'jquery', 'wp-color-picker' ),
							WP_2FA_VERSION,
							true
						);

						\wp_localize_script(
							'wp_2fa_customize_styles',
							'wp2faCustomizeStyles',
							array(
								'selectMediaTitle'  => \esc_html__( 'Select Image', 'wp-2fa' ),
								'selectMediaButton' => \esc_html__( 'Use this image', 'wp-2fa' ),
							)
						);

						// Save-settings JS (vanilla, no jQuery dependency).
						\wp_enqueue_script(
							'wp_2fa_save_settings_new',
							WP_2FA_URL . 'includes/assets/js/save-settings-new.js',
							array(),
							WP_2FA_VERSION,
							true
						);

						// Hash-state JS — preserves section / sub-tab / accordion
						// state in the URL fragment so a refresh or shared link
						// returns to the same view.
						\wp_enqueue_script(
							'wp_2fa_settings_hash_state',
							WP_2FA_URL . 'includes/assets/js/settings-hash-state.js',
							array(),
							WP_2FA_VERSION,
							true
						);

						// Test-email JS (vanilla, no jQuery dependency).
						\wp_enqueue_script(
							'wp_2fa_test_email_new',
							WP_2FA_URL . 'includes/assets/js/test-email-new.js',
							array(),
							WP_2FA_VERSION,
							true
						);

						\wp_localize_script(
							'wp_2fa_test_email_new',
							'wp2faTestEmail',
							array(
								'ajaxUrl'     => \admin_url( 'admin-ajax.php' ),
								'nonce'       => \wp_create_nonce( 'wp2fa_send_test_email_new_nonce' ),
								'sendingText' => \esc_html__( 'Sending…', 'wp-2fa' ),
								'errorText'   => \esc_html__( 'An error occurred. Please try again.', 'wp-2fa' ),
							)
						);

						\wp_localize_script(
							'wp_2fa_save_settings_new',
							'wp2faSaveSettingsNew',
							array(
								'ajaxUrl'          => \admin_url( 'admin-ajax.php' ),
								'nonce'            => \wp_create_nonce( 'wp2fa_save_settings_new_nonce' ),
								'action'           => 'wp2fa_save_settings_new',
								'saveText'         => \esc_html__( 'Save settings', 'wp-2fa' ),
								'savingText'       => \esc_html__( 'Saving…', 'wp-2fa' ),
								'successText'      => \esc_html__( 'Settings saved.', 'wp-2fa' ),
								'errorText'        => \esc_html__( 'An error occurred. Please try again.', 'wp-2fa' ),
								'errorsModalTitle' => \esc_html__( 'Settings saved with warnings', 'wp-2fa' ),
								'errorsModalClose' => \esc_html__( 'OK, I understand', 'wp-2fa' ),
							)
						);
					}
				}
			);
			Settings_Page_White_Label::init();
		}

		/**
		 * Render the settings screen.
		 */
		public static function render() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			$main_user = ! empty( WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) ) ? (int) WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) : \get_current_user_id();
			if ( ! empty( WP2FA::get_wp2fa_general_setting( 'limit_access' ) ) && $main_user !== \get_current_user_id() ) {
				echo \esc_html__( 'These settings have been disabled by your site administrator, please contact them for further assistance.', 'wp-2fa' );
				return;
			}

			// Load saved values (single option array).
			$values = Settings_Utils::get_option( WP_2FA_NEW_SETTINGS_NAME, array() );

			// sensible defaults for this page.
			$defaults = array(
				'enabled'      => false,
				'layout'       => 'cards',
				'accent_color' => '#4776ff',
				'example_text' => '',
			);

			$values = \wp_parse_args( $values, $defaults );

			?>
			<div class="wp-2fa-settings-wrapper wp-2fa-settings-new">
				<div class="page-header">
					<!-- <h2><?php \esc_html_e( 'Settings New', 'wp-2fa' ); ?></h2> -->
					<div class="page-header-actions">
						<button type="button" class="button button-primary js-save-settings-new"><?php \esc_html_e( 'Save settings', 'wp-2fa' ); ?></button>
					</div>
				</div>

			<div class="main-settings-new">
				<div class="wrap main-left">
					<?php
					$is_white_labeling_separate = Settings_Page::is_new_interface_enabled();

					foreach ( self::collect_settings_tabs() as $tab => $settings ) {
						// When the White labeling page is separate, email-settings
						// are rendered there instead of here.
						if ( $is_white_labeling_separate && 'email-settings' === $tab ) {
							continue;
						}
						?>
						<div id="wp-2fa-options-tab-<?php echo \esc_attr( $tab ); ?>" class="tabs-wrap">

							<?php
							include_once \WP_2FA_PATH . '/includes/classes/Admin/Settings/templates/' . $tab . '.php';
							?>

						</div>
						<?php
					}
					if ( ! $is_white_labeling_separate ) {
						foreach ( self::register_white_labelling_settings( array() ) as $tab => $settings ) {
							?>
							<div id="wp-2fa-options-tab-<?php echo \esc_attr( $tab ); ?>" class="tabs-wrap">

								<?php
								include_once \WP_2FA_PATH . '/includes/classes/Admin/Settings/templates/' . $tab . '.php';
								?>

							</div>
							<?php
						}
					}
					?>
				</div>

				<?php include WP_2FA_PATH . 'includes/classes/Admin/Settings/templates/sidebar.php'; ?>
			</div>

			<div class="save-footer">
				<button type="button" class="button button-primary button-large js-save-settings-new"><?php \esc_html_e( 'Save settings', 'wp-2fa' ); ?></button>
			</div>
			</div>
			<?php
		}

		/**
		 * AJAX handler for saving settings from the Settings New page.
		 *
		 * Called via wp_ajax_wp2fa_save_settings_new.
		 * Security: nonce verified first, then manage_options capability checked.
		 * Any data inside the WP_2FA_NEW_SETTINGS_NAME option group is sanitised
		 * through the existing validate_and_sanitize() method (which fires the
		 * wp2fa_settings_new_validate filter). Other settings present in the POST
		 * payload are handed off via the wp2fa_settings_new_ajax_save action so
		 * additional option groups can hook in and save themselves.
		 *
		 * @since 3.1.1.2
		 */
		public static function ajax_save() {
			// 1. Nonce check — must happen before any data access.
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! \wp_verify_nonce( $nonce, 'wp2fa_save_settings_new_nonce' ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'Security check failed. Please refresh the page and try again.', 'wp-2fa' ) ),
					403
				);
			}

			// 2. Capability check.
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'You do not have permission to perform this action.', 'wp-2fa' ) ),
					403
				);
			}

			if ( isset( $_POST[ WP_2FA_SETTINGS_NAME ] ) && is_array( $_POST[ WP_2FA_SETTINGS_NAME ] ) ) {
				$existing_general = Settings_Utils::get_option( WP_2FA_SETTINGS_NAME, array() );
				$options          = Settings_Page_General::validate_and_sanitize( $_POST[ WP_2FA_SETTINGS_NAME ] ); // call the generic validate_and_sanitize to trigger its filter, in case any settings tabs use it.

				// Merge validated output on top of existing settings, but only
				// for keys actually submitted by the form — see the policy
				// handler for a detailed explanation.
				if ( \is_array( $options ) && \is_array( $existing_general ) ) {
					$submitted_keys = \array_keys( $_POST[ WP_2FA_SETTINGS_NAME ] );
					$merged         = $existing_general;
					foreach ( $options as $key => $value ) {
						if ( \in_array( $key, $submitted_keys, true ) || ! \array_key_exists( $key, $existing_general ) ) {
							$merged[ $key ] = $value;
						}
					}
					$options = $merged;
				}

				WP2FA::update_plugin_settings( $options, false, WP_2FA_SETTINGS_NAME );
			}

			if ( isset( $_POST['email_from_setting'] ) ) {
				$options = Settings_Page_Email::validate_and_sanitize_new( \wp_unslash( $_POST ) );

				Settings_Utils::update_option( WP_2FA_EMAIL_SETTINGS_NAME, $options );
			}

			// 4. Allow other option groups rendered on this page to hook in and
			// save their own data. Each hook receives the full (unslashed) POST
			// payload so it can extract and sanitise its own sub-array.
			\do_action( WP_2FA_PREFIX . 'settings_new_ajax_save', \wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// 5. Collect any validation errors added during this request and return
			// them so the JS layer can surface them to the user.
			global $wp_settings_errors;
			if ( ! empty( $wp_settings_errors ) ) {
				$errors = array();
				foreach ( $wp_settings_errors as $error ) {
					$errors[] = array(
						'code'    => isset( $error['code'] ) ? \sanitize_key( $error['code'] ) : '',
						'message' => isset( $error['message'] ) ? \wp_strip_all_tags( $error['message'] ) : '',
						'type'    => isset( $error['type'] ) ? \sanitize_key( $error['type'] ) : 'error',
					);
				}
				\wp_send_json_success(
					array(
						'message' => esc_html__( 'Settings saved with warnings.', 'wp-2fa' ),
						'errors'  => $errors,
					)
				);
			}

			\wp_send_json_success(
				array( 'message' => \esc_html__( 'Settings saved.', 'wp-2fa' ) )
			);
		}

		/**
		 * AJAX handler for sending a test email from the Settings New page.
		 *
		 * If the request includes a subject and body (from the form's textarea /
		 * TinyMCE editor), the body is run through Email_Templates::replace_email_strings()
		 * so token placeholders are resolved, then sent via Settings_Page::send_email().
		 *
		 * When no body is provided (the generic "Test email delivery" button), a
		 * simple hard-coded test message is sent instead.
		 *
		 * @since 3.1.1.2
		 */
		public static function ajax_send_test_email() {
			// 1. Nonce check.
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! \wp_verify_nonce( $nonce, 'wp2fa_send_test_email_new_nonce' ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'Security check failed. Please refresh the page and try again.', 'wp-2fa' ) ),
					403
				);
			}

			// 2. Capability check.
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'You do not have permission to perform this action.', 'wp-2fa' ) ),
					403
				);
			}

			// 3. Resolve the recipient — always the current user.
			$user  = \wp_get_current_user();
			$email = \sanitize_email( $user->user_email );

			if ( empty( $email ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'Your account does not have an email address.', 'wp-2fa' ) )
				);
			}

			// 4. Determine subject & body.
			$raw_subject = isset( $_POST['subject'] ) ? \sanitize_text_field( \wp_unslash( $_POST['subject'] ) ) : '';
			$raw_body    = isset( $_POST['body'] ) ? \wp_kses_post( \wp_unslash( $_POST['body'] ) ) : '';

			if ( '' === $raw_body ) {
				// Generic delivery test — no template content available.
				$subject = \esc_html__( 'Test email from WP 2FA', 'wp-2fa' );
				$message = \esc_html__( 'This email was sent by the WP 2FA plugin to test the email delivery.', 'wp-2fa' );
			} else {
				$subject = $raw_subject;
				$message = \wpautop( Email_Templates::replace_email_strings( $raw_body, (string) $user->ID ) );
			}

			// 5. Send.
			$sent = Settings_Page::send_email( $email, $subject, $message );

			if ( $sent ) {
				\wp_send_json_success(
					array(
						/* translators: %s: recipient email address */
						'message' => \wp_sprintf( \esc_html__( 'Test email was successfully sent to %s', 'wp-2fa' ), '<strong>' . \esc_html( $email ) . '</strong>' ),
					)
				);
			}

			\wp_send_json_error(
				array( 'message' => \esc_html__( 'Failed to send test email. This is usually caused by an SMTP issue, a restricted "from" address, or your host blocking outgoing mail. Check your email settings or contact your hosting provider.', 'wp-2fa' ) )
			);
		}

		/**
		 * Validate and sanitize the options submitted from the Settings New screen.
		 *
		 * @param array $input Raw input array.
		 * @return array Sanitized output array.
		 */
		public static function validate_and_sanitize( $input ) {
			$out = array();

			$out['enabled']      = ( isset( $input['enabled'] ) && (bool) $input['enabled'] ) ? true : false;
			$out['layout']       = ( isset( $input['layout'] ) && in_array( $input['layout'], array( 'cards', 'compact' ), true ) ) ? $input['layout'] : 'cards';
			$out['accent_color'] = ( isset( $input['accent_color'] ) ) ? \sanitize_hex_color( $input['accent_color'] ) : '#4776ff';
			$out['example_text'] = ( isset( $input['example_text'] ) ) ? \sanitize_text_field( $input['example_text'] ) : '';

			// Let other code alter/validate before we save.
			$out = \apply_filters( WP_2FA_PREFIX . 'settings_new_validate', $out, $input );

			return $out;
		}

		/**
		 * Save network-level options (if used in multisite).
		 */
		public static function update_wp2fa_network_options() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-2fa' ) );
			}

			if ( isset( $_POST[ WP_2FA_NEW_SETTINGS_NAME ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				\check_admin_referer( WP_2FA_NEW_SETTINGS_NAME . '-options' );
				$options = self::validate_and_sanitize( wp_unslash( $_POST[ WP_2FA_NEW_SETTINGS_NAME ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				Settings_Utils::update_option( WP_2FA_NEW_SETTINGS_NAME, $options );
			}
		}

		/**
		 * Register the general settings tabs to be rendered on the settings page.
		 *
		 * @param array $collected_general_settings Array of already collected general settings tabs (if any).
		 * @return array Updated array of general settings tabs.
		 *
		 * @since 3.1.1.2
		 */
		public static function register_general_settings( array $collected_general_settings ): array {
			// $general_settings = array(
			// 'generic-settings'       => array(
			// 'id'    => 'generic-settings',
			// 'title' => \esc_html__( 'General settings', 'wp-2fa' ),
			// ),
			// 'providers-integrations' => array(
			// 'id'    => 'providers-integrations',
			// 'title' => \esc_html__( '2FA Providers integrations', 'wp-2fa' ),
			// ),
			// 'plugins-integrations'   => array(
			// 'id'    => 'plugins-integrations',
			// 'title' => \esc_html__( 'Plugin integrations', 'wp-2fa' ),
			// ),
			// 'email-settings'         => array(
			// 'id'    => 'email-settings',
			// 'title' => \esc_html__( 'Emails & SMS Templates', 'wp-2fa' ),
			// ),
			// 'settings-import'        => array(
			// 'id'    => 'settings-import',
			// 'title' => \esc_html__( 'Export / import', 'wp-2fa' ),
			// ),
			// );

			$general_settings = self::collect_settings_tabs();

			\array_shift( $general_settings ); // remove the first one (general-settings landing), as that will be rendered in a different section.

			// When the White labeling page is a separate sub-menu, email-settings
			// are shown there instead of here.
			if ( Settings_Page::is_new_interface_enabled() ) {
				unset( $general_settings['email-settings'] );
			}

			return \array_merge( $collected_general_settings, $general_settings );
		}

		/**
		 * Register the white labelling settings tabs to be rendered on the settings page.
		 *
		 * @param array $collected_white_labelling_settings Array of already collected white labelling settings tabs (if any).
		 *
		 * @return array Updated array of white labelling settings tabs.
		 *
		 * @since 3.1.1.2
		 */
		public static function register_white_labelling_settings( array $collected_white_labelling_settings ): array {
			$white_labelling_settings = array(
				'customize-code-page'    => array(
					'id'    => 'customize-code-page',
					'title' => \esc_html__( 'Customize 2FA code page', 'wp-2fa' ),
				),
				'customize-setup-wizard' => array(
					'id'    => 'customize-setup-wizard',
					'title' => \esc_html__( 'Customize 2FA setup wizard', 'wp-2fa' ),
				),
			);

			return \array_merge( $collected_white_labelling_settings, $white_labelling_settings );
		}

		/**
		 * Collect the settings tabs to be rendered on the left side of the settings page.
		 *
		 * @return array Array of tabs, each with 'id' and 'title'.
		 *
		 * @since 3.1.1.2
		 */
		private static function collect_settings_tabs(): array {

			$settings_tabs = array(
				// Thats the main.
				'general-settings'       => array(
					'id'    => 'general-settings',
					'title' => \esc_html__( 'Settings', 'wp-2fa' ),
				),
				'generic-settings'       => array(
					'id'    => 'generic-settings',
					'title' => \esc_html__( 'General settings', 'wp-2fa' ),
				),
			);


			// $settings_tabs = \array_merge( $settings_tabs, self::register_white_labelling_settings( array() ) );

			return $settings_tabs;
		}
	}
}
