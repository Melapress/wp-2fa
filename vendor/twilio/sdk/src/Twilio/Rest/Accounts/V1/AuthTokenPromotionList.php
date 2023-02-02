<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Accounts\V1;

use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Version;
class AuthTokenPromotionList extends ListResource
{
    /**
     * Construct the AuthTokenPromotionList
     *
     * @param Version $version Version that contains the resource
     */
    public function __construct(Version $version)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = [];
    }
    /**
     * Constructs a AuthTokenPromotionContext
     */
    public function getContext() : AuthTokenPromotionContext
    {
        return new AuthTokenPromotionContext($this->version);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Accounts.V1.AuthTokenPromotionList]';
    }
}
