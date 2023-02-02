<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Conversations\V1\Service\Conversation;

use WP2FA_Vendor\Twilio\Deserialize;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Rest\Conversations\V1\Service\Conversation\Message\DeliveryReceiptList;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property string $accountSid
 * @property string $chatServiceSid
 * @property string $conversationSid
 * @property string $sid
 * @property int $index
 * @property string $author
 * @property string $body
 * @property array[] $media
 * @property string $attributes
 * @property string $participantSid
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property array $delivery
 * @property string $url
 * @property array $links
 * @property string $contentSid
 */
class MessageInstance extends InstanceResource
{
    protected $_deliveryReceipts;
    /**
     * Initialize the MessageInstance
     *
     * @param Version $version Version that contains the resource
     * @param mixed[] $payload The response payload
     * @param string $chatServiceSid The SID of the Conversation Service that the
     *                               resource is associated with.
     * @param string $conversationSid The unique ID of the Conversation for this
     *                                message.
     * @param string $sid A 34 character string that uniquely identifies this
     *                    resource.
     */
    public function __construct(Version $version, array $payload, string $chatServiceSid, string $conversationSid, string $sid = null)
    {
        parent::__construct($version);
        // Marshaled Properties
        $this->properties = ['accountSid' => Values::array_get($payload, 'account_sid'), 'chatServiceSid' => Values::array_get($payload, 'chat_service_sid'), 'conversationSid' => Values::array_get($payload, 'conversation_sid'), 'sid' => Values::array_get($payload, 'sid'), 'index' => Values::array_get($payload, 'index'), 'author' => Values::array_get($payload, 'author'), 'body' => Values::array_get($payload, 'body'), 'media' => Values::array_get($payload, 'media'), 'attributes' => Values::array_get($payload, 'attributes'), 'participantSid' => Values::array_get($payload, 'participant_sid'), 'dateCreated' => Deserialize::dateTime(Values::array_get($payload, 'date_created')), 'dateUpdated' => Deserialize::dateTime(Values::array_get($payload, 'date_updated')), 'delivery' => Values::array_get($payload, 'delivery'), 'url' => Values::array_get($payload, 'url'), 'links' => Values::array_get($payload, 'links'), 'contentSid' => Values::array_get($payload, 'content_sid')];
        $this->solution = ['chatServiceSid' => $chatServiceSid, 'conversationSid' => $conversationSid, 'sid' => $sid ?: $this->properties['sid']];
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
            $this->context = new MessageContext($this->version, $this->solution['chatServiceSid'], $this->solution['conversationSid'], $this->solution['sid']);
        }
        return $this->context;
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
     * Delete the MessageInstance
     *
     * @param array|Options $options Optional Arguments
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete(array $options = []) : bool
    {
        return $this->proxy()->delete($options);
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
     * Access the deliveryReceipts
     */
    protected function getDeliveryReceipts() : DeliveryReceiptList
    {
        return $this->proxy()->deliveryReceipts;
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
        return '[Twilio.Conversations.V1.MessageInstance ' . \implode(' ', $context) . ']';
    }
}
