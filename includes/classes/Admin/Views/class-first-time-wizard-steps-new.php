<?php
/**
 * First Time Wizard Steps (New Design) – renders each wizard step
 * using the Settings_Builder component system.
 *
 * @package    wp2fa
 * @subpackage views
 * @since      4.0.0
 */

namespace WP2FA\Admin\Views;

use WP2FA\WP2FA;
use WP2FA\Methods\TOTP;
use WP2FA\Methods\Email;
use WP2FA\Methods\Backup_Codes;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Helpers\Methods_Helper;
use WP2FA\Admin\Views\Grace_Period_Notifications;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP2FA\Admin\Views\First_Time_Wizard_Steps_New' ) ) {
	/**
	 * Static helper that outputs each wizard step's form fields
	 * using the new Settings_Builder components.
	 *
	 * @since 4.0.0
	 */
	class First_Time_Wizard_Steps_New {

		/**
		 * Name prefix used in all form fields so they are collected
		 * under a single $_POST key.
		 */
		private const NAME_PREFIX = WP_2FA_POLICY_SETTINGS_NAME;

		/*
		==============================================================
		 * STEP 1 – 2FA Methods
		 * ==============================================================
		 */

		/**
		 * Render the primary 2FA methods step.
		 *
		 * @return void
		 */
		public static function step_methods() {
			$totp_enabled  = ! empty( Settings_Utils::get_setting_role( null, TOTP::POLICY_SETTINGS_NAME, true ) );
			$email_enabled = ! empty( Settings_Utils::get_setting_role( null, Email::POLICY_SETTINGS_NAME, true ) );

			// Build primary methods sortable list via the same filter used in policies.
			$all_methods = array();
			$all_methods = \apply_filters( WP_2FA_PREFIX . 'methods_policy_settings_new', $all_methods, null );

			$methods_order = Settings_Utils::get_setting_role( null, Methods_Helper::POLICY_SETTINGS_NAME, true );
			if ( \is_array( $methods_order ) && ! empty( $methods_order ) ) {
				$ordered = array();
				foreach ( $methods_order as $slug ) {
					if ( isset( $all_methods[ $slug ] ) ) {
						$ordered[ $slug ] = $all_methods[ $slug ];
					}
				}
				foreach ( $all_methods as $slug => $m ) {
					if ( ! isset( $ordered[ $slug ] ) ) {
						$ordered[ $slug ] = $m;
					}
				}
				$all_methods = $ordered;
			}
			?>
			<h3><?php \esc_html_e( 'Which 2FA methods can your users use?', 'wp-2fa' ); ?></h3>
			<!-- <p class="description">
				<?php // \esc_html_e( 'Allowing users to set up a secondary 2FA method is highly recommended. You can do this in the next step of the wizard. This will allow users to log in using an alternative method should they, for example lose access to their phone.', 'wp-2fa' ); ?>
			</p> -->

			<?php
			$all_methods = null;
			if ( ! empty( $all_methods ) ) {
				$sortable_items = array();
				foreach ( $all_methods as $method ) {
					$item = array(
						'id'            => $method['setting'],
						'title'         => $method['title'],
						'order_id'      => $method['id'],
						'checkbox_name' => self::NAME_PREFIX . '[' . $method['setting'] . ']',
						'checked'       => ! empty( $method['enabled'] ),
					);
					if ( ! empty( $method['extra_settings'] ) ) {
						$item['extra_settings'] = $method['extra_settings'];
					}
					$sortable_items[] = $item;
				}

				Settings_Builder::build_option(
					array(
						'id'          => 'wizard-methods-order',
						'type'        => 'sortable-list',
						'items'       => $sortable_items,
						'name_prefix' => self::NAME_PREFIX . '[methods_order]',
					)
				);
			} else {
				// Fallback: render simple checkboxes for TOTP and Email.
				?>
					<div class="wizard-methods-list">
						<div class="wizard-method-card">
							<!-- Hidden input ensures unchecked box submits empty value -->
							<input type="hidden" name="<?php echo esc_attr( self::NAME_PREFIX . '[' . TOTP::POLICY_SETTINGS_NAME . ']' ); ?>" value="">
							<?php
							Settings_Builder::build_option(
								array(
									'id'          => 'wizard-totp',
									'type'        => 'checkbox',
									'option_name' => self::NAME_PREFIX . '[' . TOTP::POLICY_SETTINGS_NAME . ']',
									'default'     => $totp_enabled ? TOTP::POLICY_SETTINGS_NAME : '',
									'text'        => '<strong>' . \esc_html__( 'One-time code via 2FA app', 'wp-2fa' ). '</strong>'/*. ' - <a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=totp_aplications_help" target="_blank" rel="noopener">' . \esc_html__( 'complete list of supported 2FA apps.', 'wp-2fa' ) . '</a>'*/,
									'not_bool'    => true,
									'not_id'      => true,
									'value'       => TOTP::POLICY_SETTINGS_NAME,
								)
							);
							?>
							<p class="wizard-method-desc">
							<?php
								echo \wp_sprintf(
									/* translators: link to the knowledge base website */
									\esc_html__( 'When using this method, users will need to configure a 2FA app to get the one-time login code. The plugin supports all standard 2FA apps. Refer to the %s for more information. Allowing users to configure a secondary 2FA method is highly recommended. You can configure this in the next step of the wizard. This allows users to log in using an alternative method if they lose access to their primary 2FA device, such as their phone.', 'wp-2fa' ),
									'<a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_how_to_setup_2fa_apps" target="_blank">' . \esc_html__( 'guide on how to set up 2FA apps', 'wp-2fa' ) . '</a>'
								);
							?>
							</p>
						</div>

						<div class="wizard-method-card">
							<!-- Hidden input ensures unchecked box submits empty value -->
							<input type="hidden" name="<?php echo \esc_attr( self::NAME_PREFIX . '[' . Email::POLICY_SETTINGS_NAME . ']' ); ?>" value="">
							<?php
							Settings_Builder::build_option(
								array(
									'id'          => 'wizard-email',
									'type'        => 'checkbox',
									'option_name' => self::NAME_PREFIX . '[' . Email::POLICY_SETTINGS_NAME . ']',
									'default'     => $email_enabled ? Email::POLICY_SETTINGS_NAME : '',
									'text'        => '<strong>' . \esc_html__( 'One-time code via email', 'wp-2fa' ) . '</strong>- '  . \esc_html__( 'ensure email deliverability with the free plugin ', 'wp-2fa' ) . '<a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank" rel="nofollow">WP Mail SMTP</a>',
									'not_bool'    => true,
									'not_id'      => true,
									'value'       => Email::POLICY_SETTINGS_NAME,
								)
							);
							?>
							<p class="wizard-method-desc">
								<?php
								echo \wp_sprintf(
									/* translators: 1. Link to WP Mail SMTP plugin, 2. Recommendation to allow users to set up a secondary method. */
									\esc_html__( 'When using this method, users will receive the one-time login code over email. Therefore, email deliverability is very important. Users using this method should whitelist the address from which the codes are sent. By default, this is the email address configured in your WordPress. You can run an email test from the plugin\'s settings to confirm email deliverability. If you have had email deliverability / reliability issues, we highly recommend you to install the free plugin %1$s', 'wp-2fa' ),
									'<a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank" rel="nofollow">WP Mail SMTP</a>'
								);
								?>
								</p>
						</div>
					</div>
				<?php
			}

			/**
			 * Allow extensions to add additional methods below the main list.
			 *
			 * @param bool        $wizard     True – this is the wizard context.
			 * @param string      $data_role  Data-role attribute (empty in new wizard).
			 * @param string|null $name       Role name (null for global).
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'after_primary_methods_toggles', null );

			?>

			<div class="wizard-info-blue-notice">
				<p><?php \esc_html_e( 'Allowing users to configure a secondary 2FA method is highly recommended. You can configure this in the next step of the wizard. This allows users to log in using an alternative method if they lose access to their primary 2FA device, such as their phone.', 'wp-2fa' ); ?></p>
			</div>
			<?php
			?>
			<?php
		}

		/*
		==============================================================
		 * STEP 2 – Alternative Methods
		 * ==============================================================
		 */

		/**
		 * Render the alternative / backup methods step.
		 *
		 * @return void
		 */
		public static function step_alternative_methods() {
			$backup_enabled       = Settings_Utils::string_to_bool( Settings_Utils::get_setting_role( null, Backup_Codes::get_settings_name(), true ) );
			$email_backup_enabled = Settings_Utils::get_setting_role( null, 'enable-email-backup', true );

			?>
			<h3><?php \esc_html_e( 'Which secondary 2FA methods can users use?', 'wp-2fa' ); ?></h3>
			<p class="description">
				<?php \esc_html_e( 'A secondary 2FA method acts as a backup when the primary method cannot be used. For example, if a user loses access to their phone, the device runs out of battery, or email delivery fails. We strongly recommend allowing users to configure at least one backup method at all times.', 'wp-2fa' ); ?>
			</p>
			<!-- <p class="description">
				<?php // \esc_html_e( 'Available secondary 2FA methods:', 'wp-2fa' ); ?>
			</p> -->

			<div class="wizard-method-card">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'wizard-backup-codes',
						'type'        => 'checkbox',
						'option_name' => self::NAME_PREFIX . '[' . Backup_Codes::get_settings_name() . ']',
						'default'     => $backup_enabled ? 'yes' : '',
						'text'        => '<strong>' . \esc_html__( 'Backup codes', 'wp-2fa' ) . '</strong>',
						'not_bool'    => true,
						'not_id'      => true,
						'value'       => 'yes',
					)
				);
				?>
				<p class="wizard-method-desc">
					<?php
					printf(
						'%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
						\esc_html__( 'Backup codes allow users to log in to WordPress should they find themselves unable to log in via the primary 2FA method. Backup codes are enabled by default and are generated during the 2FA configuration process. Each backup code can be used only once. Once the initial list is exhausted, more backup codes can be generated through the user\'s WordPress profile page -', 'wp-2fa' ),
						\esc_url( 'https://melapress.com/support/kb/wp-2fa-what-are-2fa-backup-codes/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=backup_codes_info' ),
						\esc_html__( 'More information', 'wp-2fa' )
					);
					?>
				</p>
			</div>


			<?php
			/**
			 * Allow extensions to register additional backup methods.
			 *
			 * @param bool        $wizard    True - wizard context.
			 * @param string      $data_role Data-role attribute.
			 * @param string|null $role      Role name.
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'after_secondary_methods_toggles', null );

			// @free:start
			?>
			<div class="wizard-upgrade-notice">
				<span>
					<strong><?php \esc_html_e( 'Send backup codes instantly via email', 'wp-2fa' ); ?></strong><br>
					<?php \esc_html_e( 'Let users receive a one-time backup code by email in seconds — plus more premium 2FA methods with WP 2FA Premium.', 'wp-2fa' ); ?>
				</span>
				<a href="https://melapress.com/wordpress-2fa/pricing/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=upgrade_now" target="_blank" class="button button-secondary"><?php \esc_html_e( 'Upgrade Now', 'wp-2fa' ); ?></a>
			</div>
			<?php
			// @free:end
		}

		/*
		==============================================================
		 * STEP 3 – 2FA Policy (Enforcement)
		 * ==============================================================
		 */

		/**
		 * Render the enforcement policy step.
		 *
		 * @return void
		 */
		public static function step_enforcement_policy() {
			$enforcement_policy = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
			$enforced_users     = array_filter( (array) WP2FA::get_wp2fa_setting( 'enforced_users' ) );
			$enforced_roles     = array_filter( (array) WP2FA::get_wp2fa_setting( 'enforced_roles' ) );
			$all_roles          = WP_Helper::get_roles_wp();

			$enforced_users_items = array_map(
				function ( $user ) {
					return array(
						'id'   => $user,
						'text' => $user,
					);
				},
				$enforced_users
			);

			$enforced_roles_items = array_map(
				function ( $role ) use ( $all_roles ) {
					return array(
						'id'   => $role,
						'text' => isset( $all_roles[ $role ] ) ? $all_roles[ $role ] : ucfirst( $role ),
					);
				},
				$enforced_roles
			);

			// Build radio options – add multisite-specific choices when applicable.
			$radio_options = array(
				'all-users' => \esc_html__( 'All users', 'wp-2fa' ),
			);

			$toggle_map = array(
				'all-users' => '',
			);

			if ( WP_Helper::is_multisite() ) {
				$radio_options['superadmins-only']            = \esc_html__( 'Only super admins', 'wp-2fa' );
				$radio_options['superadmins-siteadmins-only'] = \esc_html__( 'Only super admins and site admins', 'wp-2fa' );
				$toggle_map['superadmins-only']               = '';
				$toggle_map['superadmins-siteadmins-only']    = '';
			}

			$radio_options['certain-roles-only'] = \esc_html__( 'Only for specific users and roles', 'wp-2fa' );
			$toggle_map['certain-roles-only']    = '#wizard-enforcement-specific-inputs';

			if ( WP_Helper::is_multisite() ) {
				$radio_options['enforce-on-multisite'] = \esc_html__( 'These sub-sites', 'wp-2fa' );
				$toggle_map['enforce-on-multisite']    = '#wizard-enforcement-sites-inputs';
			}

			$radio_options['do-not-enforce'] = \esc_html__( 'Do not enforce on any users', 'wp-2fa' );
			$toggle_map['do-not-enforce']    = '';

			?>
			<h3><?php \esc_html_e( 'Do you want to enforce 2FA for some, or all users?', 'wp-2fa' ); ?></h3>
			<p class="description">
				<?php \esc_html_e( 'When 2FA is enforced, users will be prompted to configure it the next time they log in. Users are given a grace period to complete the setup before 2FA becomes mandatory. You can configure the grace period and exclude specific users or roles later from the plugin\'s settings.', 'wp-2fa' ); ?>
				<a href="https://melapress.com/support/kb/wp-2fa-configure-2fa-policies-enforce/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=enforcement_policy_help" target="_blank" rel="noopener"><?php \esc_html_e( 'Learn more.', 'wp-2fa' ); ?></a>
			</p>

			<div class="form-group settings-row">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'wizard-enforcement-policy',
						'type'        => 'radio',
						'option_name' => self::NAME_PREFIX . '[enforcement-policy]',
						'default'     => $enforcement_policy,
						'options'     => $radio_options,
						'toggle'      => $toggle_map,
					)
				);
				?>
			</div>

			<!-- Specific users / roles selection -->
			<div id="wizard-enforcement-specific-inputs" style="display:none; padding-bottom: 20px;">
				<div class="form-group settings-row" style="margin-top:12px;">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Users', 'wp-2fa' ),
							'id'   => 'wizard-enforced-users-label',
							'type' => 'settings-label',
						)
					);

					Settings_Builder::build_option(
						array(
							'id'          => 'wizard-enforced-users',
							'type'        => 'multi-select-ajax',
							'option_name' => self::NAME_PREFIX . '[enforced_users]',
							'default'     => implode( ',', $enforced_users ),
							'placeholder' => \esc_attr__( 'Type to search users…', 'wp-2fa' ),
							'items'       => $enforced_users_items,
							'data_attrs'  => array(
								'search-type' => 'users',
								'min-chars'   => '2',
							),
						)
					);
					?>
				</div>

				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Roles', 'wp-2fa' ),
							'id'   => 'wizard-enforced-roles-label',
							'type' => 'settings-label',
						)
					);

					Settings_Builder::build_option(
						array(
							'id'          => 'wizard-enforced-roles',
							'type'        => 'multi-select-ajax',
							'option_name' => self::NAME_PREFIX . '[enforced_roles]',
							'default'     => implode( ',', $enforced_roles ),
							'placeholder' => \esc_attr__( 'Type to search roles…', 'wp-2fa' ),
							'items'       => $enforced_roles_items,
							'data_attrs'  => array(
								'search-type' => 'roles',
								'min-chars'   => '2',
							),
						)
					);
					?>
				</div>
			</div>

			<?php if ( WP_Helper::is_multisite() ) : ?>
			<!-- Sub-sites selection -->
			<div id="wizard-enforcement-sites-inputs" style="display:none; padding-bottom: 20px;">
				<div class="form-group settings-row" style="margin-top:12px;">
					<?php
					$included_sites       = array_filter( (array) WP2FA::get_wp2fa_setting( 'included_sites' ) );
					$included_sites_items = array();
					foreach ( WP_Helper::get_multi_sites() as $site ) {
						if ( in_array( $site->blog_id, $included_sites, true ) || in_array( (string) $site->blog_id, $included_sites, true ) ) {
							$included_sites_items[] = array(
								'id'   => $site->blog_id,
								'text' => $site->blogname,
							);
						}
					}

					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Sites', 'wp-2fa' ),
							'id'   => 'wizard-enforced-sites-label',
							'type' => 'settings-label',
						)
					);

					Settings_Builder::build_option(
						array(
							'id'          => 'wizard-enforced-sites',
							'type'        => 'multi-select-ajax',
							'option_name' => self::NAME_PREFIX . '[included_sites]',
							'default'     => implode( ',', $included_sites ),
							'placeholder' => \esc_attr__( 'Type to search sites…', 'wp-2fa' ),
							'items'       => $included_sites_items,
							'data_attrs'  => array(
								'search-type' => 'sites',
								'min-chars'   => '2',
							),
						)
					);
					?>
				</div>
			</div>
			<?php endif; ?>
			<?php
		}

		/*
		==============================================================
		 * STEP 4 – Exclude Users
		 * ==============================================================
		 */

		/**
		 * Render the exclusions step.
		 *
		 * @return void
		 */
		public static function step_exclude_users() {
			$excluded_users = array_filter( (array) WP2FA::get_wp2fa_setting( 'excluded_users' ) );
			$excluded_roles = array_filter( (array) WP2FA::get_wp2fa_setting( 'excluded_roles' ) );
			$all_roles      = WP_Helper::get_roles_wp();

			$excluded_users_items = array_map(
				function ( $user ) {
					return array(
						'id'   => $user,
						'text' => $user,
					);
				},
				$excluded_users
			);

			$excluded_roles_items = array_map(
				function ( $role ) use ( $all_roles ) {
					return array(
						'id'   => $role,
						'text' => isset( $all_roles[ $role ] ) ? $all_roles[ $role ] : ucfirst( $role ),
					);
				},
				$excluded_roles
			);
			?>
			<h3><?php \esc_html_e( 'Do you want to exclude any users or roles from 2FA?', 'wp-2fa' ); ?></h3>
			<p class="description">
				<?php \esc_html_e( 'If you are enforcing 2FA on all users but for some reason you would like to exclude individual user(s) or users with a specific role, you can exclude them below:', 'wp-2fa' ); ?>
			</p>

			<div class="form-group settings-row">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'Exclude the following users', 'wp-2fa' ),
						'id'   => 'wizard-excluded-users-label',
						'type' => 'settings-label',
					)
				);

				Settings_Builder::build_option(
					array(
						'id'          => 'wizard-excluded-users',
						'type'        => 'multi-select-ajax',
						'option_name' => self::NAME_PREFIX . '[excluded_users]',
						'default'     => implode( ',', $excluded_users ),
						'placeholder' => \esc_attr__( 'Add users', 'wp-2fa' ),
						'items'       => $excluded_users_items,
						'data_attrs'  => array(
							'search-type' => 'users',
							'min-chars'   => '2',
						),
					)
				);
				?>
			</div>

			<div class="form-group settings-row">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'Exclude the following roles', 'wp-2fa' ),
						'id'   => 'wizard-excluded-roles-label',
						'type' => 'settings-label',
					)
				);

				Settings_Builder::build_option(
					array(
						'id'          => 'wizard-excluded-roles',
						'type'        => 'multi-select-ajax',
						'option_name' => self::NAME_PREFIX . '[excluded_roles]',
						'default'     => implode( ',', $excluded_roles ),
						'placeholder' => \esc_attr__( 'Add roles', 'wp-2fa' ),
						'items'       => $excluded_roles_items,
						'data_attrs'  => array(
							'search-type' => 'roles',
							'min-chars'   => '2',
						),
					)
				);
				?>
			</div>
			<?php
		}

		/*
		==============================================================
		 * STEP 5 – Exclude Sites (Multisite only)
		 * ==============================================================
		 */

		/**
		 * Render the exclude sites step (multisite only).
		 *
		 * @return void
		 */
		public static function step_exclude_sites() {
			if ( ! WP_Helper::is_multisite() ) {
				return;
			}

			$excluded_sites       = array_filter( (array) WP2FA::get_wp2fa_setting( 'excluded_sites' ) );
			$excluded_sites_items = array();
			foreach ( WP_Helper::get_multi_sites() as $site ) {
				if ( in_array( $site->blog_id, $excluded_sites, true ) || in_array( (string) $site->blog_id, $excluded_sites, true ) ) {
					$excluded_sites_items[] = array(
						'id'   => $site->blog_id,
						'text' => $site->blogname,
					);
				}
			}
			?>
			<h3><?php \esc_html_e( 'Do you want to exclude all the users of a site from 2FA?', 'wp-2fa' ); ?></h3>
			<p class="description">
				<?php \esc_html_e( 'If you are enforcing 2FA on all users but for some reason you do not want to enforce it on a specific sub site, specify the sub site name below:', 'wp-2fa' ); ?>
			</p>

			<div class="form-group settings-row" style="margin-top:12px;">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'Exclude the following sites', 'wp-2fa' ),
						'id'   => 'wizard-excluded-sites-label',
						'type' => 'settings-label',
					)
				);

				Settings_Builder::build_option(
					array(
						'id'          => 'wizard-excluded-sites',
						'type'        => 'multi-select-ajax',
						'option_name' => self::NAME_PREFIX . '[excluded_sites]',
						'default'     => implode( ',', $excluded_sites ),
						'placeholder' => \esc_attr__( 'Type to search sites…', 'wp-2fa' ),
						'items'       => $excluded_sites_items,
						'data_attrs'  => array(
							'search-type' => 'sites',
							'min-chars'   => '2',
						),
					)
				);
				?>
			</div>
			<?php
		}

		/*
		==============================================================
		 * STEP 6 – Grace Period
		 * ==============================================================
		 */

		/**
		 * Render the grace period step.
		 *
		 * @return void
		 */
		public static function step_grace_period() {
			$grace_policy              = WP2FA::get_wp2fa_setting( 'grace-policy', true );
			$grace_period              = (int) WP2FA::get_wp2fa_setting( 'grace-period', true );
			$grace_period_denominator  = WP2FA::get_wp2fa_setting( 'grace-period-denominator', true );
			$grace_after_expire_action = WP2FA::get_wp2fa_setting( 'grace-policy-after-expire-action', true );

			if ( ! $grace_policy ) {
				$grace_policy = 'use-grace-period';
			}
			if ( ! $grace_period ) {
				$grace_period = 3;
			}
			if ( ! $grace_period_denominator ) {
				$grace_period_denominator = 'days';
			}
			if ( ! $grace_after_expire_action ) {
				$grace_after_expire_action = 'configure-right-away';
			}

			$user                         = \wp_get_current_user();
			$last_user_to_update_settings = $user->ID;

			$grace_notification = Settings_Utils::get_setting_role( null, Grace_Period_Notifications::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME, true );
			if ( ! $grace_notification ) {
				$grace_notification = 'after-login-notification';
			}
			?>
			<h3><?php \esc_html_e( 'How long should the grace period for your users be?', 'wp-2fa' ); ?></h3>
			<p class="description">
				<?php \esc_html_e( 'When you configure the 2FA policies and require users to configure 2FA, they can either have a grace period to configure 2FA, or can be required to configure 2FA before the next time they login.', 'wp-2fa' ); ?>
			</p>

			<div class="settings-card">
				<?php
				Settings_Builder::build_option(
					array(
						'title' => \esc_html__( 'Choose which method you\'d like to use:', 'wp-2fa' ),
						'id'    => 'wizard-grace-method-title',
						'type'  => 'section-sub-title',
					)
				);
				?>

				<div class="form-group settings-row">
					<div class="settings-control">
						<label class="radio-option">
							<input type="radio"
								name="<?php echo \esc_attr( self::NAME_PREFIX ); ?>[grace-policy]"
								id="wizard-no-grace-period"
								value="no-grace-period"
								<?php \checked( $grace_policy, 'no-grace-period' ); ?>>
							<span><?php \esc_html_e( 'Users have to configure 2FA straight away.', 'wp-2fa' ); ?></span>
						</label>
						<label class="radio-option">
							<input type="radio"
								name="<?php echo \esc_attr( self::NAME_PREFIX ); ?>[grace-policy]"
								id="wizard-use-grace-period"
								value="use-grace-period"
								<?php \checked( $grace_policy, 'use-grace-period' ); ?>
								data-toggle-target="#wizard-grace-period-sub-settings">
							<span><?php \esc_html_e( 'Give users a grace period to configure 2FA', 'wp-2fa' ); ?></span>
						</label>
					</div>
				</div>

				<!-- Grace period sub-settings -->
				<div id="wizard-grace-period-sub-settings" class="grace-period-sub-settings"<?php echo ( 'use-grace-period' !== $grace_policy ) ? ' style="display:none;"' : ''; ?>>
					<div class="form-group settings-row" style="margin-left: 35px;">
						<div class="settings-control inline-controls">
							<?php
							Settings_Builder::build_option(
								array(
									'id'          => 'wizard-grace-period',
									'type'        => 'number',
									'min'         => 1,
									'max'         => 90,
									'class'       => 'small-text',
									'option_name' => self::NAME_PREFIX . '[grace-period]',
									'default'     => $grace_period,
								)
							);
							Settings_Builder::build_option(
								array(
									'id'          => 'wizard-grace-period-denominator',
									'type'        => 'select',
									'option_name' => self::NAME_PREFIX . '[grace-period-denominator]',
									'default'     => $grace_period_denominator,
									'options'     => array(
										'hours' => \esc_html__( 'Hours', 'wp-2fa' ),
										'days'  => \esc_html__( 'Days', 'wp-2fa' ),
									),
								)
							);
							?>
						</div>
					</div>

					<!-- What happens after grace period expires -->
					<div style="margin-left: 35px;">
						<?php
						Settings_Builder::build_option(
							array(
								'title' => \esc_html__( 'Users who missed the grace period', 'wp-2fa' ),
								'id'    => 'wizard-grace-expire-label',
								'type'  => 'section-sub-title',
							)
						);
						?>
						<div class="form-group settings-row">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'wizard-grace-policy-after-expire-action',
								'type'        => 'radio',
								'option_name' => self::NAME_PREFIX . '[grace-policy-after-expire-action]',
								'default'     => $grace_after_expire_action,
								'options'     => array(
									'configure-right-away' => \esc_html__( 'Disable user access to the dashboard / user page once they log in, until they configure 2FA', 'wp-2fa' ),
									'manual-block'         => \esc_html__( 'Block the user (administrators have to manually unblock them)', 'wp-2fa' ),
								),
							)
						);
						?>
						</div>
					</div>

					<!-- Notification settings -->
					<div style="margin-left: 35px;">
						<?php
						Settings_Builder::build_option(
							array(
								'title' => \esc_html__( 'Notification settings', 'wp-2fa' ),
								'id'    => 'wizard-grace-notification-label',
								'type'  => 'section-sub-title',
							)
						);
						?>
						<div class="form-group settings-row">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'wizard-grace-notification',
								'type'        => 'radio',
								'option_name' => self::NAME_PREFIX . '[' . Grace_Period_Notifications::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME . ']',
								'default'     => $grace_notification,
								'options'     => array(
									'dashboard-notification'   => \esc_html__( 'Show an admin notice in the dashboard', 'wp-2fa' ),
									'after-login-notification' => \esc_html__( 'Show a notification on a page on its own after the user authenticates and before accessing the dashboard', 'wp-2fa' ),
								),
							)
						);
						?>
						</div>
					</div>
				</div>
			</div>

			<input type="hidden" name="<?php echo \esc_attr( self::NAME_PREFIX ); ?>[2fa_settings_last_updated_by]" value="<?php echo \esc_attr( $last_user_to_update_settings ); ?>">
			<?php
		}
	}
}
