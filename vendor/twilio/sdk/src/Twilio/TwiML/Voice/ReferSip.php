<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\TwiML\Voice;

use WP2FA_Vendor\Twilio\TwiML\TwiML;
class ReferSip extends TwiML
{
    /**
     * ReferSip constructor.
     *
     * @param string $sipUrl SIP URL
     */
    public function __construct($sipUrl)
    {
        parent::__construct('Sip', $sipUrl);
    }
}
