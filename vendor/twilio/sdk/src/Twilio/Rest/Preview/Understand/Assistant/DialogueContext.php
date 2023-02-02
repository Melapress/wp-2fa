<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Preview\Understand\Assistant;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * PLEASE NOTE that this class contains preview products that are subject to change. Use them with caution. If you currently do not have developer preview access, please contact help@twilio.com.
 */
class DialogueContext extends InstanceContext
{
    /**
     * Initialize the DialogueContext
     *
     * @param Version $version Version that contains the resource
     * @param string $assistantSid The assistant_sid
     * @param string $sid The sid
     */
    public function __construct(Version $version, $assistantSid, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['assistantSid' => $assistantSid, 'sid' => $sid];
        $this->uri = '/Assistants/' . \rawurlencode($assistantSid) . '/Dialogues/' . \rawurlencode($sid) . '';
    }
    /**
     * Fetch the DialogueInstance
     *
     * @return DialogueInstance Fetched DialogueInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : DialogueInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new DialogueInstance($this->version, $payload, $this->solution['assistantSid'], $this->solution['sid']);
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
        return '[Twilio.Preview.Understand.DialogueContext ' . \implode(' ', $context) . ']';
    }
}
