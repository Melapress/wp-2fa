<?php

namespace WP2FA_Vendor\Twilio;

class ListResource
{
    protected $version;
    protected $solution = [];
    protected $uri;
    public function __construct(Version $version)
    {
        $this->version = $version;
    }
    public function __toString() : string
    {
        return '[ListResource]';
    }
}
