<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Video\V1\Room\Participant;

use WP2FA_Vendor\Twilio\Deserialize;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceResource;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property string $sid
 * @property string $participantSid
 * @property string $publisherSid
 * @property string $roomSid
 * @property string $name
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property bool $enabled
 * @property string $kind
 * @property string $url
 */
class SubscribedTrackInstance extends InstanceResource
{
    /**
     * Initialize the SubscribedTrackInstance
     *
     * @param Version $version Version that contains the resource
     * @param mixed[] $payload The response payload
     * @param string $roomSid The SID of the room where the track is published
     * @param string $participantSid The SID of the participant that subscribes to
     *                               the track
     * @param string $sid The SID that identifies the resource to fetch
     */
    public function __construct(Version $version, array $payload, string $roomSid, string $participantSid, string $sid = null)
    {
        parent::__construct($version);
        // Marshaled Properties
        $this->properties = ['sid' => Values::array_get($payload, 'sid'), 'participantSid' => Values::array_get($payload, 'participant_sid'), 'publisherSid' => Values::array_get($payload, 'publisher_sid'), 'roomSid' => Values::array_get($payload, 'room_sid'), 'name' => Values::array_get($payload, 'name'), 'dateCreated' => Deserialize::dateTime(Values::array_get($payload, 'date_created')), 'dateUpdated' => Deserialize::dateTime(Values::array_get($payload, 'date_updated')), 'enabled' => Values::array_get($payload, 'enabled'), 'kind' => Values::array_get($payload, 'kind'), 'url' => Values::array_get($payload, 'url')];
        $this->solution = ['roomSid' => $roomSid, 'participantSid' => $participantSid, 'sid' => $sid ?: $this->properties['sid']];
    }
    /**
     * Generate an instance context for the instance, the context is capable of
     * performing various actions.  All instance actions are proxied to the context
     *
     * @return SubscribedTrackContext Context for this SubscribedTrackInstance
     */
    protected function proxy() : SubscribedTrackContext
    {
        if (!$this->context) {
            $this->context = new SubscribedTrackContext($this->version, $this->solution['roomSid'], $this->solution['participantSid'], $this->solution['sid']);
        }
        return $this->context;
    }
    /**
     * Fetch the SubscribedTrackInstance
     *
     * @return SubscribedTrackInstance Fetched SubscribedTrackInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : SubscribedTrackInstance
    {
        return $this->proxy()->fetch();
    }
    /**
     * Magic getter to access properties
     *
     * @param string $name Property to access
     * @return mixed The requested property
     * @throws TwilioException For unknown properties
     */
    public function __get(string $name)
    {
        if (\array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        }
        if (\property_exists($this, '_' . $name)) {
            $method = 'get' . \ucfirst($name);
            return $this->{$method}();
        }
        throw new TwilioException('Unknown property: ' . $name);
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
        return '[Twilio.Video.V1.SubscribedTrackInstance ' . \implode(' ', $context) . ']';
    }
}
