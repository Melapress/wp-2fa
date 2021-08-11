<?php

namespace WP2FA\Admin;

use WP2FA\WP2FA;
use WP2FA\Cron\CronTasks;
use WP2FA\Utils\UserUtils;
use WP2FA\Authenticator\Authentication;
use WP2FA\Utils\SettingsUtils as SettingsUtils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

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
		 * Holds the hash generated from the global settings for the WP2FA
		 *
		 * @var string
		 */
		private $globalSettingsHash;

        /**
         * Totp key assigned to user
         *
         * @var string
         */
        private $totpKey = '';

        /**
         * Default constructor
         *
         * @param mixed $user - you can use \WP_User, integer (representing ID of the user), or any value that returns true checked against empty in PHP
         *
         * @throws \Exception
         */
        public function __construct( $user = '' ) {
            $this->setUser( $user );

			$this->globalSettingsHash = SettingsUtils::create_settings_hash( WP2FA::getAllSettings() );

			if ( ! empty( $this->globalSettingsHash ) ) {
				$this->checkMethodsAndSetUser();
				$storedHash = $this->getGlobalSettingsHashUser();
				if ( $this->globalSettingsHash !== $storedHash ) {
					$this->setUserPoliciesAndGrace();
					$this->setGlobalSettingsHash( $this->globalSettingsHash );
				}
			}
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
			$settings = SettingsUtils::get_option( WP_2FA_SETTINGS_NAME );
			if ( ! is_array( $settings ) || 'do-not-enforce' === $settings['enforcement-policy'] ) {
				//  2FA is not enforced, make sure to clear any related user meta previously created
				$this->deleteUserMeta( WP_2FA_PREFIX . 'is_locked' );
				$this->deleteUserMeta( WP_2FA_PREFIX . 'grace_period_expiry' );
				$this->deleteUserMeta( WP_2FA_PREFIX . 'locked_account_notification' );

				return false;
			}

			$is_user_instantly_enforced = get_user_meta( $user_id, WP_2FA_PREFIX . 'user_enforced_instantly', true );
			if ( $is_user_instantly_enforced ) {
				//  no need to lock the account if the user is enforced to set 2FA up instantly
				return false;
			}

			if ( WP2FA::is_user_excluded( $user_id ) ) {
				return false;
			}

			// Do not lock if user has 2FA configured.
			$has_enabled_method = get_user_meta( $user_id, WP_2FA_PREFIX . '2fa_status', true );
			if ( $has_enabled_method == 'has_enabled_methods' ) {				
				return false;
			}

			$grace_period_expiry_time = get_user_meta( $user_id, WP_2FA_PREFIX . 'grace_period_expiry', true );
			if ( ! empty( $grace_period_expiry_time ) && $grace_period_expiry_time < time() ) {
				//  set "grace period expired" flag
				$this->setUserMeta( WP_2FA_PREFIX . 'user_grace_period_expired', true );

				// Send the email to alert the user, only if we have not done so before.
				$account_notification = get_user_meta( $user_id, WP_2FA_PREFIX . 'locked_account_notification', true );
				if ( ! $account_notification ) {
					CronTasks::send_expired_grace_email( $user_id );
					$this->setUserMeta( WP_2FA_PREFIX . 'locked_account_notification', true );
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
		 * Sets user object
		 *
		 * @param \WP_User|null
		 *
		 * @return void
		 */
		public function setUser( $user = '' ) {
			if ( is_a( $user, 'WP_User' ) ) {
				$this->user = $user;

				return;
			}

			if ( is_int( $user ) ) {
				$this->user = new \WP_User( $user );

				return;
			}

			if ( empty( $user ) ) {
				$this->user = \wp_get_current_user();
			}
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
				return $this->user->get( WP_2FA_PREFIX . 'enabled_methods' );
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

                    $this->setUserMeta( 'wp_2fa_totp_key', $this->totpKey );
                }
            }

            return $this->totpKey;
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

				$globalMethods = [];

				if ( ! empty( WP2FA::get_wp2fa_setting( 'enable_totp' ) ) ) {
					$globalMethods[] = 'totp';
				}

				if ( ! empty( WP2FA::get_wp2fa_setting( 'enable_email' ) ) ) {
					$globalMethods[] = 'email';
				}

				if ( empty( \array_intersect( [ $enabledMethodsForTheUser ], $globalMethods ) ) ) {
					$this->deleteUserMeta( WP_2FA_PREFIX . 'enabled_methods' );
					if ( WP2FA::isUserEnforced( $this->user->ID ) ) {
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

			$is_needed = WP2FA::isUserEnforced( $this->user->ID );
			$excluded  = WP2FA::is_user_excluded( $this->user->ID );
			if ( $is_needed && ! $excluded ) {
				$grace_policy = WP2FA::get_wp2fa_setting( 'grace-policy' );

				// Check if want to apply the custom period, or instant expiry.
				if ( 'use-grace-period' === $grace_policy ) {
					$custom_grace_period_duration =
						WP2FA::get_wp2fa_setting( 'grace-period' ) . ' ' . WP2FA::get_wp2fa_setting( 'grace-period-denominator' );
					$grace_expiry                 = strtotime( $custom_grace_period_duration );
				} else {
					$grace_expiry = time();
				}

				$this->setUserMeta( WP_2FA_PREFIX . 'grace_period_expiry', $grace_expiry );
				if ( 'no-grace-period' === $grace_policy ) {
					$this->setEnforcedInstantly();
				}
			}

			if ( ! $is_needed || $excluded ) {
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
		private function setUserMeta( $metaKey, $value ) {
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
		 * @since latest
		 *
		 */
		public static function isUserLocked( $user_id ) {
			return (bool) get_user_meta( $user_id, WP_2FA_PREFIX . 'user_grace_period_expired', true );
		}
	}
}
