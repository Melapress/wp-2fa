<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\IpMessaging\V1\Service\Channel;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class MessageContext extends InstanceContext
{
    /**
     * Initialize the MessageContext
     *
     * @param Version $version Version that contains the resource
     * @param string $serviceSid The service_sid
     * @param string $channelSid The channel_sid
     * @param string $sid The sid
     */
    public function __construct(Version $version, $serviceSid, $channelSid, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['serviceSid' => $serviceSid, 'channelSid' => $channelSid, 'sid' => $sid];
        $this->uri = '/Services/' . \rawurlencode($serviceSid) . '/Channels/' . \rawurlencode($channelSid) . '/Messages/' . \rawurlencode($sid) . '';
    }
    /**
     * Fetch the MessageInstance
     *
     * @return MessageInstance Fetched MessageInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : MessageInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new MessageInstance($this->version, $payload, $this->solution['serviceSid'], $this->solution['channelSid'], $this->solution['sid']);
    }
    /**
     * Delete the MessageInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->version->delete('DELETE', $this->uri);
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
        $options = new Values($options);
        $data = Values::of(['Body' => $options['body'], 'Attributes' => $options['attributes']]);
        $payload = $this->version->update('POST', $this->uri, [], $data);
        return new MessageInstance($this->version, $payload, $this->solution['serviceSid'], $this->solution['channelSid'], $this->solution['sid']);
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
        return '[Twilio.IpMessaging.V1.MessageContext ' . \implode(' ', $context) . ']';
    }
}
