<?php
/**
 * Core plugin functionality.
 *
 * @package WP2FA
 */

namespace WP2FA\Core;

use WP2FA\WP2FA;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Views\Re_Login_2FA;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Licensing\Licensing_Factory;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	$n = function ( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'init', $n( 'i18n' ) );
	add_action( 'init', $n( 'init' ) );
	add_action( 'admin_enqueue_scripts', $n( 'register_dialog_assets' ), 5 );
	add_action( 'wp_enqueue_scripts', $n( 'register_dialog_assets' ), 5 );
	add_action( 'login_enqueue_scripts', $n( 'register_dialog_assets' ), 5 );
	add_action( 'admin_enqueue_scripts', $n( 'admin_scripts' ) );
	add_action( 'admin_enqueue_scripts', $n( 'admin_styles' ) );
	add_action( 'admin_head', $n( 'admin_menu_icon_css' ) );

	// Hook to allow async or defer on asset loading.
	add_filter( 'script_loader_tag', $n( 'script_loader_tag' ), 10, 2 );

	/**
	 * Fires after the plugin is loaded.
	 *
	 * @since 2.0.0
	 */
	do_action( WP_2FA_PREFIX . 'loaded' );
}

/**
 * Register the unified dialog JS and CSS so extensions can depend on them.
 *
 * @return void
 *
 * @since 4.0.0
 */
function register_dialog_assets() {
	\wp_register_script(
		'wp2fa-dialog',
		WP_2FA_URL . 'js/wp2fa-dialog.js',
		array(),
		WP_2FA_VERSION,
		true
	);

	\wp_register_style(
		'wp2fa-dialog',
		WP_2FA_URL . 'css/wp2fa-dialog.css',
		array(),
		WP_2FA_VERSION
	);
}

/**
 * Registers the default textdomain.
 *
 * @return void
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', determine_locale(), 'wp-2fa' );
	load_textdomain( 'wp-2fa', WP_LANG_DIR . '/wp-2fa/wp-2fa-' . $locale . '.mo' );
	load_plugin_textdomain( 'wp-2fa', false, plugin_basename( WP_2FA_PATH ) . '/languages/' );
}

/**
 * Initializes the plugin and fires an action other plugins can hook into.
 *
 * @return void
 */
function init() {

	/**
	 * Fires when plugin is initiated.
	 *
	 * @since 2.0.0
	 */
	do_action( WP_2FA_PREFIX . 'init' );
}

/**
 * Activate the plugin
 *
 * @return void
 */
function activate() {
	// First load the init scripts in case any rewrite functionality is being loaded.
	init();
	flush_rewrite_rules();

	// Check if the user is allowed to manage options for the site.
	if ( current_user_can( 'manage_options' ) ) {
		// Add an option to let our plugin know this user has not been through the setup wizard.
		Settings_Utils::update_option( 'redirect_on_activate', true );
	}

	// Add plugin version to wp_options.
	Settings_Utils::update_option( 'plugin_version', WP_2FA_VERSION );
}

/**
 * Deactivate the plugin
 *
 * Uninstall routines should be in uninstall.php
 *
 * @return void
 */
function deactivate() {
}

/**
 * Uninstall the plugin
 *
 * @return void
 */
function uninstall() {
	WP2FA::init();
	if ( ! empty( WP2FA::get_wp2fa_general_setting( 'delete_data_upon_uninstall' ) ) ) {
		// Delete settings from wp_options.
		global $wpdb;
		if ( WP_Helper::is_multisite() ) {
			$network_id = get_current_network_id();
			$wpdb->query(
				$wpdb->prepare(
					"
					DELETE FROM $wpdb->sitemeta
					WHERE meta_key LIKE %s
					AND site_id = %d
					",
					'%wp_2fa_%',
					$network_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"
					DELETE FROM $wpdb->options
					WHERE option_name LIKE %s
					",
					'%wp_2fa_%'
				)
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"
				DELETE FROM $wpdb->usermeta
				WHERE meta_key LIKE %s
				",
				WP_2FA_PREFIX . 'wp_2fa_%'
			)
		);
	}
}

/**
 * The list of known contexts for enqueuing scripts/styles.
 *
 * @return array
 */
function get_enqueue_contexts() {
	return array( 'admin', 'frontend', 'shared' );
}

/**
 * Generate an URL to a script, taking into account whether SCRIPT_DEBUG is enabled.
 *
 * @param string $script Script file name (no .js extension).
 * @param string $context Context for the script ('admin', 'frontend', or 'shared').
 *
 * @return string|\WP_Error URL
 */
