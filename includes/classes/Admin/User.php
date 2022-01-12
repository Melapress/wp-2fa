<?php

namespace WP2FA\Admin;

use WP2FA\WP2FA;
use WP2FA\Cron\CronTasks;
use WP2FA\Utils\UserUtils;
use WP2FA\Authenticator\Open_SSL;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Authenticator\Authentication;
use WP2FA\Utils\SettingsUtils as SettingsUtils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * User class which holds all the user related data
 */
if ( ! class_exists( '\WP2FA\Admin\User' ) ) {

	/**
	 * WP2FA User controller
	 */
	class User {

		/**
		 * Holds the current user
		 *
		 * @var \WP_User
		 */
		private $user = null;

		/**
		 * Totp key assigned to user
		 *
		 * @var string
		 */
		private $totpKey = '';

		/**
		 * Local cache of created user instances. Associative array where the keys are user IDs.
		 *
		 * @var User[]
		 * @since 2.0.0
		 */
		private static $user_instances = [];

		/**
		 * This function is supposed to be used to get instance of User object in the plugin. This way we make sure we
		 * don't create the User object for the same user multiple times from different places.
		 *
		 * @param mixed $user You can use \WP_User, integer (representing ID of the user), or any value that returns true checked against empty in PHP.
		 *
		 * @return User
		 * @since 2.0.0
		 */
		public static function get_instance( $user = '' ) {
			$user = self::determine_user( $user );
			if ( ! array_key_exists( $user->ID, self::$user_instances ) ) {
				self::$user_instances[ $user->ID ] = new User( $user );
			}

			return self::$user_instances[ $user->ID ];
		}

		/**
		 * Default constructor
		 *
		 * @param \WP_User $user
		 */
		private function __construct( $user ) {
			$this->user = $user;
			$this->update_meta_if_necessary();
		}

		/**
		 * Updates necessary user metadata if necessary. The updated is necessary only if the settings hash stored
		 * against the user doesn't match the hash for the current copy of plugin settings.
		 */
		public function update_meta_if_necessary() {
			$globalSettingsHash = SettingsUtils::get_option( WP_2FA_PREFIX . 'settings_hash' );
			if ( ! empty( $globalSettingsHash ) ) {
				$storedHash = $this->getGlobalSettingsHashUser();
				if ( $globalSettingsHash !== $storedHash ) {
					$this->setGlobalSettingsHash( $globalSettingsHash );
					//  update necessary user attributes (user meta) based on changed settings; the enforcement check
					//  needs to run first as function "setUserPoliciesAndGrace" relies on having the correct values
					$this->checkMethodsAndSetUser();
					$this->updateUserEnforcementState();
					$this->setUserPoliciesAndGrace();
				}
			}
		}

		/**
		 * Runs necessary checks and updates the user enforcement state metadata.
		 *
		 * @since 2.0.0
		 */
		private function updateUserEnforcementState() {
			if ( ! isset( $this->user->ID ) ) {
				return;
			}

			$enforcement_state = 'optional';
			if ( self::run_user_enforcement_check( $this->user ) ) {
				$enforcement_state = 'enforced';
			} elseif ( self::run_user_exclusion_check( $this->user ) ) {
				$enforcement_state = 'excluded';
			}

			$this->setUserMeta( WP_2FA_PREFIX . 'enforcement_state', $enforcement_state );
		}

		/**
		 * Runs the necessary checks to figure out if the user is enforced based on current plugin settings.
		 *
		 * @param \WP_User $user User to evaluate.
		 *
		 * @return bool True if the user is enforced based on current plugin settings.
		 * @since 2.0.0
		 */
		private function run_user_enforcement_check( $user ) {
			$user_roles     = $user->roles;
			$current_policy = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
			$enabled_method = $this->getEnabledMethods();
			$user_eligible  = false;

			// Let's check the policy settings and if the user has setup totp/email by checking for the usermeta.
			if ( empty( $enabled_method ) && WP2FA::is_this_multisite() && 'superadmins-only' === $current_policy ) {
				return is_super_admin( $user->ID );
			} elseif ( empty( $enabled_method ) && WP2FA::is_this_multisite() && 'superadmins-siteadmins-only' === $current_policy ) {
				return is_super_admin( $user->ID ) || User::isAdminUser( $user->ID );
			} elseif ( 'all-users' === $current_policy && empty( $enabled_method ) ) {

				if ( SettingsUtils::string_to_bool( WP2FA::get_wp2fa_setting( 'superadmins-role-exclude' ) ) && is_super_admin( $user->ID ) ) {
					return false;
				}

				$excluded_users = WP2FA::get_wp2fa_setting( 'excluded_users' );
				if ( ! empty( $excluded_users ) ) {
					// Compare our roles with the users and see if we get a match.
					$result = in_array( $user->user_login, $excluded_users, true );
					if ( $result ) {
						return false;
					}

					$user_eligible = true;
				}

				$excluded_roles = WP2FA::get_wp2fa_setting( 'excluded_roles' );
				if ( ! empty( $excluded_roles ) ) {

					if ( ! WP2FA::is_this_multisite() ) {
						// Compare our roles with the users and see if we get a match.
						$result = array_intersect( $excluded_roles, $user->roles );

						if ( ! empty( $result ) ) {
							return false;
						}
					} else {
						$users_caps = array();
						$subsites   = get_sites();
						// Check each site and add to our array so we know each users actual roles.
						foreach ( $subsites as $subsite ) {
							$subsite_id   = get_object_vars( $subsite )['blog_id'];
							$users_caps[] = get_user_meta( $user->ID, 'wp_' . $subsite_id . '_capabilities', true );
						}

						foreach ( $users_caps as $key => $value ) {
							if ( ! empty( $value ) ) {
								foreach ( $value as $key => $value ) {
									$result = in_array( $key, $excluded_roles_array, true );
								}
							}
						}
						if ( ! empty( $result ) ) {
							return false;
						}
					}
				}

				if ( true === $user_eligible || empty( $enabled_method ) ) {
					return true;
				}
			} elseif ( 'certain-roles-only' === $current_policy && empty( $enabled_method ) ) {
				$enforced_users = WP2FA::get_wp2fa_setting( 'enforced_users' );
				if ( ! empty( $enforced_users ) ) {
					// Turn it into an array.
					$enforced_users_array = $enforced_users;
					// Compare our roles with the users and see if we get a match.
					$result = in_array( $user->user_login, $enforced_users_array, true );
					// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
					if ( ! empty( $result ) ) {
						return true;
					}
				}

				$enforced_roles = WP2FA::get_wp2fa_setting( 'enforced_roles' );
				if ( ! empty( $enforced_roles ) ) {
					// Turn it into an array.
					$enforced_roles_array = SettingsPage::extract_roles_from_input( $enforced_roles );

					if ( ! WP2FA::is_this_multisite() ) {
						// Compare our roles with the users and see if we get a match.
						$result = array_intersect( $enforced_roles_array, $user_roles );

						// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
						if ( ! empty( $result ) ) {
							return true;
						}
					} else {
						$users_caps = array();
						$subsites   = get_sites();
						// Check each site and add to our array so we know each users actual roles.
						foreach ( $subsites as $subsite ) {
							$subsite_id   = get_object_vars( $subsite )['blog_id'];
							$users_caps[] = get_user_meta( $user->ID, 'wp_' . $subsite_id . '_capabilities', true );
						}

						foreach ( $users_caps as $key => $value ) {
							if ( ! empty( $value ) ) {
								foreach ( $value as $key => $value ) {
									$result = in_array( $key, $enforced_roles_array, true );
								}
							}
						}
						if ( ! empty( $result ) ) {
							return true;
						}
					}
				}

				if ( SettingsUtils::string_to_bool( WP2FA::get_wp2fa_setting( 'superadmins-role-add' ) ) ) {
					return is_super_admin( $user->ID );
				}

			} elseif ( 'certain-users-only' === $current_policy && empty( $enabled_method ) ) {
				$enforced_users = WP2FA::get_wp2fa_setting( 'enforced_users' );
				if ( ! empty( $enforced_users ) ) {
					// Compare our roles with the users and see if we get a match.
					$result = in_array( $user->user_login, $enforced_users, true );
					// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
					if ( ! empty( $result ) ) {
						return true;
					}
				}
			} elseif ( 'enforce-on-multisite' === $current_policy ) {
				$includedSites = WP2FA::get_wp2fa_setting( 'included_sites' );

				foreach ( $includedSites as $site_id ) {
					if ( is_user_member_of_blog( $user->ID, $site_id ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Runs the necessary checks to figure out if the user is excluded based on current plugin settings.
		 *
		 * @param \WP_User $user User to evaluate.
		 *
		 * @return bool True if the user is excluded based on current plugin settings.
		 * @since 2.0.0
		 */
		private function run_user_exclusion_check( $user ) {
			$user_roles     = $user->roles;
			$user_excluded  = false;
			$excluded_users = WP2FA::get_wp2fa_setting( 'excluded_users' );
			if ( is_array( $excluded_users ) || strlen( $excluded_users ) > 0 ) {
				// Turn it into an array.
				$excluded_users_array = is_string( $excluded_users ) ? explode( ',', $excluded_users ) : $excluded_users;

				// Compare our roles with the users and see if we get a match.
				$result = in_array( $user->user_login, $excluded_users_array, true );
				if ( $result ) {
					return true;
				}
			}

			$excluded_roles = WP2FA::get_wp2fa_setting( 'excluded_roles' );
			if ( ! empty( $excluded_roles ) ) {
				// Turn it into an array.
				$excluded_roles_array = is_string( $excluded_roles ) ? explode( ',', $excluded_roles ) : $excluded_roles;
				$excluded_roles_array = array_map( 'strtolower', $excluded_roles_array );
				// Compare our roles with the users and see if we get a match.
				$result = array_intersect( $excluded_roles_array, $user_roles );
				if ( ! empty( $result ) ) {
					return true;
				}
			}

			if ( WP2FA::is_this_multisite() ) {
				$excluded_sites = WP2FA::get_wp2fa_setting( 'excluded_sites' );
				if ( ! empty( $excluded_sites ) && is_array( $excluded_sites ) ) {

					foreach ( $excluded_sites as $site_id ) {
						if ( is_user_member_of_blog( $user->ID, $site_id ) ) {
							// User is a member of the blog we are excluding from 2FA.
							return true;
						} else {
							// User is NOT a member of the blog we are excluding.
							$user_excluded = false;
						}
					}
				}

				$included_sites = WP2FA::get_wp2fa_setting( 'included_sites' );
				if ( $included_sites && is_array( $included_sites ) ) {
					foreach ( $included_sites as $siteId ) {
						if ( is_user_member_of_blog( $user->ID, $siteId ) ) {
							$user_excluded = false;
						}
					}
				}
			}

			return $user_excluded;
		}

		/**
		 * Checks to see if the user is excluded.
		 *
		 * @param int $user_id User id.
		 *
		 * @return boolean Is user excluded or not.
		 */
		public static function is_excluded( $user_id ) {
			return 'excluded' === get_user_meta( $user_id, WP_2FA_PREFIX . 'enforcement_state', true );
		}

		/**
		 * Checks to see if user is enforced.
		 *
		 * @param int $user_id User id.
		 *
		 * @return boolean True if the user is enforced.
		 * @since 1.6
		 *
		 */
		public static function is_enforced( $user_id ) {
			return 'enforced' === get_user_meta( $user_id, WP_2FA_PREFIX . 'enforcement_state', true );
		}

		/**
		 * Locks the user account if the grace period setting is configured and the user is currently out of their grace
		 * period. It also takes care of sending the "account locked" email to the user if not already sent before.
		 *
		 * @param \WP_User $user User object.
		 *
		 * @return bool True if the user account is locked. False otherwise.
		 */
		public function lock_user_account_if_needed() {
			if ( ! $this->isUserSet() ) {
				return false;
			}

			$user_id  = $this->user->ID;
			$settings = SettingsUtils::get_option( WP_2FA_POLICY_SETTINGS_NAME );
			if ( ! is_array( $settings ) || ( isset( $settings['enforcement-policy'] ) && 'do-not-enforce' === $settings['enforcement-policy'] ) ) {
				// 2FA is not enforced, make sure to clear any related user meta previously created
				$this->deleteUserMeta( WP_2FA_PREFIX . 'is_locked' );
				$this->deleteUserMeta( WP_2FA_PREFIX . 'grace_period_expiry' );
				$this->deleteUserMeta( WP_2FA_PREFIX . 'locked_account_notification' );

				return false;
			}

			$is_user_instantly_enforced = get_user_meta( $user_id, WP_2FA_PREFIX . 'user_enforced_instantly', true );
			if ( $is_user_instantly_enforced ) {
				// no need to lock the account if the user is enforced to set 2FA up instantly.
				return false;
			}

			if ( self::is_excluded( $user_id ) ) {
				return false;
			}

			// Do not lock if user has 2FA configured.
			$has_enabled_method = get_user_meta( $user_id, WP_2FA_PREFIX . '2fa_status', true );
			if ( 'has_enabled_methods' === $has_enabled_method ) {
				return false;
			}

			$grace_period_expiry_time = get_user_meta( $user_id, WP_2FA_PREFIX . 'grace_period_expiry', true );
			$grace_period_expired     = ( ! empty( $grace_period_expiry_time ) && $grace_period_expiry_time < time() );
			if ( $grace_period_expired ) {

				/**
				 * Filter can be used to prevent locking of the user account when the grace period expires.
				 *
				 * @param boolean $should_be_locked Should account be locked? True by default.
				 * @param User $user WP2FA User object.
				 *
				 * @return boolean True if the user account should be locked.
				 * @since 2.0.0
				 */
				$should_be_locked = apply_filters( 'wp_2fa_should_account_be_locked_on_grace_period_expiration', true, $this );
				if ( ! $should_be_locked ) {
					return false;
				}

				// set "grace period expired" flag.
				$this->setUserMeta( WP_2FA_PREFIX . 'user_grace_period_expired', true );

				/**
				 * Allow 3rd party developers to execute additional code when grace period expires (account is locked)
				 *
				 * @param User $user WP2FA User object.
				 *
				 * @since 2.0.0
				 */
				do_action( 'wp_2fa_after_grace_period_expired', $this );

				/**
				 * Filter can be used to disable the email notification about locked user account.
				 *
				 * @param boolean $can_send Can the email notification be sent? True by default.
				 * @param User $user WP2FA User object.
				 *
				 * @return boolean True if the email notification can be sent.
				 * @since 2.0.0
				 */
				$notify_user = apply_filters( 'wp_2fa_send_account_locked_notification', true, $this );
				if ( $notify_user ) {
					// Send the email to alert the user, only if we have not done so before.
					$account_notification = get_user_meta( $user_id, WP_2FA_PREFIX . 'locked_account_notification', true );
					if ( ! $account_notification ) {
						CronTasks::send_expired_grace_email( $user_id );
						$this->setUserMeta( WP_2FA_PREFIX . 'locked_account_notification', true );
					}
				}

				// Grab user session and kill it, preferably with fire.
				$manager = \WP_Session_Tokens::get_instance( $user_id );
				$manager->destroy_all();

				return true;
			}

			return false;
		}

		/**
		 * Returns user object
		 *
		 * @return \WP_User|null
		 */
		public function getUser() {
			return $this->user;
		}

		/**
		 * Turns dynamic $user parameter to WordPress user object.
		 *
		 * @param string $user This can be \WP_User, integer (representing ID of the user), or any value that returns true checked against empty in PHP.
		 *
		 * @return WP_User
		 */
		private static function determine_user( $user = '' ) {
			//  regular WordPress user object
			if ( is_a( $user, 'WP_User' ) ) {
				return $user;
			}

			//  user ID as number
			if ( is_int( $user ) ) {
				return new \WP_User( $user );
			}

			//  default to current user
			return \wp_get_current_user();
		}

		/**
		 * Returns grace period for the user
		 *
		 * @return int - timestamp
		 */
		public function getGracePeriodExpiration() {
			if ( $this->isUserSet() ) {
				return (int) $this->user->get( WP_2FA_PREFIX . 'grace_period_expiry' );
			}

			return 0;
		}

		/**
		 * Does the user need to reconfigure 2FA
		 *
		 * @return bool
		 */
		public function needsToReconfigure2FA() {
			if ( $this->isUserSet() ) {
				return (bool) $this->user->get( WP_2FA_PREFIX . 'user_needs_to_reconfigure_2fa' );
			}

			return false;
		}

		/**
		 * Set the user need to reconfigure 2FA
		 *
		 * @return void
		 */
		public function setReconfigure2FA() {
			if ( $this->isUserSet() ) {
				$this->setUserMeta( WP_2FA_PREFIX . 'user_needs_to_reconfigure_2fa', true );
			}
		}

		/**
		 * Deletes user meta by given key
		 *
		 * @param string $metaName
		 *
		 * @return void
		 */
		public function deleteUserMeta( string $metaName ) {
			if ( $this->isUserSet() ) {
				\delete_user_meta( $this->user->ID, $metaName );
			}
		}

		/**
		 * Returns WP 2FA enabled methods for the user
		 *
		 * @return array
		 */
		public function getEnabledMethods() {
			if ( $this->isUserSet() ) {

				/**
				 * Checks the enabled methods fo the user.
				 *
				 * @param mixed - Value of the method.
				 * @param WP_User - The user which must be checked.
				 *
				 * @since 2.0.0
				 */
				return apply_filters( 'wp_2fa_user_enabled_methods', $this->user->get( WP_2FA_PREFIX . 'enabled_methods', $this->user ) );
			}

			return [];
		}

		/**
		 * Sets WP 2FA user is enforced instantly
		 *
		 * @return void
		 */
		public function setEnforcedInstantly() {
			if ( $this->isUserSet() ) {
				return $this->setUserMeta( WP_2FA_PREFIX . 'user_enforced_instantly', true );
			}
		}

		/**
		 * Returns WP 2FA user is enforced instantly
		 *
		 * @return bool
		 */
		public function getEnforcedInstantly() {
			if ( $this->isUserSet() ) {
				return $this->user->get( WP_2FA_PREFIX . 'user_enforced_instantly' );
			}

			return false;
		}

		/**
		 * Returns WP 2FA user is dismissed update nag
		 *
		 * @return bool
		 */
		public function getDismissedNag() {
			if ( $this->isUserSet() ) {
				return $this->user->get( WP_2FA_PREFIX . 'update_nag_dismissed' );
			}

			return false;
		}

		/**
		 * Retrieves user meta by specifc key
		 *
		 * @param string $meta
		 *
		 * @return mixed
		 */
		public function getUserMeta( string $meta ) {
			if ( $this->isUserSet() ) {
				return $this->user->get( $meta );
			}
		}

		/**
		 * User totp key getter
		 *
		 * @return string
		 */
		public function getTotpKey(): string {
			if ( '' === trim( $this->totpKey ) ) {
				$this->totpKey = Authentication::get_user_totp_key( $this->user->ID );
				if ( empty( $this->totpKey ) ) {
					$this->totpKey = Authentication::generate_key();

					$this->setUserMeta( Authentication::SECRET_META_KEY, $this->totpKey );
				} else {
					if ( Open_SSL::is_ssl_available() && false === \strpos( $this->totpKey, 'ssl_' ) ) {
						$this->totpKey = 'ssl_' . Open_SSL::encrypt( $this->totpKey );
						$this->setUserMeta( Authentication::SECRET_META_KEY, $this->totpKey );
					}
				}
			}

			return $this->totpKey;
		}

		/**
		 * Returns the encoded TOTP when we need to show the actual code to the user
		 * If for some reason the code is invalid it recreates it
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public function get_totp_decrypted(): string {
			$key = $this->getTotpKey();
			if ( Open_SSL::is_ssl_available() && false !== \strpos( $key, 'ssl_' ) ) {
				$key = Open_SSL::decrypt( substr( $key, 4 ) );

				/**
				 * If for some reason the key is not valid, that means that we have to clear the stored TOTP for the user, and create new on
				 * That could happen if the global stored secret (plugin level) is deleted.
				 *
				 * Lets check and if that is the case - create new one
				 */
				if ( ! Authentication::validate_base32_string( $key ) ) {
					$this->totpKey = '';
					$this->deleteUserMeta( Authentication::SECRET_META_KEY );
					$key = $this->getTotpKey();
					$key = Open_SSL::decrypt( substr( $key, 4 ) );
				}
			}

			return $key;
		}

		/**
		 * Checks if current user has admin rights
		 *
		 * @return boolean
		 */
		public static function isAdminUser( $userId = null ): bool {
			if ( null === $userId ) {
				return current_user_can( 'manage_options' );
			}

			return user_can( $userId, 'manage_options' );
		}

		/**
		 * Sets WP 2FA user dismissed update nag
		 *
		 * @return void
		 */
		public function setDismissedNag() {
			if ( $this->isUserSet() ) {
				$this->setUserMeta( WP_2FA_PREFIX . 'update_nag_dismissed', true );
			}
		}

		/**
		 * Check if user has enabled the proper method based on globally enabled methods
		 * sets the flag that forces the user to reconfigure their 2FA method
		 *
		 * @return void
		 */
		public function checkMethodsAndSetUser() {
			if ( $this->isUserSet() && ! $this->needsToReconfigure2FA() ) {
				$enabledMethodsForTheUser = $this->getEnabledMethods();

				if ( empty( $enabledMethodsForTheUser ) ) {
					return;
				}

				$globalMethods = UserUtils::get_available_2fa_methods();
				if ( empty( \array_intersect( [ $enabledMethodsForTheUser ], $globalMethods ) ) ) {
					$this->deleteUserMeta( WP_2FA_PREFIX . 'enabled_methods' );
					if ( self::is_enforced( $this->user->ID ) ) {
						$this->setReconfigure2FA();
					}
				}
			}
		}

		/**
		 * Checks if user needs to reconfigure the method
		 *
		 * @return boolean
		 */
		public function needsToReconfigureMethod(): bool {
			if ( ! $this->isUserSet() ) {
				return false;
			}

			return ( ! empty( $this->needsToReconfigure2FA() ) && ! $this->getDismissedNag() && empty( $this->getEnabledMethods() ) );
		}

		/**
		 * Sets global settings hash for the given user
		 *
		 * @param string $hash
		 *
		 * @return void
		 */
		public function setGlobalSettingsHash( string $hash ) {
			if ( $this->isUserSet() ) {
				$this->setUserMeta( WP_2FA_PREFIX . 'global_settings_hash', $hash );
			}
		}

		/**
		 * Get global setting for hash stored for the given user
		 *
		 * @return void|string
		 */
		public function getGlobalSettingsHashUser() {
			if ( $this->isUserSet() ) {
				return $this->user->get( WP_2FA_PREFIX . 'global_settings_hash' );
			}
		}

		/**
		 * Checks if the user variable is set
		 *
		 * @return boolean
		 */
		private function isUserSet() {
			if ( is_a( $this->user, '\WP_User' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Sets proper grace period and policies for the user based on currently stored settings
		 *
		 * @return void
		 */
		private function setUserPoliciesAndGrace() {

			if ( ! isset( $this->user->ID ) ) {
				return;
			}

			if ( self::is_enforced( $this->user->ID ) ) {
				$grace_policy = Settings::get_role_or_default_setting( 'grace-policy', $this->user );

				// Check if want to apply the custom period, or instant expiry.
				if ( 'use-grace-period' === $grace_policy ) {
					$custom_grace_period_duration =
					Settings::get_role_or_default_setting( 'grace-period', $this->user ) . ' ' . Settings::get_role_or_default_setting( 'grace-period-denominator', $this->user );
					$grace_expiry                 = strtotime( $custom_grace_period_duration );
				} else {
					$grace_expiry = time();
				}

				$this->setUserMeta( WP_2FA_PREFIX . 'grace_period_expiry', $grace_expiry );
				if ( 'no-grace-period' === $grace_policy ) {
					$this->setEnforcedInstantly();
				}
			} else {
				$this->deleteUserMeta( WP_2FA_PREFIX . 'user_enforced_instantly' );
				$this->deleteUserMeta( WP_2FA_PREFIX . 'grace_period_expiry' );
				$this->deleteUserMeta( WP_2FA_PREFIX . 'user_needs_to_reconfigure_2fa' );
			}

			//  update the 2FA status meta field
			self::setUserStatus( $this->user );
		}

		/**
		 * Updates user meta with given value
		 *
		 * @param string $metaKey
		 * @param string $value
		 *
		 * @return void
		 */
		public function setUserMeta( $metaKey, $value ) {
			\update_user_meta( $this->user->ID, $metaKey, $value );
		}

		/**
		 * Figures out the correct 2FA status of a user and stores it against the user in DB. The method is static
		 * because it is temporarily used in user listing to update user accounts created prior to version 1.7.0.
		 *
		 * @param \WP_User $user
		 *
		 * @return string
		 * @see \WP2FA\Admin\UserListing
		 * @since 1.7.0
		 */
		public static function setUserStatus( \WP_User $user ) {
			$status      = UserUtils::determine_user_2fa_status( $user );
			$status_data = UserUtils::extractStatuses( $status );
			if ( ! empty( $status_data ) ) {
				update_user_meta( $user->ID, WP_2FA_PREFIX . '2fa_status', $status_data['id'] );

				return $status_data['label'];
			}

			return '';
		}

		public static function getUserStatus( \WP_User $user ) {
			return get_user_meta( $user->ID, WP_2FA_PREFIX . '2fa_status', true );
		}

		/**
		 * Checks if the user is locked. It only checks a single user meta field to keep this as fast as possible. The
		 * value of the field is updated elsewhere.
		 *
		 * @param int $user_id WordPress user ID.
		 *
		 * @return bool True if the user account is locked. False otherwise.
		 *
		 * @since 2.0.0
		 */
		public static function isUserLocked( $user_id ) {
			$result = (bool) get_user_meta( $user_id, WP_2FA_PREFIX . 'user_grace_period_expired', true );

			return $result;
		}
	}
}
