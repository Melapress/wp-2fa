<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Preview\Marketplace;

use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
/**
 * PLEASE NOTE that this class contains preview products that are subject to change. Use them with caution. If you currently do not have developer preview access, please contact help@twilio.com.
 */
abstract class InstalledAddOnOptions
{
    /**
     * @param array $configuration The JSON object representing the configuration
     * @param string $uniqueName An application-defined string that uniquely
     *                           identifies the resource
     * @return CreateInstalledAddOnOptions Options builder
     */
    public static function create(array $configuration = Values::ARRAY_NONE, string $uniqueName = Values::NONE) : CreateInstalledAddOnOptions
    {
        return new CreateInstalledAddOnOptions($configuration, $uniqueName);
    }
    /**
     * @param array $configuration The JSON object representing the configuration
     * @param string $uniqueName An application-defined string that uniquely
     *                           identifies the resource
     * @return UpdateInstalledAddOnOptions Options builder
     */
    public static function update(array $configuration = Values::ARRAY_NONE, string $uniqueName = Values::NONE) : UpdateInstalledAddOnOptions
    {
        return new UpdateInstalledAddOnOptions($configuration, $uniqueName);
    }
}
class CreateInstalledAddOnOptions extends Options
{
    /**
     * @param array $configuration The JSON object representing the configuration
     * @param string $uniqueName An application-defined string that uniquely
     *                           identifies the resource
     */
    public function __construct(array $configuration = Values::ARRAY_NONE, string $uniqueName = Values::NONE)
    {
        $this->options['configuration'] = $configuration;
        $this->options['uniqueName'] = $uniqueName;
    }
    /**
     * The JSON object that represents the configuration of the new Add-on being installed.
     *
     * @param array $configuration The JSON object representing the configuration
     * @return $this Fluent Builder
     */
    public function setConfiguration(array $configuration) : self
    {
        $this->options['configuration'] = $configuration;
        return $this;
    }
    /**
     * An application-defined string that uniquely identifies the resource. This value must be unique within the Account.
     *
     * @param string $uniqueName An application-defined string that uniquely
     *                           identifies the resource
     * @return $this Fluent Builder
     */
    public function setUniqueName(string $uniqueName) : self
    {
        $this->options['uniqueName'] = $uniqueName;
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
        return '[Twilio.Preview.Marketplace.CreateInstalledAddOnOptions ' . $options . ']';
    }
}
class UpdateInstalledAddOnOptions extends Options
{
    /**
     * @param array $configuration The JSON object representing the configuration
     * @param string $uniqueName An application-defined string that uniquely
     *                           identifies the resource
     */
    public function __construct(array $configuration = Values::ARRAY_NONE, string $uniqueName = Values::NONE)
    {
        $this->options['configuration'] = $configuration;
        $this->options['uniqueName'] = $uniqueName;
    }
    /**
     * Valid JSON object that conform to the configuration schema exposed by the associated AvailableAddOn resource. This is only required by Add-ons that need to be configured
     *
     * @param array $configuration The JSON object representing the configuration
     * @return $this Fluent Builder
     */
    public function setConfiguration(array $configuration) : self
    {
        $this->options['configuration'] = $configuration;
        return $this;
    }
    /**
     * An application-defined string that uniquely identifies the resource. This value must be unique within the Account.
     *
     * @param string $uniqueName An application-defined string that uniquely
     *                           identifies the resource
     * @return $this Fluent Builder
     */
    public function setUniqueName(string $uniqueName) : self
    {
        $this->options['uniqueName'] = $uniqueName;
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
        return '[Twilio.Preview.Marketplace.UpdateInstalledAddOnOptions ' . $options . ']';
    }
}
