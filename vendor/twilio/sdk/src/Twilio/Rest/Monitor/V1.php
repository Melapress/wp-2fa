<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Monitor;

use WP2FA_Vendor\Twilio\Domain;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Rest\Monitor\V1\AlertList;
use WP2FA_Vendor\Twilio\Rest\Monitor\V1\EventList;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property AlertList $alerts
 * @property EventList $events
 * @method \Twilio\Rest\Monitor\V1\AlertContext alerts(string $sid)
 * @method \Twilio\Rest\Monitor\V1\EventContext events(string $sid)
 */
class V1 extends Version
{
    protected $_alerts;
    protected $_events;
    /**
     * Construct the V1 version of Monitor
     *
     * @param Domain $domain Domain that contains the version
     */
    public function __construct(Domain $domain)
    {
        parent::__construct($domain);
        $this->version = 'v1';
    }
    protected function getAlerts() : AlertList
    {
        if (!$this->_alerts) {
            $this->_alerts = new AlertList($this);
        }
        return $this->_alerts;
    }
    protected function getEvents() : EventList
    {
        if (!$this->_events) {
            $this->_events = new EventList($this);
        }
        return $this->_events;
    }
    /**
     * Magic getter to lazy load root resources
     *
     * @param string $name Resource to return
     * @return \Twilio\ListResource The requested resource
     * @throws TwilioException For unknown resource
     */
    public function __get(string $name)
    {
        $method = 'get' . \ucfirst($name);
        if (\method_exists($this, $method)) {
            return $this->{$method}();
        }
        throw new TwilioException('Unknown resource ' . $name);
    }
    /**
     * Magic caller to get resource contexts
     *
     * @param string $name Resource to return
     * @param array $arguments Context parameters
     * @return InstanceContext The requested resource context
     * @throws TwilioException For unknown resource
     */
    public function __call(string $name, array $arguments) : InstanceContext
    {
        $property = $this->{$name};
        if (\method_exists($property, 'getContext')) {
            return \call_user_func_array(array($property, 'getContext'), $arguments);
        }
        throw new TwilioException('Resource does not have a context');
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Monitor.V1]';
    }
}
