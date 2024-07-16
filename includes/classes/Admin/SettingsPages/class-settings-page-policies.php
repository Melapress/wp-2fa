<?php
/**
 * Policy settings class.
 *
 * @package    wp2fa
 * @subpackage settings-pages
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\SettingsPages;

use WP2FA\WP2FA;
use WP2FA\Methods\TOTP;
use WP2FA\Methods\Email;
use WP2FA\Utils\Debugging;
use WP2FA\Admin\Settings_Page;
use WP2FA\Utils\Generate_Modal;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Views\First_Time_Wizard_Steps;

/*
 * Policies settings tab
 */
if ( ! class_exists( '\WP2FA\Admin\SettingsPages\Settings_Page_Policies' ) ) {
	/**
	 * Settings_Page_Policies - Class for handling settings.
	 *
	 * @since 2.0.0
	 */
	class Settings_Page_Policies {
		/**
		 * Renders the settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		public static function render() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$user = wp_get_current_user();
			if ( ! empty( WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) ) ) {
				$main_user = (int) WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' );
			} else {
				$main_user = get_current_user_id();
			}

			/**
			 * Used from user settings controller.
			 *
			 * @param bool - Default at this point is false - no user settings.
			 *
			 * @since 2.4.0
			 */
			$roles_controller = \apply_filters( WP_2FA_PREFIX . 'roles_controller_exists', false );
			if ( $roles_controller ) {
				$roles = WP_Helper::get_roles();

				foreach ( $roles as $role ) {
					self::new_page_created( $role );
				}
			} else {
				self::new_page_created();
			}

			$enabled_methods = User_Helper::get_enabled_method_for_user( $user );

			if ( empty( $enabled_methods ) ) {
				$new_page_modal_content  = '<h3>' . \esc_html__( 'Exclude yourself?', 'wp-2fa' ) . '</h3>';
				$new_page_modal_content .= '</p>' . \esc_html__( 'You are about to enforce 2FA instantly on all users, including yourself, however you have not yet configured your own 2FA method. What would you like to do?', 'wp-2fa' ) . '</p>';

				echo Generate_Modal::generate_modal( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'exclude-self-from-instant-2fa',
					false,
					$new_page_modal_content, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'<a href="#" class="wp-2fa-button-secondary button-secondary" data-close-2fa-modal>' . __( 'Continue anyway', 'wp-2fa' ) . '</a>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'<a href="#" class="wp-2fa-button-primary button-primary" data-close-2fa-modal data-user-login-name="' . \esc_attr( $user->user_login ) . '">' . __( 'Exclude myself from 2FA policies', 'wp-2fa' ) . '</a>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					),
					false,
					'560px'
				);
			}
			?>

			<div class="wrap wp-2fa-settings-wrapper wp2fa-form-styles">
				<h2><?php \esc_html_e( 'WP 2FA Settings', 'wp-2fa' ); ?></h2>
				<hr>
				<?php if ( ! empty( WP2FA::get_wp2fa_general_setting( 'limit_access' ) ) && $main_user !== $user->ID ) { ?>
					<?php
					echo \esc_html__( 'These settings have been disabled by your site administrator, please contact them for further assistance.', 'wp-2fa' );
					?>
				<?php } else { ?>
					<?php
						/**
						 * Fires before the plugin settings rendering.
						 *
						 * @since 2.0.0
						 */
						do_action( WP_2FA_PREFIX . 'before_plugin_settings' );
					?>
						<?php
						if ( WP_Helper::is_multisite() ) {
							$action = 'edit.php?action=update_wp2fa_network_options';
						} else {
							$action = 'options.php';
						}
						if (! isset($_REQUEST['tab']) || isset($_REQUEST['tab']) && '2fa-settings' === $_REQUEST['tab']) { // phpcs:ignore
							?>
						<br/>
							<?php
							printf(
								'<p class="description">%1$s <a href="mailto:support@melapress.com">%2$s</a></p>',
								\esc_html__( 'Use the settings below to configure the properties of the two-factor authentication on your website and how users use it. If you have any questions send us an email at', 'wp-2fa' ),
								\esc_html__( 'support@melapress.com', 'wp-2fa' )
							);
							?>
						<br/>
							<?php $total_users = count_users(); ?>
						<form id="wp-2fa-admin-settings" action='<?php echo \esc_attr( $action ); ?>' method='post' autocomplete="off" data-2fa-total-users="<?php echo \esc_attr( $total_users['total_users'] ); ?>">
							<?php
								settings_fields( WP_2FA_POLICY_SETTINGS_NAME );
							self::select_method_setting();
							self::select_enforcement_policy_setting();
							self::excluded_roles_or_users_setting();
							if ( WP_Helper::is_multisite() ) {
								self::excluded_network_sites();
							}

							/**
							 * Fires before grace period HTML rendering settings.
							 *
							 * @since 2.0.0
							 */
							do_action( WP_2FA_PREFIX . 'before_grace_period_settings' );

							self::grace_period_setting();
							self::user_redirect_after_wizard();

							/**
							 * Fires before user profile period HTML rendering settings.
							 *
							 * @since 2.0.0
							 */
							do_action( WP_2FA_PREFIX . 'before_user_profile_settings' );
							self::user_profile_settings();
							self::disable_2fa_removal_setting();
							submit_button();
							?>
						</form>
					<?php } ?>
				<?php } ?>
			</div>
			<?php
		}

		/**
		 * Creates new page for settings (FE only).
		 *
		 * @param string $role - The name of the role, empty for global.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function new_page_created( $role = '' ) {
			$role = ( is_null( $role ) || empty( $role ) || 'global' === $role ) ? '' : $role;
			// Check if new user page has been published.
			if ( ! empty( get_transient( WP_2FA_PREFIX . 'new_custom_page_created' . $role ) ) ) {
				\delete_transient( WP_2FA_PREFIX . 'new_custom_page_created' . $role );
				$new_page_id = Settings::get_role_or_default_setting( 'custom-user-page-id', '', $role );
				if ( empty( $new_page_id ) ) {
					$new_page_id = Settings::get_custom_settings_page_id( $role );
				}

				if ( $new_page_id > 0 ) {
					$new_page_permalink = get_permalink( $new_page_id );

					$new_page_modal_content  = '<h3>' . \esc_html__( 'The plugin created the 2FA settings page with the URL:', 'wp-2fa' ) . '</h3>';
					$new_page_modal_content .= '<h4><a target="_blank" href="' . \esc_url( $new_page_permalink ) . '">' . \esc_url( $new_page_permalink ) . '</a></h4>';
					$new_page_modal_content .= '<p>' . \esc_html__( 'You can edit this page using the page editor, like you do with all other pages.', 'wp-2fa' );
					$new_page_modal_content .= '</p>';
					$new_page_modal_content .= sprintf(
					/* translators: %s: tag name. */
						\esc_html__( 'Use the %s html tag in the email templates to include the URL of the 2FA configuration page when notifying the users to configure two-factor authentication.', 'wp-2fa' ),
						'<strong>{2fa_settings_page_url}</strong>'
					);
					$new_page_modal_content .= '</p>';

					echo Generate_Modal::generate_modal( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'new-page-created' . $role, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						false,
						$new_page_modal_content, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'<a href="#" class="wp-2fa-button-primary button-primary" data-close-2fa-modal>' . __( 'OK', 'wp-2fa' ) . '</a>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						),
						true,
						'560px'
					);
				}
			}
		}

		/**
		 * Validate options before saving.
		 *
		 * @param array $input The settings array.
		 *
		 * @return array|void
		 *
		 * @since 2.0.0
		 */
		public static function validate_and_sanitize( $input ) {
			Debugging::log( 'The following settings will be processed (Policy): ' . "\n" . wp_json_encode( $input ) );

			/*
			 * Adds the ability to check the referer and act accordingly.
			*
			* @since 2.0.0
			*/
			\do_action( WP_2FA_PREFIX . 'change_referer' );

			// Bail if user doesn't have permissions to be here.
			if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['action'] ) && ! check_admin_referer( 'wp2fa-step-choose-method' ) ) {
				return;
			}

			$no_method_enabled = false;
			if ( ! isset( $input[ TOTP::POLICY_SETTINGS_NAME ] ) && ! isset( $input[ Email::POLICY_SETTINGS_NAME ] ) && ! isset( $_POST['save_step'] ) ) {
				/**
				 * At this point, none of the default providers is set / activated. This filter allows additional providers to change the behavior. Checking the input array for specific values (methods), and based on that we can raise error that none of the allowed methods has bees selected by the user, or dismiss the error otherwise.
				 *
				 * @param bool - Default at this point is true - no method is selected.
				 * @param array $input - The input array with all the data.
				 *
				 * @since 2.0.0
				 */
				$no_methods_set = \apply_filters( WP_2FA_PREFIX . 'save_additional_enabled_methods', true, $input );

				if ( $no_methods_set ) {
					add_settings_error(
						WP_2FA_POLICY_SETTINGS_NAME,
						\esc_attr( 'enable_email_settings_error' ),
						\esc_html__( 'No global 2FA methods enabled.', 'wp-2fa' ),
						'error'
					);
					$no_method_enabled = true;
				}
			}

			$simple_settings_we_can_loop = array(
				'grace-policy',
				'enable_destroy_session',
				'2fa_settings_last_updated_by',
				'limit_access',
				'hide_remove_button',
				'redirect-user-custom-page',
				'redirect-user-custom-page-global',
				'superadmins-role-add',
				'superadmins-role-exclude',
				'separate-multisite-page-url',
			);

			/**
			 * Gives the ability to filter the settings array of the plugin.
			 *
			 * @param array $settings - The array with all the default settings.
			 *
			 * @since 2.0.0
			 */
			$simple_settings_we_can_loop = \apply_filters( WP_2FA_PREFIX . 'loop_settings', $simple_settings_we_can_loop );

			$settings_to_turn_into_bools = array(
				'enable_destroy_session',
				'limit_access',
				'hide_remove_button',
			);

			$settings_to_turn_into_array = array(
				'enforced_roles',
				'enforced_users',
				'excluded_users',
				'excluded_roles',
				'excluded_sites',
			);

			foreach ( $simple_settings_we_can_loop as $simple_setting ) {
				if ( ! in_array( $simple_setting, $settings_to_turn_into_bools, true ) ) {
					// Is item is not one of our possible settings we want to turn into a bool, process.
					$output[ $simple_setting ] = ( isset( $input[ $simple_setting ] ) && ! empty( $input[ $simple_setting ] ) ) ? trim( (string) sanitize_text_field( $input[ $simple_setting ] ) ) : false;
				} else {
					// This item is one we treat as a bool, so process correctly.
					$output[ $simple_setting ] = ( isset( $input[ $simple_setting ] ) && ! empty( $input[ $simple_setting ] ) ) ? true : false;
				}
			}

			if ( $no_method_enabled ) {
				/**
				 * No methods are enabled - return the previous selection. Gives the ability for external providers to set the default values.
				 *
				 * @param array $output - The output array with all the data we will store in the settings.
				 *
				 * @since 2.0.0
				 */
				$output = \apply_filters( WP_2FA_PREFIX . 'no_method_enabled', $output );
			}

			$output['included_sites'] = array();
			if ( WP_Helper::is_multisite() ) {
				if ( isset( $input['included_sites'] ) && is_array( $input['included_sites'] ) && ! empty( $input['included_sites'] ) ) {
					foreach ( $input['included_sites'] as &$site ) {
						if ( ! filter_var( $site, FILTER_VALIDATE_INT ) ) {
							unset( $site );

							continue;
						}

						$output['included_sites'][] = $site;
					}
					unset( $site );
				} elseif ( isset( $input['enforcement-policy'] ) && 'enforce-on-multisite' === $input['enforcement-policy'] && empty( $input['included_sites'] ) ) {

					add_settings_error(
						WP_2FA_POLICY_SETTINGS_NAME,
						\esc_attr( 'included_sites_settings_error' ),
						\esc_html__( 'You must specify at least one sub-site', 'wp-2fa' ),
						'error'
					);

				}
			}

			foreach ( $settings_to_turn_into_array as $setting ) {
				if ( isset( $input[ $setting ] ) ) {
					$output[ $setting ] = $input[ $setting ];
				} else {
					$output[ $setting ] = array();
				}
			}

			if ( isset( $input['grace-period'] ) ) {
				if ( 0 === (int) $input['grace-period'] ) {
					add_settings_error(
						WP_2FA_POLICY_SETTINGS_NAME,
						\esc_attr( 'grace_settings_error' ),
						\esc_html__( 'Grace period must be at least 1 day/hour', 'wp-2fa' ),
						'error'
					);
					$output['grace-period'] = 1;
				} else {
					$output['grace-period'] = (int) $input['grace-period'];
				}
			}


			if ( isset( $input['grace-period-denominator'] ) && 'days' === $input['grace-period-denominator'] || isset( $input['grace-period-denominator'] ) && 'hours' === $input['grace-period-denominator'] || isset( $input['grace-period-denominator'] ) && 'seconds' === $input['grace-period-denominator'] ) {
				$output['grace-period-denominator'] = sanitize_text_field( $input['grace-period-denominator'] );
			}

			if ( ( isset( $input['create-custom-user-page'] ) && 'yes' === $input['create-custom-user-page'] ) || ( isset( $input['create-custom-user-page'] ) && 'no' === $input['create-custom-user-page'] ) ) {
				$output['create-custom-user-page'] = sanitize_text_field( $input['create-custom-user-page'] );
			}

			if ( ( isset( $input['create-custom-user-page'] ) && 'yes' === $input['create-custom-user-page'] ) && isset( $input['custom-user-page-url'] ) && ! empty( $input['custom-user-page-url'] ) ) {
				if ( WP2FA::get_wp2fa_setting( 'custom-user-page-url' ) !== $input['custom-user-page-url'] ) {

					if ( 'yes' === $input['create-custom-user-page'] && ! empty( $input['custom-user-page-url'] ) ) {
						$output['custom-user-page-url'] = sanitize_title_with_dashes( $input['custom-user-page-url'] );
						if ( WP_Helper::is_multisite() && isset( $input['separate-multisite-page-url'] ) ) {
							$sites = WP_Helper::get_multi_sites();

							foreach ( $sites as $site ) {
								$blog_id = $site->id;

								\switch_to_blog( $blog_id );

								self::generate_custom_user_profile_page( $output['custom-user-page-url'] );

								\restore_current_blog();
							}
						} else {
							self::generate_custom_user_profile_page( $output['custom-user-page-url'] );
						}
					}
				} else {
					$output['custom-user-page-url'] = sanitize_title_with_dashes( $input['custom-user-page-url'] );
					$output['custom-user-page-id']  = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
					if ( is_null( get_post( $output['custom-user-page-id'] ) ) ) {
						$create_page                   = self::generate_custom_user_profile_page( $output['custom-user-page-url'] );
						$output['custom-user-page-id'] = (int) $create_page;
					}
				}
			}

			if ( isset( $_REQUEST['page'] ) && 'wp-2fa-setup' !== $_REQUEST['page'] || isset( $_REQUEST[ WP_2FA_POLICY_SETTINGS_NAME ]['create-custom-user-page'] ) ) {
				if ( isset( $input['create-custom-user-page'] ) && 'no' === $input['create-custom-user-page'] ) {
					$output['custom-user-page-url']        = '';
					$output['custom-user-page-id']         = '';
					$output['separate-multisite-page-url'] = '';
					\wp_delete_post( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ), true );
				}
			}

			if ( isset( $input['create-custom-user-page'] ) && 'yes' === $input['create-custom-user-page'] && empty( $input['custom-user-page-url'] ) ) {
				add_settings_error(
					WP_2FA_POLICY_SETTINGS_NAME,
					\esc_attr( 'no_page_slug_provided' ),
					\esc_html__( 'You must provide a new page slug.', 'wp-2fa' ),
					'error'
				);
			}

			if ( isset( $input['grace-period'] ) && isset( $input['grace-period-denominator'] ) ) {
				// Turn inputs into a useable string.
				$create_a_string = $output['grace-period'] . ' ' . $output['grace-period-denominator'];
				// Turn that string into a time.
				$grace_expiry                       = strtotime( $create_a_string );
				$output['grace-period-expiry-time'] = sanitize_text_field( $grace_expiry );
			}

			// Process main policy.
			if ( isset( $input['enforcement-policy'] ) && in_array( $input['enforcement-policy'], array( 'all-users', 'certain-users-only', 'certain-roles-only', 'do-not-enforce', 'superadmins-only', 'superadmins-siteadmins-only', 'enforce-on-multisite' ), true ) ) {
				// Clear enforced roles/users if setting has changed.
				if ( 'all-users' === $input['enforcement-policy'] || 'do-not-enforce' === $input['enforcement-policy'] ) {
					$input['enforced_users']        = array();
					$input['enforced_roles']        = array();
					$output['enforced_users']       = array();
					$output['enforced_roles']       = array();
					$output['superadmins-role-add'] = 'no';
				}

				$output['enforcement-policy'] = sanitize_text_field( $input['enforcement-policy'] );

				if ( 'certain-roles-only' === $input['enforcement-policy'] && empty( $input['enforced_roles'] ) && empty( $input['enforced_users'] ) ) {
					add_settings_error(
						WP_2FA_POLICY_SETTINGS_NAME,
						\esc_attr( 'enforced_roles_settings_error' ),
						\esc_html__( 'You must specify at least one role or user', 'wp-2fa' ),
						'error'
					);

					$output['enforcement-policy'] = 'do-not-enforce';
				}

				// If any users are being excluded, delete any wp 2fa data.
				if ( isset( $output['excluded_users'] ) &&
				! empty( array_diff( (array) WP2FA::get_wp2fa_setting( 'excluded_users' ), (array) $output['excluded_users'] ) ) ) {
					// Wipe user 2fa data.
					$user_array = $output['excluded_users'];
					foreach ( $user_array as $user ) {
						if ( ! empty( $user ) ) {
							$user_to_wipe = get_user_by( 'login', $user );
							global $wpdb;
							// @codingStandardsIgnoreStart
							$wpdb->query(
								$wpdb->prepare(
									"
								DELETE FROM $wpdb->usermeta
								WHERE user_id = %d
								AND meta_key LIKE %s
								",
									array(
										$user_to_wipe->ID,
										'wp_2fa_%',
									)
								)
							);
							// @codingStandardsIgnoreEnd
						}
					}
				}
			}

			/**
			 * Allow extensions and 3rd party developers to run extra validation of the output array.
			 *
			 * @param array - Array with all the collected data.
			 */
			do_action( WP_2FA_PREFIX . 'run_extra_settings_validation', $output );

			/**
			 * Filter the values we are about to store in the plugin settings.
			 *
			 * @param array $output - The output array with all the data we will store in the settings.
			 * @param array $input  - The input array with all the data we received from the user.
			 *
			 * @since 2.0.0
			 */
			$output = \apply_filters( WP_2FA_PREFIX . 'filter_output_content', $output, $input );

			// Remove duplicates from settings errors. We do this as this sanitization callback is actually fired twice, so we end up with duplicates when saving the settings for the FIRST TIME only. The issue is not present once the settings are in the DB as the sanitization wont fire again. For details on this core issue - https://core.trac.wordpress.org/ticket/21989.
			global $wp_settings_errors;
			if ( isset( $wp_settings_errors ) ) {
				$errors             = array_map( 'unserialize', array_unique( array_map( 'serialize', $wp_settings_errors ) ) );
				$wp_settings_errors = $errors; // phpcs:ignore
			}

			/**
			 * Allow extensions and 3rd party developers to change or check the settings array.
			 *
			 * @param array - Array with all the collected and validated data.
			 *
			 * @since 2.6.0
			 */
			do_action( WP_2FA_PREFIX . 'before_settings_save', $output );

			// WordPress saves the option to the database, but we still need to do some work when the settings are saved.
			WP2FA::update_plugin_settings( $output, true );

			Debugging::log( 'The following settings are being saved (Policy): ' . "\n" . wp_json_encode( $output ) );

			// We have overridden any defaults by now so can clear this.
			Settings_Utils::delete_option( WP_2FA_PREFIX . 'default_settings_applied' );
			Settings_Utils::delete_option( 'wizard_not_finished' );

			/**
			 * Notify the extensions and 3rd party developers that the settings array is saved.
			 *
			 * @param array - Array with all the stored settings.
			 *
			 * @since 2.6.0
			 */
			do_action( WP_2FA_PREFIX . 'after_settings_save', Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() ) );

			return $output;
		}

		/**
		 * Updates global policy network options.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 *
		 * @SuppressWarnings(PHPMD.ExitExpressions)
		 */
		public static function update_wp2fa_network_options() {
			if ( isset( $_POST[ WP_2FA_POLICY_SETTINGS_NAME ] ) ) {
				check_admin_referer( 'wp_2fa_policy-options' );
				$options = self::validate_and_sanitize(wp_unslash($_POST[WP_2FA_POLICY_SETTINGS_NAME])); // phpcs:ignore
				$settings_errors = get_settings_errors( WP_2FA_POLICY_SETTINGS_NAME );
				if ( ! empty( $settings_errors ) ) {
					// redirect back to our options page.
					wp_safe_redirect(
						add_query_arg(
							array(
								'page' => Settings_Page::TOP_MENU_SLUG,
								'wp_2fa_network_settings_error' => urlencode_deep( $settings_errors[0]['message'] ),
							),
							network_admin_url( 'admin.php' )
						)
					);
					exit;
				}
				WP2FA::update_plugin_settings( $options );

				// redirect back to our options page.
				wp_safe_redirect(
					add_query_arg(
						array(
							'page' => Settings_Page::TOP_MENU_SLUG,
							'wp_2fa_network_settings_updated' => 'true',
						),
						network_admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}

		/**
		 * Creates a new page with our shortcode present.
		 *
		 * @param string $page_slug - The page slug.
		 * @param string $role      - The name of the role for which the page has been created.
		 *
		 * @return mixed
		 *
		 * @since 2.0.0
		 */
		public static function generate_custom_user_profile_page( $page_slug, string $role = '' ) {
			// Check if a page with slug exists.
			$page_exists = self::get_post_by_post_name( $page_slug, 'page' );
			if ( $page_exists ) {
				// Seeing as the page exists, return its ID.
				return $page_exists->ID;
			}

			$generated_by_message  = '<p>' . \esc_html__( 'Page generated by', 'wp-2fa' );
			$generated_by_message .= ' <a href="https://melapress.com/wordpress-2fa/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" target="_blank">' . \esc_html__( 'WP 2FA Plugin', 'wp-2fa' ) . '</a>';
			$generated_by_message .= '</p>';

			$user      = wp_get_current_user();
			$post_data = array(
				'post_title'   => 'WP 2FA User Profile',
				'post_name'    => $page_slug,
				'post_content' => '[wp-2fa-setup-form] ' . $generated_by_message,
				'post_status'  => 'publish',
				'post_author'  => $user->ID,
				'post_type'    => 'page',
			);

			// Lets insert the post now.
			$result = wp_insert_post( $post_data );

			if ( $result && ! is_wp_error( $result ) ) {
				$post_id = $result;
				set_transient( WP_2FA_PREFIX . 'new_custom_page_created' . $role, true, 60 );
				set_site_transient( WP_2FA_PREFIX . 'new_custom_page_created' . $role, true, 60 );

				return $post_id;
			}

			return $result;
		}

		/**
		 * Check if page with slug exists.
		 *
		 * @param string $slug      - The post slug.
		 * @param string $post_type - Post type.
		 *
		 * @return \WP_Post|bool
		 *
		 * @since 2.0.0
		 */
		public static function get_post_by_post_name( $slug = '', $post_type = '' ) {
			if ( ! $slug || ! $post_type ) {
				return false;
			}

			$post_object = get_page_by_path( $slug, OBJECT, $post_type );

			if ( ! $post_object ) {
				return false;
			}

			return $post_object;
		}

		/**
		 * General settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function select_method_setting() {
			First_Time_Wizard_Steps::select_method( false );
		}

		/**
		 * Policy settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function select_enforcement_policy_setting() {
			First_Time_Wizard_Steps::enforcement_policy( false );
		}

		/**
		 * User profile settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function user_profile_settings() {
			ob_start();
			$create_page = WP2FA::get_wp2fa_setting( 'create-custom-user-page' );
			?>
		<h3><?php \esc_html_e( 'Can users access the WordPress dashboard or you have custom profile pages? ', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php \esc_html_e( 'If your users do not have access to the WordPress dashboard (because you use custom user profile pages) enable this option. Once enabled, the plugin creates a page which ONLY authenticated users can access to configure their user 2FA settings. A link to this page is sent in the 2FA welcome email.', 'wp-2fa' ); ?></a>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="use_custom_page"><?php \esc_html_e( 'Frontend 2FA settings page', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<label class="radio-inline">
								<input id="use_custom_page" type="radio" name="wp_2fa_policy[create-custom-user-page]" value="yes"
								<?php checked( $create_page, 'yes' ); ?>
								>
								<?php \esc_html_e( 'Yes', 'wp-2fa' ); ?>
							</label>
							<label class="radio-inline">
								<input id="dont_use_custom_page" type="radio" name="wp_2fa_policy[create-custom-user-page]" value="no"
								<?php checked( $create_page, 'no' ); ?>
								<?php checked( $create_page, '' ); ?>
								>
								<?php \esc_html_e( 'No', 'wp-2fa' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr class="custom-user-page-setting<?php echo ( 'yes' !== $create_page ) ? ' disabled' : ''; ?>">
					<th><label for="custom-user-page-url"><?php \esc_html_e( 'Frontend 2FA settings page URL', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<?php
							if ( ! empty( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
								$custom_slug = \get_post_field( 'post_name', \get_post( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) );
							} else {
								$custom_slug = WP2FA::get_wp2fa_setting( 'custom-user-page-url' );
							}

							$has_error       = false;
							$settings_errors = \get_settings_errors( WP_2FA_SETTINGS_NAME );
							if ( ! empty( $settings_errors ) ) {
								foreach ( $settings_errors as $error ) {
									if ( 'no_page_slug_provided' === $error['code'] ) {
										$has_error = true;

										break;
									}
								}
							}

							?>
							<?php echo \esc_html( trailingslashit( get_site_url() ) ); ?>
							<input type="text" id="custom-user-page-url" name="wp_2fa_policy[custom-user-page-url]" value="<?php echo \esc_attr( sanitize_text_field( $custom_slug ) ); ?>"
							<?php echo ( $has_error ) ? ' class="error"' : ''; ?>>
						</fieldset>
							<?php
							if ( ! empty( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) && ! WP_Helper::is_multisite() ) ) {
								$edit_post_link = \get_edit_post_link( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) );
								$view_post_link = \get_permalink( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) );
								?>
							<br>
							<a href="<?php echo \esc_url( $edit_post_link ); ?>" target="_blank" class="button button-secondary" style="margin-right: 5px;"><?php \esc_html_e( 'Edit Page', 'wp-2fa' ); ?></a> <a href="<?php echo \esc_url( $view_post_link ); ?>" target="_blank" class="button button-primary"><?php \esc_html_e( 'View Page', 'wp-2fa' ); ?></a>
								<?php
							}
							?>
					</td>
				</tr>
				<?php if ( WP_Helper::is_multisite() ) { ?>
					<tr class="custom-user-page-setting<?php echo ( 'yes' !== $create_page ) ? ' disabled' : ''; ?>">
						<th><label for="separate-multisite-page-url"><?php \esc_html_e( 'Create separate pages on multisite network', 'wp-2fa' ); ?></label></th>
						<td>
							<?php
								$separate_multisite_page = WP2FA::get_wp2fa_setting( 'separate-multisite-page-url' );
							?>
							<fieldset>
								<input type="checkbox" name="wp_2fa_policy[<?php echo \esc_attr( 'separate-multisite-page-url' ); ?>]" 
								id="separate-multisite-page-url" 
								value="separate-multisite-page-url" <?php checked( $separate_multisite_page, 'separate-multisite-page-url' ); ?> class="js-nested">
								<label for="separate-multisite-page-url"><?php echo \esc_html__( 'Create User settings page separately for every site', 'wp-2fa' ); ?></label>
								<p class="description"><?php echo \esc_html__( 'When you enable this setting a page with the same slug is created on each site on the network, so the users of each sub site can use this page on their website to configure 2FA.', 'wp-2fa' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<?php } ?>
				<tr class="custom-user-page-setting<?php echo ( 'yes' !== $create_page ) ? ' disabled' : ''; ?>">
					<th colspan="2"><p class="description"><?php \esc_html_e( 'Specify the page where you want to redirect your users to after they complete the 2FA setup. This will override the global redirect setting.', 'wp-2fa' ); ?></p></th>
				</tr>
				<tr class="custom-user-page-setting<?php echo ( 'yes' !== $create_page ) ? ' disabled' : ''; ?>">
					<th><label for="redirect-user-custom-page"><?php \esc_html_e( 'Redirect users after 2FA setup', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<?php
							$custom_slug = WP2FA::get_wp2fa_setting( 'redirect-user-custom-page' );
							?>
							<?php echo \esc_html( trailingslashit( get_site_url() ) ); ?>
							<input type="text" id="redirect-user-custom-page" name="wp_2fa_policy[redirect-user-custom-page]" value="<?php echo \esc_attr( sanitize_text_field( $custom_slug ) ); ?>">
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
			<?php
			$output = ob_get_clean();

			/**
			 * Gives the ability to manipulate the output.
			 *
			 * @param string $output - Parsed HTML with the methods.
			 *
			 * @since 2.0.0
			 */
			$output = \apply_filters( WP_2FA_PREFIX . 'user_profile_settings', $output );

			echo $output; // phpcs:ignore
		}

		/**
		 * User profile settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function user_redirect_after_wizard() {
			ob_start();
			?>
		<h3><?php \esc_html_e( 'Do you want to redirect the user to a specific page after completing the 2FA setup wizard', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php \esc_html_e( 'Specify a URL of a page where you want to redirect the users once they complete the 2FA setup wizard. Leave empty for default behaviour, in which users are redirected back to the page from where they launched the wizard.', 'wp-2fa' ); ?></a>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="redirect-user-custom-page-global"><?php \esc_html_e( 'Redirect users after 2FA setup to', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<?php echo \esc_html( trailingslashit( get_site_url() ) ); ?>
							<input type="text" id="redirect-user-custom-page-global" name="wp_2fa_policy[redirect-user-custom-page-global]" value="<?php echo \esc_attr( sanitize_text_field( WP2FA::get_wp2fa_setting( 'redirect-user-custom-page-global' ) ) ); ?>">
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
			<?php
			$output = ob_get_clean();

			/**
			 * Gives the ability to manipulate the output.
			 *
			 * @param string $output - Parsed HTML with the methods.
			 *
			 * @since 2.0.0
			 */
			$output = \apply_filters( WP_2FA_PREFIX . 'redirect_after', $output );

			echo $output; // phpcs:ignore
		}

		/**
		 * Role and users exclusion settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function excluded_roles_or_users_setting() {
			?>
		<div id="exclusion_settings_wrapper">
			<?php First_Time_Wizard_Steps::exclude_users(); ?>
		</div>
			<?php
		}

		/**
		 * Role and users exclusion settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function excluded_network_sites() {
			First_Time_Wizard_Steps::excluded_network_sites();
		}

		/**
		 * Grace period settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function grace_period_setting() {
			ob_start();

			/**
			 * Fires after the grace period. Gives the ability to change the parsed code.
			 *
			 * @param string $content - HTML content.
			 * @param string $role - The name of the role.
			 * @param string $name_prefix - Name prefix for the input name, includes the role name if provided.
			 * @param string $data_role - Data attribute - used by the JS.
			 * @param string $role_id - The role name, used to identify the inputs.
			 *
			 * @since 2.0.0
			 */
			echo \apply_filters( WP_2FA_PREFIX . 'before_grace_period_settings', '', '', 'wp_2fa_policy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			?>
		<br>
		<h3><?php \esc_html_e( 'Should users be asked to setup 2FA instantly or should they have a grace period?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php \esc_html_e( 'When you enforce 2FA on users they have a grace period to configure 2FA. If they fail to configure it within the configured stipulated time, their account will be locked and have to be unlocked manually. Note that user accounts cannot be unlocked automatically, even if you change the settings. As a security precaution they always have to be unlocked them manually. Maximum grace period is 10 days.', 'wp-2fa' ); ?> <a href="https://melapress.com/support/kb/configure-grace-period-2fa/?utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" target="_blank"><?php \esc_html_e( 'Learn more.', 'wp-2fa' ); ?></a>
		</p>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="grace-policy"><?php \esc_html_e( 'Grace period', 'wp-2fa' ); ?></label></th>
					<td>
					<?php First_Time_Wizard_Steps::grace_period( true ); ?>
					</td>
				</tr>
			</tbody>
		</table>
			<?php
			$output = ob_get_clean();

			/**
			 * Gives the ability to manipulate the output.
			 *
			 * @param string $output - Parsed HTML with the methods.
			 *
			 * @since 2.0.0
			 */
			$output = \apply_filters( WP_2FA_PREFIX . 'grace_period', $output );

			echo $output; // phpcs:ignore
		}

		/**
		 * Disable removal of 2FA settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function disable_2fa_removal_setting() {
			ob_start();
			?>
		<br>
		<h3><?php \esc_html_e( 'Should users be allowed to disable 2FA from their user profile?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php \esc_html_e( 'Users can configure and also disable 2FA on their profile by clicking the "Remove 2FA" button. Enable this setting to disable the Remove 2FA button so users cannot disable 2FA from their user profile.', 'wp-2fa' ); ?>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="hide-remove-2fa"><?php \esc_html_e( 'Hide the Remove 2FA button', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<input type="checkbox" id="hide-remove-2fa" name="wp_2fa_policy[hide_remove_button]" value="hide_remove_button"
							<?php checked( 1, WP2FA::get_wp2fa_setting( 'hide_remove_button' ), true ); ?>
							>
							<?php \esc_html_e( 'Hide the Remove 2FA button on user profile pages', 'wp-2fa' ); ?>
						</fieldset>
					</td>
				</tr>

			</tbody>
		</table>
			<?php
			$output = ob_get_clean();

			/**
			 * Gives the ability to manipulate the output.
			 *
			 * @param string $output - Parsed HTML with the methods.
			 *
			 * @since 2.0.0
			 */
			$output = \apply_filters( WP_2FA_PREFIX . 'disable_2fa', $output );

			echo $output; // phpcs:ignore
		}
	}
}
