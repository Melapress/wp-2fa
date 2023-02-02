<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Numbers\V2\RegulatoryCompliance;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class RegulationContext extends InstanceContext
{
    /**
     * Initialize the RegulationContext
     *
     * @param Version $version Version that contains the resource
     * @param string $sid The unique string that identifies the Regulation resource
     */
    public function __construct(Version $version, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['sid' => $sid];
        $this->uri = '/RegulatoryCompliance/Regulations/' . \rawurlencode($sid) . '';
    }
    /**
     * Fetch the RegulationInstance
     *
     * @return RegulationInstance Fetched RegulationInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : RegulationInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new RegulationInstance($this->version, $payload, $this->solution['sid']);
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
        return '[Twilio.Numbers.V2.RegulationContext ' . \implode(' ', $context) . ']';
    }
}
