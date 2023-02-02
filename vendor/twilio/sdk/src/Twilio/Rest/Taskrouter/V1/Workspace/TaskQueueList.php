<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Taskrouter\V1\Workspace;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\InstanceContext;
use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Rest\Taskrouter\V1\Workspace\TaskQueue\TaskQueuesStatisticsList;
use WP2FA_Vendor\Twilio\Stream;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
/**
 * @property TaskQueuesStatisticsList $statistics
 */
class TaskQueueList extends ListResource
{
    protected $_statistics = null;
    /**
     * Construct the TaskQueueList
     *
     * @param Version $version Version that contains the resource
     * @param string $workspaceSid The SID of the Workspace that contains the
     *                             TaskQueue
     */
    public function __construct(Version $version, string $workspaceSid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['workspaceSid' => $workspaceSid];
        $this->uri = '/Workspaces/' . \rawurlencode($workspaceSid) . '/TaskQueues';
    }
    /**
     * Streams TaskQueueInstance records from the API as a generator stream.
     * This operation lazily loads records as efficiently as possible until the
     * limit
     * is reached.
     * The results are returned as a generator, so this operation is memory
     * efficient.
     *
     * @param array|Options $options Optional Arguments
     * @param int $limit Upper limit for the number of records to return. stream()
     *                   guarantees to never return more than limit.  Default is no
     *                   limit
     * @param mixed $pageSize Number of records to fetch per request, when not set
     *                        will use the default value of 50 records.  If no
     *                        page_size is defined but a limit is defined, stream()
     *                        will attempt to read the limit with the most
     *                        efficient page size, i.e. min(limit, 1000)
     * @return Stream stream of results
     */
    public function stream(array $options = [], int $limit = null, $pageSize = null) : Stream
    {
        $limits = $this->version->readLimits($limit, $pageSize);
        $page = $this->page($options, $limits['pageSize']);
        return $this->version->stream($page, $limits['limit'], $limits['pageLimit']);
    }
    /**
     * Reads TaskQueueInstance records from the API as a list.
     * Unlike stream(), this operation is eager and will load `limit` records into
     * memory before returning.
     *
     * @param array|Options $options Optional Arguments
     * @param int $limit Upper limit for the number of records to return. read()
     *                   guarantees to never return more than limit.  Default is no
     *                   limit
     * @param mixed $pageSize Number of records to fetch per request, when not set
     *                        will use the default value of 50 records.  If no
     *                        page_size is defined but a limit is defined, read()
     *                        will attempt to read the limit with the most
     *                        efficient page size, i.e. min(limit, 1000)
     * @return TaskQueueInstance[] Array of results
     */
    public function read(array $options = [], int $limit = null, $pageSize = null) : array
    {
        return \iterator_to_array($this->stream($options, $limit, $pageSize), \false);
    }
    /**
     * Retrieve a single page of TaskQueueInstance records from the API.
     * Request is executed immediately
     *
     * @param array|Options $options Optional Arguments
     * @param mixed $pageSize Number of records to return, defaults to 50
     * @param string $pageToken PageToken provided by the API
     * @param mixed $pageNumber Page Number, this value is simply for client state
     * @return TaskQueuePage Page of TaskQueueInstance
     */
    public function page(array $options = [], $pageSize = Values::NONE, string $pageToken = Values::NONE, $pageNumber = Values::NONE) : TaskQueuePage
    {
        $options = new Values($options);
        $params = Values::of(['FriendlyName' => $options['friendlyName'], 'EvaluateWorkerAttributes' => $options['evaluateWorkerAttributes'], 'WorkerSid' => $options['workerSid'], 'Ordering' => $options['ordering'], 'PageToken' => $pageToken, 'Page' => $pageNumber, 'PageSize' => $pageSize]);
        $response = $this->version->page('GET', $this->uri, $params);
        return new TaskQueuePage($this->version, $response, $this->solution);
    }
    /**
     * Retrieve a specific page of TaskQueueInstance records from the API.
     * Request is executed immediately
     *
     * @param string $targetUrl API-generated URL for the requested results page
     * @return TaskQueuePage Page of TaskQueueInstance
     */
    public function getPage(string $targetUrl) : TaskQueuePage
    {
        $response = $this->version->getDomain()->getClient()->request('GET', $targetUrl);
        return new TaskQueuePage($this->version, $response, $this->solution);
    }
    /**
     * Create the TaskQueueInstance
     *
     * @param string $friendlyName A string to describe the resource
     * @param array|Options $options Optional Arguments
     * @return TaskQueueInstance Created TaskQueueInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function create(string $friendlyName, array $options = []) : TaskQueueInstance
    {
        $options = new Values($options);
        $data = Values::of(['FriendlyName' => $friendlyName, 'TargetWorkers' => $options['targetWorkers'], 'MaxReservedWorkers' => $options['maxReservedWorkers'], 'TaskOrder' => $options['taskOrder'], 'ReservationActivitySid' => $options['reservationActivitySid'], 'AssignmentActivitySid' => $options['assignmentActivitySid']]);
        $payload = $this->version->create('POST', $this->uri, [], $data);
        return new TaskQueueInstance($this->version, $payload, $this->solution['workspaceSid']);
    }
    /**
     * Access the statistics
     */
    protected function getStatistics() : TaskQueuesStatisticsList
    {
        if (!$this->_statistics) {
            $this->_statistics = new TaskQueuesStatisticsList($this->version, $this->solution['workspaceSid']);
        }
        return $this->_statistics;
    }
    /**
     * Constructs a TaskQueueContext
     *
     * @param string $sid The SID of the resource to
     */
    public function getContext(string $sid) : TaskQueueContext
    {
        return new TaskQueueContext($this->version, $this->solution['workspaceSid'], $sid);
    }
    /**
     * Magic getter to lazy load subresources
     *
     * @param string $name Subresource to return
     * @return \Twilio\ListResource The requested subresource
     * @throws TwilioException For unknown subresources
     */
    public function __get(string $name)
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
        return '[Twilio.Taskrouter.V1.TaskQueueList]';
    }
}
