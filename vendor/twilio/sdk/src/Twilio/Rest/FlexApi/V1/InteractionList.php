<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\FlexApi\V1;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Serialize;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class InteractionList extends ListResource
{
    /**
     * Construct the InteractionList
     *
     * @param Version $version Version that contains the resource
     */
    public function __construct(Version $version)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = [];
        $this->uri = '/Interactions';
    }
    /**
     * Create the InteractionInstance
     *
     * @param array $channel The Interaction's channel
     * @param array $routing The Interaction's routing logic
     * @return InteractionInstance Created InteractionInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function create(array $channel, array $routing) : InteractionInstance
    {
        $data = Values::of(['Channel' => Serialize::jsonObject($channel), 'Routing' => Serialize::jsonObject($routing)]);
        $payload = $this->version->create('POST', $this->uri, [], $data);
        return new InteractionInstance($this->version, $payload);
    }
    /**
     * Constructs a InteractionContext
     *
     * @param string $sid The SID that identifies the resource to fetch
     */
    public function getContext(string $sid) : InteractionContext
    {
        return new InteractionContext($this->version, $sid);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.FlexApi.V1.InteractionList]';
    }
}
