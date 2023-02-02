<?php

namespace WP2FA_Vendor\Zxing;

interface Reader
{
    public function decode(BinaryBitmap $image);
    public function reset();
}
