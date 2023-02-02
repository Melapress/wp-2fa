<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Pricing\V1\Voice;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class NumberContext extends InstanceContext
{
    /**
     * Initialize the NumberContext
     *
     * @param Version $version Version that contains the resource
     * @param string $number The phone number to fetch
     */
    public function __construct(Version $version, $number)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['number' => $number];
        $this->uri = '/Voice/Numbers/' . \rawurlencode($number) . '';
    }
    /**
     * Fetch the NumberInstance
     *
     * @return NumberInstance Fetched NumberInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : NumberInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new NumberInstance($this->version, $payload, $this->solution['number']);
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
        return '[Twilio.Pricing.V1.NumberContext ' . \implode(' ', $context) . ']';
    }
}
