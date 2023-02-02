<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Api\V2010\Account;

use WP2FA_Vendor\Twilio\Deserialize;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Message\FeedbackList;
use WP2FA_Vendor\Twilio\Rest\Api\V2010\Account\Message\MediaList;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property string $body
 * @property string $numSegments
 * @property string $direction
 * @property string $from
 * @property string $to
 * @property \DateTime $dateUpdated
 * @property string $price
 * @property string $errorMessage
 * @property string $uri
 * @property string $accountSid
 * @property string $numMedia
 * @property string $status
 * @property string $messagingServiceSid
 * @property string $sid
 * @property \DateTime $dateSent
 * @property \DateTime $dateCreated
 * @property int $errorCode
 * @property string $priceUnit
 * @property string $apiVersion
 * @property array $subresourceUris
 */
class MessageInstance extends InstanceResource
{
    protected $_media;
    protected $_feedback;
    /**
     * Initialize the MessageInstance
     *
     * @param Version $version Version that contains the resource
     * @param mixed[] $payload The response payload
     * @param string $accountSid The SID of the Account that created the resource
     * @param string $sid The unique string that identifies the resource
     */
    public function __construct(Version $version, array $payload, string $accountSid, string $sid = null)
    {
        parent::__construct($version);
        // Marshaled Properties
        $this->properties = ['body' => Values::array_get($payload, 'body'), 'numSegments' => Values::array_get($payload, 'num_segments'), 'direction' => Values::array_get($payload, 'direction'), 'from' => Values::array_get($payload, 'from'), 'to' => Values::array_get($payload, 'to'), 'dateUpdated' => Deserialize::dateTime(Values::array_get($payload, 'date_updated')), 'price' => Values::array_get($payload, 'price'), 'errorMessage' => Values::array_get($payload, 'error_message'), 'uri' => Values::array_get($payload, 'uri'), 'accountSid' => Values::array_get($payload, 'account_sid'), 'numMedia' => Values::array_get($payload, 'num_media'), 'status' => Values::array_get($payload, 'status'), 'messagingServiceSid' => Values::array_get($payload, 'messaging_service_sid'), 'sid' => Values::array_get($payload, 'sid'), 'dateSent' => Deserialize::dateTime(Values::array_get($payload, 'date_sent')), 'dateCreated' => Deserialize::dateTime(Values::array_get($payload, 'date_created')), 'errorCode' => Values::array_get($payload, 'error_code'), 'priceUnit' => Values::array_get($payload, 'price_unit'), 'apiVersion' => Values::array_get($payload, 'api_version'), 'subresourceUris' => Values::array_get($payload, 'subresource_uris')];
        $this->solution = ['accountSid' => $accountSid, 'sid' => $sid ?: $this->properties['sid']];
    }
    /**
     * Generate an instance context for the instance, the context is capable of
     * performing various actions.  All instance actions are proxied to the context
     *
     * @return MessageContext Context for this MessageInstance
     */
    protected function proxy() : MessageContext
    {
        if (!$this->context) {
            $this->context = new MessageContext($this->version, $this->solution['accountSid'], $this->solution['sid']);
        }
        return $this->context;
    }
    /**
     * Delete the MessageInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->proxy()->delete();
    }
    /**
     * Fetch the MessageInstance
     *
     * @return MessageInstance Fetched MessageInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : MessageInstance
    {
        return $this->proxy()->fetch();
    }
    /**
     * Update the MessageInstance
     *
     * @param array|Options $options Optional Arguments
     * @return MessageInstance Updated MessageInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : MessageInstance
    {
        return $this->proxy()->update($options);
    }
    /**
     * Access the media
     */
    protected function getMedia() : MediaList
    {
        return $this->proxy()->media;
    }
    /**
     * Access the feedback
     */
    protected function getFeedback() : FeedbackList
    {
        return $this->proxy()->feedback;
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
        return '[Twilio.Api.V2010.MessageInstance ' . \implode(' ', $context) . ']';
    }
}
