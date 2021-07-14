<?php

namespace WP2FA\Admin\Controllers;

use WP2FA\WP2FA;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * WP2FA Settings controller
 */
class Settings {

    /**
     * The name of the WP2FA WP admin settings page
     *
     * @var string
     */
    private static $settingsPageName = 'wp-2fa-settings';

    /**
     * The link to the WP admin settings page
     *
     * @var string
     */
    private static $settingsPageLink = '';

    /**
     * The name of the WP2FA WP admin setup page
     *
     * @var string
     */
    private static $setupPageName = 'wp-2fa-setup';

    /**
     * The link to the WP admin setup page
     *
     * @var string
     */
    private static $setupPageLink = '';

    /**
     * The link to the custom settings page (if one is presented)
     *
     * @var string
     */
    private static $customSetupPageLink = null;

    /**
     * Returns the link to the WP admin settings page, based on the current WP install
     *
     * @return string
     */
    public static function getSettingsPageLink() {
        if ( '' === self::$settingsPageLink ) {
            if ( WP2FA::is_this_multisite() ) {
                self::$settingsPageLink = add_query_arg( 'page', self::$settingsPageName, network_admin_url( 'settings.php' ) );
            } else {
                self::$settingsPageLink = add_query_arg( 'page', self::$settingsPageName, admin_url( 'options-general.php' ) );
            }
        }

        return self::$settingsPageLink;
    }

    /**
     * Returns the link to the WP admin settings page, based on the current WP install
     *
     * @return string
     */
    public static function getSetupPageLink() {
        if ( '' === self::$setupPageLink ) {
            if ( WP2FA::is_this_multisite() ) {
                self::$setupPageLink = add_query_arg( 'show', self::$setupPageName, network_admin_url( 'profile.php' ) );
            } else {
                self::$setupPageLink = add_query_arg( 'show', self::$setupPageName, admin_url( 'profile.php' ) );
            }
        }

        return self::$setupPageLink;
    }

    /**
     * Extracts the custom settings page URL
     *
     * @return string
     */
    public static function getCustomPageLink(): string {
        if ( null === self::$customSetupPageLink ) {
            self::$customSetupPageLink = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );

            if ( ! empty( self::$customSetupPageLink ) ) {
                $customSlug = '';
                if ( WP2FA::is_this_multisite() ) {
                    switch_to_blog( get_main_site_id() );

                    $customSlug                = get_post_field( 'post_name', get_post( self::$customSetupPageLink ) );
                    self::$customSetupPageLink = trailingslashit( get_site_url() ) . $customSlug;

                    restore_current_blog();
                } else {
                    $customSlug                = get_post_field( 'post_name', get_post( self::$customSetupPageLink ) );
                    self::$customSetupPageLink = trailingslashit( get_site_url() ) . $customSlug;
                }
            }
        }

        return self::$customSetupPageLink;
    }
}
