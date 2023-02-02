<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Oauth\V1;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class OauthContext extends InstanceContext
{
    /**
     * Initialize the OauthContext
     *
     * @param Version $version Version that contains the resource
     */
    public function __construct(Version $version)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = [];
        $this->uri = '/certs';
    }
    /**
     * Fetch the OauthInstance
     *
     * @return OauthInstance Fetched OauthInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : OauthInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new OauthInstance($this->version, $payload);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        $context = [];
        foreach ($this->solution as $key => $value) {
            $context[] = "{$key}={$value}";
        }
        return '[Twilio.Oauth.V1.OauthContext ' . \implode(' ', $context) . ']';
    }
}
