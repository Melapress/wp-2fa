<?php

namespace WP2FA_Vendor;

if (!\class_exists('WP2FA_Vendor\\Freemius_InvalidArgumentException')) {
    exit;
}
if (!\class_exists('WP2FA_Vendor\\Freemius_ArgumentNotExistException')) {
    class Freemius_ArgumentNotExistException extends \WP2FA_Vendor\Freemius_InvalidArgumentException
    {
    }
}
