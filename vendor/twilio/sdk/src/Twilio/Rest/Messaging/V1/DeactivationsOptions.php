<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Messaging\V1;

use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
abstract class DeactivationsOptions
{
    /**
     * @param \DateTime $date The date to retrieve deactivated numbers for.
     * @return FetchDeactivationsOptions Options builder
     */
    public static function fetch(\DateTime $date = Values::NONE) : FetchDeactivationsOptions
    {
        return new FetchDeactivationsOptions($date);
    }
}
class FetchDeactivationsOptions extends Options
{
    /**
     * @param \DateTime $date The date to retrieve deactivated numbers for.
     */
    public function __construct(\DateTime $date = Values::NONE)
    {
        $this->options['date'] = $date;
    }
    /**
     * The request will return a list of all United States Phone Numbers that were deactivated on the day specified by this parameter. This date should be specified in YYYY-MM-DD format.
     *
     * @param \DateTime $date The date to retrieve deactivated numbers for.
     * @return $this Fluent Builder
     */
    public function setDate(\DateTime $date) : self
    {
        $this->options['date'] = $date;
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
        return '[Twilio.Messaging.V1.FetchDeactivationsOptions ' . $options . ']';
    }
}
