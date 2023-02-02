<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Trunking\V1;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Rest\Trunking\V1\Trunk\CredentialListList;
use WP2FA_Vendor\Twilio\Rest\Trunking\V1\Trunk\IpAccessControlListList;
use WP2FA_Vendor\Twilio\Rest\Trunking\V1\Trunk\OriginationUrlList;
use WP2FA_Vendor\Twilio\Rest\Trunking\V1\Trunk\PhoneNumberList;
use WP2FA_Vendor\Twilio\Rest\Trunking\V1\Trunk\RecordingList;
use WP2FA_Vendor\Twilio\Serialize;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property OriginationUrlList $originationUrls
 * @property CredentialListList $credentialsLists
 * @property IpAccessControlListList $ipAccessControlLists
 * @property PhoneNumberList $phoneNumbers
 * @property RecordingList $recordings
 * @method \Twilio\Rest\Trunking\V1\Trunk\OriginationUrlContext originationUrls(string $sid)
 * @method \Twilio\Rest\Trunking\V1\Trunk\CredentialListContext credentialsLists(string $sid)
 * @method \Twilio\Rest\Trunking\V1\Trunk\IpAccessControlListContext ipAccessControlLists(string $sid)
 * @method \Twilio\Rest\Trunking\V1\Trunk\PhoneNumberContext phoneNumbers(string $sid)
 * @method \Twilio\Rest\Trunking\V1\Trunk\RecordingContext recordings()
 */
class TrunkContext extends InstanceContext
{
    protected $_originationUrls;
    protected $_credentialsLists;
    protected $_ipAccessControlLists;
    protected $_phoneNumbers;
    protected $_recordings;
    /**
     * Initialize the TrunkContext
     *
     * @param Version $version Version that contains the resource
     * @param string $sid The unique string that identifies the resource
     */
    public function __construct(Version $version, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['sid' => $sid];
        $this->uri = '/Trunks/' . \rawurlencode($sid) . '';
    }
    /**
     * Fetch the TrunkInstance
     *
     * @return TrunkInstance Fetched TrunkInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : TrunkInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new TrunkInstance($this->version, $payload, $this->solution['sid']);
    }
    /**
     * Delete the TrunkInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->version->delete('DELETE', $this->uri);
    }
    /**
     * Update the TrunkInstance
     *
     * @param array|Options $options Optional Arguments
     * @return TrunkInstance Updated TrunkInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : TrunkInstance
    {
        $options = new Values($options);
        $data = Values::of(['FriendlyName' => $options['friendlyName'], 'DomainName' => $options['domainName'], 'DisasterRecoveryUrl' => $options['disasterRecoveryUrl'], 'DisasterRecoveryMethod' => $options['disasterRecoveryMethod'], 'TransferMode' => $options['transferMode'], 'Secure' => Serialize::booleanToString($options['secure']), 'CnamLookupEnabled' => Serialize::booleanToString($options['cnamLookupEnabled']), 'TransferCallerId' => $options['transferCallerId']]);
        $payload = $this->version->update('POST', $this->uri, [], $data);
        return new TrunkInstance($this->version, $payload, $this->solution['sid']);
    }
    /**
     * Access the originationUrls
     */
    protected function getOriginationUrls() : OriginationUrlList
    {
        if (!$this->_originationUrls) {
            $this->_originationUrls = new OriginationUrlList($this->version, $this->solution['sid']);
        }
        return $this->_originationUrls;
    }
    /**
     * Access the credentialsLists
     */
    protected function getCredentialsLists() : CredentialListList
    {
        if (!$this->_credentialsLists) {
            $this->_credentialsLists = new CredentialListList($this->version, $this->solution['sid']);
        }
        return $this->_credentialsLists;
    }
    /**
     * Access the ipAccessControlLists
     */
    protected function getIpAccessControlLists() : IpAccessControlListList
    {
        if (!$this->_ipAccessControlLists) {
            $this->_ipAccessControlLists = new IpAccessControlListList($this->version, $this->solution['sid']);
        }
        return $this->_ipAccessControlLists;
    }
    /**
     * Access the phoneNumbers
     */
    protected function getPhoneNumbers() : PhoneNumberList
    {
        if (!$this->_phoneNumbers) {
            $this->_phoneNumbers = new PhoneNumberList($this->version, $this->solution['sid']);
        }
        return $this->_phoneNumbers;
    }
    /**
     * Access the recordings
     */
    protected function getRecordings() : RecordingList
    {
        if (!$this->_recordings) {
            $this->_recordings = new RecordingList($this->version, $this->solution['sid']);
        }
        return $this->_recordings;
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
        return '[Twilio.Trunking.V1.TrunkContext ' . \implode(' ', $context) . ']';
    }
}
