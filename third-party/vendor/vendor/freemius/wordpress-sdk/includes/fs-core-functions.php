<?php

namespace WP2FA_Vendor;

/**
 * @package     Freemius
 * @copyright   Copyright (c) 2015, Freemius, Inc.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since       1.0.3
 */
if (!\defined('WP2FA_Vendor\\ABSPATH')) {
    exit;
}
if (!\function_exists('WP2FA_Vendor\\fs_dummy')) {
    function fs_dummy()
    {
    }
}
/* Url.
   --------------------------------------------------------------------------------------------*/
if (!\function_exists('WP2FA_Vendor\\fs_get_url_daily_cache_killer')) {
    function fs_get_url_daily_cache_killer()
    {
        return \date('\\YY\\Mm\\Dd');
    }
}
/* Templates / Views.
   --------------------------------------------------------------------------------------------*/
if (!\function_exists('WP2FA_Vendor\\fs_get_template_path')) {
    function fs_get_template_path($path)
    {
        return \WP2FA_Vendor\WP_FS__DIR_TEMPLATES . '/' . \trim($path, '/');
    }
    function fs_include_template($path, &$params = null)
    {
        $VARS =& $params;
        include \WP2FA_Vendor\fs_get_template_path($path);
    }
    function fs_include_once_template($path, &$params = null)
    {
        $VARS =& $params;
        include_once \WP2FA_Vendor\fs_get_template_path($path);
    }
    function fs_require_template($path, &$params = null)
    {
        $VARS =& $params;
        require \WP2FA_Vendor\fs_get_template_path($path);
    }
    function fs_require_once_template($path, &$params = null)
    {
        $VARS =& $params;
        require_once \WP2FA_Vendor\fs_get_template_path($path);
    }
    function fs_get_template($path, &$params = null)
    {
        \ob_start();
        $VARS =& $params;
        require \WP2FA_Vendor\fs_get_template_path($path);
        return \ob_get_clean();
    }
}
/* Scripts and styles including.
   --------------------------------------------------------------------------------------------*/
