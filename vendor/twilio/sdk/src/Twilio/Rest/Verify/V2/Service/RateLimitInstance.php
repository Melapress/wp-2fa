<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Verify\V2\Service;

use WP2FA_Vendor\Twilio\Deserialize;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Rest\Verify\V2\Service\RateLimit\BucketList;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property string $sid
 * @property string $serviceSid
 * @property string $accountSid
 * @property string $uniqueName
 * @property string $description
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $url
 * @property array $links
 */
class RateLimitInstance extends InstanceResource
{
    protected $_buckets;
    /**
     * Initialize the RateLimitInstance
     *
     * @param Version $version Version that contains the resource
     * @param mixed[] $payload The response payload
     * @param string $serviceSid The SID of the Service that the resource is
     *                           associated with
     * @param string $sid The unique string that identifies the resource
     */
    public function __construct(Version $version, array $payload, string $serviceSid, string $sid = null)
    {
        parent::__construct($version);
        // Marshaled Properties
        $this->properties = ['sid' => Values::array_get($payload, 'sid'), 'serviceSid' => Values::array_get($payload, 'service_sid'), 'accountSid' => Values::array_get($payload, 'account_sid'), 'uniqueName' => Values::array_get($payload, 'unique_name'), 'description' => Values::array_get($payload, 'description'), 'dateCreated' => Deserialize::dateTime(Values::array_get($payload, 'date_created')), 'dateUpdated' => Deserialize::dateTime(Values::array_get($payload, 'date_updated')), 'url' => Values::array_get($payload, 'url'), 'links' => Values::array_get($payload, 'links')];
        $this->solution = ['serviceSid' => $serviceSid, 'sid' => $sid ?: $this->properties['sid']];
    }
    /**
     * Generate an instance context for the instance, the context is capable of
     * performing various actions.  All instance actions are proxied to the context
     *
     * @return RateLimitContext Context for this RateLimitInstance
     */
    protected function proxy() : RateLimitContext
    {
        if (!$this->context) {
            $this->context = new RateLimitContext($this->version, $this->solution['serviceSid'], $this->solution['sid']);
        }
        return $this->context;
    }
    /**
     * Update the RateLimitInstance
     *
     * @param array|Options $options Optional Arguments
     * @return RateLimitInstance Updated RateLimitInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : RateLimitInstance
    {
        return $this->proxy()->update($options);
    }
    /**
     * Fetch the RateLimitInstance
     *
     * @return RateLimitInstance Fetched RateLimitInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : RateLimitInstance
    {
        return $this->proxy()->fetch();
    }
    /**
     * Delete the RateLimitInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->proxy()->delete();
    }
    /**
     * Access the buckets
     */
    protected function getBuckets() : BucketList
    {
        return $this->proxy()->buckets;
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
        return '[Twilio.Verify.V2.RateLimitInstance ' . \implode(' ', $context) . ']';
    }
}
