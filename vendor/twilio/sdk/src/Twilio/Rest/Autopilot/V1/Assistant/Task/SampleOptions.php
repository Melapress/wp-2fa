<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\Rest\Autopilot\V1\Assistant\Task;

use WP2FA_Vendor\Twilio\Options;
use WP2FA_Vendor\Twilio\Values;
/**
 * PLEASE NOTE that this class contains preview products that are subject to change. Use them with caution. If you currently do not have developer preview access, please contact help@twilio.com.
 */
abstract class SampleOptions
{
    /**
     * @param string $language The ISO language-country string that specifies the
     *                         language used for the sample
     * @return ReadSampleOptions Options builder
     */
    public static function read(string $language = Values::NONE) : ReadSampleOptions
    {
        return new ReadSampleOptions($language);
    }
    /**
     * @param string $sourceChannel The communication channel from which the new
     *                              sample was captured
     * @return CreateSampleOptions Options builder
     */
    public static function create(string $sourceChannel = Values::NONE) : CreateSampleOptions
    {
        return new CreateSampleOptions($sourceChannel);
    }
    /**
     * @param string $language The ISO language-country string that specifies the
     *                         language used for the sample
     * @param string $taggedText The text example of how end users might express
     *                           the task
     * @param string $sourceChannel The communication channel from which the sample
     *                              was captured
     * @return UpdateSampleOptions Options builder
     */
    public static function update(string $language = Values::NONE, string $taggedText = Values::NONE, string $sourceChannel = Values::NONE) : UpdateSampleOptions
    {
        return new UpdateSampleOptions($language, $taggedText, $sourceChannel);
    }
}
class ReadSampleOptions extends Options
{
    /**
     * @param string $language The ISO language-country string that specifies the
     *                         language used for the sample
     */
    public function __construct(string $language = Values::NONE)
    {
        $this->options['language'] = $language;
    }
    /**
     * The [ISO language-country](https://docs.oracle.com/cd/E13214_01/wli/docs92/xref/xqisocodes.html) string that specifies the language used for the sample. For example: `en-US`.
     *
     * @param string $language The ISO language-country string that specifies the
     *                         language used for the sample
     * @return $this Fluent Builder
     */
    public function setLanguage(string $language) : self
    {
        $this->options['language'] = $language;
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
        return '[Twilio.Autopilot.V1.ReadSampleOptions ' . $options . ']';
    }
}
class CreateSampleOptions extends Options
{
    /**
     * @param string $sourceChannel The communication channel from which the new
     *                              sample was captured
     */
    public function __construct(string $sourceChannel = Values::NONE)
    {
        $this->options['sourceChannel'] = $sourceChannel;
    }
    /**
     * The communication channel from which the new sample was captured. Can be: `voice`, `sms`, `chat`, `alexa`, `google-assistant`, `slack`, or null if not included.
     *
     * @param string $sourceChannel The communication channel from which the new
     *                              sample was captured
     * @return $this Fluent Builder
     */
    public function setSourceChannel(string $sourceChannel) : self
    {
        $this->options['sourceChannel'] = $sourceChannel;
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
        return '[Twilio.Autopilot.V1.CreateSampleOptions ' . $options . ']';
    }
}
class UpdateSampleOptions extends Options
{
    /**
     * @param string $language The ISO language-country string that specifies the
     *                         language used for the sample
     * @param string $taggedText The text example of how end users might express
     *                           the task
     * @param string $sourceChannel The communication channel from which the sample
     *                              was captured
     */
    public function __construct(string $language = Values::NONE, string $taggedText = Values::NONE, string $sourceChannel = Values::NONE)
    {
        $this->options['language'] = $language;
        $this->options['taggedText'] = $taggedText;
        $this->options['sourceChannel'] = $sourceChannel;
    }
    /**
     * The [ISO language-country](https://docs.oracle.com/cd/E13214_01/wli/docs92/xref/xqisocodes.html) string that specifies the language used for the sample. For example: `en-US`.
     *
     * @param string $language The ISO language-country string that specifies the
     *                         language used for the sample
     * @return $this Fluent Builder
     */
    public function setLanguage(string $language) : self
    {
        $this->options['language'] = $language;
        return $this;
    }
    /**
     * The text example of how end users might express the task. The sample can contain [Field tag blocks](https://www.twilio.com/docs/autopilot/api/task-sample#field-tagging).
     *
     * @param string $taggedText The text example of how end users might express
     *                           the task
     * @return $this Fluent Builder
     */
    public function setTaggedText(string $taggedText) : self
    {
        $this->options['taggedText'] = $taggedText;
        return $this;
    }
    /**
     * The communication channel from which the sample was captured. Can be: `voice`, `sms`, `chat`, `alexa`, `google-assistant`, `slack`, or null if not included.
     *
     * @param string $sourceChannel The communication channel from which the sample
     *                              was captured
     * @return $this Fluent Builder
     */
    public function setSourceChannel(string $sourceChannel) : self
    {
        $this->options['sourceChannel'] = $sourceChannel;
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
        return '[Twilio.Autopilot.V1.UpdateSampleOptions ' . $options . ']';
    }
}
