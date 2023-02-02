<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Preview\Marketplace;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Rest\Preview\Marketplace\InstalledAddOn\InstalledAddOnExtensionList;
use WP2FA_Vendor\Twilio\Serialize;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * PLEASE NOTE that this class contains preview products that are subject to change. Use them with caution. If you currently do not have developer preview access, please contact help@twilio.com.
 *
 * @property InstalledAddOnExtensionList $extensions
 * @method \Twilio\Rest\Preview\Marketplace\InstalledAddOn\InstalledAddOnExtensionContext extensions(string $sid)
 */
class InstalledAddOnContext extends InstanceContext
{
    protected $_extensions;
    /**
     * Initialize the InstalledAddOnContext
     *
     * @param Version $version Version that contains the resource
     * @param string $sid The SID of the InstalledAddOn resource to fetch
     */
    public function __construct(Version $version, $sid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['sid' => $sid];
        $this->uri = '/InstalledAddOns/' . \rawurlencode($sid) . '';
    }
    /**
     * Delete the InstalledAddOnInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->version->delete('DELETE', $this->uri);
    }
    /**
     * Fetch the InstalledAddOnInstance
     *
     * @return InstalledAddOnInstance Fetched InstalledAddOnInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : InstalledAddOnInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new InstalledAddOnInstance($this->version, $payload, $this->solution['sid']);
    }
    /**
     * Update the InstalledAddOnInstance
     *
     * @param array|Options $options Optional Arguments
     * @return InstalledAddOnInstance Updated InstalledAddOnInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function update(array $options = []) : InstalledAddOnInstance
    {
        $options = new Values($options);
        $data = Values::of(['Configuration' => Serialize::jsonObject($options['configuration']), 'UniqueName' => $options['uniqueName']]);
        $payload = $this->version->update('POST', $this->uri, [], $data);
        return new InstalledAddOnInstance($this->version, $payload, $this->solution['sid']);
    }
    /**
     * Access the extensions
     */
    protected function getExtensions() : InstalledAddOnExtensionList
    {
        if (!$this->_extensions) {
            $this->_extensions = new InstalledAddOnExtensionList($this->version, $this->solution['sid']);
        }
        return $this->_extensions;
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
        return '[Twilio.Preview.Marketplace.InstalledAddOnContext ' . \implode(' ', $context) . ']';
    }
}
