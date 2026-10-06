<?php
/**
 * Responsible for the User's operations.
 *
 * @package    wp2fa
 * @subpackage helpers
 *
 * @since 3.1.1.2
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Helpers;

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Page;
use WP2FA\Utils\Request_Utils;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Utils\Date_Time_Utils;
use WP2FA\Admin\Controllers\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP2FA\Admin\Helpers\Email_Templates' ) ) {
	/**
	 * Responsible for the email templates.
	 */
	class Email_Templates {

		/**
		 * Local static cache for email template settings.
		 *
		 * @var array
		 */
		protected static $wp_2fa_email_templates = null;

		/**
		 * Tags that exist only in the body of one particular template.
		 *
		 * A tag belongs here rather than in the shared list when only one email substitutes
		 * it. {backup_codes} is replaced in Backup_Codes::send_backup_codes_email(), and only
		 * in the message body — the subject is built from the same template set but never runs
		 * that replacement. Offering the tag everywhere would put a literal "{backup_codes}"
		 * into the delivered mail of every other template, which is the same trap {login_code}
		 * already sets in a subject line.
		 *
		 * Keyed by template name as used by get_email_templates().
		 *
		 * @var array<string,string[]>
		 *
		 * @since 4.2.1
		 */
		private const TEMPLATE_BODY_TAGS = array(
			'user_backup_codes' => array( '{backup_codes}' ),
		);

		/**
		 * The tags offered for one template's body: the shared ones plus anything only that
		 * template can resolve.
		 *
		 * @param string $template_name - Template name as keyed by get_email_templates().
		 *
		 * @return string[]
		 *
		 * @since 4.2.1
		 */
		public static function get_mail_template_body_tags( string $template_name ): array {
			return array_merge(
				self::get_mail_template_tags( true ),
				self::TEMPLATE_BODY_TAGS[ $template_name ] ?? array()
			);
		}

		/**
		 * Function to return the email templates.
		 *
		 * @param string $email_template Optional parameter to return only a specific email template.
		 *
		 * @return array Array with the email templates.
		 *
		 * @since 3.1.1.2
		 */
		public static function get_email_templates( $email_template = '' ): array {
			// Mapping of the email names to the templates. We must unify that.
			$internal_mapping = array(
				'user_backup_codes'     => array(
					'subject'     => 'user_backup_codes_email_subject',
					'body'        => 'user_backup_codes_email_body',
					'title'       => __( 'User backup codes email', 'wp-2fa' ),
					'description' => __( 'This email can be sent to a user once backup codes are generated.', 'wp-2fa' ),
				),
				'login_code_setup'      => array(
					'subject'     => 'login_code_setup_email_subject',
					'body'        => 'login_code_setup_email_body',
					'title'       => __( '2FA setup code email', 'wp-2fa' ),
					'description' => __( 'This is the email sent to a user when setting up 2FA via email.', 'wp-2fa' ),
				),
				'login_code'            => array(
					'subject'     => 'login_code_email_subject',
					'body'        => 'login_code_email_body',
					'title'       => __( 'Login code email', 'wp-2fa' ),
					'description' => __( 'This is the email sent to a user when a login code is required.', 'wp-2fa' ),
				),
				'user_account_locked'   => array(
					'subject'     => 'user_account_locked_email_subject',
					'body'        => 'user_account_locked_email_body',
					'title'       => __( 'User account locked email', 'wp-2fa' ),
					'description' => __( 'This is the email sent to a user when their account has been locked.', 'wp-2fa' ),
				),
				'user_account_unlocked' => array(
					'subject'     => 'user_account_unlocked_email_subject',
					'body'        => 'user_account_unlocked_email_body',
					'title'       => __( 'User account unlocked email', 'wp-2fa' ),
					'description' => __( 'This is the email sent to a user when the user\'s account has been unlocked.', 'wp-2fa' ),
				),
			);

			if ( isset( $email_template ) && ! empty( $email_template ) && array_key_exists( $email_template, $internal_mapping ) ) {
				return array(
					'subject'     => self::get_wp2fa_email_templates( $internal_mapping[ $email_template ]['subject'] ),
					'body'        => self::get_wp2fa_email_templates( $internal_mapping[ $email_template ]['body'] ),
					'title'       => $internal_mapping[ $email_template ]['title'],
					'description' => $internal_mapping[ $email_template ]['description'],
				);
			}

			foreach ( $internal_mapping as $template_key => $template_data ) {
				$internal_mapping[ $template_key ]['subject'] = self::get_wp2fa_email_templates( $template_data['subject'] );
				$internal_mapping[ $template_key ]['body']    = self::get_wp2fa_email_templates( $template_data['body'] );
			}

			/**
			 * Add an option for external providers to implement their own email template settings for the settings tab.
			 *
			 * The structure of the array should be the same as the internal mapping, with the addition of the 'title' and 'description' keys for each template, which will be used in the settings page to display the template name and description.
			 * - key - unique identifier for the template, used for filtering and retrieving the template.
			 * - subject - the setting name for the email subject in the database.
			 * - body - the setting name for the email body in the database.
			 * - title - the title of the email template, used for display purposes.
			 * - description - the description of the email template, used for display purposes.
			 *
			 * @param array $result - The array with all the email templates.
			 *
			 * @since 3.1.1.2
			 */
			$result = \apply_filters( WP_2FA_PREFIX . 'email_templates', $internal_mapping );

			return $result;
		}

		/**
		 * Util which we use to replace our {strings} with actual, useful stuff.
		 *
		 * @param string     $input   Text we are working on.
		 * @param int|string $user_id User id, if its needed.
		 * @param string     $token   Login code, if its needed..
		 * @param string     $override_grace_period - Value to override grace period with.
		 *
		 * @return string          The output, with all the {strings} swapped out.
		 *
		 * @since 3.1.1.2
		 */
		public static function replace_email_strings( $input = '', $user_id = '', $token = '', $override_grace_period = '' ): string {

			/**
			 * 3rd party plugins could change the mail strings, or provide their own.
			 *
			 * @param array $replacements - The array with all the currently supported strings.
			 */
			$replacements = \apply_filters(
				WP_2FA_PREFIX . 'replacement_email_strings',
				self::get_mail_template_tags( false, $user_id, $token, $override_grace_period )
			);

			$final_output = str_replace( array_keys( $replacements ), array_values( $replacements ), $input );

			return $final_output;
		}

		/**
		 * Get the mail template tags with their replacements.
		 *
		 * @param bool       $only_tags - Whether to return only the tags or the tags with their replacements.
		 * @param int|string $user_id User id, if its needed.
		 * @param string     $token Login code, if its needed..
		 * @param string     $override_grace_period - Value to override grace period with.
		 *
		 * @return array The mail template tags with their replacements or only the tags.
		 *
		 * @since 3.1.1.2
		 */
		public static function get_mail_template_tags( bool $only_tags = false, $user_id = '', $token = '', $override_grace_period = '' ) {

			if ( $only_tags ) {
				return array(
					'{site_url}',
					'{site_name}',
					'{grace_period}',
					'{user_login_name}',
					'{user_first_name}',
					'{user_last_name}',
					'{user_display_name}',
					'{login_code}',
					'{2fa_settings_page_url}',
					'{user_ip_address}',
					'{admin_email}',
					'{wp_admin_email}',
				);
			}

			if ( empty( $GLOBALS['wp_rewrite'] ) ) {
				$GLOBALS['wp_rewrite'] = new \WP_Rewrite(); // phpcs:ignore -- WordPress.WP.GlobalVariablesOverride.Prohibited
			}

			// Setup user data.
			if ( isset( $user_id ) && ! empty( $user_id ) ) {
				$user = \get_userdata( intval( $user_id ) );
			} else {
				$user = \wp_get_current_user();
			}

			// Setup token.
			$token = trim( (string) $token );
			if ( isset( $token ) && ! empty( $token ) ) {
				$login_code = $token;
			} else {
				$login_code = '';
			}

			// Gather grace period.
			$grace_period_string = '';
			if ( isset( $override_grace_period ) && ! empty( $override_grace_period ) ) {
				$grace_period_string = \sanitize_text_field( $override_grace_period );
			} else {
				$grace_policy        = WP2FA::get_wp2fa_setting( 'grace-policy' );
				$grace_period_string = Date_Time_Utils::format_grace_period_expiration_string( $grace_policy );
			}

			$new_page_id = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'custom-user-page-id' );
			if ( ! empty( $new_page_id ) ) {
				$new_page_permalink = \esc_url( \get_permalink( intval( $new_page_id ) ) );
			} else {
				$new_page_id = Settings::get_custom_settings_page_id( '', $user );
				if ( ! empty( $new_page_id ) ) {
					$new_page_permalink = \esc_url( \get_permalink( intval( $new_page_id ) ) );
				} else {
					$new_page_permalink = '';
				}
			}

			$admin_email = null;
			if ( 'use-custom-email' === self::get_wp2fa_email_templates( 'email_from_setting' ) ) {
				$admin_email = \sanitize_email( self::get_wp2fa_email_templates( 'custom_from_email_address' ) );
			} else {
				$admin_email = Settings_Page::get_default_email_address();
			}

			return array(
				'{site_url}'              => \esc_url( \get_bloginfo( 'url' ) ),
				'{site_name}'             => \sanitize_text_field( \get_bloginfo( 'name' ) ),
				'{grace_period}'          => \sanitize_text_field( $grace_period_string ),
				'{user_login_name}'       => \sanitize_text_field( $user->user_login ),
				'{user_first_name}'       => \sanitize_text_field( $user->user_firstname ),
				'{user_last_name}'        => \sanitize_text_field( $user->user_lastname ),
				'{user_display_name}'     => \sanitize_text_field( $user->display_name ),
				'{login_code}'            => $login_code,
				'{2fa_settings_page_url}' => $new_page_permalink,
				'{user_ip_address}'       => \esc_attr( Request_Utils::get_ip() ),
				'{admin_email}'           => $admin_email,
				'{wp_admin_email}'        => \sanitize_email( \is_multisite() ? \get_blog_option( \get_current_blog_id(), 'admin_email' ) : \get_option( 'admin_email' ) ) ?: $admin_email,
			);
		}

		/**
		 * Points the default "contact the administrator" lines at the site admin.
		 *
		 * The login code emails told users who had not asked for a code, or could
		 * not log in, to write to {admin_email} - which is the address the plugin
		 * sends from, very often a no-reply wordpress@ or wp2fa@ mailbox nobody
		 * reads. The defaults now use {wp_admin_email}, the site's administration
		 * address.
		 *
		 * A site that ever saved its email settings has its own copy of each
		 * template, which the new default does not reach. In those copies, only
		 * the two untouched default sentences are changed - in English or in the
		 * site's language. Anything the site wrote itself, including a deliberate
		 * {admin_email}, is left exactly as it is.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function point_default_contact_lines_at_site_admin(): void {
			$stored = Settings_Utils::get_option( WP_2FA_EMAIL_SETTINGS_NAME );
			if ( ! is_array( $stored ) ) {
				return;
			}

			$replacements = array();
			foreach ( array_unique( array( \determine_locale(), 'en_US' ) ) as $locale ) {
				$switched = \determine_locale() !== $locale && \switch_to_locale( $locale );

				/* translators: %1$s: the IP address the request came from; %2$s: the site administrator's email address. */
				$request_line = \esc_html__( 'This request was made from IP address %1$s. If you did not request this, please contact the site administrator at %2$s.', 'wp-2fa' );
				/* translators: %s: the site administrator's email address. */
				$contact_line = \esc_html__( 'If you encounter any other issues logging in, feel free to contact us at %s.', 'wp-2fa' );

				$replacements[ sprintf( $request_line, '{user_ip_address}', '{admin_email}' ) ] = sprintf( $request_line, '{user_ip_address}', '{wp_admin_email}' );
				$replacements[ sprintf( $contact_line, '{admin_email}' ) ]                      = sprintf( $contact_line, '{wp_admin_email}' );

				if ( $switched ) {
					\restore_previous_locale();
				}
			}

			$changed = false;
			foreach ( array( 'login_code_email_body', 'login_code_setup_email_body' ) as $key ) {
				if ( ! isset( $stored[ $key ] ) || ! is_string( $stored[ $key ] ) ) {
					continue;
				}
				$updated = strtr( $stored[ $key ], $replacements );
				if ( $updated !== $stored[ $key ] ) {
					$stored[ $key ] = $updated;
					$changed        = true;
				}
			}

			if ( $changed ) {
				Settings_Utils::update_option( WP_2FA_EMAIL_SETTINGS_NAME, $stored );
				self::$wp_2fa_email_templates = null;
			}
		}

		/**
		 * Repair a stored copy of the broken default "account unlocked" email.
		 *
		 * Saving the email settings stores every template, defaults included, so
		 * sites carry the broken default from before the fix: no site name after
		 * "on the website", and an empty last paragraph where "Thank you." should
		 * be. Only that exact default wording is touched, in the site's language
		 * and in English; a template someone has rewritten is left alone.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function repair_default_unlocked_email(): void {
			$stored = Settings_Utils::get_option( WP_2FA_EMAIL_SETTINGS_NAME );
			if ( ! is_array( $stored ) || ! isset( $stored['user_account_unlocked_email_body'] ) || ! is_string( $stored['user_account_unlocked_email_body'] ) ) {
				return;
			}

			$body     = $stored['user_account_unlocked_email_body'];
			$repaired = $body;

			foreach ( array_unique( array( \determine_locale(), 'en_US' ) ) as $locale ) {
				$switched = \determine_locale() !== $locale && \switch_to_locale( $locale );

				$on_the_website = \esc_html__( 'on the website', 'wp-2fa' );
				$unlocked       = __( 'has been unlocked. Please configure two-factor authentication within the grace period, otherwise your account will be locked again.', 'wp-2fa' );
				$thank_you      = __( 'Thank you.', 'wp-2fa' );

				if ( $switched ) {
					\restore_previous_locale();
				}

				$broken = $on_the_website . ' ' . $unlocked;
				if ( false === strpos( $repaired, $broken ) ) {
					continue;
				}

				$repaired = str_replace( $broken, $on_the_website . ' <strong>{site_name}</strong> ' . $unlocked, $repaired );

				/*
				 * The "Thank you." that fell off the end left an empty paragraph
				 * behind - which wpautop() on save usually turned into a stray
				 * closing tag, "</p></p>".
				 */
				if ( false === strpos( $repaired, $thank_you ) ) {
					$repaired = (string) preg_replace(
						array( '#<p>\s*</p>(\s*)$#', '#(</p>)\s*</p>(\s*)$#' ),
						array( '<p>' . $thank_you . '</p>$1', '$1<p>' . $thank_you . '</p>$2' ),
						$repaired,
						1
					);
				}
				break;
			}

			if ( $repaired !== $body ) {
				$stored['user_account_unlocked_email_body'] = $repaired;
				Settings_Utils::update_option( WP_2FA_EMAIL_SETTINGS_NAME, $stored );
				self::$wp_2fa_email_templates = null;
			}
		}

		/**
		 * Util function to grab EMAIL settings or apply defaults if no settings are saved into the db.
		 *
		 * @param  string $setting_name Settings to grab value of.
		 *
		 * @since 2.0.0
		 */
		public static function get_wp2fa_email_templates( $setting_name = '' ) {

			if ( is_null( self::$wp_2fa_email_templates ) ) {
				self::$wp_2fa_email_templates = Settings_Utils::get_option( WP_2FA_EMAIL_SETTINGS_NAME );
			}

			// If we have no setting name, return whatever is saved.
			if ( empty( $setting_name ) ) {
				return self::$wp_2fa_email_templates;
			}

			// If we have a saved setting, return it.
			if ( $setting_name && isset( self::$wp_2fa_email_templates[ $setting_name ] ) ) {
				return self::$wp_2fa_email_templates[ $setting_name ];
			}

			// Array of defaults, now we have things setup above.
			$default_settings = array(
				'email_from_setting'             => 'use-defaults',
				'custom_from_email_address'      => '',
				'custom_from_display_name'       => '',
				'send_account_locked_email'      => 'enable_account_locked_email',
				'send_account_unlocked_email'    => 'enable_account_unlocked_email',
				'send_login_code_email'          => 'enable_send_login_code_email',
				'send_reset_password_code_email' => 'enable_send_reset_password_code_email',
			);

			$default_settings = array_merge( $default_settings, self::login_code_email_template() );
			$default_settings = array_merge( $default_settings, self::reset_password_code_email_template() );
			$default_settings = array_merge( $default_settings, self::login_code_setup_email_template() );
			$default_settings = array_merge( $default_settings, self::user_locked_email_template() );
			$default_settings = array_merge( $default_settings, self::user_unlocked_email_template() );
			$default_settings = array_merge( $default_settings, self::user_backup_codes_email_template() );

			/**
			 * Allows 3rd party providers to their own settings for the mail templates.
			 *
			 * @param array $default_settings - Array with the default settings.
			 *
			 * @since 2.0.0
			 */
			$default_settings = \apply_filters( WP_2FA_PREFIX . 'mail_default_settings', $default_settings );

			return $default_settings[ $setting_name ];
		}

		/**
		 * Function to return the default user backup codes email subject and body.
		 *
		 * @return array Array with the default user backup codes email subject and body.
		 *
		 * @since 3.1.1.2
		 */
		private static function user_backup_codes_email_template(): array {
			// Create User backup codes Message.
			$user_backup_codes_subject = sprintf( /* translators: %1$s: the user's login name; %2$s: the site name. */ __( '2FA backup codes for user %1$s on %2$s', 'wp-2fa' ), '{user_login_name}', '{site_name}' );

			$user_backup_codes_body = \wp_sprintf(
				'<p>%s</p><p>%s <strong>%s</strong> %s <strong>%s</strong>. %s %s </p>%s<p>%s</p>',
				__( 'Hello,', 'wp-2fa' ),
				\esc_html__( 'Below please find the 2FA backup codes for your user', 'wp-2fa' ),
				'{user_login_name}',
				\esc_html__( 'on the website', 'wp-2fa' ),
				'{site_name}',
				__( 'The website\'s URL is', 'wp-2fa' ),
				'{site_url}',
				'{backup_codes}',
				__( 'Thank you for enabling 2FA on your account and helping us keeping the website secure.', 'wp-2fa' )
			);

			return array(
				'user_backup_codes_email_subject' => $user_backup_codes_subject,
				'user_backup_codes_email_body'    => $user_backup_codes_body,
			);
		}

		/**
		 * Function to return the default user unlocked email subject and body.
		 *
		 * @return array Array with the default user unlocked email subject and body.
		 *
		 * @since 3.1.1.2
		 */
		private static function user_unlocked_email_template(): array {
			// Create User unlocked Message.
			$user_unlocked_subject = sprintf( /* translators: %s: the site name. */ __( 'Your user on %s has been unlocked', 'wp-2fa' ), '{site_name}' );

			/*
			 * The page link paragraph is only there when a 2FA page is set, and its
			 * arguments have to come and go with it. They used to be passed either
			 * way, so without a page the format had three arguments too many: the
			 * last one, "Thank you.", fell off the end and an empty paragraph took
			 * its place. The site name had no slot at all - "on the website has
			 * been unlocked".
			 */
			$user_unlocked_body = \wp_sprintf(
				'<p>%s</p><p>%s <strong>%s</strong> %s <strong>%s</strong> %s</p>',
				__( 'Hello,', 'wp-2fa' ),
				\esc_html__( 'Your user', 'wp-2fa' ),
				'{user_login_name}',
				\esc_html__( 'on the website', 'wp-2fa' ),
				'{site_name}',
				__( 'has been unlocked. Please configure two-factor authentication within the grace period, otherwise your account will be locked again.', 'wp-2fa' )
			);

			if ( ! empty( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
				$user_unlocked_body .= \wp_sprintf(
					'<p>%s <a href="%s" target="_blank">%s</a></p>',
					__( 'You can configure 2FA from this page:', 'wp-2fa' ),
					'{2fa_settings_page_url}',
					'{2fa_settings_page_url}.'
				);
			}

			$user_unlocked_body .= '<p>' . __( 'Thank you.', 'wp-2fa' ) . '</p>';

			return array(
				'user_account_unlocked_email_subject' => $user_unlocked_subject,
				'user_account_unlocked_email_body'    => $user_unlocked_body,
			);
		}

		/**
		 * Function to return the default user locked email subject and body.
		 *
		 * @return array Array with the default user locked email subject and body.
		 *
		 * @since 3.1.1.2
		 */
		private static function user_locked_email_template(): array {
			// Create User Locked Message.
			$user_locked_subject = sprintf( /* translators: %s: the site name. */ __( 'Your user on %s has been locked', 'wp-2fa' ), '{site_name}' );

			$user_locked_body = \wp_sprintf(
				'<p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
				\esc_html__( 'Hello.', 'wp-2fa' ),
				sprintf(
					// translators: %1s - the name of the user
					// translators: %2s - the name of the site.
					\esc_html__( 'Since you have not enabled two-factor authentication for the user %1$1s on the website %2$2s within the grace period, your account has been locked.', 'wp-2fa' ),
					'{user_login_name}',
					'{site_name}'
				),
				\esc_html__( 'Contact your website administrator to unlock your account.', 'wp-2fa' ),
				\esc_html__( 'Thank you.', 'wp-2fa' )
			);

			return array(
				'user_account_locked_email_subject' => $user_locked_subject,
				'user_account_locked_email_body'    => $user_locked_body,
			);
		}

		/**
		 * Function to return the default login code setup email subject and body.
		 *
		 * @return array Array with the default login code setup email subject and body.
		 *
		 * @since 3.1.1.2
		 */
		private static function login_code_setup_email_template(): array {
			$login_code_setup_subject = sprintf( /* translators: %s: the site name. */ __( 'Your 2FA Setup Verification Code for %s', 'wp-2fa' ), '{site_name}' );

			$login_code_setup_body = \wp_sprintf(
				'<p>%s</p><p>%s</p><p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
				sprintf( /* translators: %s: the user's display name. */ \esc_html__( 'Hello %s,', 'wp-2fa' ), '{user_display_name}' ),
				sprintf( /* translators: %1$s: the user's login name; %2$s: the site name; %3$s: the site URL. */ \esc_html__( 'You have requested to set up two-factor authentication for your user %1$s on the website %2$s (%3$s).', 'wp-2fa' ), '{user_login_name}', '{site_name}', '{site_url}' ),
				sprintf(
					// translators: The login code provided from the plugin.
					\esc_html__( 'Please enter the following code to complete your setup: %1$1s', 'wp-2fa' ),
					'<strong>{login_code}</strong>'
				),
				sprintf( /* translators: %1$s: the IP address the request came from; %2$s: the site administrator's email address. */ \esc_html__( 'This request was made from IP address %1$s. If you did not request this, please contact the site administrator at %2$s.', 'wp-2fa' ), '{user_ip_address}', '{wp_admin_email}' ),
				\esc_html__( 'Thank you.', 'wp-2fa' ),
				sprintf( /* translators: %s: the site name. */ \esc_html__( 'The %s Team', 'wp-2fa' ), '{site_name}' )
			);

			return array(
				'login_code_setup_email_subject' => $login_code_setup_subject,
				'login_code_setup_email_body'    => $login_code_setup_body,
			);
		}

		/**
		 * Function to return the default login code email subject and body.
		 *
		 * @return array Array with the default login code email subject and body.
		 *
		 * @since 3.1.1.2
		 */
		private static function login_code_email_template(): array {
			// Create Login Code Message.
			$login_code_subject = sprintf( /* translators: %s: the site name. */ __( 'Your login confirmation code for %s', 'wp-2fa' ), '{site_name}' );

			$login_code_body = \wp_sprintf(
				'<p>%s</p><p>%s</p><p>%s</p><p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
				sprintf( /* translators: %s: the user's display name. */ \esc_html__( 'Hello %s,', 'wp-2fa' ), '{user_display_name}' ),
				sprintf( /* translators: %1$s: the site name; %2$s: the user's login name. */ \esc_html__( 'You are trying to log in to %1$s using the username %2$s. To complete your login, please enter the following one-time 2FA code:', 'wp-2fa' ), '{site_name}', '{user_login_name}' ),
				// A substitution tag on its own: nothing to translate, and a translator who
				// altered it would break the replacement silently.
				'{login_code}',
				\esc_html__( 'Enter this code on the login page to finish the authentication process and access your account.', 'wp-2fa' ),
				sprintf( /* translators: %1$s: the IP address the request came from; %2$s: the site administrator's email address. */ \esc_html__( 'This request was made from IP address %1$s. If you did not request this, please contact the site administrator at %2$s.', 'wp-2fa' ), '{user_ip_address}', '{wp_admin_email}' ),
				sprintf( /* translators: %s: the site administrator's email address. */ \esc_html__( 'If you encounter any other issues logging in, feel free to contact us at %s.', 'wp-2fa' ), '{wp_admin_email}' ),
				sprintf( /* translators: %s: the site name. */ \esc_html__( 'Kind regards, The %s Team', 'wp-2fa' ), '{site_name}' )
			);

			return array(
				'login_code_email_subject' => $login_code_subject,
				'login_code_email_body'    => $login_code_body,
			);
		}

		/**
		 * Function to return the default reset password code email subject and body.
		 *
		 * @return array Array with the default reset password code email subject and body.
		 *
		 * @since 3.1.1.2
		 */
		private static function reset_password_code_email_template(): array {
			// Create Reset PW Code Message.
			$reset_password_code_subject = __( '2FA code for password reset', 'wp-2fa' );

			$reset_password_code_body = \wp_sprintf(
				'<p>%s</p><p>%s</p><p><strong>%s</strong></p><p>%s</p>',
				\esc_html__( 'Hello,', 'wp-2fa' ),
				sprintf(
					// translators: The login code provided from the plugin.
					\esc_html__( 'Someone from the IP address %1$1s has requested a password reset for the user %2$2s on the website %3$3s. If this was you please use the below code to proceed with the password reset:', 'wp-2fa' ),
					'{user_ip_address}',
					'{user_login_name}',
					'{site_url}'
				),
				'{login_code}',
				\esc_html__( 'If this was not you, ignore this email and contact your website administrator.', 'wp-2fa' )
			);

			return array(
				'reset_password_code_email_subject' => $reset_password_code_subject,
				'reset_password_code_email_body'    => $reset_password_code_body,
			);
		}
	}
}
