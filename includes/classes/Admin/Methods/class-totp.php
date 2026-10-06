<?php
/**
 * Responsible for WP2FA user's TOTP manipulation.
 *
 * @package    wp2fa
 * @subpackage methods
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 *
 * @since 2.6.0
 */

declare(strict_types=1);

namespace WP2FA\Methods;

use WP2FA\WP2FA;
use WP2FA\Admin\User_Profile;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Settings_Builder;
use WP2FA\Authenticator\Login;
use WP2FA\Authenticator\Open_SSL;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Authenticator\Authentication;
use WP2FA\Admin\Methods\Traits\Providers;
use WP2FA\Admin\Methods\Traits\Validation;
use WP2FA\Admin\Methods\Traits\WhiteLabel;
use WP2FA\Methods\Wizards\TOTP_Wizard_Steps;
use WP2FA\Admin\Methods\Traits\Settings_Trait;
use WP2FA\Admin\SettingsPages\Settings_Page_White_Label;

/**
 * Class for handling totp codes.
 *
 * @since 2.6.0
 *
 * @package WP2FA
 */
if ( ! class_exists( '\WP2FA\Methods\TOTP' ) ) {
	/**
	 * TOTP code class, for handling totp (app) code generation and such.
	 *
	 * @since 2.6.0
	 */
	class TOTP {

		use Providers;
		use Settings_Trait;
		use Validation;
		use WhiteLabel;

		/**
		 * The name of the method.
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		public const METHOD_NAME = 'totp';

		/**
		 * The internal name of the method.
		 *
		 * @var string
		 *
		 * @since 2.5.0
		 */
		public const METHOD_INTERNAL_NAME = self::METHOD_NAME;

		/**
		 * Secret TOTP key meta name.
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		public const TOTP_META_KEY = WP_2FA_PREFIX . 'totp_key';

		/**
		 * A key offered for setting TOTP up again, until it is confirmed.
		 *
		 * @since 4.2.0
		 */
		public const PENDING_KEY_META = WP_2FA_PREFIX . 'totp_pending_key';

		/**
		 * How long an offered key stays good for.
		 *
		 * @since 4.2.0
		 */
		private const PENDING_KEY_LIFETIME = HOUR_IN_SECONDS;

		/**
		 * The name of the method stored in the policy
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		public const POLICY_SETTINGS_NAME = 'enable_' . self::METHOD_NAME;

		/**
		 * Is the totp method enabled
		 *
		 * @since 1.7
		 *
		 * @var bool
		 */
		private static $enabled = null;

		/**
		 * TOTP keys already read this request, keyed by user ID.
		 *
		 * This used to be a single slot, so whichever user's key was read first
		 * was handed back for every user after it - an admin viewing someone
		 * else's profile could be shown their own seed, or the other way round.
		 *
		 * @var array<int, string>
		 */
		private static $totp_keys = array();

		/**
		 * Inits the class and sets the filters.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function init() {

			self::always_init();

			\add_filter( WP_2FA_PREFIX . 'providers_translated_names', array( __CLASS__, 'provider_name_translated' ) );

			\add_filter( WP_2FA_PREFIX . 'default_settings', array( __CLASS__, 'add_default_settings' ) );

			\add_filter( WP_2FA_PREFIX . 'loop_settings', array( __CLASS__, 'settings_loop' ), 10, 1 );

			\add_filter( WP_2FA_PREFIX . 'no_method_enabled', array( __CLASS__, 'return_default_selection' ), 10, 1 );

			// add the TOTP methods to the list of available methods if enabled.
			\add_filter(
				WP_2FA_PREFIX . 'available_2fa_methods',
				function ( $available_methods, $role ) {
					if ( ! empty( Settings_Utils::get_setting_role( $role, self::POLICY_SETTINGS_NAME ) ) ) {
						array_push( $available_methods, self::METHOD_NAME );
					}

					return $available_methods;
				},
				10,
				2
			);

			\add_filter( WP_2FA_PREFIX . 'white_label_default_settings', array( __CLASS__, 'add_whitelabel_settings' ) );
			\add_action( WP_2FA_PREFIX . 'validate_login_api', array( __CLASS__, 'api_login_validate' ), 10, 3 );

			\add_filter( WP_2FA_PREFIX . 'validate_login_form', array( __CLASS__, 'validate_login_form' ), 10, 3 );

			\add_action( WP_2FA_PREFIX . 'white_label_wizard_options', array( __CLASS__, 'white_label_option_labels' ) );

			TOTP_Wizard_Steps::init();

			\add_filter( WP_2FA_PREFIX . 'providers_settings_labels', array( __CLASS__, 'provider_settings_labels' ) );
		}

		/**
		 * Adds the OOB settings in the white label default settings array
		 *
		 * @param array $labels - array with white label default settings.
		 *
		 * @return array
		 *
		 * @since 3.1.1.2
		 */
		public static function provider_settings_labels( array $labels ): array {
			\ob_start();
			?>
				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'TOTP (one-time code via app) Option label', 'wp-2fa' ),
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control ">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'totp-option-label',
								'type'        => 'text',
								'placeholder' => \esc_html__( 'Provider\'s name', 'wp-2fa' ),
								'class'       => 'form-input',
								'option_name' => 'wp_2fa_white_label[totp-option-label]',
								'default'     => \esc_html( WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true ) ),
							)
						);
						?>
					</div>
				</div>
				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'TOTP option hint', 'wp-2fa' ),
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control ">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'totp-option-label-hint',
								'type'        => 'editor',
								'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
								'option_name' => 'wp_2fa_white_label[totp-option-label-hint]',
								'default'     => WP2FA::get_wp2fa_white_label_setting( 'totp-option-label-hint', true ),
							)
						);
						?>
					</div>
				</div>
				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html(
						\wp_sprintf(
						// translators: Method option label.
						__( '%s help text', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
						)
						),
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'This message is shown to users when configuring %s.', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
						),
						'class' => 'description-settings-card',
						'id'    => 'general-settings-tab',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'method_help_totp_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[method_help_totp_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_intro', true ),
						)
						);
						?>
					</div>
				</div>
				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html(
						\wp_sprintf(
						// translators: Method option label.
						\esc_html__( '%s setup step 1', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
						)
						),
						'type' => 'settings-label',
						)
						);

						// Settings_Builder::build_option(
						// array(
						// 'text'  => \wp_sprintf(
						// \esc_html__( 'Step-by-step guidance for the authenticator-app (TOTP) method, e.g. Step 1: open your authenticator app and tap +. Step 2: scan the QR code. Step 3: enter the 6-digit code shown. %1$s', 'wp-2fa' ),
						// \wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/wp-2fa-customize-user-2fa-experience/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_customize_2fa_user_experience', \esc_html__( 'Learn more', 'wp-2fa' ) )
						// ),
						// 'class' => 'description-settings-card',
						// 'id'    => 'totp-step-1-desc',
						// 'type'  => 'description',
						// )
						// );
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'method_help_totp_step_1',
								'type'        => 'text',
								'placeholder' => \esc_html__( 'Enter text', 'wp-2fa' ),
								'class'       => 'form-input',
								'option_name' => 'wp_2fa_white_label[method_help_totp_step_1]',
								'default'     => \esc_html( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_step_1', true ) ),
							)
						);
						?>
					</div>
				</div>
				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html(
								\wp_sprintf(
								// translators: Method option label.
									\esc_html__( '%s setup step 2', 'wp-2fa' ),
									WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
								)
							),
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control ">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'method_help_totp_step_2',
								'type'        => 'text',
								'placeholder' => \esc_html__( 'Enter text', 'wp-2fa' ),
								'class'       => 'form-input',
								'option_name' => 'wp_2fa_white_label[method_help_totp_step_2]',
								'default'     => \esc_html( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_step_2', true ) ),
							)
						);
						?>
					</div>
				</div>
				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							/*
							 * The sprintf result is a runtime value, so it cannot be a msgid —
							 * wrapping it in esc_html__() asked gettext to look up whatever the
							 * label happened to be, with no text domain. The inner call is the
							 * one that translates; this only needs escaping.
							 */
							'text' => \esc_html(
								\wp_sprintf(
									/* translators: %s: the label configured for the authenticator app method. */
									\esc_html__( '%s setup step 3', 'wp-2fa' ),
									WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
								)
							),
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control ">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'method_help_totp_step_3',
								'type'        => 'text',
								'placeholder' => \esc_html__( 'Enter text', 'wp-2fa' ),
								'class'       => 'form-input',
								'option_name' => 'wp_2fa_white_label[method_help_totp_step_3]',
								'default'     => \esc_html( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_step_3', true ) ),
							)
						);
						?>
					</div>
				</div>
				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html(
								\wp_sprintf(
								// translators: Method option label.
									__( 'This message is shown when a user presses the info button for the %s method.', 'wp-2fa' ),
									WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
								)
							),
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control ">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'method_help_totp_more_intro',
								'type'        => 'editor',
								'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
								'option_name' => 'wp_2fa_white_label[method_help_totp_more_intro]',
								'default'     => WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_more_intro', true ),
							)
						);
						?>
					</div>
				</div>
				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html(
						\wp_sprintf(
						// translators: Method option label.
						\esc_html__( '%s pre-submission text', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
						)
						),
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html(
						\wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'This message is shown to users prior to configuring %s method.', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
						)
						),
						'class' => 'description-settings-card',
						'id'    => 'general-settings-tab',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'method_verification_totp_pre',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[method_verification_totp_pre]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'method_verification_totp_pre', true ),
						)
						);
						?>
					</div>
				</div>


			<?php
			$content = \ob_get_clean();

			$labels[ self::METHOD_NAME ] = array(
				'provider_name' => \esc_html( WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true ) ),
				'content'       => $content,
			);

			return $labels;
		}

		/**
		 * Returns the translated name of the provider
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_translated_name(): string {
			return esc_html__( 'TOTP (one-time code via app)', 'wp-2fa' );
		}

		/**
		 * Validates the token for TOTP.
		 *
		 * @param \WP_User $user - The user.
		 * @param string   $token - The token.
		 *
		 * @return bool
		 */
		protected static function validate_token( \WP_User $user, string $token ): bool {
			return self::check_code( $user, $token );
		}

		/**
		 * Checks a code against the user's seed - the one place every TOTP login asks.
		 *
		 * The login form (validate_totp_authentication()) and the REST login
		 * (validate_token()) each checked codes themselves, so the seed upgrade
		 * that followed a correct code only ever ran on one of them: sites that
		 * log in through the form kept their seeds in the old format.
		 *
		 * @param \WP_User|null $user - The user logging in.
		 * @param string        $code - The code they entered.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function check_code( ?\WP_User $user, string $code ): bool {
			$valid = Authentication::is_valid_authcode( self::get_totp_key( $user ), $code, $user );

			if ( $valid && $user instanceof \WP_User ) {
				self::upgrade_seed_encryption( $user );
			}

			return $valid;
		}

		/**
		 * Re-encrypts a seed still stored in the old, unauthenticated format.
		 *
		 * Only right after a code from it was accepted: that proves the seed
		 * decrypted correctly, so what is sealed again is the user's real seed
		 * and never garbage from a changed salt. The new value is checked to
		 * open back to the same seed before it replaces the old one; anything
		 * short of that leaves the stored seed as it is. Seeds otherwise stayed
		 * in the old format for good - nothing else rewrites them.
		 *
		 * @param \WP_User $user - The user who has just logged in with the seed.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function upgrade_seed_encryption( \WP_User $user ): void {
			$prefix = Open_SSL::SECRET_KEY_PREFIX;
			$stored = (string) self::get_user_totp_key( $user );

			if ( 0 !== \strpos( $stored, $prefix ) || 0 === \strpos( (string) \substr( $stored, \strlen( $prefix ) ), Open_SSL::AUTHENTICATED_PREFIX ) ) {
				return;
			}

			try {
				$seed = Open_SSL::decrypt( (string) \substr( $stored, \strlen( $prefix ) ) );
				if ( '' === $seed || ! Authentication::validate_base32_string( $seed ) ) {
					return;
				}

				$sealed = Open_SSL::encrypt( $seed );
				if ( Open_SSL::decrypt( $sealed ) !== $seed ) {
					return;
				}
			} catch ( \Throwable $e ) {
				return;
			}

			self::set_user_totp_key( $prefix . $sealed, $user );
			unset( self::$totp_keys[ self::cache_id( $user ) ] );
		}

		/**
		 * Sets the TOTP as a method for the given user
		 *
		 * @param \WP_User $user - The user for which the method has to be set, if null, it uses the current user.
		 * @param string   $totp_key - The totp key for the user to be set.
		 *
		 * @return void
		 *
		 * @throws \LogicException - If the method is called without $totp_key.
		 *
		 * @since 2.6.0
		 */
		public static function set_user_method( $user = null, string $totp_key = '' ) {
			if ( null === $user ) {
				$user = wp_get_current_user();
			}

			if ( '' === \trim( $totp_key ) ) {
				throw new \LogicException( 'TOTP key must not be empty' );
			}

			User_Helper::set_enabled_method_for_user( self::METHOD_NAME, $user );
			self::set_user_totp_key( $totp_key, $user );
			unset( self::$totp_keys[ self::cache_id( $user ) ] );
			self::clear_pending_key( $user );
			User_Profile::delete_expire_and_enforced_keys( $user->ID );
			User_Helper::set_user_status( $user );
		}

		/**
		 * The key to show the user for setting TOTP up, in stored form.
		 *
		 * While TOTP is the user's method, that must never be the key in use. It
		 * was: the active secret went into every profile page's script data, and
		 * "reconfiguring" presented and saved it again, so an old or compromised
		 * authenticator went on working after the user thought they had replaced
		 * it. A user with TOTP gets a new key, held aside until a code from it
		 * is confirmed.
		 *
		 * A user setting TOTP up for the first time gets the key prepared for
		 * them, as before - nothing is using it yet.
		 *
		 * @param int|\WP_User|null $user - The user, the current one if null.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function get_setup_key( $user = null ): string {
			$user    = self::resolve_user( $user );
			$pending = self::get_pending_key( $user );

			if ( '' !== $pending ) {
				return $pending;
			}

			if ( ! self::is_active_for( $user ) ) {
				return self::get_totp_key( $user );
			}

			$pending = Authentication::generate_key();
			self::set_pending_key( $pending, $user );

			return $pending;
		}

		/**
		 * The setup key, readable - for the QR code and the key shown beside it.
		 *
		 * @param int|\WP_User|null $user - The user, the current one if null.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function get_setup_key_decrypted( $user = null ): string {
			$user = self::resolve_user( $user );

			if ( '' === self::get_pending_key( $user ) && ! self::is_active_for( $user ) ) {
				// The first-time key, through the path that also upgrades legacy formats.
				return self::get_totp_decrypted( $user );
			}

			$key = self::get_setup_key( $user );
			try {
				Authentication::decrypt_key_if_needed( $key );
			} catch ( \Throwable $e ) {
				return '';
			}

			return $key;
		}

		/**
		 * Whether a key sent back from setup is the one this user was given.
		 *
		 * The setup form posts the key along with the code. Taking whatever came
		 * back meant the wizard could simply hand back the active secret - or a
		 * caller any secret it liked. Only the key issued for setup is accepted.
		 *
		 * @param int|\WP_User|null $user - The user.
		 * @param string            $key  - The key as posted, readable.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_issued_setup_key( $user, string $key ): bool {
			$user    = self::resolve_user( $user );
			$pending = self::get_pending_key( $user );

			if ( '' !== $pending ) {
				$issued = $pending;
			} elseif ( ! self::is_active_for( $user ) ) {
				$issued = self::get_totp_key( $user );
			} else {
				return false;
			}

			try {
				Authentication::decrypt_key_if_needed( $issued );
			} catch ( \Throwable $e ) {
				return false;
			}

			return '' !== $issued && \hash_equals( \strtoupper( $issued ), \strtoupper( \trim( $key ) ) );
		}

		/**
		 * Holds a key offered for setup aside from the one in use.
		 *
		 * @param string            $key  - The key, in stored (encrypted) form.
		 * @param int|\WP_User|null $user - The user.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function set_pending_key( string $key, $user = null ): void {
			User_Helper::set_meta(
				self::PENDING_KEY_META,
				array(
					'key'    => $key,
					'issued' => time(),
				),
				self::resolve_user( $user )
			);
		}

		/**
		 * Drops the key offered for setup.
		 *
		 * @param int|\WP_User|null $user - The user.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function clear_pending_key( $user = null ): void {
			User_Helper::remove_meta( self::PENDING_KEY_META, self::resolve_user( $user ) );
		}

		/**
		 * The key offered for setup, if there is one and it has not expired.
		 *
		 * @param \WP_User $user - The user.
		 *
		 * @return string The key in stored form, or ''.
		 */
		private static function get_pending_key( \WP_User $user ): string {
			$pending = User_Helper::get_meta( self::PENDING_KEY_META, $user );

			if ( ! is_array( $pending ) || empty( $pending['key'] ) || ! is_string( $pending['key'] ) ) {
				return '';
			}

			if ( (int) ( $pending['issued'] ?? 0 ) + self::PENDING_KEY_LIFETIME < time() ) {
				self::clear_pending_key( $user );

				return '';
			}

			return $pending['key'];
		}

		/**
		 * Whether TOTP is the method this user signs in with.
		 *
		 * @param \WP_User $user - The user.
		 *
		 * @return bool
		 */
		private static function is_active_for( \WP_User $user ): bool {
			return self::METHOD_NAME === User_Helper::get_enabled_method_for_user( $user )
				&& '' !== (string) self::get_user_totp_key( $user );
		}

		/**
		 * A user object for any of the forms the public methods accept.
		 *
		 * @param int|\WP_User|null $user - The user.
		 *
		 * @return \WP_User
		 */
		private static function resolve_user( $user ): \WP_User {
			if ( $user instanceof \WP_User ) {
				return $user;
			}

			if ( is_numeric( $user ) && (int) $user > 0 ) {
				$found = \get_userdata( (int) $user );
				if ( $found instanceof \WP_User ) {
					return $found;
				}
			}

			$current = User_Helper::get_user();

			return $current instanceof \WP_User ? $current : \wp_get_current_user();
		}

		/**
		 * Retrieves the QR code
		 *
		 * @param string|null $key - The key to encode, in stored form; the user's own if null.
		 *
		 * @since 2.6.0
		 *
		 * @return string
		 */
		public static function get_qr_code( ?string $key = null ): string {

			// Setup site information, used when generating our QR code.
			$site_name = site_url();
			$site_name = trim( str_replace( array( 'http://', 'https://' ), '', (string) $site_name ), '/' );
			/**
			 * Changing the title of the login screen for the TOTP method.
			 *
			 * @param string $title - The default title.
			 * @param \WP_User $user - The WP user.
			 *
			 * @since 2.0.0
			 */
			$totp_title = apply_filters(
				WP_2FA_PREFIX . 'totp_title',
				$site_name . ':' . User_Helper::get_user_object()->user_login,
				User_Helper::get_user_object()
			);

			return Authentication::get_google_qr_code( $totp_title, $key ?? self::get_totp_key(), $site_name );
		}

		/**
		 * Validates authentication.
		 *
		 * @param \WP_User $user - The WP user, if presented.
		 *
		 * @return bool Whether the user gave a valid code
		 *
		 * @since 2.6.0
		 */
		public static function validate_totp_authentication( ?\WP_User $user = null ): bool {
			if ( ! empty( $_REQUEST['authcode'] ) ) {  //phpcs:ignore
				$valid = self::check_code( $user, \sanitize_text_field( \wp_unslash( $_REQUEST['authcode'] ) ) );
				if ( $valid ) {
					Authentication::clear_login_attempts( $user );
				} else {
					Authentication::increase_login_attempts( $user );
				}
				return $valid;
			}

			return false;
		}

		/**
		 * Validates the TOTP login form submission.
		 *
		 * @param bool     $authenticated Whether authentication has passed.
		 * @param \WP_User $user          The user being authenticated.
		 * @param string   $provider      The provider name.
		 *
		 * @return bool
		 *
		 * @since 4.0.1
		 */
		public static function validate_login_form( $authenticated, $user, $provider ) {
			if ( self::METHOD_NAME !== $provider ) {
				return $authenticated;
			}

			if ( $authenticated ) {
				return $authenticated;
			}

			if ( true === self::validate_totp_authentication( $user ) ) {
				return true;
			}

			// Validation failed.
			\do_action(
				'wp_login_failed',
				$user->user_login,
				new \WP_Error(
					'authentication_failed',
					__( '<strong>Error</strong>: User can not be authenticated.', 'wp-2fa' )
				)
			);

			$current_nonce = isset( $_REQUEST['wp-auth-nonce'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['wp-auth-nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			Login::delete_login_nonce( $user->ID, $current_nonce );
			$login_nonce = Login::create_login_nonce( $user->ID );
			if ( ! $login_nonce ) {
				\wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
			}

			if ( Authentication::check_number_of_attempts( $user ) ) {
				Login::login_html( $user, $login_nonce['key'], \esc_url_raw( \wp_unslash( $_REQUEST['redirect_to'] ) ), \esc_html__( 'ERROR: Invalid verification code.', 'wp-2fa' ), $provider ); // phpcs:ignore
			} else {
				// Reached the maximum number of attempts - clear the attempts and redirect the user to the login page.
				Authentication::clear_login_attempts( $user );
				\wp_safe_redirect( \wp_login_url() );
			}
			exit;
		}

		/**
		 * Add extension settings to the loop array
		 *
		 * @param array $loop_settings - Currently available settings array.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function settings_loop( array $loop_settings ): array {
			array_push( $loop_settings, self::POLICY_SETTINGS_NAME );

			return $loop_settings;
		}

		/**
		 * Regenerates the TOTP key for the user
		 *
		 * @return void - JSON - object with "key" - stores the new key and "qr" - stores the new QR code.
		 *
		 * @since 2.5.0
		 */
		public static function regenerate_authentication_key() {
			// Grab current user.
			$user = wp_get_current_user();

			check_ajax_referer( 'wp-2fa-backup-codes-generate-json-' . $user->ID );

			// No key for a method the user's role does not allow.
			if ( ! User_Profile::method_allowed_for( $user, self::METHOD_NAME ) ) {
				\wp_send_json_error( array( 'error' => \esc_html__( 'This 2FA method is not available for your account.', 'wp-2fa' ) ), 403 );
			}

			$key = Authentication::generate_key();

			// The key sent back with the code must be this one.
			self::set_pending_key( $key, $user );

			$site_name = site_url();
			$site_name = trim( str_replace( array( 'http://', 'https://' ), '', (string) $site_name ), '/' );

			/**
			 * Changing the title of the login screen for the TOTP method.
			 *
			 * @param string $title - The default title.
			 * @param \WP_User $user - The WP user.
			 *
			 * @since 2.0.0
			 */
			$totp_title = apply_filters( WP_2FA_PREFIX . 'totp_title', $site_name . ':' . $user->user_login, $user );
			$new_qr     = Authentication::get_google_qr_code( $totp_title, $key, $site_name );

			\wp_send_json_success(
				array(
					'key' => Authentication::decrypt_key_if_needed( $key ),
					'qr'  => $new_qr,
				)
			);
		}

		/**
		 * User totp key getter
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_totp_key( $user = null ): string {
			$user_id = self::cache_id( $user );

			if ( '' === trim( (string) ( self::$totp_keys[ $user_id ] ?? '' ) ) ) {
				$stored_key = (string) self::get_user_totp_key( $user_id );
				$key = self::get_user_totp_key_auth( $user_id );
				// An existing seed that cannot be decrypted is a key-management
				// failure, not an invitation to replace the user's second factor.
				if ( '' !== $stored_key && ( '' === $key || ! self::is_usable_totp_key( $key ) ) ) {
					return '';
				}
				if ( empty( $key ) ) {
					$key = Authentication::generate_key();

					self::set_user_totp_key( $key, $user );
				} elseif ( Open_SSL::is_ssl_available() && false === \strpos( $key, Open_SSL::SECRET_KEY_PREFIX ) ) {
						$key = Open_SSL::SECRET_KEY_PREFIX . Open_SSL::encrypt( $key );
						self::set_user_totp_key( $key, $user );
				}

				self::$totp_keys[ $user_id ] = (string) $key;
			}

			return self::$totp_keys[ $user_id ];
		}

		/** Whether a stored seed can be decrypted and used without changing it. */
		private static function is_usable_totp_key( string $key ): bool {
			try {
				return Authentication::is_valid_key( $key );
			} catch ( \Throwable $e ) {
				return false;
			}
		}

		/**
		 * The user ID a cached TOTP key belongs to.
		 *
		 * Resolved without User_Helper::get_user_object(), which would also move
		 * the helper's ambient "current user" onto the target as a side effect.
		 * User_Helper::get_user() takes no argument at all, which is how the old
		 * single-slot cache came to ignore the user it was asked about.
		 *
		 * @param int|\WP_User|null $user The user, or null for the helper's current user.
		 *
		 * @return int
		 *
		 * @since 4.2.0
		 */
		private static function cache_id( $user ): int {
			if ( $user instanceof \WP_User ) {
				return (int) $user->ID;
			}

			if ( is_numeric( $user ) && (int) $user > 0 ) {
				return (int) $user;
			}

			$current = User_Helper::get_user();

			return ( $current instanceof \WP_User ) ? (int) $current->ID : 0;
		}

		/**
		 * Returns the encoded TOTP when we need to show the actual code to the user
		 * If for some reason the code is invalid it recreates it
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_totp_decrypted( $user = null ): string {
			$key = self::get_totp_key( $user );
			if ( Open_SSL::is_ssl_available() && false !== \strpos( $key, 'ssl_' ) ) {

				/**
				 * Old key detected - convert.
				 */
				$key = Open_SSL::decrypt_legacy( substr( $key, 4 ) );

				self::remove_user_totp_key( $user );
				unset( self::$totp_keys[ self::cache_id( $user ) ] );

				$key = self::get_totp_key( $user );
			}

			if ( Open_SSL::is_ssl_available() && false !== \strpos( $key, 'wps_' ) ) {

				/**
				 * Old key detected - convert.
				 */
				$key = Open_SSL::decrypt_wps( substr( $key, 4 ) );

				self::remove_user_totp_key( $user );

				$secret = Open_SSL::encrypt( $key );

				if ( Open_SSL::is_ssl_available() ) {
					$secret = Open_SSL::SECRET_KEY_PREFIX . $secret;
				}

				self::set_user_totp_key( $secret, $user );

				self::$totp_keys[ self::cache_id( $user ) ] = $secret;
			}

			if ( Open_SSL::is_ssl_available() && false !== \strpos( $key, Open_SSL::SECRET_KEY_PREFIX ) ) {
				$key = Open_SSL::decrypt( substr( $key, 4 ) );

				// A wrong global encryption key must never replace an enrolment.
				if ( ! Authentication::validate_base32_string( $key ) ) {
					return '';
				}
			}

			return $key;
		}

		/**
		 * Deletes the TOTP secret key for a user.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return void
		 */
		public static function remove_user_totp_key( $user = null ) {
			User_Helper::remove_meta( self::TOTP_META_KEY, $user );

			unset( self::$totp_keys[ self::cache_id( $user ) ] );
		}

		/**
		 * Returns the TOTP secret key for a user.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return string
		 */
		public static function get_user_totp_key( $user = null ) {
			return User_Helper::get_meta( self::TOTP_META_KEY, $user );
		}

		/**
		 * Updates the TOTP secret key for a user.
		 *
		 * @param string            $value - The value of the TOTP key.
		 * @param int|\WP_User|null $user  - The WP user that must be used.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function set_user_totp_key( string $value, $user = null ) {
			User_Helper::set_meta( self::TOTP_META_KEY, $value, $user );
		}

		/**
		 * Get the TOTP secret key for a user.
		 *
		 * @param  int $user_id User ID.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_user_totp_key_auth( $user_id ) {

			$key = (string) self::get_user_totp_key( $user_id );

			if ( '' === $key || ! Open_SSL::is_ssl_available() ) {
				return $key;
			}

			if ( 0 === strpos( $key, 'ssl_' ) || 0 === strpos( $key, 'wps_' ) ) {
				try {
					$plain = 0 === strpos( $key, 'ssl_' )
						? Open_SSL::decrypt_legacy( substr( $key, 4 ) )
						: Open_SSL::decrypt_wps( substr( $key, 4 ) );
				} catch ( \Throwable $e ) {
					return $key;
				}
				if ( ! is_string( $plain ) || ! Authentication::is_valid_key( $plain ) ) {
					return $key;
				}
				$key = Open_SSL::SECRET_KEY_PREFIX . Open_SSL::encrypt( $plain );
				self::set_user_totp_key( $key, $user_id );
			}

			return $key;
		}

		/**
		 * Fills up the White Label settings array with the method defaults.
		 *
		 * @param array $default_settings - The array with the collected white label settings.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function add_whitelabel_settings( array $default_settings ): array {

			\ob_start();
			?>
			<p class="description"><?php \esc_html_e( 'Click on the icon of the app that you are using for a detailed guide on how to set it up.', 'wp-2fa' ); ?></p>
			<table class="apps-wrapper">
				<tr>
				<?php foreach ( Authentication::get_apps() as $app ) { ?>
					<td>
					<a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=authentication_help#<?php echo $app['hash']; ?>" target="_blank" class="app-logo"><img src="<?php echo \esc_url( WP_2FA_URL . 'dist/images/' . $app['logo'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"></a>
					</td>
				<?php } ?>
				</tr>
			</table>
			<?php
			$mth = \ob_get_clean();

			$default_settings['method_help_totp_intro']       = '<h3>' . __( 'Setting up TOTP (one-time code via app)', 'wp-2fa' ) . '</h3>';
			$default_settings['method_help_totp_step_1']      = __( 'Download and start the application of your choice', 'wp-2fa' );
			$default_settings['method_help_totp_step_2']      = __( 'From within the application scan the QR code provided on the left. Otherwise, enter the following code manually in the application:', 'wp-2fa' );
			$default_settings['method_help_totp_step_3']      = __( 'Click the "I\'m ready" button below when you complete the application setup process to proceed with the wizard.', 'wp-2fa' );
			$default_settings['method_verification_totp_pre'] = '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code from your chosen authentication app to finalize the setup.', 'wp-2fa' ) . '</p>';
			$default_settings['totp_reconfigure_intro']       = '<h3>' . sprintf( /* translators: %s: the word "Configure" or "Reconfigure". */ __( '%s the 2FA App', 'wp-2fa' ), '{reconfigure_or_configure_capitalized}' ) . '</h3><p>' . sprintf( /* translators: %s: the word "configure" or "reconfigure". */ __( 'Click the below button to %s the current 2FA method. Note that once reset you will have to re-scan the QR code on all devices you want this to work on because the previous codes will stop working.', 'wp-2fa' ), '{reconfigure_or_configure}' ) . '</p>';
			$default_settings['totp-option-label']            = __( 'One-time code via 2FA app', 'wp-2fa' );
			$default_settings['method_help_totp_more_intro']  = $mth;
			$default_settings['totp-option-label-hint']       = sprintf(
			/* translators: link to the knowledge base website */
				\esc_html__( 'Refer to the %s for step-by-step instructions on how to set up this method.', 'wp-2fa' ),
				'<a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-wordpress-user/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_how_to_setup_totp_app_hint" target="_blank">' . \esc_html__( 'setup guide', 'wp-2fa' ) . '</a>'
			);

			return $default_settings;
		}

		/**
		 * Shows the Method option label and hint in the White Label settings.
		 *
		 * @return void
		 *
		 * @since 2.9.0
		 */
		public static function white_label_option_labels() {
			?>
			<strong class="description"><?php esc_html_e( 'TOTP (one-time code via app) Option label', 'wp-2fa' ); ?></strong>
			<br><br>
			<fieldset>
				<input type="text" id="totp-option-label" name="wp_2fa_white_label[totp-option-label]" class="large-text" value="<?php echo \esc_attr( WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true ) ); ?>">
			</fieldset>
			<br>
			<strong class="description"><?php esc_html_e( 'TOTP option hint', 'wp-2fa' ); ?></strong>
			<br>
			<fieldset>
				<?php
					echo Settings_Page_White_Label::create_standard_editor( 'totp-option-label-hint' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</fieldset>
			<br>
			<?php
		}
	}
}
