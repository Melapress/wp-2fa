<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Numbers\V2\RegulatoryCompliance\Bundle;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class EvaluationContext extends InstanceContext
{
    /**
     * Initialize the EvaluationContext
     *
     * @param Version $version Version that contains the resource
     * @param string $bundleSid The unique string that identifies the resource
     * @param string $sid The unique string that identifies the Evaluation resource
     */
    public function __construct(Version $version, $bundleSid, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['bundleSid' => $bundleSid, 'sid' => $sid];
        $this->uri = '/RegulatoryCompliance/Bundles/' . \rawurlencode($bundleSid) . '/Evaluations/' . \rawurlencode($sid) . '';
    }
    /**
     * Fetch the EvaluationInstance
     *
     * @return EvaluationInstance Fetched EvaluationInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : EvaluationInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new EvaluationInstance($this->version, $payload, $this->solution['bundleSid'], $this->solution['sid']);
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
        return '[Twilio.Numbers.V2.EvaluationContext ' . \implode(' ', $context) . ']';
    }
}
