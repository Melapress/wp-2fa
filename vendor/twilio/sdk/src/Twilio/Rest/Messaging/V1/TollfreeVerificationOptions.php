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
/**
 * PLEASE NOTE that this class contains beta products that are subject to change. Use them with caution.
 */
abstract class TollfreeVerificationOptions
{
    /**
     * @param string $tollfreePhoneNumberSid The SID of the Phone Number associated
     *                                       with the Tollfree Verification
     * @param string $status The compliance status of the Tollfree Verification
     *                       record.
     * @return ReadTollfreeVerificationOptions Options builder
     */
    public static function read(string $tollfreePhoneNumberSid = Values::NONE, string $status = Values::NONE) : ReadTollfreeVerificationOptions
    {
        return new ReadTollfreeVerificationOptions($tollfreePhoneNumberSid, $status);
    }
    /**
     * @param string $customerProfileSid Customer's Profile Bundle BundleSid
     * @param string $businessStreetAddress The address of the business or
     *                                      organization using the Tollfree number
     * @param string $businessStreetAddress2 The address of the business or
     *                                       organization using the Tollfree number
     * @param string $businessCity The city of the business or organization using
     *                             the Tollfree number
     * @param string $businessStateProvinceRegion The state/province/region of the
     *                                            business or organization using
     *                                            the Tollfree number
     * @param string $businessPostalCode The postal code of the business or
     *                                   organization using the Tollfree number
     * @param string $businessCountry The country of the business or organization
     *                                using the Tollfree number
     * @param string $additionalInformation Additional information to be provided
     *                                      for verification
     * @param string $businessContactFirstName The first name of the contact for
     *                                         the business or organization using
     *                                         the Tollfree number
     * @param string $businessContactLastName The last name of the contact for the
     *                                        business or organization using the
     *                                        Tollfree number
     * @param string $businessContactEmail The email address of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @param string $businessContactPhone The phone number of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @param string $externalReferenceId An optional external reference ID
     *                                    supplied by customer and echoed back on
     *                                    status retrieval
     * @return CreateTollfreeVerificationOptions Options builder
     */
    public static function create(string $customerProfileSid = Values::NONE, string $businessStreetAddress = Values::NONE, string $businessStreetAddress2 = Values::NONE, string $businessCity = Values::NONE, string $businessStateProvinceRegion = Values::NONE, string $businessPostalCode = Values::NONE, string $businessCountry = Values::NONE, string $additionalInformation = Values::NONE, string $businessContactFirstName = Values::NONE, string $businessContactLastName = Values::NONE, string $businessContactEmail = Values::NONE, string $businessContactPhone = Values::NONE, string $externalReferenceId = Values::NONE) : CreateTollfreeVerificationOptions
    {
        return new CreateTollfreeVerificationOptions($customerProfileSid, $businessStreetAddress, $businessStreetAddress2, $businessCity, $businessStateProvinceRegion, $businessPostalCode, $businessCountry, $additionalInformation, $businessContactFirstName, $businessContactLastName, $businessContactEmail, $businessContactPhone, $externalReferenceId);
    }
    /**
     * @param string $businessName The name of the business or organization using
     *                             the Tollfree number
     * @param string $businessWebsite The website of the business or organization
     *                                using the Tollfree number
     * @param string $notificationEmail The email address to receive the
     *                                  notification about the verification result.
     * @param string[] $useCaseCategories The category of the use case for the
     *                                    Tollfree Number. List as many are
     *                                    applicable.
     * @param string $useCaseSummary Further explaination on how messaging is used
     *                               by the business or organization
     * @param string $productionMessageSample An example of message content, i.e. a
     *                                        sample message
     * @param string[] $optInImageUrls Link to an image that shows the opt-in
     *                                 workflow. Multiple images allowed and must
     *                                 be a publicly hosted URL
     * @param string $optInType Describe how a user opts-in to text messages
     * @param string $messageVolume Estimate monthly volume of messages from the
     *                              Tollfree Number
     * @param string $businessStreetAddress The address of the business or
     *                                      organization using the Tollfree number
     * @param string $businessStreetAddress2 The address of the business or
     *                                       organization using the Tollfree number
     * @param string $businessCity The city of the business or organization using
     *                             the Tollfree number
     * @param string $businessStateProvinceRegion The state/province/region of the
     *                                            business or organization using
     *                                            the Tollfree number
     * @param string $businessPostalCode The postal code of the business or
     *                                   organization using the Tollfree number
     * @param string $businessCountry The country of the business or organization
     *                                using the Tollfree number
     * @param string $additionalInformation Additional information to be provided
     *                                      for verification
     * @param string $businessContactFirstName The first name of the contact for
     *                                         the business or organization using
     *                                         the Tollfree number
     * @param string $businessContactLastName The last name of the contact for the
     *                                        business or organization using the
     *                                        Tollfree number
     * @param string $businessContactEmail The email address of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @param string $businessContactPhone The phone number of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @return UpdateTollfreeVerificationOptions Options builder
     */
    public static function update(string $businessName = Values::NONE, string $businessWebsite = Values::NONE, string $notificationEmail = Values::NONE, array $useCaseCategories = Values::ARRAY_NONE, string $useCaseSummary = Values::NONE, string $productionMessageSample = Values::NONE, array $optInImageUrls = Values::ARRAY_NONE, string $optInType = Values::NONE, string $messageVolume = Values::NONE, string $businessStreetAddress = Values::NONE, string $businessStreetAddress2 = Values::NONE, string $businessCity = Values::NONE, string $businessStateProvinceRegion = Values::NONE, string $businessPostalCode = Values::NONE, string $businessCountry = Values::NONE, string $additionalInformation = Values::NONE, string $businessContactFirstName = Values::NONE, string $businessContactLastName = Values::NONE, string $businessContactEmail = Values::NONE, string $businessContactPhone = Values::NONE) : UpdateTollfreeVerificationOptions
    {
        return new UpdateTollfreeVerificationOptions($businessName, $businessWebsite, $notificationEmail, $useCaseCategories, $useCaseSummary, $productionMessageSample, $optInImageUrls, $optInType, $messageVolume, $businessStreetAddress, $businessStreetAddress2, $businessCity, $businessStateProvinceRegion, $businessPostalCode, $businessCountry, $additionalInformation, $businessContactFirstName, $businessContactLastName, $businessContactEmail, $businessContactPhone);
    }
}
class ReadTollfreeVerificationOptions extends Options
{
    /**
     * @param string $tollfreePhoneNumberSid The SID of the Phone Number associated
     *                                       with the Tollfree Verification
     * @param string $status The compliance status of the Tollfree Verification
     *                       record.
     */
    public function __construct(string $tollfreePhoneNumberSid = Values::NONE, string $status = Values::NONE)
    {
        $this->options['tollfreePhoneNumberSid'] = $tollfreePhoneNumberSid;
        $this->options['status'] = $status;
    }
    /**
     * The SID of the Phone Number associated with the Tollfree Verification.
     *
     * @param string $tollfreePhoneNumberSid The SID of the Phone Number associated
     *                                       with the Tollfree Verification
     * @return $this Fluent Builder
     */
    public function setTollfreePhoneNumberSid(string $tollfreePhoneNumberSid) : self
    {
        $this->options['tollfreePhoneNumberSid'] = $tollfreePhoneNumberSid;
        return $this;
    }
    /**
     * The compliance status of the Tollfree Verification record.
     *
     * @param string $status The compliance status of the Tollfree Verification
     *                       record.
     * @return $this Fluent Builder
     */
    public function setStatus(string $status) : self
    {
        $this->options['status'] = $status;
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
        return '[Twilio.Messaging.V1.ReadTollfreeVerificationOptions ' . $options . ']';
    }
}
class CreateTollfreeVerificationOptions extends Options
{
    /**
     * @param string $customerProfileSid Customer's Profile Bundle BundleSid
     * @param string $businessStreetAddress The address of the business or
     *                                      organization using the Tollfree number
     * @param string $businessStreetAddress2 The address of the business or
     *                                       organization using the Tollfree number
     * @param string $businessCity The city of the business or organization using
     *                             the Tollfree number
     * @param string $businessStateProvinceRegion The state/province/region of the
     *                                            business or organization using
     *                                            the Tollfree number
     * @param string $businessPostalCode The postal code of the business or
     *                                   organization using the Tollfree number
     * @param string $businessCountry The country of the business or organization
     *                                using the Tollfree number
     * @param string $additionalInformation Additional information to be provided
     *                                      for verification
     * @param string $businessContactFirstName The first name of the contact for
     *                                         the business or organization using
     *                                         the Tollfree number
     * @param string $businessContactLastName The last name of the contact for the
     *                                        business or organization using the
     *                                        Tollfree number
     * @param string $businessContactEmail The email address of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @param string $businessContactPhone The phone number of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @param string $externalReferenceId An optional external reference ID
     *                                    supplied by customer and echoed back on
     *                                    status retrieval
     */
    public function __construct(string $customerProfileSid = Values::NONE, string $businessStreetAddress = Values::NONE, string $businessStreetAddress2 = Values::NONE, string $businessCity = Values::NONE, string $businessStateProvinceRegion = Values::NONE, string $businessPostalCode = Values::NONE, string $businessCountry = Values::NONE, string $additionalInformation = Values::NONE, string $businessContactFirstName = Values::NONE, string $businessContactLastName = Values::NONE, string $businessContactEmail = Values::NONE, string $businessContactPhone = Values::NONE, string $externalReferenceId = Values::NONE)
    {
        $this->options['customerProfileSid'] = $customerProfileSid;
        $this->options['businessStreetAddress'] = $businessStreetAddress;
        $this->options['businessStreetAddress2'] = $businessStreetAddress2;
        $this->options['businessCity'] = $businessCity;
        $this->options['businessStateProvinceRegion'] = $businessStateProvinceRegion;
        $this->options['businessPostalCode'] = $businessPostalCode;
        $this->options['businessCountry'] = $businessCountry;
        $this->options['additionalInformation'] = $additionalInformation;
        $this->options['businessContactFirstName'] = $businessContactFirstName;
        $this->options['businessContactLastName'] = $businessContactLastName;
        $this->options['businessContactEmail'] = $businessContactEmail;
        $this->options['businessContactPhone'] = $businessContactPhone;
        $this->options['externalReferenceId'] = $externalReferenceId;
    }
    /**
     * Customer's Profile Bundle BundleSid.
     *
     * @param string $customerProfileSid Customer's Profile Bundle BundleSid
     * @return $this Fluent Builder
     */
    public function setCustomerProfileSid(string $customerProfileSid) : self
    {
        $this->options['customerProfileSid'] = $customerProfileSid;
        return $this;
    }
    /**
     * The address of the business or organization using the Tollfree number.
     *
     * @param string $businessStreetAddress The address of the business or
     *                                      organization using the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessStreetAddress(string $businessStreetAddress) : self
    {
        $this->options['businessStreetAddress'] = $businessStreetAddress;
        return $this;
    }
    /**
     * The address of the business or organization using the Tollfree number.
     *
     * @param string $businessStreetAddress2 The address of the business or
     *                                       organization using the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessStreetAddress2(string $businessStreetAddress2) : self
    {
        $this->options['businessStreetAddress2'] = $businessStreetAddress2;
        return $this;
    }
    /**
     * The city of the business or organization using the Tollfree number.
     *
     * @param string $businessCity The city of the business or organization using
     *                             the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessCity(string $businessCity) : self
    {
        $this->options['businessCity'] = $businessCity;
        return $this;
    }
    /**
     * The state/province/region of the business or organization using the Tollfree number.
     *
     * @param string $businessStateProvinceRegion The state/province/region of the
     *                                            business or organization using
     *                                            the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessStateProvinceRegion(string $businessStateProvinceRegion) : self
    {
        $this->options['businessStateProvinceRegion'] = $businessStateProvinceRegion;
        return $this;
    }
    /**
     * The postal code of the business or organization using the Tollfree number.
     *
     * @param string $businessPostalCode The postal code of the business or
     *                                   organization using the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessPostalCode(string $businessPostalCode) : self
    {
        $this->options['businessPostalCode'] = $businessPostalCode;
        return $this;
    }
    /**
     * The country of the business or organization using the Tollfree number.
     *
     * @param string $businessCountry The country of the business or organization
     *                                using the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessCountry(string $businessCountry) : self
    {
        $this->options['businessCountry'] = $businessCountry;
        return $this;
    }
    /**
     * Additional information to be provided for verification.
     *
     * @param string $additionalInformation Additional information to be provided
     *                                      for verification
     * @return $this Fluent Builder
     */
    public function setAdditionalInformation(string $additionalInformation) : self
    {
        $this->options['additionalInformation'] = $additionalInformation;
        return $this;
    }
    /**
     * The first name of the contact for the business or organization using the Tollfree number.
     *
     * @param string $businessContactFirstName The first name of the contact for
     *                                         the business or organization using
     *                                         the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessContactFirstName(string $businessContactFirstName) : self
    {
        $this->options['businessContactFirstName'] = $businessContactFirstName;
        return $this;
    }
    /**
     * The last name of the contact for the business or organization using the Tollfree number.
     *
     * @param string $businessContactLastName The last name of the contact for the
     *                                        business or organization using the
     *                                        Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessContactLastName(string $businessContactLastName) : self
    {
        $this->options['businessContactLastName'] = $businessContactLastName;
        return $this;
    }
    /**
     * The email address of the contact for the business or organization using the Tollfree number.
     *
     * @param string $businessContactEmail The email address of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessContactEmail(string $businessContactEmail) : self
    {
        $this->options['businessContactEmail'] = $businessContactEmail;
        return $this;
    }
    /**
     * The phone number of the contact for the business or organization using the Tollfree number.
     *
     * @param string $businessContactPhone The phone number of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessContactPhone(string $businessContactPhone) : self
    {
        $this->options['businessContactPhone'] = $businessContactPhone;
        return $this;
    }
    /**
     * An optional external reference ID supplied by customer and echoed back on status retrieval.
     *
     * @param string $externalReferenceId An optional external reference ID
     *                                    supplied by customer and echoed back on
     *                                    status retrieval
     * @return $this Fluent Builder
     */
    public function setExternalReferenceId(string $externalReferenceId) : self
    {
        $this->options['externalReferenceId'] = $externalReferenceId;
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
        return '[Twilio.Messaging.V1.CreateTollfreeVerificationOptions ' . $options . ']';
    }
}
class UpdateTollfreeVerificationOptions extends Options
{
    /**
     * @param string $businessName The name of the business or organization using
     *                             the Tollfree number
     * @param string $businessWebsite The website of the business or organization
     *                                using the Tollfree number
     * @param string $notificationEmail The email address to receive the
     *                                  notification about the verification result.
     * @param string[] $useCaseCategories The category of the use case for the
     *                                    Tollfree Number. List as many are
     *                                    applicable.
     * @param string $useCaseSummary Further explaination on how messaging is used
     *                               by the business or organization
     * @param string $productionMessageSample An example of message content, i.e. a
     *                                        sample message
     * @param string[] $optInImageUrls Link to an image that shows the opt-in
     *                                 workflow. Multiple images allowed and must
     *                                 be a publicly hosted URL
     * @param string $optInType Describe how a user opts-in to text messages
     * @param string $messageVolume Estimate monthly volume of messages from the
     *                              Tollfree Number
     * @param string $businessStreetAddress The address of the business or
     *                                      organization using the Tollfree number
     * @param string $businessStreetAddress2 The address of the business or
     *                                       organization using the Tollfree number
     * @param string $businessCity The city of the business or organization using
     *                             the Tollfree number
     * @param string $businessStateProvinceRegion The state/province/region of the
     *                                            business or organization using
     *                                            the Tollfree number
     * @param string $businessPostalCode The postal code of the business or
     *                                   organization using the Tollfree number
     * @param string $businessCountry The country of the business or organization
     *                                using the Tollfree number
     * @param string $additionalInformation Additional information to be provided
     *                                      for verification
     * @param string $businessContactFirstName The first name of the contact for
     *                                         the business or organization using
     *                                         the Tollfree number
     * @param string $businessContactLastName The last name of the contact for the
     *                                        business or organization using the
     *                                        Tollfree number
     * @param string $businessContactEmail The email address of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @param string $businessContactPhone The phone number of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     */
    public function __construct(string $businessName = Values::NONE, string $businessWebsite = Values::NONE, string $notificationEmail = Values::NONE, array $useCaseCategories = Values::ARRAY_NONE, string $useCaseSummary = Values::NONE, string $productionMessageSample = Values::NONE, array $optInImageUrls = Values::ARRAY_NONE, string $optInType = Values::NONE, string $messageVolume = Values::NONE, string $businessStreetAddress = Values::NONE, string $businessStreetAddress2 = Values::NONE, string $businessCity = Values::NONE, string $businessStateProvinceRegion = Values::NONE, string $businessPostalCode = Values::NONE, string $businessCountry = Values::NONE, string $additionalInformation = Values::NONE, string $businessContactFirstName = Values::NONE, string $businessContactLastName = Values::NONE, string $businessContactEmail = Values::NONE, string $businessContactPhone = Values::NONE)
    {
        $this->options['businessName'] = $businessName;
        $this->options['businessWebsite'] = $businessWebsite;
        $this->options['notificationEmail'] = $notificationEmail;
        $this->options['useCaseCategories'] = $useCaseCategories;
        $this->options['useCaseSummary'] = $useCaseSummary;
        $this->options['productionMessageSample'] = $productionMessageSample;
        $this->options['optInImageUrls'] = $optInImageUrls;
        $this->options['optInType'] = $optInType;
        $this->options['messageVolume'] = $messageVolume;
        $this->options['businessStreetAddress'] = $businessStreetAddress;
        $this->options['businessStreetAddress2'] = $businessStreetAddress2;
        $this->options['businessCity'] = $businessCity;
        $this->options['businessStateProvinceRegion'] = $businessStateProvinceRegion;
        $this->options['businessPostalCode'] = $businessPostalCode;
        $this->options['businessCountry'] = $businessCountry;
        $this->options['additionalInformation'] = $additionalInformation;
        $this->options['businessContactFirstName'] = $businessContactFirstName;
        $this->options['businessContactLastName'] = $businessContactLastName;
        $this->options['businessContactEmail'] = $businessContactEmail;
        $this->options['businessContactPhone'] = $businessContactPhone;
    }
    /**
     * The name of the business or organization using the Tollfree number.
     *
     * @param string $businessName The name of the business or organization using
     *                             the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessName(string $businessName) : self
    {
        $this->options['businessName'] = $businessName;
        return $this;
    }
    /**
     * The website of the business or organization using the Tollfree number.
     *
     * @param string $businessWebsite The website of the business or organization
     *                                using the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessWebsite(string $businessWebsite) : self
    {
        $this->options['businessWebsite'] = $businessWebsite;
        return $this;
    }
    /**
     * The email address to receive the notification about the verification result. .
     *
     * @param string $notificationEmail The email address to receive the
     *                                  notification about the verification result.
     * @return $this Fluent Builder
     */
    public function setNotificationEmail(string $notificationEmail) : self
    {
        $this->options['notificationEmail'] = $notificationEmail;
        return $this;
    }
    /**
     * The category of the use case for the Tollfree Number. List as many are applicable..
     *
     * @param string[] $useCaseCategories The category of the use case for the
     *                                    Tollfree Number. List as many are
     *                                    applicable.
     * @return $this Fluent Builder
     */
    public function setUseCaseCategories(array $useCaseCategories) : self
    {
        $this->options['useCaseCategories'] = $useCaseCategories;
        return $this;
    }
    /**
     * Use this to further explain how messaging is used by the business or organization.
     *
     * @param string $useCaseSummary Further explaination on how messaging is used
     *                               by the business or organization
     * @return $this Fluent Builder
     */
    public function setUseCaseSummary(string $useCaseSummary) : self
    {
        $this->options['useCaseSummary'] = $useCaseSummary;
        return $this;
    }
    /**
     * An example of message content, i.e. a sample message.
     *
     * @param string $productionMessageSample An example of message content, i.e. a
     *                                        sample message
     * @return $this Fluent Builder
     */
    public function setProductionMessageSample(string $productionMessageSample) : self
    {
        $this->options['productionMessageSample'] = $productionMessageSample;
        return $this;
    }
    /**
     * Link to an image that shows the opt-in workflow. Multiple images allowed and must be a publicly hosted URL.
     *
     * @param string[] $optInImageUrls Link to an image that shows the opt-in
     *                                 workflow. Multiple images allowed and must
     *                                 be a publicly hosted URL
     * @return $this Fluent Builder
     */
    public function setOptInImageUrls(array $optInImageUrls) : self
    {
        $this->options['optInImageUrls'] = $optInImageUrls;
        return $this;
    }
    /**
     * Describe how a user opts-in to text messages.
     *
     * @param string $optInType Describe how a user opts-in to text messages
     * @return $this Fluent Builder
     */
    public function setOptInType(string $optInType) : self
    {
        $this->options['optInType'] = $optInType;
        return $this;
    }
    /**
     * Estimate monthly volume of messages from the Tollfree Number.
     *
     * @param string $messageVolume Estimate monthly volume of messages from the
     *                              Tollfree Number
     * @return $this Fluent Builder
     */
    public function setMessageVolume(string $messageVolume) : self
    {
        $this->options['messageVolume'] = $messageVolume;
        return $this;
    }
    /**
     * The address of the business or organization using the Tollfree number.
     *
     * @param string $businessStreetAddress The address of the business or
     *                                      organization using the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessStreetAddress(string $businessStreetAddress) : self
    {
        $this->options['businessStreetAddress'] = $businessStreetAddress;
        return $this;
    }
    /**
     * The address of the business or organization using the Tollfree number.
     *
     * @param string $businessStreetAddress2 The address of the business or
     *                                       organization using the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessStreetAddress2(string $businessStreetAddress2) : self
    {
        $this->options['businessStreetAddress2'] = $businessStreetAddress2;
        return $this;
    }
    /**
     * The city of the business or organization using the Tollfree number.
     *
     * @param string $businessCity The city of the business or organization using
     *                             the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessCity(string $businessCity) : self
    {
        $this->options['businessCity'] = $businessCity;
        return $this;
    }
    /**
     * The state/province/region of the business or organization using the Tollfree number.
     *
     * @param string $businessStateProvinceRegion The state/province/region of the
     *                                            business or organization using
     *                                            the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessStateProvinceRegion(string $businessStateProvinceRegion) : self
    {
        $this->options['businessStateProvinceRegion'] = $businessStateProvinceRegion;
        return $this;
    }
    /**
     * The postal code of the business or organization using the Tollfree number.
     *
     * @param string $businessPostalCode The postal code of the business or
     *                                   organization using the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessPostalCode(string $businessPostalCode) : self
    {
        $this->options['businessPostalCode'] = $businessPostalCode;
        return $this;
    }
    /**
     * The country of the business or organization using the Tollfree number.
     *
     * @param string $businessCountry The country of the business or organization
     *                                using the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessCountry(string $businessCountry) : self
    {
        $this->options['businessCountry'] = $businessCountry;
        return $this;
    }
    /**
     * Additional information to be provided for verification.
     *
     * @param string $additionalInformation Additional information to be provided
     *                                      for verification
     * @return $this Fluent Builder
     */
    public function setAdditionalInformation(string $additionalInformation) : self
    {
        $this->options['additionalInformation'] = $additionalInformation;
        return $this;
    }
    /**
     * The first name of the contact for the business or organization using the Tollfree number.
     *
     * @param string $businessContactFirstName The first name of the contact for
     *                                         the business or organization using
     *                                         the Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessContactFirstName(string $businessContactFirstName) : self
    {
        $this->options['businessContactFirstName'] = $businessContactFirstName;
        return $this;
    }
    /**
     * The last name of the contact for the business or organization using the Tollfree number.
     *
     * @param string $businessContactLastName The last name of the contact for the
     *                                        business or organization using the
     *                                        Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessContactLastName(string $businessContactLastName) : self
    {
        $this->options['businessContactLastName'] = $businessContactLastName;
        return $this;
    }
    /**
     * The email address of the contact for the business or organization using the Tollfree number.
     *
     * @param string $businessContactEmail The email address of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessContactEmail(string $businessContactEmail) : self
    {
        $this->options['businessContactEmail'] = $businessContactEmail;
        return $this;
    }
    /**
     * The phone number of the contact for the business or organization using the Tollfree number.
     *
     * @param string $businessContactPhone The phone number of the contact for the
     *                                     business or organization using the
     *                                     Tollfree number
     * @return $this Fluent Builder
     */
    public function setBusinessContactPhone(string $businessContactPhone) : self
    {
        $this->options['businessContactPhone'] = $businessContactPhone;
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
        return '[Twilio.Messaging.V1.UpdateTollfreeVerificationOptions ' . $options . ']';
    }
}
