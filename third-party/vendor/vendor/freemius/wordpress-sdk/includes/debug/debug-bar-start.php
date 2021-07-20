<?php

namespace WP2FA_Vendor;

/**
 * @package     Freemius
 * @copyright   Copyright (c) 2015, Freemius, Inc.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since       1.1.7.3
 */
if (!\defined('WP2FA_Vendor\\ABSPATH')) {
    exit;
}
if (!\WP2FA_Vendor\WP_FS__DEBUG_SDK) {
    return;
}
/**
 * Initialize Freemius custom debug panels.
 *
 * @param array $panels Debug bar panels objects
 *
 * @return array Debug bar panels with your custom panels
 */
function fs_custom_panels_init($panels)
{
    if (\class_exists('WP2FA_Vendor\\Debug_Bar_Panel')) {
        if (\WP2FA_Vendor\FS_API__LOGGER_ON) {
            require_once \dirname(__FILE__) . '/class-fs-debug-bar-panel.php';
            $panels[] = new \WP2FA_Vendor\Freemius_Debug_Bar_Panel();
        }
    }
    return $panels;
}
function fs_custom_status_init($statuses)
{
    if (\class_exists('WP2FA_Vendor\\Debug_Bar_Panel')) {
        if (\WP2FA_Vendor\FS_API__LOGGER_ON) {
            require_once \dirname(__FILE__) . '/class-fs-debug-bar-panel.php';
            $statuses[] = array('fs_api_requests', \WP2FA_Vendor\fs_text_inline('Freemius API'), \WP2FA_Vendor\Freemius_Debug_Bar_Panel::requests_count() . ' ' . \WP2FA_Vendor\fs_text_inline('Requests') . ' (' . \WP2FA_Vendor\Freemius_Debug_Bar_Panel::total_time() . ')');
        }
    }
    return $statuses;
}
\add_filter('debug_bar_panels', 'fs_custom_panels_init');
\add_filter('debug_bar_statuses', 'fs_custom_status_init');
