<?php

namespace WP2FA_Vendor;

if (!\class_exists('WP2FA_Vendor\\Freemius_Exception')) {
    exit;
}
if (!\class_exists('WP2FA_Vendor\\Freemius_OAuthException')) {
    class Freemius_OAuthException extends \WP2FA_Vendor\Freemius_Exception
    {
        public function __construct($pResult)
        {
            parent::__construct($pResult);
        }
    }
}
