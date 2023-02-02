<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Routes\V2;

use WP2FA_Vendor\Twilio\Deserialize;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property string $sipTrunkDomain
 * @property string $url
 * @property string $sid
 * @property string $accountSid
 * @property string $friendlyName
 * @property string $voiceRegion
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 */
class TrunkInstance extends InstanceResource
{
    /**
     * Initialize the TrunkInstance
     *
     * @param Version $version Version that contains the resource
     * @param mixed[] $payload The response payload
     * @param string $sipTrunkDomain The SIP Trunk
     */
    public function __construct(Version $version, array $payload, string $sipTrunkDomain = null)
    {
        parent::__construct($version);
        // Marshaled Properties
        $this->properties = ['sipTrunkDomain' => Values::array_get($payload, 'sip_trunk_domain'), 'url' => Values::array_get($payload, 'url'), 'sid' => Values::array_get($payload, 'sid'), 'accountSid' => Values::array_get($payload, 'account_sid'), 'friendlyName' => Values::array_get($payload, 'friendly_name'), 'voiceRegion' => Values::array_get($payload, 'voice_region'), 'dateCreated' => Deserialize::dateTime(Values::array_get($payload, 'date_created')), 'dateUpdated' => Deserialize::dateTime(Values::array_get($payload, 'date_updated'))];
        $this->solution = ['sipTrunkDomain' => $sipTrunkDomain ?: $this->properties['sipTrunkDomain']];
    }
    /**
     * Generate an instance context for the instance, the context is capable of
     * performing various actions.  All instance actions are proxied to the context
     *
     * @return TrunkContext Context for this TrunkInstance
     */
    protected function proxy() : TrunkContext
    {
        if (!$this->context) {
            $this->context = new TrunkContext($this->version, $this->solution['sipTrunkDomain']);
        }
        return $this->context;
    }
    /**
     * Update the TrunkInstance
     *
     * @param array|Options $options Optional Arguments
     * @return TrunkInstance Updated TrunkInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : TrunkInstance
    {
        return $this->proxy()->update($options);
    }
    /**
     * Fetch the TrunkInstance
     *
     * @return TrunkInstance Fetched TrunkInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : TrunkInstance
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
        return '[Twilio.Routes.V2.TrunkInstance ' . \implode(' ', $context) . ']';
    }
}
