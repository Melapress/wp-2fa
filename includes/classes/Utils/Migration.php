<?php
namespace WP2FA\Utils;

use \WP2FA\Utils\UserUtils as UserUtils;
use WP2FA\Utils\SettingsUtils as SettingsUtils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Migration class
 */
if ( ! class_exists( '\WP2FA\Utils\Migration' ) ) {

    /**
     * Put all you migration methods here
     *
     * @package WP2FA\Utils
     * @since 1.6
     */
    class Migration extends AbstractMigration {

        /**
         * The name of the option from which we should extact version
         * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
         * Note: only numbers will be processed
         */
        protected static $versionOptionName = WP_2FA_PREFIX . 'plugin_version';

        /**
         * The constant name where the plugin version is stored
         * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
         * Note: only numbers will be processed
         */
        protected static $constNameOfPluginVersion = 'WP_2FA_VERSION';

        /**
         * The name of the plugin settings
         *
         * @var string
         */
        private static $pluginSettingsName = WP_2FA_SETTINGS_NAME;

        /**
         * The name of the plugin policy settings
         *
         * @var string
         */
        private static $pluginPolicyName = WP_2FA_POLICY_SETTINGS_NAME;

        /**
         * The name of the plugin white label settings
         *
         * @var string
         */
        private static $pluginWhiteLabelName = WP_2FA_WHITE_LABEL_SETTINGS_NAME;

        /**
         * The name of the plugin email settings
         *
         * @var string
         */
        private static $pluginEmailSettingsName = WP_2FA_EMAIL_SETTINGS_NAME;

        /**
         * Migration for version upto 1.6.0
         *
         * @return void
         * @since 1.6.0
         */
        protected static function migrateUpTo_160() {
            $settings = self::getSettings( self::$pluginSettingsName );
            if ( ! is_array( $settings ) ) {
                return;
            }

            $needsUpdate = false;

            $settings_to_convert = [ 'enforced_roles', 'enforced_users', 'excluded_users', 'excluded_roles' ];
            foreach ( $settings_to_convert as $setting_name ) {
                if ( array_key_exists( $setting_name, $settings ) && ! is_array( $settings[ $setting_name ] ) ) {
                    $settings[ $setting_name ] = array_filter(
                        explode( ',', $settings[ $setting_name ] )
                    );
                    $needsUpdate               = true;
                }
            }

            if ( ! isset( $settings['backup_codes_enabled'] ) ) {
                $settings['backup_codes_enabled'] = 'yes';
                $needsUpdate                      = true;
            }

            if ( $needsUpdate ) {
                // Update settings.
                self::setSettings( self::$pluginSettingsName, $settings );
            }
        }

        /**
         * Migration for version upto 1.6.2
         *
         * @return void
         * @since 1.6.2
         */
        protected static function migrateUpTo_162() {
            $settings = self::getSettings( self::$pluginSettingsName );
            if ( ! is_array( $settings ) ) {
                return;
            }

            $needsUpdate = false;

            $settings_to_convert = [ 'excluded_sites' ];
            foreach ( $settings_to_convert as $setting_name ) {
                if ( array_key_exists( $setting_name, $settings ) && ! is_array( $settings[ $setting_name ] ) ) {
                    $originalSettingsSplit     = array_filter(
                        explode( ',', $settings[ $setting_name ] )
                    );
                    $settings[ $setting_name ] = [];
                    foreach ( $originalSettingsSplit as $value ) {
                        $settings[ $setting_name ][] = mb_substr( $value, mb_strrpos( $value, ':' ) + 1 );
                    }
                    $needsUpdate = true;
                }
            }

            self::migrateUpTo_160();

            if ( $needsUpdate ) {
                // Update settings.
                self::setSettings( self::$pluginSettingsName, $settings );
            }
        }

        /**
         * Migration for version upto 1.5.0
         *
         * @return void
         */
        protected static function migrateUpTo_150() {
            $settings = self::getSettings( self::$pluginSettingsName );

            if ( is_array( $settings ) && array_key_exists( 'enforcment-policy', $settings ) ) {
                // Correct setting name.
                $settings['enforcement-policy'] = $settings['enforcment-policy'];
                // Remove old setting.
                unset( $settings['enforcment-policy'] );
                // Update settings.
                self::setSettings( self::$pluginSettingsName, $settings );
            }
        }

        /**
         * Migration for version upto 1.7.0
         *
         * @return void
         */
        protected static function migrateUpTo_170() {
            $settings = self::getSettings( self::$pluginSettingsName );

            if ( is_array( $settings ) && array_key_exists( 'notify_users', $settings ) ) {
                // Remove old setting.
                unset( $settings['notify_users'] );
                // Update settings.
                self::setSettings( self::$pluginSettingsName, $settings );
            }

            $email_settings = self::getSettings( self::$pluginEmailSettingsName );
            $items_to_remove = [ 'send_enforced_email', 'enforced_email_subject', 'enforced_email_body' ];

            if ( is_array( $email_settings ) && UserUtils::in_array_all( $items_to_remove, $email_settings ) ) {
                foreach ( $items_to_remove as $item ) {
                    if ( isset( $email_settings[ $item ] ) ) {
                        unset( $email_settings[ $item ] );
                    }
                }
                // Update settings.
                self::setSettings( self::$pluginEmailSettingsName, $email_settings );
            }
        }

        /**
         * Migration for version upto 2.0.0
         * Separates the current settings into 3 different types of settings:
         *  - Policy
         *  - General
         *  - White label
         *
         * @return void
         */
        protected static function migrateUpTo_200() {
            $settings = self::getSettings( self::$pluginSettingsName );

            if ( is_array( $settings ) ) {

                $new_settings_array = array_flip(
                    array(
                        'enable_grace_cron',
                        'limit_access',
                        'delete_data_upon_uninstall',
                        'enable_destroy_session',
                    )
                );

                $new_white_label_array = array_flip(
                    array(
                        'default-text-code-page',
                    )
                );

                $settings_array = array_intersect_key(
                    $settings,
                    $new_settings_array
                );

                $settings = array_diff_key( $settings, $new_settings_array );

                self::setSettings( self::$pluginSettingsName, $settings_array );

                $white_label_settings = array_intersect_key(
                    $settings,
                    $new_white_label_array
                );

                $settings = array_diff_key( $settings, $new_white_label_array );

                self::setSettings( self::$pluginWhiteLabelName, $white_label_settings );

                self::setSettings( self::$pluginPolicyName, $settings );
            }
        }

        /**
         * Returns the plugin settings by a given setting type
         *
         * @return mixed
         */
        private static function getSettings( $setting_name ) {
            return SettingsUtils::get_option( $setting_name );
        }

        /**
         * Updates the plugin settings
         *
         * @param mixed $settings
         *
         * @return void
         */
        private static function setSettings( $setting_name, $settings ) {
            SettingsUtils::update_option( $setting_name, $settings );
        }
    }
}
