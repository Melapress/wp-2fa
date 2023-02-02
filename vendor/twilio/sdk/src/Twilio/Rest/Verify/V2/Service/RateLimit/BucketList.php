<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Verify\V2\Service\RateLimit;

use WP2FA_Vendor\Twilio\Exceptions\TwilioException;
use WP2FA_Vendor\Twilio\ListResource;
use WP2FA_Vendor\Twilio\Stream;
use WP2FA_Vendor\Twilio\Values;
use WP2FA_Vendor\Twilio\Version;
class BucketList extends ListResource
{
    /**
     * Construct the BucketList
     *
     * @param Version $version Version that contains the resource
     * @param string $serviceSid The SID of the Service that the resource is
     *                           associated with
     * @param string $rateLimitSid Rate Limit Sid.
     */
    public function __construct(Version $version, string $serviceSid, string $rateLimitSid)
    {
        parent::__construct($version);
        // Path Solution
        $this->solution = ['serviceSid' => $serviceSid, 'rateLimitSid' => $rateLimitSid];
        $this->uri = '/Services/' . \rawurlencode($serviceSid) . '/RateLimits/' . \rawurlencode($rateLimitSid) . '/Buckets';
    }
    /**
     * Create the BucketInstance
     *
     * @param int $max Max number of requests.
     * @param int $interval Number of seconds that the rate limit will be enforced
     *                      over.
     * @return BucketInstance Created BucketInstance
     * @throws TwilioException When an HTTP error occurs.
     */
    public function create(int $max, int $interval) : BucketInstance
    {
        $data = Values::of(['Max' => $max, 'Interval' => $interval]);
        $payload = $this->version->create('POST', $this->uri, [], $data);
        return new BucketInstance($this->version, $payload, $this->solution['serviceSid'], $this->solution['rateLimitSid']);
    }
    /**
     * Streams BucketInstance records from the API as a generator stream.
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
     * Reads BucketInstance records from the API as a list.
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
     * @return BucketInstance[] Array of results
     */
    public function read(int $limit = null, $pageSize = null) : array
    {
        return \iterator_to_array($this->stream($limit, $pageSize), \false);
    }
    /**
     * Retrieve a single page of BucketInstance records from the API.
     * Request is executed immediately
     *
     * @param mixed $pageSize Number of records to return, defaults to 50
     * @param string $pageToken PageToken provided by the API
     * @param mixed $pageNumber Page Number, this value is simply for client state
     * @return BucketPage Page of BucketInstance
     */
    public function page($pageSize = Values::NONE, string $pageToken = Values::NONE, $pageNumber = Values::NONE) : BucketPage
    {
        $params = Values::of(['PageToken' => $pageToken, 'Page' => $pageNumber, 'PageSize' => $pageSize]);
        $response = $this->version->page('GET', $this->uri, $params);
        return new BucketPage($this->version, $response, $this->solution);
    }
    /**
     * Retrieve a specific page of BucketInstance records from the API.
     * Request is executed immediately
     *
     * @param string $targetUrl API-generated URL for the requested results page
     * @return BucketPage Page of BucketInstance
     */
    public function getPage(string $targetUrl) : BucketPage
    {
        $response = $this->version->getDomain()->getClient()->request('GET', $targetUrl);
        return new BucketPage($this->version, $response, $this->solution);
    }
    /**
     * Constructs a BucketContext
     *
     * @param string $sid A string that uniquely identifies this Bucket.
     */
    public function getContext(string $sid) : BucketContext
    {
        return new BucketContext($this->version, $this->solution['serviceSid'], $this->solution['rateLimitSid'], $sid);
    }
    /**
     * Provide a friendly representation
     *
     * @return string Machine friendly representation
     */
    public function __toString() : string
    {
        return '[Twilio.Verify.V2.BucketList]';
    }
}
