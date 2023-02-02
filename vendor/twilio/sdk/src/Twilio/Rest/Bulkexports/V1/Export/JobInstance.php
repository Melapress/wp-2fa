<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Bulkexports\V1\Export;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceResource;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property string $resourceType
 * @property string $friendlyName
 * @property array $details
 * @property string $startDay
 * @property string $endDay
 * @property string $jobSid
 * @property string $webhookUrl
 * @property string $webhookMethod
 * @property string $email
 * @property string $url
 * @property string $jobQueuePosition
 * @property string $estimatedCompletionTime
 */
class JobInstance extends InstanceResource
{
    /**
     * Initialize the JobInstance
     *
     * @param Version $version Version that contains the resource
     * @param mixed[] $payload The response payload
     * @param string $jobSid The unique string that that we created to identify the
     *                       Bulk Export job
     */
    public function __construct(Version $version, array $payload, string $jobSid = null)
    {
        parent::__construct($version);
        // Marshaled Properties
        $this->properties = ['resourceType' => Values::array_get($payload, 'resource_type'), 'friendlyName' => Values::array_get($payload, 'friendly_name'), 'details' => Values::array_get($payload, 'details'), 'startDay' => Values::array_get($payload, 'start_day'), 'endDay' => Values::array_get($payload, 'end_day'), 'jobSid' => Values::array_get($payload, 'job_sid'), 'webhookUrl' => Values::array_get($payload, 'webhook_url'), 'webhookMethod' => Values::array_get($payload, 'webhook_method'), 'email' => Values::array_get($payload, 'email'), 'url' => Values::array_get($payload, 'url'), 'jobQueuePosition' => Values::array_get($payload, 'job_queue_position'), 'estimatedCompletionTime' => Values::array_get($payload, 'estimated_completion_time')];
        $this->solution = ['jobSid' => $jobSid ?: $this->properties['jobSid']];
    }
    /**
     * Generate an instance context for the instance, the context is capable of
     * performing various actions.  All instance actions are proxied to the context
     *
     * @return JobContext Context for this JobInstance
     */
    protected function proxy() : JobContext
    {
        if (!$this->context) {
            $this->context = new JobContext($this->version, $this->solution['jobSid']);
        }
        return $this->context;
    }
    /**
     * Fetch the JobInstance
     *
     * @return JobInstance Fetched JobInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function fetch() : JobInstance
    {
        return $this->proxy()->fetch();
    }
    /**
     * Delete the JobInstance
     *
     * @return bool True if delete succeeds, false otherwise
     * @throws TwilioException When an HTTP error occurs.
     */
    public function delete() : bool
    {
        return $this->proxy()->delete();
    }
    /**
     * Magic getter to access properties
     *
     * @param string $name Property to access
     * @return mixed The requested property
     * @throws TwilioException For unknown properties
     */
    public function __get(string $name)
    {
        if (\array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        }
        if (\property_exists($this, '_' . $name)) {
            $method = 'get' . \ucfirst($name);
            return $this->{$method}();
        }
        throw new TwilioException('Unknown property: ' . $name);
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
        return '[Twilio.Bulkexports.V1.JobInstance ' . \implode(' ', $context) . ']';
    }
}
