<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\IpMessaging\V1;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Serialize;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class CredentialContext extends InstanceContext
{
    /**
     * Initialize the CredentialContext
     *
     * @param Version $version Version that contains the resource
     * @param string $sid The sid
     */
    public function __construct(Version $version, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['sid' => $sid];
        $this->uri = '/Credentials/' . \rawurlencode($sid) . '';
    }
    /**
     * Fetch the CredentialInstance
     *
     * @return CredentialInstance Fetched CredentialInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : CredentialInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new CredentialInstance($this->version, $payload, $this->solution['sid']);
    }
    /**
     * Update the CredentialInstance
     *
     * @param array|Options $options Optional Arguments
     * @return CredentialInstance Updated CredentialInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : CredentialInstance
    {
        $options = new Values($options);
        $data = Values::of(['FriendlyName' => $options['friendlyName'], 'Certificate' => $options['certificate'], 'PrivateKey' => $options['privateKey'], 'Sandbox' => Serialize::booleanToString($options['sandbox']), 'ApiKey' => $options['apiKey'], 'Secret' => $options['secret']]);
        $payload = $this->version->update('POST', $this->uri, [], $data);
        return new CredentialInstance($this->version, $payload, $this->solution['sid']);
    }
    /**
     * Delete the CredentialInstance
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
        return '[Twilio.IpMessaging.V1.CredentialContext ' . \implode(' ', $context) . ']';
    }
}
