<?php
/**
 * New first-time setup wizard – static class.
 *
 * Renders a three-screen wizard (Welcome → Steps → Finish) that reuses the
 * Settings_Builder components and CSS variables from the new settings UI.
 * Step content is rendered by First_Time_Wizard_Steps_New. Data is submitted
 * via AJAX so the page never reloads.
 *
 * @package    wp2fa
 * @subpackage settings-pages
 * @since 4.0.0
 */

namespace WP2FA\Admin\SettingsPages;

use WP2FA\WP2FA;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Settings_Page;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Helpers\MLS_Cross_Sell;
use WP2FA\Admin\Migrations\Wordfence_Login_Security;
use WP2FA\Admin\Migrations\Wordfence_Migration_Page;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Views\First_Time_Wizard_Steps_New;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;
use WP2FA\Methods\TOTP;
use WP2FA\Methods\Email;
use WP2FA\Methods\Backup_Codes;

if ( ! class_exists( '\WP2FA\Admin\SettingsPages\Setup_Wizard_New' ) ) {

	/**
	 * New first-time wizard rendered as a standalone page.
	 *
	 * @since 4.0.0
	 */
	class Setup_Wizard_New {

		/**
		 * AJAX action name.
		 *
		 * @since 4.0.0
		 */
		private const AJAX_ACTION = 'wp2fa_wizard_save';

		/**
		 * Nonce action string.
		 *
		 * @since 4.0.0
		 */
		private const NONCE_ACTION = 'wp2fa_wizard_new_nonce';

		/**
		 * Whether this class should handle the wizard request.
		 *
		 * @return bool
		 *
		 * @since 4.0.0
		 */
		public static function should_render(): bool {
			return Settings_Page::is_new_interface_enabled();
		}

		/**
		 * Render the full wizard page (called from Setup_Wizard::setup_page).
		 *
		 * Outputs a complete HTML document and calls exit().
		 *
		 * @since 4.0.0
		 */
		public static function render() {
			self::render_page();
			exit();
		}

		/**
		 * Register the AJAX save handler. Called once from WP2FA::init().
		 *
		 * @since 4.0.0
		 */
		public static function init() {
			\add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_save' ) );
		}

		/**
		 * Output the full HTML page with three screens.
		 *
		 * @since 4.0.0
		 */
		private static function render_page() {
			// Register dialog assets (normally done on admin_enqueue_scripts,
			// but the wizard renders and exits during admin_init).
			\WP2FA\Core\register_dialog_assets();

			// Enqueue assets.
			\wp_enqueue_style(
				'wp_2fa_settings_new_css',
				WP_2FA_URL . 'includes/assets/css/settings.css',
				array(),
				WP_2FA_VERSION
			);

			\wp_enqueue_style(
				'wp_2fa_wizard_new_css',
				WP_2FA_URL . 'includes/assets/css/wizard-new.css',
				array( 'wp_2fa_settings_new_css' ),
				WP_2FA_VERSION
			);

			\wp_enqueue_script( 'wp2fa-dialog' );
			\wp_enqueue_style( 'wp2fa-dialog' );

			\wp_enqueue_script(
				'wp_2fa_wizard_new_js',
				WP_2FA_URL . 'includes/assets/js/wizard-new.js',
				array( 'wp2fa-dialog' ),
				WP_2FA_VERSION,
				true
			);

			\wp_enqueue_script(
				'wp_2fa_settings_new',
				WP_2FA_URL . 'includes/assets/js/settings-design-logic.js',
				array(),
				WP_2FA_VERSION,
				true
			);

			// "Exclude yourself?" prompt when zero-setup email is toggled on.
			\wp_enqueue_script(
				'wp_2fa_zero_email_exclude_self',
				WP_2FA_URL . 'extensions/0-setup-email/js/zero-email-exclude-self.js',
				array( 'wp_2fa_wizard_new_js' ),
				WP_2FA_VERSION,
				true
			);

			$user = \wp_get_current_user();
			if ( $user && $user->exists() ) {
				\wp_localize_script(
					'wp_2fa_zero_email_exclude_self',
					'wp2faZeroEmailExcludeSelf',
					array(
						'currentUserLogin' => \esc_js( $user->user_login ),
						'modalTitle'       => \esc_html__( 'Exclude yourself?', 'wp-2fa' ),
						'modalBody'        => \esc_html__( 'You are enabling Zero-setup email 2FA. This will enforce 2FA for all users, including your own account, replacing any current 2FA method enabled. That means you will be asked for a one-time code sent by email the next time you log in.', 'wp-2fa' )
							. '<br><br>'
							. \esc_html__( "If you don't want this applied to your account, you can exclude yourself from the 2FA policies before continuing.", 'wp-2fa' ),
						'excludeBtnText'   => \esc_html__( 'Exclude myself from 2FA policies', 'wp-2fa' ),
						'continueBtnText'  => \esc_html__( 'Continue anyway', 'wp-2fa' ),
						'noteText'         => \esc_html__( "Note: After closing this prompt, don't forget to save your settings for the changes to take effect.", 'wp-2fa' ),
					)
				);
			}

			\wp_localize_script(
				'wp_2fa_wizard_new_js',
				'wp2faWizardNew',
				array(
					'ajaxUrl'             => \admin_url( 'admin-ajax.php' ),
					'nonce'               => \wp_create_nonce( self::NONCE_ACTION ),
					'action'              => self::AJAX_ACTION,
					'searchAction'        => 'wp2fa_search_policy_items',
					'searchNonce'         => \wp_create_nonce( 'wp2fa_save_policies_new_nonce' ),
					'savingText'          => \esc_html__( 'Saving…', 'wp-2fa' ),
					'errorText'           => \esc_html__( 'An error occurred. Please try again.', 'wp-2fa' ),
					'methodsRequiredText' => \esc_html__( 'Please select at least one 2FA method', 'wp-2fa' ),
					'configure2fa'        => \esc_url( Settings::get_setup_page_link() ),
					'settingsUrl'         => \esc_url( Settings::get_settings_page_link() ),
					'skipConfirmMessage'  => \__( 'If you cancel this wizard, the default plugin settings will be applied. You can always configure the plugin settings and two-factor authentication policies at a later stage from the <b>WP 2FA</b> entry in your WordPress dashboard menu.', 'wp-2fa' ),
					'skipConfirmOk'       => \esc_html__( 'OK, close the wizard', 'wp-2fa' ),
					'skipConfirmCancel'   => \esc_html__( 'Continue with the wizard', 'wp-2fa' ),
					// The companion-plugin install, fired in the background from the last slide.
					'mlsAction'           => MLS_Cross_Sell::AJAX_ACTION,
					'mlsNonce'            => \wp_create_nonce( MLS_Cross_Sell::NONCE_ACTION ),
				)
			);

			$skip_url = \esc_url( Settings::get_settings_page_link() );
			$logo_url = \esc_url( WP_2FA_URL . 'dist/images/wizard-logo.png' );

			$is_excluded = User_Helper::is_excluded( User_Helper::get_user_object()->ID );
			?>
<!DOCTYPE html>
<html <?php \language_attributes(); ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php \esc_html_e( 'WP 2FA — Setup Wizard', 'wp-2fa' ); ?></title>
			<?php
			\wp_enqueue_style( 'common' );
			\wp_enqueue_style( 'forms' );
			\wp_enqueue_style( 'buttons' );
			\remove_action( 'admin_print_styles', 'print_emoji_styles' );
			\do_action( 'admin_print_styles' );
			\wp_print_scripts( 'wp2fa-dialog' );
			\wp_print_scripts( 'wp_2fa_settings_new' );
			\wp_print_scripts( 'wp_2fa_wizard_new_js' );
			\wp_print_scripts( 'wp_2fa_zero_email_exclude_self' );
			?>
</head>
<body class="wp2fa-wizard-new wp-core-ui">

	<!-- ============ Screen 1: Welcome ============ -->
	<div id="wp2fa-wizard-welcome" class="wp2fa-wizard-screen wp2fa-wizard-screen--active">
		<div class="wp2fa-wizard-welcome-card">
			<div class="wp2fa-wizard-logo">
				<img src="<?php echo $logo_url; // phpcs:ignore ?>" alt="WP 2FA">
				<span class="wp2fa-wizard-logo-text"><?php \esc_html_e( 'WP 2FA', 'wp-2fa' ); ?></span>
			</div> 
			<h2><?php \esc_html_e( 'Let\'s get you started', 'wp-2fa' ); ?></h2>
			<p><?php \esc_html_e( 'Thank you for installing WP 2FA. This quick setup wizard will guide you through configuring the plugin and setting up two-factor authentication (2FA) for your user account and the users on this website.', 'wp-2fa' ); ?></p>
			<div class="wp2fa-wizard-welcome-actions">
				<button type="button" class="button button-primary button-hero js-wizard-start"><?php \esc_html_e( "Let's get started!", 'wp-2fa' ); ?></button>
				<a href="<?php echo $skip_url; // phpcs:ignore ?>" class="wp2fa-wizard-skip-link"><?php \esc_html_e( 'Skip Wizard', 'wp-2fa' ); ?></a>
			</div>
		</div>
	</div>

	<!-- ============ Screen 2: Steps ============ -->
	<div id="wp2fa-wizard-steps-screen" class="wp2fa-wizard-screen">
		<main class="wp2fa-wizard-main">
			<header class="wp2fa-wizard-header">
				<div class="wp2fa-wizard-header-logo">
					<img src="<?php echo $logo_url; // phpcs:ignore ?>" alt="WP 2FA">
					<span><?php \esc_html_e( 'WP 2FA', 'wp-2fa' ); ?></span>
				</div>
				<nav class="wp2fa-wizard-nav">
					<ol>
						<li data-step="methods" class="is-active">
							<span class="step-indicator"></span>
							<span class="step-label"><?php \esc_html_e( '2FA METHODS', 'wp-2fa' ); ?></span>
						</li>
						<li data-step="alternative">
							<span class="step-indicator"></span>
							<span class="step-label"><?php \esc_html_e( 'ALTERNATIVE METHODS', 'wp-2fa' ); ?></span>
						</li>
						<li data-step="enforcement">
							<span class="step-indicator"></span>
							<span class="step-label"><?php \esc_html_e( '2FA POLICY', 'wp-2fa' ); ?></span>
						</li>
						<li data-step="exclusions">
							<span class="step-indicator"></span>
							<span class="step-label"><?php \esc_html_e( 'EXCLUDE USERS', 'wp-2fa' ); ?></span>
						</li>
						<?php if ( WP_Helper::is_multisite() ) : ?>
						<li data-step="exclude-sites">
							<span class="step-indicator"></span>
							<span class="step-label"><?php \esc_html_e( 'EXCLUDE SITES', 'wp-2fa' ); ?></span>
						</li>
						<?php endif; ?>
						<li data-step="grace">
							<span class="step-indicator"></span>
							<span class="step-label"><?php \esc_html_e( 'SET GRACE PERIOD', 'wp-2fa' ); ?></span>
						</li>
						<?php if ( Wordfence_Login_Security::is_available() ) : ?>
						<li data-step="wordfence">
							<span class="step-indicator"></span>
							<span class="step-label"><?php \esc_html_e( 'WORDFENCE MIGRATION', 'wp-2fa' ); ?></span>
						</li>
						<?php endif; ?>
						<?php if ( MLS_Cross_Sell::should_offer() ) : ?>
						<li data-step="next-steps">
							<span class="step-indicator"></span>
							<span class="step-label"><?php \esc_html_e( 'NEXT STEPS', 'wp-2fa' ); ?></span>
						</li>
						<?php endif; ?>
					</ol>
				</nav>
			</header>

			<form id="wp2fa-wizard-form" class="wp2fa-wizard-form" autocomplete="off">

				<div class="wp2fa-wizard-panel" data-panel="methods">
					<?php First_Time_Wizard_Steps_New::step_methods(); ?>
				</div>

				<div class="wp2fa-wizard-panel" data-panel="alternative" style="display:none;">
					<?php First_Time_Wizard_Steps_New::step_alternative_methods(); ?>
				</div>

				<div class="wp2fa-wizard-panel" data-panel="enforcement" style="display:none;">
					<?php First_Time_Wizard_Steps_New::step_enforcement_policy(); ?>
				</div>

				<div class="wp2fa-wizard-panel" data-panel="exclusions" style="display:none;">
					<?php First_Time_Wizard_Steps_New::step_exclude_users(); ?>
				</div>

				<?php if ( WP_Helper::is_multisite() ) : ?>
				<div class="wp2fa-wizard-panel" data-panel="exclude-sites" style="display:none;">
					<?php First_Time_Wizard_Steps_New::step_exclude_sites(); ?>
				</div>
				<?php endif; ?>

				<div class="wp2fa-wizard-panel" data-panel="grace" style="display:none;">
					<?php First_Time_Wizard_Steps_New::step_grace_period(); ?>
				</div>

				<?php if ( Wordfence_Login_Security::is_available() ) : ?>
				<div class="wp2fa-wizard-panel" data-panel="wordfence" style="display:none;">
					<?php Wordfence_Migration_Page::render_wizard_step(); ?>
				</div>
				<?php endif; ?>

				<?php if ( MLS_Cross_Sell::should_offer() ) : ?>
				<div class="wp2fa-wizard-panel" data-panel="next-steps" style="display:none;">
					<?php self::render_cross_sell_step(); ?>
				</div>
				<?php endif; ?>

				<footer class="wp2fa-wizard-footer">
					<div class="wp2fa-wizard-footer-inner">
						<button type="button" class="button button-primary js-wizard-continue"><?php \esc_html_e( 'Continue', 'wp-2fa' ); ?></button>
						<button type="button" class="button button-primary js-wizard-finish" style="display:none;"><?php \esc_html_e( 'Finish Setup', 'wp-2fa' ); ?></button>
						<a href="<?php echo $skip_url; // phpcs:ignore ?>" class="wp2fa-wizard-skip-link"><?php \esc_html_e( 'Skip wizard', 'wp-2fa' ); ?></a>
					</div>
				</footer>
			</form>
		</main>
	</div>

	<!-- ============ Screen 3: Finish ============ -->
	<div id="wp2fa-wizard-finish" class="wp2fa-wizard-screen">
		<div class="wp2fa-wizard-welcome-card">
			<div class="wp2fa-wizard-logo">
				<img src="<?php echo $logo_url; // phpcs:ignore ?>" alt="WP 2FA">
				<span class="wp2fa-wizard-logo-text"><?php \esc_html_e( 'WP 2FA', 'wp-2fa' ); ?></span>
			</div>
			<div data-finish-excluded <?php echo ( $is_excluded ) ? '' : 'style="display:none;"'; // phpcs:ignore ?> >
				<h2><?php \esc_html_e( 'Congratulations.', 'wp-2fa' ); ?></h2>
				<p><?php \esc_html_e( 'Great job, the plugin and 2FA policies are now configured. You can always change the plugin settings and 2FA policies at a later stage from the WP 2FA entry in the WordPress menu.', 'wp-2fa' ); ?></p>
				<div class="wp2fa-wizard-welcome-actions">
					<a href="<?php echo $skip_url; // phpcs:ignore ?>" class="wp2fa-wizard-skip-link"><?php \esc_html_e( 'Close wizard', 'wp-2fa' ); ?></a>
				</div>
			</div>

			<div data-finish-normal <?php echo ( $is_excluded ) ? 'style="display:none;"' : ''; // phpcs:ignore ?> >
				<h2><?php \esc_html_e( "Congratulations, you're almost there…", 'wp-2fa' ); ?></h2>
				<p><?php \esc_html_e( 'Great job, the plugin and 2FA policies are now configured. You can always change the plugin settings and 2FA policies at a later stage from the WP 2FA entry in the WordPress menu.', 'wp-2fa' ); ?></p>
				<p><?php \esc_html_e( 'Now you need to configure 2FA for your own user account. You can do this now (recommended) or later.', 'wp-2fa' ); ?></p>
				<div class="wp2fa-wizard-welcome-actions">
					<a href="<?php echo \esc_url( Settings::get_setup_page_link() ); ?>" class="button button-primary button-hero"><?php \esc_html_e( 'Configure 2FA Now', 'wp-2fa' ); ?></a>
					<a href="<?php echo $skip_url; // phpcs:ignore ?>" class="wp2fa-wizard-skip-link"><?php \esc_html_e( 'Close wizard and configure 2FA Later', 'wp-2fa' ); ?></a>
				</div>
			</div>
		</div>
	</div>

</body>
</html>
			<?php
		}

		/**
		 * Handle the wizard save via AJAX.
		 *
		 * Collects the policy fields from the form, validates via the existing
		 * policies validator, merges with existing settings, and marks the
		 * wizard as finished.
		 *
		 * @since 4.0.0
		 */
		public static function ajax_save() {
			// 1. Nonce.
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! \wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'Security check failed. Please refresh and try again.', 'wp-2fa' ) ),
					403
				);
			}

			// 2. Capability.
			// Settings are stored network-wide on a multisite install, so a site
			// administrator's manage_options is not enough to change them.
			if ( ! \WP2FA\Admin\Settings_Page::can_manage_settings() ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'Permission denied.', 'wp-2fa' ) ),
					403
				);
			}

			// 3. Collect fields.
			if ( ! isset( $_POST[ WP_2FA_POLICY_SETTINGS_NAME ] ) || ! \is_array( $_POST[ WP_2FA_POLICY_SETTINGS_NAME ] ) ) {
				\wp_send_json_error(
					array( 'message' => \esc_html__( 'No data received.', 'wp-2fa' ) ),
					400
				);
			}

			$raw = \wp_unslash( $_POST[ WP_2FA_POLICY_SETTINGS_NAME ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

			// Ensure method toggles are always present so unchecked boxes can disable methods.
			$method_toggle_keys = array(
				TOTP::POLICY_SETTINGS_NAME,
				Email::POLICY_SETTINGS_NAME,
				Backup_Codes::get_settings_name(),
				'enable-email-backup',
				'specify-backup-email',
			);

			$all_methods = \apply_filters( WP_2FA_PREFIX . 'methods_policy_settings_new', array(), null );
			if ( \is_array( $all_methods ) ) {
				foreach ( $all_methods as $method ) {
					if ( isset( $method['setting'] ) && \is_string( $method['setting'] ) && '' !== $method['setting'] ) {
						$method_toggle_keys[] = $method['setting'];
					}
				}
			}

			$method_toggle_keys = \array_values( \array_unique( $method_toggle_keys ) );
			foreach ( $method_toggle_keys as $method_key ) {
				if ( ! \array_key_exists( $method_key, $raw ) ) {
					$raw[ $method_key ] = '';
				}
			}

			// Backup email sub-option must be disabled if the parent method is disabled.
			if ( empty( $raw['enable-email-backup'] ) ) {
				$raw['specify-backup-email'] = '';
			}

			// Sanitize array fields (multi-select-ajax sends comma-separated strings).
			$array_fields = array( 'excluded_users', 'excluded_roles', 'enforced_users', 'enforced_roles', 'included_sites', 'excluded_sites' );
			foreach ( $array_fields as $field ) {
				if ( isset( $raw[ $field ] ) ) {
					$values = \is_array( $raw[ $field ] )
						? \array_map( 'sanitize_text_field', \array_map( 'trim', $raw[ $field ] ) )
						: \array_map( 'sanitize_text_field', \array_map( 'trim', explode( ',', $raw[ $field ] ) ) );

					$raw[ $field ] = \array_values( \array_filter( $values ) );
				}
			}

			// Prevent Role_Settings_Controller from wiping role-specific data
			// during a global-scope wizard save.
			if ( \class_exists( Role_Settings_Controller::class ) ) {
				\remove_filter( WP_2FA_PREFIX . 'filter_output_content', array( Role_Settings_Controller::class, 'validate_and_sanitize' ), 10 );
			}

			// 4. Validate via the existing policies validator.
			$validated = Settings_Page_Policies::validate_and_sanitize( $raw, 'setup_wizard' );

			// 5. Merge with existing settings so the wizard doesn't wipe
			// values from fields it doesn't display.
			$existing = Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() );

			if ( \is_array( $validated ) && \is_array( $existing ) ) {
				$submitted_keys = \array_keys( $raw );

				// Some keys are derived/computed from other submitted keys.
				$derived_from = array(
					'grace-period-expiry-time' => array( 'grace-period', 'grace-period-denominator' ),
					'custom-user-page-id'      => array( 'create-custom-user-page', 'custom-user-page-url' ),
				);

				$merged = $existing;
				foreach ( $validated as $key => $value ) {
					if ( \in_array( $key, $submitted_keys, true ) ) {
						$merged[ $key ] = $value;
					} elseif ( isset( $derived_from[ $key ] ) && \array_intersect( $derived_from[ $key ], $submitted_keys ) ) {
						$merged[ $key ] = $value;
					} elseif ( ! \array_key_exists( $key, $existing ) ) {
						$merged[ $key ] = $value;
					}
				}

				// Guarantee method toggles reflect submitted wizard state even if not present in validator output.
				foreach ( $method_toggle_keys as $method_key ) {
					if ( \array_key_exists( $method_key, $validated ) ) {
						$merged[ $method_key ] = $validated[ $method_key ];
					} else {
						$merged[ $method_key ] = ! empty( $raw[ $method_key ] ) ? \sanitize_text_field( (string) $raw[ $method_key ] ) : false;
					}
				}
			} else {
				$merged = \is_array( $validated ) ? $validated : $existing;
			}

			WP2FA::update_plugin_settings( $merged, false, WP_2FA_POLICY_SETTINGS_NAME );

			// 6. Mark wizard as finished.
			Settings_Utils::delete_option( WP_2FA_PREFIX . 'default_settings_applied' );
			Settings_Utils::delete_option( 'wizard_not_finished' );

			// 6b. Wordfence migration, offered on the last slide when that plugin is present.
			Wordfence_Migration_Page::save_wizard_step( \wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput

			// 7. Fire extension hook.
			\do_action( WP_2FA_PREFIX . 'policies_new_ajax_save', \wp_unslash( $_POST ) ); // phpcs:ignore

			$current_user_id = User_Helper::get_user_object()->ID;
			User_Helper::update_user_state( $current_user_id );
			$is_excluded = User_Helper::is_excluded( $current_user_id );

			\wp_send_json_success(
				array(
					'message'               => \esc_html__( 'Setup complete.', 'wp-2fa' ),
					'isCurrentUserExcluded' => (bool) $is_excluded,
				)
			);
		}

		/**
		 * The last wizard slide: an offer to add the free companion plugin.
		 *
		 * The toggle is off to begin with, and nothing happens on this slide unless it is
		 * switched on. Installing is left to the wizard script so the install runs after the
		 * user has already moved on, rather than holding up the step they pressed.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function render_cross_sell_step(): void {
			$logo = WP_2FA_URL . 'dist/images/login-security.jpeg';
			?>
			<div class="wp2fa-wizard-crosssell" data-mls-step>
				<h2><?php \esc_html_e( 'Take your WordPress login security one step further', 'wp-2fa' ); ?></h2>

				<p class="wp2fa-wizard-crosssell-intro">
					<?php
					printf(
						/* translators: %s: Melapress Login Security (free), in bold. */
						\esc_html__( 'WP 2FA protects your user accounts with two-factor authentication. For even stronger login security, you can also install %s, a separate plugin from the same team.', 'wp-2fa' ),
						'<strong>' . \esc_html__( 'Melapress Login Security (free)', 'wp-2fa' ) . '</strong>'
					);
					?>
				</p>

				<div class="wp2fa-wizard-crosssell-card">
					<img class="wp2fa-wizard-crosssell-logo" src="<?php echo \esc_url( $logo ); ?>" alt="" aria-hidden="true">

					<div class="wp2fa-wizard-crosssell-body">
						<span class="wp2fa-wizard-crosssell-badge"><?php \esc_html_e( 'Recommended', 'wp-2fa' ); ?></span>
						<h3><?php \esc_html_e( 'Melapress Login Security (free)', 'wp-2fa' ); ?></h3>
						<p><?php \esc_html_e( 'Add strong password policies, limit login attempts and restrict access by IP address.', 'wp-2fa' ); ?></p>
						<a href="https://melapress.com/wordpress-login-security/?utm_source=plugin&amp;utm_medium=wp2fa&amp;utm_campaign=wizard_cross_sell" target="_blank" rel="noopener noreferrer">
							<?php \esc_html_e( 'Learn more', 'wp-2fa' ); ?>
						</a>
					</div>

					<div class="wp2fa-wizard-crosssell-choice">
						<label class="wp2fa-wizard-toggle">
							<input type="checkbox" id="wp2fa-install-mls" data-mls-toggle value="1">
							<span class="wp2fa-wizard-toggle-track" aria-hidden="true"></span>
						</label>
						<span class="wp2fa-wizard-crosssell-choice-label"><?php \esc_html_e( 'Install this plugin', 'wp-2fa' ); ?></span>
					</div>
				</div>
			</div>
			<?php
		}
	}
}
