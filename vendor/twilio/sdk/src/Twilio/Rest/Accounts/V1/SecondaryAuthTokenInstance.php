<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Accounts\V1;

use WP2FA_Vendor\Twilio\Deserialize;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceResource;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property string $accountSid
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $secondaryAuthToken
 * @property string $url
 */
class SecondaryAuthTokenInstance extends InstanceResource
{
    /**
     * Initialize the SecondaryAuthTokenInstance
     *
     * @param Version $version Version that contains the resource
     * @param mixed[] $payload The response payload
     */
    public function __construct(Version $version, array $payload)
    {
        parent::__construct($version);
        // Marshaled Properties
        $this->properties = ['accountSid' => Values::array_get($payload, 'account_sid'), 'dateCreated' => Deserialize::dateTime(Values::array_get($payload, 'date_created')), 'dateUpdated' => Deserialize::dateTime(Values::array_get($payload, 'date_updated')), 'secondaryAuthToken' => Values::array_get($payload, 'secondary_auth_token'), 'url' => Values::array_get($payload, 'url')];
        $this->solution = [];
    }
    /**
     * Generate an instance context for the instance, the context is capable of
     * performing various actions.  All instance actions are proxied to the context
     *
     * @return SecondaryAuthTokenContext Context for this SecondaryAuthTokenInstance
     */
    protected function proxy() : SecondaryAuthTokenContext
    {
        if (!$this->context) {
            $this->context = new SecondaryAuthTokenContext($this->version);
        }
        return $this->context;
    }
    /**
     * Create the SecondaryAuthTokenInstance
     *
     * @return SecondaryAuthTokenInstance Created SecondaryAuthTokenInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function create() : SecondaryAuthTokenInstance
    {
        return $this->proxy()->create();
    }
    /**
     * Delete the SecondaryAuthTokenInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->proxy()->delete();
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
        return '[Twilio.Accounts.V1.SecondaryAuthTokenInstance ' . \implode(' ', $context) . ']';
    }
}
