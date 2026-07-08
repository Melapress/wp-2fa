<?php
/**
 * White labeling New - a dedicated settings sub-page for white labeling
 * and email/SMS templates when the new interface is active.
 *
 * @package    wp2fa
 * @subpackage settings-pages
 */

namespace WP2FA\Admin\SettingsPages;

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Page;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Licensing\Licensing_Factory;
use WP2FA\Admin\Helpers\Email_Templates;

if ( ! class_exists( '\WP2FA\Admin\SettingsPages\Settings_Page_White_Labeling_New' ) ) {
	/**
	 * Class Settings_Page_White_Labeling_New
	 *
	 * Handles the separate "White labeling" sub-menu page that is shown
	 * only when use_new_interface is enabled.
	 *
	 * @package WP2FA\Admin\SettingsPages
	 *
	 * @since 4.0.0
	 */
	class Settings_Page_White_Labeling_New {

		public const PAGE_SLUG = 'wp-2fa-white-labeling';

		/**
		 * Initialize hooks for this settings page.
		 *
		 * @since 4.0.0
		 */
		public static function init() {
			\add_action(
				'admin_enqueue_scripts',
				function ( $hook ) {
					if ( 'wp-2fa_page_' . self::PAGE_SLUG === $hook ) {
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

						// Save-settings JS — reuses the same AJAX action
						// (wp2fa_save_settings_new) that the main settings page uses.
						\wp_enqueue_script(
							'wp_2fa_save_settings_new',
							WP_2FA_URL . 'includes/assets/js/save-settings-new.js',
							array(),
							WP_2FA_VERSION,
							true
						);

						// Hash-state JS — preserves section / sub-tab / accordion
						// state in the URL fragment.
						\wp_enqueue_script(
							'wp_2fa_settings_hash_state',
							WP_2FA_URL . 'includes/assets/js/settings-hash-state.js',
							array(),
							WP_2FA_VERSION,
							true
						);

						// Test-email JS.
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
		}

		/**
		 * Collect the tabs rendered on this page.
		 *
		 * @return array
		 *
		 * @since 4.0.0
		 */
		public static function collect_tabs(): array {
			$lbl = \esc_html__( 'Customize Emails Templates', 'wp-2fa' );

			$settings_array = array(
				'email-settings'         => array(
					'id'    => 'email-settings',
					'title' => $lbl,
				),
				'customize-code-page'    => array(
					'id'    => 'customize-code-page',
					'title' => \esc_html__( 'Customize 2FA code page', 'wp-2fa' ),
				),
			);

			$is_enterprise = Licensing_Factory::has_active_valid_license() && ( Licensing_Factory::provider_call( 'is_plan_or_trial__premium_only', 'business', true ) || Licensing_Factory::provider_call( 'is_plan_or_trial__premium_only', 'ent', true ) || Licensing_Factory::provider_call( 'is_plan_or_trial__premium_only', 'enterprise', true ) );
			if ( ! $is_enterprise ) {
				// Non-enterprise users can access only the code page customization tab.
				unset( $settings_array['customize-setup-wizard'], $settings_array['customize-user-prompts'], $settings_array['customize-user-profile'] );
			}

			return $settings_array;
		}

		/**
		 * Render the White labeling settings page.
		 *
		 * @since 4.0.0
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

			$tabs = self::collect_tabs();

			?>
			<div class="wp-2fa-settings-wrapper wp-2fa-settings-new">
				<div class="page-header">
					<!-- <h2><?php \esc_html_e( 'White labeling', 'wp-2fa' ); ?></h2> -->
					<div class="page-header-actions">
						<button type="button" class="button button-primary js-save-settings-new"><?php \esc_html_e( 'Save settings', 'wp-2fa' ); ?></button>
					</div>
				</div>

			<div class="main-settings-new">
				<div class="wrap main-left">
					<?php
					// Landing / overview template.
					?>
					<div id="wp-2fa-options-tab-white-labeling" class="tabs-wrap">
						<?php
						include_once \WP_2FA_PATH . '/includes/classes/Admin/Settings/templates/white-labeling.php';
						?>
					</div>

					<?php
					// Detail templates — each is shown when the user clicks
					// a list item on the landing page.
					foreach ( $tabs as $tab => $settings ) {
						?>
						<div id="wp-2fa-options-tab-<?php echo \esc_attr( $tab ); ?>" class="tabs-wrap">
							<?php
							include \WP_2FA_PATH . '/includes/classes/Admin/Settings/templates/' . $tab . '.php';
							?>
						</div>
						<?php
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
	}
}
