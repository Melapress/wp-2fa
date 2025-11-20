<?php
/**
 * Responsible for the WP core functionalities.
 *
 * @package    wp2fa
 * @subpackage helpers
 *
 * @since      2.2.0
 *
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Helpers;

use WP2FA\Admin\Plugin_Updated_Notice;
use WP2FA\Utils\Settings_Utils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/*
 * WP helper class
 */
if ( ! class_exists( '\WP2FA\Admin\Helpers\WP_Helper' ) ) {
	/**
	 * All the WP functionality must go trough this class.
	 *
	 * @since 2.2.0
	 */
	class WP_Helper {

		public const PLUGIN_PAGES = array(
			'wp-2fa_page_wp-2fa-settings',
			'wp-2fa_page_wp-2fa-settings-network',
			'toplevel_page_wp-2fa-policies',
			'toplevel_page_wp-2fa-policies-network',
			'wp-2fa_page_wp-2fa-reports',
			'wp-2fa_page_wp-2fa-reports-network',
			'wp-2fa_page_wp-2fa-help-contact-us',
			'wp-2fa_page_wp-2fa-help-contact-us-network',
			'wp-2fa_page_wp-2fa-premium-features',
			'wp-2fa_page_wp-2fa-premium-features-network',
			'wp-2fa_page_wp-2fa-policies-account',
			'wp-2fa_page_wp-2fa-policies-account-network',
		);

		/**
		 * Hold the user roles as array - Human readable is used for key of the array, and the internal role name is the value.
		 *
		 * @var array
		 *
		 * @since 2.2.0
		 */
		private static $user_roles = array();

		/**
		 * Hold the user roles as array - Internal role name is used for key of the array, and the human readable format is the value.
		 *
		 * @var array
		 *
		 * @since 2.2.0
		 */
		private static $user_roles_wp = array();

		/**
		 * Keeps the value of the multisite install of the WP.
		 *
		 * @var bool
		 *
		 * @since 2.2.0
		 */
		private static $is_multisite = null;

		/**
		 * Holds array with all the sites in multisite WP installation.
		 *
		 * @var array
		 */
		private static $sites = array();

		/**
		 * Inits the class, and fires all the necessarily methods.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function init() {
			// @free:start
			$today_date = gmdate( 'Y-m-d' );
			$today_date = gmdate( 'Y-m-d', strtotime( $today_date ) );

			$event_date_begin = gmdate( 'Y-m-d', strtotime( '11/21/2025' ) );
			$event_date_end   = gmdate( 'Y-m-d', strtotime( '12/01/2025' ) );

			$event_ending_date = \get_site_option( WP_2FA_PREFIX . '_extra_event_banner_end_date', false );

			$extra_event_banner_dismissed = \get_site_option( WP_2FA_PREFIX . '_extra_event_banner_dismissed', false );
			$extra_event_banner_super_dismissed = \get_site_option( WP_2FA_PREFIX . '_extra_event_banner_super_dismissed', false );

			if ( gmdate( 'Y-m-d', strtotime( '11/28/2025' ) ) === $today_date && $extra_event_banner_dismissed && ! $extra_event_banner_super_dismissed ) {
				\delete_site_option( WP_2FA_PREFIX . '_extra_event_banner_dismissed' );
			}

			if ( ( $today_date >= $event_date_begin ) && ( $today_date <= $event_date_end ) && ( false === $event_ending_date || strtotime( $event_ending_date ) < strtotime( $today_date ) ) ) {
				$extra_event_banner_dismissed = \get_site_option( WP_2FA_PREFIX . '_extra_event_banner_dismissed', false );
				if ( ! $extra_event_banner_dismissed ) {
					\update_site_option( WP_2FA_PREFIX . '_extra_event_banner', true );
					\update_site_option( WP_2FA_PREFIX . '_extra_event_banner_end_date', strtotime( $event_date_end ) );
					\update_site_option( WP_2FA_PREFIX . '_extra_event_banner_dismissed', false );
				}
			} else {
				\delete_site_option( WP_2FA_PREFIX . '_extra_event_banner' );
				\delete_site_option( WP_2FA_PREFIX . '_extra_event_banner_end_date' );
				\delete_site_option( WP_2FA_PREFIX . '_extra_event_banner_dismissed' );
				\delete_site_option( WP_2FA_PREFIX . '_extra_event_banner_super_dismissed' );
			}
			// @free:end

			if ( self::is_multisite() ) {
				\add_action( 'network_admin_notices', array( __CLASS__, 'show_critical_admin_notice' ) );
				// @free:start
				\add_action( 'network_admin_notices', array( __CLASS__, 'show_2025_security_survey_admin_notice' ), 20 );
				// @free:end
			} else {
				\add_action( 'admin_notices', array( __CLASS__, 'show_critical_admin_notice' ) );
				// @free:start
				\add_action( 'admin_notices', array( __CLASS__, 'show_2025_security_survey_admin_notice' ), 20 );
				// @free:end
			}
			// @free:start
			\add_action( 'wp_ajax_wp_2fa_dismiss_extra_event_banner', array( __CLASS__, 'dismiss_extra_event_banner' ) );
			// @free:end

			\add_action( 'wp_ajax_dismiss_survey_notice', array( __CLASS__, 'dismiss_survey_notice' ) );
		}

		/**
		 * Checks if specific role exists.
		 *
		 * @param string $role - The name of the role to check.
		 *
		 * @since 2.2.0
		 */
		public static function is_role_exists( string $role ): bool {
			self::set_roles();

			if ( in_array( $role, self::$user_roles, true ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Returns the currently available WP roles - the Human readable format is the key.
		 *
		 * @return array
		 *
		 * @since 2.2.0
		 */
		public static function get_roles() {
			self::set_roles();

			return self::$user_roles;
		}

		/**
		 * Returns the currently available WP roles.
		 *
		 * @return array
		 *
		 * @since 2.2.0
		 */
		public static function get_roles_wp() {
			if ( empty( self::$user_roles_wp ) ) {
				self::set_roles();
				self::$user_roles_wp = array_flip( self::$user_roles );
			}

			return self::$user_roles_wp;
		}

		/**
		 * Shows critical notices to the admin.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function show_critical_admin_notice() {
			if ( User_Helper::is_admin() ) {
				/*
				 * Gives the ability to show notices to the admins
				 */
				\do_action( WP_2FA_PREFIX . 'critical_notice' );
			}
		}

		/**
		 * Shows critical notices to the admin.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function show_2025_security_survey_admin_notice() {
			$screen                       = \get_current_screen();
			$show_extra_event_banner      = \get_site_option( WP_2FA_PREFIX . '_extra_event_banner', false );
			$extra_event_banner_dismissed = \get_site_option( WP_2FA_PREFIX . '_extra_event_banner_dismissed', false );

			if ( $show_extra_event_banner ) {
				$event_ending_date = \get_site_option( WP_2FA_PREFIX . '_extra_event_banner_end_date', false );
				if ( $event_ending_date && ( \time() > (int) ( $event_ending_date ) ) ) {
					$show_extra_event_banner = false;
					\delete_site_option( WP_2FA_PREFIX . '_extra_event_banner' );
					\delete_site_option( WP_2FA_PREFIX . '_extra_event_banner_end_date' );
					\delete_site_option( WP_2FA_PREFIX . '_extra_event_banner_dismissed' );
				}
			}
			if ( in_array( $screen->base, self::PLUGIN_PAGES, true ) && $show_extra_event_banner && ! $extra_event_banner_dismissed ) {
				\remove_action( 'admin_notices', array( Plugin_Updated_Notice::class, 'plugin_update_banner' ), 30 );
				\remove_action( 'network_admin_notices', array( Plugin_Updated_Notice::class, 'plugin_update_banner' ), 30 );
				?>
				<!-- Copy START -->
				<div class="black-friday wp-2fa-extra-event-banner" style="margin-top: 20px; margin-right: 20px;">
					<!-- SVG Icon on the Left -->
					<img class="black-friday-svg" src="<?php echo esc_url( WP_2FA_URL . 'dist/images/upgrade-plugin-icon.svg' ); ?>" alt="Premium Plugin" width="113" height="101">
					
					<!-- Text Content -->
					<div class="black-friday-content">
					<h2 class="black-friday-title"><?php \esc_html_e( 'Upgrade to Premium', 'wp-2fa' ); ?><br>
						<span class="bf-title-line-2"><span class="bf-underline"><?php \esc_html_e( 'Black Friday', 'wp-2fa' ); ?></span> <?php \esc_html_e( ' Sale Now Live!', 'wp-2fa' ); ?></span>
					</h2>
					<a href="https://melapress.com/black-friday-cyber-monday/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=BFCM2025" target="_blank" class="bf-cta-link"><?php \esc_html_e( 'Get Offer Now', 'wp-2fa' ); ?></a>
					</div>
					
					<!-- Close Button -->
					<button aria-label="Close button" class="wp-2fa-extra-event-banner-close black-friday-close" data-dismiss-nonce="<?php echo \esc_attr( \wp_create_nonce( 'wp_2fa_dismiss_extra_event_banner_nonce' ) ); ?>"></button>
				</div>
				<!-- Copy END -->
				
				<script type="text/javascript">
				jQuery(document).ready(function( $ ) {
					jQuery( 'body' ).on( 'click', '.wp-2fa-extra-event-banner-close', function ( e ) {
						var nonce  = jQuery( '.wp-2fa-extra-event-banner [data-dismiss-nonce]' ).attr( 'data-dismiss-nonce' );
						
						jQuery.ajax({
							type: 'POST',
							url: '<?php echo \esc_url( \admin_url( 'admin-ajax.php' ) ); ?>',
							async: true,
							data: {
								action: 'wp_2fa_dismiss_extra_event_banner',
								nonce : nonce,
							},
							success: function ( result ) {		
								jQuery( '.wp-2fa-extra-event-banner' ).slideUp( 300 );
							}
						});
					});
				});
				</script>
				<style>
					@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Quicksand:wght@600;700&display=swap');

					:root {
					--color-coral: #FF8977;
					--color-deep: #020E26;
					--color-pale-blue: #D9E4FD;
					--color-light-blue: #8AAAF1;
					--color-wp-2fa-maroon: #7A262A;
					--color-wp-2fa-red: #DD2B10;
					--ease-out-expo: cubic-bezier(0.32, 1, 0.3, 1);
					--ease-out-back: cubic-bezier(0.64, 0.69, 0.1, 1);
					}

					/* ==================== Black Friday Banner ==================== */
					.black-friday {
					background-color: var(--color-deep);
					font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
					-webkit-font-smoothing: subpixel-antialiased;
					color: #fff;
					display: flex;
					align-items: center;
					padding: 1.66rem;
					position: relative;
					overflow: hidden;
					transition: all 0.2s ease-in-out;
					border: none;
					border-left: 4px solid var(--color-coral);
					gap: 1.5rem; /* Space between SVG and text */
					}

					.black-friday-svg {
					flex-shrink: 0;
					width: 134px;
					height: 101px;
					z-index: 1;
					margin-right: 32px;
					}

					.black-friday-content {
					max-width: 45%;
					z-index: 1;
					}

					.black-friday-title {
					font-family: Inter, sans-serif;
					font-size: 2.2em;
					letter-spacing: .5px;
					text-transform: uppercase;
					color: var(--color-coral);
					line-height: .7em;
					font-weight: 900;
					margin-bottom: 4px;
					}

					.bf-title-line-2 {
					color: #fff;
					font-size: .725em;
					}

					.bf-underline {
					text-decoration: underline;
					}

					.black-friday-text {
					margin: .25rem 0 0;
					font-size: 13px;
					line-height: 1.3125rem;
					}

					.bf-link {
					color: #fff;
					font-weight: 400;
					text-decoration: underline;
					font-size: 0.875rem;
					padding: 0.675rem 1.3rem .7rem 0;
					transition: all 0.2s ease-in-out;
					display: inline-block;
					margin: .5rem 0 0;
					}

					.bf-link:hover {
					color: #D9E4FD;
					}

					.bf-cta-link {
					border-radius: 0.25rem;
					background: #D9E4FD;
					color: #454BF7;
					font-weight: bold;
					text-decoration: none;
					font-size: 0.875rem;
					padding: 0.675rem 1.3rem .7rem 1.3rem;
					transition: all 0.2s ease-in-out;
					display: inline-block;
					margin: .5rem 0 0;
					}

					.bf-cta-link:hover {
					background: #454BF7;
					color: #D9E4FD;
					}

					.black-friday-close {
					background-image: url('<?php echo esc_url( WP_2FA_URL . 'dist/images/close-icon-reverse.svg' ); ?>');
					background-size: cover;
					width: 12px;
					height: 12px;
					border: none;
					cursor: pointer;
					position: absolute;
					top: 20px;
					right: 20px;
					background-color: transparent;
					z-index: 1;
					}

					.black-friday {
					background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 182 127"><path d="M181.413 -165.636L134.391 234.514L0 234.514L2.19345e-05 -165.636L181.413 -165.636Z" fill="%23B6C3F2"/></svg>');
					background-repeat: no-repeat;
					background-position: -20px 0;
					}

					/* Z-index for layered elements */
					.plugin-update-content,
					.wp-2fa-svg,
					.plugin-update-close,
					.black-friday-content,
					.black-friday-svg,
					.black-friday-close {
					z-index: 1;
					}

					/* ==================== Responsive Design ==================== */
					@media (max-width: 1200px) {
					.plugin-update,
					.black-friday {
						background-image: none;
						flex-direction: column;
						text-align: center;
						gap: 1rem;
					}

					.wp-2fa-svg,
					.black-friday-svg {
						width: 90px;
						height: 80px;
						margin: 0;
					}

					.plugin-update-content,
					.black-friday-content {
						max-width: 100%;
					}
					}
				</style>

				<?php
			}
		}

		/**
		 * Handle event banner dismissal.
		 *
		 * @return void
		 *
		 * @since 3.0.1
		 */
		public static function dismiss_extra_event_banner() {
			// Grab POSTed data.
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : false;

			// Check nonce.
			if ( ! \current_user_can( 'manage_options' ) || empty( $nonce ) || ! $nonce || ! \wp_verify_nonce( $nonce, 'wp_2fa_dismiss_extra_event_banner_nonce' ) ) {
				\wp_send_json_error( \esc_html__( 'Nonce Verification Failed.', 'wp-2fa' ) );
			}

			\delete_site_option( WP_2FA_PREFIX . '_extra_event_banner' );
			\update_site_option( WP_2FA_PREFIX . '_extra_event_banner_dismissed', 'yes' );

			$today_date = gmdate( 'Y-m-d' );
			$today_date = gmdate( 'Y-m-d', strtotime( $today_date ) );

			if ( gmdate( 'Y-m-d', strtotime( '11/28/2025' ) ) === $today_date ) {
				\update_site_option( WP_2FA_PREFIX . '_extra_event_banner_super_dismissed', 'yes' );
			}

			\wp_send_json_success( \esc_html__( 'Complete.', 'wp-2fa' ) );
		}



		/**
		 * Handle notice dismissal.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public static function dismiss_survey_notice() {
			// Grab POSTed data.
			$nonce_check = \check_ajax_referer( 'wp_2fa_dismiss_survey_notice_nonce', 'nonce' );

			if ( ! $nonce_check ) {
				\wp_send_json_error( esc_html__( 'Nonce Verification Failed.', 'wp-2fa' ) );
			}
			// $nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : false;
			// Check nonce.
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error( esc_html__( 'Not enough privileges.', 'wp-2fa' ) );
			}

			Settings_Utils::update_option( 'wp_2fa_survey_notice_needed', 0 );

			\wp_send_json_success( \esc_html__( 'Complete.', 'wp-2fa' ) );
		}

		/**
		 * Check is this is a multisite setup.
		 *
		 * @return bool
		 *
		 * @since 2.2.0
		 */
		public static function is_multisite() {
			if ( null === self::$is_multisite ) {
				self::$is_multisite = function_exists( 'is_multisite' ) && is_multisite();
			}

			return self::$is_multisite;
		}

		/**
		 * Collects all the sites from multisite WP installation.
		 *
		 * @since 2.5.0
		 */
		public static function get_multi_sites(): array {
			if ( self::is_multisite() ) {
				if ( empty( self::$sites ) ) {
					self::$sites = self::get_sites();
				}

				return self::$sites;
			}

			return array();
		}

		/**
		 * Query sites from WPDB.
		 *
		 * @since 3.0.0
		 *
		 * @param int|null $limit — Maximum number of sites to return (null = no limit).
		 *
		 * @return object — Object with keys: blog_id, blogname, domain
		 */
		public static function get_sites( $limit = null ) {
			if ( self::is_multisite() ) {
				global $wpdb;
				// Build query.
				$sql =
					'SELECT blog_id, domain FROM ' . $wpdb->blogs . ( ! is_null( $limit ) ? ' LIMIT ' . $limit : '' );

				// Execute query.
				$res = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

				// Modify result.
				foreach ( $res as $row ) {
					$row->blogname = \esc_html( \get_blog_option( $row->blog_id, 'blogname' ) );
				}
			} else {
				$res           = new \stdClass();
				$res->blog_id  = \get_current_blog_id();
				$res->blogname = \esc_html( \get_bloginfo( 'name' ) );
				$res           = array( $res );
			}

			// Return result.
			return $res;
		}

		/**
		 * Calculating the signature.
		 *
		 * @param array $data - Array with data to create a signature for.
		 *
		 * @since 2.2.2
		 */
		public static function calculate_api_signature( array $data ): string {
			$now   = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
			$nonce = $now->getTimestamp();

			$pk_hash               = hash( 'sha512', $data['license_key'] . '|' . $nonce );
			$authentication_string = base64_encode( $pk_hash . '|' . $nonce );

			return $authentication_string;
		}

		/**
		 * Checks if that is the WP login page or not.
		 *
		 * @return bool
		 *
		 * @since 2.4.1
		 */
		public static function is_wp_login() {
			$abs_path = str_replace( array( '\\', '/' ), DIRECTORY_SEPARATOR, ABSPATH );

			if ( function_exists( 'is_account_page' ) && \is_account_page() ) {
				// The user is on the WooCommerce login page.

				return true;
			}

			return ( in_array( $abs_path . 'wp-login.php', get_included_files() ) || in_array( $abs_path . 'wp-register.php', get_included_files() ) ) || ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) || '/wp-login.php' == $_SERVER['PHP_SELF']; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		}

		/**
		 * Check whether we are on an admin and plugin page.
		 *
		 * @since 2.4.1
		 *
		 * @param array|string $slug ID(s) of a plugin page. Possible values: 'general', 'logs', 'about' or array of them.
		 *
		 * @return bool
		 */
		public static function is_admin_page( $slug = array() ) { // phpcs:ignore Generic.Metrics.NestingLevel.MaxExceeded

			$cur_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$check    = WP_2FA_PREFIX_PAGE;

			return \is_admin() && ( false !== strpos( $cur_page, $check ) );
		}

		/**
		 * Remove all non-WP Mail SMTP plugin notices from our plugin pages.
		 *
		 * @since 2.4.1
		 */
		public static function hide_unrelated_notices() {
			// Bail if we're not on our screen or page.
			if ( ! self::is_admin_page() ) {
				return;
			}

			self::remove_unrelated_actions( 'user_admin_notices' );
			self::remove_unrelated_actions( 'admin_notices' );
			self::remove_unrelated_actions( 'all_admin_notices' );
			self::remove_unrelated_actions( 'network_admin_notices' );
		}

		/**
		 * Creates a nonce for HTML field by given name.
		 *
		 * @param string $nonce_name -The name of the nonce to create.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function create_data_nonce( string $nonce_name ): string {
			return ' data-nonce="' . \esc_attr( \wp_create_nonce( $nonce_name ) ) . '"';
		}

		/**
		 * Extracts the domain part of the given string.
		 *
		 * @param string $url_to_check - The URL string to be checked.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function extract_domain( string $url_to_check ): string {
			// get the full domain.
			// $urlparts = parse_url( \site_url() );.

			if ( false !== strpos( $url_to_check, '@' ) ) {
				$domain = \explode( '@', $url_to_check )[1];

				return $domain;
			}
			$urlparts = parse_url( $url_to_check );
			$domain   = $urlparts ['host'];

			// get the TLD and domain.
			$domainparts = explode( '.', $domain );
			$domain      = $domainparts[ count( $domainparts ) - 2 ] . '.' . $domainparts[ count( $domainparts ) - 1 ];

			return $domain;
		}

		/**
		 * Remove all non-WP Mail SMTP notices from the our plugin pages based on the provided action hook.
		 *
		 * @since 2.4.1
		 *
		 * @param string $action The name of the action.
		 */
		private static function remove_unrelated_actions( $action ) {
			global $wp_filter;

			if ( empty( $wp_filter[ $action ]->callbacks ) || ! is_array( $wp_filter[ $action ]->callbacks ) ) {
				return;
			}

			foreach ( $wp_filter[ $action ]->callbacks as $priority => $hooks ) {
				foreach ( $hooks as $name => $arr ) {
					if (
						( // Cover object method callback case.
							is_array( $arr['function'] ) &&
							isset( $arr['function'][0] ) &&
							is_object( $arr['function'][0] ) &&
							false !== strpos( ( get_class( $arr['function'][0] ) ), 'WP2FA' )
						) ||
						( // Cover class static method callback case.
							! empty( $name ) &&
							false !== strpos( ( $name ), 'WP2FA' )
						)
					) {
						continue;
					}

					unset( $wp_filter[ $action ]->callbacks[ $priority ][ $name ] );
				}
			}
		}

		/**
		 * Sets the internal variable with all the existing WP roles.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		private static function set_roles() {
			if ( empty( self::$user_roles ) ) {
				global $wp_roles;

				if ( null === $wp_roles ) {
					wp_roles();
				}

				self::$user_roles = array_flip( $wp_roles->get_names() );
			}
		}
	}
}
