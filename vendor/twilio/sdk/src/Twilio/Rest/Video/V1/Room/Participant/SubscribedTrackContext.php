<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Video\V1\Room\Participant;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class SubscribedTrackContext extends InstanceContext
{
    /**
     * Initialize the SubscribedTrackContext
     *
     * @param Version $version Version that contains the resource
     * @param string $roomSid The SID of the Room where the Track resource to fetch
     *                        is subscribed
     * @param string $participantSid The SID of the participant that subscribes to
     *                               the Track resource to fetch
     * @param string $sid The SID that identifies the resource to fetch
     */
    public function __construct(Version $version, $roomSid, $participantSid, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['roomSid' => $roomSid, 'participantSid' => $participantSid, 'sid' => $sid];
        $this->uri = '/Rooms/' . \rawurlencode($roomSid) . '/Participants/' . \rawurlencode($participantSid) . '/SubscribedTracks/' . \rawurlencode($sid) . '';
    }
    /**
     * Fetch the SubscribedTrackInstance
     *
     * @return SubscribedTrackInstance Fetched SubscribedTrackInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : SubscribedTrackInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new SubscribedTrackInstance($this->version, $payload, $this->solution['roomSid'], $this->solution['participantSid'], $this->solution['sid']);
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
        return '[Twilio.Video.V1.SubscribedTrackContext ' . \implode(' ', $context) . ']';
    }
}