if (!\function_exists('WP2FA_Vendor\\fs_asset_url')) {
    /**
     * Generates an absolute URL to the given path. This function ensures that the URL will be correct whether the asset
     * is inside a plugin's folder or a theme's folder.
     *
     * Examples:
     * 1. "themes" folder
     *    Path: C:/xampp/htdocs/fswp/wp-content/themes/twentytwelve/freemius/assets/css/admin/common.css
     *    URL: http://fswp:8080/wp-content/themes/twentytwelve/freemius/assets/css/admin/common.css
     *
     * 2. "plugins" folder
     *    Path: C:/xampp/htdocs/fswp/wp-content/plugins/rating-widget-premium/freemius/assets/css/admin/common.css
     *    URL: http://fswp:8080/wp-content/plugins/rating-widget-premium/freemius/assets/css/admin/common.css
     *
     * @author Leo Fajardo (@leorw)
     * @since  1.2.2
     *
     * @param  string $asset_abs_path Asset's absolute path.
     *
     * @return string Asset's URL.
     */
    function fs_asset_url($asset_abs_path)
    {
        $wp_content_dir = \WP2FA_Vendor\fs_normalize_path(\WP_CONTENT_DIR);
        $asset_abs_path = \WP2FA_Vendor\fs_normalize_path($asset_abs_path);
        if (0 === \strpos($asset_abs_path, $wp_content_dir)) {
            // Handle both theme and plugin assets located in the standard directories.
            $asset_rel_path = \str_replace($wp_content_dir, '', $asset_abs_path);
            $asset_url = \content_url(\WP2FA_Vendor\fs_normalize_path($asset_rel_path));
        } else {
            $wp_plugins_dir = \WP2FA_Vendor\fs_normalize_path(\WP2FA_Vendor\WP_PLUGIN_DIR);
            if (0 === \strpos($asset_abs_path, $wp_plugins_dir)) {
                // Try to handle plugin assets that may be located in a non-standard plugins directory.
                $asset_rel_path = \str_replace($wp_plugins_dir, '', $asset_abs_path);
                $asset_url = \WP2FA_Vendor\plugins_url(\WP2FA_Vendor\fs_normalize_path($asset_rel_path));
            } else {
                // Try to handle theme assets that may be located in a non-standard themes directory.
                $active_theme_stylesheet = \get_stylesheet();
                $wp_themes_dir = \WP2FA_Vendor\fs_normalize_path(\trailingslashit(\WP2FA_Vendor\get_theme_root($active_theme_stylesheet)));
                $asset_rel_path = \str_replace($wp_themes_dir, '', \WP2FA_Vendor\fs_normalize_path($asset_abs_path));
                $asset_url = \trailingslashit(\WP2FA_Vendor\get_theme_root_uri($active_theme_stylesheet)) . \WP2FA_Vendor\fs_normalize_path($asset_rel_path);
            }
        }
        return $asset_url;
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_enqueue_local_style')) {
    function fs_enqueue_local_style($handle, $path, $deps = array(), $ver = \false, $media = 'all')
    {
        \WP2FA_Vendor\wp_enqueue_style($handle, \WP2FA_Vendor\fs_asset_url(\WP2FA_Vendor\WP_FS__DIR_CSS . '/' . \trim($path, '/')), $deps, $ver, $media);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_enqueue_local_script')) {
    function fs_enqueue_local_script($handle, $path, $deps = array(), $ver = \false, $in_footer = 'all')
    {
        \WP2FA_Vendor\wp_enqueue_script($handle, \WP2FA_Vendor\fs_asset_url(\WP2FA_Vendor\WP_FS__DIR_JS . '/' . \trim($path, '/')), $deps, $ver, $in_footer);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_img_url')) {
    function fs_img_url($path, $img_dir = \WP2FA_Vendor\WP_FS__DIR_IMG)
    {
        return \WP2FA_Vendor\fs_asset_url($img_dir . '/' . \trim($path, '/'));
    }
}
#--------------------------------------------------------------------------------
#region Request handlers.
#--------------------------------------------------------------------------------
if (!\function_exists('WP2FA_Vendor\\fs_request_get')) {
    /**
     * A helper method to fetch GET/POST user input with an optional default value when the input is not set.
     * @author Vova Feldman (@svovaf)
     *
     * @param string      $key
     * @param mixed       $def
     * @param string|bool $type Since 1.2.1.7 - when set to 'get' will look for the value passed via querystring, when
     *                          set to 'post' will look for the value passed via the POST request's body, otherwise,
     *                          will check if the parameter was passed in any of the two.
     *
     * @return mixed
     */
    function fs_request_get($key, $def = \false, $type = \false)
    {
        if (\is_string($type)) {
            $type = \strtolower($type);
        }
        /**
         * Note to WordPress.org Reviewers:
         *  This is a helper method to fetch GET/POST user input with an optional default value when the input is not set. The actual sanitization is done in the scope of the function's usage.
         */
        switch ($type) {
            case 'post':
                $value = isset($_POST[$key]) ? $_POST[$key] : $def;
                break;
            case 'get':
                $value = isset($_GET[$key]) ? $_GET[$key] : $def;
                break;
            default:
                $value = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $def;
                break;
        }
        return $value;
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_request_has')) {
    function fs_request_has($key)
    {
        return isset($_REQUEST[$key]);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_request_get_bool')) {
    /**
     * A helper method to fetch GET/POST user boolean input with an optional default value when the input is not set.
     *
     * @author Vova Feldman (@svovaf)
     *
     * @param string $key
     * @param bool $def
     *
     * @return bool|mixed
     */
    function fs_request_get_bool($key, $def = \false)
    {
        $val = \WP2FA_Vendor\fs_request_get($key, null);
        if (\is_null($val)) {
            return $def;
        }
        if (\is_bool($val)) {
            return $val;
        } else {
            if (\is_numeric($val)) {
                if (1 == $val) {
                    return \true;
                } else {
                    if (0 == $val) {
                        return \false;
                    }
                }
            } else {
                if (\is_string($val)) {
                    $val = \strtolower($val);
                    if ('true' === $val) {
                        return \true;
                    } else {
                        if ('false' === $val) {
                            return \false;
                        }
                    }
                }
            }
        }
        return $def;
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_request_is_post')) {
    function fs_request_is_post()
    {
        return 'post' === \strtolower($_SERVER['REQUEST_METHOD']);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_request_is_get')) {
    function fs_request_is_get()
    {
        return 'get' === \strtolower($_SERVER['REQUEST_METHOD']);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_get_action')) {
    function fs_get_action($action_key = 'action')
    {
        if (!empty($_REQUEST[$action_key]) && \is_string($_REQUEST[$action_key])) {
            return \strtolower($_REQUEST[$action_key]);
        }
        if ('action' == $action_key) {
            $action_key = 'fs_action';
            if (!empty($_REQUEST[$action_key]) && \is_string($_REQUEST[$action_key])) {
                return \strtolower($_REQUEST[$action_key]);
            }
        }
        return \false;
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_request_is_action')) {
    function fs_request_is_action($action, $action_key = 'action')
    {
        return \strtolower($action) === \WP2FA_Vendor\fs_get_action($action_key);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_request_is_action_secure')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.0.0
     *
     * @since  1.2.1.5 Allow nonce verification.
     *
     * @param string $action
     * @param string $action_key
     * @param string $nonce_key
     *
     * @return bool
     */
    function fs_request_is_action_secure($action, $action_key = 'action', $nonce_key = 'nonce')
    {
        if (\strtolower($action) !== \WP2FA_Vendor\fs_get_action($action_key)) {
            return \false;
        }
        $nonce = !empty($_REQUEST[$nonce_key]) ? $_REQUEST[$nonce_key] : '';
        if (empty($nonce) || \false === \WP2FA_Vendor\wp_verify_nonce($nonce, $action)) {
            return \false;
        }
        return \true;
    }
}
#endregion
if (!\function_exists('WP2FA_Vendor\\fs_is_plugin_page')) {
    function fs_is_plugin_page($page_slug)
    {
        return \WP2FA_Vendor\is_admin() && $page_slug === \WP2FA_Vendor\fs_request_get('page');
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_get_raw_referer')) {
    /**
     * Retrieves unvalidated referer from '_wp_http_referer' or HTTP referer.
     *
     * Do not use for redirects, use {@see wp_get_referer()} instead.
     *
     * @since 1.2.3
     *
     * @return string|false Referer URL on success, false on failure.
     */
    function fs_get_raw_referer()
    {
        if (\function_exists('WP2FA_Vendor\\wp_get_raw_referer')) {
            return \WP2FA_Vendor\wp_get_raw_referer();
        }
        if (!empty($_REQUEST['_wp_http_referer'])) {
            return \WP2FA_Vendor\wp_unslash($_REQUEST['_wp_http_referer']);
        } else {
            if (!empty($_SERVER['HTTP_REFERER'])) {
                return \WP2FA_Vendor\wp_unslash($_SERVER['HTTP_REFERER']);
            }
        }
        return \false;
    }
}
/* Core UI.
   --------------------------------------------------------------------------------------------*/
if (!\function_exists('WP2FA_Vendor\\fs_ui_action_button')) {
    /**
     * @param number      $module_id
     * @param string      $page
     * @param string      $action
     * @param string      $title
     * @param string      $button_class
     * @param array       $params
     * @param bool        $is_primary
     * @param bool        $is_small
     * @param string|bool $icon_class   Optional class for an icon (since 1.1.7).
     * @param string|bool $confirmation Optional confirmation message before submit (since 1.1.7).
     * @param string      $method       Since 1.1.7
     *
     * @uses fs_ui_get_action_button()
     */
    function fs_ui_action_button($module_id, $page, $action, $title, $button_class = '', $params = array(), $is_primary = \true, $is_small = \false, $icon_class = \false, $confirmation = \false, $method = 'GET')
    {
        echo \WP2FA_Vendor\fs_ui_get_action_button($module_id, $page, $action, $title, $button_class, $params, $is_primary, $is_small, $icon_class, $confirmation, $method);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_ui_get_action_button')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.1.7
     *
     * @param number      $module_id
     * @param string      $page
     * @param string      $action
     * @param string      $title
     * @param string      $button_class
     * @param array       $params
     * @param bool        $is_primary
     * @param bool        $is_small
     * @param string|bool $icon_class   Optional class for an icon.
     * @param string|bool $confirmation Optional confirmation message before submit.
     * @param string      $method
     *
     * @return string
     */
    function fs_ui_get_action_button($module_id, $page, $action, $title, $button_class = '', $params = array(), $is_primary = \true, $is_small = \false, $icon_class = \false, $confirmation = \false, $method = 'GET')
    {
        // Prepend icon (if set).
        $title = (\is_string($icon_class) ? '<i class="' . $icon_class . '"></i> ' : '') . $title;
        if (\is_string($confirmation)) {
            return \sprintf('<form action="%s" method="%s"><input type="hidden" name="fs_action" value="%s">%s<a href="#" class="%s" onclick="if (confirm(\'%s\')) this.parentNode.submit(); return false;">%s</a></form>', \WP2FA_Vendor\freemius($module_id)->_get_admin_page_url($page, $params), $method, $action, \WP2FA_Vendor\wp_nonce_field($action, '_wpnonce', \true, \false), 'button' . (!empty($button_class) ? ' ' . $button_class : '') . ($is_primary ? ' button-primary' : '') . ($is_small ? ' button-small' : ''), $confirmation, $title);
        } else {
            if ('GET' !== \strtoupper($method)) {
                return \sprintf('<form action="%s" method="%s"><input type="hidden" name="fs_action" value="%s">%s<a href="#" class="%s" onclick="this.parentNode.submit(); return false;">%s</a></form>', \WP2FA_Vendor\freemius($module_id)->_get_admin_page_url($page, $params), $method, $action, \WP2FA_Vendor\wp_nonce_field($action, '_wpnonce', \true, \false), 'button' . (!empty($button_class) ? ' ' . $button_class : '') . ($is_primary ? ' button-primary' : '') . ($is_small ? ' button-small' : ''), $title);
            } else {
                return \sprintf('<a href="%s" class="%s">%s</a></form>', \WP2FA_Vendor\wp_nonce_url(\WP2FA_Vendor\freemius($module_id)->_get_admin_page_url($page, \array_merge($params, array('fs_action' => $action))), $action), 'button' . (!empty($button_class) ? ' ' . $button_class : '') . ($is_primary ? ' button-primary' : '') . ($is_small ? ' button-small' : ''), $title);
            }
        }
    }
    function fs_ui_action_link($module_id, $page, $action, $title, $params = array())
    {
        ?><a class=""
                 href="<?php 
        echo \WP2FA_Vendor\wp_nonce_url(\WP2FA_Vendor\freemius($module_id)->_get_admin_page_url($page, \array_merge($params, array('fs_action' => $action))), $action);
        ?>"><?php 
        echo $title;
        ?></a><?php 
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_get_entity')) {
    /**
     * @author Leo Fajardo (@leorw)
     * @since 2.3.1
     *
     * @param mixed  $entity
     * @param string $class
     *
     * @return FS_Plugin|FS_User|FS_Site|FS_Plugin_License|FS_Plugin_Plan|FS_Plugin_Tag|FS_Subscription
     */
    function fs_get_entity($entity, $class)
    {
        if (!\is_object($entity) || $entity instanceof $class) {
            return $entity;
        }
        return new $class($entity);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_get_entities')) {
    /**
     * @author Leo Fajardo (@leorw)
     * @since 2.3.1
     *
     * @param mixed  $entities
     * @param string $class_name
     *
     * @return FS_Plugin[]|FS_User[]|FS_Site[]|FS_Plugin_License[]|FS_Plugin_Plan[]|FS_Plugin_Tag[]|FS_Subscription[]
     */
    function fs_get_entities($entities, $class_name)
    {
        if (!\is_array($entities) || empty($entities)) {
            return $entities;
        }
        // Get first element.
        $first_array_element = \reset($entities);
        if ($first_array_element instanceof $class_name) {
            /**
             * If the first element of the array is an instance of the context class, assume that all other
             * elements are instances of the class.
             */
            return $entities;
        }
        if (\is_array($first_array_element) && !empty($first_array_element)) {
            $first_array_element = \reset($first_array_element);
            if ($first_array_element instanceof $class_name) {
                /**
                 * If the first element of the `$entities` array is an array whose first element is an instance of the
                 * context class, assume that all other objects are instances of the class.
                 */
                return $entities;
            }
        }
        foreach ($entities as $key => $entities_or_entity) {
            if (\is_array($entities_or_entity)) {
                $entities[$key] = \WP2FA_Vendor\fs_get_entities($entities_or_entity, $class_name);
            } else {
                $entities[$key] = \WP2FA_Vendor\fs_get_entity($entities_or_entity, $class_name);
            }
        }
        return $entities;
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_nonce_url')) {
    /**
     * Retrieve URL with nonce added to URL query.
     *
     * Originally was using `wp_nonce_url()` but the new version
     * changed the return value to escaped URL, that's not the expected
     * behaviour.
     *
     * @author Vova Feldman (@svovaf)
     * @since  ~1.1.3
     *
     * @param string     $actionurl URL to add nonce action.
     * @param int|string $action    Optional. Nonce action name. Default -1.
     * @param string     $name      Optional. Nonce name. Default '_wpnonce'.
     *
     * @return string Escaped URL with nonce action added.
     */
    function fs_nonce_url($actionurl, $action = -1, $name = '_wpnonce')
    {
        return \add_query_arg($name, \wp_create_nonce($action), $actionurl);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_starts_with')) {
    /**
     * Check if string starts with.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.1.3
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    function fs_starts_with($haystack, $needle)
    {
        $length = \strlen($needle);
        return \substr($haystack, 0, $length) === $needle;
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_ends_with')) {
    /**
     * Check if string ends with.
     *
     * @author Vova Feldman (@svovaf)
     * @since  2.0.0
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    function fs_ends_with($haystack, $needle)
    {
        $length = \strlen($needle);
        $start = $length * -1;
        // negative
        return \substr($haystack, $start) === $needle;
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_strip_url_protocol')) {
    function fs_strip_url_protocol($url)
    {
        if (!\WP2FA_Vendor\fs_starts_with($url, 'http')) {
            return $url;
        }
        $protocol_pos = \strpos($url, '://');
        if ($protocol_pos > 5) {
            return $url;
        }
        return \substr($url, $protocol_pos + 3);
    }
}
#region Url Canonization ------------------------------------------------------------------
if (!\function_exists('WP2FA_Vendor\\fs_canonize_url')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.1.3
     *
     * @param string $url
     * @param bool   $omit_host
     * @param array  $ignore_params
     *
     * @return string
     */
    function fs_canonize_url($url, $omit_host = \false, $ignore_params = array())
    {
        $parsed_url = \parse_url(\strtolower($url));
        //		if ( ! isset( $parsed_url['host'] ) ) {
        //			return $url;
        //		}
        $canonical = ($omit_host || !isset($parsed_url['host']) ? '' : $parsed_url['host']) . $parsed_url['path'];
        if (isset($parsed_url['query'])) {
            \parse_str($parsed_url['query'], $queryString);
            $canonical .= '?' . \WP2FA_Vendor\fs_canonize_query_string($queryString, $ignore_params);
        }
        return $canonical;
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_canonize_query_string')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.1.3
     *
     * @param array $params
     * @param array $ignore_params
     * @param bool  $params_prefix
     *
     * @return string
     */
    function fs_canonize_query_string(array $params, array &$ignore_params, $params_prefix = \false)
    {
        if (!\is_array($params) || 0 === \count($params)) {
            return '';
        }
        // Url encode both keys and values
        $keys = \WP2FA_Vendor\fs_urlencode_rfc3986(\array_keys($params));
        $values = \WP2FA_Vendor\fs_urlencode_rfc3986(\array_values($params));
        $params = \array_combine($keys, $values);
        // Parameters are sorted by name, using lexicographical byte value ordering.
        // Ref: Spec: 9.1.1 (1)
        \uksort($params, 'strcmp');
        $pairs = array();
        foreach ($params as $parameter => $value) {
            $lower_param = \strtolower($parameter);
            // Skip ignore params.
            if (\in_array($lower_param, $ignore_params) || \false !== $params_prefix && \WP2FA_Vendor\fs_starts_with($lower_param, $params_prefix)) {
                continue;
            }
            if (\is_array($value)) {
                // If two or more parameters share the same name, they are sorted by their value
                // Ref: Spec: 9.1.1 (1)
                \natsort($value);
                foreach ($value as $duplicate_value) {
                    $pairs[] = $lower_param . '=' . $duplicate_value;
                }
            } else {
                $pairs[] = $lower_param . '=' . $value;
            }
        }
        if (0 === \count($pairs)) {
            return '';
        }
        return \implode("&", $pairs);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_urlencode_rfc3986')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.1.3
     *
     * @param string|string[] $input
     *
     * @return array|mixed|string
     */
    function fs_urlencode_rfc3986($input)
    {
        if (\is_array($input)) {
            return \array_map('fs_urlencode_rfc3986', $input);
        } else {
            if (\is_scalar($input)) {
                return \str_replace('+', ' ', \str_replace('%7E', '~', \rawurlencode($input)));
            }
        }
        return '';
    }
}
#endregion Url Canonization ------------------------------------------------------------------
if (!\function_exists('WP2FA_Vendor\\fs_download_image')) {
    /**
     * @author Vova Feldman (@svovaf)
     *
     * @since  1.2.2 Changed to usage of WP_Filesystem_Direct.
     *
     * @param string $from URL
     * @param string $to   File path.
     *
     * @return bool Is successfully downloaded.
     */
    function fs_download_image($from, $to)
    {
        $dir = \dirname($to);
        if ('direct' !== \WP2FA_Vendor\get_filesystem_method(array(), $dir)) {
            return \false;
        }
        if (!\class_exists('WP2FA_Vendor\\WP_Filesystem_Direct')) {
            require_once \ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once \ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }
        $fs = new \WP2FA_Vendor\WP_Filesystem_Direct('');
        $tmpfile = \WP2FA_Vendor\download_url($from);
        if ($tmpfile instanceof \WP_Error) {
            // Issue downloading the file.
            return \false;
        }
        $fs->copy($tmpfile, $to);
        $fs->delete($tmpfile);
        return \true;
    }
}
/* General Utilities
   --------------------------------------------------------------------------------------------*/
if (!\function_exists('WP2FA_Vendor\\fs_sort_by_priority')) {
    /**
     * Sorts an array by the value of the priority key.
     *
     * @author Daniel Iser (@danieliser)
     * @since  1.1.7
     *
     * @param $a
     * @param $b
     *
     * @return int
     */
    function fs_sort_by_priority($a, $b)
    {
        // If b has a priority and a does not, b wins.
        if (!isset($a['priority']) && isset($b['priority'])) {
            return 1;
        } elseif (isset($a['priority']) && !isset($b['priority'])) {
            return -1;
        } elseif (!isset($a['priority']) && !isset($b['priority']) || $a['priority'] === $b['priority']) {
            return 0;
        }
        // If both have priority return the winner.
        return $a['priority'] < $b['priority'] ? -1 : 1;
    }
}
#--------------------------------------------------------------------------------
#region Localization
#--------------------------------------------------------------------------------
if (!\function_exists('WP2FA_Vendor\\fs_text')) {
    /**
     * Retrieve a translated text by key.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.1.7
     *
     * @param string $key
     * @param string $slug
     *
     * @return string
     *
     * @global       $fs_text , $fs_text_overrides
     */
    function fs_text($key, $slug = 'freemius')
    {
        global $fs_text, $fs_module_info_text, $fs_text_overrides;
        if (isset($fs_text_overrides[$slug])) {
            if (isset($fs_text_overrides[$slug][$key])) {
                return $fs_text_overrides[$slug][$key];
            }
            $lower_key = \strtolower($key);
            if (isset($fs_text_overrides[$slug][$lower_key])) {
                return $fs_text_overrides[$slug][$lower_key];
            }
        }
        if (!isset($fs_text)) {
            $dir = \defined('WP2FA_Vendor\\WP_FS__DIR_INCLUDES') ? \WP2FA_Vendor\WP_FS__DIR_INCLUDES : \dirname(__FILE__);
            require_once $dir . '/i18n.php';
        }
        if (isset($fs_text[$key])) {
            return $fs_text[$key];
        }
        if (isset($fs_module_info_text[$key])) {
            return $fs_module_info_text[$key];
        }
        return $key;
    }
    #region Private
    /**
     * Retrieve an inline translated text by key with a context.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text    Translatable string.
     * @param string $context Context information for the translators.
     * @param string $key     String key for overrides.
     * @param string $slug    Module slug for overrides.
     *
     * @return string
     *
     * @global       $fs_text_overrides
     */
    function _fs_text_x_inline($text, $context, $key = '', $slug = 'freemius')
    {
        list($text, $text_domain) = \WP2FA_Vendor\fs_text_and_domain($text, $key, $slug);
        // Avoid misleading Theme Check warning.
        $fn = 'translate_with_gettext_context';
        return $fn($text, $context, $text_domain);
    }
    #endregion
    /**
     * Retrieve an inline translated text by key with a context.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text    Translatable string.
     * @param string $context Context information for the translators.
     * @param string $key     String key for overrides.
     * @param string $slug    Module slug for overrides.
     *
     * @return string
     *
     * @global       $fs_text_overrides
     */
    function fs_text_x_inline($text, $context, $key = '', $slug = 'freemius')
    {
        return \WP2FA_Vendor\_fs_text_x_inline($text, $context, $key, $slug);
    }
    /**
     * Output a translated text by key.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.1.7
     *
     * @param string $key
     * @param string $slug
     */
    function fs_echo($key, $slug = 'freemius')
    {
        echo \WP2FA_Vendor\fs_text($key, $slug);
    }
    /**
     * Output an inline translated text.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     */
    function fs_echo_inline($text, $key = '', $slug = 'freemius')
    {
        echo \WP2FA_Vendor\_fs_text_inline($text, $key, $slug);
    }
    /**
     * Output an inline translated text with a context.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text    Translatable string.
     * @param string $context Context information for the translators.
     * @param string $key     String key for overrides.
     * @param string $slug    Module slug for overrides.
     */
    function fs_echo_x_inline($text, $context, $key = '', $slug = 'freemius')
    {
        echo \WP2FA_Vendor\_fs_text_x_inline($text, $context, $key, $slug);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_text_override')) {
    /**
     * Get a translatable text override if exists, or `false`.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.1.7
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     *
     * @return string|false
     */
    function fs_text_override($text, $key, $slug)
    {
        global $fs_text_overrides;
        /**
         * Check if string is overridden.
         */
        if (!isset($fs_text_overrides[$slug])) {
            return \false;
        }
        if (empty($key)) {
            $key = \strtolower(\str_replace(' ', '-', $text));
        }
        if (isset($fs_text_overrides[$slug][$key])) {
            return $fs_text_overrides[$slug][$key];
        }
        $lower_key = \strtolower($key);
        if (isset($fs_text_overrides[$slug][$lower_key])) {
            return $fs_text_overrides[$slug][$lower_key];
        }
        return \false;
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_text_and_domain')) {
    /**
     * Get a translatable text and its text domain.
     *
     * When the text is overridden by the module, returns the overridden text and the text domain of the module. Otherwise, returns the original text and 'freemius' as the text domain.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.1.7
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     *
     * @return string[]
     */
    function fs_text_and_domain($text, $key, $slug)
    {
        $override = \WP2FA_Vendor\fs_text_override($text, $key, $slug);
        if (\false === $override) {
            // No override, use FS text domain.
            $text_domain = 'freemius';
        } else {
            // Found an override.
            $text = $override;
            // Use the module's text domain.
            $text_domain = $slug;
        }
        return array($text, $text_domain);
    }
}
if (!\function_exists('WP2FA_Vendor\\_fs_text_inline')) {
    /**
     * Retrieve an inline translated text by key.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     *
     * @return string
     *
     * @global       $fs_text_overrides
     */
    function _fs_text_inline($text, $key = '', $slug = 'freemius')
    {
        list($text, $text_domain) = \WP2FA_Vendor\fs_text_and_domain($text, $key, $slug);
        // Avoid misleading Theme Check warning.
        $fn = 'translate';
        return $fn($text, $text_domain);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_text_inline')) {
    /**
     * Retrieve an inline translated text by key.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     *
     * @return string
     *
     * @global       $fs_text_overrides
     */
    function fs_text_inline($text, $key = '', $slug = 'freemius')
    {
        return \WP2FA_Vendor\_fs_text_inline($text, $key, $slug);
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_attr')) {
    /**
     * @author Vova Feldman
     * @since  1.2.1.6
     *
     * @param string $key
     * @param string $slug
     *
     * @return string
     */
    function fs_esc_attr($key, $slug)
    {
        return \esc_attr(\WP2FA_Vendor\fs_text($key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_attr_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     *
     * @return string
     */
    function fs_esc_attr_inline($text, $key = '', $slug = 'freemius')
    {
        return \esc_attr(\WP2FA_Vendor\_fs_text_inline($text, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_attr_x_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text    Translatable string.
     * @param string $context Context information for the translators.
     * @param string $key     String key for overrides.
     * @param string $slug    Module slug for overrides.
     *
     * @return string
     */
    function fs_esc_attr_x_inline($text, $context, $key = '', $slug = 'freemius')
    {
        return \esc_attr(\WP2FA_Vendor\_fs_text_x_inline($text, $context, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_attr_echo')) {
    /**
     * @author Vova Feldman
     * @since  1.2.1.6
     *
     * @param string $key
     * @param string $slug
     */
    function fs_esc_attr_echo($key, $slug)
    {
        echo \esc_attr(\WP2FA_Vendor\fs_text($key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_attr_echo_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     */
    function fs_esc_attr_echo_inline($text, $key = '', $slug = 'freemius')
    {
        echo \esc_attr(\WP2FA_Vendor\_fs_text_inline($text, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_js')) {
    /**
     * @author Vova Feldman
     * @since  1.2.1.6
     *
     * @param string $key
     * @param string $slug
     *
     * @return string
     */
    function fs_esc_js($key, $slug)
    {
        return \WP2FA_Vendor\esc_js(\WP2FA_Vendor\fs_text($key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_js_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     *
     * @return string
     */
    function fs_esc_js_inline($text, $key = '', $slug = 'freemius')
    {
        return \WP2FA_Vendor\esc_js(\WP2FA_Vendor\_fs_text_inline($text, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_js_x_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text    Translatable string.
     * @param string $context Context information for the translators.
     * @param string $key     String key for overrides.
     * @param string $slug    Module slug for overrides.
     *
     * @return string
     */
    function fs_esc_js_x_inline($text, $context, $key = '', $slug = 'freemius')
    {
        return \WP2FA_Vendor\esc_js(\WP2FA_Vendor\_fs_text_x_inline($text, $context, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_js_echo_x_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text    Translatable string.
     * @param string $context Context information for the translators.
     * @param string $key     String key for overrides.
     * @param string $slug    Module slug for overrides.
     *
     * @return string
     */
    function fs_esc_js_echo_x_inline($text, $context, $key = '', $slug = 'freemius')
    {
        echo \WP2FA_Vendor\esc_js(\WP2FA_Vendor\_fs_text_x_inline($text, $context, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_js_echo')) {
    /**
     * @author Vova Feldman
     * @since  1.2.1.6
     *
     * @param string $key
     * @param string $slug
     */
    function fs_esc_js_echo($key, $slug)
    {
        echo \WP2FA_Vendor\esc_js(\WP2FA_Vendor\fs_text($key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_js_echo_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     */
    function fs_esc_js_echo_inline($text, $key = '', $slug = 'freemius')
    {
        echo \WP2FA_Vendor\esc_js(\WP2FA_Vendor\_fs_text_inline($text, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_json_encode_echo')) {
    /**
     * @author Vova Feldman
     * @since  1.2.1.6
     *
     * @param string $key
     * @param string $slug
     */
    function fs_json_encode_echo($key, $slug)
    {
        echo \json_encode(\WP2FA_Vendor\fs_text($key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_json_encode_echo_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     */
    function fs_json_encode_echo_inline($text, $key = '', $slug = 'freemius')
    {
        echo \json_encode(\WP2FA_Vendor\_fs_text_inline($text, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_html')) {
    /**
     * @author Vova Feldman
     * @since  1.2.1.6
     *
     * @param string $key
     * @param string $slug
     *
     * @return string
     */
    function fs_esc_html($key, $slug)
    {
        return \esc_html(\WP2FA_Vendor\fs_text($key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_html_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     *
     * @return string
     */
    function fs_esc_html_inline($text, $key = '', $slug = 'freemius')
    {
        return \esc_html(\WP2FA_Vendor\_fs_text_inline($text, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_html_x_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text    Translatable string.
     * @param string $context Context information for the translators.
     * @param string $key     String key for overrides.
     * @param string $slug    Module slug for overrides.
     *
     * @return string
     */
    function fs_esc_html_x_inline($text, $context, $key = '', $slug = 'freemius')
    {
        return \esc_html(\WP2FA_Vendor\_fs_text_x_inline($text, $context, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_html_echo_x_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text    Translatable string.
     * @param string $context Context information for the translators.
     * @param string $key     String key for overrides.
     * @param string $slug    Module slug for overrides.
     */
    function fs_esc_html_echo_x_inline($text, $context, $key = '', $slug = 'freemius')
    {
        echo \esc_html(\WP2FA_Vendor\_fs_text_x_inline($text, $context, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_html_echo')) {
    /**
     * @author Vova Feldman
     * @since  1.2.1.6
     *
     * @param string $key
     * @param string $slug
     */
    function fs_esc_html_echo($key, $slug)
    {
        echo \esc_html(\WP2FA_Vendor\fs_text($key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_esc_html_echo_inline')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  1.2.3
     *
     * @param string $text Translatable string.
     * @param string $key  String key for overrides.
     * @param string $slug Module slug for overrides.
     */
    function fs_esc_html_echo_inline($text, $key = '', $slug = 'freemius')
    {
        echo \esc_html(\WP2FA_Vendor\_fs_text_inline($text, $key, $slug));
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_override_i18n')) {
    /**
     * Override default i18n text phrases.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.1.6
     *
     * @param array[string]string $key_value
     * @param string              $slug
     *
     * @global $fs_text_overrides
     */
    function fs_override_i18n(array $key_value, $slug = 'freemius')
    {
        global $fs_text_overrides;
        if (!isset($fs_text_overrides[$slug])) {
            $fs_text_overrides[$slug] = array();
        }
        foreach ($key_value as $key => $value) {
            $fs_text_overrides[$slug][$key] = $value;
        }
    }
}
#endregion
#--------------------------------------------------------------------------------
#region Multisite Network
#--------------------------------------------------------------------------------
if (!\function_exists('WP2FA_Vendor\\fs_is_plugin_uninstall')) {
    /**
     * @author Vova Feldman (@svovaf)
     * @since  2.0.0
     */
    function fs_is_plugin_uninstall()
    {
        return \defined('WP2FA_Vendor\\WP_UNINSTALL_PLUGIN') || 0 < \WP2FA_Vendor\did_action('update_option_uninstall_plugins');
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_is_network_admin')) {
    /**
     * Unlike is_network_admin(), this one will also work properly when
     * the context execution is WP AJAX handler, and during plugin
     * uninstall.
     *
     * @author Vova Feldman (@svovaf)
     * @since  2.0.0
     */
    function fs_is_network_admin()
    {
        return \WP2FA_Vendor\WP_FS__IS_NETWORK_ADMIN || \is_multisite() && \WP2FA_Vendor\fs_is_plugin_uninstall();
    }
}
if (!\function_exists('WP2FA_Vendor\\fs_is_blog_admin')) {
    /**
     * Unlike is_blog_admin(), this one will also work properly when
     * the context execution is WP AJAX handler, and during plugin
     * uninstall.
     *
     * @author Vova Feldman (@svovaf)
     * @since  2.0.0
     */
    function fs_is_blog_admin()
    {
        return \WP2FA_Vendor\WP_FS__IS_BLOG_ADMIN || !\is_multisite() && \WP2FA_Vendor\fs_is_plugin_uninstall();
    }
}
#endregion
if (!\function_exists('WP2FA_Vendor\\fs_apply_filter')) {
    /**
     * Apply filter for specific plugin.
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.0.9
     *
     * @param string $module_unique_affix Module's unique affix.
     * @param string $tag                 The name of the filter hook.
     * @param mixed  $value               The value on which the filters hooked to `$tag` are applied on.
     *
     * @return mixed The filtered value after all hooked functions are applied to it.
     *
     * @uses   apply_filters()
     */
    function fs_apply_filter($module_unique_affix, $tag, $value)
    {
        $args = \func_get_args();
        return \call_user_func_array('apply_filters', \array_merge(array("fs_{$tag}_{$module_unique_affix}"), \array_slice($args, 2)));
    }
}