function script_url( $script, $context ) {

	if ( ! in_array( $context, get_enqueue_contexts(), true ) ) {
		return new \WP_Error( 'invalid_enqueue_context', 'Invalid $context specified in WP2FA script loader.' );
	}

	return WP_2FA_URL . 'dist/js/' . sanitize_file_name( $script ) . '.js';
}

/**
 * Generate an URL to a stylesheet, taking into account whether SCRIPT_DEBUG is enabled.
 *
 * @param string $stylesheet Stylesheet file name (no .css extension).
 * @param string $context Context for the script ('admin', 'frontend', or 'shared').
 *
 * @return string|\WP_Error  URL
 */
function style_url( $stylesheet, $context ) {

	if ( ! in_array( $context, get_enqueue_contexts(), true ) ) {
		return new \WP_Error( 'invalid_enqueue_context', 'Invalid $context specified in WP2FA stylesheet loader.' );
	}

	return WP_2FA_URL . 'dist/css/' . sanitize_file_name( $stylesheet ) . '.css';
}

/**
 * Enqueue scripts for admin.
 *
 * @return void
 */
function admin_scripts() {

	global $pagenow;

	// Get page argument from $_GET array.
	$page = ( isset( $_GET['page'] ) ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( ( empty( $page ) || false === strpos( $page, 'wp-2fa' ) ) && 'profile.php' !== $pagenow ) {
		return;
	}

	\wp_enqueue_script(
		'wp_2fa_admin',
		script_url( 'admin', 'admin' ),
		array( 'jquery-ui-widget', 'jquery-ui-core', 'jquery-ui-autocomplete', 'wp_2fa_micro_modals', 'select2', 'wp-i18n' ),
		WP_2FA_VERSION,
		true
	);

	\wp_enqueue_script(
		'wp_2fa_micro_modals',
		script_url( 'micromodal', 'admin', 'wp-i18n' ),
		array(),
		WP_2FA_VERSION,
		true
	);

	enqueue_select2_scripts();

	// Data array.
	$data_array = array(
		'ajaxURL'                        => \admin_url( 'admin-ajax.php' ),
		'roles'                          => WP_Helper::get_roles_wp(),
		'nonce'                          => wp_create_nonce( 'wp-2fa-settings-nonce' ),
		'dismissNonce'                   => wp_create_nonce( 'wp-2fa-dismiss-nag' ),
		'codeValidatedHeading'           => esc_html__( 'Congratulations', 'wp-2fa' ),
		'codeValidatedText'              => esc_html__( 'Your account just got more secure', 'wp-2fa' ),
		'codeValidatedButton'            => __( 'Close Wizard & Refresh', 'wp-2fa' ),
		'processingText'                 => esc_html__( 'Processing Update', 'wp-2fa' ),
		'email_sent_success'             => esc_html__( 'Email successfully sent', 'wp-2fa' ),
		'email_sent_failure'             => esc_html__( 'Email delivery failed', 'wp-2fa' ),
		'invalidEmail'                   => esc_html__( 'Please use a valid email address', 'wp-2fa' ),
		'license_validation_in_progress' => esc_html__( 'Validating your license, please wait...', 'wp-2fa' ),
	);
	wp_localize_script( 'wp_2fa_admin', 'wp2faData', $data_array );

	$re_login = Settings_Utils::get_setting_role( User_Helper::get_user_role(), Re_Login_2FA::RE_LOGIN_SETTINGS_NAME );

	$role = User_Helper::get_user_role();

	$redirect_page = \sanitize_text_field( Settings_Utils::get_setting_role( $role, 'redirect-user-custom-page' ) );
	$redirect_page_global = \sanitize_text_field( Settings_Utils::get_setting_role( null, 'redirect-user-custom-page' ) );
	$redirect_page_global_setting = \sanitize_text_field( Settings_Utils::get_setting_role( $role, 'redirect-user-custom-page-global' ) );

	// Priority: role-specific redirect-user-custom-page > global redirect-user-custom-page > redirect-user-custom-page-global > empty.
	if ( '' !== trim( (string) $redirect_page ) ) {
		$redirect_to_url = \trailingslashit( get_site_url() ) . $redirect_page;
	} elseif ( '' !== trim( (string) $redirect_page_global ) ) {
		$redirect_to_url = \trailingslashit( get_site_url() ) . $redirect_page_global;
	} elseif ( '' !== trim( (string) $redirect_page_global_setting ) ) {
		$redirect_to_url = \trailingslashit( get_site_url() ) . $redirect_page_global_setting;
	} else {
		$redirect_to_url = '';
	}

	$data_array = array(
		'ajaxURL'         => \admin_url( 'admin-ajax.php' ),
		'nonce'           => wp_create_nonce( 'wp2fa-verify-wizard-page' ),
		'codesPreamble'   => esc_html__( 'These are the 2FA backup codes for the user', 'wp-2fa' ),
		'readyText'       => esc_html__( 'I\'m ready', 'wp-2fa' ),
		'codeReSentText'  => esc_html__( 'New code sent', 'wp-2fa' ),
		'backupCodesSent' => esc_html__( 'Backup codes sent', 'wp-2fa' ),
		'reLoginEnabled'  => Re_Login_2FA::ENABLED_SETTING_VALUE,
		'reLogin'         => $re_login,
		'loginUrl'        => \wp_login_url(),
		'redirectToUrl'   => $redirect_to_url,
	);
	wp_localize_script( 'wp_2fa_admin', 'wp2faWizardData', $data_array );

	// Secret field visibility toggle (eye icon).
	\wp_add_inline_script(
		'wp_2fa_admin',
		'(function(){document.addEventListener("click",function(e){var btn=e.target.closest(".wp2fa-secret-toggle");if(!btn)return;e.preventDefault();var wrap=btn.closest(".wp2fa-secret-field-wrap");if(!wrap)return;var input=wrap.querySelector(".wp2fa-secret-field");if(!input)return;var isVisible=input.classList.toggle("wp2fa-secret-visible");btn.setAttribute("aria-label",isVisible?"Hide":"Show");var use=btn.querySelector("use");if(use){use.setAttribute("href",isVisible?"#wp2fa-icon-eye-off":"#wp2fa-icon-eye");}});})();'
	);

	\wp_enqueue_script( 'wp2fa-dialog' );
	\wp_enqueue_style( 'wp2fa-dialog' );

	\wp_enqueue_script(
		'wp2fa-premium-badge-dialog',
		WP_2FA_URL . 'js/wp2fa-premium-badge-dialog.js',
		array( 'wp2fa-dialog' ),
		file_exists( WP_2FA_PATH . 'js/wp2fa-premium-badge-dialog.js' ) ? (string) filemtime( WP_2FA_PATH . 'js/wp2fa-premium-badge-dialog.js' ) : WP_2FA_VERSION,
		true
	);

	$tab = ( isset( $_GET['tab'] ) ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	wp_localize_script(
		'wp2fa-premium-badge-dialog',
		'wp2faPremiumBadgeDialog',
		array(
			'enabled'       => ! Licensing_Factory::has_active_valid_license(),
			'currentPage'   => $page,
			'currentTab'    => $tab,
			'currentScreen' => $screen ? $screen->id : '',
			'dismissLabel'  => esc_html__( 'Close', 'wp-2fa' ),
			'pages'         => get_premium_badge_dialog_pages(),
		)
	);
}

/**
 * Returns the content map for premium badge dialogs.
 *
 * Keys should match admin page slugs (e.g. wp-2fa-policies) or use:
 * - "slug:tab" for tab-specific content (e.g. wp-2fa-policies:passkeys)
 * - "tab:<tab>" for any page tab match
 * - "screen:<screen-id>" for WP screen id matches
 * - "default" as fallback
 *
 * @return array
 */
function get_premium_badge_dialog_pages() {
	$hl = '<span class="wp2fa-dialog-title-highlight">';

	$default_page = array(
		'title'         => esc_html__( 'Unlock', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium Features', 'wp-2fa' ) . '</span>',
		'intro'         => esc_html__( 'Upgrade to WP 2FA Premium to unlock advanced authentication options and stronger security controls.', 'wp-2fa' ),
		'description'   => esc_html__( 'Use this dialog content map to define page-specific upsell messaging.', 'wp-2fa' ),
		'screenshotUrl' => esc_url_raw( WP_2FA_URL . 'includes/assets/images/reports-teaser-preview.png' ),
		'bullets'       => array(
			array(
				'title'       => esc_html__( 'Upsell point', 'wp-2fa' ),
				'description' => esc_html__( 'Optional description', 'wp-2fa' ),
			),
			array(
				'title'       => esc_html__( 'Upsell point', 'wp-2fa' ),
				'description' => esc_html__( 'Optional description', 'wp-2fa' ),
			),
		),
		'cta'           => array(
			'text' => esc_html__( 'View Premium Plans', 'wp-2fa' ),
			'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-badge-dialog' ),
		),
	);

	$pages = array(
		'default'              => $default_page,
		'wp-2fa-policies'      => array(
			'title'         => esc_html__( 'Unlock 2FA policies per user role with the', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium edition', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Apply different 2FA requirements to administrators, editors, customers, members, and other user roles to balance security and user experience.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(),
			'cta'           => array(
				'text' => esc_html__( 'upgrade now', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-custom-policies-per-role' ),
			),
		),
		'policies-banner'      => array(
			'title'         => esc_html__( 'Set different 2FA policies for each user role', 'wp-2fa' ),
			'intro'         => esc_html__( 'Apply different two-factor authentication requirements to each user role on your website.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Protect high-risk users with stronger authentication', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Balance security and usability for every team', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Reduce support requests with a smoother rollout', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock role-based policies', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=policies-banner-badge' ),
			),
		),
		'settings-banner'      => array(
			'title'         => esc_html__( 'Unlock advanced settings', 'wp-2fa' ),
			'intro'         => esc_html__( 'Customize authentication, integrate with more WordPress plugins, and manage security settings across multiple websites.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Offer users more ways to authenticate', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Extend 2FA to more login workflows', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Export and import settings between websites', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock advanced settings', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=settings-banner-badge' ),
			),
		),
		'wp-2fa-policies-methods' => array(
			'title'         => esc_html__( 'Unlock more 2FA methods with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Give every user a secure authentication method that works for them, whether they prefer passkeys, SMS, security keys, email verification, or authenticator apps.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Improve user adoption', 'wp-2fa' ),
					'description' => esc_html__( 'Let users choose the authentication method they prefer', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Reduce account lockouts', 'wp-2fa' ),
					'description' => esc_html__( 'Allow users to verify their identity with backup authentication methods', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Lower support overhead', 'wp-2fa' ),
					'description' => esc_html__( 'Help users regain access without administrator intervention', 'wp-2fa' ),
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'upgrade now', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-2fa-method' ),
			),
		),
		'screen:toplevel_page_wp-2fa-policies' => array(
			'title'         => esc_html__( 'Unlock 2FA policies per user role with the', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium edition', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Apply different 2FA requirements to administrators, editors, customers, members, and other user roles to balance security and user experience.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(),
			'cta'           => array(
				'text' => esc_html__( 'upgrade now', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-custom-policies-per-role' ),
			),
		),
		'screen:toplevel_page_wp-2fa-policies-network' => array(
			'title'         => esc_html__( 'Unlock 2FA policies per user role with the', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium edition', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Apply different 2FA requirements to administrators, editors, customers, members, and other user roles to balance security and user experience.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(),
			'cta'           => array(
				'text' => esc_html__( 'upgrade now', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-custom-policies-per-role' ),
			),
		),
		'wp-2fa-passkeys'      => array(
			'title'         => esc_html__( 'Unlock Advanced Passkey Management with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium', 'wp-2fa' ) . '</span>',
			'intro'         => '', //esc_html__( 'Give your users a faster, phishing-resistant way to log in and keep full control over how passkeys are used across your website.', 'wp-2fa' ),
			'description'   => esc_html__( 'Give your users a faster, phishing-resistant way to log in and keep full control over how passkeys are used across your website.', 'wp-2fa' ),
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Allow users to register multiple passkeys per account', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Enable or restrict passkeys for specific user roles', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Require an additional 2FA verification step for sensitive accounts', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Give users a secure, passwordless login experience', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Upgrade to Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-passkeys' ),
			),
		),
		'screen:wp-2fa_page_wp-2fa-passkeys' => array(
			'title'         => esc_html__( 'Unlock Advanced Passkey Management with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium', 'wp-2fa' ) . '</span>',
			'intro'         => '', //esc_html__( 'Give your users a faster, phishing-resistant way to log in and keep full control over how passkeys are used across your website.', 'wp-2fa' ),
			'description'   => esc_html__( 'Give your users a faster, phishing-resistant way to log in and keep full control over how passkeys are used across your website.', 'wp-2fa' ),
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Allow users to register multiple passkeys per account', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Enable or restrict passkeys for specific user roles', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Require an additional 2FA verification step for sensitive accounts', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Give users a secure, passwordless login experience', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Upgrade to Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-passkeys' ),
			),
		),
		'screen:wp-2fa_page_wp-2fa-passkeys-network' => array(
			'title'         => esc_html__( 'Unlock Advanced Passkey Management with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium', 'wp-2fa' ) . '</span>',
			'intro'         => '', //esc_html__( 'Give your users a faster, phishing-resistant way to log in and keep full control over how passkeys are used across your website.', 'wp-2fa' ),
			'description'   => esc_html__( 'Give your users a faster, phishing-resistant way to log in and keep full control over how passkeys are used across your website.', 'wp-2fa' ),
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Allow users to register multiple passkeys per account', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Enable or restrict passkeys for specific user roles', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Require an additional 2FA verification step for sensitive accounts', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Give users a secure, passwordless login experience', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Upgrade to Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-passkeys' ),
			),
		),
		'wp-2fa-policies:method-oob' => array(
			'title'         => esc_html__( 'Authenticate with a one-time email login link', 'wp-2fa' ),
			'intro'         => esc_html__( 'Let users skip the code entirely. Once they enter their username and password, send a secure one-time link straight to their inbox.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Removes the step of typing in a verification code', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Familiar and frictionless, most users already trust email links', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Works on any device with access to their inbox, no extra app required', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Flexible authentication option alongside other methods', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock with Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/#utm_source=plugin&utm_medium=wp2fa&utm_campaign=2fa-premium-method-oob' ),
			),
		),
		'wp-2fa-policies:method-yubikey' => array(
			'title'         => esc_html__( 'Add hardware-backed account protection', 'wp-2fa' ),
			'intro'         => esc_html__( 'Allow users to authenticate using YubiKey security keys for stronger protection against phishing and account compromise.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Hardware-based authentication', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Resistant to phishing attacks', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Trusted by security-conscious organizations', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Ideal for administrators and privileged users', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock with Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/#utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-2fa-method-yubikey' ),
			),
		),
		'wp-2fa-policies:method-0setup' => array(
			'title'         => esc_html__( 'Enable two-factor authentication instantly, with zero setup', 'wp-2fa' ),
			'intro'         => esc_html__( 'Users receive one-time verification codes via email, making it easy to roll out 2FA across your website.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'No apps or hardware required', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Fast and simple user onboarding', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Works with every email-enabled website', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Helps improve 2FA adoption rates', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock with Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa#utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-2fa-method-0setup' ),
			),
		),
		'wp-2fa-policies:method-sms' => array(
			'title'         => esc_html__( 'Authenticate with SMS verification codes', 'wp-2fa' ),
			'intro'         => esc_html__( 'Send one-time authentication codes via SMS to provide users with a familiar login verification method.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Deliver codes directly to users\' phones', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Easy for users to understand and adopt', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Supports a wide range of devices', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Flexible authentication option alongside other methods', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock with Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa#utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-2fa-method-sms' ),
			),
		),
		'wp-2fa-policies:backup-email' => array(
			'title'         => esc_html__( 'Help users recover access safely', 'wp-2fa' ),
			'intro'         => esc_html__( 'Allow users to fall back to email-based verification if they lose access to their primary authentication method.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Reduce account lockouts', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Provide a secure recovery option', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Improve user experience', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Maintain access without compromising security', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock with Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa#utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-2fa-method-backup-email' ),
			),
		),
		'wp-2fa-passkeys:bypass-2fa' => array(
			'title'         => esc_html__( 'Choose whether passkey login requires additional 2FA', 'wp-2fa' ),
			'intro'         => esc_html__( 'Control how passkeys and two-factor authentication work together. Let users bypass the extra 2FA step after a successful passkey login for a faster experience, or require both for an added layer of protection on accounts that need it.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Stack passkey + 2FA for maximum protection', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Faster login for trusted users', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Balance convenience and security', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock with Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/#utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-passkeys-bypass-feature' ),
			),
		),
		'wp-2fa-passkeys:enforce-roles' => array(
			'title'         => esc_html__( 'Require passkeys for selected user roles', 'wp-2fa' ),
			'intro'         => esc_html__( 'Control which users can authenticate with passkeys by enabling or requiring them for specific roles on your website.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Apply passkey policies per user role', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Secure administrator and privileged accounts', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Roll out passkeys gradually across your site', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Maintain flexibility for different user groups', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock with Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/#utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-passkeys' ),
			),
		),
		'wp-2fa-settings'      => array(
			'title'         => esc_html__( 'Unlock advanced integrations & management tools with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Integrate WP 2FA with more authentication providers, connect it with popular WordPress plugins, and quickly migrate settings between websites.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'More authentication options', 'wp-2fa' ),
					'description' => esc_html__( 'Integrate with additional 2FA providers such as SMS, passkeys and hardware security keys including YubiKey', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'WooCommerce integration', 'wp-2fa' ),
					'description' => esc_html__( 'Add 2FA settings to the WooCommerce customer portal with one click. No custom code required.', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Save time managing websites', 'wp-2fa' ),
					'description' => esc_html__( 'Export and import settings between sites in just a few clicks', 'wp-2fa' ),
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'upgrade now', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-settings-page' ),
			),
		),
		'export-import'        => array(
			'title'         => esc_html__( 'Move settings between websites', 'wp-2fa' ),
			'intro'         => '',
			'description'   => esc_html__( 'Export and import WP 2FA configurations to quickly deploy consistent security policies across multiple websites.', 'wp-2fa' ),
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Save time during setup and deployment', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Standardize security settings', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Simplify multi-site management', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Ideal for agencies and administrators', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock Advanced Management', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-settings-page-export-import' ),
			),
		),
		'provider-integrations' => array(
			'title'         => esc_html__( 'Offer more ways to authenticate', 'wp-2fa' ),
			'intro'         => '',
			'description'   => esc_html__( 'Give users the flexibility to choose the authentication method that best fits their workflow and security requirements.', 'wp-2fa' ),
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Passkeys for passwordless authentication', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'YubiKey hardware security key support', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'SMS verification codes via Twilio', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Email login links and zero-setup email 2FA', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock More Authentication Methods', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-settings-page-provider-integrations' ),
			),
		),
		'plugin-integrations'  => array(
			'title'         => esc_html__( 'Seamlessly integrate with WordPress plugins', 'wp-2fa' ),
			'intro'         => '',
			'description'   => esc_html__( 'Add two-factor authentication to popular WordPress plugins and custom login workflows with minimal configuration.', 'wp-2fa' ),
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'One-click WooCommerce integration', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Support custom login pages and workflows', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Protect more user journeys across your website', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Extend 2FA beyond the standard WordPress login', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Upgrade to Premium', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-settings-page-plugin-integrations' ),
			),
		),
		'wp-2fa-settings-new'  => array(
			'title'         => esc_html__( 'Unlock advanced integrations & management tools with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Integrate WP 2FA with more authentication providers, connect it with popular WordPress plugins, and quickly migrate settings between websites.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'More authentication options', 'wp-2fa' ),
					'description' => esc_html__( 'Integrate with additional 2FA providers such as SMS, passkeys and hardware security keys including YubiKey', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'WooCommerce integration', 'wp-2fa' ),
					'description' => esc_html__( 'Add 2FA settings to the WooCommerce customer portal with one click. No custom code required.', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Save time managing websites', 'wp-2fa' ),
					'description' => esc_html__( 'Export and import settings between sites in just a few clicks', 'wp-2fa' ),
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'upgrade now', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-settings-page' ),
			),
		),
		'screen:wp-2fa_page_wp-2fa-settings' => array(
			'title'         => esc_html__( 'Unlock advanced integrations & management tools with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Integrate WP 2FA with more authentication providers, connect it with popular WordPress plugins, and quickly migrate settings between websites.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'More authentication options', 'wp-2fa' ),
					'description' => esc_html__( 'Integrate with additional 2FA providers such as SMS, passkeys and hardware security keys including YubiKey', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'WooCommerce integration', 'wp-2fa' ),
					'description' => esc_html__( 'Add 2FA settings to the WooCommerce customer portal with one click. No custom code required.', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Save time managing websites', 'wp-2fa' ),
					'description' => esc_html__( 'Export and import settings between sites in just a few clicks', 'wp-2fa' ),
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'upgrade now', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-settings-page' ),
			),
		),
		'screen:wp-2fa_page_wp-2fa-settings-new' => array(
			'title'         => esc_html__( 'Unlock advanced integrations & management tools with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Integrate WP 2FA with more authentication providers, connect it with popular WordPress plugins, and quickly migrate settings between websites.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'More authentication options', 'wp-2fa' ),
					'description' => esc_html__( 'Integrate with additional 2FA providers such as SMS, passkeys and hardware security keys including YubiKey', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'WooCommerce integration', 'wp-2fa' ),
					'description' => esc_html__( 'Add 2FA settings to the WooCommerce customer portal with one click. No custom code required.', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Save time managing websites', 'wp-2fa' ),
					'description' => esc_html__( 'Export and import settings between sites in just a few clicks', 'wp-2fa' ),
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'upgrade now', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-settings-page' ),
			),
		),
		'wl-setup-wizard'      => array(
			'title'         => esc_html__( 'Brand the onboarding experience', 'wp-2fa' ),
			'intro'         => '',
			'description'   => esc_html__( 'Customize the setup wizard to match your organization\'s branding and provide users with a seamless authentication setup experience.', 'wp-2fa' ),
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Add your own branding and messaging', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Create a more professional user experience', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Improve user trust and adoption', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Deliver a consistent experience across your website', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-white-labeling-tabs' ),
			),
		),
		'wl-prompts'           => array(
			'title'         => esc_html__( 'Personalize authentication messages', 'wp-2fa' ),
			'intro'         => '',
			'description'   => esc_html__( 'Customize user-facing prompts, emails, and notifications to better reflect your brand and communication style.', 'wp-2fa' ),
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Use your own wording and tone of voice', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Create a consistent branded experience', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Improve clarity for your users', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Reinforce trust during authentication', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-white-labeling-tabs' ),
			),
		),
		'wl-profile'           => array(
			'title'         => esc_html__( 'Tailor the user account experience', 'wp-2fa' ),
			'intro'         => '',
			'description'   => esc_html__( 'Customize the 2FA settings area in user profiles to better match your website, brand, and user requirements.', 'wp-2fa' ),
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Create a seamless branded experience', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Display only the information users need', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Improve usability and clarity', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Better align authentication with your website', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-white-labeling-tabs' ),
			),
		),
		'wp-2fa-white-labeling' => array(
			'title'         => esc_html__( 'Unlock full white labelling with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Enterprise', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Deliver a seamless branded experience by customizing the look, feel, and messaging of all WP 2FA pages, setup wizards, and emails.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Strengthen your brand', 'wp-2fa' ),
					'description' => esc_html__( 'Replace WP 2FA branding with your own logo, colours, and styling', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Customize user communications', 'wp-2fa' ),
					'description' => esc_html__( 'Tailor setup instructions, messaging, and email templates to your audience', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Provide a seamless experience', 'wp-2fa' ),
					'description' => esc_html__( 'Keep users within your brand throughout the entire 2FA journey', 'wp-2fa' ),
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock Full White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-white-labeling-tabs' ),
			),
			'ctaPremium'    => array(
				'text' => esc_html__( 'Unlock Full White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-white-labeling-tabs' ),
			),
		),
		'screen:wp-2fa_page_wp-2fa-white-labeling' => array(
			'title'         => esc_html__( 'Unlock full white labelling with', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Enterprise', 'wp-2fa' ) . '</span>',
			'intro'         => esc_html__( 'Deliver a seamless branded experience by customizing the look, feel, and messaging of all WP 2FA pages, setup wizards, and emails.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Strengthen your brand', 'wp-2fa' ),
					'description' => esc_html__( 'Replace WP 2FA branding with your own logo, colours, and styling', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Customize user communications', 'wp-2fa' ),
					'description' => esc_html__( 'Tailor setup instructions, messaging, and email templates to your audience', 'wp-2fa' ),
				),
				array(
					'title'       => esc_html__( 'Provide a seamless experience', 'wp-2fa' ),
					'description' => esc_html__( 'Keep users within your brand throughout the entire 2FA journey', 'wp-2fa' ),
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock Full White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-white-labeling-tabs' ),
			),
			'ctaPremium'    => array(
				'text' => esc_html__( 'Unlock Full White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-white-labeling-tabs' ),
			),
		),
		'wp-2fa-white-labeling:email-templates' => array(
			'title'         => esc_html__( 'Customize authentication emails', 'wp-2fa' ),
			'intro'         => esc_html__( 'Personalize the emails sent by WP 2FA to match your branding, messaging, and user experience requirements.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Customize email content and wording', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Align communications with your brand', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Improve clarity for your users', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Create a more professional experience', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-email-sms-templates-tabs' ),
			),
			'ctaPremium'    => array(
				'text' => esc_html__( 'Unlock White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-email-sms-templates-tabs' ),
			),
		),
		'wp-2fa-white-labeling:sms-templates' => array(
			'title'         => esc_html__( 'Customize authentication SMS messages', 'wp-2fa' ),
			'intro'         => esc_html__( 'Tailor SMS messages sent during authentication and account recovery workflows to better suit your organization and users.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Customize SMS content and messaging', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Provide clearer instructions to users', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Maintain a consistent brand experience', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Improve communication during login and recovery', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock Full White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-email-sms-templates-tabs' ),
			),
			'ctaPremium'    => array(
				'text' => esc_html__( 'Unlock Full White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-email-sms-templates-tabs' ),
			),
		),
		'wp-2fa-white-labeling:customize-code-page-design' => array(
			'title'         => esc_html__( 'Customize the 2FA code page design', 'wp-2fa' ),
			'intro'         => esc_html__( 'Modify the appearance of the 2FA code page to better match your website\'s branding and user experience.', 'wp-2fa' ),
			'description'   => '',
			'screenshotUrl' => '',
			'bullets'       => array(
				array(
					'title'       => esc_html__( 'Align the page with your brand identity', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Create a more professional login experience', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Build user trust with consistent styling', 'wp-2fa' ),
					'description' => '',
				),
				array(
					'title'       => esc_html__( 'Deliver a seamless authentication journey', 'wp-2fa' ),
					'description' => '',
				),
			),
			'cta'           => array(
				'text' => esc_html__( 'Unlock White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-white-labeling-tabs' ),
			),
			'ctaPremium'    => array(
				'text' => esc_html__( 'Unlock White Labelling', 'wp-2fa' ),
				'url'  => esc_url_raw( 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=premium-white-labeling-tabs' ),
			),
		),
		'wp-2fa-reports-locked' => array(
			'title'       => esc_html__( 'Unlock Activity Log Reports with the', 'wp-2fa' ) . ' ' . $hl . esc_html__( 'Premium Edition', 'wp-2fa' ) . '</span>',
			'description' => esc_html__( 'An upsell message related to this page. Add screenshot and bullet points based on final copy.', 'wp-2fa' ),
		),
	);

	$pages = apply_filters( WP_2FA_PREFIX . 'premium_badge_dialog_pages', $pages );

	if ( ! is_array( $pages ) ) {
		return array( 'default' => $default_page );
	}

	if ( empty( $pages['default'] ) || ! is_array( $pages['default'] ) ) {
		$pages['default'] = $default_page;
	}

	return $pages;
}

