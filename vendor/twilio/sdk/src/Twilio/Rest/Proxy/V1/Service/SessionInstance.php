<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Proxy\V1\Service;

use WP2FA_Vendor\Twilio\Deserialize;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Rest\Proxy\V1\Service\Session\InteractionList;
use WP2FA_Vendor\Twilio\Rest\Proxy\V1\Service\Session\ParticipantList;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * PLEASE NOTE that this class contains beta products that are subject to change. Use them with caution.
 *
 * @property string $sid
 * @property string $serviceSid
 * @property string $accountSid
 * @property \DateTime $dateStarted
 * @property \DateTime $dateEnded
 * @property \DateTime $dateLastInteraction
 * @property \DateTime $dateExpiry
 * @property string $uniqueName
 * @property string $status
 * @property string $closedReason
 * @property int $ttl
 * @property string $mode
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $url
 * @property array $links
 */
class SessionInstance extends InstanceResource
{
    protected $_interactions;
    protected $_participants;
    /**
     * Initialize the SessionInstance
     *
     * @param Version $version Version that contains the resource
     * @param mixed[] $payload The response payload
     * @param string $serviceSid The SID of the resource's parent Service
     * @param string $sid The unique string that identifies the resource
     */
    public function __construct(Version $version, array $payload, string $serviceSid, string $sid = null)
    {
        parent::__construct($version);
        // Marshaled Properties
        $this->properties = ['sid' => Values::array_get($payload, 'sid'), 'serviceSid' => Values::array_get($payload, 'service_sid'), 'accountSid' => Values::array_get($payload, 'account_sid'), 'dateStarted' => Deserialize::dateTime(Values::array_get($payload, 'date_started')), 'dateEnded' => Deserialize::dateTime(Values::array_get($payload, 'date_ended')), 'dateLastInteraction' => Deserialize::dateTime(Values::array_get($payload, 'date_last_interaction')), 'dateExpiry' => Deserialize::dateTime(Values::array_get($payload, 'date_expiry')), 'uniqueName' => Values::array_get($payload, 'unique_name'), 'status' => Values::array_get($payload, 'status'), 'closedReason' => Values::array_get($payload, 'closed_reason'), 'ttl' => Values::array_get($payload, 'ttl'), 'mode' => Values::array_get($payload, 'mode'), 'dateCreated' => Deserialize::dateTime(Values::array_get($payload, 'date_created')), 'dateUpdated' => Deserialize::dateTime(Values::array_get($payload, 'date_updated')), 'url' => Values::array_get($payload, 'url'), 'links' => Values::array_get($payload, 'links')];
        $this->solution = ['serviceSid' => $serviceSid, 'sid' => $sid ?: $this->properties['sid']];
    }
    /**
     * Generate an instance context for the instance, the context is capable of
     * performing various actions.  All instance actions are proxied to the context
     *
     * @return SessionContext Context for this SessionInstance
     */
    protected function proxy() : SessionContext
    {
        if (!$this->context) {
            $this->context = new SessionContext($this->version, $this->solution['serviceSid'], $this->solution['sid']);
        }
        return $this->context;
    }
    /**
     * Fetch the SessionInstance
     *
     * @return SessionInstance Fetched SessionInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : SessionInstance
    {
        return $this->proxy()->fetch();
    }
    /**
     * Delete the SessionInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->proxy()->delete();
    }
    /**
     * Update the SessionInstance
     *
     * @param array|Options $options Optional Arguments
     * @return SessionInstance Updated SessionInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : SessionInstance
    {
        return $this->proxy()->update($options);
    }
    /**
     * Access the interactions
     */
    protected function getInteractions() : InteractionList
    {
        return $this->proxy()->interactions;
    }
    /**
     * Access the participants
     */
    protected function getParticipants() : ParticipantList
    {
        return $this->proxy()->participants;
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
        return '[Twilio.Proxy.V1.SessionInstance ' . \implode(' ', $context) . ']';
    }
}
