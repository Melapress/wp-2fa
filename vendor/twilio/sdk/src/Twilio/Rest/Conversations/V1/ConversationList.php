<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Conversations\V1;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Serialize;
use WP2FA_Vendor\Twilio\Stream;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class ConversationList extends ListResource
{
    /**
     * Construct the ConversationList
     *
     * @param Version $version Version that contains the resource
     */
    public function __construct(Version $version)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = [];
        $this->uri = '/Conversations';
    }
    /**
     * Create the ConversationInstance
     *
     * @param array|Options $options Optional Arguments
     * @return ConversationInstance Created ConversationInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function create(array $options = []) : ConversationInstance
    {
        $options = new Values($options);
        $data = Values::of(['FriendlyName' => $options['friendlyName'], 'UniqueName' => $options['uniqueName'], 'DateCreated' => Serialize::iso8601DateTime($options['dateCreated']), 'DateUpdated' => Serialize::iso8601DateTime($options['dateUpdated']), 'MessagingServiceSid' => $options['messagingServiceSid'], 'Attributes' => $options['attributes'], 'State' => $options['state'], 'Timers.Inactive' => $options['timersInactive'], 'Timers.Closed' => $options['timersClosed']]);
        $headers = Values::of(['X-Twilio-Webhook-Enabled' => $options['xTwilioWebhookEnabled']]);
        $payload = $this->version->create('POST', $this->uri, [], $data, $headers);
        return new ConversationInstance($this->version, $payload);
    }
    /**
     * Streams ConversationInstance records from the API as a generator stream.
     * This operation lazily loads records as efficiently as possible until the
     * limit
     * is reached.
     * The results are returned as a generator, so this operation is memory
     * efficient.
     *
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
    public function stream(int $limit = null, $pageSize = null) : Stream
    {
        $limits = $this->version->readLimits($limit, $pageSize);
        $page = $this->page($limits['pageSize']);
        return $this->version->stream($page, $limits['limit'], $limits['pageLimit']);
    }
    /**
     * Reads ConversationInstance records from the API as a list.
     * Unlike stream(), this operation is eager and will load `limit` records into
     * memory before returning.
     *
     * @param int $limit Upper limit for the number of records to return. read()
     *                   guarantees to never return more than limit.  Default is no
     *                   limit
     * @param mixed $pageSize Number of records to fetch per request, when not set
     *                        will use the default value of 50 records.  If no
     *                        page_size is defined but a limit is defined, read()
     *                        will attempt to read the limit with the most
     *                        efficient page size, i.e. min(limit, 1000)
     * @return ConversationInstance[] Array of results
     */
    public function read(int $limit = null, $pageSize = null) : array
    {
        return \iterator_to_array($this->stream($limit, $pageSize), \false);
    }
    /**
     * Retrieve a single page of ConversationInstance records from the API.
     * Request is executed immediately
     *
     * @param mixed $pageSize Number of records to return, defaults to 50
     * @param string $pageToken PageToken provided by the API
     * @param mixed $pageNumber Page Number, this value is simply for client state
     * @return ConversationPage Page of ConversationInstance
     */
    public function page($pageSize = Values::NONE, string $pageToken = Values::NONE, $pageNumber = Values::NONE) : ConversationPage
    {
        $params = Values::of(['PageToken' => $pageToken, 'Page' => $pageNumber, 'PageSize' => $pageSize]);
        $response = $this->version->page('GET', $this->uri, $params);
        return new ConversationPage($this->version, $response, $this->solution);
    }
    /**
     * Retrieve a specific page of ConversationInstance records from the API.
     * Request is executed immediately
     *
     * @param string $targetUrl API-generated URL for the requested results page
     * @return ConversationPage Page of ConversationInstance
     */
    public function getPage(string $targetUrl) : ConversationPage
    {
        $response = $this->version->getDomain()->getClient()->request('GET', $targetUrl);
        return new ConversationPage($this->version, $response, $this->solution);
    }
    /**
     * Constructs a ConversationContext
     *
     * @param string $sid A 34 character string that uniquely identifies this
     *                    resource.
     */
    public function getContext(string $sid) : ConversationContext
    {
        return new ConversationContext($this->version, $sid);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Conversations.V1.ConversationList]';
    }
}