/**
 * Enqueue Select2 jQuery library
 *
 * @return void
 */
function enqueue_select2_scripts() {
	\wp_enqueue_style( 'select2', style_url( 'select2.min', 'admin' ), array(), WP_2FA_VERSION );
	\wp_enqueue_script( 'select2', script_url( 'select2.min', 'admin' ), array( 'jquery' ), WP_2FA_VERSION, false );
}

/**
 * Output a tiny global CSS rule to fix the menu icon size on all admin pages.
 *
 * WordPress expects square SVG icons but ours has a non-square viewBox (13.31×20).
 * Without this, the icon renders oversized when NOT on the plugin's own pages.
 *
 * @return void
 */
function admin_menu_icon_css() {
	echo '<style>#toplevel_page_wp-2fa-policies .wp-menu-image.svg{background-size:20px 20px!important}</style>' . "\n";
}

/**
 * Enqueue styles for admin.
 *
 * @return void
 */
function admin_styles() {

	global $pagenow;

	// Only load legacy admin styles on WP 2FA settings pages — not profile pages.
	// Profile pages use the new wizard CSS (wp2fa-wizard.css + wp2fa-profile.css).
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ( empty( $page ) || false === strpos( $page, 'wp-2fa' ) ) && 'profile.php' !== $pagenow && 'user-edit.php' !== $pagenow ) {
		return;
	}

	// On profile/user-edit pages, only enqueue if NOT using the new wizard
	// (the new Wizard_Integration handles its own CSS).
	if ( in_array( $pagenow, array( 'profile.php', 'user-edit.php' ), true ) ) {
		return;
	}

	wp_enqueue_style(
		'wp_2fa_admin',
		WP_2FA_URL . 'css/admin/wp2fa-admin-styles.css',
		array(),
		WP_2FA_VERSION
	);
}

/**
 * Add async/defer attributes to enqueued scripts that have the specified script_execution flag.
 *
 * @link https://core.trac.wordpress.org/ticket/12009
 * @param string $tag    The script tag.
 * @param string $handle The script handle.
 * @return string
 */
function script_loader_tag( $tag, $handle ) {
	$script_execution = wp_scripts()->get_data( $handle, 'script_execution' );

	if ( ! $script_execution ) {
		return $tag;
	}

	if ( 'async' !== $script_execution && 'defer' !== $script_execution ) {
		return $tag;
	}

	// Abort adding async/defer for scripts that have this script as a dependency. _doing_it_wrong()?
	foreach ( wp_scripts()->registered as $script ) {
		if ( in_array( $handle, $script->deps, true ) ) {
			return $tag;
		}
	}

	// Add the attribute if it hasn't already been added.
	if ( ! preg_match( ":\s$script_execution(=|>|\s):", $tag ) ) {
		$tag = preg_replace( ':(?=></script>):', " $script_execution", $tag, 1 );
	}

	return $tag;
}

/**
 * Generates random string used to salt the key
 *
 * @return string
 *
 * @since 2.3.0
 */
function wp_salt(): string {
	return WP2FA::get_secret_key();
}
