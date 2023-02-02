<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */
namespace WP2FA_Vendor\Twilio\TwiML\Voice;

use WP2FA_Vendor\Twilio\TwiML\TwiML;
class Number extends TwiML
{
    /**
     * Number constructor.
     *
     * @param string $phoneNumber Phone Number to dial
     * @param array $attributes Optional attributes
     */
    public function __construct($phoneNumber, $attributes = [])
    {
        parent::__construct('Number', $phoneNumber, $attributes);
    }
    /**
     * Add SendDigits attribute.
     *
     * @param string $sendDigits DTMF tones to play when the call is answered
     */
    public function setSendDigits($sendDigits) : self
    {
        return $this->setAttribute('sendDigits', $sendDigits);
    }
    /**
     * Add Url attribute.
     *
     * @param string $url TwiML URL
     */
    public function setUrl($url) : self
    {
        return $this->setAttribute('url', $url);
    }
    /**
     * Add Method attribute.
     *
     * @param string $method TwiML URL method
     */
    public function setMethod($method) : self
    {
        return $this->setAttribute('method', $method);
    }
    /**
     * Add StatusCallbackEvent attribute.
     *
     * @param string[] $statusCallbackEvent Events to call status callback
     */
    public function setStatusCallbackEvent($statusCallbackEvent) : self
    {
        return $this->setAttribute('statusCallbackEvent', $statusCallbackEvent);
    }
    /**
     * Add StatusCallback attribute.
     *
     * @param string $statusCallback Status callback URL
     */
    public function setStatusCallback($statusCallback) : self
    {
        return $this->setAttribute('statusCallback', $statusCallback);
    }
    /**
     * Add StatusCallbackMethod attribute.
     *
     * @param string $statusCallbackMethod Status callback URL method
     */
    public function setStatusCallbackMethod($statusCallbackMethod) : self
    {
        return $this->setAttribute('statusCallbackMethod', $statusCallbackMethod);
    }
    /**
     * Add Byoc attribute.
     *
     * @param string $byoc BYOC trunk SID (Beta)
     */
    public function setByoc($byoc) : self
    {
        return $this->setAttribute('byoc', $byoc);
    }
    /**
     * Add MachineDetection attribute.
     *
     * @param string $machineDetection Enable machine detection or end of greeting
     *                                 detection
     */
    public function setMachineDetection($machineDetection) : self
    {
        return $this->setAttribute('machineDetection', $machineDetection);
    }
    /**
     * Add AmdStatusCallbackMethod attribute.
     *
     * @param string $amdStatusCallbackMethod HTTP Method to use with
     *                                        amd_status_callback
     */
    public function setAmdStatusCallbackMethod($amdStatusCallbackMethod) : self
    {
        return $this->setAttribute('amdStatusCallbackMethod', $amdStatusCallbackMethod);
    }
    /**
     * Add AmdStatusCallback attribute.
     *
     * @param string $amdStatusCallback The URL we should call to send amd status
     *                                  information to your application
     */
    public function setAmdStatusCallback($amdStatusCallback) : self
    {
        return $this->setAttribute('amdStatusCallback', $amdStatusCallback);
    }
    /**
     * Add MachineDetectionTimeout attribute.
     *
     * @param int $machineDetectionTimeout Number of seconds to wait for machine
     *                                     detection
     */
    public function setMachineDetectionTimeout($machineDetectionTimeout) : self
    {
        return $this->setAttribute('machineDetectionTimeout', $machineDetectionTimeout);
    }
    /**
     * Add MachineDetectionSpeechThreshold attribute.
     *
     * @param int $machineDetectionSpeechThreshold Number of milliseconds for
     *                                             measuring stick for the length
     *                                             of the speech activity
     */
    public function setMachineDetectionSpeechThreshold($machineDetectionSpeechThreshold) : self
    {
        return $this->setAttribute('machineDetectionSpeechThreshold', $machineDetectionSpeechThreshold);
    }
    /**
     * Add MachineDetectionSpeechEndThreshold attribute.
     *
     * @param int $machineDetectionSpeechEndThreshold Number of milliseconds of
     *                                                silence after speech activity
     */
    public function setMachineDetectionSpeechEndThreshold($machineDetectionSpeechEndThreshold) : self
    {
        return $this->setAttribute('machineDetectionSpeechEndThreshold', $machineDetectionSpeechEndThreshold);
    }
    /**
     * Add MachineDetectionSilenceTimeout attribute.
     *
     * @param int $machineDetectionSilenceTimeout Number of milliseconds of initial
     *                                            silence
     */
    public function setMachineDetectionSilenceTimeout($machineDetectionSilenceTimeout) : self
    {
        return $this->setAttribute('machineDetectionSilenceTimeout', $machineDetectionSilenceTimeout);
    }
}
