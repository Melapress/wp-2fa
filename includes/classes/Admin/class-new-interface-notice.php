<?php
/**
 * Responsible for showing the new interface announcement dialog after manual upgrades.
 *
 * @package    wp2fa
 * @subpackage admin
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin;

use WP2FA\WP2FA;
use WP2FA\Utils\Settings_Utils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\New_Interface_Notice' ) ) {

	/**
	 * Displays a one-time dialog prompting users to switch to the new interface after a manual plugin upgrade.
	 * Also shows a persistent banner on plugin pages if the user dismissed the dialog.
	 *
	 * @since 4.0.0
	 */
	class New_Interface_Notice {

		/**
		 * Option name used to flag that the dialog should be shown.
		 *
		 * @var string
		 */
		public const SHOW_DIALOG_OPTION = 'show-new-interface-dialog';

		/**
		 * Option name used to flag that the persistent banner should be shown.
		 *
		 * @var string
		 */
		public const SHOW_BANNER_OPTION = 'show-new-interface-banner';

		/**
		 * Initialize hooks.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function init() {
			// If the new interface is already in use, no need to advertise it.
			if ( Settings_Utils::string_to_bool( WP2FA::get_wp2fa_general_setting( 'use_new_interface' ) ) ) {
				return;
			}

			$show_dialog = Settings_Utils::get_option( self::SHOW_DIALOG_OPTION, false );
			$show_banner = Settings_Utils::get_option( self::SHOW_BANNER_OPTION, false );

			if ( ! $show_dialog && ! $show_banner ) {
				return;
			}

			\add_action( 'wp_ajax_wp2fa_switch_new_interface', array( __CLASS__, 'ajax_switch_new_interface' ) );
			\add_action( 'wp_ajax_wp2fa_dismiss_new_interface_dialog', array( __CLASS__, 'ajax_dismiss_dialog' ) );
			\add_action( 'wp_ajax_wp2fa_dismiss_new_interface_banner', array( __CLASS__, 'ajax_dismiss_banner' ) );

			if ( $show_dialog ) {
				\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_dialog_assets' ) );
				\add_action( 'admin_footer', array( __CLASS__, 'render_dialog_script' ) );
			} elseif ( $show_banner ) {
				\add_action( 'admin_notices', array( __CLASS__, 'render_banner' ) );
				\add_action( 'network_admin_notices', array( __CLASS__, 'render_banner' ) );
			}
		}

		/**
		 * Enqueue the dialog JS and CSS assets.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function enqueue_dialog_assets() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			\wp_enqueue_script( 'wp2fa-dialog' );
			\wp_enqueue_style( 'wp2fa-dialog' );
		}

		/**
		 * Outputs inline JavaScript in the admin footer to show the new interface dialog.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function render_dialog_script() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			$nonce        = \wp_create_nonce( 'wp2fa_new_interface_dialog' );
			$icon_url     = \esc_url( WP_2FA_URL . 'dist/images/wp-2fa-square.png' );
			$policies_url = \is_multisite() ? \network_admin_url( 'admin.php?page=wp-2fa-policies' ) : \admin_url( 'admin.php?page=wp-2fa-policies' );
			?>
			<style type="text/css">
			.wp2fa-new-ui-notice {
				text-align: left;
			}
			.wp2fa-new-ui-notice__header {
				display: flex;
				align-items: center;
				gap: 16px;
				margin-bottom: 20px;
			}
			.wp2fa-new-ui-notice__header img {
				width: 56px;
				height: 56px;
				flex-shrink: 0;
			}
			.wp2fa-new-ui-notice__header h2 {
				margin: 0;
				font-size: 22px;
				font-weight: 700;
				color: #1d2327;
				line-height: 1.3;
				max-width: 50%;
			}
			.wp2fa-new-ui-notice p {
				font-size: 14px;
				color: #3c434a;
				line-height: 1.7;
				margin: 0 0 16px;
			}
			.wp2fa-new-ui-notice__features {
				list-style: none;
				padding: 0;
				margin: 20px 0 8px;
			}
			.wp2fa-new-ui-notice__features li {
				display: flex;
				align-items: center;
				gap: 14px;
				padding: 3px 0;
				font-size: 15px;
				font-weight: 600;
				color: #1d2327;
			}
			.wp2fa-new-ui-notice__features li::before {
				content: '';
				flex-shrink: 0;
				width: 28px;
				height: 28px;
				border-radius: 50%;
				background: #2271b1;
				background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z'/%3E%3C/svg%3E");
				background-repeat: no-repeat;
				background-position: center;
				background-size: 16px;
			}
			.wp2fa-dialog-buttons .wp2fa-btn-switch,
			.wp2fa-dialog-buttons .wp2fa-btn-switch:focus,
			.wp2fa-dialog-buttons .wp2fa-btn-switch:active {
				background: #2271b1 !important;
				border-color: #2271b1 !important;
				color: #fff !important;
				font-size: 14px;
				font-weight: 600;
				padding: 8px 20px;
				border-radius: 4px;
				cursor: pointer;
				text-decoration: none;
				display: inline-block;
				line-height: 1.5;
				box-shadow: none !important;
				outline: none;
			}
			.wp2fa-dialog-buttons .wp2fa-btn-switch:hover {
				background: #135e96 !important;
				border-color: #135e96 !important;
				color: #fff !important;
			}
			.wp2fa-dialog-buttons .wp2fa-btn-keep {
				background: transparent;
				border: 0;
				color: #2271b1;
				font-size: 14px;
				font-weight: 500;
				padding: 8px 0;
				cursor: pointer;
				text-decoration: underline;
				display: inline-block;
				line-height: 1.5;
				box-shadow: none;
				outline: none;
			}
			.wp2fa-dialog-buttons .wp2fa-btn-keep:hover {
				color: #135e96;
			}
			.wp2fa-dialog-buttons .wp2fa-btn-keep:focus {
				color: #135e96;
			}
			.wp2fa-new-ui-notice__title {
				margin: 0 0 16px;
				font-size: 22px;
				font-weight: 700;
				color: #1d2327;
				line-height: 1.3;
			}
			</style>
			<script type="text/javascript">
			document.addEventListener( 'DOMContentLoaded', function() {
				if ( typeof wp2faDialog === 'undefined' ) {
					return;
				}

				var ajaxUrl = '<?php echo \esc_url( \admin_url( 'admin-ajax.php' ) ); ?>';
				var nonce   = '<?php echo \esc_attr( $nonce ); ?>';
				var iconUrl = '<?php echo \esc_js( $icon_url ); ?>';

				var messageHtml = '<div class="wp2fa-new-ui-notice">' +
					'<div class="wp2fa-new-ui-notice__header">' +
					'<img src="' + iconUrl + '" alt="WP 2FA" />' +
					'<h2><?php echo \esc_js( \__( 'Announcing the New WP 2FA Interface – Try it now!', 'wp-2fa' ) ); ?></h2>' +
					'</div>' +
					'<p><?php echo \esc_js( \__( 'WP 2FA 4.0 introduces a completely redesigned user interface, built to make configuration and day-to-day management easier, faster, and more intuitive.', 'wp-2fa' ) ); ?></p>' +
					'<p><?php echo \esc_js( \__( 'To ensure a smooth upgrade experience, you are still using the previous interface. You can switch to the new interface by clicking the button "Switch to the new interface" below, or at any time with a single click from the plugin\'s General Settings.', 'wp-2fa' ) ); ?></p>' +
					'<ul class="wp2fa-new-ui-notice__features">' +
					'<li><?php echo \esc_js( \__( 'Improved navigation and usability', 'wp-2fa' ) ); ?></li>' +
					'<li><?php echo \esc_js( \__( 'A more streamlined setup experience', 'wp-2fa' ) ); ?></li>' +
					'<li><?php echo \esc_js( \__( 'Better organization of settings and options', 'wp-2fa' ) ); ?></li>' +
					'<li><?php echo \esc_js( \__( 'A modern design consistent with the latest Melapress plugins', 'wp-2fa' ) ); ?></li>' +
					'</ul>' +
					'</div>';

				var deferMessageHtml = '<div class="wp2fa-new-ui-notice">' +
					'<h2 class="wp2fa-new-ui-notice__title"><?php echo \esc_js( \__( 'You can upgrade to the new UI at any time.', 'wp-2fa' ) ); ?></h2>' +
					'<p><?php echo \esc_js( \__( 'You can switch to the new interface whenever you are ready by going to:', 'wp-2fa' ) ); ?> <strong><?php echo \esc_js( \__( 'Settings > General Settings', 'wp-2fa' ) ); ?></strong> <?php echo \esc_js( \__( 'and then clicking the Switch to the new interface button.', 'wp-2fa' ) ); ?></p>' +
					'</div>';

				wp2faDialog.open( {
					content: messageHtml,
					size: 'large',
					onClose: function() {
						dismissDialog();
					},
					buttons: [
						{
							text: '<?php echo \esc_js( \__( 'Switch to the new interface', 'wp-2fa' ) ); ?>',
							className: 'wp2fa-btn-switch',
							onClick: function() {
								switchToNewInterface();
							}
						},
						{
							text: '<?php echo \esc_js( \__( 'Keep using the current interface for now', 'wp-2fa' ) ); ?>',
							className: 'wp2fa-btn-keep',
							element: 'link',
							onClick: function() {
								window.setTimeout( function() {
									wp2faDialog.open( {
										content: deferMessageHtml,
										size: 'large',
										onClose: function() {
											dismissDialog();
										},
										buttons: [
											{
												text: '<?php echo \esc_js( \__( 'Understood', 'wp-2fa' ) ); ?>',
												className: 'wp2fa-btn-switch',
												onClick: function() {
													dismissDialog();
												}
											}
										]
									} );
								}, 0 );
							}
						}
					]
				} );

				var policiesUrl = '<?php echo \esc_url( $policies_url ); ?>';

				function switchToNewInterface() {
					var xhr = new XMLHttpRequest();
					xhr.open( 'POST', ajaxUrl, true );
					xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
					xhr.onload = function() {
						window.location.href = policiesUrl;
					};
					xhr.send( 'action=wp2fa_switch_new_interface&nonce=' + encodeURIComponent( nonce ) );
				}

				function dismissDialog() {
					var xhr = new XMLHttpRequest();
					xhr.open( 'POST', ajaxUrl, true );
					xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
					xhr.onload = function() {
						window.location.href = policiesUrl;
					};
					xhr.send( 'action=wp2fa_dismiss_new_interface_dialog&nonce=' + encodeURIComponent( nonce ) );
				}
			} );
			</script>
			<?php
		}

		/**
		 * AJAX handler: switch to the new interface.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function ajax_switch_new_interface() {
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';

			if ( ! \current_user_can( 'manage_options' ) || ! \wp_verify_nonce( $nonce, 'wp2fa_new_interface_dialog' ) ) {
				\wp_send_json_error( \esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			// Update the use_new_interface setting to true.
			$settings = Settings_Utils::get_option( WP_2FA_SETTINGS_NAME, array() );

			if ( ! \is_array( $settings ) ) {
				$settings = array();
			}

			$settings['use_new_interface'] = true;

			Settings_Utils::update_option( WP_2FA_SETTINGS_NAME, $settings );

			// Clear the in-memory cached settings so the new value is picked up.
			WP2FA::update_plugin_settings( $settings, true, WP_2FA_SETTINGS_NAME );

			// Remove the dialog flag.
			Settings_Utils::delete_option( self::SHOW_DIALOG_OPTION );

			// Remove the banner flag too.
			Settings_Utils::delete_option( self::SHOW_BANNER_OPTION );

			\wp_send_json_success();
		}

		/**
		 * AJAX handler: dismiss the dialog without switching.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function ajax_dismiss_dialog() {
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';

			if ( ! \current_user_can( 'manage_options' ) || ! \wp_verify_nonce( $nonce, 'wp2fa_new_interface_dialog' ) ) {
				\wp_send_json_error( \esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			// Remove the dialog flag.
			Settings_Utils::delete_option( self::SHOW_DIALOG_OPTION );

			// Show the persistent banner on plugin pages instead.
			Settings_Utils::update_option( self::SHOW_BANNER_OPTION, true );

			\wp_send_json_success();
		}

		/**
		 * AJAX handler: dismiss the persistent banner.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function ajax_dismiss_banner() {
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';

			if ( ! \current_user_can( 'manage_options' ) || ! \wp_verify_nonce( $nonce, 'wp2fa_new_interface_banner' ) ) {
				\wp_send_json_error( \esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			Settings_Utils::delete_option( self::SHOW_BANNER_OPTION );

			\wp_send_json_success();
		}

		/**
		 * Renders the persistent banner on plugin admin pages.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function render_banner() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			// Only show on WP 2FA plugin pages.
			$screen = \get_current_screen();
			if ( ! $screen || ! \in_array( $screen->id, \WP2FA\Admin\Helpers\WP_Helper::PLUGIN_PAGES, true ) ) {
				return;
			}

			$nonce    = \wp_create_nonce( 'wp2fa_new_interface_banner' );
			$switch_nonce = \wp_create_nonce( 'wp2fa_new_interface_dialog' );
			$icon_url = \esc_url( WP_2FA_URL . 'dist/images/wp-2fa-square.png' );
			?>
			<div class="wp2fa-new-interface-banner" id="wp2fa-new-interface-banner">
				<div class="wp2fa-new-interface-banner__content">
					<img src="<?php echo $icon_url; ?>" alt="WP 2FA" class="wp2fa-new-interface-banner__icon" />
					<div class="wp2fa-new-interface-banner__text">
						<span class="wp2fa-new-interface-banner__title">
							<?php echo \esc_html__( 'New Interface Available', 'wp-2fa' ); ?>
							&ndash;
							<span class="wp2fa-new-interface-banner__highlight"><?php echo \esc_html__( 'Try WP 2FA 4.0 Now!', 'wp-2fa' ); ?></span>
						</span>
						<span class="wp2fa-new-interface-banner__desc"><?php echo \esc_html__( 'A completely redesigned UI, built to be easier and more intuitive. Switch in one click from General Settings.', 'wp-2fa' ); ?></span>
					</div>
				</div>
				<div class="wp2fa-new-interface-banner__actions">
					<button type="button" class="wp2fa-new-interface-banner__switch-btn" id="wp2fa-banner-switch-btn"><?php echo \esc_html__( 'Switch to new interface', 'wp-2fa' ); ?></button>
					<button type="button" class="wp2fa-new-interface-banner__close" id="wp2fa-banner-close-btn" aria-label="<?php \esc_attr_e( 'Dismiss', 'wp-2fa' ); ?>">&times;</button>
				</div>
			</div>
			<style>
			.wp2fa-new-interface-banner {
				display: flex;
				align-items: center;
				justify-content: space-between;
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				padding: 12px 16px;
				margin: 10px 20px 16px 0;
				gap: 16px;
			}
			.wp2fa-new-interface-banner__content {
				display: flex;
				align-items: center;
				gap: 14px;
				flex: 1;
				min-width: 0;
			}
			.wp2fa-new-interface-banner__icon {
				width: 36px;
				height: 36px;
				flex-shrink: 0;
			}
			.wp2fa-new-interface-banner__text {
				display: flex;
				flex-direction: column;
				gap: 2px;
				min-width: 0;
			}
			.wp2fa-new-interface-banner__title {
				font-size: 14px;
				font-weight: 600;
				color: #1d2327;
				white-space: nowrap;
			}
			.wp2fa-new-interface-banner__highlight {
				color: #2271b1;
			}
			.wp2fa-new-interface-banner__desc {
				font-size: 13px;
				color: #50575e;
				line-height: 1.4;
			}
			.wp2fa-new-interface-banner__actions {
				display: flex;
				align-items: center;
				gap: 12px;
				flex-shrink: 0;
			}
			.wp2fa-new-interface-banner__switch-btn {
				background: #2271b1;
				border: 1px solid #2271b1;
				color: #fff;
				font-size: 13px;
				font-weight: 500;
				padding: 7px 16px;
				border-radius: 4px;
				cursor: pointer;
				white-space: nowrap;
				line-height: 1.4;
			}
			.wp2fa-new-interface-banner__switch-btn:hover,
			.wp2fa-new-interface-banner__switch-btn:focus {
				background: #135e96;
				border-color: #135e96;
				color: #fff;
				outline: none;
			}
			.wp2fa-new-interface-banner__close {
				background: none;
				border: none;
				font-size: 20px;
				line-height: 1;
				color: #787c82;
				cursor: pointer;
				padding: 4px 8px;
				border-radius: 3px;
			}
			/* .wp2fa-new-interface-banner__close:hover, */
			.wp2fa-new-interface-banner__close:focus {
				color: #1d2327;
				background: #e8e8e8;
				outline: none;
			}
			@media screen and (max-width: 600px) {
				.wp2fa-new-interface-banner {
					flex-direction: column;
					align-items: flex-start;
				}
				.wp2fa-new-interface-banner__title {
					white-space: normal;
				}
			}
			</style>
			<script>
			(function() {
				var ajaxUrl = '<?php echo \esc_url( \admin_url( 'admin-ajax.php' ) ); ?>';
				var bannerNonce = '<?php echo \esc_attr( $nonce ); ?>';
				var switchNonce = '<?php echo \esc_attr( $switch_nonce ); ?>';
				var banner = document.getElementById('wp2fa-new-interface-banner');

				document.getElementById('wp2fa-banner-close-btn').addEventListener('click', function() {
					banner.style.display = 'none';
					var xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxUrl, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.send('action=wp2fa_dismiss_new_interface_banner&nonce=' + encodeURIComponent(bannerNonce));
				});

				document.getElementById('wp2fa-banner-switch-btn').addEventListener('click', function() {
					var xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxUrl, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.onload = function() {
						window.location.reload();
					};
					xhr.send('action=wp2fa_switch_new_interface&nonce=' + encodeURIComponent(switchNonce));
				});
			})();
			</script>
			<?php
		}
	}
}
