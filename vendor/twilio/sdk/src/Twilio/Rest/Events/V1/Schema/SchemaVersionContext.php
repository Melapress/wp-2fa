<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Events\V1\Schema;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * PLEASE NOTE that this class contains beta products that are subject to change. Use them with caution.
 */
class SchemaVersionContext extends InstanceContext
{
    /**
     * Initialize the SchemaVersionContext
     *
     * @param Version $version Version that contains the resource
     * @param string $id The unique identifier of the schema.
     * @param int $schemaVersion The version of the schema
     */
    public function __construct(Version $version, $id, $schemaVersion)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['id' => $id, 'schemaVersion' => $schemaVersion];
        $this->uri = '/Schemas/' . \rawurlencode($id) . '/Versions/' . \rawurlencode($schemaVersion) . '';
    }
    /**
     * Fetch the SchemaVersionInstance
     *
     * @return SchemaVersionInstance Fetched SchemaVersionInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : SchemaVersionInstance
    {
        $payload = $this->version->fetch('GET', $this->uri);
        return new SchemaVersionInstance($this->version, $payload, $this->solution['id'], $this->solution['schemaVersion']);
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
        return '[Twilio.Events.V1.SchemaVersionContext ' . \implode(' ', $context) . ']';
    }
}
