<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Taskrouter\V1;

use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
abstract class WorkspaceOptions
{
    /**
     * @param string $defaultActivitySid The SID of the Activity that will be used
     *                                   when new Workers are created in the
     *                                   Workspace
     * @param string $eventCallbackUrl The URL we should call when an event occurs
     * @param string $eventsFilter The list of Workspace events for which to call
     *                             event_callback_url
     * @param string $friendlyName A string to describe the Workspace resource
     * @param bool $multiTaskEnabled Whether multi-tasking is enabled
     * @param string $timeoutActivitySid The SID of the Activity that will be
     *                                   assigned to a Worker when a Task
     *                                   reservation times out without a response
     * @param string $prioritizeQueueOrder The type of TaskQueue to prioritize when
     *                                     Workers are receiving Tasks from both
     *                                     types of TaskQueues
     * @return UpdateWorkspaceOptions Options builder
     */
    public static function update(string $defaultActivitySid = Values::NONE, string $eventCallbackUrl = Values::NONE, string $eventsFilter = Values::NONE, string $friendlyName = Values::NONE, bool $multiTaskEnabled = Values::NONE, string $timeoutActivitySid = Values::NONE, string $prioritizeQueueOrder = Values::NONE) : UpdateWorkspaceOptions
    {
        return new UpdateWorkspaceOptions($defaultActivitySid, $eventCallbackUrl, $eventsFilter, $friendlyName, $multiTaskEnabled, $timeoutActivitySid, $prioritizeQueueOrder);
    }
    /**
     * @param string $friendlyName The friendly_name of the Workspace resources to
     *                             read
     * @return ReadWorkspaceOptions Options builder
     */
    public static function read(string $friendlyName = Values::NONE) : ReadWorkspaceOptions
    {
        return new ReadWorkspaceOptions($friendlyName);
    }
    /**
     * @param string $eventCallbackUrl The URL we should call when an event occurs
     * @param string $eventsFilter The list of Workspace events for which to call
     *                             event_callback_url
     * @param bool $multiTaskEnabled Whether multi-tasking is enabled
     * @param string $template An available template name
     * @param string $prioritizeQueueOrder The type of TaskQueue to prioritize when
     *                                     Workers are receiving Tasks from both
     *                                     types of TaskQueues
     * @return CreateWorkspaceOptions Options builder
     */
    public static function create(string $eventCallbackUrl = Values::NONE, string $eventsFilter = Values::NONE, bool $multiTaskEnabled = Values::NONE, string $template = Values::NONE, string $prioritizeQueueOrder = Values::NONE) : CreateWorkspaceOptions
    {
        return new CreateWorkspaceOptions($eventCallbackUrl, $eventsFilter, $multiTaskEnabled, $template, $prioritizeQueueOrder);
    }
}
class UpdateWorkspaceOptions extends Options
{
    /**
     * @param string $defaultActivitySid The SID of the Activity that will be used
     *                                   when new Workers are created in the
     *                                   Workspace
     * @param string $eventCallbackUrl The URL we should call when an event occurs
     * @param string $eventsFilter The list of Workspace events for which to call
     *                             event_callback_url
     * @param string $friendlyName A string to describe the Workspace resource
     * @param bool $multiTaskEnabled Whether multi-tasking is enabled
     * @param string $timeoutActivitySid The SID of the Activity that will be
     *                                   assigned to a Worker when a Task
     *                                   reservation times out without a response
     * @param string $prioritizeQueueOrder The type of TaskQueue to prioritize when
     *                                     Workers are receiving Tasks from both
     *                                     types of TaskQueues
     */
    public function __construct(string $defaultActivitySid = Values::NONE, string $eventCallbackUrl = Values::NONE, string $eventsFilter = Values::NONE, string $friendlyName = Values::NONE, bool $multiTaskEnabled = Values::NONE, string $timeoutActivitySid = Values::NONE, string $prioritizeQueueOrder = Values::NONE)
    {
        $this->options['defaultActivitySid'] = $defaultActivitySid;
        $this->options['eventCallbackUrl'] = $eventCallbackUrl;
        $this->options['eventsFilter'] = $eventsFilter;
        $this->options['friendlyName'] = $friendlyName;
        $this->options['multiTaskEnabled'] = $multiTaskEnabled;
        $this->options['timeoutActivitySid'] = $timeoutActivitySid;
        $this->options['prioritizeQueueOrder'] = $prioritizeQueueOrder;
    }
    /**
     * The SID of the Activity that will be used when new Workers are created in the Workspace.
     *
     * @param string $defaultActivitySid The SID of the Activity that will be used
     *                                   when new Workers are created in the
     *                                   Workspace
     * @return $this Fluent Builder
     */
    public function setDefaultActivitySid(string $defaultActivitySid) : self
    {
        $this->options['defaultActivitySid'] = $defaultActivitySid;
        return $this;
    }
    /**
     * The URL we should call when an event occurs. See [Workspace Events](https://www.twilio.com/docs/taskrouter/api/event) for more information. This parameter supports Twilio's [Webhooks (HTTP callbacks) Connection Overrides](https://www.twilio.com/docs/usage/webhooks/webhooks-connection-overrides).
     *
     * @param string $eventCallbackUrl The URL we should call when an event occurs
     * @return $this Fluent Builder
     */
    public function setEventCallbackUrl(string $eventCallbackUrl) : self
    {
        $this->options['eventCallbackUrl'] = $eventCallbackUrl;
        return $this;
    }
    /**
     * The list of Workspace events for which to call event_callback_url. For example if `EventsFilter=task.created,task.canceled,worker.activity.update`, then TaskRouter will call event_callback_url only when a task is created, canceled, or a Worker activity is updated.
     *
     * @param string $eventsFilter The list of Workspace events for which to call
     *                             event_callback_url
     * @return $this Fluent Builder
     */
    public function setEventsFilter(string $eventsFilter) : self
    {
        $this->options['eventsFilter'] = $eventsFilter;
        return $this;
    }
    /**
     * A descriptive string that you create to describe the Workspace resource. For example: `Sales Call Center` or `Customer Support Team`.
     *
     * @param string $friendlyName A string to describe the Workspace resource
     * @return $this Fluent Builder
     */
    public function setFriendlyName(string $friendlyName) : self
    {
        $this->options['friendlyName'] = $friendlyName;
        return $this;
    }
    /**
     * Whether to enable multi-tasking. Can be: `true` to enable multi-tasking, or `false` to disable it. However, all workspaces should be maintained as multi-tasking. There is no default when omitting this parameter. A multi-tasking Workspace can't be updated to single-tasking unless it is not a Flex Project and another (legacy) single-tasking Workspace exists. Multi-tasking allows Workers to handle multiple Tasks simultaneously. In multi-tasking mode, each Worker can receive parallel reservations up to the per-channel maximums defined in the Workers section. In single-tasking mode (legacy mode), each Worker will only receive a new reservation when the previous task is completed. Learn more at [Multitasking](https://www.twilio.com/docs/taskrouter/multitasking).
     *
     * @param bool $multiTaskEnabled Whether multi-tasking is enabled
     * @return $this Fluent Builder
     */
    public function setMultiTaskEnabled(bool $multiTaskEnabled) : self
    {
        $this->options['multiTaskEnabled'] = $multiTaskEnabled;
        return $this;
    }
    /**
     * The SID of the Activity that will be assigned to a Worker when a Task reservation times out without a response.
     *
     * @param string $timeoutActivitySid The SID of the Activity that will be
     *                                   assigned to a Worker when a Task
     *                                   reservation times out without a response
     * @return $this Fluent Builder
     */
    public function setTimeoutActivitySid(string $timeoutActivitySid) : self
    {
        $this->options['timeoutActivitySid'] = $timeoutActivitySid;
        return $this;
    }
    /**
     * The type of TaskQueue to prioritize when Workers are receiving Tasks from both types of TaskQueues. Can be: `LIFO` or `FIFO`. For more information, see [Queue Ordering](https://www.twilio.com/docs/taskrouter/queue-ordering-last-first-out-lifo).
     *
     * @param string $prioritizeQueueOrder The type of TaskQueue to prioritize when
     *                                     Workers are receiving Tasks from both
     *                                     types of TaskQueues
     * @return $this Fluent Builder
     */
    public function setPrioritizeQueueOrder(string $prioritizeQueueOrder) : self
    {
        $this->options['prioritizeQueueOrder'] = $prioritizeQueueOrder;
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
        return '[Twilio.Taskrouter.V1.UpdateWorkspaceOptions ' . $options . ']';
    }
}
class ReadWorkspaceOptions extends Options
{
    /**
     * @param string $friendlyName The friendly_name of the Workspace resources to
     *                             read
     */
    public function __construct(string $friendlyName = Values::NONE)
    {
        $this->options['friendlyName'] = $friendlyName;
    }
    /**
     * The `friendly_name` of the Workspace resources to read. For example `Customer Support` or `2014 Election Campaign`.
     *
     * @param string $friendlyName The friendly_name of the Workspace resources to
     *                             read
     * @return $this Fluent Builder
     */
    public function setFriendlyName(string $friendlyName) : self
    {
        $this->options['friendlyName'] = $friendlyName;
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
        return '[Twilio.Taskrouter.V1.ReadWorkspaceOptions ' . $options . ']';
    }
}
class CreateWorkspaceOptions extends Options
{
    /**
     * @param string $eventCallbackUrl The URL we should call when an event occurs
     * @param string $eventsFilter The list of Workspace events for which to call
     *                             event_callback_url
     * @param bool $multiTaskEnabled Whether multi-tasking is enabled
     * @param string $template An available template name
     * @param string $prioritizeQueueOrder The type of TaskQueue to prioritize when
     *                                     Workers are receiving Tasks from both
     *                                     types of TaskQueues
     */
    public function __construct(string $eventCallbackUrl = Values::NONE, string $eventsFilter = Values::NONE, bool $multiTaskEnabled = Values::NONE, string $template = Values::NONE, string $prioritizeQueueOrder = Values::NONE)
    {
        $this->options['eventCallbackUrl'] = $eventCallbackUrl;
        $this->options['eventsFilter'] = $eventsFilter;
        $this->options['multiTaskEnabled'] = $multiTaskEnabled;
        $this->options['template'] = $template;
        $this->options['prioritizeQueueOrder'] = $prioritizeQueueOrder;
    }
    /**
     * The URL we should call when an event occurs. If provided, the Workspace will publish events to this URL, for example, to collect data for reporting. See [Workspace Events](https://www.twilio.com/docs/taskrouter/api/event) for more information. This parameter supports Twilio's [Webhooks (HTTP callbacks) Connection Overrides](https://www.twilio.com/docs/usage/webhooks/webhooks-connection-overrides).
     *
     * @param string $eventCallbackUrl The URL we should call when an event occurs
     * @return $this Fluent Builder
     */
    public function setEventCallbackUrl(string $eventCallbackUrl) : self
    {
        $this->options['eventCallbackUrl'] = $eventCallbackUrl;
        return $this;
    }
    /**
     * The list of Workspace events for which to call event_callback_url. For example, if `EventsFilter=task.created, task.canceled, worker.activity.update`, then TaskRouter will call event_callback_url only when a task is created, canceled, or a Worker activity is updated.
     *
     * @param string $eventsFilter The list of Workspace events for which to call
     *                             event_callback_url
     * @return $this Fluent Builder
     */
    public function setEventsFilter(string $eventsFilter) : self
    {
        $this->options['eventsFilter'] = $eventsFilter;
        return $this;
    }
    /**
     * Whether to enable multi-tasking. Can be: `true` to enable multi-tasking, or `false` to disable it. However, all workspaces should be created as multi-tasking. The default is `true`. Multi-tasking allows Workers to handle multiple Tasks simultaneously. When enabled (`true`), each Worker can receive parallel reservations up to the per-channel maximums defined in the Workers section. In single-tasking mode (legacy mode), each Worker will only receive a new reservation when the previous task is completed. Learn more at [Multitasking](https://www.twilio.com/docs/taskrouter/multitasking).
     *
     * @param bool $multiTaskEnabled Whether multi-tasking is enabled
     * @return $this Fluent Builder
     */
    public function setMultiTaskEnabled(bool $multiTaskEnabled) : self
    {
        $this->options['multiTaskEnabled'] = $multiTaskEnabled;
        return $this;
    }
    /**
     * An available template name. Can be: `NONE` or `FIFO` and the default is `NONE`. Pre-configures the Workspace with the Workflow and Activities specified in the template. `NONE` will create a Workspace with only a set of default activities. `FIFO` will configure TaskRouter with a set of default activities and a single TaskQueue for first-in, first-out distribution, which can be useful when you are getting started with TaskRouter.
     *
     * @param string $template An available template name
     * @return $this Fluent Builder
     */
    public function setTemplate(string $template) : self
    {
        $this->options['template'] = $template;
        return $this;
    }
    /**
     * The type of TaskQueue to prioritize when Workers are receiving Tasks from both types of TaskQueues. Can be: `LIFO` or `FIFO` and the default is `FIFO`. For more information, see [Queue Ordering](https://www.twilio.com/docs/taskrouter/queue-ordering-last-first-out-lifo).
     *
     * @param string $prioritizeQueueOrder The type of TaskQueue to prioritize when
     *                                     Workers are receiving Tasks from both
     *                                     types of TaskQueues
     * @return $this Fluent Builder
     */
    public function setPrioritizeQueueOrder(string $prioritizeQueueOrder) : self
    {
        $this->options['prioritizeQueueOrder'] = $prioritizeQueueOrder;
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
        return '[Twilio.Taskrouter.V1.CreateWorkspaceOptions ' . $options . ']';
    }
}
