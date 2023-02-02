<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Conversations\V1\Conversation;

use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
abstract class MessageOptions
{
    /**
     * @param string $author The channel specific identifier of the message's
     *                       author.
     * @param string $body The content of the message.
     * @param \DateTime $dateCreated The date that this resource was created.
     * @param \DateTime $dateUpdated The date that this resource was last updated.
     * @param string $attributes A string metadata field you can use to store any
     *                           data you wish.
     * @param string $mediaSid The Media SID to be attached to the new Message.
     * @param string $contentSid The unique ID of the multi-channel Rich Content
     *                           template.
     * @param string $contentVariables A structurally valid JSON string that
     *                                 contains values to resolve Rich Content
     *                                 template variables.
     * @param string $xTwilioWebhookEnabled The X-Twilio-Webhook-Enabled HTTP
     *                                      request header
     * @return CreateMessageOptions Options builder
     */
    public static function create(string $author = Values::NONE, string $body = Values::NONE, \DateTime $dateCreated = Values::NONE, \DateTime $dateUpdated = Values::NONE, string $attributes = Values::NONE, string $mediaSid = Values::NONE, string $contentSid = Values::NONE, string $contentVariables = Values::NONE, string $xTwilioWebhookEnabled = Values::NONE) : CreateMessageOptions
    {
        return new CreateMessageOptions($author, $body, $dateCreated, $dateUpdated, $attributes, $mediaSid, $contentSid, $contentVariables, $xTwilioWebhookEnabled);
    }
    /**
     * @param string $author The channel specific identifier of the message's
     *                       author.
     * @param string $body The content of the message.
     * @param \DateTime $dateCreated The date that this resource was created.
     * @param \DateTime $dateUpdated The date that this resource was last updated.
     * @param string $attributes A string metadata field you can use to store any
     *                           data you wish.
     * @param string $xTwilioWebhookEnabled The X-Twilio-Webhook-Enabled HTTP
     *                                      request header
     * @return UpdateMessageOptions Options builder
     */
    public static function update(string $author = Values::NONE, string $body = Values::NONE, \DateTime $dateCreated = Values::NONE, \DateTime $dateUpdated = Values::NONE, string $attributes = Values::NONE, string $xTwilioWebhookEnabled = Values::NONE) : UpdateMessageOptions
    {
        return new UpdateMessageOptions($author, $body, $dateCreated, $dateUpdated, $attributes, $xTwilioWebhookEnabled);
    }
    /**
     * @param string $xTwilioWebhookEnabled The X-Twilio-Webhook-Enabled HTTP
     *                                      request header
     * @return DeleteMessageOptions Options builder
     */
    public static function delete(string $xTwilioWebhookEnabled = Values::NONE) : DeleteMessageOptions
    {
        return new DeleteMessageOptions($xTwilioWebhookEnabled);
    }
    /**
     * @param string $order The sort order of the returned messages
     * @return ReadMessageOptions Options builder
     */
    public static function read(string $order = Values::NONE) : ReadMessageOptions
    {
        return new ReadMessageOptions($order);
    }
}
class CreateMessageOptions extends Options
{
    /**
     * @param string $author The channel specific identifier of the message's
     *                       author.
     * @param string $body The content of the message.
     * @param \DateTime $dateCreated The date that this resource was created.
     * @param \DateTime $dateUpdated The date that this resource was last updated.
     * @param string $attributes A string metadata field you can use to store any
     *                           data you wish.
     * @param string $mediaSid The Media SID to be attached to the new Message.
     * @param string $contentSid The unique ID of the multi-channel Rich Content
     *                           template.
     * @param string $contentVariables A structurally valid JSON string that
     *                                 contains values to resolve Rich Content
     *                                 template variables.
     * @param string $xTwilioWebhookEnabled The X-Twilio-Webhook-Enabled HTTP
     *                                      request header
     */
    public function __construct(string $author = Values::NONE, string $body = Values::NONE, \DateTime $dateCreated = Values::NONE, \DateTime $dateUpdated = Values::NONE, string $attributes = Values::NONE, string $mediaSid = Values::NONE, string $contentSid = Values::NONE, string $contentVariables = Values::NONE, string $xTwilioWebhookEnabled = Values::NONE)
    {
        $this->options['author'] = $author;
        $this->options['body'] = $body;
        $this->options['dateCreated'] = $dateCreated;
        $this->options['dateUpdated'] = $dateUpdated;
        $this->options['attributes'] = $attributes;
        $this->options['mediaSid'] = $mediaSid;
        $this->options['contentSid'] = $contentSid;
        $this->options['contentVariables'] = $contentVariables;
        $this->options['xTwilioWebhookEnabled'] = $xTwilioWebhookEnabled;
    }
    /**
     * The channel specific identifier of the message's author. Defaults to `system`.
     *
     * @param string $author The channel specific identifier of the message's
     *                       author.
     * @return $this Fluent Builder
     */
    public function setAuthor(string $author) : self
    {
        $this->options['author'] = $author;
        return $this;
    }
    /**
     * The content of the message, can be up to 1,600 characters long.
     *
     * @param string $body The content of the message.
     * @return $this Fluent Builder
     */
    public function setBody(string $body) : self
    {
        $this->options['body'] = $body;
        return $this;
    }
    /**
     * The date that this resource was created.
     *
     * @param \DateTime $dateCreated The date that this resource was created.
     * @return $this Fluent Builder
     */
    public function setDateCreated(\DateTime $dateCreated) : self
    {
        $this->options['dateCreated'] = $dateCreated;
        return $this;
    }
    /**
     * The date that this resource was last updated. `null` if the message has not been edited.
     *
     * @param \DateTime $dateUpdated The date that this resource was last updated.
     * @return $this Fluent Builder
     */
    public function setDateUpdated(\DateTime $dateUpdated) : self
    {
        $this->options['dateUpdated'] = $dateUpdated;
        return $this;
    }
    /**
     * A string metadata field you can use to store any data you wish. The string value must contain structurally valid JSON if specified.  **Note** that if the attributes are not set "{}" will be returned.
     *
     * @param string $attributes A string metadata field you can use to store any
     *                           data you wish.
     * @return $this Fluent Builder
     */
    public function setAttributes(string $attributes) : self
    {
        $this->options['attributes'] = $attributes;
        return $this;
    }
    /**
     * The Media SID to be attached to the new Message.
     *
     * @param string $mediaSid The Media SID to be attached to the new Message.
     * @return $this Fluent Builder
     */
    public function setMediaSid(string $mediaSid) : self
    {
        $this->options['mediaSid'] = $mediaSid;
        return $this;
    }
    /**
     * The unique ID of the multi-channel [Rich Content](https://www.twilio.com/docs/content-api) template, required for template-generated messages.  **Note** that if this field is set, `Body` and `MediaSid` parameters are ignored.
     *
     * @param string $contentSid The unique ID of the multi-channel Rich Content
     *                           template.
     * @return $this Fluent Builder
     */
    public function setContentSid(string $contentSid) : self
    {
        $this->options['contentSid'] = $contentSid;
        return $this;
    }
    /**
     * A structurally valid JSON string that contains values to resolve Rich Content template variables.
     *
     * @param string $contentVariables A structurally valid JSON string that
     *                                 contains values to resolve Rich Content
     *                                 template variables.
     * @return $this Fluent Builder
     */
    public function setContentVariables(string $contentVariables) : self
    {
        $this->options['contentVariables'] = $contentVariables;
        return $this;
    }
    /**
     * The X-Twilio-Webhook-Enabled HTTP request header
     *
     * @param string $xTwilioWebhookEnabled The X-Twilio-Webhook-Enabled HTTP
     *                                      request header
     * @return $this Fluent Builder
     */
    public function setXTwilioWebhookEnabled(string $xTwilioWebhookEnabled) : self
    {
        $this->options['xTwilioWebhookEnabled'] = $xTwilioWebhookEnabled;
        return $this;
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        $options = \http_build_query(Values::of($this->options), '', ' ');
        return '[Twilio.Conversations.V1.CreateMessageOptions ' . $options . ']';
    }
}
class UpdateMessageOptions extends Options
{
    /**
     * @param string $author The channel specific identifier of the message's
     *                       author.
     * @param string $body The content of the message.
     * @param \DateTime $dateCreated The date that this resource was created.
     * @param \DateTime $dateUpdated The date that this resource was last updated.
     * @param string $attributes A string metadata field you can use to store any
     *                           data you wish.
     * @param string $xTwilioWebhookEnabled The X-Twilio-Webhook-Enabled HTTP
     *                                      request header
     */
    public function __construct(string $author = Values::NONE, string $body = Values::NONE, \DateTime $dateCreated = Values::NONE, \DateTime $dateUpdated = Values::NONE, string $attributes = Values::NONE, string $xTwilioWebhookEnabled = Values::NONE)
    {
        $this->options['author'] = $author;
        $this->options['body'] = $body;
        $this->options['dateCreated'] = $dateCreated;
        $this->options['dateUpdated'] = $dateUpdated;
        $this->options['attributes'] = $attributes;
        $this->options['xTwilioWebhookEnabled'] = $xTwilioWebhookEnabled;
    }
    /**
     * The channel specific identifier of the message's author. Defaults to `system`.
     *
     * @param string $author The channel specific identifier of the message's
     *                       author.
     * @return $this Fluent Builder
     */
    public function setAuthor(string $author) : self
    {
        $this->options['author'] = $author;
        return $this;
    }
    /**
     * The content of the message, can be up to 1,600 characters long.
     *
     * @param string $body The content of the message.
     * @return $this Fluent Builder
     */
    public function setBody(string $body) : self
    {
        $this->options['body'] = $body;
        return $this;
    }
    /**
     * The date that this resource was created.
     *
     * @param \DateTime $dateCreated The date that this resource was created.
     * @return $this Fluent Builder
     */
    public function setDateCreated(\DateTime $dateCreated) : self
    {
        $this->options['dateCreated'] = $dateCreated;
        return $this;
    }
    /**
     * The date that this resource was last updated. `null` if the message has not been edited.
     *
     * @param \DateTime $dateUpdated The date that this resource was last updated.
     * @return $this Fluent Builder
     */
    public function setDateUpdated(\DateTime $dateUpdated) : self
    {
        $this->options['dateUpdated'] = $dateUpdated;
        return $this;
    }
    /**
     * A string metadata field you can use to store any data you wish. The string value must contain structurally valid JSON if specified.  **Note** that if the attributes are not set "{}" will be returned.
     *
     * @param string $attributes A string metadata field you can use to store any
     *                           data you wish.
     * @return $this Fluent Builder
     */
    public function setAttributes(string $attributes) : self
    {
        $this->options['attributes'] = $attributes;
        return $this;
    }
    /**
     * The X-Twilio-Webhook-Enabled HTTP request header
     *
     * @param string $xTwilioWebhookEnabled The X-Twilio-Webhook-Enabled HTTP
     *                                      request header
     * @return $this Fluent Builder
     */
    public function setXTwilioWebhookEnabled(string $xTwilioWebhookEnabled) : self
    {
        $this->options['xTwilioWebhookEnabled'] = $xTwilioWebhookEnabled;
        return $this;
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        $options = \http_build_query(Values::of($this->options), '', ' ');
        return '[Twilio.Conversations.V1.UpdateMessageOptions ' . $options . ']';
    }
}
class DeleteMessageOptions extends Options
{
    /**
     * @param string $xTwilioWebhookEnabled The X-Twilio-Webhook-Enabled HTTP
     *                                      request header
     */
    public function __construct(string $xTwilioWebhookEnabled = Values::NONE)
    {
        $this->options['xTwilioWebhookEnabled'] = $xTwilioWebhookEnabled;
    }
    /**
     * The X-Twilio-Webhook-Enabled HTTP request header
     *
     * @param string $xTwilioWebhookEnabled The X-Twilio-Webhook-Enabled HTTP
     *                                      request header
     * @return $this Fluent Builder
     */
    public function setXTwilioWebhookEnabled(string $xTwilioWebhookEnabled) : self
    {
        $this->options['xTwilioWebhookEnabled'] = $xTwilioWebhookEnabled;
        return $this;
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        $options = \http_build_query(Values::of($this->options), '', ' ');
        return '[Twilio.Conversations.V1.DeleteMessageOptions ' . $options . ']';
    }
}
class ReadMessageOptions extends Options
{
    /**
     * @param string $order The sort order of the returned messages
     */
    public function __construct(string $order = Values::NONE)
    {
        $this->options['order'] = $order;
    }
    /**
     * The sort order of the returned messages. Can be: `asc` (ascending) or `desc` (descending), with `asc` as the default.
     *
     * @param string $order The sort order of the returned messages
     * @return $this Fluent Builder
     */
    public function setOrder(string $order) : self
    {
        $this->options['order'] = $order;
        return $this;
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        $options = \http_build_query(Values::of($this->options), '', ' ');
        return '[Twilio.Conversations.V1.ReadMessageOptions ' . $options . ']';
    }
}
