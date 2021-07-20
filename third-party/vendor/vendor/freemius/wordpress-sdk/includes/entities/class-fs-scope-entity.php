<?php

namespace WP2FA_Vendor;

/**
 * @package     Freemius
 * @copyright   Copyright (c) 2015, Freemius, Inc.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since       1.0.4
 */
if (!\defined('WP2FA_Vendor\\ABSPATH')) {
    exit;
}
class FS_Scope_Entity extends \WP2FA_Vendor\FS_Entity
{
    /**
     * @var string
     */
    public $public_key;
    /**
     * @var string
     */
    public $secret_key;
    /**
     * @param bool|stdClass $scope_entity
     */
    function __construct($scope_entity = \false)
    {
        parent::__construct($scope_entity);
    }
}
