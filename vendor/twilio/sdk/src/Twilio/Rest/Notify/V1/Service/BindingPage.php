<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Notify\V1\Service;

use WP2FA_Vendor\Twilio\Http\Response;
use WP2FA_Vendor\Twilio\Page;
use WP2FA_Vendor\Twilio\Version;
/**
 * PLEASE NOTE that this class contains beta products that are subject to change. Use them with caution.
 */
class BindingPage extends Page
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
     * @return BindingInstance \Twilio\Rest\Notify\V1\Service\BindingInstance
     */
    public function buildInstance(array $payload) : BindingInstance
    {
        return new BindingInstance($this->version, $payload, $this->solution['serviceSid']);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Notify.V1.BindingPage]';
    }
}
