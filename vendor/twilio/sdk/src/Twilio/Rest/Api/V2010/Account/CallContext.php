<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Api\V2010\Account;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Call\EventList;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Call\FeedbackList;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Call\NotificationList;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Call\PaymentList;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Call\RecordingList;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Call\SiprecList;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Call\StreamList;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Call\UserDefinedMessageList;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Call\UserDefinedMessageSubscriptionList;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property RecordingList $recordings
 * @property NotificationList $notifications
 * @property FeedbackList $feedback
 * @property EventList $events
 * @property PaymentList $payments
 * @property SiprecList $siprec
 * @property StreamList $streams
 * @property UserDefinedMessageSubscriptionList $userDefinedMessageSubscriptions
 * @property UserDefinedMessageList $userDefinedMessages
 * @method \Twilio\Rest\Api\V2010\Account\Call\RecordingContext recordings(string $sid)
 * @method \Twilio\Rest\Api\V2010\Account\Call\NotificationContext notifications(string $sid)
 * @method \Twilio\Rest\Api\V2010\Account\Call\FeedbackContext feedback()
 * @method \Twilio\Rest\Api\V2010\Account\Call\PaymentContext payments(string $sid)
 * @method \Twilio\Rest\Api\V2010\Account\Call\SiprecContext siprec(string $sid)
 * @method \Twilio\Rest\Api\V2010\Account\Call\StreamContext streams(string $sid)
 * @method \Twilio\Rest\Api\V2010\Account\Call\UserDefinedMessageSubscriptionContext userDefinedMessageSubscriptions(string $sid)
 */
class CallContext extends InstanceContext
{
    protected $_recordings;
    protected $_notifications;
    protected $_feedback;
    protected $_events;
    protected $_payments;
    protected $_siprec;
    protected $_streams;
    protected $_userDefinedMessageSubscriptions;
    protected $_userDefinedMessages;
    /**
     * Initialize the CallContext
     *
     * @param Version $version Version that contains the resource
     * @param string $accountSid The SID of the Account that created the
     *                           resource(s) to fetch
     * @param string $sid The SID of the Call resource to fetch
     */
    public function __construct(Version $version, $accountSid, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['accountSid' => $accountSid, 'sid' => $sid];
        $this->uri = '/Accounts/' . \rawurlencode($accountSid) . '/Calls/' . \rawurlencode($sid) . '.json';
    }
    /**
     * Delete the CallInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->version->delete('DELETE', $this->uri);
    }
    /**
     * Fetch the CallInstance
     *
     * @return CallInstance Fetched CallInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : CallInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new CallInstance($this->version, $payload, $this->solution['accountSid'], $this->solution['sid']);
    }
    /**
     * Update the CallInstance
     *
     * @param array|Options $options Optional Arguments
     * @return CallInstance Updated CallInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : CallInstance
    {
        $options = new Values($options);
        $data = Values::of(['Url' => $options['url'], 'Method' => $options['method'], 'Status' => $options['status'], 'FallbackUrl' => $options['fallbackUrl'], 'FallbackMethod' => $options['fallbackMethod'], 'StatusCallback' => $options['statusCallback'], 'StatusCallbackMethod' => $options['statusCallbackMethod'], 'Twiml' => $options['twiml'], 'TimeLimit' => $options['timeLimit']]);
        $payload = $this->version->update('POST', $this->uri, [], $data);
        return new CallInstance($this->version, $payload, $this->solution['accountSid'], $this->solution['sid']);
    }
    /**
     * Access the recordings
     */
    protected function getRecordings() : RecordingList
    {
        if (!$this->_recordings) {
            $this->_recordings = new RecordingList($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->_recordings;
    }
    /**
     * Access the notifications
     */
    protected function getNotifications() : NotificationList
    {
        if (!$this->_notifications) {
            $this->_notifications = new NotificationList($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->_notifications;
    }
    /**
     * Access the feedback
     */
    protected function getFeedback() : FeedbackList
    {
        if (!$this->_feedback) {
            $this->_feedback = new FeedbackList($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->_feedback;
    }
    /**
     * Access the events
     */
    protected function getEvents() : EventList
    {
        if (!$this->_events) {
            $this->_events = new EventList($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->_events;
    }
    /**
     * Access the payments
     */
    protected function getPayments() : PaymentList
    {
        if (!$this->_payments) {
            $this->_payments = new PaymentList($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->_payments;
    }
    /**
     * Access the siprec
     */
    protected function getSiprec() : SiprecList
    {
        if (!$this->_siprec) {
            $this->_siprec = new SiprecList($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->_siprec;
    }
    /**
     * Access the streams
     */
    protected function getStreams() : StreamList
    {
        if (!$this->_streams) {
            $this->_streams = new StreamList($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->_streams;
    }
    /**
     * Access the userDefinedMessageSubscriptions
     */
    protected function getUserDefinedMessageSubscriptions() : UserDefinedMessageSubscriptionList
    {
        if (!$this->_userDefinedMessageSubscriptions) {
            $this->_userDefinedMessageSubscriptions = new UserDefinedMessageSubscriptionList($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->_userDefinedMessageSubscriptions;
    }
    /**
     * Access the userDefinedMessages
     */
    protected function getUserDefinedMessages() : UserDefinedMessageList
    {
        if (!$this->_userDefinedMessages) {
            $this->_userDefinedMessages = new UserDefinedMessageList($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->_userDefinedMessages;
    }
    /**
     * Magic getter to lazy load subresources
     *
     * @param string $name Subresource to return
     * @return ListResource The requested subresource
     * @throws TwilioException For unknown subresources
     */
    public function __get(string $name) : ListResource
    {
        if (\property_exists($this, '_' . $name)) {
            $method = 'get' . \ucfirst($name);
            return $this->{$method}();
        }
        throw new TwilioException('Unknown subresource ' . $name);
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
        $context = [];
        foreach ($this->solution as $key => $value) {
            $context[] = "{$key}={$value}";
        }
        return '[Twilio.Api.V2010.CallContext ' . \implode(' ', $context) . ']';
    }
}
