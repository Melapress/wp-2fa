<?php

namespace WP2FA_Vendor;

if (!\class_exists('WP2FA_Vendor\\Freemius_Exception')) {
    exit;
}
if (!\class_exists('WP2FA_Vendor\\Freemius_InvalidArgumentException')) {
    class Freemius_InvalidArgumentException extends \WP2FA_Vendor\Freemius_Exception
    {
    }
}
