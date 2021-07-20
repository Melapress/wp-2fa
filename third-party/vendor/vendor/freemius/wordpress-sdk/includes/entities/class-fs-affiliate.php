<?php

namespace WP2FA_Vendor;

/**
 * @package     Freemius
 * @copyright   Copyright (c) 2015, Freemius, Inc.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since       1.2.3
 */
if (!\defined('WP2FA_Vendor\\ABSPATH')) {
    exit;
}
class FS_Affiliate extends \WP2FA_Vendor\FS_Scope_Entity
{
    #region Properties
    /**
     * @var string
     */
    public $paypal_email;
    /**
     * @var number
     */
    public $custom_affiliate_terms_id;
    /**
     * @var boolean
     */
    public $is_using_custom_terms;
    /**
     * @var string status Enum: `pending`, `rejected`, `suspended`, or `active`. Defaults to `pending`.
     */
    public $status;
    /**
     * @var string
     */
    public $domain;
    #endregion Properties
    /**
     * @author Leo Fajardo
     *
     * @return bool
     */
    function is_active()
    {
        return 'active' === $this->status;
    }
    /**
     * @author Leo Fajardo
     *
     * @return bool
     */
    function is_pending()
    {
        return 'pending' === $this->status;
    }
    /**
     * @author Leo Fajardo
     *
     * @return bool
     */
    function is_suspended()
    {
        return 'suspended' === $this->status;
    }
    /**
     * @author Leo Fajardo
     *
     * @return bool
     */
    function is_rejected()
    {
        return 'rejected' === $this->status;
    }
    /**
     * @author Leo Fajardo
     *
     * @return bool
     */
    function is_blocked()
    {
        return 'blocked' === $this->status;
    }
}
