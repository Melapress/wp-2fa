<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Taskrouter\V1\Workspace\TaskQueue;

use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
abstract class TaskQueueRealTimeStatisticsOptions
{
    /**
     * @param string $taskChannel The TaskChannel for which to fetch statistics
     * @return FetchTaskQueueRealTimeStatisticsOptions Options builder
     */
    public static function fetch(string $taskChannel = Values::NONE) : FetchTaskQueueRealTimeStatisticsOptions
    {
        return new FetchTaskQueueRealTimeStatisticsOptions($taskChannel);
    }
}
class FetchTaskQueueRealTimeStatisticsOptions extends Options
{
    /**
     * @param string $taskChannel The TaskChannel for which to fetch statistics
     */
    public function __construct(string $taskChannel = Values::NONE)
    {
        $this->options['taskChannel'] = $taskChannel;
    }
    /**
     * The TaskChannel for which to fetch statistics. Can be the TaskChannel's SID or its `unique_name`, such as `voice`, `sms`, or `default`.
     *
     * @param string $taskChannel The TaskChannel for which to fetch statistics
     * @return $this Fluent Builder
     */
    public function setTaskChannel(string $taskChannel) : self
    {
        $this->options['taskChannel'] = $taskChannel;
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
        return '[Twilio.Taskrouter.V1.FetchTaskQueueRealTimeStatisticsOptions ' . $options . ']';
    }
}
