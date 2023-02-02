<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Trusthub\V1\CustomerProfiles;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class CustomerProfilesChannelEndpointAssignmentContext extends InstanceContext
{
    /**
     * Initialize the CustomerProfilesChannelEndpointAssignmentContext
     *
     * @param Version $version Version that contains the resource
     * @param string $customerProfileSid The unique string that identifies the
     *                                   resource.
     * @param string $sid The unique string that identifies the resource
     */
    public function __construct(Version $version, $customerProfileSid, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['customerProfileSid' => $customerProfileSid, 'sid' => $sid];
        $this->uri = '/CustomerProfiles/' . \rawurlencode($customerProfileSid) . '/ChannelEndpointAssignments/' . \rawurlencode($sid) . '';
    }
    /**
     * Fetch the CustomerProfilesChannelEndpointAssignmentInstance
     *
     * @return CustomerProfilesChannelEndpointAssignmentInstance Fetched
     *                                                           CustomerProfilesChannelEndpointAssignmentInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : CustomerProfilesChannelEndpointAssignmentInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new CustomerProfilesChannelEndpointAssignmentInstance($this->version, $payload, $this->solution['customerProfileSid'], $this->solution['sid']);
    }
    /**
     * Delete the CustomerProfilesChannelEndpointAssignmentInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->version->delete('DELETE', $this->uri);
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
        return '[Twilio.Trusthub.V1.CustomerProfilesChannelEndpointAssignmentContext ' . \implode(' ', $context) . ']';
    }
}
