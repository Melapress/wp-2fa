<?php
/**
 * Responsible for the plugin wizard ordering
 *
 * @package    wp2fa
 * @subpackage traits
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Methods\Traits;

use WP2FA\WP2FA;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\Methods_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
/**
 * Responsible for the login attempts
 *
 * @since 2.6.0
 */
trait Methods_Wizards_Trait {

	/**
	 * Inits the class hooks
	 *
	 * @return void
	 *
	 * @since 2.6.0
	 */
	public static function common_init() {
		\add_filter( WP_2FA_PREFIX . 'methods_policy_settings_new', array( __CLASS__, 'get_policy_settings' ), 10, 2 );
	}

	/**
	 * Returns the order in the wizard
	 *
	 * @param string $role - The name of the role - could be empty.
	 * @param array  $methods - The array with currently collected methods.
	 *
	 * @return integer
	 *
	 * @since 2.6.0
	 */
	public static function get_order( ?string $role = null, array $methods = array() ): int {

		$methods_order = Settings_Utils::get_setting_role( $role, Methods_Helper::POLICY_SETTINGS_NAME, true );

		if ( \is_array( $methods_order ) ) {
			$methods_order = \array_flip( $methods_order );

			if ( isset( $methods_order[ self::get_main_class()::METHOD_NAME ] ) ) {
				static::$order = (int) $methods_order[ self::get_main_class()::METHOD_NAME ];
			}
		}

		if ( isset( $methods[ static::$order ] ) ) {
			// Obviously we have a problem here - such order already exists in the methods array, so grab the biggest order and increase it.
			// TODO: maybe we need to update the settings for that method as well ?

			static::$order = max( array_keys( $methods ) );

			++static::$order;
		}

		return static::$order;
	}

	/**
	 * Returns the main class of the given wizard steps class.
	 *
	 * @return string
	 *
	 * @since 2.6.0
	 */
	public static function get_main_class(): string {
		return static::$main_class;
	}

	/**
	 * Creates hidden field for the method order
	 *
	 * @param string $role - The name of the role (if present).
	 *
	 * @return string
	 *
	 * @since 2.6.0
	 */
	public static function hidden_order_setting( ?string $role = null ): string {
		$name_prefix = WP_2FA_POLICY_SETTINGS_NAME;
		if ( null !== $role && '' !== trim( (string) $role ) ) {
			$name_prefix .= "[{$role}]";
		}
		$hidden_field = '<input type="hidden" name="' . \esc_attr( $name_prefix ) . '[methods_order][]" value="' . \esc_attr( self::get_main_class()::METHOD_NAME ) . '">';

		return $hidden_field;
	}

	/**
	 * Returns the policy settings for the method.
	 *
	 * @param array       $settings - The current array with the settings.
	 * @param null|string $role - The name of the role (if present).
	 *
	 * @return array
	 *
	 * @since 2.6.0
	 */
	public static function get_policy_settings( array $settings, $role = null ) {

		$method_settings = array(
			'id'      => self::get_main_class()::METHOD_NAME,
			'title'   => \esc_html( WP2FA::get_wp2fa_white_label_setting( self::get_main_class()::METHOD_INTERNAL_NAME . '-option-label', true ) ),
			'enabled' => self::get_main_class()::is_enabled( $role ),
			'setting' => self::get_main_class()::POLICY_SETTINGS_NAME,
		);

		$extra_settings = static::get_extra_policy_settings_html( $role );
		if ( ! empty( $extra_settings ) ) {
			$method_settings['extra_settings'] = $extra_settings;
		}

		$integration_settings = static::get_integration_settings_html( $role );
		if ( ! empty( $integration_settings ) ) {
			$method_settings['integration_settings'] = $integration_settings;
		}

		$description = static::get_method_description();
		if ( ! empty( $description ) ) {
			$method_settings['description'] = $description;
		}

		$title_hint = static::get_method_title_hint();
		if ( ! empty( $title_hint ) ) {
			$method_settings['title_hint'] = $title_hint;
		}

		// Check if the provider requires external setup and whether it is configured.
		$setup_url = static::get_setup_url();
		if ( ! empty( $setup_url ) ) {
			$main_class                        = self::get_main_class();
			$method_settings['is_configured']  = \method_exists( $main_class, 'is_set' ) && $main_class::is_set();
			$method_settings['setup_url']      = $setup_url;
		}

		$settings[ self::get_main_class()::METHOD_NAME ] = $method_settings;

		return $settings;
	}

	/**
	 * Returns HTML for extra policy settings displayed beneath the method
	 * checkbox in the sortable list.
	 *
	 * Override in individual wizard-steps classes to provide method-specific
	 * settings (e.g. code validity period, allow custom email).
	 *
	 * @param null|string $role - The name of the role (if present).
	 *
	 * @return string Empty string when no extra settings exist.
	 *
	 * @since 3.2.0
	 */
	public static function get_extra_policy_settings_html( $role = null ): string {
		return '';
	}

	/**
	 * Returns the URL to the provider setup page.
	 *
	 * Override in individual wizard-steps classes for methods that require
	 * external configuration (e.g. Authy, Twilio, Clickatell, Yubico).
	 *
	 * @return string Empty string when no setup is required.
	 *
	 * @since 3.2.0
	 */
	public static function get_setup_url(): string {
		return '';
	}

	/**
	 * Returns HTML for integration settings displayed in a collapsible section
	 * beneath the method card in the policy settings.
	 *
	 * Override in individual wizard-steps classes for methods that have
	 * integration credentials (e.g. Twilio Account SID, Auth token).
	 *
	 * @param null|string $role - The name of the role (if present).
	 *
	 * @return string Empty string when no integration settings exist.
	 *
	 * @since 3.2.1
	 */
	public static function get_integration_settings_html( $role = null ): string {
		return '';
	}

	/**
	 * Returns a description string for the method, shown below the title
	 * in the policy method card. May contain HTML links.
	 *
	 * Override in individual wizard-steps classes to provide a method-specific
	 * description with documentation links.
	 *
	 * @return string Empty string when no description is needed.
	 *
	 * @since 3.2.1
	 */
	public static function get_method_description(): string {
		return '';
	}

	/**
	 * Returns an inline hint shown next to the method title in the sortable list.
	 * May contain HTML links.
	 *
	 * Override in individual wizard-steps classes to provide method-specific hints.
	 *
	 * @return string Empty string when no hint is needed.
	 *
	 * @since 4.0.0
	 */
	public static function get_method_title_hint(): string {
		return '';
	}
}
