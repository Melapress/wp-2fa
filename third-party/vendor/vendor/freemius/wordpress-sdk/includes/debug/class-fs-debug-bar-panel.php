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
/**
 * Extends Debug Bar plugin by adding a panel to show all Freemius API requests.
 *
 * @author Vova Feldman (@svovaf)
 * @since  1.1.7.3
 *
 * Class Freemius_Debug_Bar_Panel
 */
class Freemius_Debug_Bar_Panel extends \WP2FA_Vendor\Debug_Bar_Panel
{
    function init()
    {
        $this->title('Freemius');
    }
    static function requests_count()
    {
        if (\class_exists('WP2FA_Vendor\\Freemius_Api_WordPress')) {
            $logger = \WP2FA_Vendor\Freemius_Api_WordPress::GetLogger();
        } else {
            $logger = array();
        }
        return \number_format(\count($logger));
    }
    static function total_time()
    {
        if (\class_exists('WP2FA_Vendor\\Freemius_Api_WordPress')) {
            $logger = \WP2FA_Vendor\Freemius_Api_WordPress::GetLogger();
        } else {
            $logger = array();
        }
        $total_time = 0.0;
        foreach ($logger as $l) {
            $total_time += $l['total'];
        }
        return \number_format(100 * $total_time, 2) . ' ' . \WP2FA_Vendor\fs_text_x_inline('ms', 'milliseconds');
    }
    function render()
    {
        ?>
			<div id='debug-bar-php'>
				<?php 
        \WP2FA_Vendor\fs_require_template('/debug/api-calls.php');
        ?>
				<br>
				<?php 
        \WP2FA_Vendor\fs_require_template('/debug/scheduled-crons.php');
        ?>
				<br>
				<?php 
        \WP2FA_Vendor\fs_require_template('/debug/plugins-themes-sync.php');
        ?>
				<br>
				<?php 
        \WP2FA_Vendor\fs_require_template('/debug/logger.php');
        ?>
			</div>
		<?php 
    }
}
