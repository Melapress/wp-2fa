<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest;

use WP2FA_Vendor\Twilio\Domain;
use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\Rest\Chat\V1;
use WP2FA_Vendor\Twilio\Rest\Chat\V2;
use WP2FA_Vendor\Twilio\Rest\Chat\V3;
/**
 * @property \Twilio\Rest\Chat\V1 $v1
 * @property \Twilio\Rest\Chat\V2 $v2
 * @property \Twilio\Rest\Chat\V3 $v3
 * @property \Twilio\Rest\Chat\V2\CredentialList $credentials
 * @property \Twilio\Rest\Chat\V2\ServiceList $services
 * @property \Twilio\Rest\Chat\V3\ChannelList $channels
 * @method \Twilio\Rest\Chat\V2\CredentialContext credentials(string $sid)
 * @method \Twilio\Rest\Chat\V2\ServiceContext services(string $sid)
 * @method \Twilio\Rest\Chat\V3\ChannelContext channels(string $serviceSid, string $sid)
 */
class Chat extends Domain
{
    protected $_v1;
    protected $_v2;
    protected $_v3;
    /**
     * Construct the Chat Domain
     *
     * @param Client $client Client to communicate with Twilio
     */
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->baseUrl = 'https://chat.twilio.com';
    }
    /**
     * @return V1 Version v1 of chat
     */
    protected function getV1() : V1
    {
        if (!$this->_v1) {
            $this->_v1 = new V1($this);
        }
        return $this->_v1;
    }
    /**
     * @return V2 Version v2 of chat
     */
    protected function getV2() : V2
    {
        if (!$this->_v2) {
            $this->_v2 = new V2($this);
        }
        return $this->_v2;
    }
    /**
     * @return V3 Version v3 of chat
     */
    protected function getV3() : V3
    {
        if (!$this->_v3) {
            $this->_v3 = new V3($this);
        }
        return $this->_v3;
    }
    /**
     * Magic getter to lazy load version
     *
     * @param string $name Version to return
     * @return \Twilio\Version The requested version
     * @throws TwilioException For unknown versions
     */
    public function __get(string $name)
    {
        $method = 'get' . \ucfirst($name);
        if (\method_exists($this, $method)) {
            return $this->{$method}();
        }
        throw new TwilioException('Unknown version ' . $name);
    }
    /**
     * Magic caller to get resource contexts
     *
     * @param string $name Resource to return
     * @param array $arguments Context parameters
     * @return \Twilio\InstanceContext The requested resource context
     * @throws TwilioException For unknown resource
     */
    public function __call(string $name, array $arguments)
    {
        $method = 'context' . \ucfirst($name);
        if (\method_exists($this, $method)) {
            return \call_user_func_array([$this, $method], $arguments);
        }
        throw new TwilioException('Unknown context ' . $name);
    }
    protected function getCredentials() : \WP2FA_Vendor\Twilio\Rest\Chat\V2\CredentialList
    {
        return $this->v2->credentials;
    }
    /**
     * @param string $sid The SID of the Credential resource to fetch
     */
    protected function contextCredentials(string $sid) : \WP2FA_Vendor\Twilio\Rest\Chat\V2\CredentialContext
    {
        return $this->v2->credentials($sid);
    }
    protected function getServices() : \WP2FA_Vendor\Twilio\Rest\Chat\V2\ServiceList
    {
        return $this->v2->services;
    }
    /**
     * @param string $sid The SID of the Service resource to fetch
     */
    protected function contextServices(string $sid) : \WP2FA_Vendor\Twilio\Rest\Chat\V2\ServiceContext
    {
        return $this->v2->services($sid);
    }
    protected function getChannels() : \WP2FA_Vendor\Twilio\Rest\Chat\V3\ChannelList
    {
        return $this->v3->channels;
    }
    /**
     * @param string $serviceSid Service Sid.
     * @param string $sid A string that uniquely identifies this Channel.
     */
    protected function contextChannels(string $serviceSid, string $sid) : \WP2FA_Vendor\Twilio\Rest\Chat\V3\ChannelContext
    {
        return $this->v3->channels($serviceSid, $sid);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Chat]';
    }
}
