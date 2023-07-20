<?php

namespace WP2FA_Vendor;

/**
 * PHP-Scoper configuration file.
 *
 * @package   wp2fa
 * @copyright %%YEAR%% Melapress
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://wordpress.org/plugins/wp-2fa/
 */
use WP2FA_Vendor\Isolated\Symfony\Component\Finder\Finder;
return array(
    'prefix' => 'WP2FA_Vendor',
    'finders' => array(
        // General dependencies.
        Finder::create()->files()->ignoreVCS(\true)->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.(json|lock)/')->exclude(array('doc', 'test', 'test_old', 'tests', 'Tests', 'vendor-bin'))->in('../vendor'),
    ),
    'patchers' => array(static function (string $file_path, string $prefix, string $content) : string {
        $path = \dirname(__FILE__) . \DIRECTORY_SEPARATOR . \implode(\DIRECTORY_SEPARATOR, array('composer', 'autoload_real.php'));
        if (0 === \strcasecmp($file_path, $path)) {
            $content = \str_replace('spl_autoload_unregister(array(\'ComposerAutoloader', 'spl_autoload_unregister(array(\'' . $prefix . '\\\\ComposerAutoloader', $content);
        }
        return $content;
    }, function ($file_path, $prefix, $contents) {
        /*
         * There is currently no easy way to simply whitelist all global WordPress functions.
         *
         * This list here is a manual attempt after scanning through the AMP plugin, which means
         * it needs to be maintained and kept in sync with any changes to the dependency.
         *
         * As long as there's no built-in solution in PHP-Scoper for this, an alternative could be
         * to generate a list based on php-stubs/wordpress-stubs. devowlio/wp-react-starter/ seems
         * to be doing just this successfully.
         *
         * @see https://github.com/humbug/php-scoper/issues/303
         * @see https://github.com/php-stubs/wordpress-stubs
         * @see https://github.com/devowlio/wp-react-starter/
         */
        $contents = \str_replace("\\{$prefix}\\_doing_it_wrong", '\\_doing_it_wrong', $contents);
        $contents = \str_replace("\\{$prefix}\\__", '\\__', $contents);
        $contents = \str_replace("\\{$prefix}\\esc_html_e", '\\esc_html_e', $contents);
        $contents = \str_replace("\\{$prefix}\\esc_html", '\\esc_html', $contents);
        $contents = \str_replace("\\{$prefix}\\esc_attr", '\\esc_attr', $contents);
        $contents = \str_replace("\\{$prefix}\\esc_url", '\\esc_url', $contents);
        $contents = \str_replace("\\{$prefix}\\do_action", '\\do_action', $contents);
        $contents = \str_replace("\\{$prefix}\\site_url", '\\site_url', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_guess_url", '\\wp_guess_url', $contents);
        $contents = \str_replace("\\{$prefix}\\untrailingslashit", '\\untrailingslashit', $contents);
        $contents = \str_replace("\\{$prefix}\\WP_CONTENT_URL", '\\WP_CONTENT_URL', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_list_pluck", '\\wp_list_pluck', $contents);
        $contents = \str_replace("\\{$prefix}\\is_customize_preview", '\\is_customize_preview', $contents);
        $contents = \str_replace("\\{$prefix}\\do_action", '\\do_action', $contents);
        $contents = \str_replace("\\{$prefix}\\trailingslashit", '\\trailingslashit', $contents);
        $contents = \str_replace("\\{$prefix}\\get_template_directory_uri", '\\get_template_directory_uri', $contents);
        $contents = \str_replace("\\{$prefix}\\get_stylesheet_directory_uri", '\\get_stylesheet_directory_uri', $contents);
        $contents = \str_replace("\\{$prefix}\\includes_url", '\\includes_url', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_styles", '\\wp_styles', $contents);
        $contents = \str_replace("\\{$prefix}\\get_stylesheet", '\\get_stylesheet', $contents);
        $contents = \str_replace("\\{$prefix}\\get_template", '\\get_template', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_parse_url", '\\wp_parse_url', $contents);
        $contents = \str_replace("\\{$prefix}\\is_wp_error", '\\is_wp_error', $contents);
        $contents = \str_replace("\\{$prefix}\\content_url", '\\content_url', $contents);
        $contents = \str_replace("\\{$prefix}\\get_admin_url", '\\get_admin_url', $contents);
        $contents = \str_replace("\\{$prefix}\\WP_CONTENT_DIR", '\\WP_CONTENT_DIR', $contents);
        $contents = \str_replace("\\{$prefix}\\ABSPATH", '\\ABSPATH', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_nonce_url", '\\wp_nonce_url', $contents);
        $contents = \str_replace("\\{$prefix}\\WPINC", '\\WPINC', $contents);
        $contents = \str_replace("\\{$prefix}\\home_url", '\\home_url', $contents);
        $contents = \str_replace("\\{$prefix}\\__", '\\__', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_array_slice_assoc", '\\wp_array_slice_assoc', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_json_encode", '\\wp_json_encode', $contents);
        $contents = \str_replace("\\{$prefix}\\get_transient", '\\get_transient', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_cache_get", '\\wp_cache_get', $contents);
        $contents = \str_replace("\\{$prefix}\\set_transient", '\\set_transient', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_cache_set", '\\wp_cache_set', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_using_ext_object_cache", '\\wp_using_ext_object_cache', $contents);
        $contents = \str_replace("\\{$prefix}\\_doing_it_wrong", '\\_doing_it_wrong', $contents);
        $contents = \str_replace("\\{$prefix}\\plugin_dir_url", '\\plugin_dir_url', $contents);
        $contents = \str_replace("\\{$prefix}\\is_admin_bar_showing", '\\is_admin_bar_showing', $contents);
        $contents = \str_replace("\\{$prefix}\\get_bloginfo", '\\get_bloginfo', $contents);
        $contents = \str_replace("\\{$prefix}\\add_filter", '\\add_filter', $contents);
        $contents = \str_replace("\\{$prefix}\\add_action", '\\add_action', $contents);
        $contents = \str_replace("\\{$prefix}\\apply_filters", '\\apply_filters', $contents);
        $contents = \str_replace("\\{$prefix}\\add_query_arg", '\\add_query_arg', $contents);
        $contents = \str_replace("\\{$prefix}\\remove_query_arg", '\\remove_query_arg', $contents);
        $contents = \str_replace("\\{$prefix}\\get_post", '\\get_post', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_scripts", '\\wp_scripts', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_styles", '\\wp_styles', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_style_is", '\\wp_style_is', $contents);
        $contents = \str_replace("\\{$prefix}\\WP_PLUGIN_URL", '\\WP_PLUGIN_URL', $contents);
        $contents = \str_replace("\\{$prefix}\\WPMU_PLUGIN_URL", '\\WPMU_PLUGIN_URL', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_list_pluck", '\\wp_list_pluck', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_array_slice_assoc", '\\wp_array_slice_assoc', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_json_encode", '\\wp_json_encode', $contents);
        $contents = \str_replace("\\{$prefix}\\WP_Http", '\\WP_Http', $contents);
        $contents = \str_replace("\\{$prefix}\\WP_Error", '\\WP_Error', $contents);
        $contents = \str_replace("\\{$prefix}\\MINUTE_IN_SECONDS", '\\MINUTE_IN_SECONDS', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_next_scheduled", '\\wp_next_scheduled', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_remote_post", '\\wp_remote_post', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_create_nonce", '\\wp_create_nonce', $contents);
        $contents = \str_replace("\\{$prefix}\\admin_url", '\\admin_url', $contents);
        $contents = \str_replace("\\{$prefix}\\check_ajax_referer", '\\check_ajax_referer', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_die", '\\wp_die', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_clear_scheduled_hook", '\\wp_clear_scheduled_hook', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_unschedule_event", '\\wp_unschedule_event', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_convert_hr_to_bytes", '\\wp_convert_hr_to_bytes', $contents);
        $contents = \str_replace("\\{$prefix}\\maybe_unserialize", '\\maybe_unserialize', $contents);
        $contents = \str_replace("\\{$prefix}\\delete_site_transient", '\\delete_site_transient', $contents);
        $contents = \str_replace("\\{$prefix}\\set_site_transient", '\\set_site_transient', $contents);
        $contents = \str_replace("\\{$prefix}\\get_site_transient", '\\get_site_transient', $contents);
        $contents = \str_replace("\\{$prefix}\\is_multisite", '\\is_multisite', $contents);
        $contents = \str_replace("\\{$prefix}\\update_site_option", '\\update_site_option', $contents);
        $contents = \str_replace("\\{$prefix}\\delete_site_option", '\\delete_site_option', $contents);
        $contents = \str_replace("\\{$prefix}\\wp_schedule_event", '\\wp_schedule_event', $contents);
        return $contents;
    }),
    'exclude-files' => array(),
    // list<string>
    'exclude-namespaces' => array('WP2FA', 'Composer'),
    // list<string|regex>
    'exclude-constants' => array(),
    // list<string|regex>
    'exclude-classes' => array(),
    // list<string|regex>
    'exclude-functions' => array(),
    // list<string|regex>
    'whitelist' => array('add_action'),
);
