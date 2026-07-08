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
use WP2FA\Utils\Settings_Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP2FA\Admin\Helpers\SMS_Templates' ) ) {
	/**
	 * Responsible for the SMS templates.
	 */
	class SMS_Templates {

		/**
		 * Local static cache for SMS template settings.
		 *
		 * @var array
		 */
		protected static $wp_2fa_sms_templates = null;

		/**
		 * Function to return the SMS templates.
		 *
		 * @param string $sms_template Optional parameter to return only a specific SMS template.
		 *
		 * @return array Array with the SMS templates.
		 *
		 * @since 3.1.1.2
		 */
		public static function get_sms_templates( $sms_template = '' ): array {
			// Mapping of the SMS names to the templates. We must unify that.
			$internal_mapping = array(
				'default-twilio-registration-text' => array(
					'body'        => 'default-twilio-registration-text',
					'title'       => __( 'SMS registration text', 'wp-2fa' ),
					'description' => __( 'This is the template for the SMS message sent during the 2FA setup for users to confirm their mobile number.', 'wp-2fa' ),
				),
				'default-twilio-code-text'         => array(
					'body'        => 'default-twilio-code-text',
					'title'       => __( 'SMS code text', 'wp-2fa' ),
					'description' => __( 'This is the template for the SMS message with the one-time code sent to users when authenticating.', 'wp-2fa' ),
				),
			);

			if ( isset( $sms_template ) && ! empty( $sms_template ) && array_key_exists( $sms_template, $internal_mapping ) ) {
				return array(
					'body'        => self::get_wp2fa_sms_templates( $internal_mapping[ $sms_template ]['body'] ),
					'title'       => $internal_mapping[ $sms_template ]['title'],
					'description' => $internal_mapping[ $sms_template ]['description'],
				);
			}

			foreach ( $internal_mapping as $template_key => $template_data ) {

				$template = self::get_wp2fa_sms_templates( $template_key );
				if ( \is_array( $template ) && isset( $template['body'] ) && ! empty( $template['body'] ) ) {
					$body = (string) $template['body'];
				} else {
					$body = (string) WP2FA::get_wp2fa_white_label_setting( $template_key, true );
				}

				$internal_mapping[ $template_key ]['body'] = $body;
			}

			/**
			 * Add an option for external providers to implement their own sms template settings for the settings tab.
			 *
			 * The structure of the array should be the same as the internal mapping, with the addition of the 'title' and 'description' keys for each template, which will be used in the settings page to display the template name and description.
			 * - key - unique identifier for the template, used for filtering and retrieving the template.
			 * - body - the setting name for the sms body in the database.
			 * - title - the title of the sms template, used for display purposes.
			 * - description - the description of the sms template, used for display purposes.
			 *
			 * @param array $result - The array with all the sms templates.
			 *
			 * @since 3.1.1.2
			 */
			$result = \apply_filters( WP_2FA_PREFIX . 'sms_templates', $internal_mapping );

			return $result;
		}

		/**
		 * Util function to grab SMS settings or apply defaults if no settings are saved into the db.
		 *
		 * @param  string $setting_name Settings to grab value of.
		 *
		 * @since 2.0.0
		 */
		public static function get_wp2fa_sms_templates( $setting_name = '' ) {

			if ( is_null( self::$wp_2fa_sms_templates ) ) {
				self::$wp_2fa_sms_templates = Settings_Utils::get_option( WP_2FA_EMAIL_SETTINGS_NAME );

				if ( false === self::$wp_2fa_sms_templates || ! is_array( self::$wp_2fa_sms_templates ) ) {
					self::$wp_2fa_sms_templates = self::get_sms_templates();
				}
			}

			// If we have no setting name, return what ever is saved.
			if ( empty( $setting_name ) ) {
				return self::$wp_2fa_sms_templates;
			}

			// If we have a saved setting, return it.
			if ( $setting_name && isset( self::$wp_2fa_sms_templates[ $setting_name ] ) ) {
				return self::$wp_2fa_sms_templates[ $setting_name ];
			}
		}

		/**
		 * Get the SMS template tags with their replacements.
		 *
		 * @param bool       $only_tags - Whether to return only the tags or the tags with their replacements.
		 * @param int|string $user_id User id, if its needed.
		 * @param string     $token Login code, if its needed..
		 *
		 * @return array The SMS template tags with their replacements or only the tags.
		 *
		 * @since 3.1.1.2
		 */
		public static function get_sms_template_tags( bool $only_tags = false, $user_id = '', $token = '' ) {

			if ( $only_tags ) {
				return array(
					'{site_url}',
					'{site_name}',
					'{user_login_name}',
					'{user_login_code}',
					'{domain}',
				);
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

			return array(
				'{site_url}'        => \esc_url( \get_bloginfo( 'url' ) ),
				'{site_name}'       => \sanitize_text_field( \get_bloginfo( 'name' ) ),
				'{user_login_name}' => \sanitize_text_field( $user->user_login ),
				'{user_login_code}' => $login_code,
				'{domain}'          => \esc_url( WP_Helper::wp_get_high_level_domain( \get_bloginfo( 'url' ) ) ),
			);
		}
	}
}
