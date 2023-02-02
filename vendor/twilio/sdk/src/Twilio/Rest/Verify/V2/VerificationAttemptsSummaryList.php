<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Verify\V2;

use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Version;
class VerificationAttemptsSummaryList extends ListResource
{
    /**
     * Construct the VerificationAttemptsSummaryList
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
     * Constructs a VerificationAttemptsSummaryContext
     */
    public function getContext() : VerificationAttemptsSummaryContext
    {
        return new VerificationAttemptsSummaryContext($this->version);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Verify.V2.VerificationAttemptsSummaryList]';
    }
}
