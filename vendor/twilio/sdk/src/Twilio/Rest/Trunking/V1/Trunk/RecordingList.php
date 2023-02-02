<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Trunking\V1\Trunk;

use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Version;
class RecordingList extends ListResource
{
    /**
     * Construct the RecordingList
     *
     * @param Version $version Version that contains the resource
     * @param string $trunkSid The unique string that identifies the resource
     */
    public function __construct(Version $version, string $trunkSid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['trunkSid' => $trunkSid];
    }
    /**
     * Constructs a RecordingContext
     */
    public function getContext() : RecordingContext
    {
        return new RecordingContext($this->version, $this->solution['trunkSid']);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Trunking.V1.RecordingList]';
    }
}
