<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Trunking\V1\Trunk;

use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
abstract class RecordingOptions
{
    /**
     * @param string $mode The recording mode for the trunk.
     * @param string $trim The recording trim setting for the trunk.
     * @return UpdateRecordingOptions Options builder
     */
    public static function update(string $mode = Values::NONE, string $trim = Values::NONE) : UpdateRecordingOptions
    {
        return new UpdateRecordingOptions($mode, $trim);
    }
}
class UpdateRecordingOptions extends Options
{
    /**
     * @param string $mode The recording mode for the trunk.
     * @param string $trim The recording trim setting for the trunk.
     */
    public function __construct(string $mode = Values::NONE, string $trim = Values::NONE)
    {
        $this->options['mode'] = $mode;
        $this->options['trim'] = $trim;
    }
    /**
     * The recording mode for the trunk. Can be do-not-record (default), record-from-ringing, record-from-answer, record-from-ringing-dual, or record-from-answer-dual.
     *
     * @param string $mode The recording mode for the trunk.
     * @return $this Fluent Builder
     */
    public function setMode(string $mode) : self
    {
        $this->options['mode'] = $mode;
        return $this;
    }
    /**
     * The recording trim setting for the trunk. Can be do-not-trim (default) or trim-silence.
     *
     * @param string $trim The recording trim setting for the trunk.
     * @return $this Fluent Builder
     */
    public function setTrim(string $trim) : self
    {
        $this->options['trim'] = $trim;
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
        return '[Twilio.Trunking.V1.UpdateRecordingOptions ' . $options . ']';
    }
}
