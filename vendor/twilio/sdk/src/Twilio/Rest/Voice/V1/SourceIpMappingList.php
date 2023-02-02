<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Voice\V1;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Stream;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class SourceIpMappingList extends ListResource
{
    /**
     * Construct the SourceIpMappingList
     *
     * @param Version $version Version that contains the resource
     */
    public function __construct(Version $version)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = [];
        $this->uri = '/SourceIpMappings';
    }
    /**
     * Create the SourceIpMappingInstance
     *
     * @param string $ipRecordSid The unique string that identifies an IP Record
     * @param string $sipDomainSid The unique string that identifies a SIP Domain
     * @return SourceIpMappingInstance Created SourceIpMappingInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function create(string $ipRecordSid, string $sipDomainSid) : SourceIpMappingInstance
    {
        $data = Values::of(['IpRecordSid' => $ipRecordSid, 'SipDomainSid' => $sipDomainSid]);
        $payload = $this->version->create('POST', $this->uri, [], $data);
        return new SourceIpMappingInstance($this->version, $payload);
    }
    /**
     * Streams SourceIpMappingInstance records from the API as a generator stream.
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
     * Reads SourceIpMappingInstance records from the API as a list.
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
     * @return SourceIpMappingInstance[] Array of results
     */
    public function read(int $limit = null, $pageSize = null) : array
    {
        return \iterator_to_array($this->stream($limit, $pageSize), \false);
    }
    /**
     * Retrieve a single page of SourceIpMappingInstance records from the API.
     * Request is executed immediately
     *
     * @param mixed $pageSize Number of records to return, defaults to 50
     * @param string $pageToken PageToken provided by the API
     * @param mixed $pageNumber Page Number, this value is simply for client state
     * @return SourceIpMappingPage Page of SourceIpMappingInstance
     */
    public function page($pageSize = Values::NONE, string $pageToken = Values::NONE, $pageNumber = Values::NONE) : SourceIpMappingPage
    {
        $params = Values::of(['PageToken' => $pageToken, 'Page' => $pageNumber, 'PageSize' => $pageSize]);
        $response = $this->version->page('GET', $this->uri, $params);
        return new SourceIpMappingPage($this->version, $response, $this->solution);
    }
    /**
     * Retrieve a specific page of SourceIpMappingInstance records from the API.
     * Request is executed immediately
     *
     * @param string $targetUrl API-generated URL for the requested results page
     * @return SourceIpMappingPage Page of SourceIpMappingInstance
     */
    public function getPage(string $targetUrl) : SourceIpMappingPage
    {
        $response = $this->version->getDomain()->getClient()->request('GET', $targetUrl);
        return new SourceIpMappingPage($this->version, $response, $this->solution);
    }
    /**
     * Constructs a SourceIpMappingContext
     *
     * @param string $sid The unique string that identifies the resource
     */
    public function getContext(string $sid) : SourceIpMappingContext
    {
        return new SourceIpMappingContext($this->version, $sid);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Voice.V1.SourceIpMappingList]';
    }
}
