<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Events\V1;

use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
/**
 * PLEASE NOTE that this class contains beta products that are subject to change. Use them with caution.
 */
abstract class EventTypeOptions
{
    /**
     * @param string $schemaId A string to filter Event Types by schema.
     * @return ReadEventTypeOptions Options builder
     */
    public static function read(string $schemaId = Values::NONE) : ReadEventTypeOptions
    {
        return new ReadEventTypeOptions($schemaId);
    }
}
class ReadEventTypeOptions extends Options
{
    /**
     * @param string $schemaId A string to filter Event Types by schema.
     */
    public function __construct(string $schemaId = Values::NONE)
    {
        $this->options['schemaId'] = $schemaId;
    }
    /**
     * A string parameter filtering the results to return only the Event Types using a given schema.
     *
     * @param string $schemaId A string to filter Event Types by schema.
     * @return $this Fluent Builder
     */
    public function setSchemaId(string $schemaId) : self
    {
        $this->options['schemaId'] = $schemaId;
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
        return '[Twilio.Events.V1.ReadEventTypeOptions ' . $options . ']';
    }
}
