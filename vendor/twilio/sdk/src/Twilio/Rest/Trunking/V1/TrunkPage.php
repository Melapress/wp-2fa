<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Trunking\V1;

use WP2FA_Vendor\Twilio\Http\Response;
use WP2FA_Vendor\Twilio\Page;
use WP2FA_Vendor\Twilio\Version;
class TrunkPage extends Page
{
    /**
     * @param Version $version Version that contains the resource
     * @param Response $response Response from the API
     * @param array $solution The context solution
     */
    public function __construct(Version $version, Response $response, array $solution)
    {
        parent::__construct($version, $response);
        // Path Solution
        $this->solution = $solution;
    }
    /**
     * @param array $payload Payload response from the API
     * @return TrunkInstance \Twilio\Rest\Trunking\V1\TrunkInstance
     */
    public function buildInstance(array $payload) : TrunkInstance
    {
        return new TrunkInstance($this->version, $payload);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Trunking.V1.TrunkPage]';
    }
}
