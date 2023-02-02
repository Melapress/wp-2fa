<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Supersim\V1;

use WP2FA_Vendor\Twilio\Deserialize;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Rest\Supersim\V1\NetworkAccessProfile\NetworkAccessProfileNetworkList;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * PLEASE NOTE that this class contains beta products that are subject to change. Use them with caution.
 *
 * @property string $sid
 * @property string $uniqueName
 * @property string $accountSid
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $url
 * @property array $links
 */
class NetworkAccessProfileInstance extends InstanceResource
{
    protected $_networks;
    /**
     * Initialize the NetworkAccessProfileInstance
     *
     * @param Version $version Version that contains the resource
     * @param mixed[] $payload The response payload
     * @param string $sid The SID that identifies the resource to fetch
     */
    public function __construct(Version $version, array $payload, string $sid = null)
    {
        parent::__construct($version);
        // Marshaled Properties
        $this->properties = ['sid' => Values::array_get($payload, 'sid'), 'uniqueName' => Values::array_get($payload, 'unique_name'), 'accountSid' => Values::array_get($payload, 'account_sid'), 'dateCreated' => Deserialize::dateTime(Values::array_get($payload, 'date_created')), 'dateUpdated' => Deserialize::dateTime(Values::array_get($payload, 'date_updated')), 'url' => Values::array_get($payload, 'url'), 'links' => Values::array_get($payload, 'links')];
        $this->solution = ['sid' => $sid ?: $this->properties['sid']];
    }
    /**
     * Generate an instance context for the instance, the context is capable of
     * performing various actions.  All instance actions are proxied to the context
     *
     * @return NetworkAccessProfileContext Context for this
     *                                     NetworkAccessProfileInstance
     */
    protected function proxy() : NetworkAccessProfileContext
    {
        if (!$this->context) {
            $this->context = new NetworkAccessProfileContext($this->version, $this->solution['sid']);
        }
        return $this->context;
    }
    /**
     * Fetch the NetworkAccessProfileInstance
     *
     * @return NetworkAccessProfileInstance Fetched NetworkAccessProfileInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : NetworkAccessProfileInstance
    {
        return $this->proxy()->fetch();
    }
    /**
     * Update the NetworkAccessProfileInstance
     *
     * @param array|Options $options Optional Arguments
     * @return NetworkAccessProfileInstance Updated NetworkAccessProfileInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : NetworkAccessProfileInstance
    {
        return $this->proxy()->update($options);
    }
    /**
     * Access the networks
     */
    protected function getNetworks() : NetworkAccessProfileNetworkList
    {
        return $this->proxy()->networks;
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
        return '[Twilio.Supersim.V1.NetworkAccessProfileInstance ' . \implode(' ', $context) . ']';
    }
}
