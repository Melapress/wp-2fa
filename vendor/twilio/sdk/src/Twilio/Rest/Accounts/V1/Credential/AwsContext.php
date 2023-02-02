<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Accounts\V1\Credential;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class AwsContext extends InstanceContext
{
    /**
     * Initialize the AwsContext
     *
     * @param Version $version Version that contains the resource
     * @param string $sid The unique string that identifies the resource
     */
    public function __construct(Version $version, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['sid' => $sid];
        $this->uri = '/Credentials/AWS/' . \rawurlencode($sid) . '';
    }
    /**
     * Fetch the AwsInstance
     *
     * @return AwsInstance Fetched AwsInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : AwsInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new AwsInstance($this->version, $payload, $this->solution['sid']);
    }
    /**
     * Update the AwsInstance
     *
     * @param array|Options $options Optional Arguments
     * @return AwsInstance Updated AwsInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : AwsInstance
    {
        $options = new Values($options);
        $data = Values::of(['FriendlyName' => $options['friendlyName']]);
        $payload = $this->version->update('POST', $this->uri, [], $data);
        return new AwsInstance($this->version, $payload, $this->solution['sid']);
    }
    /**
     * Delete the AwsInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->version->delete('DELETE', $this->uri);
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
        return '[Twilio.Accounts.V1.AwsContext ' . \implode(' ', $context) . ']';
    }
}
