<?php

namespace WP2FA_Vendor;

/**
 * @package     Freemius
 * @copyright   Copyright (c) 2015, Freemius, Inc.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since       2.2.1
 */
if (!\defined('WP2FA_Vendor\\ABSPATH')) {
    exit;
}
if (!\function_exists('WP2FA_Vendor\\fs_get_plugins')) {
    /**
     * @author Leo Fajardo (@leorw)
     * @since 2.2.1
     *
     * @param bool $delete_cache
     *
     * @return array
     */
    function fs_get_plugins($delete_cache = \false)
    {
        $cached_plugins = \wp_cache_get('plugins', 'plugins');
        if (!\is_array($cached_plugins)) {
            $cached_plugins = array();
        }
        $plugin_folder = '';
        if (isset($cached_plugins[$plugin_folder])) {
            $plugins = $cached_plugins[$plugin_folder];
        } else {
            if (!\function_exists('WP2FA_Vendor\\get_plugins')) {
                require_once \ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugins = \WP2FA_Vendor\get_plugins();
            if ($delete_cache && \WP2FA_Vendor\is_plugin_active('woocommerce/woocommerce.php')) {
                \WP2FA_Vendor\wp_cache_delete('plugins', 'plugins');
            }
        }
        return $plugins;
    }
}
